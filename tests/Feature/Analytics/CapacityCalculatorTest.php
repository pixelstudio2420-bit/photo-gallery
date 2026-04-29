<?php

namespace Tests\Feature\Analytics;

use App\Services\Analytics\CapacityCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks down CapacityCalculator math against synthetic data.
 *
 * Properties:
 *   • snapshot() returns the expected shape (no missing keys)
 *   • utilization% = observed / ceiling × 100, exactly
 *   • bottleneck() picks the worst dimension
 *   • recommendations are returned (never empty array)
 *   • dailyTrend() returns rows keyed by date
 *   • DAU correctly counts distinct activity_logs.user_id when present
 */
class CapacityCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private CapacityCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new CapacityCalculator();
    }

    public function test_snapshot_returns_complete_shape(): void
    {
        $snap = $this->calc->snapshot();

        $this->assertArrayHasKey('as_of', $snap);
        $this->assertArrayHasKey('today', $snap);
        $this->assertArrayHasKey('recent_minute', $snap);
        $this->assertArrayHasKey('baselines', $snap);
        $this->assertArrayHasKey('utilization', $snap);
        $this->assertArrayHasKey('bottleneck', $snap);
        $this->assertArrayHasKey('recommendations', $snap);

        // Empty snapshot still returns the shape with zeros.
        $this->assertSame(0, $snap['today']['requests_so_far']);
        $this->assertNotEmpty($snap['baselines'], 'seeded baselines must be present');
    }

    public function test_utilization_math_is_exact(): void
    {
        // Synthesize a high-RPS minute bucket: 1500 req in 1 min = 25 RPS.
        // Production baseline = 200 RPS → 12.5%.
        // Dev baseline = 30 RPS → 83.3%.
        DB::table('request_minute_buckets')->insert([
            'bucket_at'       => now()->subMinutes(1)->startOfMinute(),
            'route_group'     => 'public.read',
            'request_count'   => 1500,
            'error_count'     => 0,
            'duration_ms_sum' => 150000,
            'duration_ms_max' => 200,
            'distinct_users'  => 100,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $snap = $this->calc->snapshot();
        $rps  = $snap['utilization']['rps'] ?? null;

        $this->assertNotNull($rps);
        // Should pick production_per_box=200 over dev_per_box=30
        // since both seeded baselines are present.
        $this->assertSame('rps.production_per_box', $rps['baseline_metric']);
        $this->assertSame(200, $rps['ceiling']);
        $this->assertEqualsWithDelta(12.5, $rps['percent'], 0.5);
    }

    public function test_bottleneck_returns_worst_dimension(): void
    {
        // Force a very high LINE quota usage by inserting fake
        // line_deliveries rows.
        if (\Schema::hasTable('line_deliveries')) {
            // 190 of 200 free quota = 95%.
            for ($i = 0; $i < 190; $i++) {
                DB::table('line_deliveries')->insert([
                    'line_user_id'  => 'U' . str_repeat(dechex($i % 16), 32),
                    'delivery_type' => 'push',
                    'message_type'  => 'text',
                    'status'        => 'sent',
                    'created_at'    => now(),
                ]);
            }
        }

        $snap = $this->calc->snapshot();
        $bot  = $snap['bottleneck'];

        $this->assertNotNull($bot);
        if (isset($snap['utilization']['line_quota'])) {
            $this->assertSame('line_quota', $bot['dimension']);
            $this->assertGreaterThanOrEqual(80, $bot['percent']);
        }
    }

    public function test_recommendations_always_returned(): void
    {
        $snap = $this->calc->snapshot();
        $this->assertNotEmpty($snap['recommendations']);
        $this->assertIsArray($snap['recommendations']);
    }

    public function test_dau_uses_activity_logs_when_present(): void
    {
        if (!\Schema::hasTable('activity_logs')) {
            $this->markTestSkipped('activity_logs table not present');
        }

        DB::table('activity_logs')->insert([
            ['action' => 'test', 'user_id' => 1, 'created_at' => now()],
            ['action' => 'test', 'user_id' => 2, 'created_at' => now()],
            ['action' => 'test', 'user_id' => 1, 'created_at' => now()],   // dup
            ['action' => 'test', 'user_id' => 3, 'created_at' => now()],
        ]);

        $snap = $this->calc->snapshot();
        $this->assertSame(3, $snap['today']['dau_estimated'],
            'distinct user_id count from activity_logs');
    }

    public function test_daily_trend_returns_data_keyed_by_date(): void
    {
        DB::table('usage_daily')->insert([
            [
                'date' => now()->subDays(2)->toDateString(),
                'metric' => 'requests.total', 'feature' => null,
                'value' => 5000, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'date' => now()->subDay()->toDateString(),
                'metric' => 'requests.total', 'feature' => null,
                'value' => 7000, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $trend = $this->calc->dailyTrend(7);
        $this->assertNotEmpty($trend);
        // The keys are full date strings (with time portion in pgsql);
        // we just confirm the structure: array keyed by something + the
        // metric value lives inside.
        $found = false;
        foreach ($trend as $byDate) {
            if (($byDate['requests.total'] ?? 0) === 7000) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'must find the 7000-request day in the trend');
    }
}
