<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A photographer's subscription record.
 *
 * One photographer → many subscriptions over time (one active, rest are
 * historical). The `status` field drives everything:
 *
 *   pending   — created, awaiting first payment
 *   active    — paid, in current_period_{start,end}
 *   grace     — renewal failed, waiting to retry within grace_ends_at
 *   cancelled — user cancelled; may still be active until period_end
 *   expired   — grace ran out, quota rolled back to free
 */
class PhotographerSubscription extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_GRACE     = 'grace';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED   = 'expired';

    protected $fillable = [
        'photographer_id',
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
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PhotographerProfile::class, 'photographer_id', 'user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'subscription_id');
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
        // Usable = entitled to plan features. Grace is still usable but
        // flagged so UI can show "please update payment" banners.
        //
        // We enforce the gate three-fold (status + period + grace deadline)
        // because the nightly SubscriptionExpireOverdueCommand can lag a
        // few minutes behind the actual period_end timestamp. Without the
        // time-based check, a photographer whose period rolled over at
        // 12:00 could keep using paid features until ~01:00 cron run.
        // Free tier (no row at all, or status='active' with no period_end)
        // returns true here — they're "always usable" for the free caps.
        if (!in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_GRACE], true)) {
            return false;
        }
        if ($this->current_period_end && $this->current_period_end->isPast()) {
            return false;
        }
        if ($this->status === self::STATUS_GRACE
            && $this->grace_ends_at
            && $this->grace_ends_at->isPast()) {
            return false;
        }
        return true;
    }

    public function daysUntilRenewal(): ?int
    {
        if (!$this->current_period_end) return null;
        // diffInDays returns a float in Carbon 3+ (e.g. 29.63 days). Cast
        // to int explicitly to avoid the implicit-float-to-int deprecation
        // warning under PHP 8.1+ when this gets passed to max() with an
        // integer 0.
        return max(0, (int) now()->diffInDays($this->current_period_end, false));
    }
}
