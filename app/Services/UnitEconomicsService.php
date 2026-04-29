<?php

namespace App\Services;

use App\Models\BusinessExpense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer LTV / Unit Economics.
 *
 * All numbers derived from orders + auth_users + business_expenses.
 * Uses only rows with status=paid (OR via PaymentSlip verified) to count revenue.
 *
 * NOTE: AOV / LTV are rolling calculations using windowed data so they stay meaningful
 * as the business scales. Results cached briefly in AppSetting for free.
 */
class UnitEconomicsService
{
    /** Rolling window for headline metrics (days). */
    const WINDOW_DAYS = 90;

    /** How far back to look at cohort behaviour. */
    const COHORT_MONTHS = 12;

    /**
     * Headline KPIs: total customers, paying customers, AOV, LTV, repeat rate, etc.
     */
    public function headline(): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        $totalUsers  = Schema::hasTable('auth_users') ? (int) DB::table('auth_users')->count() : 0;

        if (!Schema::hasTable('orders')) {
            return $this->empty($totalUsers);
        }

        $paidOrders = DB::table('orders')
            ->where('status', 'paid')
            ->select('user_id', 'total', 'created_at');

        $windowed = (clone $paidOrders)->where('created_at', '>=', $since)->get();
        $allPaid  = (clone $paidOrders)->get();

        $payingUsers = $allPaid->pluck('user_id')->unique()->count();
        $revenue90   = (float) $windowed->sum('total');
        $orders90    = $windowed->count();

        $aov = $orders90 > 0 ? $revenue90 / $orders90 : 0;

        // Orders per user (lifetime)
        $ordersPerUser = $allPaid->groupBy('user_id')->map(fn ($g) => $g->count());
        $avgOrdersPerUser = $ordersPerUser->count() > 0 ? $ordersPerUser->avg() : 0;

        // Repeat rate = users with ≥2 paid orders / total paying users
        $repeaters = $ordersPerUser->filter(fn ($n) => $n >= 2)->count();
        $repeatRate = $payingUsers > 0 ? $repeaters / $payingUsers * 100 : 0;

        // LTV estimate (simple) = avg_orders_per_user * AOV
        $ltv = $avgOrdersPerUser * $aov;

        // Gross margin proxy: monthly expenses / monthly revenue
        $monthlyExpense = (float) BusinessExpense::active()->get()->sum(fn ($e) => $e->monthlyCost());
        $monthlyRevenue = $this->revenueForMonth(now());
        $grossMarginPct = $monthlyRevenue > 0 ? max(0, ($monthlyRevenue - $monthlyExpense) / $monthlyRevenue * 100) : 0;

        // CAC from marketing spend expenses if we have them; fallback to null
        $cac = $this->estimateCac();

        // Break-even orders this month (how many paid orders needed to cover expenses at current AOV)
        $breakEvenOrders = $aov > 0 ? (int) ceil($monthlyExpense / $aov) : null;

