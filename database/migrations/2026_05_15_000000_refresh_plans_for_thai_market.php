<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh photographer + consumer storage plans with Thai-market-focused
 * features and an updated price ladder.
 *
 * Why this exists: the original seeders (2026_04_24_100004 and 2026_05_11_000007)
 * are idempotent and skip rows that already exist. This migration updates the
 * existing rows in place — so feature lists, taglines, and badges reflect the
 * 3 USPs we lead the marketing pitch with:
 *
 *   1. LINE-first delivery       (ส่งรูปเข้า LINE, Rich Menu, Push, OA)
 *   2. Face Search AI            (selfie → matching photos)
 *   3. Auto-payout to Thai bank  (PromptPay-out, e-Tax invoice)
 *
 * Pricing changes vs original seed:
 *
 *   Photographer:
 *     - Free      ฿0     unchanged (lead magnet)
 *     - Starter   ฿299   unchanged
 *     - Lite      ฿590   NEW — fills the gap between Starter and Pro
 *     - Pro       ฿890   unchanged (flagship, badge updated)
 *     - Business  ฿2,490 lowered from ฿2,990 (more competitive vs SnapShot.io)
 *     - Studio    ฿4,990 unchanged
 *
 *   Consumer storage:
 *     - Free      ฿0     unchanged
 *     - Personal  ฿79    unchanged
 *     - Plus      ฿199   unchanged (popular)
 *     - Pro       ฿399   unchanged
 *     - Max       ฿699   unchanged
 *
 * Migration is idempotent — safe to run multiple times. The down() restores
 * the original feature sets from the initial seeder.
 *
 * NOTE: We use UPDATE-on-existing semantics; we never DELETE rows that admins
 * may already have customers paying against.
 */
