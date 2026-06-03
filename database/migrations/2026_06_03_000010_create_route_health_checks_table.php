<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History store for the Route & Page Health monitor.
 *
 * Each row is one target checked in one run. The dashboard aggregates these
 * for uptime % (last 30d), the recent-runs timeline, and per-route trends.
 * Pruned by the existing audit:prune sweep (TTL via env, see
 * PruneAuditTablesCommand) so the table can't grow unbounded.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('route_health_checks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('run_id', 32)->index();   // groups all targets in one run
            $table->string('target_key', 64)->index();
            $table->string('label', 120);
            $table->string('kind', 16)->default('route');   // route | page
            $table->string('path', 512);
            $table->unsignedSmallInteger('status')->nullable();   // HTTP status (599 = uncaught throw)
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('result', 8)->default('ok');   // ok | warn | fail
            $table->text('error')->nullable();             // short reason / exception snippet
            $table->timestamp('checked_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_health_checks');
    }
};
