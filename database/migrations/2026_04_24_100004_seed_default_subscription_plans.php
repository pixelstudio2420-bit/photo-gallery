<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the 5 default subscription plans (Free / Starter / Pro / Business / Studio)
 * plus AppSettings that govern the subscription system.
 *
 * Pricing & storage math:
 *   Free       ฿0       2 GB      (portfolio only, 20% commission)
 *   Starter    ฿299    20 GB      (basic AI, 0% commission)
 *   Pro        ฿890   100 GB      (full AI, 0% commission)   ⭐ flagship
 *   Business   ฿2,990 500 GB      (+ team of 3, 0% commission)
 *   Studio     ฿4,990   2 TB      (+ team of 10 + API + white-label)
 *
 * R2 storage + AI (Rekognition) cost margins:
 *   Starter  47% margin | Pro 49% | Business 11% | Studio 30%
 *
 * Plans are admin-editable after seed — this migration only creates rows
 * that don't exist yet (idempotent). Re-running will not clobber admin tweaks.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subscription_plans') || !Schema::hasTable('app_settings')) {
            return;
        }

        $gb = 1073741824; // 1024^3
        $now = now();

        $plans = [
            [
                'code'                  => 'free',
                'name'                  => 'Free — 2 GB',
                'tagline'               => 'สำหรับลองระบบ แสดง portfolio',
                'description'           => 'พื้นที่ทำ portfolio 2 GB, ไม่สามารถเปิดขาย event ได้, AI preview จำกัด 10 รูป/วัน',
                'price_thb'             => 0,
                'price_annual_thb'      => null,
                'billing_cycle'         => 'monthly',
                'storage_bytes'         => 2 * $gb,
                'ai_features'           => json_encode(['ai_preview_limited']),
                'max_concurrent_events' => 0,
                'max_team_seats'        => 1,
                'monthly_ai_credits'    => 50,
                'commission_pct'        => 20,
                'badge'                 => null,
                'color_hex'             => '#9ca3af',
                'sort_order'            => 10,
                'features_json'         => json_encode([
                    'พื้นที่ portfolio 2 GB',
                    'ไม่สามารถเปิดขาย event',
                    'AI preview 10 ภาพ/วัน',
                    'เหมาะสำหรับลองระบบ',
                ]),
                'is_active'             => 1,
                'is_default_free'       => 1,
                'is_public'             => 1,
            ],
            [
                'code'                  => 'starter',
                'name'                  => 'Starter — 20 GB',
                'tagline'               => 'สำหรับงานพาร์ทไทม์ หรือช่างภาพเริ่มต้น',
                'description'           => 'พื้นที่ 20 GB, AI Face Search พื้นฐาน 5,000 ภาพ/เดือน, จัดการ event พร้อมกันได้ 2 งาน',
                'price_thb'             => 299,
                'price_annual_thb'      => 2990,   // 2 เดือนฟรี
                'billing_cycle'         => 'monthly',
                'storage_bytes'         => 20 * $gb,
                'ai_features'           => json_encode([
                    'face_search',
                    'quality_filter',
                    'presets',
                ]),
                'max_concurrent_events' => 2,
                'max_team_seats'        => 1,
                'monthly_ai_credits'    => 5000,
                'commission_pct'        => 0,
                'badge'                 => null,
                'color_hex'             => '#3b82f6',
                'sort_order'            => 20,
                'features_json'         => json_encode([
                    'พื้นที่ทำงาน 20 GB',
                    '0% ค่าคอมมิชชั่น',
                    'AI Face Search (5,000 ภาพ/เดือน)',
                    'AI คัดรูปเบลอพื้นฐาน',
                    'Event พร้อมกัน 2 งาน',
                ]),
                'is_active'             => 1,
                'is_default_free'       => 0,
                'is_public'             => 1,
            ],
            [
                'code'                  => 'pro',
                'name'                  => 'Pro — 100 GB',
                'tagline'               => 'สำหรับช่างภาพ full-time (ขายดีที่สุด)',
                'description'           => 'พื้นที่ 100 GB, AI ครบทุกฟีเจอร์, Event พร้อมกัน 5 งาน, priority upload',
                'price_thb'             => 890,
                'price_annual_thb'      => 8900,   // 2 เดือนฟรี
                'billing_cycle'         => 'monthly',
                'storage_bytes'         => 100 * $gb,
                'ai_features'           => json_encode([
                    'face_search',
                    'quality_filter',
                    'duplicate_detection',
                    'auto_tagging',
                    'best_shot',
                    'priority_upload',
                    'presets',
                ]),
                'max_concurrent_events' => 5,
                'max_team_seats'        => 1,
                'monthly_ai_credits'    => 50000,
                'commission_pct'        => 0,
                'badge'                 => 'ขายดีที่สุด',
                'color_hex'             => '#6366f1',
                'sort_order'            => 30,
                'features_json'         => json_encode([
                    'พื้นที่ทำงาน 100 GB',
                    '0% ค่าคอมมิชชั่น',
                    'AI Face Search ครบ (50,000 ภาพ/เดือน)',
                    'AI คัดรูปซ้ำ + คุณภาพ',
                    'AI Auto-tag (กีฬา/งานแต่ง/คอนเสิร์ต)',
                    'AI Best Shot Recommendation',
                    'Priority upload (เร็วกว่า 2x)',
                    'Event พร้อมกัน 5 งาน',
                    'Analytics ละเอียด',
                ]),
                'is_active'             => 1,
                'is_default_free'       => 0,
                'is_public'             => 1,
            ],
            [
                'code'                  => 'business',
                'name'                  => 'Business — 500 GB',
                'tagline'               => 'สำหรับสตูดิโอเล็ก หรือช่างภาพระดับมืออาชีพ',
                'description'           => 'พื้นที่ 500 GB, AI ไม่จำกัด, ทีม 3 ผู้ใช้, custom branding, priority support',
                'price_thb'             => 2990,
                'price_annual_thb'      => 29900,
                'billing_cycle'         => 'monthly',
                'storage_bytes'         => 500 * $gb,
                'ai_features'           => json_encode([
                    'face_search',
                    'quality_filter',
                    'duplicate_detection',
                    'auto_tagging',
                    'best_shot',
                    'priority_upload',
                    'color_enhance',
                    'customer_analytics',
                    'smart_captions',
                    'custom_branding',
                    'presets',
                ]),
                'max_concurrent_events' => null,
                'max_team_seats'        => 3,
                'monthly_ai_credits'    => 200000,
                'commission_pct'        => 0,
                'badge'                 => null,
                'color_hex'             => '#10b981',
                'sort_order'            => 40,
                'features_json'         => json_encode([
                    'พื้นที่ทำงาน 500 GB',
                    '0% ค่าคอมมิชชั่น',
                    'AI ไม่จำกัด (200,000 ภาพ/เดือน)',
                    'AI Color Enhance',
                    'Customer Behavior Analytics',
                    'Smart Captions หลายภาษา',
                    'ทีม 3 ผู้ใช้',
                    'Custom watermark + branding',
                    'Priority support (ตอบใน 4 ชม.)',
                    'Event ไม่จำกัด',
                ]),
                'is_active'             => 1,
                'is_default_free'       => 0,
                'is_public'             => 1,
            ],
            [
                'code'                  => 'studio',
                'name'                  => 'Studio — 2 TB',
                'tagline'               => 'สำหรับ agency / corporate event',
                'description'           => 'พื้นที่ 2 TB, ทีม 10 ผู้ใช้, API access, white-label, account manager',
                'price_thb'             => 4990,
                'price_annual_thb'      => 49900,
                'billing_cycle'         => 'monthly',
                'storage_bytes'         => 2048 * $gb,
                'ai_features'           => json_encode([
                    'face_search',
                    'quality_filter',
                    'duplicate_detection',
                    'auto_tagging',
                    'best_shot',
                    'priority_upload',
                    'color_enhance',
                    'customer_analytics',
                    'smart_captions',
                    'custom_branding',
                    'video_thumbnails',
                    'api_access',
                    'white_label',
                    'presets',
                ]),
                'max_concurrent_events' => null,
                'max_team_seats'        => 10,
                'monthly_ai_credits'    => 1000000,
                'commission_pct'        => 0,
                'badge'                 => null,
                'color_hex'             => '#f59e0b',
                'sort_order'            => 50,
                'features_json'         => json_encode([
                    'พื้นที่ทำงาน 2 TB (fair use)',
                    '0% ค่าคอมมิชชั่น',
                    'AI ทุกฟีเจอร์ไม่จำกัด',
                    'Video thumbnail extraction',
                    'ทีม 10 ผู้ใช้',
                    'White-label (ซ่อนแบรนด์)',
                    'API access',
                    'Account manager',
                    'SLA guaranteed',
                ]),
                'is_active'             => 1,
                'is_default_free'       => 0,
                'is_public'             => 1,
            ],
        ];

        foreach ($plans as $p) {
            $exists = DB::table('subscription_plans')->where('code', $p['code'])->exists();
            if ($exists) continue;
            DB::table('subscription_plans')->insert(array_merge($p, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // AppSettings controlling the subscription system
        $settings = [
            // Master toggle — off means no plan is enforced; free is open to all.
            'subscriptions_enabled' => '1',

            // Default plan code assigned on signup (must exist in the plans table)
            'default_subscription_plan' => 'free',

            // Grace period (days) after failed renewal before downgrade to free.
            'subscription_grace_period_days' => '7',

            // How many days before current_period_end to notify photographer.
            'subscription_renewal_reminder_days' => '3',

            // Max renewal retry attempts within the grace window.
            'subscription_max_renewal_attempts' => '3',

            // Discount (%) on annual billing vs paying monthly × 12.
            'subscription_annual_discount_pct' => '16.7',   // approx 2 months free

            // Event lifecycle UX: days after event ends that we prompt "delete to reclaim?"
            'subscription_reclaim_prompt_days' => '3',
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
        if (Schema::hasTable('subscription_plans')) {
            DB::table('subscription_plans')
                ->whereIn('code', ['free', 'starter', 'pro', 'business', 'studio'])
                ->delete();
        }

        if (Schema::hasTable('app_settings')) {
            DB::table('app_settings')->whereIn('key', [
                'subscriptions_enabled',
                'default_subscription_plan',
                'subscription_grace_period_days',
                'subscription_renewal_reminder_days',
                'subscription_max_renewal_attempts',
                'subscription_annual_discount_pct',
                'subscription_reclaim_prompt_days',
            ])->delete();
        }
    }
};
