<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add `payment_expires_at` to the orders table so the customer-facing
 * payment pages can render a countdown timer.
 *
 * The countdown is a UX nudge to convert faster — Thai customers often
 * pay within minutes when they see a deadline ticking, vs leaving the
 * tab open for hours and forgetting to come back.
 *
 * Default window is 30 minutes after the order is created (overridable
 * via AppSetting `payment_expiry_minutes`). The Order model's `creating`
 * hook stamps the value at insert time so existing rows are NOT
 * retroactively expired — a backfill below sets the column for any
 * existing pending orders so the dashboard / countdown still has a
 * value to render against (otherwise a customer revisiting a 6-hour-old
 * pending order would see a blank or NaN countdown).
 *
 * Indexed because the abandoned-order sweep (cron) will scan for
 * expired-but-still-pending rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_expires_at')->nullable()->after('status');
            $table->index('payment_expires_at', 'idx_orders_pay_expires');
        });

        // Backfill: any pending order without an expiry gets one 30 mins
        // from now (not 30 mins from created_at, so the customer who
        // refreshes RIGHT NOW gets a usable countdown — the alternative
        // would expire half-hour-old orders immediately).
        DB::table('orders')
            ->whereIn('status', ['pending', 'pending_payment'])
            ->whereNull('payment_expires_at')
            ->update(['payment_expires_at' => now()->addMinutes(30)]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_pay_expires');
            $table->dropColumn('payment_expires_at');
        });
    }
};
