<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track AI-credit consumption on the photographer profile.
 *
 * SubscriptionPlan.monthly_ai_credits caps how many AI operations
 * (face indexing, quality filter, auto-tag, etc.) the photographer
 * can run per billing period. We denormalise the running counter +
 * the window start onto photographer_profiles so the hot path
 * doesn't need to JOIN; SubscriptionService::syncProfileCache()
 * resets the counter whenever a new period activates.
 *
 * Also tracks active-event count cache for the max_concurrent_events
 * gate — refreshed lazily by SubscriptionService::activeEventCount().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $t) {
            if (!Schema::hasColumn('photographer_profiles', 'ai_credits_used')) {
                $t->unsignedInteger('ai_credits_used')->default(0)
                    ->after('storage_recalculated_at');
            }
            if (!Schema::hasColumn('photographer_profiles', 'ai_credits_period_start')) {
                $t->timestamp('ai_credits_period_start')->nullable()
                    ->after('ai_credits_used');
            }
            if (!Schema::hasColumn('photographer_profiles', 'ai_credits_period_end')) {
                $t->timestamp('ai_credits_period_end')->nullable()
                    ->after('ai_credits_period_start');
            }
        });
    }

    public function down(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $t) {
            foreach (['ai_credits_used', 'ai_credits_period_start', 'ai_credits_period_end'] as $col) {
                if (Schema::hasColumn('photographer_profiles', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
