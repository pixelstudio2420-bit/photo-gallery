<?php

namespace Tests\Feature\Analytics;

use App\Services\Analytics\UsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks down `analytics:rollup minute|daily` and the report command.
 *
 *   • `rollup minute` reads the cache → writes buckets
 *   • `rollup daily` aggregates buckets into usage_daily
 *   • Re-running `rollup daily` for the same date overwrites in place
 *   • `capacity-report` outputs the snapshot
 *   • `--json` flag emits parseable JSON
 */
class AnalyticsCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_rollup_minute_persists_cache_buckets(): void
    {
        // Seed a cache bucket "in the past" so the cutoff lets it through.
        $bucketEpoch = time() - 65;
        $bucketIso = gmdate('Y-m-d\TH:i', $bucketEpoch);
        Cache::put("metrics.bucket.{$bucketIso}.health.count", 12, 600);
        Cache::put("metrics.bucket.{$bucketIso}.health.duration_ms_sum", 1200, 600);
        Cache::put("metrics.bucket.{$bucketIso}.health.duration_ms_max", 200, 600);
        Cache::put("metrics.registry.{$bucketIso}", '|health|', 600);

        $this->artisan('analytics:rollup', ['phase' => 'minute'])->assertExitCode(0);

        $row = DB::table('request_minute_buckets')
            ->where('route_group', 'health')->first();
        $this->assertNotNull($row);
        $this->assertSame(12, (int) $row->request_count);
    }

    public function test_rollup_daily_aggregates_yesterdays_buckets(): void
    {
        // Seed two buckets from yesterday into request_minute_buckets.
        $yesterday = now()->subDay();
        DB::table('request_minute_buckets')->insert([
            [
                'bucket_at'       => $yesterday->copy()->setTime(10, 0),
                'route_group'     => 'public.read',
                'request_count'   => 60,
                'error_count'     => 1,
                'duration_ms_sum' => 6000,
                'duration_ms_max' => 200,
                'distinct_users'  => 5,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'bucket_at'       => $yesterday->copy()->setTime(11, 0),
                'route_group'     => 'public.read',
                'request_count'   => 120,    // peak minute
                'error_count'     => 2,
                'duration_ms_sum' => 12000,
                'duration_ms_max' => 250,
                'distinct_users'  => 8,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);

        $this->artisan('analytics:rollup', [
            'phase'  => 'daily',
            '--date' => $yesterday->toDateString(),
        ])->assertExitCode(0);

        $totalRow = DB::table('usage_daily')
            ->whereDate('date', $yesterday->toDateString())
            ->where('metric', 'requests.total')
            ->whereNull('feature')
            ->first();
        $this->assertNotNull($totalRow);
        $this->assertSame(180, (int) $totalRow->value);

        $errorsRow = DB::table('usage_daily')
            ->whereDate('date', $yesterday->toDateString())
            ->where('metric', 'requests.errors')
            ->whereNull('feature')
            ->first();
        $this->assertSame(3, (int) $errorsRow->value);

        $peakRow = DB::table('usage_daily')
            ->whereDate('date', $yesterday->toDateString())
            ->where('metric', 'requests.peak_rps')
            ->whereNull('feature')
            ->first();
        // peak minute = 120 → peak_rps = 2 (120 / 60)
        $this->assertSame(2, (int) $peakRow->value);
    }

    public function test_rollup_daily_is_idempotent(): void
    {
        $yesterday = now()->subDay();
        DB::table('request_minute_buckets')->insert([
            'bucket_at'       => $yesterday->copy()->setTime(9, 0),
            'route_group'     => 'health',
            'request_count'   => 50,
            'error_count'     => 0,
            'duration_ms_sum' => 1000,
            'duration_ms_max' => 100,
            'distinct_users'  => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->artisan('analytics:rollup', ['phase' => 'daily', '--date' => $yesterday->toDateString()]);
        $firstCount = DB::table('usage_daily')
            ->whereDate('date', $yesterday->toDateString())->count();

        $this->artisan('analytics:rollup', ['phase' => 'daily', '--date' => $yesterday->toDateString()]);
        $secondCount = DB::table('usage_daily')
            ->whereDate('date', $yesterday->toDateString())->count();

        $this->assertSame($firstCount, $secondCount,
            're-running daily for the same date must update in place, not duplicate');
    }

    public function test_capacity_report_emits_human_readable_snapshot(): void
    {
        $this->artisan('analytics:capacity-report')
            ->expectsOutputToContain('Capacity snapshot')
            ->expectsOutputToContain('Today so far')
            ->expectsOutputToContain('Utilization')
            ->expectsOutputToContain('Recommendations')
            ->assertExitCode(0);
    }

    public function test_capacity_report_json_flag_emits_parseable_json(): void
    {
        $output = \Illuminate\Support\Facades\Artisan::call('analytics:capacity-report', ['--json' => true]);
        $this->assertSame(0, $output);
        $rendered = \Illuminate\Support\Facades\Artisan::output();
        $decoded = json_decode($rendered, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('utilization', $decoded);
        $this->assertArrayHasKey('baselines', $decoded);
    }

    public function test_unknown_phase_fails(): void
    {
        $this->artisan('analytics:rollup', ['phase' => 'bogus'])
            ->assertExitCode(1);
    }
}
