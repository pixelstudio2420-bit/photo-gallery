<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        /* ==========================================================
         | 1. Landing Pages
         | สร้าง LP แบบกำหนดเองต่อ campaign
         | ========================================================*/
        if (! Schema::hasTable('marketing_landing_pages')) {
            Schema::create('marketing_landing_pages', function (Blueprint $t) {
                $t->id();
                $t->string('slug', 120)->unique();
                $t->string('title');
                $t->string('subtitle')->nullable();
                $t->string('hero_image')->nullable();
                $t->string('theme', 32)->default('indigo'); // indigo/pink/emerald/amber/slate
                $t->string('cta_label', 80)->nullable();
                $t->string('cta_url', 500)->nullable();
                $t->json('sections')->nullable();           // blocks: heading, text, image, features, testimonial, faq
                $t->json('seo')->nullable();                // title, description, og_image, noindex
                $t->json('utm_override')->nullable();       // default UTM for outbound CTAs
                $t->string('status', 16)->default('draft'); // draft|published|archived
                $t->unsignedBigInteger('campaign_id')->nullable();
                $t->unsignedBigInteger('views')->default(0);
                $t->unsignedBigInteger('conversions')->default(0);
                $t->unsignedBigInteger('author_id')->nullable();
                $t->timestamp('published_at')->nullable();
                $t->timestamps();

                $t->index(['status', 'published_at']);
                $t->index('campaign_id');
            });
        } else {
            // Upgrade existing legacy table (phase 1/2) to include phase 3 columns
            Schema::table('marketing_landing_pages', function (Blueprint $t) {
                if (!Schema::hasColumn('marketing_landing_pages', 'subtitle')) {
                    $t->string('subtitle')->nullable();
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'hero_image')) {
                    $t->string('hero_image')->nullable();
                }
                if (!Schema::hasColumn('marketing_landing_pages', 'theme')) {
                    $t->string('theme', 32)->default('indigo');
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
        }

        /* ==========================================================
         | 2. Push Subscriptions (browser Web Push — VAPID)
         | ========================================================*/
        if (Schema::hasTable('marketing_push_subscriptions')) {
            // Upgrade legacy schema (p256dh_key/auth_token/is_active) to Phase 3
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
        }
        if (! Schema::hasTable('marketing_push_subscriptions')) {
            Schema::create('marketing_push_subscriptions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('endpoint', 600);
                $t->string('p256dh', 200);
                $t->string('auth', 100);
                $t->string('ua', 255)->nullable();
                $t->string('locale', 10)->nullable();
                $t->json('tags')->nullable();
                $t->string('status', 16)->default('active'); // active|stale|revoked
                $t->timestamp('last_seen_at')->nullable();
                $t->timestamps();

                $t->unique('endpoint');
                $t->index(['status', 'user_id']);
            });
        }

        /* ==========================================================
         | 3. Push Campaigns (broadcast history)
         | ========================================================*/
        if (! Schema::hasTable('marketing_push_campaigns')) {
            Schema::create('marketing_push_campaigns', function (Blueprint $t) {
                $t->id();
                $t->string('title');
                $t->string('body', 500);
                $t->string('icon')->nullable();
                $t->string('click_url', 500)->nullable();
                $t->string('segment', 24)->default('all');  // all|users|guests|tag
                $t->string('segment_value')->nullable();
                $t->unsignedInteger('targets')->default(0);
                $t->unsignedInteger('sent')->default(0);
                $t->unsignedInteger('failed')->default(0);
                $t->unsignedInteger('clicks')->default(0);
                $t->string('status', 16)->default('draft'); // draft|sending|sent|failed
                $t->unsignedBigInteger('author_id')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->timestamps();

                $t->index('status');
            });
        }

        /* ==========================================================
         | 4. Marketing Events (unified funnel/cohort/LTV/ROAS)
         | เก็บ event แยก table เพื่อ analytics ไม่กระทบ production DB
         | ========================================================*/
        if (! Schema::hasTable('marketing_events')) {
            Schema::create('marketing_events', function (Blueprint $t) {
                $t->id();
                $t->string('event_name', 64);               // page_view, view_product, add_to_cart, begin_checkout, purchase, signup, ...
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('session_id', 64)->nullable();
                $t->string('url', 500)->nullable();
                $t->string('referrer', 500)->nullable();
                $t->string('utm_source', 80)->nullable();
                $t->string('utm_medium', 80)->nullable();
                $t->string('utm_campaign', 120)->nullable();
                $t->string('utm_content', 120)->nullable();
                $t->string('utm_term', 120)->nullable();
                $t->unsignedBigInteger('lp_id')->nullable();          // landing page id
                $t->unsignedBigInteger('campaign_id')->nullable();    // email campaign id
                $t->unsignedBigInteger('push_campaign_id')->nullable();
                $t->unsignedBigInteger('order_id')->nullable();
                $t->decimal('value', 12, 2)->nullable();    // purchase amount
                $t->string('currency', 8)->nullable();
                $t->json('meta')->nullable();
                $t->string('ip', 45)->nullable();
                $t->string('country', 8)->nullable();
                $t->string('device', 16)->nullable();       // desktop|mobile|tablet|bot
                $t->timestamp('occurred_at')->useCurrent();
                $t->timestamps();

                $t->index(['event_name', 'occurred_at']);
                $t->index(['utm_source', 'utm_medium']);
                $t->index(['utm_campaign']);
                $t->index(['user_id', 'occurred_at']);
                $t->index('session_id');
                $t->index('lp_id');
            });
        }

        /* ==========================================================
         | 5. Phase 3 App Settings
         | ========================================================*/
        $now = now();
        $seeds = [
            // Landing Pages
            ['key' => 'marketing_landing_pages_enabled', 'value' => '0'],
            ['key' => 'marketing_lp_default_theme',      'value' => 'indigo'],

            // Push
            ['key' => 'marketing_push_enabled',          'value' => '0'],
            ['key' => 'marketing_push_vapid_public',     'value' => ''],
            ['key' => 'marketing_push_vapid_private',    'value' => ''],
            ['key' => 'marketing_push_vapid_subject',    'value' => 'mailto:admin@example.com'],
            ['key' => 'marketing_push_prompt_delay',     'value' => '10'],
            ['key' => 'marketing_push_prompt_text',      'value' => 'รับข่าวสารและโปรโมชั่นแบบทันใจ?'],

            // Analytics v2 (Phase 3 — funnel/cohort/LTV/ROAS)
            ['key' => 'marketing_analytics_enabled',     'value' => '0'],
            ['key' => 'marketing_event_retention_days',  'value' => '180'],
        ];
        foreach ($seeds as $s) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $s['key']],
                ['value' => $s['value'], 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_events');
        Schema::dropIfExists('marketing_push_campaigns');
        Schema::dropIfExists('marketing_push_subscriptions');
        Schema::dropIfExists('marketing_landing_pages');

        DB::table('app_settings')->whereIn('key', [
            'marketing_lp_enabled',
            'marketing_lp_default_theme',
            'marketing_push_enabled',
            'marketing_push_vapid_public',
            'marketing_push_vapid_private',
            'marketing_push_vapid_subject',
            'marketing_push_prompt_delay',
            'marketing_push_prompt_text',
            'marketing_analytics_v2_enabled',
            'marketing_event_retention_days',
        ])->delete();
    }
};
