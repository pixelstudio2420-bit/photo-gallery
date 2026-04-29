<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed default face-search cost-control AppSettings.
 *
 * Defaults are conservative — if an admin never visits the new settings page
 * these caps still prevent a runaway bill. Worst-case under the defaults:
 *
 *   per-event:      500 searches/day × ~2 calls each        ≈ $1/day/event
 *   per-user:        50 searches/day × ~2 calls each        ≈ $0.10/day/user
 *   per-IP:         100 searches/day × ~2 calls each        ≈ $0.20/day/IP
 *   global:      10,000 searches/month × ~2 calls           ≈ $20/month
 *   fallback:    max 20 photos (else path is refused)        — caps worst-case
 *                                                             compareFaces loop
 *
 * Idempotent: skips keys that already exist so re-running doesn't stomp an
 * admin's manual tuning.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        $defaults = [
            // Master kill-switch — flip to '0' to disable face-search globally
            // without touching per-event `face_search_enabled` flags.
            'face_search_enabled_globally'    => '1',

            // Daily quotas (0 = disabled)
            'face_search_daily_cap_per_event' => '500',
            'face_search_daily_cap_per_user'  => '50',
            'face_search_daily_cap_per_ip'    => '100',

            // Global monthly ceiling — catches the sum-of-all-events attack
            // that per-event caps cannot. 0 = disabled.
            'face_search_monthly_global_cap'  => '10000',

            // Fallback path is 1 API call per event photo. If the event has
            // more than this many photos AND they are not yet indexed, refuse
            // the search with a "please wait for indexing" error rather than
            // spending 200 × $0.001 on a single request.
            'face_search_fallback_max_photos' => '20',

            // Cache repeated searches (same selfie + same event) for N minutes.
            // 0 = disabled. Typical user hits "search" multiple times with
            // the same selfie while evaluating results — caching is free
            // savings.
            'face_search_cache_ttl_minutes'   => '10',
        ];

        $now = now();
        foreach ($defaults as $key => $value) {
            $exists = DB::table('app_settings')->where('key', $key)->exists();
            if ($exists) {
                continue;
            }
            DB::table('app_settings')->insert([
                'key'   => $key,
                'value' => $value,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        DB::table('app_settings')->whereIn('key', [
            'face_search_enabled_globally',
            'face_search_daily_cap_per_event',
            'face_search_daily_cap_per_user',
            'face_search_daily_cap_per_ip',
            'face_search_monthly_global_cap',
            'face_search_fallback_max_photos',
            'face_search_cache_ttl_minutes',
        ])->delete();
    }
};
