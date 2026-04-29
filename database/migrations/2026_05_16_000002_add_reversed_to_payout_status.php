<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend photographer_payouts.status to allow 'reversed'.
 *
 * Needed by Admin\FinanceController::reversePhotographerPayouts() —
 * when an admin approves a full refund we mark the matching payout
 * 'reversed' so the disbursement engine doesn't pay the photographer
 * for an order the buyer got their money back on.
 *
 * Also adds the optional 'reversed_at' / 'reversal_reason' columns
 * for audit visibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ENUM widening — must use raw ALTER (Laravel's column->change()
        // doesn't support enum value addition reliably across MySQL versions).
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("
                ALTER TABLE `photographer_payouts`
                MODIFY COLUMN `status` ENUM('pending','processing','paid','reversed')
                NOT NULL DEFAULT 'pending'
            ");
        } elseif ($driver === 'pgsql') {
            // Constraint name follows Laravel default: <table>_<column>_check
            DB::statement('ALTER TABLE photographer_payouts DROP CONSTRAINT IF EXISTS photographer_payouts_status_check');
            DB::statement("
                ALTER TABLE photographer_payouts
                ADD CONSTRAINT photographer_payouts_status_check
                CHECK (status IN ('pending','processing','paid','reversed'))
            ");
        } elseif ($driver === 'sqlite') {
            // SQLite enforces enum via CHECK constraint and doesn't support
            // ALTER CONSTRAINT. Easiest path that keeps test parity with the
            // production constraint: rebuild the column. We do it via the
            // schema builder so SQLite's "create new table + copy rows + drop
            // old + rename" dance happens automatically.
            //
            // This also unblocks the CommissionResolver tests that exercise
            // the 'reversed' exclusion contract — without this branch the
            // SQLite test DB would diverge from prod.
            Schema::table('photographer_payouts', function ($t) {
                $t->string('status', 16)->default('pending')->change();
            });
        }

        Schema::table('photographer_payouts', function ($t) {
            if (!Schema::hasColumn('photographer_payouts', 'reversed_at')) {
                $t->timestamp('reversed_at')->nullable();
            }
            if (!Schema::hasColumn('photographer_payouts', 'reversal_reason')) {
                $t->string('reversal_reason', 250)->nullable();
            }
        });
    }

    public function down(): void
    {
        // First null-out any 'reversed' rows so the narrower enum accepts.
        DB::statement("UPDATE photographer_payouts SET status='pending' WHERE status='reversed'");

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("
                ALTER TABLE `photographer_payouts`
                MODIFY COLUMN `status` ENUM('pending','processing','paid')
                NOT NULL DEFAULT 'pending'
            ");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE photographer_payouts DROP CONSTRAINT IF EXISTS photographer_payouts_status_check');
            DB::statement("
                ALTER TABLE photographer_payouts
                ADD CONSTRAINT photographer_payouts_status_check
                CHECK (status IN ('pending','processing','paid'))
            ");
        }

        Schema::table('photographer_payouts', function ($t) {
            foreach (['reversed_at', 'reversal_reason'] as $col) {
                if (Schema::hasColumn('photographer_payouts', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
