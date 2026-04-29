<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit row for one AI operation.
 *
 * Created BEFORE consuming credits so a partially-completed task is
 * still observable in admin/dashboard. AiTaskService is responsible
 * for flipping status pending → running → done/failed and stamping
 * credits_used / items_processed / result_meta.
 */
class AiTask extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE    = 'done';
    public const STATUS_FAILED  = 'failed';

    // Task kinds — keep in sync with SubscriptionPlan.ai_features keys.
    public const KIND_FACE_SEARCH         = 'face_search';
    public const KIND_QUALITY_FILTER      = 'quality_filter';
    public const KIND_DUPLICATE_DETECTION = 'duplicate_detection';
    public const KIND_AUTO_TAGGING        = 'auto_tagging';
    public const KIND_BEST_SHOT           = 'best_shot';
    public const KIND_COLOR_ENHANCE       = 'color_enhance';
    public const KIND_SMART_CAPTIONS      = 'smart_captions';
    public const KIND_VIDEO_THUMBNAILS    = 'video_thumbnails';

    protected $fillable = [
        'photographer_id',
        'event_id',
        'kind',
        'status',
        'credits_used',
        'items_processed',
        'input_meta',
        'result_meta',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'input_meta'  => 'array',
        'result_meta' => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function markRunning(): void
    {
        $this->forceFill(['status' => self::STATUS_RUNNING, 'started_at' => now()])->save();
    }

    public function markDone(int $itemsProcessed, ?array $result = null): void
    {
        $this->forceFill([
            'status'           => self::STATUS_DONE,
            'items_processed'  => $itemsProcessed,
            'result_meta'      => $result,
            'finished_at'      => now(),
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status'        => self::STATUS_FAILED,
            'error_message' => $message,
            'finished_at'   => now(),
        ])->save();
    }
}
