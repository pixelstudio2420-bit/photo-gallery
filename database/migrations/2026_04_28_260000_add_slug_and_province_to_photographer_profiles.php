<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Per-photographer landing page support:
 *   - `slug`         — pretty URL component (`/photographer/{slug}`)
 *   - `province_id`  — homebase province for grouping + SEO filters
 *
 * Why these two columns?
 *   slug:     `photographer_code` (PH0002) is fine for admin/internal but
 *             unfriendly in URLs. Slugs are human-readable, stable, and
 *             SEO-rich. We keep the code as the canonical id and treat
 *             the slug as a redirect target ("/photographer/best-portrait-bkk"
 *             resolves to PH0002 internally).
 *   province: customer search and SEO landings filter by location. The
 *             column references thai_provinces.id; back-fillable from
 *             photographer phone area code or left null until set in onboarding.
 *
 * Backfill strategy
 * -----------------
 * up() generates slugs for existing rows from display_name (slugified +
 * uniqueness suffix). province_id is left NULL — onboarding form can
 * pick it up next time, and the photographer profile edit page lets the
 * user pick their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('photographer_profiles')) return;

        Schema::table('photographer_profiles', function (Blueprint $t) {
            if (!Schema::hasColumn('photographer_profiles', 'slug')) {
                $t->string('slug', 80)->nullable()->after('photographer_code');
                $t->unique('slug', 'photographer_profiles_slug_unique');
            }
            if (!Schema::hasColumn('photographer_profiles', 'province_id')) {
                $t->unsignedSmallInteger('province_id')->nullable()->after('phone');
                $t->index('province_id', 'photographer_profiles_province_idx');
            }
        });

        // Backfill slugs for existing rows.
        // ASCII fallback prefix uses photographer_code so the URL never
        // collides even when display_name has identical Thai content.
        if (Schema::hasColumn('photographer_profiles', 'slug')) {
            DB::table('photographer_profiles')
                ->whereNull('slug')
                ->orderBy('id')
                ->each(function ($row) {
                    $base = (string) ($row->display_name ?? $row->photographer_code ?? 'photographer');
                    // Prefer ASCII slug; if Thai-only display name slugifies
                    // to empty, fall back to the code.
                    $slug = Str::slug($base, '-', 'en');
                    if ($slug === '') {
                        $slug = Str::lower((string) $row->photographer_code);
                    }
                    // Append code suffix for uniqueness (handles 2 photographers
                    // named "Studio One").
                    $candidate = $slug . '-' . Str::lower((string) $row->photographer_code);
                    DB::table('photographer_profiles')
                        ->where('id', $row->id)
                        ->update(['slug' => mb_substr($candidate, 0, 80)]);
                });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('photographer_profiles')) return;

        Schema::table('photographer_profiles', function (Blueprint $t) {
            if (Schema::hasColumn('photographer_profiles', 'slug')) {
                $t->dropUnique('photographer_profiles_slug_unique');
                $t->dropColumn('slug');
            }
            if (Schema::hasColumn('photographer_profiles', 'province_id')) {
                $t->dropIndex('photographer_profiles_province_idx');
                $t->dropColumn('province_id');
            }
        });
    }
};
