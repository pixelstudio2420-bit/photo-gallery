<?php

namespace App\Services\Payout;

use App\Models\AppSetting;
use App\Models\CommissionTier;
use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the commission_rate (photographer keep %) to apply for a single
 * earned amount, applying the platform's tier system.
 *
 * Resolution order (highest-priority match wins):
 *   1. CommissionTier matched against the photographer's lifetime gross
 *      revenue. Highest tier the photographer has crossed wins.
 *   2. Photographer's individual `commission_rate` override on
 *      photographer_profiles.commission_rate (manually set by admin).
 *   3. Global default = 100 - app_settings.platform_commission (default 80%).
 *
 * Why this resolver?
 * ──────────────────
 * Before this service, OrderFulfillmentService read profile.commission_rate
 * directly. That meant a photographer who had earned ฿500k lifetime sat on
 * the same starting rate as a brand-new account, even when admin had
 * configured a tier system saying "earn ≥ ฿100k → keep 85%". Now the tier
 * is consulted FIRST, and the profile rate is only the fallback. The admin
 * can still pin a specific photographer's rate by setting the profile
 * column above any tier — handy for top-100 partners on bespoke contracts.
 *
 * Caching
 * ───────
 * Lifetime revenue is recalculated every 30 min (cache key per photographer).
 * That's tight enough to feel near-real-time for tier transitions, loose
 * enough that a 1k-payouts/day photographer doesn't trigger a SUM scan
 * on every paid order.
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

        // Tier-based rate (priority 1)
        $tierRate = $this->resolveTierRate($photographerId);

        // Profile override (priority 2). The DB stores `commission_rate` as
        // the photographer's KEEP rate (e.g. 80 → keep 80%, platform takes 20%).
        $profileRate = $profile && $profile->commission_rate !== null
            ? (float) $profile->commission_rate
            : null;

        // Global default (priority 3) — the historical fallback.
        $defaultRate = 100.0 - (float) AppSetting::get('platform_commission', 20);

        // Pick the photographer-friendliest of (tier, profile) — admin-set
        // profile.commission_rate is treated as a FLOOR: a manual VIP rate
        // is never undercut by an automatically-resolved tier.
        if ($tierRate !== null && $profileRate !== null) {
            return max($tierRate, $profileRate);
        }
        if ($tierRate !== null)    return $tierRate;
        if ($profileRate !== null) return $profileRate;
        return $defaultRate;
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
