<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a pointer from orders → user_storage_invoices so the existing
 * orders / payment_transactions / webhook pipeline can process consumer
 * storage purchases without a parallel payment path.
 *
 * When a paid order has order_type='user_storage_subscription',
 * OrderFulfillmentService dispatches it to UserStorageService::activateFromPaidInvoice().
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'user_storage_invoice_id')) {
                $t->unsignedBigInteger('user_storage_invoice_id')->nullable()->after('subscription_invoice_id');
                $t->index('user_storage_invoice_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $t) {
            if (Schema::hasColumn('orders', 'user_storage_invoice_id')) {
                try { $t->dropIndex(['orders_user_storage_invoice_id_index']); } catch (\Throwable) {}
                $t->dropColumn('user_storage_invoice_id');
            }
        });
    }
};
