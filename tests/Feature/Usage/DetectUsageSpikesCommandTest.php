<?php

namespace Tests\Feature\Usage;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DetectUsageSpikesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSchema();
    }

    private function bootSchema(): void
    {
        if (!Schema::hasTable('usage_counters')) {
            Schema::create('usage_counters', function ($t) {
                $t->unsignedBigInteger('user_id');
                $t->string('resource', 48);
                $t->string('period', 8);
                $t->string('period_key', 32);
                $t->bigInteger('units')->default(0);
                $t->bigInteger('cost_microcents')->default(0);
                $t->timestamp('updated_at')->useCurrent();
                $t->primary(['user_id', 'resource', 'period', 'period_key']);
            });
        } else {
            DB::table('usage_counters')->truncate();
        }
        if (!Schema::hasTable('usage_events')) {
            Schema::create('usage_events', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id');
                $t->string('plan_code', 32);
                $t->string('resource', 48);
                $t->bigInteger('units');
                $t->bigInteger('cost_microcents')->default(0);
                $t->json('metadata')->nullable();
                $t->timestamp('occurred_at');
            });
        } else {
            DB::table('usage_events')->truncate();
        }
    }

    private function seedHourlyUsage(int $userId, string $resource, array $hourlyUnits): void
    {
        $now = Carbon::now()->startOfHour();
        foreach ($hourlyUnits as $hoursAgo => $units) {
            $hour = $now->copy()->subHours($hoursAgo);
            DB::table('usage_counters')->insert([
                'user_id'    => $userId,
                'resource'   => $resource,
                'period'     => 'hour',
                'period_key' => $hour->format('Y-m-d\TH'),
                'units'      => $units,
                'cost_microcents' => 0,
                'updated_at' => $hour,
            ]);
        }
    }

    public function test_detects_a_10x_spike_against_baseline(): void
    {
        // Baseline: 50 calls/hr × 7 days × 24 hours = enough samples.
        // We seed 7 sample hours at 50 each, giving avg=50 and total=350.
        $hourly = [];
        for ($h = 1; $h <= 7; $h++) $hourly[$h] = 50;
        // Most recent completed hour: spike to 600 (12× baseline)
        $hourly[1] = 600;

        $this->seedHourlyUsage(99, 'ai.face_search', $hourly);

        $this->artisan('usage:detect-spikes')
            ->assertExitCode(0)
            ->expectsOutputToContain('Detected 1 usage spike')
            ->expectsOutputToContain('user=99 resource=ai.face_search');

        // A sentinel ledger row should have been written
        $sentinel = DB::table('usage_events')->where('resource', '_spike')->first();
        $this->assertNotNull($sentinel);
        $this->assertSame(99, (int) $sentinel->user_id);
    }

    public function test_does_not_flag_below_minimum_baseline(): void
    {
        // Baseline ~5 calls/hr × 7h = 35 total — under min_baseline_calls=50
        // → no signal even if current is huge
        $hourly = [];
        for ($h = 1; $h <= 7; $h++) $hourly[$h] = 5;
        $hourly[1] = 1000;

        $this->seedHourlyUsage(101, 'ai.face_search', $hourly);

        $this->artisan('usage:detect-spikes')
            ->assertExitCode(0)
            ->expectsOutputToContain('No usage spikes detected');
    }

    public function test_dry_run_does_not_write_sentinel(): void
    {
        $hourly = [];
        for ($h = 1; $h <= 7; $h++) $hourly[$h] = 50;
        $hourly[1] = 700;
        $this->seedHourlyUsage(102, 'ai.face_search', $hourly);

        $this->artisan('usage:detect-spikes', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(0, DB::table('usage_events')->where('resource', '_spike')->count());
    }

    public function test_disabled_returns_success_without_scanning(): void
    {
        config(['usage.spike_detection.enabled' => false]);
        $this->artisan('usage:detect-spikes')
            ->assertExitCode(0)
            ->expectsOutputToContain('Spike detection is disabled');
    }
}
