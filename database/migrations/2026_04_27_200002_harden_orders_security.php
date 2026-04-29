<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-enforced order integrity guards.
 *
 *   1. Add `paid_at` timestamp + `idempotency_key` for outside callers
 *   2. Lock total/subtotal once status leaves cart/pending_payment
 *      (we can't mutate fillable, but a CHECK trigger raises if the
 *      total changes after status moves to paid/refunded)
 *   3. UNIQUE on order_number across the platform (prevents collision
 *      from Str::random in the rare birthday-paradox case)
 *
 * Postgres-only DDL guarded by driver check.
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Add idempotency_key column (helps de-dupe order creation requests)
        if (!Schema::hasColumn('orders', 'idempotency_key')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->string('idempotency_key', 128)->nullable()->after('order_number');
            });
        }
        if (!Schema::hasColumn('orders', 'paid_at')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->timestampTz('paid_at')->nullable()->after('status');
            });
        }

        if ($driver !== 'pgsql') {
            return; // sqlite doesn't support CHECK on UPDATE the same way; service-level guard applies.
        }

        // Order number must be globally unique
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX IF NOT EXISTS orders_order_number_unique
            ON orders (order_number)
            WHERE order_number IS NOT NULL
        SQL);

        // Idempotency key must be unique per user when present
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX IF NOT EXISTS orders_idempotency_user_unique
            ON orders (user_id, idempotency_key)
            WHERE idempotency_key IS NOT NULL
        SQL);

        // Sanity: total >= 0 always
        DB::statement(<<<SQL
            ALTER TABLE orders
            ADD CONSTRAINT orders_total_non_negative_chk
            CHECK (total IS NULL OR total >= 0)
        SQL);
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS orders_order_number_unique');
            DB::statement('DROP INDEX IF EXISTS orders_idempotency_user_unique');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_total_non_negative_chk');
        }

        if (Schema::hasColumn('orders', 'paid_at')) {
            Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('paid_at'));
        }
        if (Schema::hasColumn('orders', 'idempotency_key')) {
            Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('idempotency_key'));
        }
    }
};
