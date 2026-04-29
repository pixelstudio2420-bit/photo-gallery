<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Expand the digital_orders.status ENUM to include values the application
     * actually uses: pending_payment, pending_review, failed, expired.
     *
     * Original ENUM was: ['pending','paid','cancelled','refunded']
     * Controllers/views use: pending_payment, pending_review, paid, cancelled,
     * refunded, failed — causing "Data truncated for column 'status'" errors.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `digital_orders` MODIFY COLUMN `status` ENUM(
                'pending',
                'pending_payment',
                'pending_review',
                'paid',
                'cancelled',
                'refunded',
                'failed',
                'expired'
            ) NOT NULL DEFAULT 'pending_payment'");
        } elseif ($driver === 'pgsql') {
            // Constraint name follows Laravel default: <table>_<column>_check
            DB::statement('ALTER TABLE digital_orders DROP CONSTRAINT IF EXISTS digital_orders_status_check');
            DB::statement("
                ALTER TABLE digital_orders
                ADD CONSTRAINT digital_orders_status_check
                CHECK (status IN (
                    'pending','pending_payment','pending_review','paid',
                    'cancelled','refunded','failed','expired'
                ))
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // Revert any extended values back to 'pending' to avoid data loss on rollback
            DB::statement("UPDATE digital_orders SET status='pending' WHERE status IN ('pending_payment','pending_review','failed','expired')");
            DB::statement("ALTER TABLE `digital_orders` MODIFY COLUMN `status` ENUM(
                'pending','paid','cancelled','refunded'
            ) NOT NULL DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            DB::statement("UPDATE digital_orders SET status='pending' WHERE status IN ('pending_payment','pending_review','failed','expired')");
            DB::statement('ALTER TABLE digital_orders DROP CONSTRAINT IF EXISTS digital_orders_status_check');
            DB::statement("
                ALTER TABLE digital_orders
                ADD CONSTRAINT digital_orders_status_check
                CHECK (status IN ('pending','paid','cancelled','refunded'))
            ");
        }
    }
};
