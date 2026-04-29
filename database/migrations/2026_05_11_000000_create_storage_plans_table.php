<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consumer cloud-storage plans — the "buy GB of space" tiers that end users
 * (not photographers) subscribe to. Think Google One / Dropbox Plus / iCloud+
 * but sold locally in THB by us.
 *
 * These are separate from `subscription_plans` (which are the photographer
 * seller-tier plans) because:
 *   - Target audience is different (consumers vs. photographers)
 *   - Feature surface is different (file storage vs. selling tools + AI)
 *   - We want each system toggleable independently via AppSetting
 *
 * `storage_bytes` is the monthly quota enforced by StorageQuotaService.
 * `max_file_size_bytes` caps single-file uploads so someone can't DoS us
 * with a 500 GB ISO on a 50 GB plan.
 * `features` is a JSON array of capability flags (e.g. 'sharing', 'versioning',
 * 'public_links'). Gating middleware reads this.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('storage_plans')) {
            return;
        }

        Schema::create('storage_plans', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();             // free, personal, plus, pro, max
            $t->string('name', 120);                      // "Personal — 50 GB"
            $t->string('tagline', 160)->nullable();       // "สำหรับเก็บรูป-เอกสารส่วนตัว"
            $t->text('description')->nullable();

            // Pricing
            $t->decimal('price_thb', 10, 2)->default(0);           // monthly
            $t->decimal('price_annual_thb', 10, 2)->nullable();    // optional yearly discount
            $t->string('billing_cycle', 16)->default('monthly');   // monthly | annual | trial

            // Quota & limits
            $t->unsignedBigInteger('storage_bytes');               // total account quota
            $t->unsignedBigInteger('max_file_size_bytes')->nullable();  // per-upload cap; null = no cap
            $t->unsignedInteger('max_files')->nullable();          // optional file count cap
            $t->json('features')->nullable();                      // ['sharing','public_links','versioning']

            // Presentation
            $t->string('badge', 40)->nullable();                   // "ยอดนิยม"
            $t->string('color_hex', 16)->default('#6366f1');
            $t->unsignedInteger('sort_order')->default(0);
            $t->json('features_json')->nullable();                 // extra marketing bullets

            // Flags
            $t->boolean('is_active')->default(true)->index();
            $t->boolean('is_default_free')->default(false);        // auto-assigned on signup
            $t->boolean('is_public')->default(true);               // hide from picker if false

            $t->timestamps();

            $t->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_plans');
    }
};
