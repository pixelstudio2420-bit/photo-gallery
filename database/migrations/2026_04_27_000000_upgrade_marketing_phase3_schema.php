<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Upgrade legacy marketing tables to Phase 3 schema.
 *
 * The Phase 1/2 migration (2026_04_25) created `marketing_landing_pages` and
 * `marketing_push_subscriptions` with a different (legacy) schema. The Phase 3
 * migration (2026_04_26) skipped the Schema::create on databases where those
 * tables already existed — so MySQL environments were left with old columns.
 *
 * This migration adds the missing columns without dropping any existing data.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── Landing Pages ────────────────────────────────────────────────
        if (Schema::hasTable('marketing_landing_pages')) {
            Schema::table('marketing_landing_pages', function (Blueprint $t) {
                if (!Schema::hasColumn('marketing_landing_pages', 'subtitle')) {
                    $t->string('subtitle')->nullable()->after('title');
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'hero_image')) {
                    $t->string('hero_image')->nullable()->after('subtitle');
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'theme')) {
                    $t->string('theme', 32)->default('indigo')->after('hero_image');
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'sections')) {
                    $t->json('sections')->nullable();
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'seo')) {
                    $t->json('seo')->nullable();
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'utm_override')) {
                    $t->json('utm_override')->nullable();
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'status')) {
                    $t->string('status', 16)->default('draft');
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'campaign_id')) {
                    $t->unsignedBigInteger('campaign_id')->nullable();
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'views')) {
                    $t->unsignedBigInteger('views')->default(0);
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'conversions')) {
                    $t->unsignedBigInteger('conversions')->default(0);
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'author_id')) {
                    $t->unsignedBigInteger('author_id')->nullable();
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'published_at')) {
                    $t->timestamp('published_at')->nullable();
                }
            });

            // Data migration: map legacy columns into new columns
            // legacy is_published (bool) → status ('published'|'draft')
            if (Schema::hasColumn('marketing_landing_pages', 'is_published')) {
                // Postgres: `is_published` is BOOLEAN — compare with TRUE not 1.
                // MySQL accepts both, but PG strictly requires matching types.
                $driver = \DB::connection()->getDriverName();
                $boolTrue = $driver === 'pgsql' ? 'true' : '1';
                DB::statement("
                    UPDATE marketing_landing_pages
                    SET status = CASE WHEN is_published = {$boolTrue} THEN 'published' ELSE 'draft' END,
                        published_at = CASE WHEN is_published = {$boolTrue} AND published_at IS NULL THEN created_at ELSE published_at END
                    WHERE (status = 'draft' OR status = '' OR status IS NULL)
                ");
            }
            // legacy view_count → views, conversion_count → conversions
            if (Schema::hasColumn('marketing_landing_pages', 'view_count')) {
                DB::statement("UPDATE marketing_landing_pages SET views = view_count WHERE views = 0");
            }
            if (Schema::hasColumn('marketing_landing_pages', 'conversion_count')) {
                DB::statement("UPDATE marketing_landing_pages SET conversions = conversion_count WHERE conversions = 0");
            }

            // Try to add indexes (OK if they already exist)
            try {
                Schema::table('marketing_landing_pages', function (Blueprint $t) {
                    $t->index(['status', 'published_at'], 'mlp_status_published_idx');
                });
            } catch (\Throwable $e) { /* index already exists */ }
            try {
                Schema::table('marketing_landing_pages', function (Blueprint $t) {
                    $t->index('campaign_id', 'mlp_campaign_idx');
                });
            } catch (\Throwable $e) { /* index already exists */ }
        }

        // ── Push Subscriptions ──────────────────────────────────────────
        if (Schema::hasTable('marketing_push_subscriptions')) {
            Schema::table('marketing_push_subscriptions', function (Blueprint $t) {
                if (!Schema::hasColumn('marketing_push_subscriptions', 'p256dh')) {
                    $t->string('p256dh', 200)->nullable();
                }
                if (!Schema::hasColumn('marketing_push_subscriptions', 'auth')) {
                    $t->string('auth', 100)->nullable();
                }
                if (!Schema::hasColumn('marketing_push_subscriptions', 'ua')) {
                    $t->string('ua', 255)->nullable();
                }
                if (!Schema::hasColumn('marketing_push_subscriptions', 'locale')) {
                    $t->string('locale', 10)->nullable();
                }
                if (!Schema::hasColumn('marketing_push_subscriptions', 'tags')) {
                    $t->json('tags')->nullable();
                }
                if (!Schema::hasColumn('marketing_push_subscriptions', 'status')) {
                    $t->string('status', 16)->default('active');
                }
                if (!Schema::hasColumn('marketing_push_subscriptions', 'last_seen_at')) {
                    $t->timestamp('last_seen_at')->nullable();
                }
            });

            // Migrate legacy data
            if (Schema::hasColumn('marketing_push_subscriptions', 'p256dh_key')) {
                DB::statement("UPDATE marketing_push_subscriptions SET p256dh = p256dh_key WHERE (p256dh IS NULL OR p256dh = '')");
            }
            if (Schema::hasColumn('marketing_push_subscriptions', 'auth_token')) {
                DB::statement("UPDATE marketing_push_subscriptions SET auth = auth_token WHERE (auth IS NULL OR auth = '')");
            }
            if (Schema::hasColumn('marketing_push_subscriptions', 'user_agent')) {
                DB::statement("UPDATE marketing_push_subscriptions SET ua = user_agent WHERE (ua IS NULL OR ua = '')");
            }
            if (Schema::hasColumn('marketing_push_subscriptions', 'is_active')) {
                $boolTrue = \DB::connection()->getDriverName() === 'pgsql' ? 'true' : '1';
                DB::statement("UPDATE marketing_push_subscriptions SET status = CASE WHEN is_active = {$boolTrue} THEN 'active' ELSE 'revoked' END");
            }
        }
    }

    public function down(): void
    {
        // Only drop columns that are uniquely added by this upgrade.
        // Legacy columns are preserved.
        if (Schema::hasTable('marketing_landing_pages')) {
            Schema::table('marketing_landing_pages', function (Blueprint $t) {
                foreach (['subtitle', 'hero_image', 'theme', 'sections', 'seo',
                         'utm_override', 'status', 'campaign_id', 'views',
                         'conversions', 'author_id', 'published_at'] as $col) {
                    if (Schema::hasColumn('marketing_landing_pages', $col)) {
                        try { $t->dropColumn($col); } catch (\Throwable $e) {}
                    }
                }
            });
        }
        if (Schema::hasTable('marketing_push_subscriptions')) {
            Schema::table('marketing_push_subscriptions', function (Blueprint $t) {
                foreach (['p256dh', 'auth', 'ua', 'locale', 'tags', 'status', 'last_seen_at'] as $col) {
                    if (Schema::hasColumn('marketing_push_subscriptions', $col)) {
                        try { $t->dropColumn($col); } catch (\Throwable $e) {}
                    }
                }
            });
        }
    }
};
