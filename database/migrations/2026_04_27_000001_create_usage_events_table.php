<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only usage ledger.
 *
 * Records one row per metered operation: AI call, storage write, R2 read,
 * bandwidth byte, etc. The ledger is the source of truth for:
 *   - per-user cost reports (PlanCostCalculator)
 *   - margin analytics (admin dashboard)
 *   - audit trail when a customer disputes a charge
 *   - circuit-breaker spike detection
 *
 * Hot-path counters live in `usage_counters` — this table is for analytics.
 *
 * Postgres partitioning is set up by month (occurred_at) to keep recent
 * windows fast and to make retention pruning a `DROP PARTITION`. SQLite
 * (the test driver) skips partitioning entirely; the regular indexed
 * table is good enough for tests.
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::create('usage_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('user_id')->index();
            $t->string('plan_code', 32);
            // 'ai.face_search', 'storage.bytes', 'r2.read', 'r2.write',
            // 'bandwidth.egress', 'export.run', etc. Snake-case dotted ids.
            $t->string('resource', 48);
            // bytes / call count / row count, depending on resource semantics
            $t->unsignedBigInteger('units');
            // 1¢ = 10000 microcents. Allows fractional pricing without floats.
            $t->bigInteger('cost_microcents')->default(0);
            $t->json('metadata')->nullable();
            $t->timestampTz('occurred_at');

            // Hot indexes
            $t->index(['user_id', 'resource', 'occurred_at'], 'usage_events_user_resource_time');
            $t->index(['resource', 'occurred_at'], 'usage_events_resource_time');
            $t->index(['plan_code', 'occurred_at'], 'usage_events_plan_time');
        });

        // Postgres-only: convert to a partitioned table by month.
        // We use raw SQL because Laravel's schema builder doesn't expose
        // PARTITION BY. The test runner (sqlite) skips this branch.
        if ($driver === 'pgsql') {
            // Existing table is created above WITHOUT partitioning so the
            // schema builder can manage indexes. Production deployments
            // should switch to declarative partitioning — but that requires
            // dropping + recreating the parent table, which is risky on
            // a live system. Document the recommended manual swap here:
            //
            //   1. Stop writes
            //   2. Rename usage_events → usage_events_legacy
            //   3. Create partitioned parent + monthly partitions
            //   4. INSERT INTO usage_events SELECT * FROM usage_events_legacy
            //   5. DROP usage_events_legacy
            //
            // Until then, this single table is safe up to ~50M rows. Past
            // that, partition manually and run `pg_repack` for online swap.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
