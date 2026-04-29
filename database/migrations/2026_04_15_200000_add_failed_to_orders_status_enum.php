<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // MySQL: ALTER TABLE to expand ENUM
        // Postgres: drop & re-add the CHECK constraint Laravel generated for enum()
        // SQLite: rewrite the table's CHECK constraint via writable_schema —
        //         Laravel's enum() generates a CHECK on sqlite, so the new
        //         'failed' status would be rejected without this branch.
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('cart','pending_payment','pending_review','paid','cancelled','refunded','failed') NOT NULL DEFAULT 'pending_payment'");
        } elseif ($driver === 'pgsql') {
            // Constraint name follows Laravel default: <table>_<column>_check
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
            DB::statement("
                ALTER TABLE orders
                ADD CONSTRAINT orders_status_check
                CHECK (status IN ('cart','pending_payment','pending_review','paid','cancelled','refunded','failed'))
            ");
        } elseif ($driver === 'sqlite') {
            // SQLite does not support ALTER TABLE on CHECK constraints. The
            // textbook fix is to recreate the table; a lighter alternative
            // — used in-memory test DBs — is to patch the schema string in
            // sqlite_master after enabling writable_schema. We catch the
            // not-found case so this is a no-op on environments where the
            // CHECK clause was never applied.
            //
            // Laravel's sqlite enum() emits the values with a space after
            // each comma: `'cart', 'pending_payment', ...`. Match that
            // formatting exactly so the str_replace fires.
            try {
                $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='orders'");
                $oldEnum = "'cart', 'pending_payment', 'pending_review', 'paid', 'cancelled', 'refunded'";
                $newEnum = "'cart', 'pending_payment', 'pending_review', 'paid', 'cancelled', 'refunded', 'failed'";
                if ($row && is_string($row->sql)
                    && str_contains($row->sql, $oldEnum)
                    && !str_contains($row->sql, "'failed'")) {
                    $newSql = str_replace($oldEnum, $newEnum, $row->sql);
                    DB::statement('PRAGMA writable_schema = 1');
                    DB::update(
                        "UPDATE sqlite_master SET sql = ? WHERE type='table' AND name='orders'",
                        [$newSql]
                    );
                    DB::statement('PRAGMA writable_schema = 0');
                }
            } catch (\Throwable) {
                // Best-effort — sqlite is dev/test only and the production
                // path uses Postgres; we'd rather not abort the migration
                // run than die on an environment quirk.
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('cart','pending_payment','pending_review','paid','cancelled','refunded') NOT NULL DEFAULT 'pending_payment'");
        } elseif ($driver === 'pgsql') {
            DB::statement("UPDATE orders SET status='pending_payment' WHERE status='failed'");
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
            DB::statement("
                ALTER TABLE orders
                ADD CONSTRAINT orders_status_check
                CHECK (status IN ('cart','pending_payment','pending_review','paid','cancelled','refunded'))
            ");
        }
    }
};
