<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enhance slip verification accuracy.
 *
 * Adds:
 *  - fraud_flags         JSON : list of fraud indicators that triggered (was
 *                                computed but never persisted — the admin view
 *                                already tries to render it).
 *  - verify_breakdown    JSON : per-check pass/fail + points contributed, so
 *                                admins can see *why* a score came out the way
 *                                it did and tune thresholds intelligently.
 *  - slipok_trans_ref    VARCHAR(100) INDEX : SlipOK's unique per-transaction
 *                                identifier. Strongest fraud signal we have —
 *                                same transRef appearing twice = slip reuse.
 *  - receiver_account    VARCHAR(100) : destination account (masked) returned
 *                                by SlipOK — used to verify the slip was
 *                                actually sent to our configured bank.
 *  - receiver_name       VARCHAR(200) : destination account holder.
 *  - sender_name         VARCHAR(200) : sender name from SlipOK (useful for
 *                                cross-reference + manual review).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payment_slips')) {
            return;
        }

        Schema::table('payment_slips', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_slips', 'fraud_flags')) {
                $table->json('fraud_flags')->nullable()->after('verify_score');
            }
            if (!Schema::hasColumn('payment_slips', 'verify_breakdown')) {
                $table->json('verify_breakdown')->nullable()->after('fraud_flags');
            }
            if (!Schema::hasColumn('payment_slips', 'slipok_trans_ref')) {
                $table->string('slipok_trans_ref', 100)->nullable()->after('verify_breakdown');
            }
            if (!Schema::hasColumn('payment_slips', 'receiver_account')) {
                $table->string('receiver_account', 100)->nullable()->after('slipok_trans_ref');
            }
            if (!Schema::hasColumn('payment_slips', 'receiver_name')) {
                $table->string('receiver_name', 200)->nullable()->after('receiver_account');
            }
            if (!Schema::hasColumn('payment_slips', 'sender_name')) {
                $table->string('sender_name', 200)->nullable()->after('receiver_name');
            }
        });

        // Index slipok_trans_ref for fast duplicate lookup. Separate step so
        // the add-column + add-index pair is idempotent on re-run.
        if (Schema::hasColumn('payment_slips', 'slipok_trans_ref')
            && !$this->indexExists('payment_slips', 'payment_slips_slipok_trans_ref_idx')) {
            try {
                \DB::statement("CREATE INDEX payment_slips_slipok_trans_ref_idx ON payment_slips (slipok_trans_ref)");
            } catch (\Throwable $e) {
                // ignore — duplicate-index is still safe due to try/catch
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_slips')) {
            return;
        }

        Schema::table('payment_slips', function (Blueprint $table) {
            try {
                $table->dropIndex('payment_slips_slipok_trans_ref_idx');
            } catch (\Throwable $e) {
                // ignore
            }

            foreach (['sender_name', 'receiver_name', 'receiver_account', 'slipok_trans_ref', 'verify_breakdown', 'fraud_flags'] as $col) {
                if (Schema::hasColumn('payment_slips', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Check if an index exists on a table — driver-agnostic.
     * Works on MySQL, MariaDB, and PostgreSQL.
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $driver = \DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                $result = \DB::select(
                    "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$table, $index]
                );
                return !empty($result);
            }
            // MySQL / MariaDB
            $result = \DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
            return !empty($result);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
