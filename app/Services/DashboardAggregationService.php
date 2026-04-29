<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aggregates admin dashboard statistics in a single place.
 *
 * Each `try`/`catch` here logs the failure and returns a safe default
 * instead of swallowing it silently — the dashboard still renders
 * (graceful degradation) but operators get a real signal.
 *
 * Cached for 60s (stats) / 300s (settings) to absorb spikes.
 */
class DashboardAggregationService
{
    private const STATS_TTL    = 60;
    private const SETTINGS_TTL = 300;

    /** @return array<string, int|float> */
    public function coreStats(): array
    {
        return Cache::remember('admin.dashboard.core_stats', self::STATS_TTL, fn () => array_merge(
            $this->orderStats(),
            $this->userStats(),
            $this->eventStats(),
            $this->photographerStats(),
            $this->slipStats(),
        ));
    }

    public function pendingRefunds(): int
    {
        return $this->safeInt(
            'admin.dashboard.pending_refunds',
            self::STATS_TTL,
            fn () => DB::table('payment_refunds')->where('status', 'requested')->count(),
            context: ['source' => 'pending_refunds'],
        );
    }

    public function platformCommissionRate(): float
    {
        return Cache::remember('setting.platform_commission', self::SETTINGS_TTL, function () {
            try {
                $row = DB::table('app_settings')->where('key', 'platform_commission')->first();
                return $row ? (float) $row->value : 20.0;
            } catch (Throwable $e) {
                Log::warning('Failed to load platform commission rate', [
                    'error' => $e->getMessage(),
                ]);
                return 20.0;
            }
        });
    }

    /** @return array<string, int|float> */
    public function commissionStats(): array
    {
        $defaults = [
            'total_platform_fee'    => 0,
            'month_platform_fee'    => 0,
            'today_platform_fee'    => 0,
            'total_payout'          => 0,
            'month_payout'          => 0,
            'pending_payout'        => 0,
            'pending_payout_amount' => 0,
        ];

        return $this->safeAggregate(
            sql: "SELECT
                COALESCE(SUM(platform_fee), 0)                                                                                  AS total_platform_fee,
                COALESCE(SUM(platform_fee) FILTER (WHERE date_trunc('month', created_at)=date_trunc('month', NOW())), 0)        AS month_platform_fee,
                COALESCE(SUM(platform_fee) FILTER (WHERE DATE(created_at)=CURRENT_DATE), 0)                                     AS today_platform_fee,
                COALESCE(SUM(payout_amount), 0)                                                                                 AS total_payout,
                COALESCE(SUM(payout_amount) FILTER (WHERE date_trunc('month', created_at)=date_trunc('month', NOW())), 0)       AS month_payout,
                COUNT(*) FILTER (WHERE status='pending')                                                                        AS pending_payout,
                COALESCE(SUM(payout_amount) FILTER (WHERE status='pending'), 0)                                                 AS pending_payout_amount
             FROM photographer_payouts",
            defaults: $defaults,
            context: ['source' => 'commission_stats'],
        );
    }

    /** @return array<string, int|float> */
    public function digitalStats(): array
    {
        $defaults = [
            'total_orders'   => 0,
            'pending_review' => 0,
            'paid_orders'    => 0,
            'total_revenue'  => 0,
            'today_revenue'  => 0,
            'month_revenue'  => 0,
            'today_orders'   => 0,
        ];

        return $this->safeAggregate(
            sql: "SELECT
                COUNT(*) AS total_orders,
                COUNT(*) FILTER (WHERE status='pending_review') AS pending_review,
                COUNT(*) FILTER (WHERE status='paid')           AS paid_orders,
                COALESCE(SUM(amount) FILTER (WHERE status='paid'), 0)                                                       AS total_revenue,
                COALESCE(SUM(amount) FILTER (WHERE status='paid' AND DATE(paid_at)=CURRENT_DATE), 0)                        AS today_revenue,
                COALESCE(SUM(amount) FILTER (WHERE status='paid' AND date_trunc('month', paid_at)=date_trunc('month', NOW())), 0) AS month_revenue,
                COUNT(*) FILTER (WHERE DATE(created_at)=CURRENT_DATE) AS today_orders
             FROM digital_orders",
            defaults: $defaults,
            context: ['source' => 'digital_stats'],
        );
    }

