<?php

namespace App\Services\Finance;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PhotographerPayout;
use App\Models\SubscriptionInvoice;
use App\Models\PhotographerSubscription;
use App\Models\AiTask;
use App\Models\PhotographerProfile;
use App\Models\GiftCardTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * CostAnalysisService
 * ───────────────────
 * Computes platform-level revenue, cost, and profit numbers for the
 * admin finance dashboard. Aggregates across:
 *
 *   REVENUE sources:
 *     - Photo-package orders (paid)
 *     - Credit-package orders (paid)
 *     - Subscription invoices (paid)
 *     - Gift card sales (active source=purchase)
 *     - Consumer storage subscriptions (paid)
 *
 *   COST sources:
 *     - Photographer payouts (the platform's COGS)
 *     - Payment-gateway fees (estimated %, configurable per gateway)
 *     - Storage costs (R2 / S3 — bytes × $/GB-month, configurable)
 *     - AI API costs (Rekognition $0.001/face, OpenAI / Anthropic per token)
 *     - Server / hosting (flat monthly, configurable)
 *     - Email / SMS (estimated per send, configurable)
 *
 * Cost rates are stored in AppSettings with sensible defaults so a
 * fresh install gets a working calculation, and admins can tune them
 * to match their actual cloud bills. All rates are in THB.
 *
 * Returned numbers are wrapped in Cache::remember(60s) so the
 * dashboard view loads quickly even with millions of order rows.
 */
class CostAnalysisService
{
    /**
     * Default cost rates (THB).
     * Override per-install via AppSetting keys (cost.<name>).
     *
     *   storage_per_gb_month    R2: ~฿0.55 / GB / month (R2 is $0.015/GB-month)
     *   server_monthly_baseline VPS or dyno fixed cost
     *   rekognition_per_face    AWS: $0.001 per face indexed
     *   ai_caption_per_call     OpenAI gpt-4o-mini ~฿0.05 / call (rough)
     *   email_per_send          Postmark / SES estimate
     *   gateway_fee_pct         Average across gateways (PromptPay 0%, card 2.5-3.5%)
     *   bank_transfer_fee       Per disbursement
     */
    public const DEFAULTS = [
        'storage_per_gb_month'     => 0.55,
        'server_monthly_baseline'  => 1500.00,  // VPS + DB + Redis baseline
        'rekognition_per_face'     => 0.04,     // ~$0.001 × 36 ฿/USD ÷ rough estimate
        'ai_caption_per_call'      => 0.05,
        'email_per_send'           => 0.15,
        'gateway_fee_pct'          => 2.5,      // weighted across all gateways
        'bank_transfer_fee'        => 5.00,     // per disbursement (Omise transfer fee ≈ ฿5-15)
    ];

    /**
     * Public entry point for the dashboard. Returns the full cost +
     * revenue + profit analysis for the requested time window.
     *
     * @param string $period 'day' | 'month' | 'year' | 'custom'
     * @param ?Carbon $from
     * @param ?Carbon $to
     */
    public function analyse(string $period = 'month', ?Carbon $from = null, ?Carbon $to = null): array
    {
        [$from, $to] = $this->resolveRange($period, $from, $to);
        $cacheKey = "cost.analysis.{$period}.{$from->format('Ymd')}.{$to->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($from, $to, $period) {
            $revenue = $this->revenueBreakdown($from, $to);
            $costs   = $this->costBreakdown($from, $to);

            $totalRevenue = array_sum($revenue);
            $totalCost    = array_sum($costs);
            $grossProfit  = $totalRevenue - $totalCost;
            $margin       = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

            return [
                'period'        => $period,
                'from'          => $from->toDateTimeString(),
                'to'            => $to->toDateTimeString(),
                'revenue'       => $revenue,
                'costs'         => $costs,
                'totals'        => [
                    'revenue'      => $totalRevenue,
                    'cost'         => $totalCost,
                    'gross_profit' => $grossProfit,
                    'margin_pct'   => round($margin, 2),
                ],
                'rates'         => $this->getRates(),
                'trend'         => $this->trend($period, $from, $to),
            ];
        });
    }

    /**
     * Revenue line-items.
     */
    private function revenueBreakdown(Carbon $from, Carbon $to): array
    {
        $photoOrders = (float) Order::where('order_type', Order::TYPE_PHOTO_PACKAGE)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->sum('total');

        $creditOrders = (float) Order::where('order_type', Order::TYPE_CREDIT_PACKAGE)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->sum('total');

        $subscriptions = 0;
        if (Schema::hasTable('subscription_invoices')) {
            $subscriptions = (float) SubscriptionInvoice::where('status', SubscriptionInvoice::STATUS_PAID)
                ->whereBetween('paid_at', [$from, $to])
                ->sum('amount_thb');
        }

        $userStorage = (float) Order::where('order_type', Order::TYPE_USER_STORAGE_SUBSCRIPTION)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->sum('total');

        $giftCards = 0;
        if (class_exists(\App\Models\Order::class) && defined(Order::class . '::TYPE_GIFT_CARD')) {
            $giftCards = (float) Order::where('order_type', Order::TYPE_GIFT_CARD)
                ->where('status', 'paid')
                ->whereBetween('created_at', [$from, $to])
                ->sum('total');
        }

        return [
            'photo_orders'        => round($photoOrders, 2),
            'credit_packages'     => round($creditOrders, 2),
            'subscriptions'       => round($subscriptions, 2),
            'user_storage'        => round($userStorage, 2),
            'gift_cards'          => round($giftCards, 2),
        ];
    }

    /**
     * Cost line-items.
     */
    private function costBreakdown(Carbon $from, Carbon $to): array
    {
        $rates = $this->getRates();

        // 1. Photographer payouts — the biggest line item (COGS).
        $payoutsPaid = (float) PhotographerPayout::where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('payout_amount');

        // 2. Payment-gateway fees — estimated as a % of paid revenue.
        $paidRevenue = (float) Order::where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->sum('total');
        $gatewayFees = round($paidRevenue * ($rates['gateway_fee_pct'] / 100), 2);

        // 3. Storage costs — bytes × ($/GB-month) × (period in months).
        // For day/month/year we scale rate proportionally.
        $totalBytes = (float) DB::table('photographer_profiles')->sum('storage_used_bytes');
        $totalGb    = $totalBytes / (1024 ** 3);
        $monthsInRange = max(1/30, $from->diffInDays($to) / 30);
        $storageCost = round($totalGb * $rates['storage_per_gb_month'] * $monthsInRange, 2);

        // 4. AI processing — count completed tasks × per-task cost.
        $aiTaskCount = 0;
        if (Schema::hasTable('ai_tasks')) {
            $aiTaskCount = (int) AiTask::where('status', 'done')
                ->whereBetween('created_at', [$from, $to])
                ->count();
        }
        // Rough split: 70% face/quality (Rekognition), 30% captions (OpenAI)
        $rekognitionTasks = (int) ($aiTaskCount * 0.7);
        $captionTasks     = (int) ($aiTaskCount * 0.3);
        $rekognitionCost  = round($rekognitionTasks * $rates['rekognition_per_face'], 2);
        $captionsCost     = round($captionTasks * $rates['ai_caption_per_call'], 2);

        // 5. Bank-transfer fees on disbursements.
        $disbursementCount = 0;
        if (Schema::hasTable('photographer_disbursements')) {
            $disbursementCount = (int) DB::table('photographer_disbursements')
                ->where('status', 'succeeded')
                ->whereBetween('settled_at', [$from, $to])
                ->count();
        }
        $disbursementFees = round($disbursementCount * $rates['bank_transfer_fee'], 2);

        // 6. Email sends — estimate from contact_messages + order events.
        $emailEstimate = 0;
        if (Schema::hasTable('contact_messages')) {
            $emailEstimate += (int) DB::table('contact_messages')
                ->whereBetween('created_at', [$from, $to])
                ->count();
        }
        // Each paid order generates ~3 emails (confirmation + receipt + admin alert)
        $paidOrderCount = (int) Order::where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $emailEstimate += $paidOrderCount * 3;
        $emailCost = round($emailEstimate * $rates['email_per_send'], 2);

        // 7. Server / hosting — pro-rate the monthly baseline.
        $serverCost = round($rates['server_monthly_baseline'] * $monthsInRange, 2);

        return [
            'photographer_payouts' => round($payoutsPaid, 2),
            'gateway_fees'         => $gatewayFees,
            'storage'              => $storageCost,
            'ai_rekognition'       => $rekognitionCost,
            'ai_captions'          => $captionsCost,
            'disbursement_fees'    => $disbursementFees,
            'email'                => $emailCost,
            'server_hosting'       => $serverCost,
        ];
    }

    /**
     * Daily/weekly/monthly trend points for the chart.
     */
    private function trend(string $period, Carbon $from, Carbon $to): array
    {
        // Determine the bucket size based on the period.
        // day → 24 hourly buckets
        // month → daily buckets
        // year → monthly buckets
        $buckets = [];
        if ($period === 'day') {
            for ($h = 0; $h < 24; $h++) {
                $bucketStart = (clone $from)->setTime($h, 0, 0);
                $bucketEnd   = (clone $bucketStart)->addHour();
                $buckets[] = $this->bucketTotals($bucketStart, $bucketEnd, $bucketStart->format('H:i'));
            }
        } elseif ($period === 'month') {
            $cursor = clone $from;
            while ($cursor->lessThanOrEqualTo($to)) {
                $bucketEnd = (clone $cursor)->endOfDay();
                $buckets[] = $this->bucketTotals($cursor, $bucketEnd, $cursor->format('d/m'));
                $cursor->addDay();
            }
        } else { // year or custom
            $cursor = (clone $from)->startOfMonth();
            while ($cursor->lessThanOrEqualTo($to)) {
                $bucketEnd = (clone $cursor)->endOfMonth();
                $buckets[] = $this->bucketTotals($cursor, $bucketEnd, $cursor->translatedFormat('M Y'));
                $cursor->addMonth();
            }
        }
        return $buckets;
    }

    private function bucketTotals(Carbon $from, Carbon $to, string $label): array
    {
        $revenue = (float) Order::where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->sum('total');
        $payouts = (float) PhotographerPayout::where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('payout_amount');
        return [
            'label'   => $label,
            'revenue' => $revenue,
            'cost'    => $payouts, // simplified: payouts is the dominant cost line
            'profit'  => $revenue - $payouts,
        ];
    }

    /**
     * Get cost rates from AppSettings, falling back to defaults.
     */
    public function getRates(): array
    {
        $rates = [];
        foreach (self::DEFAULTS as $key => $default) {
            $rates[$key] = (float) AppSetting::get('cost.' . $key, $default);
        }
        return $rates;
    }

    public function setRate(string $key, float $value): void
    {
        if (!array_key_exists($key, self::DEFAULTS)) {
            throw new \InvalidArgumentException("Unknown cost key: {$key}");
        }
        AppSetting::set('cost.' . $key, $value);
        Cache::flush(); // simple — flush all on rate change
    }

    private function resolveRange(string $period, ?Carbon $from, ?Carbon $to): array
    {
        if ($period === 'custom' && $from && $to) {
            return [$from, $to];
        }
        return match ($period) {
            'day'   => [now()->startOfDay(),   now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'year'  => [now()->startOfYear(),  now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }
}
