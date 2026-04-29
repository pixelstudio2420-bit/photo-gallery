<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row = one paid promotion window for a photographer.
 *
 * Not to be confused with Coupon::PROMO — that's discount codes.
 * "Promotion" here = "this photographer paid us to rank higher".
 */
class PhotographerPromotion extends Model
{
    public const KIND_BOOST     = 'boost';      // bumps ranking
    public const KIND_FEATURED  = 'featured';   // dedicated card in featured slot
    public const KIND_HIGHLIGHT = 'highlight';  // visual emphasis (border/badge)

    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED  = 'refunded';

    protected $fillable = [
        'photographer_id', 'kind', 'placement', 'placement_target',
        'boost_score', 'billing_cycle', 'amount_thb',
        'starts_at', 'ends_at', 'status', 'order_id', 'meta',
    ];

    protected $casts = [
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'amount_thb'  => 'decimal:2',
        'boost_score' => 'decimal:2',
        'meta'        => 'array',
    ];

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function isActiveNow(): bool
    {
        $now = now();
        return $this->status === self::STATUS_ACTIVE
            && $this->starts_at?->lte($now)
            && $this->ends_at?->gte($now);
    }
}
