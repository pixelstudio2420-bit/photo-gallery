<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circuit breakers — last-line defence against runaway feature cost.
 *
 * When `spent_thb >= threshold_thb` for the current window, the breaker
 * trips OPEN and the feature is disabled platform-wide until either:
 *   - the period rolls over (auto-reset), or
 *   - an admin manually resets it.
 *
 * One row per feature (e.g. 'ai.face_search', 'ai.preset_generate'),
 * keyed by `feature` so reads are a single-row PK lookup on the hot path.
 *
 * State machine:
 *   closed     → normal operation
 *   open       → feature is rejected for everyone
 *   half_open  → admin manually unlocked; first failure trips back open
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('circuit_breakers', function (Blueprint $t) {
            $t->string('feature', 64)->primary();
            $t->string('state', 16)->default('closed');
            $t->timestampTz('opened_at')->nullable();
            $t->timestampTz('reopened_at')->nullable();
            $t->decimal('threshold_thb', 12, 2);
            $t->decimal('spent_thb', 12, 2)->default(0);
            $t->timestampTz('period_starts');
            $t->timestampTz('period_ends');
            $t->text('notes')->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuit_breakers');
    }
};
