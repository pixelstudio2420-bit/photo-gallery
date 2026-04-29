<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralRedemption extends Model
{
    protected $table = 'marketing_referral_redemptions';

    protected $fillable = [
        'referral_code_id', 'redeemer_user_id', 'order_id',
        'discount_applied', 'reward_granted', 'status', 'rewarded_at',
    ];

    protected $casts = [
        'discount_applied' => 'decimal:2',
        'reward_granted'   => 'decimal:2',
        'rewarded_at'      => 'datetime',
    ];

    public function code(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'redeemer_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order::class, 'order_id');
    }
}
