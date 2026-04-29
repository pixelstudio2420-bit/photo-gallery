<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-page SEO override store + revision history.
 *
 * Why a DB-backed override layer (not just controller code)
 * ---------------------------------------------------------
 * Up to this migration, the only way to change the title/description
 * of a page was to edit the controller and redeploy. The new model:
 *
 *   1. Each entry in `seo_pages` is keyed by (route_name, locale).
 *   2. SeoService::render() consults this table BEFORE falling back to
 *      controller-set values, so an admin can override any page from
 *      /admin/seo without a deploy.
 *   3. Every save copies the prior row into `seo_page_revisions`, giving
 *      us a full history + one-click rollback.
 *
 * Why not key by URL path
 * -----------------------
 * URL paths can change (rewrites, locale prefix, query strings). Route
 * NAMES are stable across renames. Programmatic SEO landings register
 * named routes like seo.landing.niche / seo.landing.province, so the
 * 78 generated URLs map to just 2 rows here (with `route_params` JSON
 * narrowing the override to a specific niche/province combo when needed).
 *
 * Multi-language
 * --------------
 * `locale` column carries 'th' / 'en' / etc. The (route_name, locale,
 * route_params) triple is unique. To localize, just write a second row
 * with the same route_name and a different locale.
 *
 * Cost / cache
 * ------------
 * Hot path is one cache fetch per request: the entire active table is
 * loaded once per ~5 minutes into a single array keyed by route_name.
 * Cache::flush() on save (handled by the observer) makes overrides
 * appear immediately. Even at 10K rows the cache payload is < 5 MB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seo_pages')) {
            Schema::create('seo_pages', function (Blueprint $table) {
                $table->id();

                // ── Identity ─────────────────────────────────────────────
                // route_name is the primary lookup key. Use 'home' for /,
                // 'events.show' for the event detail page, etc.
                $table->string('route_name', 200)->index();
                $table->string('locale', 8)->default('th')->index();

                // route_params lets us scope an override to ONE specific
                // record on a parameterised route. Empty {} = "all matches
                // of this route_name". Example: route_name="events.show",
                // route_params={"slug":"marathon-2026"} targets exactly
                // that event without globbing.
                $table->json('route_params')->nullable();

                // Human-friendly path for display in the dashboard. Not
                // used for matching — that's route_name + route_params.
                $table->string('path_preview', 500)->nullable();

                // ── Meta tags ────────────────────────────────────────────
                $table->string('title', 200)->nullable();
                $table->text('description')->nullable();
                $table->text('keywords')->nullable();
                $table->string('canonical_url', 500)->nullable();
                $table->string('meta_robots', 100)->nullable();

                // ── Open Graph / Twitter Card ───────────────────────────
                $table->string('og_title', 200)->nullable();
                $table->text('og_description')->nullable();
                $table->string('og_image', 500)->nullable();
                $table->string('og_type', 40)->nullable();

                // ── Structured data (JSON-LD blocks) ─────────────────────
                // Stored as an array of schema objects. SeoService merges
                // these on top of the schemas the controller registered.
                $table->json('structured_data')->nullable();

                // ── Asset alt text overrides ─────────────────────────────
                // {"hero": "ช่างภาพงานวิ่งกรุงเทพ", "cover": "..." }
                // Views read via SeoOverride::altText('hero', $default).
                $table->json('alt_text_map')->nullable();

                // ── Lifecycle / governance ───────────────────────────────
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_locked')->default(false);   // protect critical pages
                $table->timestamp('last_validated_at')->nullable();
                $table->json('validation_warnings')->nullable();
                $table->unsignedInteger('version')->default(1);

                $table->unsignedInteger('created_by')->nullable();   // auth_admins.id
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();

                // (route_name, locale, route_params_hash) — uniqueness via
                // a generated md5 column to keep the index small (json
                // unique indexes are driver-specific).
                $table->string('match_key', 64)->index();
                $table->unique(['route_name', 'locale', 'match_key'], 'seo_pages_unique_target');
            });
        }

        if (!Schema::hasTable('seo_page_revisions')) {
            Schema::create('seo_page_revisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seo_page_id')->index();
                $table->unsignedInteger('version');
                $table->json('snapshot');              // full row snapshot
                $table->string('change_reason', 200)->nullable();
                $table->unsignedInteger('changed_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('seo_page_id')
                    ->references('id')->on('seo_pages')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_page_revisions');
        Schema::dropIfExists('seo_pages');
    }
};
