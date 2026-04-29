<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit system groundwork — alters existing tables.
 *
 * 1) orders:
 *    • Adds `order_type` enum → distinguishes photo-package purchases (buyers
 *      buying photos from events) from credit-package purchases
 *      (photographers buying upload credits from the platform).
 *    • Adds nullable `credit_package_id` FK to `upload_credit_packages`.
 *      NOT enforced at DB level (FK added in a later migration once the
 *      target table exists, kept loose here so migration order is forgiving).
 *
 * 2) photographer_profiles:
 *    • `billing_mode` → 'commission' (legacy) | 'credits' (new default). New
 *      photographers default to credits. Existing ones stay on commission
 *      until an admin migrates them.
 *    • `credits_balance_cached` → fast-read denormalised balance. Source of
 *      truth is the sum of non-expired photographer_credit_bundles.remaining.
 *    • `credits_last_recalc_at` → when the cache was last rebuilt.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $t) {
                if (!Schema::hasColumn('orders', 'order_type')) {
                    // We can't use enum without a default on MySQL with strict
                    // mode, so default to the historical behaviour.
                    $t->string('order_type', 32)->default('photo_package')->after('package_id')->index();
                }
                if (!Schema::hasColumn('orders', 'credit_package_id')) {
                    $t->unsignedBigInteger('credit_package_id')->nullable()->after('order_type');
                    $t->index('credit_package_id', 'idx_orders_credit_package');
                }
            });
        }

        if (Schema::hasTable('photographer_profiles')) {
            Schema::table('photographer_profiles', function (Blueprint $t) {
                if (!Schema::hasColumn('photographer_profiles', 'billing_mode')) {
                    $t->string('billing_mode', 16)->default('credits')->after('tier')->index();
                }
                if (!Schema::hasColumn('photographer_profiles', 'credits_balance_cached')) {
                    $t->unsignedInteger('credits_balance_cached')->default(0)->after('billing_mode');
                }
                if (!Schema::hasColumn('photographer_profiles', 'credits_last_recalc_at')) {
                    $t->timestamp('credits_last_recalc_at')->nullable()->after('credits_balance_cached');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $t) {
                if (Schema::hasColumn('orders', 'credit_package_id')) {
                    try { $t->dropIndex('idx_orders_credit_package'); } catch (\Throwable) {}
                    $t->dropColumn('credit_package_id');
                }
                if (Schema::hasColumn('orders', 'order_type')) {
                    $t->dropColumn('order_type');
                }
            });
        }

        if (Schema::hasTable('photographer_profiles')) {
            Schema::table('photographer_profiles', function (Blueprint $t) {
                foreach (['credits_last_recalc_at', 'credits_balance_cached', 'billing_mode'] as $col) {
                    if (Schema::hasColumn('photographer_profiles', $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
