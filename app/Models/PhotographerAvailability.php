<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Photographer recurring or one-off availability rule.
 *
 * Two flavours:
 *   • type=recurring  → applies every week, set `day_of_week` (0=Sun..6=Sat)
 *   • type=override   → single-day rule, set `specific_date`
 *
 * Effect:
 *   • effect=available → time window is bookable
 *   • effect=blocked   → time window is NOT bookable (lunch break, holiday)
 *
 * Resolution priority (high → low):
 *   1. override (specific_date matches today)
 *   2. recurring (day_of_week matches)
 *   3. fallback: photographer is available 24/7 if no rules at all
 */
class PhotographerAvailability extends Model
{
    public const TYPE_RECURRING = 'recurring';
    public const TYPE_OVERRIDE  = 'override';

    public const EFFECT_AVAILABLE = 'available';
    public const EFFECT_BLOCKED   = 'blocked';

    protected $fillable = [
        'photographer_id', 'type',
        'day_of_week', 'specific_date',
        'time_start', 'time_end',
        'effect', 'label',
    ];

    protected $casts = [
        'specific_date' => 'date',
        'day_of_week'   => 'integer',
    ];

    /**
     * Cache-bust hook — every save/delete on an availability rule
     * invalidates the cached rule set for that photographer. Without
     * this the AvailabilityService cache would serve stale rules for
     * up to its TTL after an admin tweak (10 min by default).
     */
    protected static function booted(): void
    {
        $bust = function (self $model) {
            \App\Services\AvailabilityService::flushCacheFor((int) $model->photographer_id);
        };
        static::saved($bust);
        static::deleted($bust);
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function scopeForPhotographer($q, int $photographerId)
    {
        return $q->where('photographer_id', $photographerId);
    }

    public function scopeRecurring($q) { return $q->where('type', self::TYPE_RECURRING); }
    public function scopeOverride($q)  { return $q->where('type', self::TYPE_OVERRIDE); }

    public static function dayOfWeekLabel(int $dow): string
    {
        return [
            0 => 'อาทิตย์', 1 => 'จันทร์', 2 => 'อังคาร', 3 => 'พุธ',
            4 => 'พฤหัสบดี', 5 => 'ศุกร์', 6 => 'เสาร์',
        ][$dow] ?? '?';
    }
}
