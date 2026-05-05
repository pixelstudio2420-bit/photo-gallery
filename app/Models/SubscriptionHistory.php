<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit trail for subscription state transitions.
 * See migration header for the full design rationale.
 *
 * Append-only by convention — no `update()` or `delete()` should ever
 * fire on these rows in business code. The cascade-on-delete is for
 * the rare case of a hard-deleted parent subscription.
 */
class SubscriptionHistory extends Model
{
    protected $table = 'subscription_history';  // singular, not pluralised
    public $timestamps = false; // only created_at, set by DB default

    public const EVT_CREATED               = 'created';
    public const EVT_ACTIVATED             = 'activated';
    public const EVT_RENEWED               = 'renewed';
    public const EVT_UPGRADED              = 'upgraded';
    public const EVT_DOWNGRADED            = 'downgraded';
    public const EVT_CANCELLED             = 'cancelled';
    public const EVT_REACTIVATED           = 'reactivated';
    public const EVT_GRACE_ENTERED         = 'grace_entered';
    public const EVT_EXPIRED               = 'expired';
    public const EVT_REFUNDED              = 'refunded';
    public const EVT_PLAN_CHANGE_SCHEDULED = 'plan_change_scheduled';

    public const TRIG_USER    = 'user';
    public const TRIG_ADMIN   = 'admin';
    public const TRIG_CRON    = 'cron';
    public const TRIG_WEBHOOK = 'webhook';
    public const TRIG_SYSTEM  = 'system';

    protected $fillable = [
        'subscription_id', 'photographer_id', 'event_type',
        'from_plan_id', 'to_plan_id', 'amount_thb',
        'triggered_by', 'triggered_by_id', 'metadata',
    ];

    protected $casts = [
        'amount_thb' => 'decimal:2',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    /* ──────────────── Relations ──────────────── */

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PhotographerSubscription::class, 'subscription_id');
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'to_plan_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_id');
    }

    /* ──────────────── Scopes ──────────────── */

    public function scopeOfType(Builder $q, string $eventType): Builder
    {
        return $q->where('event_type', $eventType);
    }

    public function scopeForPhotographer(Builder $q, int $photographerId): Builder
    {
        return $q->where('photographer_id', $photographerId);
    }

    /* ──────────────── Display helpers ──────────────── */

    public function eventLabel(): string
    {
        return match ($this->event_type) {
            self::EVT_CREATED               => 'สมัครครั้งแรก',
            self::EVT_ACTIVATED             => 'แผนเปิดใช้งาน',
            self::EVT_RENEWED               => 'ต่ออายุ',
            self::EVT_UPGRADED              => 'อัปเกรดแผน',
            self::EVT_DOWNGRADED            => 'ดาวน์เกรดแผน',
            self::EVT_CANCELLED             => 'ยกเลิก',
            self::EVT_REACTIVATED           => 'กลับมาใช้งาน',
            self::EVT_GRACE_ENTERED         => 'จ่ายเงินไม่สำเร็จ — เข้าช่วงผ่อนผัน',
            self::EVT_EXPIRED               => 'แผนหมดอายุ',
            self::EVT_REFUNDED              => 'คืนเงิน',
            self::EVT_PLAN_CHANGE_SCHEDULED => 'นัดเปลี่ยนแผน (รอรอบถัดไป)',
            default                         => $this->event_type,
        };
    }

    public function eventColor(): string
    {
        return match ($this->event_type) {
            self::EVT_CREATED, self::EVT_ACTIVATED, self::EVT_REACTIVATED, self::EVT_UPGRADED  => 'emerald',
            self::EVT_RENEWED                                                                   => 'blue',
            self::EVT_DOWNGRADED, self::EVT_PLAN_CHANGE_SCHEDULED                               => 'amber',
            self::EVT_CANCELLED, self::EVT_GRACE_ENTERED, self::EVT_EXPIRED, self::EVT_REFUNDED => 'rose',
            default                                                                             => 'gray',
        };
    }
}
