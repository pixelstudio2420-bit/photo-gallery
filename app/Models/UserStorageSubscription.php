<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A consumer user's cloud-storage subscription.
 *
 * One auth_users row → many subscriptions over time. Only one is
 * `active` or `grace` at any moment; the rest are historical.
 *
 * Lifecycle mirrors PhotographerSubscription:
 *   pending   — created, awaiting first payment
 *   active    — paid, within current_period_{start,end}
 *   grace     — renewal failed; waiting for retry before downgrade
 *   cancelled — user cancelled; may still run until period_end if
 *               cancel_at_period_end is true
 *   expired   — grace ran out, quota rolled back to Free plan
 */
class UserStorageSubscription extends Model
{
    use HasFactory;

    protected $table = 'user_storage_subscriptions';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_GRACE     = 'grace';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED   = 'expired';

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'started_at',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'cancelled_at',
        'grace_ends_at',
        'last_renewed_at',
        'renewal_attempts',
        'next_retry_at',
        'last_failure_reason',
        'payment_method_type',
        'omise_customer_id',
        'omise_schedule_id',
        'meta',
    ];

    protected $casts = [
        'started_at'           => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'cancelled_at'         => 'datetime',
        'grace_ends_at'        => 'datetime',
        'last_renewed_at'      => 'datetime',
        'next_retry_at'        => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'renewal_attempts'     => 'integer',
        'meta'                 => 'array',
    ];

    // ─── Relations ───────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StoragePlan::class, 'plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(UserStorageInvoice::class, 'subscription_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeActiveOrGrace($q)
    {
        return $q->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_GRACE]);
    }

    public function scopeDueForRenewal($q, int $lookAheadHours = 24)
    {
        return $q->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now()->addHours($lookAheadHours))
            ->where(function ($q) {
                $q->whereNull('cancel_at_period_end')
                  ->orWhere('cancel_at_period_end', false);
            });
    }

    public function scopeGraceExpired($q)
    {
        return $q->where('status', self::STATUS_GRACE)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isGrace(): bool
    {
        return $this->status === self::STATUS_GRACE;
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_GRACE], true);
    }

    public function daysUntilRenewal(): ?int
    {
        if (!$this->current_period_end) return null;
        return max(0, now()->diffInDays($this->current_period_end, false));
    }
}
