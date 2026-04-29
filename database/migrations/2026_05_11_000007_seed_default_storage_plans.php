<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the 5 default consumer storage plans plus AppSettings that gate the
 * storage system.
 *
 * Pricing (THB/month) + R2 cost margins at ฿0.55/GB:
 *   Free        ฿0       5 GB      (loss leader — signup magnet)
 *   Personal    ฿79     50 GB      (margin ~45%)
 *   Plus        ฿199   200 GB      (margin ~45%)
 *   Pro         ฿399   500 GB      (margin ~31%)
 *   Max         ฿699   1 TB        (margin ~19%)
 *
 * Intentionally NOT offering a 2TB tier — competitors (Google One ฿350,
 * Dropbox ฿350) are below our break-even at that size. Stay in the
 * niche where our margin math works.
 *
 * Plans are admin-editable post-seed — re-running this migration won't
 * clobber tweaks (idempotent upsert-on-code).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('storage_plans') || !Schema::hasTable('app_settings')) {
            return;
        }

        $gb = 1073741824;      // 1024^3
        $tb = 1099511627776;   // 1024^4
        $mb = 1048576;         // 1024^2
        $now = now();

        $plans = [
            [
                'code'                => 'free',
                'name'                => 'Free — 5 GB',
                'tagline'             => 'สำหรับลองใช้ฟรี',
                'description'         => 'พื้นที่เก็บไฟล์ 5 GB, อัปโหลดไฟล์ได้สูงสุด 100 MB/ไฟล์, แชร์ลิงก์พื้นฐาน',
                'price_thb'           => 0,
                'price_annual_thb'    => null,
                'billing_cycle'       => 'monthly',
                'storage_bytes'       => 5 * $gb,
                'max_file_size_bytes' => 100 * $mb,
                'max_files'           => null,
                'features'            => json_encode(['sharing']),
                'badge'               => null,
                'color_hex'           => '#9ca3af',
                'sort_order'          => 10,
                'features_json'       => json_encode([
                    'พื้นที่เก็บไฟล์ 5 GB',
                    'อัปโหลดสูงสุด 100 MB/ไฟล์',
                    'แชร์ลิงก์พื้นฐาน',
                    'ไม่มีค่าใช้จ่าย',
                ]),
                'is_active'           => 1,
                'is_default_free'     => 1,
                'is_public'           => 1,
            ],
            [
                'code'                => 'personal',
                'name'                => 'Personal — 50 GB',
                'tagline'             => 'สำหรับเก็บรูป-เอกสารส่วนตัว',
                'description'         => 'พื้นที่ 50 GB, อัปโหลดสูงสุด 2 GB/ไฟล์, แชร์ลิงก์มีรหัสผ่าน, ประวัติการเข้าถึง',
                'price_thb'           => 79,
                'price_annual_thb'    => 790,
                'billing_cycle'       => 'monthly',
                'storage_bytes'       => 50 * $gb,
                'max_file_size_bytes' => 2 * $gb,
                'max_files'           => null,
                'features'            => json_encode(['sharing', 'password_links', 'access_logs']),
                'badge'               => null,
                'color_hex'           => '#3b82f6',
                'sort_order'          => 20,
                'features_json'       => json_encode([
                    'พื้นที่เก็บไฟล์ 50 GB',
                    'อัปโหลดสูงสุด 2 GB/ไฟล์',
                    'แชร์ลิงก์พร้อมรหัสผ่าน',
                    'ประวัติการเข้าถึงไฟล์',
                    'ยกเลิกเมื่อไรก็ได้',
                ]),
                'is_active'           => 1,
                'is_default_free'     => 0,
                'is_public'           => 1,
            ],
            [
                'code'                => 'plus',
                'name'                => 'Plus — 200 GB',
                'tagline'             => 'ยอดนิยม — สำหรับครอบครัว/งานอดิเรก',
                'description'         => 'พื้นที่ 200 GB, อัปโหลดสูงสุด 5 GB/ไฟล์, ลิงก์หมดอายุได้, preview ไฟล์ในเว็บ',
                'price_thb'           => 199,
                'price_annual_thb'    => 1990,
                'billing_cycle'       => 'monthly',
                'storage_bytes'       => 200 * $gb,
                'max_file_size_bytes' => 5 * $gb,
                'max_files'           => null,
                'features'            => json_encode([
                    'sharing', 'password_links', 'access_logs',
                    'expiring_links', 'file_preview',
                ]),
                'badge'               => 'ยอดนิยม',
                'color_hex'           => '#6366f1',
                'sort_order'          => 30,
                'features_json'       => json_encode([
                    'พื้นที่เก็บไฟล์ 200 GB',
                    'อัปโหลดสูงสุด 5 GB/ไฟล์',
                    'ลิงก์หมดอายุอัตโนมัติ',
                    'ดูตัวอย่างไฟล์ในเว็บ (PDF/รูป)',
                    'Priority support',
                ]),
                'is_active'           => 1,
                'is_default_free'     => 0,
                'is_public'           => 1,
            ],
            [
                'code'                => 'pro',
                'name'                => 'Pro — 500 GB',
                'tagline'             => 'สำหรับ freelance / ทำงานหนัก',
                'description'         => 'พื้นที่ 500 GB, อัปโหลดสูงสุด 10 GB/ไฟล์, public links ไม่จำกัด, audit log ละเอียด',
                'price_thb'           => 399,
                'price_annual_thb'    => 3990,
                'billing_cycle'       => 'monthly',
                'storage_bytes'       => 500 * $gb,
                'max_file_size_bytes' => 10 * $gb,
                'max_files'           => null,
                'features'            => json_encode([
                    'sharing', 'password_links', 'access_logs',
                    'expiring_links', 'file_preview',
                    'public_links', 'bulk_download', 'advanced_audit',
                ]),
                'badge'               => null,
                'color_hex'           => '#10b981',
                'sort_order'          => 40,
                'features_json'       => json_encode([
                    'พื้นที่เก็บไฟล์ 500 GB',
                    'อัปโหลดสูงสุด 10 GB/ไฟล์',
                    'Public links ไม่จำกัด',
                    'Bulk download (ZIP)',
                    'Audit log ละเอียด',
                    'Priority support (ตอบใน 8 ชม.)',
                ]),
                'is_active'           => 1,
                'is_default_free'     => 0,
                'is_public'           => 1,
            ],
            [
                'code'                => 'max',
                'name'                => 'Max — 1 TB',
                'tagline'             => 'สำหรับมืออาชีพ / เก็บทุกอย่าง',
                'description'         => 'พื้นที่ 1 TB, อัปโหลดสูงสุด 20 GB/ไฟล์, ทุกฟีเจอร์, priority support',
                'price_thb'           => 699,
                'price_annual_thb'    => 6990,
                'billing_cycle'       => 'monthly',
                'storage_bytes'       => (int) $tb,
                'max_file_size_bytes' => 20 * $gb,
                'max_files'           => null,
                'features'            => json_encode([
                    'sharing', 'password_links', 'access_logs',
                    'expiring_links', 'file_preview',
                    'public_links', 'bulk_download', 'advanced_audit',
                    'versioning', 'api_access',
                ]),
                'badge'               => null,
                'color_hex'           => '#f59e0b',
                'sort_order'          => 50,
                'features_json'       => json_encode([
                    'พื้นที่เก็บไฟล์ 1 TB',
                    'อัปโหลดสูงสุด 20 GB/ไฟล์',
                    'File versioning',
                    'API access',
                    'Priority support (ตอบใน 4 ชม.)',
                    'Dedicated account manager (upon request)',
                ]),
                'is_active'           => 1,
                'is_default_free'     => 0,
                'is_public'           => 1,
            ],
        ];

        foreach ($plans as $p) {
            $exists = DB::table('storage_plans')->where('code', $p['code'])->exists();
            if ($exists) continue;
            DB::table('storage_plans')->insert(array_merge($p, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // AppSettings controlling the consumer storage system.
        //
        // Master toggles distinguish the photo-selling marketplace (existing)
        // from the consumer storage business (new). Either can be on/off
        // independently — an admin can run photos-only, storage-only, or both.
        $settings = [
            // Existing photo-selling business stays on by default.
            'sales_mode_photos_enabled' => '1',

            // NEW consumer storage business — OFF by default. Admin flips on
            // once pricing pages / Omise creds / R2 creds are verified live.
            'sales_mode_storage_enabled' => '0',

            // Alias that CheckStorageSystemEnabled middleware reads. Kept
            // separate from sales_mode_storage_enabled so we can put the
            // system in maintenance without changing the "is this business
            // running?" flag in marketing pages.
            'user_storage_enabled' => '0',

            // Default plan assigned on signup (must exist in storage_plans).
            'default_user_storage_plan' => 'free',

            // Grace period (days) after failed renewal before auto-downgrade.
            'user_storage_grace_period_days' => '7',

            // Renewal reminder lead time.
            'user_storage_renewal_reminder_days' => '3',

            // Max renewal retry attempts during grace.
            'user_storage_max_renewal_attempts' => '3',

            // Discount on annual billing (~2 months free).
            'user_storage_annual_discount_pct' => '16.7',

            // Public link policy — admin-overridable cap on public share hits.
            'user_storage_public_link_max_hits' => '0', // 0 = unlimited
        ];

        foreach ($settings as $key => $value) {
            $exists = DB::table('app_settings')->where('key', $key)->exists();
            if ($exists) continue;
            DB::table('app_settings')->insert([
                'key'   => $key,
                'value' => $value,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('storage_plans')) {
            DB::table('storage_plans')
                ->whereIn('code', ['free', 'personal', 'plus', 'pro', 'max'])
                ->delete();
        }

        if (Schema::hasTable('app_settings')) {
            DB::table('app_settings')->whereIn('key', [
                'sales_mode_photos_enabled',
                'sales_mode_storage_enabled',
                'user_storage_enabled',
                'default_user_storage_plan',
                'user_storage_grace_period_days',
                'user_storage_renewal_reminder_days',
                'user_storage_max_renewal_attempts',
                'user_storage_annual_discount_pct',
                'user_storage_public_link_max_hits',
            ])->delete();
        }
    }
};
