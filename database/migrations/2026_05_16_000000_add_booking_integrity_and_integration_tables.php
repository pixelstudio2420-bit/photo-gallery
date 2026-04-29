<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Booking-system hardening — schema for the integrity / integration /
 * audit fixes the production audit flagged.
 *
 * What this adds
 * ==============
 *
 * 1) bookings.idempotency_key       — caller-supplied (Idempotency-Key
 *    header on POST /bookings). Combined with a partial unique index
 *    so a webhook retry / double-tap can't create two bookings.
 *
 * 2) bookings.deposit_idempotency_key — same pattern for the deposit
 *    payment webhook. Without this, a Stripe retry doubles the
 *    deposit_paid total because markDepositPaid() increments.
 *
 * 3) booking_calendar_sync          — queue/retry state for GCal sync
 *    operations. The current code does inline best-effort sync; this
 *    table makes "did this booking sync to Google Calendar?" a single
 *    SQL query, and lets a worker retry on transient 5xx without
 *    blocking the booking confirm flow.
 *
 * 4) booking_sheets_exports         — same audit pattern for the new
 *    Google Sheets export. One row per (sheet, booking) sync attempt.
 *
 * 5) booking_reminder_locks         — advisory-lock-style mutex rows
 *    used by SendBookingReminders to atomically CLAIM a reminder
 *    slot. Replaces the read-then-write race in the current code.
 *
 * Driver portability
 * ------------------
 * Postgres + sqlite both support partial unique indexes. MySQL gets a
 * stricter (NULLs not equal) constraint as a fallback.
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // ── (1) + (2) Booking idempotency columns ──────────────────────
        Schema::table('bookings', function (Blueprint $t) {
            if (!Schema::hasColumn('bookings', 'idempotency_key')) {
                $t->string('idempotency_key', 64)->nullable();
            }
            if (!Schema::hasColumn('bookings', 'deposit_idempotency_key')) {
                $t->string('deposit_idempotency_key', 64)->nullable();
            }
        });

        if ($driver === 'pgsql' || $driver === 'sqlite') {
            $this->maybePartialUnique($driver, 'uniq_bookings_idempotency_key',
                "CREATE UNIQUE INDEX uniq_bookings_idempotency_key
                 ON bookings(idempotency_key) WHERE idempotency_key IS NOT NULL");
            $this->maybePartialUnique($driver, 'uniq_bookings_deposit_idempotency_key',
                "CREATE UNIQUE INDEX uniq_bookings_deposit_idempotency_key
                 ON bookings(deposit_idempotency_key) WHERE deposit_idempotency_key IS NOT NULL");
        } else {
            try { DB::statement('CREATE UNIQUE INDEX uniq_bookings_idempotency_key ON bookings(idempotency_key)'); } catch (\Throwable) {}
            try { DB::statement('CREATE UNIQUE INDEX uniq_bookings_deposit_idempotency_key ON bookings(deposit_idempotency_key)'); } catch (\Throwable) {}
        }

        // ── (3) Calendar sync audit ─────────────────────────────────────
        if (!Schema::hasTable('booking_calendar_sync')) {
            Schema::create('booking_calendar_sync', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('booking_id');
                $t->unsignedInteger('photographer_id');
                // upsert | delete
                $t->string('operation', 16);
                // pending → succeeded | failed | skipped
                $t->string('status', 16)->default('pending');
                $t->string('gcal_event_id', 256)->nullable();
                $t->unsignedSmallInteger('http_status')->nullable();
                $t->string('error', 500)->nullable();
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->timestamp('synced_at')->nullable();
                $t->timestamps();

                $t->index('booking_id');
                $t->index(['photographer_id', 'status']);
                $t->index('created_at');
            });
        }

        // ── (4) Sheets export audit ─────────────────────────────────────
        if (!Schema::hasTable('booking_sheets_exports')) {
            Schema::create('booking_sheets_exports', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('booking_id');
                $t->string('spreadsheet_id', 128);
                $t->string('range', 64)->default('Bookings!A:M');
                // append | update — append = new row; update = locate row + replace
                $t->string('operation', 16)->default('append');
                $t->string('status', 16)->default('pending');
                $t->string('row_a1', 32)->nullable();   // e.g. "Bookings!A37"
                $t->unsignedSmallInteger('http_status')->nullable();
                $t->string('error', 500)->nullable();
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->timestamp('synced_at')->nullable();
                $t->timestamps();

                $t->index('booking_id');
                $t->index(['spreadsheet_id', 'status']);
            });
        }

        // ── (5) Reminder claim mutex ────────────────────────────────────
        // One row per (booking_id, reminder_slot) combo. The slot column
        // is one of 3d|1d|1h|day|post. The unique constraint makes
        // the "claim a reminder" operation atomic: INSERT IGNORE wins
        // for exactly one process; concurrent crons see the duplicate
        // and skip without sending.
        if (!Schema::hasTable('booking_reminder_claims')) {
            Schema::create('booking_reminder_claims', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('booking_id');
                $t->string('slot', 8);
                $t->timestamp('claimed_at');
                $t->string('claimed_by', 64)->nullable();   // host:pid for forensics
                $t->timestamp('sent_at')->nullable();
                $t->string('status', 16)->default('claimed');  // claimed | sent | failed
                $t->string('error', 500)->nullable();
                $t->timestamps();

                $t->unique(['booking_id', 'slot'], 'uniq_reminder_claim');
                $t->index('claimed_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reminder_claims');
        Schema::dropIfExists('booking_sheets_exports');
        Schema::dropIfExists('booking_calendar_sync');

        $driver = DB::connection()->getDriverName();
        foreach (['uniq_bookings_idempotency_key', 'uniq_bookings_deposit_idempotency_key'] as $idx) {
            try {
                if ($driver === 'pgsql' || $driver === 'sqlite') {
                    DB::statement("DROP INDEX IF EXISTS {$idx}");
                } else {
                    DB::statement("DROP INDEX {$idx} ON bookings");
                }
            } catch (\Throwable) {}
        }
        Schema::table('bookings', function (Blueprint $t) {
            foreach (['idempotency_key', 'deposit_idempotency_key'] as $c) {
                if (Schema::hasColumn('bookings', $c)) $t->dropColumn($c);
            }
        });
    }

    private function maybePartialUnique(string $driver, string $name, string $sql): void
    {
        $exists = DB::selectOne(
            $driver === 'pgsql'
                ? "SELECT 1 FROM pg_indexes WHERE indexname = ?"
                : "SELECT 1 FROM sqlite_master WHERE type='index' AND name = ?",
            [$name],
        );
        if (!$exists) DB::statement($sql);
    }
};
