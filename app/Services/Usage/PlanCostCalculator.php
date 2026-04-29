<?php

namespace App\Services\Usage;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PlanCostCalculator — answers two questions every SaaS founder asks:
 *
 *   1. "How much is this user costing us this month?"
 *   2. "Is plan X profitable on average?"
 *
 * Both come from aggregating usage_events:
 *
 *   user_cost_microcents  = SUM(cost_microcents) WHERE user_id = ? AND month = ?
 *   plan_revenue          = (subscriptions.amount_thb × users_on_plan)
 *   plan_cost             = SUM(cost_microcents) WHERE plan_code = ? AND month = ?
 *   plan_margin_pct       = (revenue - cost) / revenue × 100
 *
 * Storage is special — it's stored as bytes in usage_counters, so we
 * amortise it at report time using the configured per-byte-month rate.
 */
class PlanCostCalculator
{
    /**
     * What a single user has cost the platform this month, in THB.
     *
     * Returns an array of resource → cost so you can see "user X is
     * costing us ฿80/month, mostly on AI". USD-microcents are converted
     * to THB at a configurable conversion rate (default ฿35/USD).
     */
    public function userCostThb(int $userId, ?Carbon $forMonth = null): array
    {
        $period = ($forMonth ?? now())->format('Y-m');

        $byResource = DB::table('usage_events')
            ->where('user_id', $userId)
            ->whereRaw("to_char(occurred_at, 'YYYY-MM') = ?", [$period])
            ->groupBy('resource')
            ->selectRaw('resource, SUM(cost_microcents) AS cost_microcents, SUM(units) AS units')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->resource => [
                'units' => (int) $r->units,
                'thb'   => $this->microcentsToThb((int) $r->cost_microcents),
            ]])
            ->all();

        // Storage cost — amortised from the bytes counter.
        $byResource['storage.amortized'] = [
            'units' => UsageMeter::lifetime($userId, 'storage.bytes'),
            'thb'   => $this->storageCostThb(
                UsageMeter::lifetime($userId, 'storage.bytes'),
                fractionOfMonth: $this->fractionOfMonthSoFar($forMonth),
            ),
        ];

        $totalThb = array_sum(array_column($byResource, 'thb'));

        return [
            'user_id'  => $userId,
            'period'   => $period,
            'total'    => round($totalThb, 4),
            'by_resource' => $byResource,
        ];
    }

    /**
     * Aggregate margin per plan for a given month.
     *
     * @return array<int, array{plan_code:string,subs:int,revenue_thb:float,cost_thb:float,margin_thb:float,margin_pct:?float,risk:string}>
     */
    public function planMargins(?Carbon $forMonth = null): array
    {
        $period = ($forMonth ?? now())->format('Y-m');

        $revenuePerPlan = DB::table('photographer_subscriptions as s')
            ->leftJoin('subscription_plans as p', 'p.id', '=', 's.subscription_plan_id')
            ->where('s.status', 'active')
            ->groupBy('p.plan_code')
            ->selectRaw("p.plan_code, COUNT(*) AS subs, COALESCE(SUM(p.price_thb), 0) AS revenue")
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->plan_code => [
                'subs'    => (int) $r->subs,
                'revenue' => (float) $r->revenue,
            ]])
            ->all();

        $costPerPlan = DB::table('usage_events')
            ->whereRaw("to_char(occurred_at, 'YYYY-MM') = ?", [$period])
            ->groupBy('plan_code')
            ->selectRaw('plan_code, SUM(cost_microcents) AS cost_microcents')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->plan_code => $this->microcentsToThb((int) $r->cost_microcents)])
            ->all();

        // Add storage amortisation per plan.
        $storageBytesPerPlan = DB::table('usage_counters as c')
            ->join('photographer_profiles as p', 'p.user_id', '=', 'c.user_id')
            ->where('c.resource', 'storage.bytes')
            ->where('c.period', 'month')
            ->groupBy('p.subscription_plan_code')
            ->selectRaw('p.subscription_plan_code AS plan_code, SUM(c.units) AS bytes')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->plan_code => (int) $r->bytes])
            ->all();

        $rows = [];
        foreach (array_keys($revenuePerPlan + $costPerPlan + $storageBytesPerPlan) as $planCode) {
            $rev      = (float) ($revenuePerPlan[$planCode]['revenue'] ?? 0);
            $costAi   = (float) ($costPerPlan[$planCode] ?? 0);
            $costStor = $this->storageCostThb($storageBytesPerPlan[$planCode] ?? 0, 1.0);
            $cost     = $costAi + $costStor;
            $margin   = $rev - $cost;

            $rows[] = [
                'plan_code'   => $planCode,
                'subs'        => (int) ($revenuePerPlan[$planCode]['subs'] ?? 0),
                'revenue_thb' => round($rev, 2),
                'cost_thb'    => round($cost, 2),
                'cost_breakdown' => [
                    'ai_and_ops_thb' => round($costAi, 2),
                    'storage_thb'    => round($costStor, 2),
                ],
                'margin_thb'  => round($margin, 2),
                'margin_pct'  => $rev > 0 ? round($margin / $rev * 100, 1) : null,
                'risk'        => $this->riskLabel($rev, $cost),
            ];
        }
        return $rows;
    }

    /**
     * Identify the top-N most expensive users so admins can investigate
     * outliers before they wreck margins.
     *
     * @return array<int, array{user_id:int, plan_code:string, total_thb:float}>
     */
    public function topSpenders(int $limit = 20, ?Carbon $forMonth = null): array
    {
        $period = ($forMonth ?? now())->format('Y-m');
        return DB::table('usage_events')
            ->whereRaw("to_char(occurred_at, 'YYYY-MM') = ?", [$period])
            ->groupBy('user_id', 'plan_code')
            ->selectRaw('user_id, plan_code, SUM(cost_microcents) AS cost_microcents')
            ->orderByDesc('cost_microcents')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'user_id'   => (int) $r->user_id,
                'plan_code' => (string) $r->plan_code,
                'total_thb' => $this->microcentsToThb((int) $r->cost_microcents),
            ])
            ->all();
    }

    /* ─────────────────── helpers ─────────────────── */

    private function microcentsToThb(int $microcents): float
    {
        // 1 USD = 100 cents = 1,000,000 microcents
        $usd = $microcents / 1_000_000;
        $rate = (float) config('usage.usd_to_thb_rate', 35.0);
        return round($usd * $rate, 4);
    }

    /**
     * Storage cost ≈ bytes × $0.015/GB/month × THB/USD.
     * Multiply by `fractionOfMonth` (0..1) for partial-month reporting.
     */
    private function storageCostThb(int $bytes, float $fractionOfMonth): float
    {
        if ($bytes <= 0) return 0.0;
        $gbMonths = ($bytes / 1_073_741_824) * max(0.0, min(1.0, $fractionOfMonth));
        $usd      = $gbMonths * 0.015;   // $/GB/mo list price
        $rate     = (float) config('usage.usd_to_thb_rate', 35.0);
        return round($usd * $rate, 4);
    }

    private function fractionOfMonthSoFar(?Carbon $atMonth): float
    {
        $at = $atMonth ?? now();
        $start = $at->copy()->startOfMonth();
        $end   = $at->copy()->endOfMonth();
        $now   = min($at, now()); // future months ignored — return 0 is safer, but 1.0 if billing for the period
        $totalSeconds   = max(1, $end->diffInSeconds($start));
        $elapsedSeconds = max(0, $now->diffInSeconds($start));
        return min(1.0, $elapsedSeconds / $totalSeconds);
    }

    private function riskLabel(float $revenue, float $cost): string
    {
        if ($revenue <= 0) return 'unfunded';
        $margin = ($revenue - $cost) / $revenue;
        return match (true) {
            $margin >= 0.5 => 'healthy',
            $margin >= 0.2 => 'thin',
            $margin >= 0   => 'razor',
            default        => 'losing',
        };
    }
}