    public function topPhotographerPayouts(int $limit = 5): Collection
    {
        return $this->safeCollect(
            sql: "SELECT pp.display_name, pp.commission_rate,
                        COUNT(po.id) AS order_count,
                        COALESCE(SUM(po.gross_amount), 0) AS gross,
                        COALESCE(SUM(po.payout_amount), 0) AS payout,
                        COALESCE(SUM(po.platform_fee), 0)  AS fee
                 FROM photographer_payouts po
                 JOIN photographer_profiles pp ON pp.id = po.photographer_id
                 WHERE date_trunc('month', po.created_at) = date_trunc('month', NOW())
                 GROUP BY po.photographer_id, pp.display_name, pp.commission_rate
                 ORDER BY fee DESC LIMIT ?",
            bindings: [$limit],
            context: ['source' => 'top_photographer_payouts'],
        );
    }

    public function revenueChart(int $days = 14): Collection
    {
        return $this->safeCollect(
            sql: "SELECT DATE(created_at) AS d, SUM(total) AS revenue, COUNT(*) AS orders
                  FROM orders
                  WHERE status='paid' AND created_at >= NOW() - (? || ' days')::interval
                  GROUP BY DATE(created_at) ORDER BY d",
            bindings: [(string) $days],
            context: ['source' => 'revenue_chart', 'days' => $days],
        );
    }

    public function photoSparkline(int $days = 7): Collection
    {
        return $this->safeCollect(
            sql: "SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS revenue, COUNT(*) AS orders
                  FROM orders
                  WHERE status='paid' AND created_at >= NOW() - (? || ' days')::interval
                  GROUP BY DATE(created_at) ORDER BY d",
            bindings: [(string) $days],
            context: ['source' => 'photo_sparkline'],
        );
    }

    public function digitalSparkline(int $days = 7): Collection
    {
        return $this->safeCollect(
            sql: "SELECT DATE(paid_at) AS d, COALESCE(SUM(amount),0) AS revenue, COUNT(*) AS orders
                  FROM digital_orders
                  WHERE status='paid' AND paid_at >= NOW() - (? || ' days')::interval
                  GROUP BY DATE(paid_at) ORDER BY d",
            bindings: [(string) $days],
            context: ['source' => 'digital_sparkline'],
        );
    }

    public function pendingSlips(int $limit = 5): Collection
    {
        return $this->safeCollect(
            sql: "SELECT ps.id, ps.created_at, o.order_number, o.total,
                        u.first_name, u.last_name, e.name AS event_name
                 FROM payment_slips ps
                 JOIN orders o      ON o.id = ps.order_id
                 JOIN auth_users u  ON u.id = o.user_id
                 JOIN event_events e ON e.id = o.event_id
                 WHERE ps.verify_status='pending'
                 ORDER BY ps.created_at DESC LIMIT ?",
            bindings: [$limit],
            context: ['source' => 'pending_slips'],
        );
    }

    public function pendingDigitalOrders(int $limit = 5): Collection
    {
        return $this->safeCollect(
            sql: "SELECT do.id, do.order_number, do.amount, do.created_at,
                        u.first_name, u.last_name, dp.name AS product_name
                 FROM digital_orders do
                 JOIN auth_users u        ON u.id = do.user_id
                 JOIN digital_products dp ON dp.id = do.product_id
                 WHERE do.status='pending_review'
                 ORDER BY do.created_at DESC LIMIT ?",
            bindings: [$limit],
            context: ['source' => 'pending_digital_orders'],
        );
    }

    public function latestOrders(int $limit = 8): Collection
    {
        return $this->safeCollect(
            sql: "SELECT o.id, o.order_number, o.total, o.status, o.created_at,
                        u.first_name, u.last_name,
                        e.name AS event_name,
                        COUNT(oi.id) AS photo_count
                 FROM orders o
                 JOIN auth_users u   ON u.id = o.user_id
                 JOIN event_events e ON e.id = o.event_id
                 LEFT JOIN order_items oi ON oi.order_id = o.id
                 GROUP BY o.id, u.first_name, u.last_name, e.name
                 ORDER BY o.created_at DESC LIMIT ?",
            bindings: [$limit],
            context: ['source' => 'latest_orders'],
        );
    }

    public function topEvents(int $limit = 5): Collection
    {
        return $this->safeCollect(
            sql: "SELECT e.name, COUNT(DISTINCT o.id) AS order_count,
                        COALESCE(SUM(o.total), 0) AS revenue
                 FROM event_events e
                 JOIN orders o ON o.event_id = e.id AND o.status='paid'
                 GROUP BY e.id, e.name
                 ORDER BY revenue DESC LIMIT ?",
            bindings: [$limit],
            context: ['source' => 'top_events'],
        );
    }

    public function latestUsers(int $limit = 5): Collection
    {
        return $this->safeCollect(
            sql: "SELECT id, first_name, last_name, email, created_at
                  FROM auth_users ORDER BY created_at DESC LIMIT ?",
            bindings: [$limit],
            context: ['source' => 'latest_users'],
        );
    }

