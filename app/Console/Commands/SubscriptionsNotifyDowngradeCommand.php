<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\PhotographerProfile;
use App\Models\PhotographerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\UserNotification;
use App\Services\LineNotifyService;
use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * subscriptions:notify-downgrade
 *
 * Daily nudge fired 7 / 3 / 1 days before period_end on a subscription
 * that's heading for a SHRINK at rollover — either because the user
 * scheduled a downgrade (`meta.pending_plan_code` set via changePlan)
 * or because they hit "cancel" (`cancel_at_period_end = true`, which
 * means the sub falls back to the FREE plan when its period ends).
 *
 * What's actually inside the message:
 *   • Active event count vs new plan's max_concurrent_events
 *   • Storage used vs new plan's storage_bytes
 *   • AI features that the new plan doesn't include
 *
 * Photographers see a concrete checklist of "X events to close,
 * Y GB to delete" so they can take action before the silent
 * sync-on-rollover kicks in (which leaves existing data untouched
 * but blocks them from creating more — confusing if they didn't
 * see this notice).
 *
 * Anti-spam: meta.downgrade_warned_buckets stores per-bucket flags
 * (t7 / t3 / t1) so each bucket fires at most once per period
 * regardless of how many days the cron runs.
 */
class SubscriptionsNotifyDowngradeCommand extends Command
{
    protected $signature   = 'subscriptions:notify-downgrade
                              {--quiet-if-none : Skip the "no work" log line when nothing fired}
                              {--dry-run       : Show what would be sent without sending}';
    protected $description = 'แจ้งช่างภาพ 7/3/1 วันก่อน downgrade ที่จะเกิด — บอกว่ามี event/photo เกิน cap ใหม่กี่อัน';

    /**
     * Day-bucket definitions. Each fires once when current_period_end
     * lands in the bucket; meta.downgrade_warned_buckets tracks which
     * have fired for this period. The bucket key is also the meta flag.
     *
     * Order matters: small-to-large so bucketFor() returns the SMALLEST
     * bucket whose threshold the remaining days fit under. 2 days out
     * matches t3 (not t7).
     */
    private const BUCKETS = [
        ['key' => 't1', 'days' => 1, 'label' => 'พรุ่งนี้'],
        ['key' => 't3', 'days' => 3, 'label' => 'อีก 3 วัน'],
        ['key' => 't7', 'days' => 7, 'label' => 'อีก 7 วัน'],
    ];