        return [
            'total_users'         => $totalUsers,
            'paying_users'        => $payingUsers,
            'conversion_pct'      => $totalUsers > 0 ? round($payingUsers / $totalUsers * 100, 2) : 0,
            'window_days'         => self::WINDOW_DAYS,
            'orders_90d'          => $orders90,
            'revenue_90d'         => round($revenue90, 2),
            'aov'                 => round($aov, 2),
            'avg_orders_per_user' => round($avgOrdersPerUser, 2),
            'repeat_rate_pct'     => round($repeatRate, 2),
            'ltv'                 => round($ltv, 2),
            'cac'                 => $cac ? round($cac, 2) : null,
            'ltv_cac_ratio'       => ($cac && $cac > 0) ? round($ltv / $cac, 2) : null,
            'monthly_expense'     => round($monthlyExpense, 2),
            'monthly_revenue'     => round($monthlyRevenue, 2),
            'gross_margin_pct'    => round($grossMarginPct, 2),
            'break_even_orders'   => $breakEvenOrders,
            'generated_at'        => now()->toIso8601String(),
        ];
    }

    /**
     * Cohort revenue: group customers by their first-order month, track revenue in subsequent months.
     * Rows are pivot-ready (month 0 = first month, month 1 = next, etc.)
     *
     * @return array<int, array{cohort: string, size: int, months: array<int,float>, revenue_total: float}>
     */
    public function cohortTable(): array
    {
        if (!Schema::hasTable('orders')) return [];

        $paidOrders = DB::table('orders')
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(self::COHORT_MONTHS))
            ->select('user_id', 'total', 'created_at')
            ->get();

        // First paid order per user
        $firstOrder = $paidOrders->groupBy('user_id')->map(fn ($g) => $g->min('created_at'));

        $cohorts = []; // cohort_key => ['size' => n, 'months' => [offset => revenue]]

        foreach ($paidOrders as $o) {
            $firstAt  = Carbon::parse($firstOrder[$o->user_id]);
            $thisAt   = Carbon::parse($o->created_at);
            $cohort   = $firstAt->format('Y-m');
            // Use startOfMonth to avoid month-boundary noise (e.g. Jan 31 → Feb 1 shouldn't be month 1)
            $offset   = $firstAt->copy()->startOfMonth()->diffInMonths($thisAt->copy()->startOfMonth());
            // Some dates lose precision. Also guard against negative offsets.
            if ($offset < 0) $offset = 0;

            if (!isset($cohorts[$cohort])) {
                $cohorts[$cohort] = ['size' => 0, 'months' => [], 'users' => []];
            }
            $cohorts[$cohort]['users'][$o->user_id] = true;
            $cohorts[$cohort]['months'][$offset] = ($cohorts[$cohort]['months'][$offset] ?? 0) + (float) $o->total;
        }

        $out = [];
        foreach ($cohorts as $label => $c) {
            ksort($c['months']);
            $out[] = [
                'cohort'        => $label,
                'size'          => count($c['users']),
                'months'        => array_map(fn ($v) => round($v, 2), $c['months']),
                'revenue_total' => round(array_sum($c['months']), 2),
            ];
        }
        usort($out, fn ($a, $b) => strcmp($a['cohort'], $b['cohort']));
        return $out;
    }

    /**
     * Monthly revenue + order count for last 12 months (for line chart).
     */
    public function monthlyTrend(int $months = 12): array
    {
        if (!Schema::hasTable('orders')) return [];
        $rows = DB::table('orders')
            ->selectRaw("to_char(created_at, 'YYYY-MM') as m, COUNT(*) as c, SUM(total) as r")
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupBy('m')
            ->orderBy('m')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'month'   => (string) $r->m,
                'orders'  => (int) $r->c,
                'revenue' => round((float) $r->r, 2),
            ];
        }
        return $out;
    }

    /**
     * Top N customers by lifetime paid revenue.
     */
    public function topCustomers(int $limit = 15): array
    {
        if (!Schema::hasTable('orders') || !Schema::hasTable('auth_users')) return [];

        $rows = DB::table('orders as o')
            ->join('auth_users as u', 'u.id', '=', 'o.user_id')
            ->where('o.status', 'paid')
            ->selectRaw('u.id, u.first_name, u.last_name, u.email, COUNT(o.id) as orders, SUM(o.total) as revenue, MIN(o.created_at) as first_at, MAX(o.created_at) as last_at')
            ->groupBy('u.id', 'u.first_name', 'u.last_name', 'u.email')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'id'       => $r->id,
            'name'     => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: $r->email,
            'email'    => $r->email,
            'orders'   => (int) $r->orders,
            'revenue'  => round((float) $r->revenue, 2),
            'first_at' => $r->first_at,
            'last_at'  => $r->last_at,
        ])->all();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════════════

    protected function revenueForMonth(Carbon $when): float
    {
        if (!Schema::hasTable('orders')) return 0;
        return (float) DB::table('orders')
            ->where('status', 'paid')
            ->whereYear('created_at', $when->year)
            ->whereMonth('created_at', $when->month)
            ->sum('total');
    }

    /**
     * CAC heuristic: this month's marketing/ads expense ÷ new paying customers acquired this month.
     */
    protected function estimateCac(): ?float
    {
        if (!Schema::hasTable('orders')) return null;

        $adsThisMonth = (float) BusinessExpense::active()
            ->get()
            ->filter(fn ($e) => str_contains(mb_strtolower($e->name . ' ' . ($e->category ?? '')), 'ads')
                || str_contains(mb_strtolower($e->name . ' ' . ($e->category ?? '')), 'marketing')
                || $e->category === 'marketing')
            ->sum(fn ($e) => $e->monthlyCost());

        if ($adsThisMonth <= 0) return null;

        // New paying users this month = users whose FIRST paid order is this month
        $userFirst = DB::table('orders')
            ->where('status', 'paid')
            ->select('user_id', DB::raw('MIN(created_at) as first_at'))
            ->groupBy('user_id')
            ->get();

        $newPayers = $userFirst->filter(function ($r) {
            $d = Carbon::parse($r->first_at);
            return $d->isCurrentMonth();
        })->count();

        if ($newPayers <= 0) return null;
        return $adsThisMonth / $newPayers;
    }

    protected function empty(int $totalUsers): array
    {
        return [
            'total_users' => $totalUsers, 'paying_users' => 0, 'conversion_pct' => 0,
            'window_days' => self::WINDOW_DAYS, 'orders_90d' => 0, 'revenue_90d' => 0,
            'aov' => 0, 'avg_orders_per_user' => 0, 'repeat_rate_pct' => 0, 'ltv' => 0,
            'cac' => null, 'ltv_cac_ratio' => null,
            'monthly_expense' => 0, 'monthly_revenue' => 0, 'gross_margin_pct' => 0,
            'break_even_orders' => null, 'generated_at' => now()->toIso8601String(),
        ];
    }
}
