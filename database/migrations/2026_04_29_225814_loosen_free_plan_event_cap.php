<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Free plan was originally seeded with max_concurrent_events = 0,
 * which forced every photographer on Free plan into "draft only"
 * mode forever. The original product intent was "Free = portfolio
 * only" but in practice it surprised first-time users:
 *
 *   1. Photographer signs up → lands on Free plan automatically.
 *   2. Creates an event, picks "เผยแพร่" → server silently downgrades
 *      to "draft" with a flash they can't see (separate fix).
 *   3. They think the publish button is broken.
 *
 * This migration switches Free plan to UNLIMITED concurrent events
 * (NULL in subscription_plans.max_concurrent_events — the
 * SubscriptionService treats null as "no cap"). The actual gate
 * becomes storage_gb instead — Free users can publish as many events
 * as they want, but they can only upload photos until their storage
 * quota fills, and deletes free up the space (see
 * EventController::destroy() + StorageQuotaService::recalculate()).
 *
 * The seeder migration (2026_04_24_100004_seed_default_subscription_plans)
 * is a one-shot — its `up()` runs once and never again, so we can't
 * just edit the seed. This is the targeted patch.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_plans')
            ->where('code', 'free')
            ->update(['max_concurrent_events' => null]);
    }

    public function down(): void
    {
        // Revert to the original portfolio-only cap. Anyone running
        // `migrate:rollback` on this migration knew what they were
        // doing (probably reverting to a clean seed for testing).
        DB::table('subscription_plans')
            ->where('code', 'free')
            ->update(['max_concurrent_events' => 0]);
    }
};
