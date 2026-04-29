<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends Model
{
    protected $table = 'marketing_loyalty_accounts';

    protected $fillable = [
        'user_id', 'points_balance', 'points_earned_total', 'points_redeemed_total',
        'lifetime_spend', 'tier', 'tier_expires_at',
    ];

    protected $casts = [
        'lifetime_spend'  => 'decimal:2',
        'tier_expires_at' => 'datetime',
    ];

    public const TIERS = ['bronze', 'silver', 'gold', 'platinum'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class, 'account_id');
    }

    public function tierBadgeColor(): string
    {
        return match ($this->tier) {
            'platinum' => 'indigo',
            'gold'     => 'amber',
            'silver'   => 'slate',
            default    => 'orange',
        };
    }

    public function tierIcon(): string
    {
        return match ($this->tier) {
            'platinum' => 'bi-gem',
            'gold'     => 'bi-trophy-fill',
            'silver'   => 'bi-award-fill',
            default    => 'bi-star-fill',
        };
    }
}
