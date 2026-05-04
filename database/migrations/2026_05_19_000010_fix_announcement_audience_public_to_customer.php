<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill: rows in `announcements` with `audience='public'` are invisible
 * to every feed because the Announcement model defines audience constants
 * as ('photographer','customer','all') and scopeVisibleTo() filters
 * `audience IN (target, 'all')`. The 'public' value matches none of
 * those, so the rows render in admin tooling but never on
 * `/announcements` (customer feed) or `/photographer/announcements`.
 *
 * Source of the bad data: `GeoEventBroadcastService::publishGeoAnnouncement()`
 * which inserted directly via `DB::table` without going through the
 * Announcement model (so the fillable + cast safeguards didn't catch
 * the typo). Fixed in the same commit by using
 * `Announcement::AUDIENCE_CUSTOMER` constant.
 *
 * This migration rewrites every existing 'public' row to 'customer' since
 * the geo-broadcast feature targets end-customers (not photographers).
 *
 * Idempotent: only updates rows whose current value is exactly 'public'.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        $affected = DB::table('announcements')
            ->where('audience', 'public')
            ->update([
                'audience'   => 'customer',
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            \Illuminate\Support\Facades\Log::info('Migration 2026_05_19_000010: fixed announcement audience', [
                'rows_updated' => $affected,
            ]);
        }
    }

    public function down(): void
    {
        // No-op — we cannot reliably distinguish customer rows that were
        // originally 'public' from genuinely-customer rows. The forward
        // migration is data-cleansing; rolling it back would re-break
        // the feed visibility for those rows.
    }
};
