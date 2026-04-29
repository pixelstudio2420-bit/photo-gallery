<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill payment_methods rows for every gateway the admin can
 * enable in /admin/settings/payment-gateways.
 *
 * Why this is needed
 * ──────────────────
 * The admin settings page saves an `xxx_enabled` AppSetting per
 * gateway, but the actual checkout page lists rows from the
 * `payment_methods` table — the two were unsynced.
 *
 * The original PaymentMethodSeeder only seeded four method types
 * (promptpay, bank_transfer, stripe, manual). When an admin enabled
 * Omise / PayPal / LINE Pay / TrueMoney / 2C2P from the settings
 * page, no payment_methods row existed, so:
 *
 *   • /admin/payments/methods → no toggle row to flip
 *   • PaymentMethod::active()->get() at checkout → no entry returned
 *   • Customer never saw the method
 *
 * This migration is the data-fix half of that bug. The companion
 * code change in HandlesIntegrations::updatePaymentGateways() now
 * mirrors the AppSetting toggle into payment_methods.is_active so
 * the two stores stay in lock-step going forward.
 *
 * is_active is set to whatever the matching AppSetting flag already
 * holds (default '0' if unset), so a fresh deploy doesn't suddenly
 * expose unconfigured gateways at checkout. Existing rows are left
 * alone — we only INSERT the missing ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        $methods = [
            // method_type => [display_name, sort_order, enabled_flag_key, default_when_no_flag]
            'promptpay'     => ['PromptPay',                 1,  'promptpay_enabled', true],
            'bank_transfer' => ['โอนผ่านธนาคาร',              2,  null,                true],   // Thai-market default ON
            'stripe'        => ['บัตรเครดิต/เดบิต (Stripe)',     3,  'stripe_enabled',    false],
            'omise'         => ['บัตรเครดิต/เดบิต (Omise)',      4,  'omise_enabled',     false],
            'paypal'        => ['PayPal',                    5,  'paypal_enabled',    false],
            'line_pay'      => ['LINE Pay',                  6,  'line_pay_enabled',  false],
            'truemoney'     => ['TrueMoney Wallet',          7,  'truemoney_enabled', false],
            'two_c_two_p'   => ['2C2P',                      8,  '2c2p_enabled',      false],
            'manual'        => ['ชำระเงินแบบ Manual',          9,  null,                false],  // admin-only, opt-in
        ];

        foreach ($methods as $type => [$name, $sort, $flagKey, $defaultIfNoFlag]) {
            // Read the existing AppSetting toggle so a deploy that's
            // already had the admin enable a gateway in settings keeps
            // that intent. For methods without a flag (bank_transfer,
            // manual) we use the explicit default — those aren't
            // toggleable via the gateway settings page.
            if ($flagKey !== null) {
                $row = DB::table('app_settings')->where('key', $flagKey)->first();
                $isActive = $row && (string) $row->value === '1';
            } else {
                $isActive = $defaultIfNoFlag;
            }

            // Don't overwrite the is_active value of a row that already
            // exists — admins may have hand-toggled it from
            // /admin/payments/methods. Only the rows this migration is
            // creating from scratch get the computed default.
            $existing = DB::table('payment_methods')
                ->where('method_type', $type)
                ->first();

            if ($existing) {
                // Row exists — only patch missing/changed display name.
                // Leave is_active alone so we don't clobber admin choice.
                if ($existing->method_name !== $name && empty($existing->method_name)) {
                    DB::table('payment_methods')
                        ->where('id', $existing->id)
                        ->update(['method_name' => $name]);
                }
                continue;
            }

            DB::table('payment_methods')->insert([
                'method_name' => $name,
                'method_type' => $type,
                'is_active'   => $isActive,
                'sort_order'  => $sort,
            ]);
        }
    }

    public function down(): void
    {
        // Don't delete rows on rollback — operators may have
        // configured them with credentials. A rollback only deletes
        // the rows that were exclusively introduced by THIS migration
        // (the ones not in the original seeder set).
        DB::table('payment_methods')
            ->whereIn('method_type', ['omise', 'paypal', 'line_pay', 'truemoney', 'two_c_two_p'])
            ->delete();
    }
};
