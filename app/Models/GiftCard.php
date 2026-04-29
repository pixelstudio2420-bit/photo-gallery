<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCard extends Model
{
    protected $fillable = [
        'code', 'initial_amount', 'balance', 'currency',
        'purchaser_user_id', 'purchaser_email', 'purchaser_name',
        'recipient_name', 'recipient_email', 'personal_message',
        'redeemed_by_user_id',
        'source', 'source_order_id',
        'status', 'expires_at', 'activated_at',
        'issued_by_admin_id', 'admin_note',
    ];

    protected $casts = [
        'initial_amount' => 'decimal:2',
        'balance'        => 'decimal:2',
        'expires_at'     => 'datetime',
        'activated_at'   => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            'pending'  => 'รอชำระเงิน',
            'active'   => 'ใช้งานได้',
            'redeemed' => 'ใช้หมดแล้ว',
            'expired'  => 'หมดอายุ',
            'voided'   => 'ยกเลิก',
        ];
    }

    public static function sources(): array
    {
        return [
            'admin'    => 'ออกโดยแอดมิน',
            'purchase' => 'ซื้อออนไลน์',
            'promo'    => 'โปรโมชัน',
            'refund'   => 'คืนเงิน',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class);
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'purchaser_user_id');
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'redeemed_by_user_id');
    }

    public function isRedeemable(): bool
    {
        if ($this->status !== 'active') return false;
        if ((float) $this->balance <= 0) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    /**
     * Generate a human-friendly code like "GC-7FQ2-9KMX-3V8R".
     */
    public static function generateCode(): string
    {
        $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I
        do {
            $parts = [];
            for ($i = 0; $i < 3; $i++) {
                $p = '';
                for ($j = 0; $j < 4; $j++) $p .= $alpha[random_int(0, strlen($alpha) - 1)];
                $parts[] = $p;
            }
            $code = 'GC-' . implode('-', $parts);
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
