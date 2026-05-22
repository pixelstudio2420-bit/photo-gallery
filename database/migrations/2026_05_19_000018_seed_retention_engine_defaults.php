<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enable the event retention engine with sane per-tier defaults.
 *
 * Project owner asked the platform to actively control R2 storage
 * cost by auto-purging old event originals — especially for Free
 * photographers who don't pay subscription. Previously the retention
 * engine existed (PurgeExpiredEventsCommand) but was disabled by
 * default (`event_auto_delete_enabled=0`) and had a single global
 * mode setting. This migration:
 *
 *   1. Flips `event_auto_delete_enabled` to 1 — the cron will now
 *      actually run.
 *   2. Sets per-tier retention DAYS so Pro/Studio (paying users)
 *      keep originals longer than Free.
 *   3. Adds NEW per-tier MODE settings — Free tier defaults to
 *      `full` (hard delete) while Pro stays `portfolio` (keep
 *      previews + cover for portfolio display). This is the key
 *      cost-control lever: a Free photographer who stops engaging
 *      stops costing R2 entirely after 60 days.
 *
 * Settings keys created
 * ─────────────────────
 *   event_auto_delete_enabled       → 1   (cron will run)
 *   event_retention_mode            → portfolio (fallback global)
 *   event_default_retention_days    → 90  (fallback global)
 *
 *   retention_days_creator          → 60   (Free)
 *   retention_days_seller           → 180  (Seller, ex-paid)
 *   retention_days_pro              → 365  (Pro / Studio)
 *
 *   retention_mode_creator          → full       (Free = aggressive)
 *   retention_mode_seller           → portfolio  (Seller = preserve)
 *   retention_mode_pro              → portfolio  (Pro = preserve)
 *
 *   r2_cost_per_gb_month_usd        → 0.015    (Cloudflare R2 rate)
 *   r2_derivative_multiplier        → 0.04     (% of storage that
 *                                                preview+thumb take
 *                                                of total — used in
 *                                                the post-purge cost
 *                                                projection)
 *
 * Safety
 * ──────
 * Idempotent — only writes keys that don't already exist. Admins
 * who explicitly turned auto-delete off will see it re-enabled here
 * (intentional — project owner asked for this).
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $defaults = [
            // Master switch + global fallback
            'event_auto_delete_enabled'    => '1',
            'event_retention_mode'         => 'portfolio',
            'event_default_retention_days' => '90',

            // Per-tier days
            'retention_days_creator'       => '60',
            'retention_days_seller'        => '180',
            'retention_days_pro'           => '365',

            // Per-tier modes (NEW) — Free is aggressive (full delete),
            // paying tiers keep portfolio preview.
            'retention_mode_creator'       => 'full',
            'retention_mode_seller'        => 'portfolio',
            'retention_mode_pro'           => 'portfolio',

            // R2 cost estimator — used by the admin dashboard widget
            // to surface "this photographer costs $X/month".
            'r2_cost_per_gb_month_usd'     => '0.015',
            'r2_derivative_multiplier'     => '0.04',
        ];

        foreach ($defaults as $key => $value) {
            // Only insert if not already present — preserves any
            // explicit admin override the project may have set
            // through the UI between migration runs.
            $exists = DB::table('app_settings')->where('key', $key)->exists();
            if ($exists) {
                // The master switch is the one exception — project
                // owner explicitly asked for it to be ON, so force
                // it even if a previous admin disabled it.
                if ($key === 'event_auto_delete_enabled') {
                    DB::table('app_settings')
                        ->where('key', $key)
                        ->update(['value' => $value, 'updated_at' => $now]);
                }
                continue;
            }
            DB::table('app_settings')->insert([
                'key'        => $key,
                'value'      => $value,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Conservative rollback — disable the master switch but
        // leave the per-tier values in place so admins don't lose
        // their tuning if they re-run later.
        DB::table('app_settings')
            ->where('key', 'event_auto_delete_enabled')
            ->update(['value' => '0', 'updated_at' => now()]);

        // Drop only the NEW per-tier mode keys this migration introduced
        // (the legacy `retention_days_*` keys predate us).
        DB::table('app_settings')->whereIn('key', [
            'retention_mode_creator',
            'retention_mode_seller',
            'retention_mode_pro',
            'r2_cost_per_gb_month_usd',
            'r2_derivative_multiplier',
        ])->delete();
    }
};
