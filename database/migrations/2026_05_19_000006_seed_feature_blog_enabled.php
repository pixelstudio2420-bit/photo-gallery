<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed `feature_blog_enabled = '1'` so existing installations keep the
 * blog visible after the feature-toggle system rolls out. Without this,
 * `Features::blogEnabled()` falls back to its default (true) anyway —
 * but having the row in app_settings makes the toggle visible in the
 * admin UI immediately rather than appearing only after the first
 * save.
 *
 * Idempotent: insertOrIgnore on the primary key.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        DB::table('app_settings')->insertOrIgnore([
            'key'   => 'feature_blog_enabled',
            'value' => '1',
        ]);

        // Flush the AppSetting in-memory + cache store so the new row
        // is visible to subsequent requests.
        try {
            \App\Models\AppSetting::flushCache();
        } catch (\Throwable) {
            // Cache may not be ready during early migrate runs; harmless
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }
        DB::table('app_settings')->where('key', 'feature_blog_enabled')->delete();
    }
};
