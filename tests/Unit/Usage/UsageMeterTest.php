<?php

namespace Tests\Unit\Usage;

use App\Services\Usage\UsageMeter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifies UsageMeter writes to the ledger AND increments counters
 * atomically, and that reverse() backs out cleanly.
 *
 * The real Postgres production schema differs from sqlite (json column
 * type, partitioning), but the SQL UsageMeter emits is portable. We
 * boot a minimal sqlite schema here so tests run without the broken
 * production migration set.
 */
class UsageMeterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSchema();
    }

    private function bootSchema(): void
    {
        if (!Schema::hasTable('usage_events')) {
            Schema::create('usage_events', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id')->index();
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
    }

    public function test_record_inserts_a_ledger_row(): void
    {
        UsageMeter::record(1, 'pro', 'ai.face_search', units: 1, metadata: ['event_id' => 99]);

        $row = DB::table('usage_events')->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->user_id);
        $this->assertSame('pro', $row->plan_code);
        $this->assertSame('ai.face_search', $row->resource);
        $this->assertSame(1, (int) $row->units);
        $this->assertSame(10_000, (int) $row->cost_microcents); // $0.001 = 10000 microcents
    }

    public function test_record_increments_counters_for_every_period_bucket(): void
    {
        UsageMeter::record(1, 'pro', 'ai.face_search');

        // 4 periods: minute, hour, day, month
        $this->assertSame(4, DB::table('usage_counters')->where('user_id', 1)->count());

        $month = (string) now()->format('Y-m');
        $row = DB::table('usage_counters')
            ->where('user_id', 1)
            ->where('resource', 'ai.face_search')
            ->where('period', 'month')
            ->where('period_key', $month)
            ->first();
        $this->assertSame(1, (int) $row->units);
    }

    public function test_record_is_additive_across_calls(): void
    {
        UsageMeter::record(7, 'starter', 'ai.face_search', units: 1);
        UsageMeter::record(7, 'starter', 'ai.face_search', units: 3);
        UsageMeter::record(7, 'starter', 'ai.face_search', units: 5);

        $this->assertSame(9, UsageMeter::counter(7, 'ai.face_search', 'month'));
        $this->assertSame(3, DB::table('usage_events')->where('user_id', 7)->count());
    }

    public function test_record_isolates_users(): void
    {
        UsageMeter::record(10, 'pro', 'ai.face_search', units: 5);
        UsageMeter::record(11, 'pro', 'ai.face_search', units: 2);

        $this->assertSame(5, UsageMeter::counter(10, 'ai.face_search', 'month'));
        $this->assertSame(2, UsageMeter::counter(11, 'ai.face_search', 'month'));
    }

    public function test_record_isolates_resources(): void
    {
        UsageMeter::record(1, 'pro', 'ai.face_search', units: 5);
        UsageMeter::record(1, 'pro', 'photo.upload',   units: 100);

        $this->assertSame(5,   UsageMeter::counter(1, 'ai.face_search', 'month'));
        $this->assertSame(100, UsageMeter::counter(1, 'photo.upload',   'month'));
    }

    public function test_reverse_decrements_counters_and_appends_negative_ledger_row(): void
    {
        UsageMeter::record (1, 'pro', 'photo.upload', units: 10);
        UsageMeter::reverse(1, 'pro', 'photo.upload', units: 3);

        $this->assertSame(7, UsageMeter::counter(1, 'photo.upload', 'month'));

        $rows = DB::table('usage_events')
            ->where('user_id', 1)
            ->where('resource', 'photo.upload')
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $rows);
        $this->assertSame(10, (int) $rows[0]->units);
        $this->assertSame(-3, (int) $rows[1]->units);
    }

    public function test_record_with_zero_units_is_a_no_op(): void
    {
        UsageMeter::record(1, 'pro', 'ai.face_search', units: 0);
        $this->assertSame(0, DB::table('usage_events')->count());
        $this->assertSame(0, DB::table('usage_counters')->count());
    }

    public function test_storage_resource_records_zero_cost_in_ledger(): void
    {
        // Storage cost is amortised at report time — per-call cost is 0
        // so the ledger doesn't double-count.
        UsageMeter::record(1, 'pro', 'storage.bytes', units: 1_000_000);

        $row = DB::table('usage_events')->first();
        $this->assertSame(0, (int) $row->cost_microcents);
        $this->assertSame(1_000_000, (int) $row->units);
    }

    public function test_concurrent_records_dont_lose_increments(): void
    {
        // Simulate concurrent writers via N sequential transactions —
        // each txn upserts the same key, and the counter must equal N.
        for ($i = 0; $i < 50; $i++) {
            UsageMeter::record(99, 'starter', 'photo.upload', units: 1);
        }
        $this->assertSame(50, UsageMeter::counter(99, 'photo.upload', 'month'));
        $this->assertSame(50, DB::table('usage_events')->where('user_id', 99)->count());
    }
}
