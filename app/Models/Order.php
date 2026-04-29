<?php
namespace App\Models;
use App\Observers\OrderIntegrityObserver;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';
    protected $fillable = [
        'user_id','event_id','package_id','order_number','total','status','note',
        'delivery_method','delivery_status','delivered_at','delivery_meta',
        // Credit system
        'order_type','credit_package_id',
        // Subscription system (uses order_type='subscription', sets subscription_invoice_id)
        'subscription_invoice_id',
        // Consumer cloud-storage system (uses order_type='user_storage_subscription')
        'user_storage_invoice_id',
        // Photographer addon store (uses order_type='addon', sets addon_purchase_id)
        'addon_purchase_id',
        // Coupon + referral discount columns (added in 2026_05_12 migration)
        'subtotal','discount_amount','coupon_id','coupon_code',
        // Marketing system: referral linkage + loyalty + UTM attribution
        'referral_code_id','loyalty_points_earned','loyalty_points_redeemed','utm_attribution_id',
        // Idempotency + paid-at — added by 2026_04_27_200002 hardening migration
        'idempotency_key','paid_at',
    ];

    protected $casts = [
        'total'             => 'decimal:2',
        'subtotal'          => 'decimal:2',
        'discount_amount'   => 'decimal:2',
        'delivered_at'      => 'datetime',
        'delivery_meta'     => 'array',
    ];

    // Order types — photo_package is the legacy default (buyer buys photos),
    // credit_package is the new flow (photographer buys upload credits),
    // subscription is the new recurring plan flow (photographer pays monthly
    // for GB quota + AI features).
    public const TYPE_PHOTO_PACKAGE           = 'photo_package';
    public const TYPE_CREDIT_PACKAGE          = 'credit_package';
    public const TYPE_SUBSCRIPTION            = 'subscription';
    public const TYPE_USER_STORAGE_SUBSCRIPTION = 'user_storage_subscription';
    public const TYPE_GIFT_CARD               = 'gift_card';
    public const TYPE_ADDON                   = 'addon';

    public function user() { return $this->belongsTo(User::class,'user_id'); }
    public function event() { return $this->belongsTo(Event::class,'event_id'); }
    public function package() { return $this->belongsTo(PricingPackage::class,'package_id'); }
    public function creditPackage() { return $this->belongsTo(UploadCreditPackage::class,'credit_package_id'); }
    public function creditBundle() { return $this->hasOne(PhotographerCreditBundle::class,'order_id'); }
    public function items() { return $this->hasMany(OrderItem::class,'order_id'); }
    public function transactions() { return $this->hasMany(PaymentTransaction::class,'order_id'); }
    public function slips() { return $this->hasMany(PaymentSlip::class,'order_id'); }
    public function downloadTokens() { return $this->hasMany(DownloadToken::class,'order_id'); }
    public function payout() { return $this->hasOne(PhotographerPayout::class,'order_id'); }
    public function refund() { return $this->hasOne(PaymentRefund::class,'order_id'); }
    public function isPaid() { return $this->status === 'paid'; }
    public function isCreditPackageOrder() { return ($this->order_type ?? self::TYPE_PHOTO_PACKAGE) === self::TYPE_CREDIT_PACKAGE; }
    public function isSubscriptionOrder() { return ($this->order_type ?? null) === self::TYPE_SUBSCRIPTION; }
    public function isUserStorageOrder() { return ($this->order_type ?? null) === self::TYPE_USER_STORAGE_SUBSCRIPTION; }
    public function isGiftCardOrder() { return ($this->order_type ?? null) === self::TYPE_GIFT_CARD; }
    public function isAddonOrder() { return ($this->order_type ?? null) === self::TYPE_ADDON; }

    public function subscriptionInvoice()
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }

    public function userStorageInvoice()
    {
        return $this->belongsTo(UserStorageInvoice::class, 'user_storage_invoice_id');
    }

    /* ---------- Coupon + Referral relations ---------- */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function couponUsage()
    {
        return $this->hasOne(CouponUsage::class, 'order_id');
    }

    public function referralCode()
    {
        return $this->belongsTo(\App\Models\Marketing\ReferralCode::class, 'referral_code_id');
    }

    public function referralRedemption()
    {
        return $this->hasOne(\App\Models\Marketing\ReferralRedemption::class, 'order_id');
    }

    /**
     * When an Order is permanently deleted, purge the per-order folder tree
     * (slips, delivery manifests, etc.) from every enabled storage driver.
     *
     * Child PaymentSlip rows also have their own deleting hook that removes
     * individual slip_path files — we rely on that for row-level deletes
     * and use the directory purge here as a belt-and-braces cleanup so any
     * file not tracked by a PaymentSlip row (old orphans, failed retries,
     * etc.) is swept away with the order.
     */
    protected static function booted(): void
    {
        // OrderIntegrityObserver — refuses to mutate total/subtotal/
        // discount_amount once the order is in paid/refunded/completed.
        // Application-level guard so it ports across drivers and surfaces
        // as a clean exception in logs + tests.
        static::observe(OrderIntegrityObserver::class);

        static::deleting(function (self $order) {
            try {
                app(\App\Services\StorageManager::class)
                    ->purgeDirectory("orders/{$order->id}");
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "Order#{$order->id} directory purge failed: " . $e->getMessage()
                );
            }
        });
    }
}
