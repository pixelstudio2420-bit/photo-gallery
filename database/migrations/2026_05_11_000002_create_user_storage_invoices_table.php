<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice ledger for the consumer storage system — one row per billing
 * period per subscription.
 *
 * Parallel to subscription_invoices (photographer side) but tracking
 * end-user cloud-storage charges. Invoice becomes `paid` when its linked
 * order is marked paid by the payment webhook.
 *
 * invoice_number is a "STR-YYMMDD-XXXXXX" string for display.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('user_storage_invoices')) {
            return;
        }

        Schema::create('user_storage_invoices', function (Blueprint $t) {
            $t->id();

            $t->unsignedBigInteger('subscription_id')->index();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('order_id')->nullable()->index();

            $t->string('invoice_number', 32)->unique();       // STR-YYMMDD-XXXXXX
            $t->timestamp('period_start')->nullable();
            $t->timestamp('period_end')->nullable();
            $t->decimal('amount_thb', 10, 2);

            $t->string('status', 16)->default('pending')->index();
            // pending | paid | failed | refunded | voided
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->string('failure_reason', 255)->nullable();

            $t->json('meta')->nullable();

            $t->timestamps();

            $t->foreign('subscription_id')
                ->references('id')->on('user_storage_subscriptions')
                ->cascadeOnDelete();

            $t->index(['subscription_id', 'status']);
            $t->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_storage_invoices');
    }
};
