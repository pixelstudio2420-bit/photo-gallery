<?php

namespace App\Services;

use App\Models\AiTask;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\PhotographerProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * AiTaskService
 * ─────────────
 * One-stop dispatcher for every AI feature on a photographer's plan:
 *   - duplicate_detection (perceptual hash)
 *   - quality_filter      (blur detection)
 *   - best_shot           (heuristic ranking)
 *   - color_enhance       (auto-adjust)
 *   - auto_tagging        (Rekognition labels — stub if not configured)
 *   - face_search         (Rekognition indexing — stub if not configured)
 *   - smart_captions      (LLM — stub if not configured)
 *   - video_thumbnails    (FFmpeg — stub if not installed)
 *
 * Every entry point follows the same shape:
 *   1) Verify plan grants the feature (subs.canAccessFeature)
 *   2) Compute credit cost (1 per photo for most features)
 *   3) consumeAiCredits — atomic, refuses if cap exceeded
 *   4) Create AiTask row (audit) → markRunning() → ... → markDone()
 *   5) Return ['ok' => bool, 'task' => AiTask, 'message' => string]
 *
 * Sync-by-default — no queue dispatch. The bulk of the work is per-photo
 * GD operations or one-off API calls; running them inline keeps the
 * controllers simple and lets the photographer see results immediately.
 * Heavy events (>50 photos) can be wrapped in a job later by swapping
 * the run* helpers with dispatch() calls — the AiTask row already
 * supports the running/done lifecycle for that.
 */
class AiTaskService
{
    public function __construct(
        private SubscriptionService $subs,
        private DuplicateDetectionAi $duplicates,
        private QualityFilterAi $quality,
        private BestShotAi $bestShot,
        private ColorEnhanceAi $color,
        private AutoTaggingAi $tagging,
        private FaceSearchAiPhotographer $faceSearch,
        private SmartCaptionsAi $captions,
        private VideoThumbnailsAi $video,
    ) {}

    /**
     * Generic gate + ledger entry. Returns null if the plan doesn't
     * permit the feature OR credits exceeded; caller must abort.
     */
    private function startTask(
        PhotographerProfile $profile,
        ?Event $event,
        string $kind,
        int $creditsNeeded,
        array $inputMeta = []
    ): ?AiTask {
        // 1) Plan gate
        if (!$this->subs->canAccessFeature($profile, $kind)) {
            return null;
        }

        // 2) Atomic credit consume
        if (!$this->subs->consumeAiCredits($profile, $creditsNeeded)) {
            return null;
        }

        // 3) Audit row
        $task = AiTask::create([
            'photographer_id' => $profile->user_id,
            'event_id'        => $event?->id,
            'kind'            => $kind,
            'status'          => AiTask::STATUS_PENDING,
            'credits_used'    => $creditsNeeded,
            'input_meta'      => $inputMeta,
        ]);
        $task->markRunning();
        return $task;
    }

    /**
     * Each public method below mirrors the same shape — swap to
     * dispatch() when async is needed.
     */

