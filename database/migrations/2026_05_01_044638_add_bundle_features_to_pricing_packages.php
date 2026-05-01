<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds bundle-marketing fields to pricing_packages so the table can power:
 *   • Volume bundles      (3 / 6 / 10 / 20 photos at increasing discounts)
 *   • Face-match bundles  ("buy all photos of you" — count is dynamic per buyer)
 *   • Event-all bundles   ("download every photo from the event" — flat fee)
 *
 * Why one table for three bundle types instead of three tables:
 *   The buyer-facing UX is identical — pick a card, see the price, add to cart.
 *   Splitting the storage just because the *math* differs would force every
 *   listing query to UNION across three tables. A discriminator column is the
 *   pragmatic call.
 *
 * Each new column is nullable so legacy rows (the ones already shipped before
 * this migration) keep working unchanged: `bundle_type` defaults to 'count',
 * which is exactly the volume-bundle semantics they already had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            // ── Bundle classification ─────────────────────────────────────
            // 'count'      — fixed photo_count, fixed price (the legacy shape)
            // 'face_match' — count = however many photos the face-search found
            //                for THIS buyer; price = base × count × discount_pct
            //                (capped at max_price). Special-cased in BundleService.
            // 'event_all'  — single flat-fee bundle for the entire event;
            //                photo_count is informational only at runtime.
            $table->string('bundle_type', 20)->default('count')->after('photo_count');

            // ── Pricing helpers ──────────────────────────────────────────
            // Discount percentage applied to face_match bundles. Stored as a
            // plain percent (50.00 = 50% off). Ignored for count/event_all
            // since their price is set directly.
            $table->decimal('discount_pct', 5, 2)->nullable()->after('price');

            // Hard ceiling for face_match bundles. Without this a buyer with
            // 200 photos on a ฿100/photo event would see "฿10,000 — 50% off
            // = ฿5,000" which scares people off. Cap at e.g. ฿1,500.
            $table->decimal('max_price', 10, 2)->nullable()->after('discount_pct');

            // The "fake" original price shown crossed-out next to the bundle
            // price. Drives the loss-aversion frame: "~~฿600~~ ฿480, save ฿120!"
            // Computed at seed time as photo_count × per_photo (no discount).
            $table->decimal('original_price', 10, 2)->nullable()->after('max_price');

            // ── Marketing chrome ─────────────────────────────────────────
            // Free-form badge text shown on the card. Defaults to a smart
            // value based on position ("ขายดีที่สุด", "คุ้มค่าที่สุด", etc).
            $table->string('badge', 50)->nullable()->after('original_price');

            // Whether to render the "best value" highlight ring + scale-up
            // styling on this card. Photographers can pin one bundle as the
            // recommended pick; we default to the 6-photo bundle when seeding.
            $table->boolean('is_featured')->default(false)->after('badge');

            // Display order on the public page. Without this the order would
            // depend on photo_count for count-bundles but be undefined for
            // face_match/event_all (where photo_count is null/dynamic).
            $table->integer('sort_order')->default(0)->after('is_featured');

            // Optional one-line subtitle shown beneath the bundle name.
            // e.g. "เหมาะสำหรับลูกค้าทั่วไป" or "สำหรับครอบครัว".
            $table->string('bundle_subtitle', 200)->nullable()->after('description');

            // Denormalized counter — incremented every time an order using
            // this bundle is paid. Cheap for the dashboard and avoids a
            // join+count on every photographer page load.
            $table->unsignedInteger('purchase_count')->default(0)->after('sort_order');
        });

        // Index that matches the most common public-listing query:
        //   WHERE event_id = ? AND is_active = 1 ORDER BY sort_order
        // The existing primary-key index on `id` doesn't help here.
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->index(['event_id', 'is_active', 'sort_order'], 'pkg_event_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->dropIndex('pkg_event_active_sort_idx');
            $table->dropColumn([
                'bundle_type',
                'discount_pct',
                'max_price',
                'original_price',
                'badge',
                'is_featured',
                'sort_order',
                'bundle_subtitle',
                'purchase_count',
            ]);
        });
    }
};
