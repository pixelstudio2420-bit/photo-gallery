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
 * Strictly idempotent — only INSERTs keys that don't already exist. No key
 * is ever force-updated. If an admin explicitly disabled the engine (GDPR
 * hold, vacation, billing investigation), re-running this migration (after
 * a rollback or migrate:fresh) will NOT silently re-enable it.
 *
 * Why no force-overwrite of `event_auto_delete_enabled`
 * ─────────────────────────────────────────────────────
 * Earlier iteration force-updated this key to '1' on every migration run,
 * which is a real GDPR/data-loss footgun: an admin who paused auto-delete
 * during a regulatory hold could see it silently flip back on after the
 * next routine `migrate`. The "project owner wanted it ON" intent is
 * satisfied by the initial insert on fresh install. Subsequent re-enabling
 * is an ADMIN action via /admin/settings/retention, not a migration side-effect.
 *
 * Tracked-keys marker — precise rollback
 * ───────────────────────────────────────
 * up() records which keys it actually INSERTED (not the ones it found
 * pre-existing) into the marker `__retention_seed_migration_inserted__`.
 * down() reads the marker and deletes only those, so rollback never wipes
 * settings that pre-dated this migration (e.g. legacy retention_days_*
 * keys that admins may have tuned for months).
 */
return new class extends Migration {
    /** Marker key used to remember which keys this migration inserted. */
    private const INSERTED_MARKER_KEY = '__retention_seed_migration_inserted__';

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

        // Track keys actually inserted by THIS migration run so down() can
        // roll back precisely without touching legacy values.
        $insertedKeys = [];

        foreach ($defaults as $key => $value) {
            $exists = DB::table('app_settings')->where('key', $key)->exists();
            if ($exists) {
                continue; // Preserve any pre-existing value; never force.
            }
            DB::table('app_settings')->insert([
                'key'        => $key,
                'value'      => $value,
                'updated_at' => $now,
            ]);
            $insertedKeys[] = $key;
        }

        // Persist marker so down() can target precisely what up() inserted.
        // If marker already exists (e.g. defensive re-run), merge lists.
        if (!empty($insertedKeys)) {
            $existing = DB::table('app_settings')->where('key', self::INSERTED_MARKER_KEY)->value('value');
            $prior = $existing ? (array) json_decode((string) $existing, true) : [];
            $merged = array_values(array_unique(array_merge($prior, $insertedKeys)));
            $payload = json_encode($merged, JSON_UNESCAPED_UNICODE);

            DB::table('app_settings')->updateOrInsert(
                ['key' => self::INSERTED_MARKER_KEY],
                ['value' => $payload, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        // Read the marker — only delete keys this migration actually inserted.
        // Pre-existing values (from earlier migrations or admin overrides)
        // are preserved.
        $marker = DB::table('app_settings')->where('key', self::INSERTED_MARKER_KEY)->value('value');
        $insertedKeys = $marker ? (array) json_decode((string) $marker, true) : [];

        if (!empty($insertedKeys)) {
            DB::table('app_settings')->whereIn('key', $insertedKeys)->delete();
        }

        // Drop the marker itself so a subsequent up()→down() cycle is clean.
        DB::table('app_settings')->where('key', self::INSERTED_MARKER_KEY)->delete();
    }
};
