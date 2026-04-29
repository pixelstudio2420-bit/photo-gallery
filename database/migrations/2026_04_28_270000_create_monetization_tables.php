<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monetization stack — 5 tables in one migration.
 *
 * Why one file
 * ------------
 * The 5 tables are tightly coupled (campaigns → creatives → impressions
 * → clicks → conversions). Splitting them across files would make the
 * FK ordering brittle and the rollback story painful (you'd need to
 * down() in reverse FK order across 5 files). One migration = one
 * atomic deploy.
 *
 * Table responsibilities
 * ----------------------
 *
 *   photographer_promotions — boost slots a photographer paid for to
 *       appear higher in search results. One row per active boost
 *       window. Status follows the standard "pending → active → expired"
 *       arc. The boost_score column is what the ranking algorithm reads.
 *
 *   ad_campaigns — top-level brand campaigns (e.g. "Sony A7IV launch").
 *       Carries pricing model + budget cap. Contains many creatives.
 *
 *   ad_creatives — actual banner/image variants. A campaign can have
 *       2-5 creatives for A/B testing. Each ad_creative renders in N
 *       slots (homepage, search, landing).
 *
 *   ad_impressions / ad_clicks — high-volume tracking tables. Append
 *       only, partition-friendly (we'll partition by month later if
 *       Postgres pg_size > 5GB on these tables).
 *
 * High-write / low-read
 * ---------------------
 * impressions/clicks are the hottest write paths in the whole app. We
 * keep them denormalised (no FK on user_id, just an index) so the
 * INSERT cost stays under 1ms even at thousands of writes/sec.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Photographer Promotion ──────────────────────────────────────
        if (!Schema::hasTable('photographer_promotions')) {
            Schema::create('photographer_promotions', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('photographer_id')->index();   // → auth_users.id
                $t->string('kind', 20)->default('boost');           // boost | featured | highlight
                $t->string('placement', 20)->default('global');     // global | category | province
                $t->string('placement_target', 80)->nullable();     // category slug or province id when scoped
                $t->decimal('boost_score', 6, 2)->default(0);       // added to organic ranking score
                $t->string('billing_cycle', 16);                    // pay_per_use | daily | monthly | yearly
                $t->decimal('amount_thb', 10, 2);                   // total paid for this period
                $t->timestamp('starts_at')->index();
                $t->timestamp('ends_at')->index();
                $t->string('status', 16)->default('pending');       // pending | active | expired | cancelled | refunded
                $t->unsignedBigInteger('order_id')->nullable();     // payment reference
                $t->json('meta')->nullable();
                $t->timestamps();

                $t->index(['status', 'starts_at', 'ends_at'], 'idx_promo_active');
                $t->index(['kind', 'placement', 'status'], 'idx_promo_lookup');
            });
        }

        // ── Ad Campaign (brand-level container) ─────────────────────────
        if (!Schema::hasTable('ad_campaigns')) {
            Schema::create('ad_campaigns', function (Blueprint $t) {
                $t->id();
                $t->string('name', 200);
                $t->string('advertiser', 200);                      // brand display name
                $t->string('contact_email', 180)->nullable();
                $t->string('pricing_model', 20);                    // cpm | cpc | flat_daily | flat_monthly
                $t->decimal('rate_thb', 10, 4);                     // ฿ per 1000 impressions (CPM), per click (CPC), or flat
                $t->decimal('budget_cap_thb', 12, 2)->nullable();   // overall cap; null = unlimited
                $t->decimal('spent_thb', 12, 2)->default(0);
                $t->timestamp('starts_at')->index();
                $t->timestamp('ends_at')->index();
                $t->string('status', 16)->default('pending');       // pending | active | paused | exhausted | ended
                $t->unsignedBigInteger('order_id')->nullable();
                $t->unsignedInteger('created_by')->nullable();      // admin who created
                $t->timestamps();

                $t->index(['status', 'starts_at', 'ends_at'], 'idx_camp_active');
            });
        }

        // ── Ad Creative (one campaign → many image/text variants) ──────
        if (!Schema::hasTable('ad_creatives')) {
            Schema::create('ad_creatives', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('campaign_id')->index();
                $t->string('headline', 120);
                $t->string('body', 300)->nullable();
                $t->string('image_url', 500);
                $t->string('click_url', 500);                       // outbound destination (must be absolute https://)
                $t->string('cta_label', 40)->default('เรียนรู้เพิ่มเติม');
                $t->string('placement', 24);                        // homepage_banner | sidebar | search_inline | landing_native
                $t->unsignedSmallInteger('weight')->default(100);   // higher = served more often within campaign
                $t->boolean('is_active')->default(true)->index();
                $t->timestamps();

                $t->foreign('campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
            });
        }

        // ── Ad Impression (high-volume, partition-friendly) ────────────
        if (!Schema::hasTable('ad_impressions')) {
            Schema::create('ad_impressions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('creative_id')->index();
                $t->unsignedBigInteger('campaign_id')->index();
                $t->string('placement', 24)->index();
                $t->ipAddress('ip')->index();
                $t->string('user_agent_hash', 16)->index();         // sha1 of UA, first 16 chars (cheap dedup key)
                $t->string('session_id', 80)->nullable();
                $t->unsignedInteger('user_id')->nullable()->index();
                $t->string('referrer_host', 120)->nullable();
                $t->boolean('is_bot')->default(false)->index();
                $t->timestamp('seen_at')->useCurrent()->index();
                // No updated_at — append-only.
            });
        }

        // ── Ad Click ───────────────────────────────────────────────────
        if (!Schema::hasTable('ad_clicks')) {
            Schema::create('ad_clicks', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('creative_id')->index();
                $t->unsignedBigInteger('campaign_id')->index();
                $t->ipAddress('ip')->index();
                $t->string('user_agent_hash', 16)->index();
                $t->unsignedInteger('user_id')->nullable()->index();
                $t->string('referrer_host', 120)->nullable();
                $t->boolean('is_suspicious')->default(false)->index();  // flagged by anti-fraud
                $t->string('fraud_reason', 60)->nullable();
                $t->timestamp('clicked_at')->useCurrent()->index();
            });
        }

        // ── Daily aggregation ─────────────────────────────────────────
        // Cron runs at 00:05 daily to roll up the impression/click tables
        // into this thin reporting layer. Dashboards read from here, not
        // from the raw tables.
        if (!Schema::hasTable('ad_daily_metrics')) {
            Schema::create('ad_daily_metrics', function (Blueprint $t) {
                $t->id();
                $t->date('date')->index();
                $t->unsignedBigInteger('campaign_id')->index();
                $t->unsignedBigInteger('creative_id')->nullable()->index();
                $t->unsignedInteger('impressions')->default(0);
                $t->unsignedInteger('valid_impressions')->default(0); // bot-filtered
                $t->unsignedInteger('clicks')->default(0);
                $t->unsignedInteger('valid_clicks')->default(0);      // suspicion-filtered
                $t->unsignedInteger('conversions')->default(0);
                $t->decimal('spend_thb', 10, 2)->default(0);
                $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $t->unique(['date', 'campaign_id', 'creative_id'], 'idx_daily_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_daily_metrics');
        Schema::dropIfExists('ad_clicks');
        Schema::dropIfExists('ad_impressions');
        Schema::dropIfExists('ad_creatives');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('photographer_promotions');
    }
};
