<?php

namespace Tests\Feature\Line;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Health-sweeper for LINE delivery — alerts when stuck-pending backlog
 * or recent-failure rate crosses thresholds.
 *
 * Tests pin down:
 *   • Healthy state → no alert + dedup cleared.
 *   • Stuck-pending pile → alert raised.
 *   • Failure-rate burst → alert raised.
 *   • Dedup TTL prevents alert spam.
 */
class CheckLineDeliveryHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        DB::table('line_deliveries')->truncate();
    }

    private function seedDelivery(string $status, int $minutesAgo): void
    {
        DB::table('line_deliveries')->insert([
            'user_id'        => null,
            'line_user_id'   => 'U' . str_repeat('1', 32),
            'delivery_type'  => 'push',
            'message_type'   => 'text',
            'status'         => $status,
            'attempts'       => 1,
            'created_at'     => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_healthy_state_clears_dedup_and_passes(): void
    {
        // Pre-seed dedup keys to confirm they get cleared on a clean run.
        Cache::put('line.delivery.alert.stuck', 'stale', 3600);
        Cache::put('line.delivery.alert.rate', 'stale', 3600);

        // 5 successful sends in the last 10 min — no problem
        for ($i = 0; $i < 5; $i++) {
            $this->seedDelivery('sent', 5);
        }

        $exitCode = \Artisan::call('line:check-delivery-health', ['--quiet-if-clean' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertNull(Cache::get('line.delivery.alert.stuck'),
            'Healthy run must clear stuck dedup so next failure alerts immediately.');
        $this->assertNull(Cache::get('line.delivery.alert.rate'),
            'Healthy run must clear rate dedup.');
    }

    public function test_stuck_pending_backlog_raises_alert(): void
    {
        // 3 deliveries stuck in pending for 45 min — over the 30 min threshold
        for ($i = 0; $i < 3; $i++) {
            $this->seedDelivery('pending', 45);
        }

        \Artisan::call('line:check-delivery-health');

        $this->assertNotNull(Cache::get('line.delivery.alert.stuck'),
            'Stuck-pending threshold must set the dedup key.');
    }

    public function test_recent_pending_below_threshold_no_alert(): void
    {
        // 5 pending in last 10 min — less than the 30-min stuck window
        for ($i = 0; $i < 5; $i++) {
            $this->seedDelivery('pending', 10);
        }

        \Artisan::call('line:check-delivery-health');

        $this->assertNull(Cache::get('line.delivery.alert.stuck'),
            'Pending deliveries younger than 30 min must NOT count as stuck.');
    }

    public function test_failure_rate_burst_raises_alert(): void
    {
        // 12 failed + 8 sent in last 30 min → 60% failure rate
        for ($i = 0; $i < 12; $i++) {
            $this->seedDelivery('failed', 5);
        }
        for ($i = 0; $i < 8; $i++) {
            $this->seedDelivery('sent', 5);
        }

        \Artisan::call('line:check-delivery-health');

        $this->assertNotNull(Cache::get('line.delivery.alert.rate'),
            'Failure rate above 25% threshold (with min 10 failures) must alert.');
    }

    public function test_low_volume_does_not_alert(): void
    {
        // 3 failures + 2 sent — 60% rate but only 3 failures (below min 10)
        for ($i = 0; $i < 3; $i++) {
            $this->seedDelivery('failed', 5);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->seedDelivery('sent', 5);
        }

        \Artisan::call('line:check-delivery-health');

        $this->assertNull(Cache::get('line.delivery.alert.rate'),
            'Below the min-failures floor, rate alerting is skipped (avoids false positives on low-volume systems).');
    }

    public function test_dedup_prevents_repeat_alert_within_ttl(): void
    {
        // First run: dedup key should be set
        for ($i = 0; $i < 3; $i++) {
            $this->seedDelivery('pending', 45);
        }
        \Artisan::call('line:check-delivery-health');
        $firstKey = Cache::get('line.delivery.alert.stuck');
        $this->assertNotNull($firstKey);

        // Re-run immediately: dedup must still be active (TTL is 1h)
        \Artisan::call('line:check-delivery-health');
        $secondKey = Cache::get('line.delivery.alert.stuck');

        // Key still present (i.e., dedup window respected)
        $this->assertNotNull($secondKey);
        $this->assertSame($firstKey, $secondKey,
            'Repeat run within dedup window must NOT overwrite the original timestamp.');
    }

    public function test_old_failures_outside_window_excluded(): void
    {
        // 15 failures from 90 min ago — outside the 30-min rate window
        for ($i = 0; $i < 15; $i++) {
            $this->seedDelivery('failed', 90);
        }
        // 5 successful sends inside the window
        for ($i = 0; $i < 5; $i++) {
            $this->seedDelivery('sent', 5);
        }

        \Artisan::call('line:check-delivery-health');

        $this->assertNull(Cache::get('line.delivery.alert.rate'),
            'Old failures must not influence the recent-rate calculation.');
    }
}
