<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DefaultAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin (superadmin)
        DB::table('auth_admins')->updateOrInsert(
            ['email' => 'admin@photogallery.com'],
            [
                'email'         => 'admin@photogallery.com',
                'password_hash' => Hash::make('password123'),
                'first_name'    => 'Admin',
                'last_name'     => 'Super',
                'role'          => 'superadmin',
                'permissions'   => null,
                'is_active'     => true,
                'created_at'    => now(),
            ]
        );

        // 2. Customer
        DB::table('auth_users')->updateOrInsert(
            ['email' => 'user@photogallery.com'],
            [
                'email'          => 'user@photogallery.com',
                'password_hash'  => Hash::make('password123'),
                'first_name'     => 'Test',
                'last_name'      => 'Customer',
                'username'       => 'testcustomer',
                'auth_provider'  => 'local',
                'status'         => 'active',
                'email_verified' => true,
                'email_verified_at' => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]
        );

        // 3. Photographer (user + profile)
        DB::table('auth_users')->updateOrInsert(
            ['email' => 'photographer@photogallery.com'],
            [
                'email'          => 'photographer@photogallery.com',
                'password_hash'  => Hash::make('password123'),
                'first_name'     => 'Test',
                'last_name'      => 'Photographer',
                'username'       => 'testphotographer',
                'auth_provider'  => 'local',
                'status'         => 'active',
                'email_verified' => true,
                'email_verified_at' => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]
        );

        $photographerUser = DB::table('auth_users')
            ->where('email', 'photographer@photogallery.com')
            ->first();

        // Only set commission_rate when the row is being CREATED.
        // Once a profile exists, commission_rate is owned by the plan-
        // sync logic in SubscriptionService::syncProfileCache (Free → 80%
        // keep, paid plans → 100% keep). Re-running this seeder must
        // NEVER overwrite the synced value or paid-plan photographers
        // silently lose 20% of their earnings on the next paid order.
        $existingProfile = DB::table('photographer_profiles')
            ->where('user_id', $photographerUser->id)
            ->first();

        $profileData = [
            'user_id'           => $photographerUser->id,
            'photographer_code' => 'PH' . str_pad($photographerUser->id, 4, '0', STR_PAD_LEFT),
            'display_name'      => 'Test Photographer',
            'bio'               => 'Default test photographer account',
            'status'            => 'approved',
            'approved_at'       => now(),
            'updated_at'        => now(),
        ];

        if (!$existingProfile) {
            // Fresh insert — seed default 80% (matches free plan).
            $profileData['commission_rate'] = 80.00;
            $profileData['created_at']      = now();
        }

        DB::table('photographer_profiles')->updateOrInsert(
            ['user_id' => $photographerUser->id],
            $profileData
        );
    }
}
