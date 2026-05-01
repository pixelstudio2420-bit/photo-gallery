<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programmatic-SEO infrastructure.
 *
 * Two tables:
 *   1. seo_page_templates  — patterns that drive auto-generation per page
 *      type (e.g. location pages, category pages, photographer pages).
 *      Each template has title/meta/body patterns with {placeholders}
 *      that the PSeoService fills from real data at render/generate time.
 *
 *   2. seo_landing_pages   — concrete generated pages, one row per URL.
 *      Holds the resolved title/meta/body so:
 *        • Page renders are fast (no per-request generation)
 *        • Admins can override individual pages without editing templates
 *        • Sitemap.xml can be built with one indexed query
 *
 * The split lets admins toggle/edit ONE template and bulk-regenerate
 * 100s of pages, without losing per-page customization (overrides
 * persist via the `is_locked` flag on individual landing-page rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_page_templates', function (Blueprint $table) {
            $table->id();
            // Page-type discriminator. Drives WHICH data the generator pulls
            // and the URL pattern. Constrained set so the generator code can
            // match confidently without string typos.
            //   location      — /events-in-{location-slug}
            //   category      — /{category-slug}-photographers
            //   combo         — /{category-slug}-photographers-in-{location-slug}
            //   photographer  — enhanced /photographers/{slug}
            //   event_archive — /events-archive paginated by month
            //   custom        — admin-created one-off
            $table->string('type', 30);
            $table->string('name', 120);

            // Toggle for the auto-generator. Existing pages keep rendering
            // even when off; only NEW page generation is paused.
            $table->boolean('is_auto_enabled')->default(true);

            // Patterns with {variable} substitution by PSeoService.
            $table->string('title_pattern', 500);
            $table->string('meta_description_pattern', 500);
            $table->text('body_template')->nullable();
            $table->string('h1_pattern', 500)->nullable();

            // Thin-content guard — don't generate pages that would have
            // less than this many data points (e.g. don't list a city
            // with only 1 photographer registered).
            $table->unsignedInteger('min_data_points')->default(3);

            // Schema.org type to embed (Person, LocalBusiness, Event, etc.)
            $table->string('schema_type', 60)->nullable();

            // Internal-linking config — JSON
            //   { "include_related": true, "max_links": 8, "from_types": ["combo"] }
            $table->json('linking_config')->nullable();

            $table->timestamps();
            $table->index('type');
            $table->index('is_auto_enabled');
        });

        Schema::create('seo_landing_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()
                ->constrained('seo_page_templates')->nullOnDelete();

            // Globally-unique slug excluding leading slash, e.g.
            // "events-in-bangkok" or "wedding-photographers-bangkok-2026".
            $table->string('slug', 200)->unique();
            $table->string('type', 30);

            // Resolved final values served to the browser. PSeoService
            // writes them on generate; admins can override.
            $table->string('title', 500);
            $table->string('meta_description', 500);
            $table->string('h1', 500)->nullable();
            $table->text('body_html')->nullable();

            $table->string('og_image', 500)->nullable();
            $table->string('og_title', 500)->nullable();

            // JSON describing the source data the generator pulled at
            // generation time. Used by the regenerator to refresh the
            // page when source records change.
            //   {"location_id":1, "category_id":2, "year":2026, "data_count":15}
            $table->json('source_meta')->nullable();

            // Lock = "admin has customized; don't overwrite on regenerate".
            $table->boolean('is_locked')->default(false);

            // Publish gate — supports draft → review → live workflow.
            $table->boolean('is_published')->default(true);

            // Pre-rendered Schema.org JSON-LD. NULL = use type default.
            $table->json('schema_json')->nullable();

            // Per-page traffic stats (denormalized counter).
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();

            // Drives the "stale pages" list for proactive regeneration.
            $table->timestamp('regenerated_at')->nullable();

            $table->timestamps();
            $table->index(['type', 'is_published']);
            $table->index('regenerated_at');
            $table->index('view_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_landing_pages');
        Schema::dropIfExists('seo_page_templates');
    }
};
