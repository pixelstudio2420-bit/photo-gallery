<?php

namespace App\Services\Payout;

use App\Models\AppSetting;
use App\Models\CommissionTier;
use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the commission_rate (photographer keep %) to apply for a single
 * earned amount.
 *
 * Resolution model
 * ────────────────
 * The photographer's *active subscription plan* is the baseline:
 *   keep% = 100 - subscription_plans.commission_pct
 *
 * The tier system + profile override can ONLY raise that baseline (they
 * act as upward overrides — loyalty bonus / VIP rate). They cannot
 * undercut the plan rate, so a Pro-tier subscriber (kept 100%) doesn't
 * accidentally drop to an 80% global default.
 *
 * Resolution order (rate-wise; the function takes the MAX of these):
 *   1. Subscription plan baseline  ← NEW (this is where the plan
 *      commission_pct column finally has effect on real payouts)
 *   2. CommissionTier matched against lifetime revenue (loyalty bonus)
 *   3. Profile.commission_rate override (admin-pinned VIP rate)
 *   4. Global fallback when no plan is attached at all
 *      = 100 - app_settings.platform_commission (default 80%)
 *
 * Why the change
 * ──────────────
 * Before, the resolver ignored subscription_plans.commission_pct entirely
 * — the marketing pages showed a Pro tier with "0% commission" but the
 * actual money split read from a separate AppSetting + Tier ladder. That
 * meant:
 *   • Free-tier subscribers (commission_pct=30) still kept 80% by default
 *   • Pro/Business/Studio subscribers (commission_pct=0) showed
 *     "0% commission" in the UI but lost whatever the tier resolver said
 *
 * After this change, the Free plan's 30% commission_pct now actually
 * deducts 30% on every photo order, and Pro keeps 100% as advertised.
 *
 * Caching
 * ───────
 * Lifetime revenue: 30-min cache (existing).
 * Subscription-plan rate: not cached — reads a single indexed lookup
 * by user_id, so a SUM scan isn't involved.
 */
class CommissionResolver
{
    private const CACHE_TTL_SECONDS = 1800; // 30 min

    /**
     * Decide what % the photographer keeps on a new sale.
     *
     * @param  int  $photographerId   user_id of the photographer
     * @return float  Percentage (0..100) the photographer keeps after platform fee.
     */
    public function resolveKeepRate(int $photographerId): float
    {
        $profile = PhotographerProfile::where('user_id', $photographerId)->first();

        // 1. Plan baseline — this is the rate the photographer signed up
        //    for via their active subscription. If no subscription is
        //    attached, returns null and we fall through to the global
        //    default at the end.
        $planRate = $this->resolvePlanRate($photographerId, $profile);

        // 2. Tier rate (loyalty bonus based on lifetime revenue).
        $tierRate = $this->resolveTierRate($photographerId);

        // 3. Profile override (admin VIP pin). The DB stores
        //    `commission_rate` as the KEEP rate (80 → keep 80%, platform
        //    takes 20%).
        $profileRate = $profile && $profile->commission_rate !== null
            ? (float) $profile->commission_rate
            : null;

        // 4. Global fallback when no plan applies — the historical default.
        $defaultRate = 100.0 - (float) AppSetting::get('platform_commission', 20);

        // The plan rate is the AUTHORITATIVE baseline. Tier and profile
        // act as UPWARD overrides only — they raise the rate when the
        // photographer has earned a loyalty boost or is on a VIP contract,
        // but they cannot undercut the plan rate. (If a Free-plan user has
        // an admin-set 95% override, they keep 95% — admin takes precedence.
        // If they don't, they get exactly the 70% the Free plan defines.)
        $baseline = $planRate ?? $defaultRate;

        $rate = $baseline;
        if ($tierRate    !== null && $tierRate    > $rate) $rate = $tierRate;
        if ($profileRate !== null && $profileRate > $rate) $rate = $profileRate;

        return $rate;
    }

    /**
     * Look up the keep% from the photographer's currently-attached
     * subscription plan. Returns null when no `subscription_plan_code`
     * is set on the profile (i.e. brand-new signup or legacy account
     * predating the plan system) so the caller can fall back.
     *
     * The KEEP rate is `100 - commission_pct` because the plan column
     * stores the platform's TAKE percentage (the inverse). e.g. Free
     * plan with commission_pct=30 → keep 70%.
     */
    private function resolvePlanRate(int $photographerId, ?PhotographerProfile $profile): ?float
    {
        $code = $profile?->subscription_plan_code;
        if (!$code) {
            return null;
        }

        // Single indexed lookup; no caching layer here because the cost
        // is already trivial vs the rest of the resolver. If this becomes
        // a hotspot we can wrap it with Cache::remember keyed by code.
        $commissionPct = \Illuminate\Support\Facades\DB::table('subscription_plans')
            ->where('code', $code)
            ->where('is_active', 1)
            ->value('commission_pct');

        if ($commissionPct === null) {
            return null;
        }

        return 100.0 - (float) $commissionPct;
    }

    /**
     * Resolve the tier rate for a photographer by their lifetime revenue.
     * Returns null when no tier configuration exists or the photographer
     * hasn't crossed any tier's min_revenue.
     */
    public function resolveTierRate(int $photographerId): ?float
    {
        // No tiers configured → return null so the caller falls back.
        if (!CommissionTier::active()->exists()) {
            return null;
        }

        $lifetime = $this->lifetimeRevenue($photographerId);
        $tier     = CommissionTier::resolveForRevenue($lifetime);

        return $tier ? (float) $tier->commission_rate : null;
    }

    /**
     * Lifetime gross revenue (SUM of all paid PhotographerPayout.gross_amount
     * for this photographer). Cached to avoid scanning on every paid order.
     *
     * Counts both 'paid' and 'pending' rows because a pending row already
     * represents earned revenue (the customer paid; we just haven't
     * disbursed yet). 'reversed' is excluded since it represents a chargeback.
     */
    public function lifetimeRevenue(int $photographerId): float
    {
        return (float) Cache::remember(
            "commission.lifetime_revenue.{$photographerId}",
            self::CACHE_TTL_SECONDS,
            fn () => (float) PhotographerPayout::query()
                ->where('photographer_id', $photographerId)
                ->whereIn('status', ['pending', 'paid'])
                ->sum('gross_amount'),
        );
    }

    /**
     * Invalidate the cached lifetime revenue for a photographer. Called by
     * model events on PhotographerPayout (create / update) so the next
     * resolver call sees the fresh number. Also exposed for ad-hoc admin
     * "recompute" actions.
     */
    public function invalidate(int $photographerId): void
    {
        Cache::forget("commission.lifetime_revenue.{$photographerId}");
    }
}
