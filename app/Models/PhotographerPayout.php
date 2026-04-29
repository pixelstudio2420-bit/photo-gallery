<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PhotographerPayout extends Model
{
    protected $table = 'photographer_payouts';
    protected $fillable = ['photographer_id','order_id','gross_amount','commission_rate','payout_amount','platform_fee','status','note','paid_at'];
    protected $casts = ['gross_amount'=>'decimal:2','payout_amount'=>'decimal:2','platform_fee'=>'decimal:2','commission_rate'=>'decimal:2','paid_at'=>'datetime'];
    public function photographer() { return $this->belongsTo(User::class,'photographer_id'); }
    public function photographerProfile() { return $this->hasOne(PhotographerProfile::class,'user_id','photographer_id'); }
    public function order() { return $this->belongsTo(Order::class,'order_id'); }

    /**
     * Invalidate CommissionResolver's lifetime-revenue cache when a
     * payout is created or its status flips. Without this, a photographer
     * who just crossed a tier threshold would keep getting the lower rate
     * for up to 30 min until the cache TTL ticked over.
     *
     * Best-effort: if the resolver isn't bound (e.g. test environments
     * without the full container), we silently skip — the cache will
     * self-expire on TTL.
     */
    protected static function booted(): void
    {
        $invalidate = function (self $payout) {
            try {
                app(\App\Services\Payout\CommissionResolver::class)
                    ->invalidate((int) $payout->photographer_id);
            } catch (\Throwable) {
                // No-op: cache invalidation failure should never block a
                // payout write.
            }
        };
        static::created($invalidate);
        static::updated($invalidate);
    }
}
