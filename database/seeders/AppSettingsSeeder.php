<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'site_name', 'value' => 'Photo Gallery'],
            ['key' => 'site_description', 'value' => 'Professional Event Photography'],
            ['key' => 'site_logo', 'value' => ''],
            ['key' => 'contact_email', 'value' => 'admin@photogallery.com'],
            ['key' => 'contact_phone', 'value' => ''],
            ['key' => 'default_currency', 'value' => 'THB'],
            ['key' => 'watermark_enabled', 'value' => '1'],
            ['key' => 'watermark_text', 'value' => 'Photo Gallery'],
            ['key' => 'max_photo_upload_size', 'value' => '10'],
            ['key' => 'default_language', 'value' => 'th'],
            ['key' => 'photographer_commission_rate', 'value' => '70'],

            // Feature toggles — default OFF for opt-in features so a fresh
            // install ships in a safe minimal state. Admin enables each
            // from /admin/settings → Features.
            ['key' => 'credits_system_enabled', 'value' => '0'],
            ['key' => 'feature_chat_enabled',   'value' => '0'],
        ];

        foreach ($settings as $setting) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $setting['key']],
                ['key' => $setting['key'], 'value' => $setting['value'], 'updated_at' => now()]
            );
        }
    }
}
