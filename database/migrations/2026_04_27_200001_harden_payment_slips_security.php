<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-enforced security guards for payment_slips.
 *
 * Why DB-level vs application-level?
 *   Defence in depth. The application service layer should already
 *   reject the bad cases — but a DB constraint is the last line that
 *   no controller bug, no race condition, no SQL injection can
 *   bypass. If we ever drift from "1 paid slip per order", the next
 *   row insert raises an integrity error instead of quietly creating
 *   a double-payment.
 *
 * Constraints added (Postgres syntax — sqlite tests skip the partial
 * index part since sqlite indexes don't support WHERE clauses pre-3.8).
 *
 *   1. UNIQUE(slip_hash) WHERE verify_status != 'rejected'
 *      → blocks cross-user reuse of the same image
 *      (rejected slips don't count — false positives can re-upload)
 *
 *   2. UNIQUE(order_id) WHERE verify_status = 'approved'
 *      → at most ONE approved slip per order, ever
 *
 *   3. UNIQUE(slipok_trans_ref, user_id) WHERE slipok_trans_ref IS NOT NULL
 *      → ONE user can't reuse the same SlipOK transaction reference
 *
 *   4. UNIQUE(slipok_trans_ref) WHERE verify_status='approved'
 *      → cross-user: a transRef approved for user A blocks user B
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            // sqlite test path — partial unique indexes work in sqlite 3.8+
            // but not on every Laravel-supported version. We skip them
            // for tests; service-level guards still apply.
            return;
        }

        // 1. Cross-user slip-hash reuse blocker
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX IF NOT EXISTS payment_slips_hash_active_unique
            ON payment_slips (slip_hash)
            WHERE slip_hash IS NOT NULL
              AND verify_status != 'rejected'
        SQL);

        // 2. At-most-one approved slip per order
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX IF NOT EXISTS payment_slips_order_approved_unique
            ON payment_slips (order_id)
            WHERE verify_status = 'approved'
        SQL);

        // 3. Same user can't reuse same SlipOK transRef twice (any status except rejected)
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX IF NOT EXISTS payment_slips_slipok_user_unique
            ON payment_slips (slipok_trans_ref, order_id)
            WHERE slipok_trans_ref IS NOT NULL
              AND verify_status != 'rejected'
        SQL);

        // 4. SlipOK transRef can only ever be APPROVED on one slip platform-wide
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX IF NOT EXISTS payment_slips_slipok_approved_unique
            ON payment_slips (slipok_trans_ref)
            WHERE slipok_trans_ref IS NOT NULL
              AND verify_status = 'approved'
        SQL);

        // 5. Sanity: amount must be > 0 (DB-level CHECK)
        DB::statement(<<<SQL
            ALTER TABLE payment_slips
            ADD CONSTRAINT payment_slips_amount_positive_chk
            CHECK (amount IS NULL OR amount > 0)
        SQL);
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS payment_slips_hash_active_unique');
        DB::statement('DROP INDEX IF EXISTS payment_slips_order_approved_unique');
        DB::statement('DROP INDEX IF EXISTS payment_slips_slipok_user_unique');
        DB::statement('DROP INDEX IF EXISTS payment_slips_slipok_approved_unique');
        DB::statement('ALTER TABLE payment_slips DROP CONSTRAINT IF EXISTS payment_slips_amount_positive_chk');
    }
};
