<?php

namespace Tests\Feature\Operations;

use App\Jobs\Operations\QueueHeartbeatJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests for the operational commands added in the "push to 90%" round:
 *
 *   • orders:backfill-delivered-at  — repairs the delivered_at regression
 *   • audit:prune                   — retention cleanup of audit tables
 *   • queue:check-heartbeat         — dead-worker detection + alerting
 *   • QueueHeartbeatJob             — writes the cache key the check reads
 *
 * These commands are scheduled in routes/console.php; testing them
 * directly catches regressions before they reach the cron.
 */
class OperationsCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(QueueHeartbeatJob::CACHE_KEY);
        Cache::forget('queue.heartbeat.alert_sent_at');
    }

    // =========================================================================
    // QueueHeartbeatJob + queue:check-heartbeat
    // =========================================================================

    public function test_heartbeat_job_writes_cache_key(): void
    {
        (new QueueHeartbeatJob())->handle();
        $this->assertNotNull(Cache::get(QueueHeartbeatJob::CACHE_KEY));
    }

    public function test_check_heartbeat_passes_when_recent(): void
    {
        // Pretend the worker just ran a heartbeat job.
        Cache::put(QueueHeartbeatJob::CACHE_KEY, now()->toIso8601String(), 3600);

        $this->artisan('queue:check-heartbeat')
            ->expectsOutputToContain('Queue worker heartbeat OK')
            ->assertExitCode(0);

        // No alert dedup key — healthy run clears it.
        $this->assertNull(Cache::get('queue.heartbeat.alert_sent_at'));
    }

    public function test_check_heartbeat_alerts_when_stale(): void
    {
        Cache::put(QueueHeartbeatJob::CACHE_KEY,
            now()->subHour()->toIso8601String(),  // 60min stale
            3600);

        Mail::fake();
        $this->artisan('queue:check-heartbeat')->assertExitCode(0);

        // Cache dedup key is set when an alert fires.
        $this->assertNotNull(Cache::get('queue.heartbeat.alert_sent_at'));
    }

    public function test_check_heartbeat_dedupe_blocks_repeat_alerts(): void
    {
        Cache::put(QueueHeartbeatJob::CACHE_KEY,
            now()->subHours(3)->toIso8601String(), 3600);
        // Fire once — sets the dedup key.
        $this->artisan('queue:check-heartbeat')->assertExitCode(0);
        $firstStamp = Cache::get('queue.heartbeat.alert_sent_at');

        // Fire again immediately — dedup must short-circuit.
        $this->artisan('queue:check-heartbeat')
            ->expectsOutputToContain('alert deduplicated')
            ->assertExitCode(0);

        $secondStamp = Cache::get('queue.heartbeat.alert_sent_at');
        $this->assertSame($firstStamp, $secondStamp,
            'dedup key must not be overwritten by the duplicate run');
    }

    public function test_check_heartbeat_alerts_when_key_never_seen(): void
    {
        Cache::forget(QueueHeartbeatJob::CACHE_KEY);

        $this->artisan('queue:check-heartbeat')
            ->expectsOutputToContain('never reported')
            ->assertExitCode(0);

        $this->assertNotNull(Cache::get('queue.heartbeat.alert_sent_at'));
    }

    // =========================================================================
    // orders:backfill-delivered-at
    // =========================================================================

    public function test_backfill_delivered_at_repairs_line_orders(): void
    {
        // Schema for the orders table varies across migrations; gate
        // by table presence so this test isn't brittle on minimal envs.
        if (!Schema::hasTable('orders')
            || !Schema::hasColumn('orders', 'delivery_method')
            || !Schema::hasColumn('orders', 'delivery_status')
            || !Schema::hasColumn('orders', 'delivered_at')) {
            $this->markTestSkipped('orders table missing required delivery columns');
        }

        $orderId = DB::table('orders')->insertGetId([
            'user_id'         => 1,
            'order_number'    => 'BF-' . uniqid(),
            'total'           => 100,
            'status'          => 'paid',
            'delivery_method' => 'line',
            'delivery_status' => 'sent',
            'delivered_at'    => null,
            'created_at'      => now(),
            'updated_at'      => now()->subHour(),
        ]);

        $this->artisan('orders:backfill-delivered-at')
            ->expectsOutputToContain('Backfilled 1 row')
            ->assertExitCode(0);

        $row = DB::table('orders')->where('id', $orderId)->first();
        $this->assertNotNull($row->delivered_at,
            'backfill must populate delivered_at');
    }

    public function test_backfill_dry_run_does_not_write(): void
    {
        if (!Schema::hasTable('orders')
            || !Schema::hasColumn('orders', 'delivery_method')) {
            $this->markTestSkipped('orders schema not present');
        }

        $orderId = DB::table('orders')->insertGetId([
            'user_id'         => 1,
            'order_number'    => 'BF2-' . uniqid(),
            'total'           => 100,
            'status'          => 'paid',
            'delivery_method' => 'line',
            'delivery_status' => 'sent',
            'delivered_at'    => null,
            'created_at'      => now(),
            'updated_at'      => now()->subHour(),
        ]);

        $this->artisan('orders:backfill-delivered-at --dry-run')
            ->assertExitCode(0);

        $row = DB::table('orders')->where('id', $orderId)->first();
        $this->assertNull($row->delivered_at,
            'dry-run must NOT touch any row');
    }

    public function test_backfill_skips_orders_already_having_delivered_at(): void
    {
        if (!Schema::hasTable('orders')
            || !Schema::hasColumn('orders', 'delivery_method')) {
            $this->markTestSkipped('orders schema not present');
        }

        $earlier = now()->subDays(5);
        $orderId = DB::table('orders')->insertGetId([
            'user_id'         => 1,
            'order_number'    => 'BF3-' . uniqid(),
            'total'           => 100,
            'status'          => 'paid',
            'delivery_method' => 'line',
            'delivery_status' => 'delivered',
            'delivered_at'    => $earlier,    // already populated
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->artisan('orders:backfill-delivered-at')
            ->expectsOutputToContain('Nothing to backfill')
            ->assertExitCode(0);

        $row = DB::table('orders')->where('id', $orderId)->first();
        // Timestamp didn't shift.
        $this->assertNotNull($row->delivered_at);
    }

    // =========================================================================
    // audit:prune
    // =========================================================================

    public function test_audit_prune_deletes_rows_older_than_retention(): void
    {
        // Seed an old + a fresh row in line_deliveries.
        DB::table('line_deliveries')->insert([
            [
                'line_user_id'  => 'U' . str_repeat('a', 32),
                'delivery_type' => 'push',
                'message_type'  => 'text',
                'status'        => 'sent',
                'created_at'    => now()->subDays(120),  // > 90d default
            ],
            [
                'line_user_id'  => 'U' . str_repeat('b', 32),
                'delivery_type' => 'push',
                'message_type'  => 'text',
                'status'        => 'sent',
                'created_at'    => now()->subDays(2),    // fresh
            ],
        ]);

        $this->artisan('audit:prune')->assertExitCode(0);

        // Old row gone.
        $this->assertSame(0,
            DB::table('line_deliveries')
                ->where('line_user_id', 'U' . str_repeat('a', 32))->count(),
            '120-day-old row must be pruned',
        );
        // Fresh row preserved.
        $this->assertSame(1,
            DB::table('line_deliveries')
                ->where('line_user_id', 'U' . str_repeat('b', 32))->count(),
            '2-day-old row must be kept',
        );
    }

    public function test_audit_prune_dry_run_does_not_delete(): void
    {
        DB::table('line_deliveries')->insert([
            'line_user_id'  => 'U' . str_repeat('c', 32),
            'delivery_type' => 'push',
            'message_type'  => 'text',
            'status'        => 'sent',
            'created_at'    => now()->subDays(120),
        ]);

        $before = DB::table('line_deliveries')->count();
        $this->artisan('audit:prune --dry-run')->assertExitCode(0);
        $after = DB::table('line_deliveries')->count();
        $this->assertSame($before, $after, 'dry-run must not delete');
    }

    public function test_audit_prune_skips_active_gcal_watch_channels(): void
    {
        // Active channels should NEVER be pruned even if they're old —
        // pruning a live watch breaks the reverse-sync pipeline.
        DB::table('gcal_watch_channels')->insert([
            'photographer_id' => 1,
            'channel_id'      => 'ch-old-active',
            'resource_id'     => 'r',
            'token'           => 't',
            'expiration_at'   => now()->addDay(),
            'last_renewed_at' => now()->subDays(60),
            'status'          => 'active',
            'created_at'      => now()->subDays(100),
            'updated_at'      => now(),
        ]);
        DB::table('gcal_watch_channels')->insert([
            'photographer_id' => 2,
            'channel_id'      => 'ch-old-stopped',
            'resource_id'     => 'r2',
            'token'           => 't2',
            'expiration_at'   => now()->subDays(60),
            'last_renewed_at' => now()->subDays(60),
            'status'          => 'stopped',
            'created_at'      => now()->subDays(60),
            'updated_at'      => now()->subDays(60),
        ]);

        $this->artisan('audit:prune')->assertExitCode(0);

        // Active row still there.
        $this->assertSame(1,
            DB::table('gcal_watch_channels')
                ->where('channel_id', 'ch-old-active')->count(),
            'active watch channel must NEVER be pruned regardless of age');
        // Stopped+old row deleted.
        $this->assertSame(0,
            DB::table('gcal_watch_channels')
                ->where('channel_id', 'ch-old-stopped')->count());
    }
}
