<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plans — the "photo selling workspace" tiers photographers
 * pay monthly for. Each plan grants a storage quota (GB) and a set of AI
 * features. Prices are stored in THB to keep math local.
 *
 * Unlike the credit packages (one-time consumable), these are recurring:
 * the subscriber pays every month and the plan_id drives their quota
 * + capability bitmap until cancelled or downgraded.
 *
 * `ai_features` is a JSON array of feature flags that the gating
 * middleware reads (e.g. ['face_search', 'duplicate_detection']).
 *
 * `commission_pct` lets the free tier charge a commission while paid
 * tiers set it to 0 — photographers who pay monthly keep 100% of sales.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('subscription_plans')) {
            return;
        }

        Schema::create('subscription_plans', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();             // free, starter, pro, business, studio
            $t->string('name', 120);                      // "Pro — 100 GB"
            $t->string('tagline', 160)->nullable();       // "เหมาะกับช่างภาพ full-time"
            $t->text('description')->nullable();

            // Pricing
            $t->decimal('price_thb', 10, 2)->default(0);  // 890.00
            $t->decimal('price_annual_thb', 10, 2)->nullable();  // optional annual discount price
            $t->string('billing_cycle', 16)->default('monthly'); // 'monthly' | 'annual'

            // Quota & features
            $t->unsignedBigInteger('storage_bytes');      // plan → storage_quota_bytes on profile
            $t->json('ai_features')->nullable();          // ['face_search','quality_filter',...]
            $t->unsignedInteger('max_concurrent_events')->nullable();  // null = unlimited
            $t->unsignedInteger('max_team_seats')->default(1);
            $t->unsignedInteger('monthly_ai_credits')->default(0);  // indexing quota/month
            $t->decimal('commission_pct', 5, 2)->default(0);  // 0 for paid plans; 20-30 for free

            // Presentation
            $t->string('badge', 40)->nullable();          // "ขายดี" | "Popular"
            $t->string('color_hex', 16)->default('#6366f1');
            $t->unsignedInteger('sort_order')->default(0);
            $t->json('features_json')->nullable();        // extra bullet points for marketing page

            // Flags
            $t->boolean('is_active')->default(true)->index();
            $t->boolean('is_default_free')->default(false);  // auto-assigned on signup
            $t->boolean('is_public')->default(true);         // hide from plan picker if false

            $t->timestamps();

            $t->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
