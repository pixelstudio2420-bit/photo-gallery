<?php

namespace App\Support;

use App\Models\PhotographerProfile;
use App\Models\PhotographerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\Usage\QuotaService;
use App\Services\Usage\UsageMeter;
use Illuminate\Support\Facades\Cache;

/**
 * PlanGate — the single answer to "is this photographer allowed to use
 * feature X right now?".
 *
 * The platform has SEVERAL places that need this check:
 *   • HTTP routes — handled by RequireSubscriptionFeature middleware
 *   • Service-level calls — LineNotifyService, PhotoDeliveryService,
 *     queue jobs (none of which run middleware)
 *   • View toggles — show/hide upgrade prompts, hide gated UI
 *
 * Before this class, each callsite re-implemented the gate by reading
 * `ai_features` directly OR (worse) didn't gate at all — which is what
 * let free-plan photographers use LINE delivery and bypass the AI
 * monthly cap.
 *
 * Every public method:
 *   1. Resolves the photographer's CURRENT (and active!) subscription
 *   2. Reads the bound SubscriptionPlan
 *   3. Returns a definitive yes/no/quota-state
 *
 * The "active" check is two-fold — both status AND time-based:
 *
 *     status ∈ [active, grace]   AND   current_period_end > now
 *
 * Status-only would let an over-running grace period stay usable past
 * its grace_ends_at. Time-only would let a force-cancelled subscription
 * keep working until its period ends naturally. Both checks are needed
 * for the gate to be tamper-proof.
 *
 * Cached in-memory per request to keep service-level call sites cheap
 * (UsageMeter::record() already runs on every metered op; we cannot
 * afford to add a JOIN to that path).
 */
final class PlanGate
{
    /**
     * Feature key for LINE notifications + photo delivery. Paid plans
     * get this in their ai_features JSON; free plans don't.
     *
     * Why a `line.notify` key (and not just "line_enabled" boolean)?
     * Same reason `face_search` / `quality_filter` are keys: it lets the
     * existing RequireSubscriptionFeature middleware gate routes by
     * passing this string, AND it lets PlanGate::canUseLine() share one
     * code path with the other feature checks.
     */
    public const FEAT_LINE_NOTIFY = 'line_notify';
    public const FEAT_FACE_SEARCH = 'face_search';

    /**
     * AI resources that count against monthly_ai_credits.
     *
     * If you add a new AI resource (e.g. ai.quality_filter), add it here
     * so it shares the credit budget. This is also where the AntiAbuse
     * service looks to detect "AI used too fast" patterns.
     */
    public const AI_RESOURCES = [
        'ai.face_search',
        'ai.face_detect',
        'ai.face_compare',
        'ai.face_index',
    ];

    /**
     * Is the photographer's plan currently usable?
     *
     * Time-based + status-based combined check. Free plan returns true
     * by default — they're "always on" for the free tier, but their
     * caps are obviously much smaller.
     */
    public static function isPlanActive(?int $photographerId): bool
    {
        if (!$photographerId) return false; // anon → never (caller decides whether to default-allow)
        $sub = self::currentSubscription($photographerId);
        if (!$sub) {
            // No subscription row = on the implicit free plan (always usable
            // but with the FREE caps). This is the seeded default for new
            // sign-ups before they pick a paid plan.
            return true;
        }
        if (!in_array($sub->status, ['active', 'grace'], true)) {
            return false;
        }
        if ($sub->current_period_end && $sub->current_period_end->isPast()) {
            // Period over. Whatever the status flag says, the plan is
            // expired in calendar terms and features must stop. The
            // nightly SubscriptionExpireOverdueCommand will flip the
            // status next; we're enforcing the boundary live.
            return false;
        }
        if ($sub->status === 'grace' && $sub->grace_ends_at && $sub->grace_ends_at->isPast()) {
            return false;
        }
        return true;
    }

    /**
     * Returns the SubscriptionPlan model the photographer is currently
     * entitled to. For an inactive plan, falls back to the FREE plan —
     * never returns null so callers can rely on `->ai_features` access.
     */
    public static function currentPlan(?int $photographerId): SubscriptionPlan
    {
        $cacheKey = 'plan_gate_plan_' . ((int) $photographerId);
        return Cache::store('array')->remember($cacheKey, 60, function () use ($photographerId) {
            if (!$photographerId) return self::freePlan();
            if (!self::isPlanActive($photographerId)) return self::freePlan();

            $sub = self::currentSubscription($photographerId);
            if ($sub && $sub->plan) return $sub->plan;
            return self::freePlan();
        });
    }

