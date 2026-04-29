<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track photographer-facing addon purchases (promotion slots, storage
 * top-ups, AI credit packs, branding unlocks).
 *
 * Why a dedicated table (vs reusing photographer_promotions or stuffing
 * into Order.metadata)
 * --------------------------------------------------------------------
 * Each purchase is a discrete "the photographer bought X for ฿Y" event.
 * `photographer_promotions` only models boost/featured/highlight; this
 * table covers EVERYTHING in the photographer self-serve store —
 * including non-promotion addons (extra storage, extra AI credits,
 * branding, priority lane).
 *
 * Storing the catalog snapshot (sku + price_thb_at_purchase + meta) on
 * the row means we can:
 *   1. show old purchases in the photographer's history correctly even
 *      after we change pricing
 *   2. roll a refund without joining 5 different lookup tables
 *   3. build "best sellers / revenue per addon" reports trivially
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('photographer_addon_purchases')) return;

        Schema::create('photographer_addon_purchases', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('photographer_id')->index();
            $t->string('sku', 60)->index();          // catalog id, e.g. 'boost.monthly', 'storage.50gb'
            $t->string('category', 24)->index();     // promotion | storage | ai_credits | branding | priority
            $t->decimal('price_thb', 10, 2);
            $t->json('snapshot')->nullable();        // captured catalog row at purchase time
            $t->unsignedBigInteger('order_id')->nullable()->index();   // payment order
            $t->unsignedBigInteger('promotion_id')->nullable()->index(); // when category=promotion
            $t->string('status', 16)->default('pending');               // pending | paid | activated | refunded | failed
            $t->timestamp('activated_at')->nullable();
            $t->timestamp('expires_at')->nullable();   // null = lifetime/unlimited
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photographer_addon_purchases');
    }
};
