<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add `line_notify` to ai_features for every PAID subscription plan.
 *
 * Background: PhotoDeliveryService now plan-gates LINE delivery via
 * PlanGate::canUseLine(), which checks whether the photographer's
 * active plan's ai_features JSON contains `'line_notify'`. Until this
 * migration runs, every plan (including paid Pro / Business / Studio)
 * was missing the key — so the new gate would block LINE delivery for
 * ALL plans, not just free.
 *
 * This script:
 *   • Adds `line_notify` to ai_features for any plan with price_thb > 0
 *   • Leaves the free plan untouched (LINE stays gated for them)
 *   • Idempotent: re-running adds nothing if the key is already present
 *   • Safe: never removes existing features
 *
 * If you ever need to re-grant or revoke the LINE feature on a specific
 * tier, edit the row in /admin/subscriptions/plans/{id} (the `ai_features`
 * field). This migration only sets the initial state.
 */
return new class extends Migration {
    public function up(): void
    {
        $rows = DB::table('subscription_plans')
            ->where('price_thb', '>', 0)
            ->get(['id', 'ai_features']);

        foreach ($rows as $row) {
            $features = json_decode((string) $row->ai_features, true);
            if (!is_array($features)) $features = [];

            if (!in_array('line_notify', $features, true)) {
                $features[] = 'line_notify';
                DB::table('subscription_plans')
                    ->where('id', $row->id)
                    ->update([
                        'ai_features' => json_encode(array_values(array_unique($features))),
                        'updated_at'  => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Strip `line_notify` from every plan. Down is conservative — we
        // remove the feature flag from ALL plans (paid AND free) so the
        // post-rollback state matches the pre-up state precisely (free
        // never had it, paid had whatever was already there minus the
        // newly-added line_notify).
        $rows = DB::table('subscription_plans')->get(['id', 'ai_features']);
        foreach ($rows as $row) {
            $features = json_decode((string) $row->ai_features, true);
            if (!is_array($features)) continue;
            $filtered = array_values(array_filter($features, fn ($f) => $f !== 'line_notify'));
            if (count($filtered) !== count($features)) {
                DB::table('subscription_plans')
                    ->where('id', $row->id)
                    ->update([
                        'ai_features' => json_encode($filtered),
                        'updated_at'  => now(),
                    ]);
            }
        }
    }
};
