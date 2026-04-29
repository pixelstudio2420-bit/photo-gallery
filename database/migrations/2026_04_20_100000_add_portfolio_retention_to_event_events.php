<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event Portfolio Retention — keep preview + cover after retention expires.
 *
 * When an event passes its retention window we can now choose between two
 * fates instead of one:
 *
 *   • FULL PURGE  → the old behaviour (row + files all gone). Used when the
 *     admin explicitly wants the event erased.
 *   • PORTFOLIO  → (new default) only the ORIGINAL photo files are deleted.
 *     The cover image, thumbnails and watermarked previews survive so the
 *     photographer's profile page can still show the work as part of their
 *     portfolio. The event row is archived (status = 'archived') and marked
 *     `originals_purged_at = now()` so the UI and download guards can behave
 *     correctly.
 *
 * Adds two knobs per event:
 *   originals_purged_at   — when the originals were wiped (null = intact)
 *   is_portfolio          — opt-in flag the photographer can set to mark
 *                            specific events as "keep forever as portfolio"
 *
 * Adds two global defaults in app_settings:
 *   event_retention_mode           = 'portfolio' | 'full' (default portfolio)
 *   event_portfolio_keep_days      = how long a portfolio-only event sticks
 *                                     around before the row is also deleted
 *                                     (0 = forever). Default 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_events', function (Blueprint $table) {
            if (!Schema::hasColumn('event_events', 'originals_purged_at')) {
                $table->timestamp('originals_purged_at')
                    ->nullable()
                    ->after('auto_delete_warned_at')
                    ->comment('When original photo files were wiped (portfolio mode). NULL = originals intact.');
            }
            if (!Schema::hasColumn('event_events', 'is_portfolio')) {
                $table->boolean('is_portfolio')
                    ->default(false)
                    ->after('originals_purged_at')
                    ->comment('Photographer flag: keep this event in portfolio view even after retention.');
            }
        });

        // Helpful index for portfolio queries on the photographer profile page.
        Schema::table('event_events', function (Blueprint $table) {
            try {
                $table->index(['photographer_id', 'originals_purged_at'], 'idx_event_photog_portfolio');
            } catch (\Throwable) {
                // index may already exist on re-runs
            }
        });

        // Seed global defaults (idempotent)
        $defaults = [
            'event_retention_mode'       => 'portfolio', // portfolio | full
            'event_portfolio_keep_days'  => '0',          // 0 = forever
        ];

        foreach ($defaults as $key => $value) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::table('event_events', function (Blueprint $table) {
            try { $table->dropIndex('idx_event_photog_portfolio'); } catch (\Throwable) {}

            if (Schema::hasColumn('event_events', 'originals_purged_at')) {
                $table->dropColumn('originals_purged_at');
            }
            if (Schema::hasColumn('event_events', 'is_portfolio')) {
                $table->dropColumn('is_portfolio');
            }
        });

        DB::table('app_settings')->whereIn('key', [
            'event_retention_mode',
            'event_portfolio_keep_days',
        ])->delete();
    }
};
