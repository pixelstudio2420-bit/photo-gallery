<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    protected $table = 'coupon_usage';
    public $timestamps = false;

    protected $fillable = ['coupon_id', 'user_id', 'order_id', 'discount_amount', 'used_at'];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'used_at'         => 'datetime',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function scopeInPeriod($q, string $period = '30d')
    {
        $days = match ($period) {
            '7d'   => 7,
            '30d'  => 30,
            '90d'  => 90,
            '365d' => 365,
            default => 30,
        };
        return $q->where('used_at', '>=', now()->subDays($days));
    }
}
