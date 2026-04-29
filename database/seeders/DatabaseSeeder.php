<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Note: The existing photo_gallery_v2 database already has data.
     * This seeder is for fresh installations only.
     */
    public function run(): void
    {
        $this->call([
            AppSettingsSeeder::class,
            PaymentMethodSeeder::class,
            DefaultAccountsSeeder::class,

            // Production-default catalogue data — pack the system with
            // working examples so new installs look complete on day zero.
            EventCategorySeeder::class,
            PricingPackageSeeder::class,
            CouponSeeder::class,
            DigitalProductSeeder::class,
            BusinessExpenseSeeder::class,
        ]);
    }
}
