<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single issued grant of credits to a photographer.
 *
 * One bundle = one purchase OR one admin grant OR one monthly free-tier
 * refill. Credits are consumed FIFO across bundles (by expires_at), so a
 * bundle expiring tomorrow is drained before one expiring next year.
 *
 * IMMUTABLE once issued — the only field that changes is `credits_remaining`.
 * If an admin needs to claw back credits, append a compensating transaction
 * instead of mutating this row, so audit history stays intact.
 */
class PhotographerCreditBundle extends Model
{
    use HasFactory;

    protected $table = 'photographer_credit_bundles';

    // Explicit fillable — never allow mass-assignment of credits_remaining
    // from user input; all mutations go through CreditService.
    protected $fillable = [
        'photographer_id',
        'package_id',
        'order_id',
        'source',
        'credits_initial',
        'credits_remaining',
        'price_paid_thb',
        'expires_at',
        'note',
    ];

    protected $casts = [
        'photographer_id'    => 'integer',
        'package_id'         => 'integer',
        'order_id'           => 'integer',
        'credits_initial'    => 'integer',
        'credits_remaining'  => 'integer',
        'price_paid_thb'     => 'decimal:2',
        'expires_at'         => 'datetime',
    ];

    // ────────────────────────────────────────────────────────────────────
    // Scopes
    // ────────────────────────────────────────────────────────────────────

    /** Only bundles with remaining credits AND not expired. */
    public function scopeUsable($q)
    {
        return $q->where('credits_remaining', '>', 0)
            ->where(function ($w) {
                $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /** FIFO order: oldest expiry first, NULL expiry last (so permanent bundles are held in reserve). */
    public function scopeFifo($q)
    {
        // NULLS LAST via CASE — portable across MySQL/MariaDB/SQLite.
        return $q->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('expires_at', 'asc')
            ->orderBy('id', 'asc');
    }

    /** Bundles expiring within N days and not yet drained. */
    public function scopeExpiringSoon($q, int $daysAhead)
    {
        return $q->where('credits_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($daysAhead)]);
    }

    // ────────────────────────────────────────────────────────────────────
    // Relations
    // ────────────────────────────────────────────────────────────────────

    public function photographer()
    {
        return $this->belongsTo(\App\Models\User::class, 'photographer_id');
    }

    public function package()
    {
        return $this->belongsTo(UploadCreditPackage::class, 'package_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function transactions()
    {
        return $this->hasMany(CreditTransaction::class, 'bundle_id');
    }

    // ────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function getConsumedAttribute(): int
    {
        return max(0, ((int) $this->credits_initial) - ((int) $this->credits_remaining));
    }

    /** Rounded percentage used (0-100) for progress bars. */
    public function getPercentUsedAttribute(): float
    {
        $initial = max(1, (int) $this->credits_initial);
        return round(($this->consumed / $initial) * 100, 1);
    }
}
