<?php

namespace App\Services\Finance;

use App\Models\AppSetting;
use App\Models\PhotographerPayout;
use App\Models\PhotographerSubscription;
use App\Models\PhotographerProfile;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\AiTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * PlanProfitabilityService
 * ────────────────────────
 * Computes per-plan profitability so admins can see:
 *
 *   • Subscriber count per plan (popularity)
 *   • Revenue per plan
 *   • Cost-to-serve per plan (storage + AI usage)
 *   • Per-plan margin %
 *   • Per-feature cost breakdown (which AI features eat the most)
 *
 * Cost-to-serve formula per subscriber on plan X:
 *   storageCost  = (used_bytes / 1024^3) × storage_per_gb_month
 *   aiCost       = aiTasksThisPeriod × per-task rate
 *   gatewayFee   = subPrice × gateway_fee_pct
 *   marginThisSub = subPrice − storageCost − aiCost − gatewayFee
 *
 * Plan-level numbers aggregate across all subscribers on that plan.
 *
 * Used at /admin/finance/plan-profit to inform pricing decisions:
 * if Pro is barely break-even because users use lots of AI, we know to
 * raise the price or cap AI credits.
 */
class PlanProfitabilityService
{
    public function __construct(private CostAnalysisService $costs) {}

    /**
     * Full report — list of plans with subscriber count, revenue, cost,
     * margin. Cached 5 min because plans rarely change and the admin
     * doesn't need second-by-second freshness.
     */
    public function report(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to   ??= now()->endOfMonth();

        $cacheKey = "plan.profitability.{$from->format('Ymd')}.{$to->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($from, $to) {
            $plans = SubscriptionPlan::active()->ordered()->get();
            $rates = $this->costs->getRates();
            $monthsInRange = max(1/30, $from->diffInDays($to) / 30);

            $rows = [];
            foreach ($plans as $plan) {
                $rows[] = $this->analysePlan($plan, $from, $to, $rates, $monthsInRange);
            }

            // Sort by subscriber count desc — popularity ranking
            usort($rows, fn($a, $b) => $b['subscribers'] - $a['subscribers']);

            $totalRevenue = array_sum(array_column($rows, 'revenue'));
            $totalCost    = array_sum(array_column($rows, 'cost'));
            $totalProfit  = $totalRevenue - $totalCost;

            return [
                'from'           => $from->toDateTimeString(),
                'to'             => $to->toDateTimeString(),
                'plans'          => $rows,
                'totals'         => [
                    'revenue' => $totalRevenue,
                    'cost'    => $totalCost,
                    'profit'  => $totalProfit,
                    'margin'  => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0,
                ],
                'most_popular'   => $rows[0] ?? null,
                'least_popular'  => count($rows) ? end($rows) : null,
                'period_months'  => $monthsInRange,
            ];
        });
    }

    /**
     * Per-plan computation — extracted so unit tests can hit a single
     * plan without invoking the cache layer.
     */
    public function analysePlan(SubscriptionPlan $plan, Carbon $from, Carbon $to, array $rates, float $monthsInRange): array
    {
        // ── Subscriber base ─────────────────────────────────────────
        $subscribers = (int) PhotographerSubscription::where('plan_id', $plan->id)
            ->whereIn('status', ['active', 'grace'])
            ->count();

        // ── Revenue from paid invoices in the period ───────────────
        $revenue = 0;
        if (Schema::hasTable('subscription_invoices')) {
            $revenue = (float) SubscriptionInvoice::join('photographer_subscriptions', 'subscription_invoices.subscription_id', '=', 'photographer_subscriptions.id')
                ->where('photographer_subscriptions.plan_id', $plan->id)
                ->where('subscription_invoices.status', SubscriptionInvoice::STATUS_PAID)
                ->whereBetween('subscription_invoices.paid_at', [$from, $to])
                ->sum('subscription_invoices.amount_thb');
        }

        // ── Cost-to-serve ───────────────────────────────────────────
        // Storage: sum of used_bytes across this plan's photographers.
        $totalBytes = (float) PhotographerProfile::where('subscription_plan_code', $plan->code)
            ->sum('storage_used_bytes');
        $totalGb       = $totalBytes / (1024 ** 3);
        $storageCost   = round($totalGb * $rates['storage_per_gb_month'] * $monthsInRange, 2);

        // AI tasks attributable to this plan's users.
        $aiTaskCount = 0;
        if (Schema::hasTable('ai_tasks')) {
            $aiTaskCount = (int) DB::table('ai_tasks')
                ->join('photographer_profiles', 'ai_tasks.photographer_id', '=', 'photographer_profiles.user_id')
                ->where('photographer_profiles.subscription_plan_code', $plan->code)
                ->where('ai_tasks.status', 'done')
                ->whereBetween('ai_tasks.created_at', [$from, $to])
                ->count();
        }
        $rekognitionShare = (int) ($aiTaskCount * 0.7);
        $captionShare     = (int) ($aiTaskCount * 0.3);
        $aiCost = round(
            ($rekognitionShare * $rates['rekognition_per_face']) +
            ($captionShare     * $rates['ai_caption_per_call']),
            2
        );

        // Gateway fees on the revenue collected.
        $gatewayFees = round($revenue * ($rates['gateway_fee_pct'] / 100), 2);

        // Server cost share — flat baseline allocated proportionally to
        // subscriber count. If we have 100 subs total and Pro has 30,
        // Pro carries 30% of the server cost. Falls back to even split
        // when total is 0.
        $totalSubs = (int) PhotographerSubscription::whereIn('status', ['active', 'grace'])->count();
        $serverShare = $totalSubs > 0 ? ($subscribers / $totalSubs) : 0;
        $serverCost  = round($rates['server_monthly_baseline'] * $monthsInRange * $serverShare, 2);

        $totalCost = $storageCost + $aiCost + $gatewayFees + $serverCost;
        $profit    = $revenue - $totalCost;
        $margin    = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;

        // Per-subscriber averages — useful for product decisions.
        $arpu = $subscribers > 0 ? round($revenue / $subscribers, 2) : 0;
        $costPerSub = $subscribers > 0 ? round($totalCost / $subscribers, 2) : 0;

        // Feature-level cost — split AI cost into the actual features
        // the plan grants. Rough but informative.
        $aiFeatures = $plan->ai_features ?? [];
        $featureCosts = [];
        if (count($aiFeatures) > 0 && $aiCost > 0) {
            $perFeature = round($aiCost / count($aiFeatures), 2);
            foreach ($aiFeatures as $f) {
                $featureCosts[$f] = $perFeature;
            }
        }

        return [
            'plan_id'          => $plan->id,
            'plan_code'        => $plan->code,
            'plan_name'        => $plan->name,
            'price_monthly'    => (float) $plan->price_thb,
            'price_annual'     => (float) ($plan->price_annual_thb ?? 0),
            'is_free'          => $plan->isFree(),
            'subscribers'      => $subscribers,
            'revenue'          => round($revenue, 2),
            'cost'             => round($totalCost, 2),
            'profit'           => round($profit, 2),
            'margin_pct'       => $margin,
            'arpu'             => $arpu,
            'cost_per_sub'     => $costPerSub,
            'cost_breakdown'   => [
                'storage'       => $storageCost,
                'ai'            => $aiCost,
                'gateway_fees'  => $gatewayFees,
                'server_share'  => $serverCost,
            ],
            'feature_costs'    => $featureCosts,
            'storage_gb_total' => round($totalGb, 2),
            'storage_gb_cap'   => $plan->storage_gb,
            'ai_tasks_period'  => $aiTaskCount,
        ];
    }
}
