<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track which event each order item came from.
 *
 * Without this column, multi-event carts (a buyer puts photos from
 * Event A AND Event B in the same cart) collapsed to a single
 * order.event_id = first item's event. Side-effects:
 *
 *   1) The whole subtotal was priced as if every photo was from Event A
 *      (fewer ฿ collected when Event B charged more per photo).
 *   2) The single PhotographerPayout went to Event A's photographer for
 *      the WHOLE order — Event B's photographer got nothing despite
 *      their photo being sold.
 *
 * With order_items.event_id we can:
 *   - Use each item's true event price at checkout time
 *   - Split the photographer payout per unique event_id at fulfillment
 *
 * Backfilled from order.event_id where possible (single-event orders).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            if (!Schema::hasColumn('order_items', 'event_id')) {
                $t->unsignedBigInteger('event_id')->nullable()->after('photo_id');
                $t->index('event_id');
            }
        });

        // Backfill from order.event_id where the order is single-event.
        // Each driver speaks its own UPDATE-with-JOIN dialect:
        //   - Postgres: UPDATE … FROM …
        //   - MySQL:    UPDATE … INNER JOIN …
        //   - SQLite:   UPDATE … WHERE EXISTS … (correlated subquery)
        if (Schema::hasColumn('order_items', 'event_id')) {
            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                \Illuminate\Support\Facades\DB::statement('
                    UPDATE order_items
                    SET event_id = o.event_id
                    FROM orders o
                    WHERE order_items.order_id = o.id
                      AND order_items.event_id IS NULL
                      AND o.event_id IS NOT NULL
                ');
            } elseif ($driver === 'sqlite') {
                // SQLite has neither MySQL's nor Postgres's join-update.
                // Correlated subquery is portable + supported since 3.0.
                \Illuminate\Support\Facades\DB::statement('
                    UPDATE order_items
                    SET event_id = (
                        SELECT o.event_id FROM orders o
                        WHERE o.id = order_items.order_id
                    )
                    WHERE order_items.event_id IS NULL
                      AND EXISTS (
                          SELECT 1 FROM orders o
                          WHERE o.id = order_items.order_id
                            AND o.event_id IS NOT NULL
                      )
                ');
            } else {
                // MySQL / MariaDB
                \Illuminate\Support\Facades\DB::statement('
                    UPDATE order_items oi
                    INNER JOIN orders o ON oi.order_id = o.id
                    SET oi.event_id = o.event_id
                    WHERE oi.event_id IS NULL AND o.event_id IS NOT NULL
                ');
            }
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            if (Schema::hasColumn('order_items', 'event_id')) {
                $t->dropIndex(['event_id']);
                $t->dropColumn('event_id');
            }
        });
    }
};
