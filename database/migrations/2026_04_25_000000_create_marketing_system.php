<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing System — Phase 1-3 unified migration
 *
 * Adds tables for:
 *   - Newsletter subscribers
 *   - Email campaigns
 *   - UTM attribution
 *   - Referral codes + redemptions
 *   - Loyalty accounts + transactions
 *   - Landing pages
 *   - Push subscriptions (web push)
 *
 * Seeds 40+ app_settings keys — all features default OFF.
 * Each feature has `marketing_<feature>_enabled` toggle plus per-platform config.
 */
return new class extends Migration {
    public function up(): void
    {
        // ───────────────────────────────────────────────────────────
        // 1. Newsletter Subscribers
        // ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('marketing_subscribers')) {
            Schema::create('marketing_subscribers', function (Blueprint $t) {
                $t->id();
                $t->string('email')->unique();
                $t->string('name')->nullable();
                $t->string('locale', 8)->default('th');
                $t->string('source', 32)->nullable();      // newsletter_widget|checkout|referral|import
                $t->enum('status', ['pending', 'confirmed', 'unsubscribed', 'bounced'])->default('pending');
                $t->string('confirm_token', 64)->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('unsubscribed_at')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->json('tags')->nullable();              // segmentation
                $t->json('meta')->nullable();              // utm, ip, etc.
                $t->timestamps();
                $t->index(['status', 'created_at']);
                $t->index('user_id');
            });
        }

        // ───────────────────────────────────────────────────────────
        // 2. Email Campaigns
        // ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('marketing_campaigns')) {
            Schema::create('marketing_campaigns', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('subject');
                $t->string('channel', 16)->default('email');   // email|line|push
                $t->longText('body_markdown')->nullable();
                $t->longText('body_html')->nullable();
                $t->json('segment')->nullable();                // {type: 'all'|'subscribers'|'vip'|'dormant'|'tag', value: ...}
                $t->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'cancelled', 'failed'])->default('draft');
                $t->timestamp('scheduled_at')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->unsignedInteger('total_recipients')->default(0);
                $t->unsignedInteger('sent_count')->default(0);
                $t->unsignedInteger('open_count')->default(0);
                $t->unsignedInteger('click_count')->default(0);
                $t->unsignedInteger('bounce_count')->default(0);
                $t->unsignedInteger('unsubscribe_count')->default(0);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['status', 'scheduled_at']);
            });
        }

        // ───────────────────────────────────────────────────────────
        // 3. Campaign Recipients (one row per send)
        // ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('marketing_campaign_recipients')) {
            Schema::create('marketing_campaign_recipients', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('campaign_id');
                $t->string('email')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->unsignedBigInteger('subscriber_id')->nullable();
                $t->enum('status', ['queued', 'sent', 'opened', 'clicked', 'bounced', 'unsubscribed', 'failed'])->default('queued');
                $t->string('tracking_token', 48)->nullable()->unique();
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('opened_at')->nullable();
                $t->timestamp('clicked_at')->nullable();
                $t->text('error')->nullable();
                $t->timestamps();
                $t->index(['campaign_id', 'status']);
                $t->foreign('campaign_id')->references('id')->on('marketing_campaigns')->cascadeOnDelete();
            });
        }

        // ───────────────────────────────────────────────────────────
        // 4. UTM Attribution (every inbound click logged)
        // ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('marketing_utm_attributions')) {
            Schema::create('marketing_utm_attributions', function (Blueprint $t) {
                $t->id();
                $t->string('session_id', 64)->index();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->unsignedBigInteger('order_id')->nullable()->index();
                $t->string('utm_source', 64)->nullable();
                $t->string('utm_medium', 64)->nullable();
                $t->string('utm_campaign', 128)->nullable();
                $t->string('utm_term', 128)->nullable();
                $t->string('utm_content', 128)->nullable();
                $t->string('gclid', 128)->nullable();           // Google Ads click id
                $t->string('fbclid', 128)->nullable();          // Facebook click id
                $t->string('lineclid', 128)->nullable();        // LINE click id
                $t->string('referer', 512)->nullable();
                $t->string('landing_page', 512)->nullable();
                $t->string('user_agent', 256)->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamps();
                $t->index(['utm_source', 'created_at']);
                $t->index(['utm_campaign', 'created_at']);
            });
        }

        // ───────────────────────────────────────────────────────────
        // 5. Referral Codes
        // ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('marketing_referral_codes')) {
            Schema::create('marketing_referral_codes', function (Blueprint $t) {
                $t->id();
                $t->string('code', 32)->unique();
                $t->unsignedBigInteger('owner_user_id');       // who owns this referral
                $t->enum('discount_type', ['percent', 'fixed'])->default('percent');
                $t->decimal('discount_value', 10, 2)->default(10);
                $t->decimal('reward_value', 10, 2)->default(50);  // what owner gets
                $t->unsignedInteger('max_uses')->default(0);       // 0 = unlimited
                $t->unsignedInteger('uses_count')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamp('expires_at')->nullable();
                $t->timestamps();
                $t->index('owner_user_id');
            });
        }

        // ───────────────────────────────────────────────────────────
        // 6. Referral Redemptions
        // ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('marketing_referral_redemptions')) {
            Schema::create('marketing_referral_redemptions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('referral_code_id');
                $t->unsignedBigInteger('redeemer_user_id')->nullable();  // who used the code
                $t->unsignedBigInteger('order_id')->nullable();
                $t->decimal('discount_applied', 10, 2)->default(0);
                $t->decimal('reward_granted', 10, 2)->default(0);
                $t->enum('status', ['pending', 'rewarded', 'reversed'])->default('pending');
                $t->timestamp('rewarded_at')->nullable();
                $t->timestamps();
                $t->index(['referral_code_id', 'status']);
                $t->foreign('referral_code_id')->references('id')->on('marketing_referral_codes')->cascadeOnDelete();
            });
        }

        // ───────────────────────────────────────────────────────────
        // 7. Loyalty Accounts
        // ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('marketing_loyalty_accounts')) {
            Schema::create('marketing_loyalty_accounts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->unique();
                $t->unsignedInteger('points_balance')->default(0);
                $t->unsignedInteger('points_earned_total')->default(0);
                $t->unsignedInteger('points_redeemed_total')->default(0);
                $t->decimal('lifetime_spend', 12, 2)->default(0);
                $t->enum('tier', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze');
                $t->timestamp('tier_expires_at')->nullable();
                $t->timestamps();
            });
        }

        // ───────────────────────────────────────────────────────────
        // 8. Loyalty Transactions (ledger)
        // ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('marketing_loyalty_transactions')) {
            Schema::create('marketing_loyalty_transactions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('account_id');
                $t->unsignedBigInteger('user_id');
                $t->enum('type', ['earn', 'redeem', 'adjust', 'expire', 'reverse']);
                $t->integer('points');                  // signed: +earn, -redeem
                $t->decimal('related_amount', 10, 2)->nullable();
                $t->string('reason', 64)->nullable();
                $t->unsignedBigInteger('order_id')->nullable();
                $t->timestamps();
                $t->index(['account_id', 'created_at']);
                $t->foreign('account_id')->references('id')->on('marketing_loyalty_accounts')->cascadeOnDelete();
            });
        }

        // ───────────────────────────────────────────────────────────
        // 9. Landing Pages
        // ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('marketing_landing_pages')) {
            Schema::create('marketing_landing_pages', function (Blueprint $t) {
                $t->id();
                $t->string('slug', 96)->unique();
                $t->string('title');
                $t->string('headline')->nullable();
                $t->string('subheadline', 512)->nullable();
                $t->longText('body_markdown')->nullable();
                $t->string('cta_label', 64)->nullable();
                $t->string('cta_url', 512)->nullable();
                $t->string('og_image', 512)->nullable();
                $t->json('meta')->nullable();           // seo, pixel events, etc.
                $t->boolean('is_published')->default(false);
                $t->unsignedInteger('view_count')->default(0);
                $t->unsignedInteger('conversion_count')->default(0);
                $t->timestamps();
                $t->index(['is_published', 'slug']);
            });
        }

        // ───────────────────────────────────────────────────────────
        // 10. Push Subscriptions (Web Push / FCM)
        // ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('marketing_push_subscriptions')) {
            Schema::create('marketing_push_subscriptions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->string('endpoint', 512);
                $t->string('p256dh_key', 256)->nullable();
                $t->string('auth_token', 128)->nullable();
                $t->string('user_agent', 256)->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index('is_active');
            });
        }

        // ───────────────────────────────────────────────────────────
        // 11. Add marketing fields to existing tables (idempotent)
        // ───────────────────────────────────────────────────────────
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $t) {
                if (!Schema::hasColumn('orders', 'referral_code_id')) {
                    $t->unsignedBigInteger('referral_code_id')->nullable()->after('id');
                    $t->index('referral_code_id');
                }
                if (!Schema::hasColumn('orders', 'loyalty_points_earned')) {
                    $t->unsignedInteger('loyalty_points_earned')->default(0)->after('referral_code_id');
                }
                if (!Schema::hasColumn('orders', 'loyalty_points_redeemed')) {
                    $t->unsignedInteger('loyalty_points_redeemed')->default(0)->after('loyalty_points_earned');
                }
                if (!Schema::hasColumn('orders', 'utm_attribution_id')) {
                    $t->unsignedBigInteger('utm_attribution_id')->nullable()->after('loyalty_points_redeemed');
                }
            });
        }

        // ───────────────────────────────────────────────────────────
        // 12. Seed app_settings (idempotent — all OFF by default)
        // ───────────────────────────────────────────────────────────
        $now = now();
        $seeds = [
            // Master
            ['marketing_enabled',                    '0'],

            // ── Pixels / Analytics ──
            ['marketing_fb_pixel_enabled',           '0'],
            ['marketing_fb_pixel_id',                ''],
            ['marketing_fb_conversions_api_enabled', '0'],
            ['marketing_fb_conversions_api_token',   ''],
            ['marketing_fb_test_event_code',         ''],

            ['marketing_ga4_enabled',                '0'],
            ['marketing_ga4_measurement_id',         ''],
            ['marketing_ga4_api_secret',             ''],

            ['marketing_gtm_enabled',                '0'],
            ['marketing_gtm_container_id',           ''],

            ['marketing_google_ads_enabled',         '0'],
            ['marketing_google_ads_conversion_id',   ''],
            ['marketing_google_ads_conversion_label', ''],

            ['marketing_line_tag_enabled',           '0'],
            ['marketing_line_tag_id',                ''],

            ['marketing_tiktok_pixel_enabled',       '0'],
            ['marketing_tiktok_pixel_id',            ''],

            // ── UTM Tracking ──
            ['marketing_utm_tracking_enabled',       '1'], // low-cost, on by default
            ['marketing_utm_retention_days',         '90'],

            // ── SEO / Social ──
            ['marketing_schema_markup_enabled',      '1'], // free SEO boost, on by default
            ['marketing_og_auto_enabled',            '1'],
            ['marketing_og_default_image',           ''],

            // ── LINE Marketing ──
            ['marketing_line_messaging_enabled',     '0'],
            ['marketing_line_channel_access_token',  ''],
            ['marketing_line_channel_secret',        ''],
            ['marketing_line_oa_id',                 ''],
            ['marketing_line_notify_enabled',        '0'],
            ['marketing_line_notify_token',          ''],

            // ── Newsletter / Email ──
            ['marketing_newsletter_enabled',         '0'],
            ['marketing_newsletter_double_optin',    '1'],
            ['marketing_newsletter_welcome_enabled', '0'],
            ['marketing_email_campaigns_enabled',    '0'],
            ['marketing_email_from_name',            ''],
            ['marketing_email_from_address',         ''],
            ['marketing_email_unsubscribe_text',     'ยกเลิกการรับอีเมล'],

            // ── Referral ──
            ['marketing_referral_enabled',           '0'],
            ['marketing_referral_discount_type',     'percent'],
            ['marketing_referral_discount_value',    '10'],
            ['marketing_referral_reward_value',      '50'],
            ['marketing_referral_cooldown_days',     '0'],

            // ── Loyalty ──
            ['marketing_loyalty_enabled',            '0'],
            ['marketing_loyalty_earn_rate',          '1'],     // points per 1 THB
            ['marketing_loyalty_redeem_rate',        '10'],    // points per 1 THB discount
            ['marketing_loyalty_min_redeem',         '100'],
            ['marketing_loyalty_tier_silver_spend',  '3000'],
            ['marketing_loyalty_tier_gold_spend',    '15000'],
            ['marketing_loyalty_tier_platinum_spend', '50000'],

            // ── Landing Pages ──
            ['marketing_landing_pages_enabled',      '0'],

            // ── Push ──
            ['marketing_push_enabled',               '0'],
            ['marketing_push_vapid_public',          ''],
            ['marketing_push_vapid_private',         ''],

            // ── Analytics Dashboard ──
            ['marketing_analytics_enabled',          '1'],
        ];

        foreach ($seeds as [$key, $value]) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        // Drop FK-dependent first
        Schema::dropIfExists('marketing_push_subscriptions');
        Schema::dropIfExists('marketing_landing_pages');
        Schema::dropIfExists('marketing_loyalty_transactions');
        Schema::dropIfExists('marketing_loyalty_accounts');
        Schema::dropIfExists('marketing_referral_redemptions');
        Schema::dropIfExists('marketing_referral_codes');
        Schema::dropIfExists('marketing_utm_attributions');
        Schema::dropIfExists('marketing_campaign_recipients');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_subscribers');

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $t) {
                foreach (['utm_attribution_id', 'loyalty_points_redeemed', 'loyalty_points_earned', 'referral_code_id'] as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }

        DB::table('app_settings')->where('key', 'like', 'marketing_%')->delete();
    }
};
