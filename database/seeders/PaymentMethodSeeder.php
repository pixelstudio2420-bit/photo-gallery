<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        // One row per gateway PaymentService knows how to instantiate.
        // The set must stay in lock-step with PaymentService::createGateway()
        // and PaymentService::ENABLED_FLAG_MAP — adding a new gateway
        // requires:
        //   1. Adding the row here so /admin/payments/methods has a
        //      toggle for it on a fresh install.
        //   2. Mapping the AppSetting flag in
        //      HandlesIntegrations::updatePaymentGateways() so admin
        //      settings sync to is_active.
        //   3. Adding the gateway class + its createGateway() match arm.
        //
        // is_active=true for promptpay + bank_transfer because those
        // ship as the Thai-market default. Everything else starts OFF
        // — admins opt in after pasting credentials in the settings
        // page, and the sync there will flip these rows on for them.
        $methods = [
            ['method_name' => 'PromptPay',                 'method_type' => 'promptpay',     'is_active' => true,  'sort_order' => 1],
            ['method_name' => 'โอนผ่านธนาคาร',              'method_type' => 'bank_transfer', 'is_active' => true,  'sort_order' => 2],
            ['method_name' => 'บัตรเครดิต/เดบิต (Stripe)',     'method_type' => 'stripe',        'is_active' => false, 'sort_order' => 3],
            ['method_name' => 'บัตรเครดิต/เดบิต (Omise)',      'method_type' => 'omise',         'is_active' => false, 'sort_order' => 4],
            ['method_name' => 'PayPal',                    'method_type' => 'paypal',        'is_active' => false, 'sort_order' => 5],
            ['method_name' => 'LINE Pay',                  'method_type' => 'line_pay',      'is_active' => false, 'sort_order' => 6],
            ['method_name' => 'TrueMoney Wallet',          'method_type' => 'truemoney',     'is_active' => false, 'sort_order' => 7],
            ['method_name' => '2C2P',                      'method_type' => 'two_c_two_p',   'is_active' => false, 'sort_order' => 8],
            ['method_name' => 'ชำระเงินแบบ Manual',          'method_type' => 'manual',        'is_active' => false, 'sort_order' => 9],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->updateOrInsert(
                ['method_type' => $method['method_type']],
                $method
            );
        }
    }
}
