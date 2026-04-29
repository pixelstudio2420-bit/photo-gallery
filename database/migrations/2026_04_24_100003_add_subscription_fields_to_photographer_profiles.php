<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised pointers to the photographer's current subscription so that
 * the hot-path quota/AI-gating middleware doesn't have to join on every
 * request.
 *
 * - `current_subscription_id` — FK to photographer_subscriptions; null = free tier.
 * - `subscription_plan_code`  — cached plan code for quick middleware check.
 * - `subscription_status`     — cached status; mirror of subscription row.
 * - `subscription_renews_at`  — surfaces upcoming renewal date in dashboard.
 *
 * SubscriptionService is responsible for keeping these in sync; never
 * write them from anywhere else or they'll drift.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('photographer_profiles')) {
            return;
        }

        Schema::table('photographer_profiles', function (Blueprint $t) {
            if (!Schema::hasColumn('photographer_profiles', 'current_subscription_id')) {
                $t->unsignedBigInteger('current_subscription_id')->nullable()->after('billing_mode');
                $t->index('current_subscription_id');
            }

            if (!Schema::hasColumn('photographer_profiles', 'subscription_plan_code')) {
                $t->string('subscription_plan_code', 50)->nullable()->after('current_subscription_id');
                $t->index('subscription_plan_code');
            }

            if (!Schema::hasColumn('photographer_profiles', 'subscription_status')) {
                $t->string('subscription_status', 16)->nullable()->after('subscription_plan_code');
                $t->index('subscription_status');
            }

            if (!Schema::hasColumn('photographer_profiles', 'subscription_renews_at')) {
                $t->timestamp('subscription_renews_at')->nullable()->after('subscription_status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('photographer_profiles')) {
            return;
        }

        Schema::table('photographer_profiles', function (Blueprint $t) {
            foreach (['current_subscription_id', 'subscription_plan_code', 'subscription_status', 'subscription_renews_at'] as $col) {
                if (Schema::hasColumn('photographer_profiles', $col)) {
                    // Drop index first where applicable — Laravel's schema builder
                    // silently ignores non-existent indexes in most drivers but
                    // we explicitly scope to keep the migration reversible.
                    try { $t->dropIndex(['photographer_profiles_'.$col.'_index']); } catch (\Throwable) {}
                    $t->dropColumn($col);
                }
            }
        });
    }
};
