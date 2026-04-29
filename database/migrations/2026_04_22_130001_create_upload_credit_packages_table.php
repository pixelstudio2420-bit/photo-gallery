<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog of credit packages photographers can purchase.
 *
 * Admin-managed via the Admin Settings UI. Prices are in THB to keep math
 * predictable for Thai photographers; switch to multi-currency later if
 * we expand internationally.
 *
 * `credits` is the number of upload slots a single purchase grants.
 * `validity_days` is how long those credits last from purchase (365 is
 *   generous enough that bulk-buyers feel safe but still forces occasional
 *   top-up so revenue repeats).
 *
 * `badge` is a small "Popular"/"Best value" ribbon shown in the UI; free
 * text so the marketing team can tweak without a schema change.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('upload_credit_packages')) {
            return;
        }

        Schema::create('upload_credit_packages', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();           // starter, wedding, event, concert
            $t->string('name', 120);                    // "Event 2,000 ภาพ"
            $t->string('description')->nullable();      // short subtitle
            $t->unsignedInteger('credits');             // 2000
            $t->decimal('price_thb', 10, 2);            // 2990.00
            $t->unsignedInteger('validity_days')->default(365);
            $t->string('badge', 40)->nullable();        // Popular | Best value | null
            $t->string('color_hex', 16)->default('#6366f1');  // card accent
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();

            $t->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_credit_packages');
    }
};
