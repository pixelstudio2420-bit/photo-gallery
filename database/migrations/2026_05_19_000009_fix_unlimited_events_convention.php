<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bug fix: migration 2026_05_19_000007 wrote `max_concurrent_events = -1`
 * to Pro and Studio plans intending it as a sentinel for "unlimited",
 * but the rest of the codebase uses `NULL` for unlimited:
 *
 *   - SubscriptionService::maxConcurrentEvents() returns the raw value;
 *     callers check `$cap === null` for unlimited.
 *   - SubscriptionService::canCreateMoreEvents() does
 *     `activeEventCount() < $cap`, so cap = -1 makes the check evaluate
 *     `0 < -1` = false → photographer is BLOCKED from creating events.
 *   - EventController::enforceConcurrentEventCap() shows a Thai error
 *     message containing "-1" if it's reached → ugly, broken UX.
 *   - Admin\SubscriptionController validates `nullable|integer|min:0`,
 *     so admin can't even fix this through the UI.
 *
 * Fix: rewrite -1 → NULL on Pro and Studio so all "unlimited" rows
 * follow the established convention. Free stays at 1 (correct already),
 * Starter stays at 3, Lite stays at 3.
 *
 * Idempotent: only updates rows whose current value is exactly -1.
 *
 * The defensive `maxConcurrentEvents()` patch in SubscriptionService.php
 * (same commit) handles either NULL or any negative value as unlimited
 * for future-proofing.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            return;
        }

        DB::table('subscription_plans')
            ->where('max_concurrent_events', -1)
            ->update([
                'max_concurrent_events' => null,
                'updated_at'            => now(),
            ]);

        try {
            \Illuminate\Support\Facades\Cache::forget('public.pricing.plans');
            \Illuminate\Support\Facades\Cache::forget('public.for-photographers.plans');
        } catch (\Throwable) {}
    }

    public function down(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            return;
        }

        // Restore -1 sentinel only on the plans we changed up()
        // (Pro and Studio) so down() doesn't accidentally rewrite NULL
        // rows that were always NULL (Business, etc.).
        DB::table('subscription_plans')
            ->whereIn('code', ['pro', 'studio'])
            ->whereNull('max_concurrent_events')
            ->update([
                'max_concurrent_events' => -1,
                'updated_at'            => now(),
            ]);
    }
};
