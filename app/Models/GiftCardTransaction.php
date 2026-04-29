<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardTransaction extends Model
{
    protected $fillable = [
        'gift_card_id', 'type', 'amount', 'balance_after',
        'user_id', 'order_id', 'admin_id',
        'note', 'meta',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta'          => 'array',
    ];

    public static function types(): array
    {
        return [
            'issue'  => 'ออกบัตร',
            'redeem' => 'ใช้จ่าย',
            'refund' => 'คืนยอด',
            'adjust' => 'ปรับยอด',
            'expire' => 'หมดอายุ',
            'void'   => 'ยกเลิก',
        ];
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }
}
