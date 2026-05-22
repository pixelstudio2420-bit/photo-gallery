<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Estimate Cloudflare R2 storage cost per photographer + org-wide.
 *
 * R2 pricing is straightforward:
 *   • $0.015 / GB / month for storage at rest
 *   • $0.00 for egress (no bandwidth charges)
 *   • $4.50 / million Class A operations (writes)
 *   • $0.36 / million Class B operations (reads)
 *
 * For this estimator we focus on the storage line — that's the part
 * the platform pays continuously regardless of activity. Operations
 * cost is negligible compared to bulk storage for a photo platform.
 *
 * Read by the admin dashboard widget so ops can see "this Free
 * photographer with 80 GB costs us $1.20/month and pays $0/month —
 * dump them via retention".
 *
 * Cached 5 min so repeated dashboard hits don't run the SUM query.
 */
class R2CostEstimatorService
{
    private const CACHE_KEY = 'admin.r2_cost_estimate.v1';
    private const CACHE_TTL = 300;

    /**
     * Compute the per-photographer cost breakdown.
     *
     * @return Collection<int, array> Each row:
     *   [
     *     'user_id'              => int,
     *     'display_name'         => string,
     *     'tier'                 => string,            // creator|seller|pro
     *     'plan_code'            => string,            // free|starter|lite|pro|...
     *     'storage_used_bytes'   => int,
     *     'storage_used_gb'      => float,
     *     'storage_quota_bytes'  => int,
     *     'monthly_cost_usd'     => float,             // bytes × rate
     *     'monthly_revenue_thb'  => float,             // subscription thb (0 for free)
     *     'monthly_revenue_usd'  => float,             // converted at rate below
     *     'gap_usd'              => float,             // cost - revenue (positive = losing money)
     *     'over_quota'           => bool,
     *   ]
     */
    public function perPhotographer(int $limit = 50): Collection
    {
        return Cache::remember(self::CACHE_KEY . ":limit={$limit}", self::CACHE_TTL, function () use ($limit) {
            $costPerGb = (float) AppSetting::get('r2_cost_per_gb_month_usd', 0.015);
            $thbPerUsd = (float) AppSetting::get('usd_thb_rate', 34.5); // for revenue conversion

            // One query: profile + active subscription (if any) + plan price.
            // LEFT JOIN so photographers with no sub still show up (free).
            $rows = DB::table('photographer_profiles as pp')
                ->leftJoin('photographer_subscriptions as ps', function ($j) {
                    $j->on('ps.photographer_id', '=', 'pp.user_id')
                      ->whereIn('ps.status', ['active', 'grace']);
                })
                ->leftJoin('subscription_plans as sp', 'sp.id', '=', 'ps.plan_id')
                ->select(
                    'pp.user_id',
                    'pp.display_name',
                    'pp.tier',
                    'pp.storage_used_bytes',
                    'pp.storage_quota_bytes',
                    DB::raw('COALESCE(sp.code, ?) AS plan_code'),
                    DB::raw('COALESCE(sp.price_thb, 0) AS plan_price_thb')
                )
                ->setBindings(['free'], 'select')
                ->where('pp.status', 'approved')
                ->orderByDesc('pp.storage_used_bytes')
                ->limit($limit)
                ->get();

            return $rows->map(function ($r) use ($costPerGb, $thbPerUsd) {
                $bytes = (int) ($r->storage_used_bytes ?? 0);
                $gb    = $bytes / (1024 ** 3);
                $cost  = round($gb * $costPerGb, 4);
                $revThb = (float) ($r->plan_price_thb ?? 0);
                $revUsd = round($revThb / max($thbPerUsd, 1), 4);
                $quota = (int) ($r->storage_quota_bytes ?? 0);
                return [
                    'user_id'              => (int) $r->user_id,
                    'display_name'         => (string) ($r->display_name ?? '(no name)'),
                    'tier'                 => (string) ($r->tier ?? '-'),
                    'plan_code'            => (string) ($r->plan_code ?? 'free'),
                    'storage_used_bytes'   => $bytes,
                    'storage_used_gb'      => round($gb, 2),
                    'storage_quota_bytes'  => $quota,
                    'storage_quota_gb'     => round($quota / (1024 ** 3), 2),
                    'monthly_cost_usd'     => $cost,
                    'monthly_revenue_thb'  => $revThb,
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
        return Cache::remember(self::CACHE_KEY . ':org', self::CACHE_TTL, function () {
            $costPerGb = (float) AppSetting::get('r2_cost_per_gb_month_usd', 0.015);
            $thbPerUsd = (float) AppSetting::get('usd_thb_rate', 34.5);

            $row = DB::table('photographer_profiles as pp')
                ->leftJoin('photographer_subscriptions as ps', function ($j) {
                    $j->on('ps.photographer_id', '=', 'pp.user_id')
                      ->whereIn('ps.status', ['active', 'grace']);
                })
                ->leftJoin('subscription_plans as sp', 'sp.id', '=', 'ps.plan_id')
                ->where('pp.status', 'approved')
                ->selectRaw('
                    COUNT(DISTINCT pp.user_id) AS photographer_count,
                    COALESCE(SUM(pp.storage_used_bytes), 0) AS total_bytes,
                    COALESCE(SUM(sp.price_thb), 0) AS total_revenue_thb
                ')
                ->first();

            $totalBytes = (int) ($row->total_bytes ?? 0);
            $totalGb    = $totalBytes / (1024 ** 3);
            $totalCostUsd  = round($totalGb * $costPerGb, 2);
            $totalRevUsd   = round(((float) ($row->total_revenue_thb ?? 0)) / max($thbPerUsd, 1), 2);

            return [
                'photographer_count' => (int) ($row->photographer_count ?? 0),
                'total_bytes'        => $totalBytes,
                'total_gb'           => round($totalGb, 2),
                'total_cost_usd'     => $totalCostUsd,
                'total_revenue_thb'  => (float) ($row->total_revenue_thb ?? 0),
                'total_revenue_usd'  => $totalRevUsd,
                'gap_usd'            => round($totalCostUsd - $totalRevUsd, 2),
                'usd_thb_rate'       => $thbPerUsd,
                'cost_per_gb_usd'    => $costPerGb,
            ];
        });
    }

    /**
     * Project how much R2 spend the retention engine would recover
     * if it ran today — based on event_photos files older than the
     * per-tier retention_days threshold. Cheaper than running the
     * full purge command; uses a single SUM query.
     *
     * Output values are USD/month savings projected after the next
     * full purge cycle settles (originals removed, derivatives kept
     * per portfolio mode).
     */
    public function projectedSavings(): array
    {
        return Cache::remember(self::CACHE_KEY . ':projected', self::CACHE_TTL, function () {
            $costPerGb = (float) AppSetting::get('r2_cost_per_gb_month_usd', 0.015);
            $derivativeMultiplier = (float) AppSetting::get('r2_derivative_multiplier', 0.04);

            // Bytes that would be reclaimed if portfolio-mode purge
            // ran NOW = sum of original file bytes on events past
            // retention threshold (using global default; per-tier
            // adjustment skipped here to keep the projection cheap).
            $defaultDays = (int) AppSetting::get('event_default_retention_days', 90);
            $cutoff = now()->subDays($defaultDays);

            // Note: event_photos uses `file_size` (not file_size_bytes) and has
            // no `deleted_at` column on this schema — soft delete is signalled
            // by `status IN ('deleted','removed')` instead. We just exclude
            // those rows so the projection only counts photos still in R2.
            $row = DB::table('event_photos as ep')
                ->join('event_events as ee', 'ee.id', '=', 'ep.event_id')
                ->where('ee.created_at', '<', $cutoff)
                ->whereNotIn('ep.status', ['deleted', 'removed'])
                ->selectRaw('COALESCE(SUM(ep.file_size), 0) AS old_bytes')
                ->first();

            $oldBytes = (int) ($row->old_bytes ?? 0);
            $oldGb    = $oldBytes / (1024 ** 3);
            $savingsUsdPortfolio = round($oldGb * $costPerGb * (1 - $derivativeMultiplier), 2);
            $savingsUsdFull      = round($oldGb * $costPerGb, 2);

            return [
                'old_bytes'             => $oldBytes,
                'old_gb'                => round($oldGb, 2),
                'savings_usd_portfolio' => $savingsUsdPortfolio, // ~96% recovered
                'savings_usd_full'      => $savingsUsdFull,      // 100% recovered
                'retention_days'        => $defaultDays,
            ];
        });
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY . ':org');
        Cache::forget(self::CACHE_KEY . ':projected');
        // Wipe per-limit variations via brute pattern — common limits.
        foreach ([10, 20, 50, 100, 200] as $n) {
            Cache::forget(self::CACHE_KEY . ":limit={$n}");
        }
    }
}
