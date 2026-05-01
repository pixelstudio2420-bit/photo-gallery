<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-page customization fields for pSEO landing pages.
 *
 * The original schema gave us title / meta / body — enough for SEO but
 * the rendered page looked generic. These fields turn each landing
 * page into a fully-customizable marketing surface:
 *
 *   • hero_image      — banner photo at the top of the page
 *   • theme           — one of 8 colour presets (photography/wedding/
 *                       sport/concert/corporate/portrait/festival/default)
 *                       drives gradient, accent colour, icon set
 *   • cta_text/url    — primary call-to-action button below the hero
 *   • extra_sections  — JSON array for FAQ, testimonials, custom HTML
 *                       blocks the admin can drop in
 *   • show_gallery    — toggle the related-events grid
 *   • show_related    — toggle the sibling-pages internal-link block
 *   • show_stats      — toggle the stats badges (count of events, etc.)
 *   • show_faq        — toggle the auto-generated FAQ section
 *
 * Defaults are picked so existing landing pages keep their current
 * look (theme=default, all sections shown). The auto-theme logic in
 * PSeoService maps event categories to themes at generate time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_landing_pages', function (Blueprint $table) {
            // Visual customization
            $table->string('hero_image', 500)->nullable()->after('og_image');
            $table->string('theme', 30)->default('default')->after('hero_image');
            $table->string('theme_color', 30)->nullable()->after('theme');

            // CTA — primary action button below hero
            $table->string('cta_text', 100)->nullable()->after('theme_color');
            $table->string('cta_url', 500)->nullable()->after('cta_text');

            // Optional sections — JSON list of {type, title, body}
            //   [{ "type": "faq", "title": "...", "body": "..." },
            //    { "type": "testimonial", "title": "...", "body": "..." }]
            $table->json('extra_sections')->nullable()->after('cta_url');

            // Section visibility toggles — let admin hide noise per page
            $table->boolean('show_gallery')->default(true)->after('extra_sections');
            $table->boolean('show_related')->default(true)->after('show_gallery');
            $table->boolean('show_stats')->default(true)->after('show_related');
            $table->boolean('show_faq')->default(false)->after('show_stats');
        });
    }

    public function down(): void
    {
        Schema::table('seo_landing_pages', function (Blueprint $table) {
            $table->dropColumn([
                'hero_image', 'theme', 'theme_color',
                'cta_text', 'cta_url', 'extra_sections',
                'show_gallery', 'show_related', 'show_stats', 'show_faq',
            ]);
        });
    }
};
