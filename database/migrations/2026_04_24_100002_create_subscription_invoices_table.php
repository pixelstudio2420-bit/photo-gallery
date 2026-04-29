<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice ledger — one row per billing period per subscription.
 *
 * Keeps a permanent trail of what was charged, when, for what period. The
 * `order_id` FK links to the generic orders table so we can reuse all
 * existing payment machinery (checkout, webhooks, transactions). An
 * invoice becomes `paid` when the linked order is paid.
 *
 * invoice_number is a human-friendly "SUB-YYMMDD-XXXXXX" string for
 * display and customer service lookups.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('subscription_invoices')) {
            return;
        }

        Schema::create('subscription_invoices', function (Blueprint $t) {
            $t->id();

            $t->unsignedBigInteger('subscription_id')->index();
            $t->unsignedBigInteger('photographer_id')->index();
            $t->unsignedBigInteger('order_id')->nullable()->index();  // orders table FK

            $t->string('invoice_number', 32)->unique();  // SUB-YYMMDD-XXXXXX
            $t->timestamp('period_start')->nullable();
            $t->timestamp('period_end')->nullable();
            $t->decimal('amount_thb', 10, 2);

            $t->string('status', 16)->default('pending')->index();
            // pending | paid | failed | refunded | voided
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->string('failure_reason', 255)->nullable();

            $t->json('meta')->nullable();  // gateway refs, dunning info, etc.

            $t->timestamps();

            $t->foreign('subscription_id')
                ->references('id')->on('photographer_subscriptions')
                ->cascadeOnDelete();

            $t->index(['subscription_id', 'status']);
            $t->index(['photographer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
