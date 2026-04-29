<?php

namespace Tests\Unit;

use App\Services\DashboardAggregationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Tests\TestCase;

/**
 * Verifies the dashboard aggregation service:
 *   1. Returns default values when underlying queries throw
 *   2. Logs the failure (no more silent swallowing)
 *   3. Caches results to absorb spikes
 */
class DashboardAggregationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_core_stats_returns_zeroed_defaults_when_db_fails(): void
    {
        DB::shouldReceive('selectOne')
            ->andThrow(new QueryException('pgsql', 'select 1', [], new PDOException('Connection refused')));

        Log::shouldReceive('error')->atLeast()->once();

        $service = new DashboardAggregationService();
        $stats   = $service->coreStats();

        $this->assertSame(0, $stats['total_orders']);
        $this->assertSame(0, $stats['paid_orders']);
        $this->assertSame(0, $stats['total_users']);
        $this->assertSame(0.0, $stats['total_revenue']);
        $this->assertSame(0, $stats['total_events']);
    }

    public function test_core_stats_logs_error_with_source_context(): void
    {
        DB::shouldReceive('selectOne')
            ->andThrow(new QueryException('pgsql', 'select 1', [], new PDOException('relation "orders" does not exist')));

        // slipStats() uses DB::table()->where->count — mock that path too
        DB::shouldReceive('table')->andThrow(new \RuntimeException('table missing'));

        Log::shouldReceive('error')
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'query failed')
                    && array_key_exists('source', $context)
                    && array_key_exists('error', $context);
            })
            ->atLeast()->once();

        (new DashboardAggregationService())->coreStats();
    }

    public function test_commission_stats_returns_safe_defaults_on_failure(): void
    {
        DB::shouldReceive('selectOne')
            ->andThrow(new QueryException('pgsql', 'select 1', [], new PDOException('table missing')));
        Log::shouldReceive('error')->atLeast()->once();

        $stats = (new DashboardAggregationService())->commissionStats();

        $this->assertSame(0, $stats['total_platform_fee']);
        $this->assertSame(0, $stats['pending_payout_amount']);
    }

    public function test_pending_refunds_returns_zero_when_table_missing(): void
    {
        DB::shouldReceive('table->where->count')
            ->andThrow(new \Exception('table missing'));
        Log::shouldReceive('error')->atLeast()->once();

        $count = (new DashboardAggregationService())->pendingRefunds();

        $this->assertSame(0, $count);
    }

    public function test_revenue_chart_returns_empty_collection_on_failure(): void
    {
        DB::shouldReceive('select')
            ->andThrow(new QueryException('pgsql', 'select 1', [], new PDOException('boom')));
        Log::shouldReceive('error')->atLeast()->once();

        $chart = (new DashboardAggregationService())->revenueChart(14);

        $this->assertCount(0, $chart);
    }

    public function test_platform_commission_falls_back_to_default_rate_on_failure(): void
    {
        Cache::flush();
        DB::shouldReceive('table->where->first')
            ->andThrow(new \Exception('app_settings table missing'));
        Log::shouldReceive('warning')->atLeast()->once();

        $rate = (new DashboardAggregationService())->platformCommissionRate();

        $this->assertSame(20.0, $rate);
    }
}
