<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PhotographerProfile extends Model
{
    protected $table = 'photographer_profiles';
    protected $fillable = [
        // ID card upload removed from the system — the column stays in DB
        // for back-compat with old rows but isn't writable through the model.
        'user_id','photographer_code','display_name','phone','bio','avatar',
        'bank_name','bank_account_number','bank_account_name','promptpay_number',
        'promptpay_verified_name','promptpay_verified_at','omise_recipient_id',
        'portfolio_url','portfolio_samples','specialties','years_experience',
        'commission_rate','status','tier','onboarding_stage','approved_by','approved_at',
        'contract_signed_at','contract_signer_ip','rejection_reason',
        // Storage quota (added 2026-04-22)
        'storage_quota_bytes','storage_used_bytes','storage_recalculated_at',
        // Credit system (added 2026-04-22)
        'billing_mode','credits_balance_cached','credits_last_recalc_at',
        // Subscription system (added 2026-04-24)
        'current_subscription_id','subscription_plan_code','subscription_status','subscription_renews_at',
        // Slug + location (added earlier)
        'slug','province_id',
        // Profile enrichment for pSEO (added 2026-05-01)
        'district_id','headline','languages','equipment','service_areas',
        'website_url','instagram_handle','facebook_url','line_id',
        'accepts_bookings','response_time_hours','profile_completion',
    ];
    protected $casts = [
        'commission_rate'         => 'decimal:2',
        'approved_at'             => 'datetime',
        'contract_signed_at'      => 'datetime',
        'promptpay_verified_at'   => 'datetime',
        'storage_recalculated_at' => 'datetime',
        'credits_last_recalc_at'  => 'datetime',
        'portfolio_samples'       => 'array',
        'specialties'             => 'array',
        'languages'               => 'array',
        'equipment'               => 'array',
        'service_areas'           => 'array',
        'years_experience'        => 'integer',
        'storage_quota_bytes'     => 'integer',
        'storage_used_bytes'      => 'integer',
        'credits_balance_cached'  => 'integer',
        'subscription_renews_at'  => 'datetime',
        'accepts_bookings'        => 'boolean',
        'response_time_hours'     => 'integer',
        'profile_completion'      => 'integer',
    ];

    /**
     * Compute the profile-completion score (0-100) based on which
     * key fields are filled. Drives the photographer dashboard
     * "complete your profile to rank higher" prompt and the pSEO
     * generator's `min_data_points` gate. Cheap to call — pure
     * field checks, no DB hits.
     */
    public function computeProfileCompletion(): int
    {
        $score = 0;
        // Each weighted field — totals to 100.
        $checks = [
            'display_name'      => 10,
            'bio'               => 15,
            'avatar'            => 10,
            'phone'             => 5,
            'province_id'       => 10,
            'specialties'       => 10,
            'years_experience'  => 5,
            'portfolio_url'     => 5,
            'languages'         => 5,
            'equipment'         => 5,
            'instagram_handle'  => 5,
            'facebook_url'      => 5,
            'headline'          => 10,
        ];
        foreach ($checks as $field => $weight) {
            $val = $this->{$field} ?? null;
            if (is_array($val) ? count($val) > 0 : !empty($val)) {
                $score += $weight;
            }
        }
        return min(100, $score);
    }

    /* ───────── Relations ───────── */

    public function province()
    {
        return $this->belongsTo(\App\Models\ThaiProvince::class, 'province_id');
    }

    // Billing modes — controls how uploads are metered/charged
    public const BILLING_COMMISSION = 'commission';
    public const BILLING_CREDITS    = 'credits';

    /* ══════════════════════════════════════════════════════════════
     * TIER SYSTEM
     * ══════════════════════════════════════════════════════════════
     * Three-tier access model so OAuth sign-ups can start using the
     * platform immediately while the "pro" verification path stays in
     * place for admin compliance. The tier is NOT something the admin
     * toggles manually — it's derived from which fields the photographer
     * has filled in. See computeTier() below.
     *
     *   creator → authenticated only; can upload/browse, NO selling
     *   seller  → verified PromptPay ID; can sell up to monthly cap
     *   pro     → ID card + signed contract; no cap, verified badge
     */

    public const TIER_CREATOR = 'creator';
    public const TIER_SELLER  = 'seller';
    public const TIER_PRO     = 'pro';

    /** Ordering used by canReach() so comparison is a single int compare. */
    private const TIER_RANK = [
        self::TIER_CREATOR => 0,
        self::TIER_SELLER  => 1,
        self::TIER_PRO     => 2,
    ];

    /**
     * Is the admin-gated "Pro" tier currently active on this installation?
     *
     * When OFF (admin toggle), the Pro tier is effectively hidden:
     *   • computeTier() never returns TIER_PRO
     *   • RequirePhotographerTier downgrades `pro` requirements to `seller`
     *   • Upgrade CTAs are hidden from the wizard / profile / dashboard
     *
     * Why this is a toggle: early-stage operations don't have a review team
     * to sign off on ID cards; Pro becomes a dead-end UI. Flipping this
     * off lets every seller operate unrestricted until Pro review is ready.
     */
    public static function isProTierEnabled(): bool
    {
        return (string) AppSetting::get('photographer_pro_tier_enabled', '1') === '1';
    }

    public static function tierLabels(): array
    {
        $labels = [
            self::TIER_CREATOR => 'Creator — อัปโหลด + สร้าง event ได้',
            self::TIER_SELLER  => 'Seller — ขายได้ (ต้องยืนยัน PromptPay)',
            self::TIER_PRO     => 'Pro — ไม่มีลิมิต (อนุมัติโดยแอดมิน)',
        ];
        if (!self::isProTierEnabled()) {
            // Re-label Seller to reflect that it's now the ceiling (no limits)
            $labels[self::TIER_SELLER] = 'Seller — ขายได้ ไม่มีลิมิต';
            unset($labels[self::TIER_PRO]);
        }
        return $labels;
    }

    /**
     * Derive the tier this profile is ENTITLED to based on the fields it
     * has filled in. Callers that want to honour the entitlement should
     * also write the result back to `tier` (see syncTier() for that).
     *
     * Intentionally does NOT read `$this->tier` — otherwise stale values
     * survive a field being cleared (e.g. admin nulls out the PromptPay
     * number; tier must drop from seller → creator, not stay at seller).
     */
    public function computeTier(): string
    {
        // Pro = admin-approved (onboarding_stage = 'active') AND has PromptPay.
        // The ID-card-upload + contract-signing gate was removed (2026-04-25)
        // — Pro is now a pure admin decision: once the admin flips the
        // onboarding stage to 'active', the photographer is Pro-entitled.
        //
        // When admin has disabled the Pro tier entirely, we skip this check
        // so existing Pro-entitled photographers show as Seller (the new
        // ceiling). Flipping Pro back ON restores them automatically.
        if (self::isProTierEnabled()
            && $this->onboarding_stage === 'active'
            && !empty($this->promptpay_number)) {
            return self::TIER_PRO;
        }

        // Seller = has a PromptPay number. Verification is optional at the
        // tier level — unverified sellers just can't receive payouts yet
        // (the payout job checks promptpay_verified_at).
        if (!empty($this->promptpay_number)) {
            return self::TIER_SELLER;
        }

        return self::TIER_CREATOR;
    }

    /**
     * Recompute + persist the tier. Returns true when it actually changed,
     * so callers can log tier-up events without fetching old + new again.
     */
    public function syncTier(): bool
    {
        $computed = $this->computeTier();
        if ($this->tier === $computed) {
            return false;
        }
        $this->tier = $computed;
        $this->save();
        return true;
    }

    /** Tier-gate: "can this profile reach at least `$required`?" */
    public function canReach(string $required): bool
    {
        $currentRank  = self::TIER_RANK[$this->tier ?? self::TIER_CREATOR] ?? 0;
        $requiredRank = self::TIER_RANK[$required] ?? 0;
        return $currentRank >= $requiredRank;
    }

    public function isCreator(): bool { return $this->tier === self::TIER_CREATOR; }
    public function isSeller(): bool  { return $this->tier === self::TIER_SELLER; }
    public function isPro(): bool     { return $this->tier === self::TIER_PRO; }

    /**
     * Profile-completeness percentage (0-100). Each item contributes
     * independently — partial fills earn partial credit, no more
     * "all-or-nothing" pairs.
     *
     * Weights (sum to 100):
     *   display_name        15  — required to even show on listings
     *   phone               10  — contact / SMS notification target
     *   bio                 10  — marketing copy
     *   avatar              10  — profile polish
     *   promptpay_number    25  — primary payout channel (highest weight)
     *   bank_account        15  — backup payout (number + name set)
     *   portfolio           15  — trust signal (URL or 1+ uploaded samples)
     *
     * ID Card / contract upload were removed from the schema-of-completeness
     * (admins no longer require them; the platform identifies sellers
     * through their PromptPay verification round-trip with ITMX instead).
     */
    public function completenessPercent(): int
    {
        $score = 0;
        if (!empty($this->display_name))      $score += 15;
        if (!empty($this->phone))             $score += 10;
        if (!empty($this->bio))               $score += 10;
        if (!empty($this->avatar))            $score += 10;
        if (!empty($this->promptpay_number))  $score += 25;
        if (!empty($this->bank_account_number) && !empty($this->bank_account_name)) {
            $score += 15;
        }
        if (!empty($this->portfolio_url) || !empty($this->portfolio_samples)) {
            $score += 15;
        }
        return min(100, $score);
    }

    /**
     * PromptPay is considered ready-to-receive-payouts only after the
     * verification round-trip succeeded and stored a name.
     */
    public function isPromptPayVerified(): bool
    {
        return !empty($this->promptpay_number)
            && !empty($this->promptpay_verified_name)
            && !is_null($this->promptpay_verified_at);
    }

    public static function onboardingStages(): array
    {
        return [
            'draft'           => 'ร่าง',
            'submitted'       => 'ส่งให้ตรวจแล้ว',
            'under_review'    => 'กำลังตรวจสอบ',
            'approved'        => 'อนุมัติ (ยังไม่เซ็นสัญญา)',
            'contract_signed' => 'เซ็นสัญญาแล้ว',
            'active'          => 'พร้อมรับงาน',
            'rejected'        => 'ปฏิเสธ',
        ];
    }

    public static function specialtyOptions(): array
    {
        return [
            'wedding'   => 'งานแต่งงาน',
            'event'     => 'Event / ประชุม',
            'sport'     => 'กีฬา / วิ่ง',
            'graduation'=> 'รับปริญญา',
            'portrait'  => 'ภาพบุคคล',
            'product'   => 'ถ่ายสินค้า',
            'landscape' => 'ภาพทิวทัศน์',
            'street'    => 'Street Photography',
        ];
    }
    public function user() { return $this->belongsTo(User::class,'user_id'); }
    public function events() { return $this->hasMany(Event::class,'photographer_id','user_id'); }
    public function payouts() { return $this->hasMany(PhotographerPayout::class,'photographer_id','user_id'); }
    public function reviews() { return $this->hasMany(Review::class,'photographer_id','user_id'); }

    /**
     * Legacy status flag — kept because older code (PhotographerAuth, admin
     * notifications, event publish checks) still branches on it. The tier
     * system is the forward-looking model: `canReach()` is preferred for
     * new gate checks. A suspended/rejected profile is never approved
     * regardless of tier.
     */
    public function isApproved() { return $this->status === 'approved'; }

    /** Blocked by admin action (rejected/suspended/banned) — true = cannot use anything. */
    public function isBlocked(): bool
    {
        return in_array($this->status, ['rejected', 'suspended', 'banned'], true);
    }

    /**
     * Full public URL for the avatar, regardless of which disk the file
     * actually lives on (R2, S3, or local public).
     *
     * Historical quirk we deliberately handle here:
     *   • OAuth-linked profiles store `avatar` as a remote URL
     *     (`https://lh3.googleusercontent.com/...`) — pass-through unchanged.
     *   • Pre-R2 rows have bare keys like `photographers/2/profile/XYZ.jpg`
     *     physically on the local public disk.
     *   • Post-R2 rows have the same-shaped key but the object lives on R2.
     *
     * Centralising the lookup through `StorageManager::resolveUrl()` means
     * legacy rows keep resolving correctly AFTER the primary driver flipped
     * to R2, instead of 404ing when the view hardcodes `Storage::disk('public')`.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (empty($this->avatar)) return null;
        try {
            return app(\App\Services\StorageManager::class)->resolveUrl($this->avatar) ?: null;
        } catch (\Throwable) {
            return asset('storage/' . ltrim($this->avatar, '/'));
        }
    }

    /**
     * Remove avatar + any per-photographer assets when a profile row is
     * deleted. Avatars used to be public-disk-only; with the R2 switchover
     * they can land on any configured cloud driver, so we:
     *
     *   1. Sweep the avatar path across every enabled driver via
     *      StorageManager::deleteAsset — covers both the old public-disk
     *      rows and new cloud rows with a single call.
     *   2. purgeDirectory() on `photographers/{user_id}` — nukes the whole
     *      tree (profile/portfolio/docs) on R2 + public in one sweep so
     *      nothing survives on any driver.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $profile) {
            $storage = app(\App\Services\StorageManager::class);

            if ($profile->avatar) {
                $storage->deleteAsset($profile->avatar);
            }
            if ($profile->user_id) {
                try {
                    $storage->purgeDirectory("photographers/{$profile->user_id}");
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        "PhotographerProfile#{$profile->id} directory purge failed: " . $e->getMessage()
                    );
                }
            }
        });
    }

    // ══════════════════════════════════════════════════════════════
    // Credit system relations + helpers
    // ══════════════════════════════════════════════════════════════

    /** All credit bundles this photographer has ever been granted. */
    public function creditBundles()
    {
        return $this->hasMany(PhotographerCreditBundle::class, 'photographer_id', 'user_id');
    }

    /** All credit ledger entries. */
    public function creditTransactions()
    {
        return $this->hasMany(CreditTransaction::class, 'photographer_id', 'user_id');
    }

    public function isCreditsMode(): bool
    {
        return ($this->billing_mode ?? self::BILLING_CREDITS) === self::BILLING_CREDITS;
    }

    public function isCommissionMode(): bool
    {
        return ($this->billing_mode ?? self::BILLING_CREDITS) === self::BILLING_COMMISSION;
    }

    // ══════════════════════════════════════════════════════════════
    // Subscription relations + helpers
    // ══════════════════════════════════════════════════════════════

    /** All subscription history rows for this photographer. */
    public function subscriptions()
    {
        return $this->hasMany(PhotographerSubscription::class, 'photographer_id', 'user_id');
    }

    /** The current subscription (denormalised pointer — avoids a join on hot paths). */
    public function currentSubscription()
    {
        return $this->belongsTo(PhotographerSubscription::class, 'current_subscription_id');
    }

    /** Resolve the current plan; falls back to the default free plan. */
    public function currentPlan(): ?SubscriptionPlan
    {
        $sub = $this->currentSubscription()->with('plan')->first();
        if ($sub && $sub->plan) return $sub->plan;
        return SubscriptionPlan::defaultFree();
    }

    public function hasActiveSubscription(): bool
    {
        return in_array($this->subscription_status, [
            PhotographerSubscription::STATUS_ACTIVE,
            PhotographerSubscription::STATUS_GRACE,
        ], true);
    }

    public function subscriptionHasFeature(string $feature): bool
    {
        $plan = $this->currentPlan();
        return $plan?->hasFeature($feature) ?? false;
    }
}
