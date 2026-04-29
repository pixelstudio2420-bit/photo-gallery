<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'coupons';
    public $timestamps = false;

    protected $fillable = [
        'code', 'name', 'description', 'type', 'value',
        'min_order', 'max_discount',
        'usage_limit', 'usage_count', 'per_user_limit',
        'start_date', 'end_date', 'is_active',
    ];

    protected $casts = [
        'value'        => 'decimal:2',
        'min_order'    => 'decimal:2',
        'max_discount' => 'decimal:2',
        'is_active'    => 'boolean',
        'start_date'   => 'datetime',
        'end_date'     => 'datetime',
    ];

    public function usages()
    {
        return $this->hasMany(CouponUsage::class, 'coupon_id');
    }

    // ─── Scopes ───
    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->where(function ($sub) {
                $sub->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($sub) {
                $sub->whereNull('end_date')->orWhere('end_date', '>', now());
            });
    }

    public function scopeExpired($q)
    {
        return $q->whereNotNull('end_date')->where('end_date', '<=', now());
    }

    public function scopeExpiringSoon($q, int $days = 7)
    {
        return $q->whereNotNull('end_date')
            ->where('end_date', '>', now())
            ->where('end_date', '<=', now()->addDays($days));
    }

    public function scopeExhausted($q)
    {
        return $q->whereNotNull('usage_limit')
            ->whereColumn('usage_count', '>=', 'usage_limit');
    }

    // ─── Helpers ───

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->start_date && $this->start_date->isFuture()) return false;
        if ($this->end_date && $this->end_date->isPast()) return false;
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return false;
        return true;
    }

    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        if (!$this->end_date) return false;
        if ($this->end_date->isPast()) return false;
        return $this->end_date->lte(now()->addDays($days));
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit && $this->usage_count >= $this->usage_limit;
    }

    public function getUsagePercentageAttribute(): float
    {
        if (!$this->usage_limit) return 0;
        return round(($this->usage_count / $this->usage_limit) * 100, 1);
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) return 'disabled';
        if ($this->isExpired()) return 'expired';
        if ($this->isExhausted()) return 'exhausted';
        if ($this->start_date && $this->start_date->isFuture()) return 'scheduled';
        return 'active';
    }

    public function getTotalDiscountAttribute(): float
    {
        return (float) \DB::table('coupon_usage')->where('coupon_id', $this->id)->sum('discount_amount');
    }

    public function getRevenueGeneratedAttribute(): float
    {
        return (float) \DB::table('coupon_usage')
            ->join('orders', 'coupon_usage.order_id', '=', 'orders.id')
            ->where('coupon_usage.coupon_id', $this->id)
            ->sum('orders.total');
    }

    /**
     * Generate a random unique coupon code.
     */
    public static function generateCode(string $prefix = '', int $length = 8): string
    {
        do {
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $random = '';
            for ($i = 0; $i < $length; $i++) {
                $random .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $code = strtoupper($prefix . $random);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Calculate discount amount for a given order total.
     */
    public function calculateDiscount(float $orderTotal): float
    {
        if ($this->min_order && $orderTotal < $this->min_order) return 0;

        if ($this->type === 'percent') {
            $discount = $orderTotal * ((float) $this->value / 100);
            if ($this->max_discount) {
                $discount = min($discount, (float) $this->max_discount);
            }
            return round($discount, 2);
        }

        return min((float) $this->value, $orderTotal);
    }
}
