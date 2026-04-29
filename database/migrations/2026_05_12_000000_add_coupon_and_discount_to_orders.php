<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wires coupons + referral discounts into the orders table.
 *
 *  - subtotal         : pre-discount item total (so we can show original price)
 *  - discount_amount  : combined discount applied (coupon + referral + loyalty)
 *  - coupon_id        : applied coupon (referral_code_id already exists)
 *  - coupon_code      : denormalised display code (kept even if coupon row is deleted)
 *
 * referral_code_id was added by the marketing system migration (2026_04_25_000000).
 * This migration only fills the coupon side and the shared discount columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'subtotal')) {
                $table->decimal('subtotal', 10, 2)
                    ->default(0)
                    ->after('total')
                    ->comment('Pre-discount item subtotal');
            }

            if (!Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)
                    ->default(0)
                    ->after('subtotal')
                    ->comment('Total discount applied (coupon + referral)');
            }

            if (!Schema::hasColumn('orders', 'coupon_id')) {
                $table->unsignedBigInteger('coupon_id')
                    ->nullable()
                    ->after('discount_amount');
                $table->index('coupon_id', 'orders_coupon_id_idx');
            }

            if (!Schema::hasColumn('orders', 'coupon_code')) {
                $table->string('coupon_code', 64)
                    ->nullable()
                    ->after('coupon_id')
                    ->comment('Denormalised so we keep the code even if coupon is deleted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'coupon_id')) {
                try { $table->dropIndex('orders_coupon_id_idx'); } catch (\Throwable $e) {}
                $table->dropColumn('coupon_id');
            }
            if (Schema::hasColumn('orders', 'coupon_code')) {
                $table->dropColumn('coupon_code');
            }
            if (Schema::hasColumn('orders', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('orders', 'subtotal')) {
                $table->dropColumn('subtotal');
            }
        });
    }
};
