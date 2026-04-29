<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    protected $table = 'marketing_loyalty_transactions';

    protected $fillable = [
        'account_id', 'user_id', 'type', 'points',
        'related_amount', 'reason', 'order_id',
    ];

    protected $casts = [
        'points'         => 'integer',
        'related_amount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'account_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'earn'    => 'รับแต้ม',
            'redeem'  => 'ใช้แต้ม',
            'adjust'  => 'ปรับแต้ม',
            'expire'  => 'แต้มหมดอายุ',
            'reverse' => 'คืนแต้ม',
            default   => ucfirst($this->type),
        };
    }

    public function typeBadgeColor(): string
    {
        return match ($this->type) {
            'earn'    => 'emerald',
            'redeem'  => 'rose',
            'adjust'  => 'amber',
            'expire'  => 'slate',
            'reverse' => 'sky',
            default   => 'slate',
        };
    }
}
