<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use Illuminate\Support\Facades\DB;

class CouponAnalyticsService
{
    /**
     * Overall dashboard stats.
     */
    public function dashboardStats(string $period = '30d'): array
    {
        $start = $this->periodToDate($period);

        return [
            'total_coupons'     => Coupon::count(),
            'active_coupons'    => Coupon::active()->count(),
            'expired_coupons'   => Coupon::expired()->count(),
            'expiring_soon'     => Coupon::expiringSoon(7)->count(),

            'total_redemptions' => CouponUsage::count(),
            'period_redemptions' => CouponUsage::where('used_at', '>=', $start)->count(),
            'total_discount'    => (float) CouponUsage::sum('discount_amount'),
            'period_discount'   => (float) CouponUsage::where('used_at', '>=', $start)->sum('discount_amount'),

            'revenue_generated' => (float) DB::table('coupon_usage')
                ->join('orders', 'coupon_usage.order_id', '=', 'orders.id')
                ->sum('orders.total'),
            'period_revenue'    => (float) DB::table('coupon_usage')
                ->join('orders', 'coupon_usage.order_id', '=', 'orders.id')
                ->where('coupon_usage.used_at', '>=', $start)
                ->sum('orders.total'),

            'avg_discount'      => (float) CouponUsage::avg('discount_amount'),
            'unique_customers'  => CouponUsage::distinct('user_id')->count('user_id'),
        ];
    }

    /**
     * Redemption over time (daily for last N days).
     */
    public function redemptionTrend(int $days = 30): array
    {
        $start = now()->subDays($days)->startOfDay();

        $rows = DB::table('coupon_usage')
            ->selectRaw('DATE(used_at) as date, COUNT(*) as count, SUM(discount_amount) as discount')
            ->where('used_at', '>=', $start)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $row = $rows->firstWhere('date', $date);
            $data[] = [
                'date'     => $date,
                'label'    => now()->subDays($i)->format('d/m'),
                'count'    => $row->count ?? 0,
                'discount' => (float) ($row->discount ?? 0),
            ];
        }

        return $data;
    }

    /**
     * Top performing coupons.
     */
    public function topPerformers(int $limit = 10): array
    {
        return DB::table('coupons')
            ->leftJoin('coupon_usage', 'coupons.id', '=', 'coupon_usage.coupon_id')
            ->leftJoin('orders', 'coupon_usage.order_id', '=', 'orders.id')
            ->select(
                'coupons.id',
                'coupons.code',
                'coupons.name',
                'coupons.type',
                'coupons.value',
                'coupons.is_active',
                'coupons.end_date',
                'coupons.usage_count',
                'coupons.usage_limit',
                DB::raw('COALESCE(SUM(coupon_usage.discount_amount), 0) as total_discount'),
                DB::raw('COALESCE(SUM(orders.total), 0) as revenue_generated'),
                DB::raw('COUNT(DISTINCT coupon_usage.user_id) as unique_customers')
            )
            ->groupBy('coupons.id', 'coupons.code', 'coupons.name', 'coupons.type', 'coupons.value',
                     'coupons.is_active', 'coupons.end_date', 'coupons.usage_count', 'coupons.usage_limit')
            ->orderByDesc('revenue_generated')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Coupon type distribution (percent vs fixed).
     */
    public function typeDistribution(): array
    {
        $rows = DB::table('coupon_usage')
            ->join('coupons', 'coupon_usage.coupon_id', '=', 'coupons.id')
            ->selectRaw('coupons.type, COUNT(*) as count, SUM(coupon_usage.discount_amount) as total_discount')
            ->groupBy('coupons.type')
            ->get();

        $percent = $rows->firstWhere('type', 'percent');
        $fixed   = $rows->firstWhere('type', 'fixed');

        return [
            'percent' => [
                'count'    => $percent->count ?? 0,
                'discount' => (float) ($percent->total_discount ?? 0),
            ],
            'fixed' => [
                'count'    => $fixed->count ?? 0,
                'discount' => (float) ($fixed->total_discount ?? 0),
            ],
        ];
    }

    /**
     * Top customers using coupons.
     */
    public function topCustomers(int $limit = 10): array
    {
        return DB::table('coupon_usage')
            ->join('auth_users', 'coupon_usage.user_id', '=', 'auth_users.id')
            ->select(
                'auth_users.id',
                'auth_users.first_name',
                'auth_users.last_name',
                'auth_users.email',
                DB::raw('COUNT(*) as redemption_count'),
                DB::raw('SUM(coupon_usage.discount_amount) as total_saved')
            )
            ->groupBy('auth_users.id', 'auth_users.first_name', 'auth_users.last_name', 'auth_users.email')
            ->orderByDesc('redemption_count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Conversion impact: orders with coupons vs without.
     */
    public function conversionImpact(string $period = '30d'): array
    {
        $start = $this->periodToDate($period);

        $withCoupon = DB::table('orders')
            ->join('coupon_usage', 'orders.id', '=', 'coupon_usage.order_id')
            ->where('orders.created_at', '>=', $start)
            ->where('orders.status', 'paid')
            ->selectRaw('COUNT(DISTINCT orders.id) as count, AVG(orders.total) as avg_total, SUM(orders.total) as total')
            ->first();

        $allOrders = DB::table('orders')
            ->where('orders.created_at', '>=', $start)
            ->where('orders.status', 'paid')
            ->selectRaw('COUNT(*) as count, AVG(total) as avg_total, SUM(total) as total')
            ->first();

        $withoutCoupon = [
            'count'     => ($allOrders->count ?? 0) - ($withCoupon->count ?? 0),
            'avg_total' => 0,
            'total'     => ($allOrders->total ?? 0) - ($withCoupon->total ?? 0),
        ];
        if ($withoutCoupon['count'] > 0) {
            $withoutCoupon['avg_total'] = $withoutCoupon['total'] / $withoutCoupon['count'];
        }

        return [
            'with_coupon' => [
                'count'     => (int) ($withCoupon->count ?? 0),
                'avg_total' => round((float) ($withCoupon->avg_total ?? 0), 2),
                'total'     => (float) ($withCoupon->total ?? 0),
            ],
            'without_coupon' => [
                'count'     => (int) $withoutCoupon['count'],
                'avg_total' => round((float) $withoutCoupon['avg_total'], 2),
                'total'     => (float) $withoutCoupon['total'],
            ],
        ];
    }

    /**
     * Status breakdown.
     */
    public function statusBreakdown(): array
    {
        $all = Coupon::get();
        return [
            'active'    => $all->where('status', 'active')->count(),
            'expired'   => $all->where('status', 'expired')->count(),
            'exhausted' => $all->where('status', 'exhausted')->count(),
            'disabled'  => $all->where('status', 'disabled')->count(),
            'scheduled' => $all->where('status', 'scheduled')->count(),
        ];
    }

    private function periodToDate(string $period): \Carbon\Carbon
    {
        $days = match ($period) {
            '7d'   => 7,
            '30d'  => 30,
            '90d'  => 90,
            '365d' => 365,
            default => 30,
        };
        return now()->subDays($days)->startOfDay();
    }
}