    public function handle(): int
    {
        $now = now();
        $sent  = 0;
        $skipped = 0;
        $dryRun = (bool) $this->option('dry-run');

        // Find every subscription that's heading for a shrink:
        //   • cancel_at_period_end = true → falls back to FREE
        //   • meta.pending_plan_code set + that plan is smaller in
        //     at least one dimension → custom downgrade scheduled
        // Both share period_end as the trigger point.
        $candidates = PhotographerSubscription::query()
            ->with('plan')
            ->where('status', PhotographerSubscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '>', $now)
            ->where('current_period_end', '<=', $now->copy()->addDays(8))  // T+8 ceiling
            ->where(function ($q) {
                // Postgres jsonb `?` operator collides with PDO parameter
                // binding, so we use `->>` to extract the key as text and
                // check it's non-null. Equivalent semantics: returns rows
                // where meta has a non-empty `pending_plan_code` value.
                $q->where('cancel_at_period_end', true)
                  ->orWhereRaw("meta->>'pending_plan_code' IS NOT NULL");
            })
            ->get();

        foreach ($candidates as $sub) {
            try {
                $bucket = $this->bucketFor($sub->current_period_end, $now);
                if (!$bucket) {
                    continue; // outside any T-7/T-3/T-1 window
                }

                $alreadySent = (bool) (($sub->meta['downgrade_warned_buckets'][$bucket['key']] ?? false));
                if ($alreadySent) {
                    $skipped++;
                    continue;
                }

                $payload = $this->buildPayload($sub);
                if (!$payload) {
                    // Nothing actually shrinks (target plan equals current OR
                    // photographer doesn't exceed any new caps). Still mark
                    // the bucket as "fired" so we don't re-evaluate every
                    // day for nothing.
                    $this->markBucketFired($sub, $bucket['key']);
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->info("[DRY] sub#{$sub->id} bucket={$bucket['key']} target={$payload['target_plan']} excess_events={$payload['excess_events']} excess_storage_gb={$payload['excess_storage_gb']}");
                    continue;
                }

                $this->dispatch($sub, $payload, $bucket);
                $this->markBucketFired($sub, $bucket['key']);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('SubscriptionsNotifyDowngrade error', [
                    'sub_id' => $sub->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        if ($sent === 0 && $skipped === 0) {
            if (!$this->option('quiet-if-none')) {
                $this->info('No subscriptions heading for downgrade in the next 7 days.');
            }
            return self::SUCCESS;
        }

        $this->info("Notified {$sent} photographer(s) about upcoming downgrade · {$skipped} skipped (already sent / no shrink).");
        return self::SUCCESS;
    }

    /**
     * Decide which bucket the current_period_end falls into.
     * Returns null when none — i.e. period_end is more than 7 days
     * away (we don't fire) or already past (overdue cron handles it).
     */
    private function bucketFor(\Carbon\Carbon $periodEnd, \Carbon\Carbon $now): ?array
    {
        $hours = $now->diffInHours($periodEnd, false);  // signed
        if ($hours <= 0) return null;
        $days = (int) ceil($hours / 24);

        // Match the LATEST bucket whose `days` is >= remaining days.
        // Day 7.5 → t7. Day 2.5 → t3. Day 0.5 → t1.
        foreach (self::BUCKETS as $bucket) {
            if ($days <= $bucket['days']) return $bucket;
        }
        return null;
    }

    /**
     * Build the per-photographer impact summary.
     * Returns null when there's no actual shrink (e.g. pending plan
     * is bigger or equal in every dimension — user scheduled an
     * UPGRADE that we don't need to warn about).
     */
    private function buildPayload(PhotographerSubscription $sub): ?array
    {
        $profile = PhotographerProfile::where('user_id', $sub->photographer_id)->first();
        if (!$profile) return null;

        // Resolve target plan: pending_plan_code wins, else free fallback
        // (cancel_at_period_end → photographer drops to free at rollover).
        $targetCode = $sub->meta['pending_plan_code'] ?? null;
        if (!$targetCode && $sub->cancel_at_period_end) {
            $targetCode = 'free';
        }
        if (!$targetCode) return null;

        $targetPlan = SubscriptionPlan::byCode($targetCode)->first();
        if (!$targetPlan) return null;

        $current = $sub->plan;
        if (!$current) return null;

        // Compute excesses against the TARGET plan
        $activeEvents = Event::where('photographer_id', $sub->photographer_id)
            ->whereIn('status', ['active', 'published'])
            ->count();
        $newCap = $targetPlan->max_concurrent_events;
        $excessEvents = ($newCap !== null && $newCap >= 0)
            ? max(0, $activeEvents - (int) $newCap)
            : 0;

        $usedGb = round((int) ($profile->storage_used_bytes ?? 0) / (1024 ** 3), 2);
        $newQuotaGb = round((int) $targetPlan->storage_bytes / (1024 ** 3), 2);
        $excessStorageGb = max(0.0, $usedGb - $newQuotaGb);

        $currentFeatures = (array) ($current->ai_features ?? []);
        $targetFeatures  = (array) ($targetPlan->ai_features ?? []);
        $lostFeatures    = array_values(array_diff($currentFeatures, $targetFeatures));

        // No real shrink to warn about?
        if ($excessEvents === 0 && $excessStorageGb <= 0 && empty($lostFeatures)) {
            return null;
        }

        return [
            'photographer_id'   => $sub->photographer_id,
            'current_plan_name' => $current->name,
            'target_plan'       => $targetPlan->code,
            'target_plan_name'  => $targetPlan->name,
            'period_end'        => $sub->current_period_end,
            'active_events'     => $activeEvents,
            'new_event_cap'     => $newCap,
            'excess_events'     => $excessEvents,
            'used_gb'           => $usedGb,
            'new_quota_gb'      => $newQuotaGb,
            'excess_storage_gb' => $excessStorageGb,
            'lost_features'     => $lostFeatures,
        ];
    }

    private function dispatch(PhotographerSubscription $sub, array $payload, array $bucket): void
    {
        // Build a single Thai message body shared by LINE, email, in-app.
        // Concrete numbers + a CTA so the photographer knows EXACTLY
        // what to do before period_end hits.
        $lines = [];
        $lines[] = "⚠ แผนของคุณจะถูก downgrade ในอีก " . $bucket['label'];
        $lines[] = "{$payload['current_plan_name']} → {$payload['target_plan_name']}";
        $lines[] = "วันที่ {$payload['period_end']->format('d M Y')}";
        $lines[] = "";
        $lines[] = "📋 สิ่งที่ต้องจัดการก่อนหมดรอบ:";

        if ($payload['excess_events'] > 0) {
            $lines[] = "  • อีเวนต์ที่เปิดอยู่: {$payload['active_events']} งาน — แผนใหม่จำกัด {$payload['new_event_cap']} → ปิดเพิ่มอีก {$payload['excess_events']} งาน";
        }
        if ($payload['excess_storage_gb'] > 0) {
            $lines[] = "  • พื้นที่ที่ใช้: " . number_format($payload['used_gb'], 1) . " GB — แผนใหม่ให้แค่ " . number_format($payload['new_quota_gb'], 1) . " GB → ลบรูป " . number_format($payload['excess_storage_gb'], 1) . " GB";
        }
        if (!empty($payload['lost_features'])) {
            $lines[] = "  • ฟีเจอร์ที่จะหาย: " . implode(', ', array_slice($payload['lost_features'], 0, 4)) . (count($payload['lost_features']) > 4 ? ' …' : '');
        }
        $lines[] = "";
        $lines[] = "เปลี่ยนใจ? ต่ออายุแผนเดิม:";
        $lines[] = url('/photographer/subscription/plans');

        $body = implode("\n", $lines);

        // ── In-app notification (always) ────────────────────────────────
        try {
            if (Schema::hasTable('user_notifications')) {
                UserNotification::create([
                    'user_id'    => $sub->photographer_id,
                    'type'       => 'subscription',
                    'title'      => "แผนจะ downgrade ใน {$bucket['label']}",
                    'message'    => "{$payload['current_plan_name']} → {$payload['target_plan_name']} · "
                                  . ($payload['excess_events'] > 0 ? "ปิดอีเวนต์ {$payload['excess_events']} อัน · " : '')
                                  . ($payload['excess_storage_gb'] > 0 ? "ลบรูป " . number_format($payload['excess_storage_gb'], 1) . " GB" : ''),
                    'is_read'    => false,
                    'action_url' => url('/photographer/subscription/plans'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::debug('downgrade notice in-app failed: ' . $e->getMessage());
        }

        // ── LINE push (best-effort) ─────────────────────────────────────
        try {
            app(LineNotifyService::class)->pushText($sub->photographer_id, $body);
        } catch (\Throwable $e) {
            Log::debug('downgrade notice LINE failed: ' . $e->getMessage());
        }

        // ── Email (best-effort, only when we have a mail service) ────────
        try {
            if (app()->bound(MailService::class)) {
                $user = DB::table('auth_users')
                    ->where('id', $sub->photographer_id)
                    ->select('email', 'first_name')
                    ->first();
                if ($user && !empty($user->email)) {
                    app(MailService::class)->sendRaw(
                        to:      $user->email,
                        subject: "[Loadroop] แผนของคุณจะ downgrade ใน {$bucket['label']}",
                        body:    $body,
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::debug('downgrade notice email failed: ' . $e->getMessage());
        }
    }

    private function markBucketFired(PhotographerSubscription $sub, string $key): void
    {
        $meta = $sub->meta ?? [];
        $meta['downgrade_warned_buckets']      ??= [];
        $meta['downgrade_warned_buckets'][$key]  = now()->toIso8601String();
        $sub->update(['meta' => $meta]);
    }
}
