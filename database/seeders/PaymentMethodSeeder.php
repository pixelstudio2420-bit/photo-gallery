<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'method_name' => 'PromptPay',
                'method_type' => 'promptpay',
                'is_active'   => true,
                'sort_order'  => 1,
            ],
            [
                'method_name' => 'Bank Transfer',
                'method_type' => 'bank_transfer',
                'is_active'   => true,
                'sort_order'  => 2,
            ],
            [
                'method_name' => 'Credit/Debit Card (Stripe)',
                'method_type' => 'stripe',
                'is_active'   => false,
                'sort_order'  => 3,
            ],
            [
                'method_name' => 'Manual Payment',
                'method_type' => 'manual',
                'is_active'   => false,
                'sort_order'  => 4,
            ],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->updateOrInsert(
                ['method_type' => $method['method_type']],
                $method
            );
        }
    }
}
