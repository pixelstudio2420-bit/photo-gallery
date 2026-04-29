<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom branding settings.
 *
 * One-row-per-photographer settings store for the "Custom Branding" feature
 * (Business+) and "White-label" (Studio). Editable from the photographer's
 * settings panel; consumed by event/portfolio public pages.
 *
 * Fields:
 *   - logo_path: optional studio logo (replaces site logo on public pages)
 *   - accent_hex: brand accent color (used in CTAs / borders)
 *   - watermark_text: text overlay on photo previews
 *   - hide_platform_credits: removes "Powered by …" footer (Studio plan)
 *   - custom_domain: future use — CNAME-style domain mapping (Studio)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photographer_branding', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('photographer_id')->unique();
            $t->string('logo_path')->nullable();
            $t->string('accent_hex', 7)->nullable(); // #RRGGBB
            $t->string('watermark_text')->nullable();
            $t->boolean('watermark_enabled')->default(false);
            $t->boolean('hide_platform_credits')->default(false);
            $t->string('custom_domain')->nullable();
            $t->json('extra')->nullable(); // future expansion (font, etc.)
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photographer_branding');
    }
};
