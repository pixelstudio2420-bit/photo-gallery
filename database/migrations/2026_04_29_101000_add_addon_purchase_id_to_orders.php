<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link addon purchases to their payment Order.
 *
 * Orders that buy a photographer add-on (storage top-up, AI credit pack,
 * promotion boost, branding flag) carry order_type='addon' and a FK to
 * the matching photographer_addon_purchases row. Without this column,
 * the OrderFulfillmentService had no way to find which add-on a paid
 * order should activate, so the V1 store had to skip payment entirely.
 *
 * Mirrors the existing subscription_invoice_id / credit_package_id /
 * user_storage_invoice_id pattern: one nullable FK per order_type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedBigInteger('addon_purchase_id')->nullable()->after('user_storage_invoice_id');
            $t->index('addon_purchase_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['addon_purchase_id']);
            $t->dropColumn('addon_purchase_id');
        });
    }
};
