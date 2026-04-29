<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReferralCode extends Model
{
    protected $table = 'marketing_referral_codes';

    protected $fillable = [
        'code', 'owner_user_id',
        'discount_type', 'discount_value', 'reward_value',
        'max_uses', 'uses_count', 'is_active', 'expires_at',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'expires_at'      => 'datetime',
        'discount_value'  => 'decimal:2',
        'reward_value'    => 'decimal:2',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(ReferralRedemption::class, 'referral_code_id');
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeActive($q)
    {
        return $q->where('is_active', true)
                 ->where(function ($w) {
                     $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
                 });
    }

    public function isUsable(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_uses > 0 && $this->uses_count >= $this->max_uses) return false;
        return true;
    }

    /**
     * Generate a human-friendly referral code (8 chars).
     */
    public static function generateUniqueCode(int $length = 8): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('code', $code)->exists());
        return $code;
    }

    public function discountAmount(float $subtotal): float
    {
        if ($this->discount_type === 'percent') {
            return round($subtotal * ($this->discount_value / 100), 2);
        }
        return min((float) $this->discount_value, $subtotal);
    }
}
