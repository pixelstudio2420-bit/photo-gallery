<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrich photographer_profiles with the fields needed to drive
 * a credible pSEO landing page + a fuller marketplace listing.
 *
 * Most marketplaces beat their photographer-listing competitors on
 * "rich profile data" — Google's helpful-content update specifically
 * rewards pages with depth (real bios, location signals, specialties).
 * Adding the fields below lets each photographer write the page
 * their own pSEO landing will render from.
 *
 * New fields:
 *   • district_id          — finer-grained location than province
 *   • headline             — short tagline ("Wedding & Portrait, Bangkok")
 *   • languages            — JSON array of ISO codes ["th","en","ja"]
 *   • equipment            — JSON array of camera + lens names
 *   • service_areas        — JSON array of province IDs the photographer
 *                            covers in addition to their home province
 *   • website_url          — separate from portfolio_url for SEO links
 *   • instagram_handle     — without leading @
 *   • facebook_url         — full URL
 *   • line_id              — optional contact ID (private — not on pSEO)
 *   • accepts_bookings     — quick toggle "currently taking new work"
 *   • response_time_hours  — typical reply-to-inquiry time (drives a
 *                            "responds within X hrs" badge)
 *   • portfolio_completion — denormalized 0-100 score for prompts
 *                            ("complete your profile to rank higher")
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('district_id')->nullable()->after('province_id');
            $table->string('headline', 200)->nullable()->after('display_name');
            $table->json('languages')->nullable()->after('specialties');
            $table->json('equipment')->nullable()->after('languages');
            $table->json('service_areas')->nullable()->after('equipment');
            $table->string('website_url', 300)->nullable()->after('portfolio_url');
            $table->string('instagram_handle', 80)->nullable()->after('website_url');
            $table->string('facebook_url', 300)->nullable()->after('instagram_handle');
            $table->string('line_id', 80)->nullable()->after('facebook_url');
            $table->boolean('accepts_bookings')->default(true)->after('line_id');
            $table->unsignedSmallInteger('response_time_hours')->nullable()->after('accepts_bookings');
            $table->unsignedTinyInteger('profile_completion')->default(0)->after('response_time_hours');
        });
    }

    public function down(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'district_id', 'headline', 'languages', 'equipment',
                'service_areas', 'website_url', 'instagram_handle',
                'facebook_url', 'line_id', 'accepts_bookings',
                'response_time_hours', 'profile_completion',
            ]);
        });
    }
};
