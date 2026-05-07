<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event close lifecycle — separate "close sale" from "delete".
 *
 * Why this is needed
 * ──────────────────
 * Today the photographer's only off-ramp for an event they no longer
 * want to sell is the destructive `events.destroy` action which:
 *   • Hard-deletes the row + all event_photos (FK CASCADE)
 *   • Purges R2 storage at events/{id}/* — irreversible
 *   • Orphans any existing orders.event_id (no FK constraint there)
 *     so customers who already paid can't download their photos
 *     and have to dispute via support.
 *
 * The right model is the same one Notion / Stripe / event-ticketing
 * platforms use: a non-destructive "close" that pauses NEW sales while
 * keeping all existing data + customer access intact, plus a strict
 * "delete" that's only allowed when there are no paid orders to strand.
 *
 * Schema changes
 * ──────────────
 *  1. Extend `event_events.status` CHECK to include 'closed'
 *     • active|published → currently selling (counted toward
 *       max_concurrent_events cap)
 *     • closed           → not selling anymore. Customers can still
 *       download what they paid for. Doesn't count toward the cap, so
 *       the photographer can open new events without first deleting.
 *     • archived|hidden  → existing tombstone statuses, kept as-is
 *     • draft            → existing pre-publish state
 *
 *  2. Add `sales_ends_at` (nullable timestamp)
 *     When set, a daily cron auto-flips status active|published →
 *     closed at that timestamp. UI shows "ปิดการขายเมื่อ DD/MM HH:mm".
 *     Photographer can:
 *       • leave NULL → manual close only
 *       • set future date → scheduled auto-close
 *       • set past date → flips to closed on next cron tick
 *
 *  3. Add `closed_at` (nullable timestamp)
 *     Stamped when the status transitions to 'closed' (manually or by
 *     cron). Lets the dashboard show "ปิดเมื่อ X วันที่แล้ว" without
 *     having to rely on updated_at (which gets bumped by every save).
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // 1) Extend status CHECK to include 'closed'.
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE event_events DROP CONSTRAINT IF EXISTS event_events_status_check');
            DB::statement("
                ALTER TABLE event_events
                ADD CONSTRAINT event_events_status_check
                CHECK (status IN ('draft', 'published', 'active', 'closed', 'archived', 'hidden'))
            ");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("
                ALTER TABLE event_events
                MODIFY COLUMN status ENUM('draft','published','active','closed','archived','hidden') NOT NULL
            ");
        }
        // SQLite test DB — CHECK constraint defined inline at table create
        // can't be ALTERed. Tests don't exercise the CHECK so the seed step
        // works regardless. Production targets pgsql/mysql which DO get
        // rewritten above.

        // 2 & 3) Add the lifecycle timestamps.
        Schema::table('event_events', function (Blueprint $t) {
            $t->timestamp('sales_ends_at')->nullable()->after('end_time')
                ->comment('When set: auto-close on cron tick. NULL = manual close only.');
            $t->timestamp('closed_at')->nullable()->after('sales_ends_at')
                ->comment('Stamped on transition active|published → closed.');
            $t->index('sales_ends_at', 'event_sales_ends_at_idx'); // cron tick scan
            $t->index('closed_at', 'event_closed_at_idx');         // dashboard "recently closed" lists
        });
    }

    public function down(): void
    {
        Schema::table('event_events', function (Blueprint $t) {
            $t->dropIndex('event_sales_ends_at_idx');
            $t->dropIndex('event_closed_at_idx');
            $t->dropColumn(['sales_ends_at', 'closed_at']);
        });

        $driver = DB::connection()->getDriverName();

        // Flip any 'closed' rows back to 'archived' BEFORE narrowing the
        // CHECK constraint, otherwise the ALTER would fail on existing data.
        DB::table('event_events')->where('status', 'closed')->update(['status' => 'archived']);

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE event_events DROP CONSTRAINT IF EXISTS event_events_status_check');
            DB::statement("
                ALTER TABLE event_events
                ADD CONSTRAINT event_events_status_check
                CHECK (status IN ('draft', 'published', 'active', 'archived', 'hidden'))
            ");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("
                ALTER TABLE event_events
                MODIFY COLUMN status ENUM('draft','published','active','archived','hidden') NOT NULL
            ");
        }
    }
};
