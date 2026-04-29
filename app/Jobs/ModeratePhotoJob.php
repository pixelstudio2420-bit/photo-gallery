<?php

namespace App\Jobs;

use App\Models\AdminNotification;
use App\Models\AppSetting;
use App\Models\EventPhoto;
use App\Services\ImageModerationService;
use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Run a single EventPhoto through AWS Rekognition DetectModerationLabels,
 * persist the result, and raise admin/photographer notifications.
 *
 * Dispatched by:
 *   • EventPhoto::created (when a row first becomes `active`)
 *   • ProcessUploadedPhotoJob (end-of-pipeline hook)
 *   • `photos:remoderate` command (manual backfill)
 *
 * Idempotent: running twice on the same photo overwrites the previous
 * decision with the latest scan, but won't duplicate notifications once a
 * terminal (approved/rejected/flagged) state matches the previous one.
 *
 * Failure handling:
 *   • AWS outage / transient errors → service returns "skipped", we keep
 *     moderation_status='pending' so it gets picked up again later.
 *   • Unexpected throw → retry up to 3×, then mark moderation_status='pending'
 *     with a log entry. We NEVER auto-hide a photo because of an infra fault.
 */
class ModeratePhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;
    public int $backoff = 60;

    public function __construct(public int $photoId)
    {
        // Same queue priority as other image jobs so they don't starve this one.
        $this->onQueue('default');
    }

    public function handle(ImageModerationService $moderator): void
    {
        $photo = EventPhoto::find($this->photoId);
        if (!$photo) {
            Log::info("ModeratePhotoJob: photo #{$this->photoId} not found, skipping");
            return;
        }

        // Short-circuits — don't scan what we don't need to.
        if (!$moderator->isEnabled()) {
            $this->markSkipped($photo, 'disabled');
            return;
        }
        if ($this->shouldSkipPhoto($photo)) {
            $this->markSkipped($photo, 'photo_excluded');
            return;
        }

        // Read original image bytes. Prefer the already-processed original;
        // moderation looks at the raw pixels, not watermarked previews.
        $bytes = $this->readOriginalBytes($photo);
        if (!$bytes) {
            Log::warning("ModeratePhotoJob: cannot read bytes for photo #{$photo->id}");
            // Leave it `pending` so the remoderate command picks it up later.
            return;
        }

        $result = $moderator->moderate($bytes);

        // Release memory explicitly — photo JPEGs are multi-MB.
        unset($bytes);

        $this->applyDecision($photo, $result);
    }

    /* ────────── Decision handlers ────────── */

    private function applyDecision(EventPhoto $photo, array $result): void
    {
        $decision = $result['decision'];
        $previous = $photo->moderation_status;

        // Persist. forceFill() because the moderation_* columns aren't in
        // $fillable — they're lifecycle-managed, not user-editable.
        $photo->forceFill([
            'moderation_status' => $decision,
            'moderation_score'  => $result['score'],
            'moderation_labels' => !empty($result['labels']) ? $result['labels'] : null,
        ])->save();

        Log::info("ModeratePhotoJob: photo #{$photo->id} decision={$decision} score={$result['score']}", [
            'categories' => $result['matched_categories'] ?? [],
            'reason'     => $result['reason'] ?? $result['skipped_reason'],
        ]);

        // Notifications are side-effects — only fire on a transition INTO a
        // new state, not on re-confirmation of the same state (idempotency).
        if ($previous === $decision) {
            return;
        }

        match ($decision) {
            ImageModerationService::DECISION_FLAGGED  => $this->notifyAdminFlagged($photo, $result),
            ImageModerationService::DECISION_REJECTED => $this->notifyRejection($photo, $result),
            default => null,
        };
    }

    private function notifyAdminFlagged(EventPhoto $photo, array $result): void
    {
        try {
            $categories = implode(', ', $result['matched_categories'] ?? []);
            AdminNotification::notify(
                type:    'photo_flagged',
                title:   'ภาพรอการตรวจสอบ',
                message: "Event #{$photo->event_id} — คะแนน " . $result['score'] . "% ({$categories})",
                link:    "admin/moderation/{$photo->id}",
                refId:   (string) $photo->id
            );
        } catch (\Throwable $e) {
            Log::warning('ModeratePhotoJob: admin notification failed: ' . $e->getMessage());
        }
    }

    private function notifyRejection(EventPhoto $photo, array $result): void
    {
        // 1) Admin notification (higher priority — auto-reject means high confidence)
        try {
            $categories = implode(', ', $result['matched_categories'] ?? []);
            AdminNotification::notify(
                type:    'photo_rejected',
                title:   'ภาพถูกปฏิเสธอัตโนมัติ',
                message: "Event #{$photo->event_id} — คะแนน " . $result['score'] . "% ({$categories})",
                link:    "admin/moderation/{$photo->id}",
                refId:   (string) $photo->id
            );
        } catch (\Throwable $e) {
            Log::warning('ModeratePhotoJob: admin rejection notification failed: ' . $e->getMessage());
        }

        // 2) Photographer email (if we know who uploaded it + rule enabled)
        $notifyUploader = AppSetting::get('moderation_notify_uploader', '1') === '1';
        if (!$notifyUploader || empty($photo->uploaded_by)) {
            return;
        }

        try {
            $uploader = $photo->uploader; // User belongsTo (table=auth_users)
            if (!$uploader || empty($uploader->email)) {
                return;
            }

            $eventName = optional($photo->event)->name ?? "Event #{$photo->event_id}";

            // Queue rather than send inline — MailService::queue falls back
            // to sync if no queue driver is configured.
            MailService::queue('photoRejected', [
                $uploader->email,
                trim(($uploader->first_name ?? '') . ' ' . ($uploader->last_name ?? '')) ?: 'ช่างภาพ',
                [
                    'photo_id'    => $photo->id,
                    'event_name'  => $eventName,
                    'categories'  => $result['matched_categories'] ?? [],
                    'score'       => $result['score'],
                    'filename'    => $photo->original_filename ?? $photo->filename,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('ModeratePhotoJob: photographer email failed: ' . $e->getMessage());
        }
    }

    /* ────────── Helpers ────────── */

    /**
     * Opt-outs the admin has configured:
     *   • moderation_skip_verified_photographers → trust uploads from approved
     *     photographers (reduces AWS cost for high-volume trusted sources)
     *   • per-event toggle `moderation_enabled` on Event model (future hook)
     */
    private function shouldSkipPhoto(EventPhoto $photo): bool
    {
        // Skip if already in a manual/reviewed state — don't auto-reverse
        // an admin's decision.
        if (in_array($photo->moderation_status, ['skipped'], true) && $photo->moderation_reviewed_at) {
            return true;
        }

        $skipVerified = AppSetting::get('moderation_skip_verified_photographers', '0') === '1';
        if (!$skipVerified) {
            return false;
        }

        $uploader = $photo->uploader;
        if (!$uploader) {
            return false;
        }

        // A "verified photographer" = has an approved Photographer profile.
        $photographer = \App\Models\Photographer::where('user_id', $uploader->id)
            ->where('status', 'approved')
            ->first();

        return $photographer !== null;
    }

    private function readOriginalBytes(EventPhoto $photo): ?string
    {
        try {
            $disk = $photo->storage_disk ?: 'public';
            $path = $photo->original_path;
            if (empty($path)) {
                return null;
            }
            $contents = Storage::disk($disk)->get($path);
            return $contents ?: null;
        } catch (\Throwable $e) {
            Log::warning("ModeratePhotoJob: read error for photo #{$photo->id}: " . $e->getMessage());
            return null;
        }
    }

    private function markSkipped(EventPhoto $photo, string $reason): void
    {
        $photo->forceFill([
            'moderation_status' => ImageModerationService::DECISION_SKIPPED,
            'moderation_score'  => 0,
            'moderation_labels' => ['skipped_reason' => $reason],
        ])->save();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ModeratePhotoJob: photo #{$this->photoId} permanently failed", [
            'error' => $exception->getMessage(),
        ]);

        // Don't auto-reject on infra failure — leave pending so it can retry.
        try {
            EventPhoto::where('id', $this->photoId)
                ->where('moderation_status', 'pending')
                ->update(['moderation_status' => 'pending']);
        } catch (\Throwable) {
            // ignore
        }
    }
}
