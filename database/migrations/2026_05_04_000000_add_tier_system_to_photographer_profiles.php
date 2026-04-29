<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introduce a three-tier photographer model so OAuth sign-ups can start using
 * the platform immediately instead of being stuck behind a multi-step form.
 *
 *   creator → signed in, can upload/browse, cannot sell
 *   seller  → has a verified PromptPay ID, can sell up to monthly cap
 *   pro     → ID card + contract, no cap, verified badge
 *
 * The column is a plain varchar (not an enum) so we can add tiers later
 * without a migration. The default 'creator' means a freshly created profile
 * is immediately usable; tiers are upgraded by the model's computeTier()
 * based on which fields the photographer has filled in.
 *
 * PromptPay verification columns capture the name returned by the payout
 * provider when we pre-flight a transfer. Storing it lets us show the photographer
 * "นี่คือ คุณ ก. ใช่ไหม?" on save and later reconcile payouts without having
 * to look the name up every time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('photographer_profiles', 'tier')) {
                // varchar(20) — creator | seller | pro. Indexed because
                // admin list + tier-gated middleware both filter on it.
                $table->string('tier', 20)->default('creator')->after('status')->index();
            }

            if (!Schema::hasColumn('photographer_profiles', 'promptpay_verified_name')) {
                $table->string('promptpay_verified_name', 200)->nullable()
                    ->after('promptpay_number');
            }
            if (!Schema::hasColumn('photographer_profiles', 'promptpay_verified_at')) {
                $table->timestamp('promptpay_verified_at')->nullable()
                    ->after('promptpay_verified_name');
            }
        });

        // Backfill tier for any rows that pre-date this migration. Mirrors
        // the live computeTier() logic so existing approved photographers
        // don't all get bumped to 'creator' on deploy.
        try {
            DB::table('photographer_profiles')->get()->each(function ($row) {
                $tier = 'creator';
                if (!empty($row->promptpay_number)) {
                    $tier = 'seller';
                }
                if (!empty($row->id_card_path) && !empty($row->contract_signed_at)) {
                    $tier = 'pro';
                }
                DB::table('photographer_profiles')
                    ->where('id', $row->id)
                    ->update(['tier' => $tier]);
            });
        } catch (\Throwable $e) {
            // Backfill is best-effort — if the table is empty or the column
            // isn't writable yet (weird driver edge), leave the default.
        }
    }

    public function down(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('photographer_profiles', 'tier')) {
                // drop the index first on drivers that complain otherwise (mysql strict mode)
                try { $table->dropIndex(['tier']); } catch (\Throwable $e) {}
                $table->dropColumn('tier');
            }
            if (Schema::hasColumn('photographer_profiles', 'promptpay_verified_name')) {
                $table->dropColumn('promptpay_verified_name');
            }
            if (Schema::hasColumn('photographer_profiles', 'promptpay_verified_at')) {
                $table->dropColumn('promptpay_verified_at');
            }
        });
    }
};
