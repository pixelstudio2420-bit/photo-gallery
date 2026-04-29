<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DigitalOrder extends Model
{
    protected $table = 'digital_orders';
    protected $fillable = ['order_number','user_id','product_id','amount','payment_method','payment_ref','status','slip_image','paid_at','note','download_token','downloads_remaining','expires_at'];
    protected $casts = ['amount'=>'decimal:2','paid_at'=>'datetime','expires_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class,'user_id'); }
    public function product() { return $this->belongsTo(DigitalProduct::class,'product_id'); }

    /**
     * Public URL for the uploaded slip image. Slips now live on R2 under
     * `users/{user_id}/payment-slips/` instead of the local `public` disk,
     * but historical rows may still be on `public` (or any configured
     * mirror), so resolveUrl() probes the primary driver first and then
     * sweeps every enabled driver. Views should render via this accessor
     * instead of assuming the `public` disk.
     */
    public function getSlipImageUrlAttribute(): string
    {
        if (empty($this->slip_image)) return '';
        return app(\App\Services\StorageManager::class)->resolveUrl($this->slip_image);
    }

    /**
     * Clean up the uploaded slip image when a digital order is permanently
     * deleted. Slips may live on any enabled driver (R2 for fresh uploads,
     * `public` for legacy rows, optionally mirrored to S3) — `deleteAsset()`
     * sweeps every configured driver and silently no-ops on missing keys,
     * so the same call works across the entire mixed-history fleet without
     * having to remember which disk each row landed on.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $order) {
            if ($order->slip_image) {
                try {
                    app(\App\Services\StorageManager::class)
                        ->deleteAsset($order->slip_image);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        "DigitalOrder#{$order->id} slip delete failed: " . $e->getMessage()
                    );
                }
            }
        });
    }
}
