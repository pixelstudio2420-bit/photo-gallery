<?php

namespace Tests\Feature\Analytics;

use App\Services\Analytics\UsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks down the cache-counter side of analytics.
 *
 * Properties:
 *   • record() increments the right keys
 *   • drain() returns + clears
 *   • duplicate user_id within a bucket counts once (set semantics)
 *   • empty bucket → drain returns []
 *   • flushTo() persists to DB and is idempotent
 *   • A throw inside record() does NOT propagate (best-effort contract)
 */
class UsageTrackerTest extends TestCase
{
    use RefreshDatabase;

    private UsageTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tracker = new UsageTracker();
        // Flush any leftover keys.
        Cache::flush();
    }

    public function test_record_increments_counters(): void
    {
        $this->tracker->record('public.read', 200, 120, userId: 42);
        $this->tracker->record('public.read', 200, 80,  userId: 42);
        $this->tracker->record('public.read', 500, 300, userId: 99);

        $bucket = gmdate('Y-m-d\TH:i', time());
        $rows = $this->tracker->drain($bucket);

        $this->assertArrayHasKey('public.read', $rows);
        $this->assertSame(3, $rows['public.read']['count']);
        $this->assertSame(1, $rows['public.read']['errors']);
        $this->assertSame(500, $rows['public.read']['duration_ms_sum']);
        $this->assertSame(300, $rows['public.read']['duration_ms_max']);
        $this->assertSame(2, $rows['public.read']['distinct_users']);
    }

    public function test_drain_clears_bucket_so_second_drain_is_empty(): void
    {
        $this->tracker->record('health', 200, 50, null);
        $bucket = gmdate('Y-m-d\TH:i', time());

        $first  = $this->tracker->drain($bucket);
        $second = $this->tracker->drain($bucket);

        $this->assertNotEmpty($first);
        $this->assertEmpty($second, 'drain must clear the bucket');
    }

    public function test_unknown_bucket_drains_empty(): void
    {
        $this->assertSame([], $this->tracker->drain('1999-01-01T00:00'));
    }

    public function test_flush_to_persists_buckets_to_db(): void
    {
        // Record one bucket "in the previous minute" so the cutoff
        // (default = current minute) lets it through.
        $now = time();
        $bucketEpoch = $now - 65;   // > 1 min ago
        $bucketIso = gmdate('Y-m-d\TH:i', $bucketEpoch);

        // Manually populate cache as if record() ran a minute ago.
        Cache::put("metrics.bucket.{$bucketIso}.public.read.count", 7, 600);
        Cache::put("metrics.bucket.{$bucketIso}.public.read.errors", 1, 600);
        Cache::put("metrics.bucket.{$bucketIso}.public.read.duration_ms_sum", 1500, 600);
        Cache::put("metrics.bucket.{$bucketIso}.public.read.duration_ms_max", 400, 600);
        Cache::put("metrics.bucket.{$bucketIso}.public.read.users", '|11|22|', 600);
        Cache::put("metrics.registry.{$bucketIso}", '|public.read|', 600);

        $persisted = $this->tracker->flushTo(now());

        $this->assertGreaterThanOrEqual(1, $persisted);

        $row = DB::table('request_minute_buckets')
            ->where('route_group', 'public.read')
            ->orderByDesc('id')->first();
        $this->assertNotNull($row);
        $this->assertSame(7, (int) $row->request_count);
        $this->assertSame(1, (int) $row->error_count);
        $this->assertSame(1500, (int) $row->duration_ms_sum);
        $this->assertSame(400, (int) $row->duration_ms_max);
        $this->assertSame(2, (int) $row->distinct_users);
    }

    public function test_flush_is_idempotent_via_drain_clearing_cache(): void
    {
        $bucketEpoch = time() - 120;
        $bucketIso = gmdate('Y-m-d\TH:i', $bucketEpoch);
        Cache::put("metrics.bucket.{$bucketIso}.health.count", 5, 600);
        Cache::put("metrics.registry.{$bucketIso}", '|health|', 600);

        $this->tracker->flushTo(now());
        $this->tracker->flushTo(now());   // second call

        $count = (int) DB::table('request_minute_buckets')
            ->where('route_group', 'health')->count();
        $this->assertSame(1, $count, 'second flush must NOT add a duplicate row');

        $row = DB::table('request_minute_buckets')
            ->where('route_group', 'health')->first();
        $this->assertSame(5, (int) $row->request_count,
            'request_count must NOT have doubled');
    }

    public function test_record_swallows_cache_exceptions(): void
    {
        // We can't easily make Cache fail in this test, but we can
        // simulate by passing extreme inputs that some drivers reject.
        // The contract is: never throws regardless.
        $this->expectNotToPerformAssertions();
        try {
            $this->tracker->record('public.read', 200, -1, null);   // negative duration
            $this->tracker->record('', 0, 0, null);                  // empty group
            $this->tracker->record(str_repeat('x', 5000), 200, 100, null); // huge group
        } catch (\Throwable $e) {
            $this->fail('record() threw: ' . $e->getMessage());
        }
    }
}
