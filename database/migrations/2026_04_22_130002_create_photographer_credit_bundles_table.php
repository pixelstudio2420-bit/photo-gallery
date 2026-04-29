<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A "bundle" is a single grant of credits with its own expiry clock.
 *
 * Each purchase / admin grant / free-tier monthly refill creates one
 * bundle row. When a photographer uploads a photo, we consume from the
 * OLDEST bundle first (FIFO by expires_at) so credits about to expire
 * are used before fresher ones. This makes the expiry mechanic feel
 * fair instead of punishing photographers who occasionally overbuy.
 *
 * `credits_remaining` is the only field that changes after issue. All
 * other fields are immutable once set (audit integrity). Expired bundles
 * are marked via ExpireCreditsJob (sets remaining=0, logs ledger entry)
 * rather than deleted, so the history stays queryable.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('photographer_credit_bundles')) {
            return;
        }

        Schema::create('photographer_credit_bundles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('photographer_id');      // auth_users.id (photographer)
            $t->unsignedBigInteger('package_id')->nullable(); // upload_credit_packages.id (null for admin grants/bonuses)
            $t->unsignedBigInteger('order_id')->nullable();   // orders.id when purchased
            $t->string('source', 32)->default('purchase');    // purchase|grant|bonus|subscription_monthly|promo
            $t->unsignedInteger('credits_initial');
            $t->unsignedInteger('credits_remaining');
            $t->decimal('price_paid_thb', 10, 2)->default(0);
            $t->timestamp('expires_at')->nullable();          // null = no expiry
            $t->string('note', 255)->nullable();              // admin/system context
            $t->timestamps();

            $t->index(['photographer_id', 'expires_at'], 'idx_pcb_lookup');
            $t->index('credits_remaining');
            $t->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photographer_credit_bundles');
    }
};
