<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache the Omise recipient_id on the photographer row.
 *
 * Why cache: Omise's /recipients endpoint does NOT support idempotency keys,
 * so calling it once per payout would leak duplicate recipient records into
 * the Omise dashboard. By caching the id here, the provider can POST
 * /recipients exactly once per photographer (first time they're paid out)
 * and reuse the id thereafter.
 *
 * Nullable — old rows remain untouched, and the provider lazily populates
 * the column on first payout. If admin ever needs to force a re-create
 * (e.g. the recipient was deactivated in Omise), clearing this column
 * triggers a new /recipients call on the next transfer.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $table) {
            $table->string('omise_recipient_id', 100)
                ->nullable()
                ->after('promptpay_verified_at')
                ->comment('Cached Omise recipient_id — populated lazily on first payout');
        });
    }

    public function down(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $table) {
            $table->dropColumn('omise_recipient_id');
        });
    }
};
