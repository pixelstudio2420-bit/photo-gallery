<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photographer recurring availability slots.
 *
 * Each row defines either:
 *   • A weekly recurring window (e.g. "every Mon 09:00–17:00 = available")
 *   • A one-off override (e.g. "2026-12-25 = blocked, holiday")
 *
 * The customer booking form filters slots by these rules — only times
 * inside an available window AND outside any block are bookable.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('photographer_availabilities')) return;

        Schema::create('photographer_availabilities', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('photographer_id'); // = auth_users.id

            // ── Type ────────────────────────────────────────────────────
            //  recurring: applies every week (uses day_of_week)
            //  override : single-day ad-hoc rule (uses specific_date)
            $t->enum('type', ['recurring', 'override'])->default('recurring');

            // ── Recurring fields ────────────────────────────────────────
            $t->unsignedTinyInteger('day_of_week')->nullable(); // 0=Sun..6=Sat

            // ── Override fields ─────────────────────────────────────────
            $t->date('specific_date')->nullable();

            // ── Time window ─────────────────────────────────────────────
            $t->time('time_start');
            $t->time('time_end');

            // ── Effect ──────────────────────────────────────────────────
            //  available: bookable
            //  blocked  : not bookable (holiday, personal time, etc.)
            $t->enum('effect', ['available', 'blocked'])->default('available');

            // Optional label — "Wedding photography hours", "Lunch break"
            $t->string('label', 100)->nullable();

            $t->timestamps();

            // ── Indexes ────────────────────────────────────────────────
            $t->index(['photographer_id', 'type']);
            $t->index(['photographer_id', 'day_of_week']);
            $t->index(['photographer_id', 'specific_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photographer_availabilities');
    }
};
