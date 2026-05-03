<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation for geo-targeted notifications.
 *
 *   auth_users:    province_id, district_id, subdistrict_id (nullable, indexed)
 *   announcements: target_province_id, target_district_id, target_subdistrict_id
 *                  show_as_popup (boolean — controls whether the announcement
 *                                 appears as a modal vs an inbox entry)
 *
 * Why on auth_users (not a separate locations table)?
 *   - 1:1 — each user has one current province at a time
 *   - Hot-path filter for the popup display partial: WHERE
 *     province_id = :user_province AND show_as_popup = true → indexed
 *
 * Geo targeting semantics on announcements:
 *   - All target_* NULL          → broadcast to everyone (default)
 *   - target_province_id set      → only users in that province see it
 *   - target_district_id set      → must match district_id (province
 *                                   match implicit since districts FK
 *                                   to a single province)
 *   - target_subdistrict_id set   → most specific filter
 *
 * Combination rule: more-specific target wins. Setting district_id
 * and subdistrict_id together means "show to users in this exact
 * subdistrict only" — the popup query already AND's all three.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('auth_users')) {
            Schema::table('auth_users', function (Blueprint $t) {
                if (!Schema::hasColumn('auth_users', 'province_id'))    $t->unsignedBigInteger('province_id')->nullable()->index();
                if (!Schema::hasColumn('auth_users', 'district_id'))    $t->unsignedBigInteger('district_id')->nullable()->index();
                if (!Schema::hasColumn('auth_users', 'subdistrict_id')) $t->unsignedBigInteger('subdistrict_id')->nullable();
            });
        }

        if (Schema::hasTable('announcements')) {
            Schema::table('announcements', function (Blueprint $t) {
                if (!Schema::hasColumn('announcements', 'target_province_id'))    $t->unsignedBigInteger('target_province_id')->nullable()->after('audience')->index();
                if (!Schema::hasColumn('announcements', 'target_district_id'))    $t->unsignedBigInteger('target_district_id')->nullable()->after('target_province_id');
                if (!Schema::hasColumn('announcements', 'target_subdistrict_id')) $t->unsignedBigInteger('target_subdistrict_id')->nullable()->after('target_district_id');

                // show_as_popup distinguishes the "interrupt the user with a
                // modal" announcement (e.g. new event in your area) from
                // a passive notice that lives in an inbox/dashboard list.
                if (!Schema::hasColumn('announcements', 'show_as_popup'))         $t->boolean('show_as_popup')->default(false)->after('is_pinned');

                // Tracks which announcements each user has already seen +
                // dismissed — stored as a separate table (many-to-many).
                // Created below.
            });
        }

        // Per-user dismissal log so the popup only fires once per user
        // per announcement. Foreign keys deliberately omitted to keep
        // the migration portable across environments where the parent
        // tables might be present at slightly different states.
        if (!Schema::hasTable('announcement_dismissals')) {
            Schema::create('announcement_dismissals', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('announcement_id');
                $t->unsignedBigInteger('user_id');
                $t->timestamp('dismissed_at')->useCurrent();
                $t->unique(['announcement_id', 'user_id']);
                $t->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');

        if (Schema::hasTable('announcements')) {
            Schema::table('announcements', function (Blueprint $t) {
                foreach (['target_province_id','target_district_id','target_subdistrict_id','show_as_popup'] as $col) {
                    if (Schema::hasColumn('announcements', $col)) $t->dropColumn($col);
                }
            });
        }
        if (Schema::hasTable('auth_users')) {
            Schema::table('auth_users', function (Blueprint $t) {
                foreach (['province_id','district_id','subdistrict_id'] as $col) {
                    if (Schema::hasColumn('auth_users', $col)) $t->dropColumn($col);
                }
            });
        }
    }
};
