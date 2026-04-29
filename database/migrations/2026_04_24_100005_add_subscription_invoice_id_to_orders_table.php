<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a pointer from orders → subscription_invoices so we can reuse the
 * existing orders/payment_transactions/webhook machinery for subscription
 * billing without creating a parallel payment pipeline.
 *
 * When an order of order_type='subscription' is paid, PaymentWebhookController
 * routes it to SubscriptionService::activateFromPaidInvoice() instead of the
 * photo-delivery or credit-issuance flow.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'subscription_invoice_id')) {
                $t->unsignedBigInteger('subscription_invoice_id')->nullable()->after('credit_package_id');
                $t->index('subscription_invoice_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $t) {
            if (Schema::hasColumn('orders', 'subscription_invoice_id')) {
                try { $t->dropIndex(['orders_subscription_invoice_id_index']); } catch (\Throwable) {}
                $t->dropColumn('subscription_invoice_id');
            }
        });
    }
};