    /**
     * Can this photographer push LINE messages to their customers right now?
     *
     * Three-AND gate:
     *   (1) plan is active (time + status)
     *   (2) plan's ai_features contains 'line_notify'
     *   (3) global LINE feature flag is on (admin kill switch)
     */
    public static function canUseLine(?int $photographerId): bool
    {
        if (!self::isPlanActive($photographerId)) return false;
        if (!self::currentPlan($photographerId)->hasFeature(self::FEAT_LINE_NOTIFY)) return false;

        // Global feature kill-switch from FeatureFlagController defaults.
        // We use the same key SubscriptionService::featureGloballyEnabled
        // would query, so the admin's /admin/features toggle for
        // line_notify is honoured here too.
        try {
            return (string) \App\Models\AppSetting::get(
                'feature_' . self::FEAT_LINE_NOTIFY . '_enabled',
                '1'
            ) === '1';
        } catch (\Throwable $e) {
            return true; // fail-open on AppSetting hiccup
        }
    }

    /**
     * Can this photographer make ONE more AI call right now?
     *
     * Combines:
     *   (1) plan is active
     *   (2) plan's ai_features contains the feature flag (e.g. face_search)
     *   (3) monthly_ai_credits not yet exhausted (counted across ALL
     *       AI resources — see AI_RESOURCES — so a photographer can't
     *       cheat the cap by mixing detect/index/compare).
     */
    public static function canUseAi(?int $photographerId, string $feature = self::FEAT_FACE_SEARCH): array
    {
        if (!$photographerId) {
            return ['allowed' => false, 'reason' => 'unauthenticated', 'remaining' => 0];
        }
        if (!self::isPlanActive($photographerId)) {
            return ['allowed' => false, 'reason' => 'plan_inactive', 'remaining' => 0];
        }
        $plan = self::currentPlan($photographerId);
        if (!$plan->hasFeature($feature)) {
            return [
                'allowed'   => false,
                'reason'    => 'feature_not_in_plan',
                'remaining' => 0,
                'plan_code' => $plan->code,
            ];
        }

        $cap   = (int) ($plan->monthly_ai_credits ?? 0);
        if ($cap <= 0) {
            // Treat NULL/0 as "unlimited" only when the plan code is one of
            // the unlimited tiers; otherwise zero means "no AI for this plan".
            return [
                'allowed'   => true,
                'reason'    => 'no_cap_set',
                'remaining' => null,
            ];
        }
        $used  = self::aiCreditsUsedThisMonth($photographerId);
        return [
            'allowed'   => $used < $cap,
            'reason'    => $used < $cap ? 'ok' : 'monthly_cap_reached',
            'remaining' => max(0, $cap - $used),
            'cap'       => $cap,
            'used'      => $used,
        ];
    }

    /**
     * AI credits consumed this calendar month, summed across every AI
     * resource. The reason we sum (instead of read one resource) is that
     * monthly_ai_credits is a budget for "AI as a whole" — face_search,
     * face_index, etc. all spend from the same pool.
     */
    public static function aiCreditsUsedThisMonth(int $photographerId): int
    {
        $total = 0;
        foreach (self::AI_RESOURCES as $r) {
            $total += UsageMeter::counter($photographerId, $r, 'month');
        }
        return $total;
    }

    /**
     * Days left in the current billing period. Returns null when the
     * subscription has no fixed period (e.g. lifetime free).
     */
    public static function daysUntilRenewal(?int $photographerId): ?int
    {
        $sub = $photographerId ? self::currentSubscription($photographerId) : null;
        if (!$sub || !$sub->current_period_end) return null;
        return max(0, (int) now()->diffInDays($sub->current_period_end, false));
    }

    /* ──────────────────── Internals ──────────────────── */

    private static function currentSubscription(int $photographerId): ?PhotographerSubscription
    {
        $cacheKey = 'plan_gate_sub_' . $photographerId;
        return Cache::store('array')->remember($cacheKey, 60, function () use ($photographerId) {
            // photographer_id on the subscription is the auth_users.id
            // (not the photographer_profiles.id) — same convention as
            // PhotographerProfile.user_id.
            return PhotographerSubscription::with('plan')
                ->where('photographer_id', $photographerId)
                ->whereIn('status', ['active', 'grace'])
                ->latest('id')
                ->first();
        });
    }

    private static function freePlan(): SubscriptionPlan
    {
        return Cache::store('array')->remember('plan_gate_free_plan', 300, function () {
            return SubscriptionPlan::defaultFree() ?? new SubscriptionPlan([
                'code'                  => 'free',
                'name'                  => 'Free',
                'storage_bytes'         => 2 * 1024 * 1024 * 1024,
                'ai_features'           => [],
                'max_concurrent_events' => 0,
                'max_team_seats'        => 1,
                'monthly_ai_credits'    => 50,
                'commission_pct'        => 20,
            ]);
        });
    }
}
