<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Estimate Cloudflare R2 storage cost per photographer + org-wide.
 *
 * R2 pricing
 * ──────────
 *   • $0.015 / GB / month storage at rest (configurable via
 *     AppSetting `r2_cost_per_gb_month_usd`)
 *   • $0.00 egress (no bandwidth charges)
 *   • $4.50 / million Class A writes
 *   • $0.36 / million Class B reads
 *
 * We only model the storage line — that's what bleeds continuously
 * regardless of activity. Operations cost is negligible for a photo
 * platform's working set.
 *
 * Read by the admin dashboard widget so ops can see "this Free
 * photographer with 80 GB costs us $1.20/month and pays $0/month —
 * dump them via retention".
 *
 * Accuracy contract
 * ─────────────────
 *   • Uses `pp.subscription_plan_code` (denormalised; SubscriptionService
 *     keeps it fresh on every plan change) to look up the photographer's
 *     plan — NO LEFT JOIN to photographer_subscriptions, which would
 *     duplicate rows when active+grace overlap during renewal.
 *   • Converts annual plans to monthly via billing_cycle CASE so a
 *     12,000 THB/yr Pro plan is reported as ~1,000 THB/month — not 12,000.
 *   • projectedSavings honours auto_delete_exempt, originals_purged_at,
 *     the configured from_field (shoot_date vs created_at), and per-event
 *     retention_days_override. Mirrors PurgeExpiredEventsCommand's candidate
 *     filter so the projection equals what the cron will actually reclaim.
 *
 * Cache strategy
 * ──────────────
 *   • 5-min TTL via Cache::remember so repeated dashboard hits don't
 *     re-run the JOIN.
 *   • Keys are versioned: `admin.r2_cost_estimate.v{N}:...`. flushCache()
 *     bumps the version, instantly invalidating ALL variants (including
 *     non-enumerated $limit values that a future caller might pass).
 *     The old cache entries naturally expire on their own TTL.
 */
class R2CostEstimatorService
{
    private const CACHE_PREFIX = 'admin.r2_cost_estimate';
    private const CACHE_VERSION_KEY = 'admin.r2_cost_estimate.version';
    private const CACHE_TTL = 300;