    /* ────────────────────── Private helpers ────────────────────── */

    /** @return array<string, int|float> */
    private function orderStats(): array
    {
        return $this->safeAggregate(
            sql: "SELECT
                COUNT(*)                                                                                AS total_orders,
                COUNT(*) FILTER (WHERE status='paid')                                                   AS paid_orders,
                COUNT(*) FILTER (WHERE DATE(created_at)=CURRENT_DATE)                                   AS today_orders,
                COALESCE(SUM(total) FILTER (WHERE status='paid'), 0)                                    AS total_revenue,
                COALESCE(SUM(total) FILTER (WHERE status='paid' AND DATE(created_at)=CURRENT_DATE), 0)  AS today_revenue,
                COALESCE(SUM(total) FILTER (WHERE status='paid' AND date_trunc('month', created_at)=date_trunc('month', NOW())), 0) AS month_revenue
             FROM orders",
            defaults: [
                'total_orders' => 0, 'paid_orders' => 0, 'today_orders' => 0,
                'total_revenue' => 0.0, 'today_revenue' => 0.0, 'month_revenue' => 0.0,
            ],
            context: ['source' => 'order_stats'],
        );
    }

    /** @return array<string, int> */
    private function userStats(): array
    {
        return $this->safeAggregate(
            sql: "SELECT COUNT(*) AS total_users,
                         COUNT(*) FILTER (WHERE DATE(created_at)=CURRENT_DATE) AS new_users_today
                  FROM auth_users",
            defaults: ['total_users' => 0, 'new_users_today' => 0],
            context: ['source' => 'user_stats'],
        );
    }

    /** @return array<string, int> */
    private function eventStats(): array
    {
        return $this->safeAggregate(
            sql: "SELECT COUNT(*) AS total_events,
                         COUNT(*) FILTER (WHERE status='active') AS active_events
                  FROM event_events",
            defaults: ['total_events' => 0, 'active_events' => 0],
            context: ['source' => 'event_stats'],
        );
    }

    /** @return array<string, int> */
    private function photographerStats(): array
    {
        return $this->safeAggregate(
            sql: "SELECT COUNT(*) AS total_photographers,
                         COUNT(*) FILTER (WHERE status='pending') AS pending_photographers
                  FROM photographer_profiles",
            defaults: ['total_photographers' => 0, 'pending_photographers' => 0],
            context: ['source' => 'photographer_stats'],
        );
    }

    /** @return array<string, int> */
    private function slipStats(): array
    {
        return [
            'pending_slips' => $this->safeInt(
                cacheKey: '_internal.pending_slips_inline',
                ttl: 0,
                resolver: fn () => DB::table('payment_slips')->where('verify_status', 'pending')->count(),
                context: ['source' => 'slip_stats'],
            ),
        ];
    }

    /**
     * Run a SELECT that returns ONE row of aggregates. On any error: log and
     * return $defaults. Cast every value via array_map to coerce nullable cols.
     *
     * @param  array<string, int|float>  $defaults
     * @param  array<string, mixed>      $context
     * @return array<string, int|float>
     */
    private function safeAggregate(string $sql, array $defaults, array $context = []): array
    {
        try {
            $row = DB::selectOne($sql);
            if (!$row) {
                return $defaults;
            }
            $merged = [];
            foreach ($defaults as $key => $default) {
                $value = $row->{$key} ?? $default;
                $merged[$key] = is_float($default) ? (float) $value : (int) $value;
            }
            return $merged;
        } catch (Throwable $e) {
            Log::error('Dashboard aggregate query failed', $context + [
                'error' => $e->getMessage(),
                'code'  => $e->getCode(),
            ]);
            return $defaults;
        }
    }

    /** @param  array<string, mixed>  $context */
    private function safeCollect(string $sql, array $bindings = [], array $context = []): Collection
    {
        try {
            return collect(DB::select($sql, $bindings));
        } catch (Throwable $e) {
            Log::error('Dashboard collection query failed', $context + [
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /** @param  array<string, mixed>  $context */
    private function safeInt(string $cacheKey, int $ttl, \Closure $resolver, array $context = []): int
    {
        $run = function () use ($resolver, $context): int {
            try {
                return (int) $resolver();
            } catch (Throwable $e) {
                Log::error('Dashboard counter query failed', $context + [
                    'error' => $e->getMessage(),
                ]);
                return 0;
            }
        };

        if ($ttl <= 0) {
            return $run();
        }
        return Cache::remember($cacheKey, $ttl, $run);
    }
}