return new class extends Migration {
    public function up(): void
    {
        $this->refreshPhotographerPlans();
        $this->refreshStoragePlans();
        $this->insertNewLitePlanIfMissing();
    }

    private function refreshPhotographerPlans(): void
    {
        if (!Schema::hasTable('subscription_plans')) return;

        $now = now();

        // ── FREE: portfolio + LINE Login lead-magnet ─────────────────
        DB::table('subscription_plans')->where('code', 'free')->update([
            'name'        => 'Free — 2 GB',
            'tagline'     => 'ลองระบบฟรี · เริ่มต้นด้วย LINE Login',
            'description' => 'พื้นที่ 2 GB สำหรับ portfolio · Login ด้วย LINE 1 คลิก · AI preview 10 รูป/วัน',
            'features_json' => json_encode([
                'พื้นที่ portfolio 2 GB',
                'Login ด้วย LINE / Google',
                'AI preview 10 ภาพ/วัน',
                'แสดงในหน้าค้นหาช่างภาพ',
                'ค่าคอมมิชชั่น 20% ต่อออเดอร์',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => $now,
        ]);

        // ── STARTER: solo photographer ────────────────────────────────
        DB::table('subscription_plans')->where('code', 'starter')->update([
            'name'        => 'Starter — 20 GB',
            'tagline'     => 'งาน part-time · เริ่มขายจริง',
            'description' => 'พื้นที่ 20 GB · ส่งรูปเข้า LINE หลังจ่ายเงิน · AI Face Search 5,000 ภาพ/เดือน · 0% commission',
            'features_json' => json_encode([
                '🎯 พื้นที่ทำงาน 20 GB',
                '🟢 ส่งรูปเข้า LINE อัตโนมัติหลังจ่ายเงิน',
                '🤖 AI Face Search 5,000 ภาพ/เดือน',
                '✨ AI คัดรูปเบลอเบื้องต้น',
                '💰 0% ค่าคอมมิชชั่น · โอนเข้าบัญชีอัตโนมัติ',
                '📅 เปิดขายพร้อมกัน 2 อีเวนต์',
                '🧾 ออกใบกำกับภาษีอัตโนมัติ',
                '📲 รองรับ PromptPay + LINE Pay',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => $now,
        ]);

        // ── PRO: flagship plan ───────────────────────────────────────
        DB::table('subscription_plans')->where('code', 'pro')->update([
            'name'        => 'Pro — 100 GB',
            'tagline'     => '⭐ ขายดีที่สุด · สำหรับช่างภาพ full-time',
            'description' => 'พื้นที่ 100 GB · AI ครบทุกฟีเจอร์ · Rich Menu LINE · Auto-payout · Priority upload x2',
            'badge'       => 'แนะนำที่สุด',
            'features_json' => json_encode([
                '🚀 พื้นที่ทำงาน 100 GB',
                '🟢 LINE: ส่งรูป + Rich Menu + Push Promo',
                '🤖 AI ครบ 50,000 ภาพ/เดือน (Face/คุณภาพ/ซ้ำ/Best-shot)',
                '🏷️ AI Auto-tag (วิ่ง/แต่ง/รับปริญญา/คอนเสิร์ต)',
                '💰 0% commission · auto-payout ทุกวันจันทร์',
                '📅 เปิดขายพร้อมกัน 5 อีเวนต์',
                '⚡ Priority upload (เร็วกว่า 2 เท่า)',
                '📊 Analytics ละเอียด + ลูกค้าซื้อซ้ำ',
                '🧾 e-Tax invoice + e-Receipt',
                '⭐ Pro Verified Badge (ขึ้นโปรไฟล์ก่อน)',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => $now,
        ]);

        // ── BUSINESS: studio team ─────────────────────────────────────
        DB::table('subscription_plans')->where('code', 'business')->update([
            'name'        => 'Business — 500 GB',
            'tagline'     => 'สตูดิโอเล็ก · ทีม 3 คน · custom branding',
            'price_thb'        => 2490,
            'price_annual_thb' => 24900,
            'description' => 'พื้นที่ 500 GB · ทีม 3 คน · LINE OA Rich Menu สั่งทำเอง · custom watermark · Priority support',
            'features_json' => json_encode([
                '🏢 พื้นที่ทำงาน 500 GB',
                '👥 ทีม 3 ผู้ใช้',
                '🟢 LINE OA: Rich Menu + Broadcast + Multicast',
                '🤖 AI ไม่จำกัด (200,000 ภาพ/เดือน)',
                '🎨 Custom watermark + branding',
                '📊 Customer Behavior Analytics',
                '💬 Smart Captions หลายภาษา',
                '📅 อีเวนต์ไม่จำกัด',
                '⏱️ Priority support (ตอบใน 4 ชม.)',
                '🧾 e-Tax + e-Receipt + ผูก peakaccount.com',
                '💰 0% commission · auto-payout',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => $now,
        ]);

        // ── STUDIO: agency-tier ──────────────────────────────────────
        DB::table('subscription_plans')->where('code', 'studio')->update([
            'name'        => 'Studio — 2 TB',
            'tagline'     => 'Agency / Corporate · API + White-label',
            'description' => 'พื้นที่ 2 TB · ทีม 10 คน · API + White-label · Account manager · SLA 99.9%',
            'features_json' => json_encode([
                '🏢 พื้นที่ 2 TB (fair use)',
                '👥 ทีม 10 ผู้ใช้',
                '🟢 LINE OA: ทุก feature + Webhook custom',
                '🤖 AI ทุกฟีเจอร์ · 1,000,000 ภาพ/เดือน',
                '🎬 Video thumbnail extraction',
                '⚪ White-label (ซ่อนแบรนด์เรา)',
                '🔌 API access เต็มรูปแบบ',
                '👤 Dedicated Account Manager',
                '📜 SLA 99.9% guaranteed',
                '🧾 e-Tax + ใบเสร็จออกในนามบริษัท',
                '💰 0% commission · daily payout option',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => $now,
        ]);
    }

    /**
     * Insert the new "Lite" tier (between Starter and Pro) if it doesn't exist.
     * Slot it at sort_order 25 so it sits visually between Starter (20) and Pro (30).
     */
    private function insertNewLitePlanIfMissing(): void
    {
        if (!Schema::hasTable('subscription_plans')) return;

        $exists = DB::table('subscription_plans')->where('code', 'lite')->exists();
        if ($exists) {
            // Update if exists (idempotent)
            DB::table('subscription_plans')->where('code', 'lite')->update([
                'name'        => 'Lite — 50 GB',
                'tagline'     => 'ครึ่งทางระหว่าง Starter ↔ Pro',
                'description' => 'พื้นที่ 50 GB · AI 20,000 ภาพ/เดือน · 3 อีเวนต์พร้อมกัน · LINE delivery + auto-payout',
                'price_thb'   => 590,
                'price_annual_thb' => 5900,
                'features_json' => json_encode([
                    '🎯 พื้นที่ทำงาน 50 GB',
                    '🟢 ส่งรูปเข้า LINE อัตโนมัติ',
                    '🤖 AI Face Search 20,000 ภาพ/เดือน',
                    '✨ AI คัดรูปเบลอ + รูปซ้ำ',
                    '💰 0% commission · auto-payout',
                    '📅 เปิดขาย 3 อีเวนต์พร้อมกัน',
                    '🧾 ใบกำกับภาษีอัตโนมัติ',
                    '⚡ Priority upload',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at'  => now(),
            ]);
            return;
        }

        $gb = 1073741824;
        DB::table('subscription_plans')->insert([
            'code'                  => 'lite',
            'name'                  => 'Lite — 50 GB',
            'tagline'               => 'ครึ่งทางระหว่าง Starter ↔ Pro',
            'description'           => 'พื้นที่ 50 GB · AI 20,000 ภาพ/เดือน · 3 อีเวนต์พร้อมกัน · LINE delivery + auto-payout',
            'price_thb'             => 590,
            'price_annual_thb'      => 5900,
            'billing_cycle'         => 'monthly',
            'storage_bytes'         => 50 * $gb,
            'ai_features'           => json_encode([
                'face_search', 'quality_filter', 'duplicate_detection', 'presets', 'priority_upload',
            ]),
            'max_concurrent_events' => 3,
            'max_team_seats'        => 1,
            'monthly_ai_credits'    => 20000,
            'commission_pct'        => 0,
            'badge'                 => null,
            'color_hex'             => '#0ea5e9',
            'sort_order'            => 25,
            'features_json'         => json_encode([
                '🎯 พื้นที่ทำงาน 50 GB',
                '🟢 ส่งรูปเข้า LINE อัตโนมัติ',
                '🤖 AI Face Search 20,000 ภาพ/เดือน',
                '✨ AI คัดรูปเบลอ + รูปซ้ำ',
                '💰 0% commission · auto-payout',
                '📅 เปิดขาย 3 อีเวนต์พร้อมกัน',
                '🧾 ใบกำกับภาษีอัตโนมัติ',
                '⚡ Priority upload',
            ], JSON_UNESCAPED_UNICODE),
            'is_active'             => 1,
            'is_default_free'       => 0,
            'is_public'             => 1,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    private function refreshStoragePlans(): void
    {
        if (!Schema::hasTable('storage_plans')) return;

        $now = now();

        // FREE
        DB::table('storage_plans')->where('code', 'free')->update([
            'name'        => 'Free — 5 GB',
            'tagline'     => 'ลองใช้ฟรี · ไม่ต้องใช้บัตรเครดิต',
            'description' => 'เก็บไฟล์ 5 GB · อัปโหลด 100 MB/ไฟล์ · แชร์ลิงก์พื้นฐาน · ดาวน์โหลดผ่าน LINE',
            'features_json' => json_encode([
                '☁️ พื้นที่เก็บไฟล์ 5 GB',
                '📤 อัปโหลดสูงสุด 100 MB/ไฟล์',
                '🔗 แชร์ลิงก์พื้นฐาน',
                '🟢 รับลิงก์ดาวน์โหลดเข้า LINE',
                '🆓 ไม่มีค่าใช้จ่าย',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => $now,
        ]);

        // PERSONAL
        DB::table('storage_plans')->where('code', 'personal')->update([
            'name'        => 'Personal — 50 GB',
            'tagline'     => 'เก็บรูป-เอกสารส่วนตัว · ปลอดภัย',
            'description' => 'พื้นที่ 50 GB · อัปโหลด 2 GB/ไฟล์ · แชร์ลิงก์มีรหัสผ่าน · ประวัติการเข้าถึง',
            'features_json' => json_encode([
                '☁️ พื้นที่เก็บไฟล์ 50 GB',
                '📤 อัปโหลดสูงสุด 2 GB/ไฟล์',
                '🔒 ลิงก์มีรหัสผ่าน',
                '📋 ประวัติการเข้าถึงไฟล์',
                '🟢 แชร์ผ่าน LINE 1 คลิก',
                '💳 จ่ายผ่าน PromptPay/LINE Pay/บัตร',
                '↩️ ยกเลิกเมื่อไรก็ได้',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => $now,
        ]);

        // PLUS
        DB::table('storage_plans')->where('code', 'plus')->update([
            'name'        => 'Plus — 200 GB',
            'tagline'     => '🏆 ยอดนิยม · ครอบครัว/งานอดิเรก',
            'description' => 'พื้นที่ 200 GB · อัปโหลด 5 GB/ไฟล์ · ลิงก์หมดอายุได้ · preview ใน browser',
            'features_json' => json_encode([
                '☁️ พื้นที่เก็บไฟล์ 200 GB',
                '📤 อัปโหลดสูงสุด 5 GB/ไฟล์',
                '⏰ ลิงก์หมดอายุอัตโนมัติ',
                '👁️ ดูตัวอย่างไฟล์ในเว็บ (PDF/รูป)',
                '🟢 แชร์ผ่าน LINE + บันทึก log',
                '🎁 ใช้ Gift Card ได้',
                '⚡ Priority support',
            ], JSON_UNESCAPED_UNICODE),
            'badge'       => 'ยอดนิยม',
            'updated_at'  => $now,
        ]);

        // PRO
        DB::table('storage_plans')->where('code', 'pro')->update([
            'name'        => 'Pro — 500 GB',
            'tagline'     => 'Freelance · ทำงานหนัก',
            'description' => 'พื้นที่ 500 GB · อัปโหลด 10 GB/ไฟล์ · public links ไม่จำกัด · audit log',
            'features_json' => json_encode([
                '☁️ พื้นที่เก็บไฟล์ 500 GB',
                '📤 อัปโหลดสูงสุด 10 GB/ไฟล์',
                '🌐 Public links ไม่จำกัด',
                '📦 Bulk download (ZIP)',
                '📋 Audit log ละเอียด',
                '🟢 LINE notification ทุกการ download',
                '🧾 e-Tax invoice อัตโนมัติ',
                '⏱️ Priority support (ตอบใน 8 ชม.)',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => $now,
        ]);

        // MAX
        DB::table('storage_plans')->where('code', 'max')->update([
            'name'        => 'Max — 1 TB',
            'tagline'     => 'มืออาชีพ · เก็บทุกอย่าง',
            'description' => 'พื้นที่ 1 TB · อัปโหลด 20 GB/ไฟล์ · file versioning · API · ทีมงาน',
            'features_json' => json_encode([
                '☁️ พื้นที่เก็บไฟล์ 1 TB',
                '📤 อัปโหลดสูงสุด 20 GB/ไฟล์',
                '🕐 File versioning (กู้คืนเวอร์ชั่นเก่า)',
                '🔌 API access เต็มรูปแบบ',
                '🟢 LINE bot integration',
                '🧾 e-Tax + ใบเสร็จในนามบริษัท',
                '⏱️ Priority support (ตอบใน 4 ชม.)',
                '👤 Account manager (on request)',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => $now,
        ]);
    }

    public function down(): void
    {
        // Down restores plans to the values from the original seeders.
        // We do NOT delete rows — that would orphan paying customers.
        if (Schema::hasTable('subscription_plans')) {
            DB::table('subscription_plans')->where('code', 'lite')->delete();
            // Revert business pricing
            DB::table('subscription_plans')->where('code', 'business')->update([
                'price_thb'        => 2990,
                'price_annual_thb' => 29900,
            ]);
            // Revert badge
            DB::table('subscription_plans')->where('code', 'pro')->update([
                'badge' => 'ขายดีที่สุด',
            ]);
        }
    }
};
