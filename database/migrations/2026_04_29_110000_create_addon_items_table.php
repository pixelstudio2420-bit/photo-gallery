<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB-backed catalog for the photographer self-serve store.
 *
 * Why move from config to DB?
 * ───────────────────────────
 * `config/addon_catalog.php` was simple and git-reviewable, but admins
 * couldn't change pricing or activate/deactivate items without a deploy.
 * That's a non-starter for a marketplace where promotional pricing
 * changes weekly. This table mirrors the config schema 1:1 so the
 * existing `AddonService::catalog()` can switch sources transparently:
 * read DB rows when present, fall back to config for back-compat.
 *
 * Schema design rules
 *   • `sku` is the immutable identifier — once shipped, NEVER rename.
 *     The activation handler in AddonService dispatches by category,
 *     and historical photographer_addon_purchases.snapshot rows hold
 *     a SKU that admins can't break by editing it later.
 *   • `is_active` is the soft-delete equivalent. Don't actually delete
 *     rows that have purchases against them (FK preservation matters
 *     for audit + refund paths). The admin UI will warn on delete.
 *   • `meta` (JSON) holds category-specific fields:
 *       - promotion: kind, cycle, boost_score
 *       - storage:   storage_gb
 *       - ai_credits: credits
 *       - branding:  one_time, cycle
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_items', function (Blueprint $t) {
            $t->id();
            $t->string('sku', 60)->unique();              // boost.monthly, storage.50gb, etc.
            $t->string('category', 30);                    // promotion | storage | ai_credits | branding | priority
            $t->string('label', 120);                      // "Boost · 1 เดือน"
            $t->string('tagline', 200)->nullable();
            $t->decimal('price_thb', 10, 2)->default(0);
            $t->string('badge', 30)->nullable();           // "ขายดี" | "แนะนำ" | "พรีเมี่ยม"
            $t->json('meta')->nullable();                  // category-specific keys (boost_score, storage_gb, …)
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();

            $t->index('category');
            $t->index(['category', 'is_active', 'sort_order'], 'addon_items_browse_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_items');
    }
};
