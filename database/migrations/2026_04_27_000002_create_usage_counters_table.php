<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hot rolling counters keyed by (user, resource, period, period_key).
 *
 * The middleware reads here on every request — no JOIN, no aggregation,
 * just a primary-key lookup. UsageMeter::record() updates this table in
 * the same transaction as the usage_events insert so the counter never
 * drifts.
 *
 * Period values: 'minute', 'hour', 'day', 'month'.
 * Period keys:
 *   - minute → '2026-04-27T13:45'
 *   - hour   → '2026-04-27T13'
 *   - day    → '2026-04-27'
 *   - month  → '2026-04'
 *
 * Old keys are aged out by a daily prune job (>13 months for month rows,
 * >35 days for day, >48h for hour, >2h for minute).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id');
            $t->string('resource', 48);
            $t->string('period', 8);
            $t->string('period_key', 32);
            $t->unsignedBigInteger('units')->default(0);
            $t->bigInteger('cost_microcents')->default(0);
            $t->timestampTz('updated_at')->useCurrent();

            $t->primary(['user_id', 'resource', 'period', 'period_key']);
            $t->index(['resource', 'period', 'period_key'], 'usage_counters_resource_window');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
    }
};
