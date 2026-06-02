<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support columns for the inactive Free account sweep.
 *
 *   pending_deletion_at      → When the sweep warned this profile; the
 *                               delete pass fires once this lands in the past.
 *   inactive_sweep_exempt    → Per-profile opt-out. Admin can flag VIPs,
 *                               friends-of-the-house, customer-service
 *                               accounts so they're never targeted.
 *
 * Both are nullable, indexed (the cron filters on them), and reversible.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('photographer_profiles', 'pending_deletion_at')) {
                $table->timestamp('pending_deletion_at')->nullable()->index();
            }
            if (!Schema::hasColumn('photographer_profiles', 'inactive_sweep_exempt')) {
                $table->boolean('inactive_sweep_exempt')->default(false)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('photographer_profiles', 'pending_deletion_at')) {
                $table->dropColumn('pending_deletion_at');
            }
            if (Schema::hasColumn('photographer_profiles', 'inactive_sweep_exempt')) {
                $table->dropColumn('inactive_sweep_exempt');
            }
        });
    }
};
