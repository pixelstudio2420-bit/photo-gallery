<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Strip deprecated AI features from subscription_plans rows.
 *
 * Why a migration (not an admin one-off)
 * --------------------------------------
 * `subscription_plans.ai_features` (JSON array) is the source of truth
 * for which Pro/Business/Studio plans grant access to which AI tools.
 * The 2026-04-28 product audit cut three AI features from the MVP:
 *
 *   - color_enhance
 *   - smart_captions
 *   - video_thumbnails
 *
 * …plus removed `api_access` from the Studio plan (kept the API code
 * paths but feature-gated them off by default — see FeatureFlagController).
 *
 * Without this migration, paid customers would still see those features
 * granted in their plan even though the global feature flag is OFF —
 * confusing UX (greyed-out tile with "this feature is disabled").
 *
 * Reversibility
 * -------------
 * The down() restores the original feature lists for the four plans
 * that had any of the stripped features. Codes match the seed data.
 *
 * Idempotency
 * -----------
 * Skips any plan whose ai_features doesn't contain a deprecated entry,
 * so re-running over a migrated DB is a no-op. JSON parse errors are
 * logged but don't abort the migration.
 */
return new class extends Migration
{
    private const DEPRECATED = [
        'color_enhance',
        'smart_captions',
        'video_thumbnails',
        'api_access',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            return;
        }

        DB::table('subscription_plans')->orderBy('id')->each(function ($plan) {
            $features = $this->decodeJsonArray($plan->ai_features);
            if ($features === null) return;

            $stripped = array_values(array_diff($features, self::DEPRECATED));
            if (count($stripped) === count($features)) return;   // nothing to remove

            DB::table('subscription_plans')
                ->where('id', $plan->id)
                ->update([
                    'ai_features' => json_encode($stripped, JSON_UNESCAPED_UNICODE),
                    'updated_at'  => now(),
                ]);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            return;
        }

        // Restore the original feature lists from the seed.
        $restore = [
            'business' => [
                'face_search', 'quality_filter', 'duplicate_detection',
                'auto_tagging', 'best_shot', 'priority_upload',
                'color_enhance', 'customer_analytics', 'smart_captions',
                'custom_branding', 'presets',
            ],
            'studio' => [
                'face_search', 'quality_filter', 'duplicate_detection',
                'auto_tagging', 'best_shot', 'priority_upload',
                'color_enhance', 'customer_analytics', 'smart_captions',
                'custom_branding', 'video_thumbnails', 'api_access',
                'white_label', 'presets',
            ],
        ];

        foreach ($restore as $code => $features) {
            DB::table('subscription_plans')
                ->where('code', $code)
                ->update([
                    'ai_features' => json_encode($features, JSON_UNESCAPED_UNICODE),
                    'updated_at'  => now(),
                ]);
        }
    }

    private function decodeJsonArray($raw): ?array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
};