    public function runDuplicateDetection(PhotographerProfile $p, Event $event): array
    {
        $count = $event->photos()->count();
        $task = $this->startTask($p, $event, AiTask::KIND_DUPLICATE_DETECTION, $count);
        if (!$task) return $this->refuseResponse($p, AiTask::KIND_DUPLICATE_DETECTION);

        try {
            $result = $this->duplicates->run($event);
            $task->markDone($result['processed'], $result);
            return ['ok' => true, 'task' => $task, 'result' => $result];
        } catch (\Throwable $e) {
            $task->markFailed($e->getMessage());
            Log::error('AI duplicate detection failed', ['event' => $event->id, 'error' => $e->getMessage()]);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function runQualityFilter(PhotographerProfile $p, Event $event): array
    {
        $count = $event->photos()->count();
        $task = $this->startTask($p, $event, AiTask::KIND_QUALITY_FILTER, $count);
        if (!$task) return $this->refuseResponse($p, AiTask::KIND_QUALITY_FILTER);

        try {
            $result = $this->quality->run($event);
            $task->markDone($result['processed'], $result);
            return ['ok' => true, 'task' => $task, 'result' => $result];
        } catch (\Throwable $e) {
            $task->markFailed($e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function runBestShot(PhotographerProfile $p, Event $event): array
    {
        $count = $event->photos()->count();
        $task = $this->startTask($p, $event, AiTask::KIND_BEST_SHOT, $count);
        if (!$task) return $this->refuseResponse($p, AiTask::KIND_BEST_SHOT);

        try {
            $result = $this->bestShot->run($event);
            $task->markDone($result['processed'], $result);
            return ['ok' => true, 'task' => $task, 'result' => $result];
        } catch (\Throwable $e) {
            $task->markFailed($e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function runColorEnhance(PhotographerProfile $p, Event $event): array
    {
        $count = $event->photos()->count();
        $task = $this->startTask($p, $event, AiTask::KIND_COLOR_ENHANCE, $count);
        if (!$task) return $this->refuseResponse($p, AiTask::KIND_COLOR_ENHANCE);

        try {
            $result = $this->color->run($event);
            $task->markDone($result['processed'], $result);
            return ['ok' => true, 'task' => $task, 'result' => $result];
        } catch (\Throwable $e) {
            $task->markFailed($e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function runAutoTagging(PhotographerProfile $p, Event $event): array
    {
        $count = $event->photos()->count();
        $task = $this->startTask($p, $event, AiTask::KIND_AUTO_TAGGING, $count);
        if (!$task) return $this->refuseResponse($p, AiTask::KIND_AUTO_TAGGING);

        try {
            $result = $this->tagging->run($event);
            $task->markDone($result['processed'], $result);
            return ['ok' => true, 'task' => $task, 'result' => $result];
        } catch (\Throwable $e) {
            $task->markFailed($e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function runFaceIndex(PhotographerProfile $p, Event $event): array
    {
        $count = $event->photos()->count();
        $task = $this->startTask($p, $event, AiTask::KIND_FACE_SEARCH, $count);
        if (!$task) return $this->refuseResponse($p, AiTask::KIND_FACE_SEARCH);

        try {
            $result = $this->faceSearch->run($event);
            $task->markDone($result['processed'], $result);
            return ['ok' => true, 'task' => $task, 'result' => $result];
        } catch (\Throwable $e) {
            $task->markFailed($e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function runSmartCaptions(PhotographerProfile $p, Event $event): array
    {
        $count = $event->photos()->count();
        $task = $this->startTask($p, $event, AiTask::KIND_SMART_CAPTIONS, $count);
        if (!$task) return $this->refuseResponse($p, AiTask::KIND_SMART_CAPTIONS);

        try {
            $result = $this->captions->run($event);
            $task->markDone($result['processed'], $result);
            return ['ok' => true, 'task' => $task, 'result' => $result];
        } catch (\Throwable $e) {
            $task->markFailed($e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function runVideoThumbnails(PhotographerProfile $p, Event $event): array
    {
        $count = $event->photos()->count();
        $task = $this->startTask($p, $event, AiTask::KIND_VIDEO_THUMBNAILS, $count);
        if (!$task) return $this->refuseResponse($p, AiTask::KIND_VIDEO_THUMBNAILS);

        try {
            $result = $this->video->run($event);
            $task->markDone($result['processed'], $result);
            return ['ok' => true, 'task' => $task, 'result' => $result];
        } catch (\Throwable $e) {
            $task->markFailed($e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function refuseResponse(PhotographerProfile $p, string $kind): array
    {
        if (!$this->subs->canAccessFeature($p, $kind)) {
            return ['ok' => false, 'message' => 'แผนปัจจุบันยังไม่รองรับฟีเจอร์นี้ — กรุณาอัปเกรด'];
        }
        return ['ok' => false, 'message' => 'เครดิต AI หมดในรอบนี้ — รอรอบใหม่หรืออัปเกรดแผน'];
    }

    /**
     * Recent task feed for the AI dashboard.
     */
    public function recentTasks(PhotographerProfile $profile, int $limit = 20)
    {
        return AiTask::where('photographer_id', $profile->user_id)
            ->orderByDesc('id')
            ->with('event:id,name')
            ->limit($limit)
            ->get();
    }
}
