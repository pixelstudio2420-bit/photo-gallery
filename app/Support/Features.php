<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Feature toggle registry — admin-controlled on/off switches for major
 * subsystems. Backed by `app_settings` so toggles persist across deploys
 * and cache hits don't hit the DB on every page render.
 *
 * Usage:
 *   if (Features::blogEnabled()) { ... }
 *   Features::setBlog(true);
 *   Features::all();   // ['blog' => true, ...]
 *
 * Each toggle defaults to `enabled` so pre-existing installs keep working
 * after the migration runs (default value is seeded via the
 * 2026_05_19_000006 migration).
 *
 * Currently registered feature flags:
 *   blog_enabled — controls public /blog/* routes, navbar links, footer
 *                  link, and sitemap entries. Admin /admin/blog/* routes
 *                  remain accessible so admins can re-enable the system.
 */
class Features
{
    /* ─── Setting keys (must match keys stored in app_settings) ─── */
    public const KEY_BLOG = 'feature_blog_enabled';

    /* ════════════════════════════════════════════════════════════
     * Reads
     * ════════════════════════════════════════════════════════════ */

    /** Public-facing blog (article reading, listing, RSS) is enabled? */
    public static function blogEnabled(): bool
    {
        return self::flag(self::KEY_BLOG, true);
    }

    /** Snapshot of all flags as [name => bool] for views/JSON. */
    public static function all(): array
    {
        return [
            'blog' => self::blogEnabled(),
        ];
    }

    /* ════════════════════════════════════════════════════════════
     * Writes
     * ════════════════════════════════════════════════════════════ */

    public static function setBlog(bool $enabled): void
    {
        AppSetting::set(self::KEY_BLOG, $enabled ? '1' : '0');
    }

    /**
     * Bulk update from form input (key => bool).
     * Unknown keys are ignored so untrusted input can't write arbitrary
     * settings rows.
     */
    public static function bulkSet(array $flags): void
    {
        $allowed = [
            'blog' => self::KEY_BLOG,
        ];
        $rows = [];
        foreach ($flags as $name => $value) {
            if (!isset($allowed[$name])) continue;
            $rows[$allowed[$name]] = $value ? '1' : '0';
        }
        if ($rows) {
            AppSetting::setMany($rows);
        }
    }

    /* ════════════════════════════════════════════════════════════
     * Internal
     * ════════════════════════════════════════════════════════════ */

    /**
     * Read a flag with a default. Treats string '1' / 'true' as enabled,
     * everything else as disabled — matches the convention used elsewhere
     * in the codebase (CachePurgeController, BlogAiController etc.).
     */
    protected static function flag(string $key, bool $default = true): bool
    {
        $value = AppSetting::get($key, $default ? '1' : '0');
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }
}