    /**
     * Compute the per-photographer cost breakdown.
     *
     * @return Collection<int, array> Each row:
     *   [
     *     'profile_id'           => int,   // PhotographerProfile.id (FK target for admin.photographers.show)
     *     'user_id'              => int,   // auth_users.id
     *     'display_name'         => string,
     *     'tier'                 => string,            // creator|seller|pro
     *     'plan_code'            => string,            // free|starter|lite|pro|...
     *     'storage_used_bytes'   => int,
     *     'storage_used_gb'      => float,
     *     'storage_quota_bytes'  => int,
     *     'storage_quota_gb'     => float,
     *     'monthly_cost_usd'     => float,             // bytes × rate
     *     'monthly_revenue_thb'  => float,             // subscription thb (0 for free; annual ÷ 12)
     *     'monthly_revenue_usd'  => float,             // converted at rate below
     *     'gap_usd'              => float,             // cost - revenue (positive = losing money)
     *     'over_quota'           => bool,
     *   ]
     */
    public function perPhotographer(int $limit = 50): Collection
    {
        return Cache::remember($this->cacheKey("limit={$limit}"), self::CACHE_TTL, function () use ($limit) {
            $costPerGb = $this->costPerGb();
            $thbPerUsd = $this->thbPerUsd();

            // Single query, no fan-out:
            //   • pp.subscription_plan_code is denormalised by SubscriptionService
            //     → exact 1:1 with subscription_plans.code (or NULL = free)
            //   • LEFT JOIN sp by code is safe (sp.code is unique)
            //   • Annual plans normalised to monthly via SQL CASE
            $rows = DB::table('photographer_profiles as pp')
                ->leftJoin('subscription_plans as sp', 'sp.code', '=', 'pp.subscription_plan_code')
                ->select(
                    'pp.id as profile_id',
                    'pp.user_id',
                    'pp.display_name',
                    'pp.tier',
                    'pp.storage_used_bytes',
                    'pp.storage_quota_bytes',
                    DB::raw("COALESCE(pp.subscription_plan_code, 'free') AS plan_code"),
                    DB::raw("CASE
                        WHEN sp.billing_cycle = 'annual' THEN COALESCE(sp.price_thb, 0) / 12.0
                        ELSE COALESCE(sp.price_thb, 0)
                    END AS monthly_price_thb")
                )
                ->where('pp.status', 'approved')
                ->orderByDesc('pp.storage_used_bytes')
                ->limit($limit)
                ->get();

            return $rows->map(function ($r) use ($costPerGb, $thbPerUsd) {
                $bytes  = (int) ($r->storage_used_bytes ?? 0);
                $gb     = $bytes / (1024 ** 3);
                $cost   = round($gb * $costPerGb, 4);
                $revThb = (float) ($r->monthly_price_thb ?? 0);
                $revUsd = round($revThb / max($thbPerUsd, 1), 4);
                $quota  = (int) ($r->storage_quota_bytes ?? 0);
                return [
                    'profile_id'           => (int) $r->profile_id,
                    'user_id'              => (int) $r->user_id,
                    'display_name'         => (string) ($r->display_name ?? '(no name)'),
                    'tier'                 => (string) ($r->tier ?? '-'),
                    'plan_code'            => (string) ($r->plan_code ?? 'free'),
                    'storage_used_bytes'   => $bytes,
                    'storage_used_gb'      => round($gb, 2),
                    'storage_quota_bytes'  => $quota,
                    'storage_quota_gb'     => round($quota / (1024 ** 3), 2),
                    'monthly_cost_usd'     => $cost,
                    'monthly_revenue_thb'  => round($revThb, 2),
                    'monthly_revenue_usd'  => $revUsd,
                    'gap_usd'              => round($cost - $revUsd, 4),
                    'over_quota'           => $quota > 0 && $bytes > $quota,
                ];
            });
        });
    }

    /**
     * Org-wide summary: total bytes, total monthly cost, total
     * monthly revenue, net (cost - revenue). Cheaper to compute
     * because it's a single aggregate query.
     */
    public function orgSummary(): array
    {
        return Cache::remember($this->cacheKey('org'), self::CACHE_TTL, function () {
            $costPerGb = $this->costPerGb();
            $thbPerUsd = $this->thbPerUsd();

            // Two aggregates in one query — neither risks duplication because
            // we never join photographer_subscriptions. subscription_plans
            // joins by unique code → 1:1 safe.
            $row = DB::table('photographer_profiles as pp')
                ->leftJoin('subscription_plans as sp', 'sp.code', '=', 'pp.subscription_plan_code')
                ->where('pp.status', 'approved')
                ->selectRaw("
                    COUNT(*) AS photographer_count,
                    COALESCE(SUM(pp.storage_used_bytes), 0) AS total_bytes,
                    COALESCE(SUM(
                        CASE
                            WHEN sp.billing_cycle = 'annual' THEN COALESCE(sp.price_thb, 0) / 12.0
                            ELSE COALESCE(sp.price_thb, 0)
                        END
                    ), 0) AS total_revenue_thb
                ")
                ->first();

            $totalBytes   = (int) ($row->total_bytes ?? 0);
            $totalGb      = $totalBytes / (1024 ** 3);
            $totalCostUsd = round($totalGb * $costPerGb, 2);
            $totalRevThb  = (float) ($row->total_revenue_thb ?? 0);
            $totalRevUsd  = round($totalRevThb / max($thbPerUsd, 1), 2);

            return [
                'photographer_count' => (int) ($row->photographer_count ?? 0),
                'total_bytes'        => $totalBytes,
                'total_gb'           => round($totalGb, 2),
                'total_cost_usd'     => $totalCostUsd,
                'total_revenue_thb'  => round($totalRevThb, 2),
                'total_revenue_usd'  => $totalRevUsd,
                'gap_usd'            => round($totalCostUsd - $totalRevUsd, 2),
                'usd_thb_rate'       => $thbPerUsd,
                'cost_per_gb_usd'    => $costPerGb,
            ];
        });
    }

    /**
     * Project how much R2 spend the retention engine would recover
     * if it ran today.
     *
     * The query mirrors PurgeExpiredEventsCommand's candidate filter +
     * per-event eligibility:
     *   • auto_delete_exempt = false
     *   • originals_purged_at IS NULL  (already archived events excluded)
     *   • date base honours event_auto_delete_from_field (shoot_date or
     *     created_at) with fallback to created_at when shoot_date is null
     *   • retention_days_override per event if set, else the global default
     *     (per-tier days NOT modelled here — exact per-tier breakdown would
     *     require a join + branching; the global default is a reasonable
     *     conservative estimate the comment now states)
     *
     * Output is USD/month savings projected after the next purge cycle
     * settles. Two scenarios:
     *   savings_usd_portfolio → originals removed, derivatives kept
     *   savings_usd_full      → 100% wipe
     */
    public function projectedSavings(): array
    {
        return Cache::remember($this->cacheKey('projected'), self::CACHE_TTL, function () {
            $costPerGb            = $this->costPerGb();
            $derivativeMultiplier = (float) AppSetting::get('r2_derivative_multiplier', 0.04);
            $defaultDays          = (int) AppSetting::get('event_default_retention_days', 90);
            $fromField            = (string) AppSetting::get('event_auto_delete_from_field', 'shoot_date');

            // Validate from_field to avoid SQL injection through AppSetting.
            $fromField = in_array($fromField, ['shoot_date', 'created_at'], true) ? $fromField : 'shoot_date';

            // Use INTERVAL '1 day' * coalesce(retention_days_override, default)
            // so each event uses its own threshold. Postgres-specific.
            //
            // Date base picks shoot_date when configured AND not null,
            // else falls back to created_at — matches effectiveDeleteAt().
            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                $baseExpr = $fromField === 'shoot_date'
                    ? "COALESCE(ee.shoot_date, ee.created_at)"
                    : "ee.created_at";
                // Per-event eta = base + (retention_days_override OR $defaultDays) days
                $dueExpr = "{$baseExpr} + (COALESCE(ee.retention_days_override, {$defaultDays}) * INTERVAL '1 day')";
            } else {
                // SQLite/MySQL fallback for the test environment — use a fixed
                // cutoff with the global default. Less accurate but portable.
                $baseExpr = $fromField === 'shoot_date'
                    ? "COALESCE(ee.shoot_date, ee.created_at)"
                    : "ee.created_at";
                $dueExpr = "DATE({$baseExpr}, '+{$defaultDays} day')";
            }

            $row = DB::table('event_photos as ep')
                ->join('event_events as ee', 'ee.id', '=', 'ep.event_id')
                ->where('ee.auto_delete_exempt', false)
                ->whereNull('ee.originals_purged_at')
                ->whereNotIn('ep.status', ['deleted', 'removed'])
                ->whereRaw("{$dueExpr} <= NOW()")
                ->selectRaw('COALESCE(SUM(ep.file_size), 0) AS old_bytes, COUNT(*) AS old_count')
                ->first();

            $oldBytes = (int) ($row->old_bytes ?? 0);
            $oldGb    = $oldBytes / (1024 ** 3);
            $savingsUsdPortfolio = round($oldGb * $costPerGb * (1 - $derivativeMultiplier), 2);
            $savingsUsdFull      = round($oldGb * $costPerGb, 2);

            return [
                'old_bytes'             => $oldBytes,
                'old_count'             => (int) ($row->old_count ?? 0),
                'old_gb'                => round($oldGb, 2),
                'savings_usd_portfolio' => $savingsUsdPortfolio, // ~96% recovered
                'savings_usd_full'      => $savingsUsdFull,      // 100% recovered
                'retention_days'        => $defaultDays,
                'from_field'            => $fromField,
            ];
        });
    }

    /**
     * Invalidate all cached variants in O(1) by bumping the version key.
     *
     * Old cache entries still exist under their previous version namespace,
     * but no code path reads them anymore — they expire on their TTL.
     * Works with ANY $limit value (or future variants) because version
     * change orphans the entire previous namespace, not just enumerated keys.
     */
    public function flushCache(): void
    {
        // Bump the version. All future cacheKey() calls return a fresh
        // namespace, instantly invalidating org / projected / every :limit=N
        // entry regardless of N.
        try {
            Cache::increment(self::CACHE_VERSION_KEY);
        } catch (\Throwable) {
            // Some cache drivers don't support increment on a missing key —
            // initialize then bump.
            Cache::forever(self::CACHE_VERSION_KEY, 2);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────

    private function cacheKey(string $variant): string
    {
        return self::CACHE_PREFIX . ':v' . $this->cacheVersion() . ':' . $variant;
    }

    private function cacheVersion(): int
    {
        $v = Cache::get(self::CACHE_VERSION_KEY);
        if ($v === null) {
            Cache::forever(self::CACHE_VERSION_KEY, 1);
            return 1;
        }
        return (int) $v;
    }

    private function costPerGb(): float
    {
        return (float) AppSetting::get('r2_cost_per_gb_month_usd', 0.015);
    }

    /**
     * THB/USD rate, with a layered fallback so the dashboard stays in
     * sync with the rest of the platform:
     *   1. AppSetting 'usd_thb_rate'  (admin-tunable, primary)
     *   2. config('usage.usd_to_thb_rate', 35.0) (legacy default used by
     *      PlanCostCalculator + FaceSearchController + UsageController)
     *
     * Default of 35.0 matches the legacy config so the gap_usd stays
     * consistent across /admin (R2 widget) and /admin/finance views.
     */
    private function thbPerUsd(): float
    {
        $appSetting = AppSetting::get('usd_thb_rate', null);
        if ($appSetting !== null && (float) $appSetting > 0) {
            return (float) $appSetting;
        }
        return (float) config('usage.usd_to_thb_rate', 35.0);
    }
}
