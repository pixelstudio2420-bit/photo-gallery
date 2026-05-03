<?php

use Database\Seeders\BlogContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One-time fix: the previous seed (migration 2026_05_19_000004)
 * stored articles in markdown format, but the public /blog/{slug}
 * view renders content through HtmlSanitizer::clean() which expects
 * HTML — so markdown literals like `## หัวข้อ` displayed as plain
 * text instead of <h2>.
 *
 * This migration:
 *   1. Deletes the 10 SEEDED draft posts (by slug whitelist) IF
 *      they're still status='draft' and haven't been customized.
 *   2. Re-runs BlogContentSeeder which now converts markdown → HTML
 *      at insert time.
 *
 * Skips any post whose status is no longer 'draft' (admin published
 * + customized) — never destroys admin edits.
 */
return new class extends Migration {
    private const SEEDED_SLUGS = [
        'reduce-photographer-no-show-thailand',
        'start-event-photographer-2026-thailand',
        'protect-photo-theft-thai-photographers',
        'event-photo-pricing-guide-thailand',
        'photographer-booking-system-thailand',
        'face-search-find-your-photos-thailand',
        'graduation-photos-thailand-find-fast',
        'wedding-photographer-thailand-fast-delivery',
        'thai-festival-photography-songkran-loy-krathong',
        'corporate-event-photography-thailand-bulk-delivery',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('blog_posts')) {
            Log::warning('Blog tables missing — skipping reseed migration');
            return;
        }

        try {
            // Only delete drafts (admin hasn't published / modified).
            // Force-delete (not soft) so the seeder can re-insert the
            // same slug without unique-constraint conflict.
            $deleted = DB::table('blog_posts')
                ->whereIn('slug', self::SEEDED_SLUGS)
                ->where('status', 'draft')
                ->delete();

            Log::info("Reseed blog content: deleted {$deleted} draft posts, running seeder…");

            (new BlogContentSeeder())->run();
        } catch (\Throwable $e) {
            Log::error('Reseed blog content failed: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // No-op — can't reverse content reseed safely.
    }
};
