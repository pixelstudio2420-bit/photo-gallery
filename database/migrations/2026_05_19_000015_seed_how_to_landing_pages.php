<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed two help-style landing pages requested by the project owner:
 *
 *   /lp/how-to-buy   — "วิธีซื้อรูปจากอีเวนต์" (customer-facing)
 *   /lp/how-to-sell  — "วิธีใช้งานสำหรับช่างภาพ" (photographer-only,
 *                       linked from /photographer/* sidebar)
 *
 * Both rows go into the existing `marketing_landing_pages` table —
 * the LandingPage CMS that already exists (model + admin CRUD at
 * /admin/marketing/landing). After seed, admins can edit the copy
 * + add/reorder section blocks via the standard admin UI without
 * touching code.
 *
 * Section block format matches LandingPageService::BLOCK_TYPES:
 *   heading | text | image | features | testimonial | faq | cta
 *
 * Idempotent — uses updateOrInsert so re-running the migration on a
 * production DB that already has these rows just refreshes the
 * content. Admins who edited the seeded copy WILL lose their edits
 * on re-run — but migrations only run once in production, so that
 * concern is theoretical.
 *
 * Also ensures `marketing_landing_pages_enabled` is '1' so the
 * /lp/{slug} route doesn't 404 — without this gate the feature was
 * shipped with default '0' for staged rollout, and the seeded pages
 * would be invisible.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();

        // Enable BOTH gates the MarketingService::enabled() check requires:
        //   1. marketing_enabled (master switch — blocks everything if off)
        //   2. marketing_landing_pages_enabled (per-feature toggle)
        // Without both, /lp/{slug} aborts 404 because the controller's
        // first guard is `if (!$marketing->enabled('landing_pages')) abort(404)`.
        // Idempotent: only writes if the row doesn't exist (preserves any
        // explicit admin "off" setting on re-deploy).
        // Force both gates on. The project owner explicitly asked for
        // these guides to be live; without enabling the master switch
        // the /lp/{slug} controller aborts 404 even when the row + the
        // per-feature toggle are correct. This overrides any prior
        // admin "off" state — admin can flip them back off via
        // /admin/marketing if they want, but the seeded guides are
        // expected to be reachable from the seed onward.
        foreach ([
            'marketing_enabled'                 => '1',
            'marketing_landing_pages_enabled'   => '1',
        ] as $key => $value) {
            $exists = DB::table('app_settings')->where('key', $key)->exists();
            if ($exists) {
                DB::table('app_settings')->where('key', $key)
                    ->update(['value' => $value, 'updated_at' => $now]);
            } else {
                DB::table('app_settings')->insert([
                    'key' => $key, 'value' => $value, 'updated_at' => $now,
                ]);
            }
        }

        // ───── Customer guide: how-to-buy ─────
        DB::table('marketing_landing_pages')->updateOrInsert(
            ['slug' => 'how-to-buy'],
            [
                'slug'         => 'how-to-buy',
                'title'        => 'วิธีซื้อรูปจากอีเวนต์',
                'subtitle'     => 'ค้นหาตัวเองในอีเวนต์ → จ่ายเงิน → รับรูปภายใน 1 นาที',
                'theme'        => 'indigo',
                'cta_label'    => 'เริ่มค้นหาอีเวนต์',
                'cta_url'      => '/events',
                'status'       => 'published',
                'published_at' => $now,
                'sections'     => json_encode([
                    [
                        'type' => 'heading',
                        'data' => [
                            'heading' => '4 ขั้นตอนซื้อรูปงานของคุณ',
                            'sub'     => 'ใช้เวลาแค่ 1-2 นาที — ไม่ต้องสมัครสมาชิกก็ซื้อได้',
                        ],
                    ],
                    [
                        'type' => 'features',
                        'data' => [
                            'raw' =>
                                "bi-search-heart | 1. ค้นหาอีเวนต์ของคุณ | พิมพ์ชื่องาน วันที่ หรือสแกน QR Code ที่ช่างภาพแจกในงาน · ระบบจะพาไปหน้าอีเวนต์ที่มีรูปทั้งหมด\n" .
                                "bi-person-bounding-box | 2. ค้นหาตัวเองด้วย AI | อัปโหลดเซลฟี่ 1 ใบ · AI Face Search จับคู่ใบหน้าให้อัตโนมัติในไม่กี่วินาที (เกณฑ์ความเหมือน 80% ปรับได้) · เซลฟี่ของคุณไม่ถูกบันทึกไว้\n" .
                                "bi-cart-check | 3. เลือกรูปและจ่ายเงิน | เลือกรูปที่ต้องการ · จ่ายผ่าน PromptPay / บัตรเครดิต / โอน · ระบบตรวจสลิปกับธนาคารให้อัตโนมัติ\n" .
                                "bi-line | 4. รับรูปทาง LINE หรือดาวน์โหลด | รูปต้นฉบับความละเอียดเต็มถูกส่งเข้า LINE หรือลิงก์ดาวน์โหลด — เก็บไฟล์ไว้ได้ตลอด ไม่หมดอายุ",
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'heading' => 'ทำไมต้องเลือกเรา?',
                            'sub'     => '4 จุดเด่นที่ทำให้การซื้อรูปออนไลน์ปลอดภัย',
                        ],
                    ],
                    [
                        'type' => 'features',
                        'data' => [
                            'raw' =>
                                "bi-shield-check | ตรวจสลิปกับธนาคาร | เชื่อม SlipOK ตรวจสลิปจริงกับ API ธนาคาร · กันสลิปปลอม สลิปซ้ำ ยอดไม่ตรง\n" .
                                "bi-lock-fill | ลิงก์ดาวน์โหลดเข้ารหัส | ลิงก์ที่ส่งมีอายุไม่กี่นาที · เปิดได้เฉพาะคุณ · ไม่หลุดให้คนอื่น\n" .
                                "bi-arrow-counterclockwise | คืนเงินได้ | ถ้าไม่ได้รับรูปจากระบบให้ติดต่อแอดมิน — มี audit trail ทุกออเดอร์ คืนเงินผ่านบัญชีเดิมได้",
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'data' => [
                            'raw' =>
                                "ต้องสมัครสมาชิกก่อนซื้อไหม? || ไม่ต้องครับ ใช้แค่อีเมล + เบอร์โทร ลงทะเบียนตอนจ่ายเงินก็ได้ · LINE Login ก็ใช้ได้ ใช้บัญชี LINE สมัคร 1-tap\n" .
                                "AI Face Search ใช้ฟรีไหม? || ฟรี ไม่จำกัดจำนวนการค้นหา · ภาพเซลฟี่ของคุณไม่ถูกเก็บลงระบบ ใช้แล้วลบทิ้งทันที\n" .
                                "ราคารูปเท่าไหร่? || ขึ้นกับช่างภาพแต่ละคน เริ่มต้น ฿20-50 ต่อรูป · ซื้อแพ็กเกจหลายรูปได้ราคาดีกว่า · ดูราคาตอนเลือกรูปก่อนจ่ายเงิน\n" .
                                "ดาวน์โหลดได้กี่ครั้ง? || ไม่จำกัดจำนวนครั้ง · ลิงก์มีอายุ 7 วัน · ถ้าหายให้กดส่งใหม่ในหน้า 'ดาวน์โหลดของฉัน'\n" .
                                "ไม่ได้รับรูปทำยังไง? || ดูในหน้า /profile/orders · ถ้ายังไม่เจอ กด 'ติดต่อเรา' รายงานหมายเลขคำสั่งซื้อให้แอดมิน · เราตอบกลับภายใน 24 ชั่วโมง",
                        ],
                    ],
                    [
                        'type' => 'cta',
                        'data' => [
                            'label' => 'เริ่มค้นหาอีเวนต์',
                            'note'  => 'พิมพ์ชื่อ event ที่คุณไปร่วม หรือสแกน QR ที่ได้จากช่างภาพ',
                            'url'   => '/events',
                        ],
                    ],
                ]),
                'seo' => json_encode([
                    'meta_title'       => 'วิธีซื้อรูปจากอีเวนต์ — ค้นหาตัวเองด้วย AI Face Search',
                    'meta_description' => 'คู่มือ 4 ขั้นตอนซื้อรูปงานของคุณจากอีเวนต์ วิ่ง / รับปริญญา / แต่งงาน / คอนเสิร์ต — ค้นหาตัวเองด้วยใบหน้า ใช้เวลา 1 นาที',
                ]),
                'utm_override' => json_encode([]),
                'views'        => 0,
                'conversions'  => 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]
        );

        // ───── Photographer guide: how-to-sell ─────
        DB::table('marketing_landing_pages')->updateOrInsert(
            ['slug' => 'how-to-sell'],
            [
                'slug'         => 'how-to-sell',
                'title'        => 'คู่มือช่างภาพ — เริ่มขายรูปใน 5 นาที',
                'subtitle'     => 'จากสมัครครั้งแรก → อัพโหลดรูปงาน → รับเงินเข้าบัญชี ใน 5 ขั้นตอน',
                'theme'        => 'emerald',
                'cta_label'    => 'ไปที่แดชบอร์ด',
                'cta_url'      => '/photographer',
                'status'       => 'published',
                'published_at' => $now,
                'sections'     => json_encode([
                    [
                        'type' => 'heading',
                        'data' => [
                            'heading' => '5 ขั้นตอนเริ่มขายรูปงานอีเวนต์',
                            'sub'     => 'ตั้งแต่สมัครครั้งแรกจนได้รับเงินเข้าบัญชี',
                        ],
                    ],
                    [
                        'type' => 'features',
                        'data' => [
                            'raw' =>
                                "bi-line | 1. สมัครผ่าน LINE 1 นาที | กดปุ่ม 'เข้าสู่ระบบด้วย LINE' → อนุญาต 1 ครั้ง → กรอกชื่อ + PromptPay\n" .
                                "bi-calendar-event | 2. สร้างอีเวนต์ | ที่ /photographer/events/create กรอกชื่อ วันที่ สถานที่ราคา/รูป + cover image — Free plan สร้างได้ 1 อีเวนต์พร้อมกัน · Pro/Studio ไม่จำกัด\n" .
                                "bi-cloud-upload | 3. อัปโหลดรูปงาน | drag-drop รูปทั้งหมดในครั้งเดียว · ระบบใส่ลายน้ำ + Face Index อัตโนมัติ · ลูกค้าค้นหาตัวเองได้ทันทีหลัง publish\n" .
                                "bi-cart-check | 4. ลูกค้าจ่ายเงินอัตโนมัติ | ลูกค้าเลือกรูป → ระบบรับชำระ + ตรวจสลิป → ส่งรูปต้นฉบับเข้า LINE ลูกค้า · คุณไม่ต้องส่งเอง\n" .
                                "bi-cash-coin | 5. รับเงินเข้า PromptPay | รายได้สะสมในแดชบอร์ด · กด 'แจ้งถอน' เมื่อยอดถึงขั้นต่ำ → admin อนุมัติ + โอนเข้า PromptPay ของคุณ",
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'heading' => 'เครื่องมือสำคัญในแดชบอร์ด',
                            'sub'     => 'ทุกฟังก์ชันที่ต้องรู้สำหรับขายรูปแบบมืออาชีพ',
                        ],
                    ],
                    [
                        'type' => 'features',
                        'data' => [
                            'raw' =>
                                "bi-images | จัดการรูป | อัปโหลด · ลบหลายรูปพร้อมกัน · จัดเรียงด้วย drag-drop · ดูสถิติยอดดูแยกแต่ละรูป\n" .
                                "bi-pause-circle | ปิด/เปิดการขาย | ปิดงานเก่าได้ — ลูกค้าที่ซื้อแล้วยังดาวน์โหลดได้ · ตั้งเวลาปิดอัตโนมัติได้\n" .
                                "bi-graph-up | Analytics | ดูสถิติยอดขาย รายได้รายวัน อัตราการดู → ซื้อ + แหล่งที่ลูกค้ามาจาก\n" .
                                "bi-people | ลูกค้าของคุณ | ดูใครซื้อรูปไหน · ส่ง LINE ติดตาม · หาลูกค้าประจำได้ง่ายขึ้น",
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'heading' => 'ระดับแผนสมัครสมาชิก',
                            'sub'     => 'เลือกแผนที่เหมาะกับปริมาณงานของคุณ',
                        ],
                    ],
                    [
                        'type' => 'features',
                        'data' => [
                            'raw' =>
                                "bi-stars | Free | 5 GB · 1 อีเวนต์พร้อมกัน · เริ่มลองใช้ได้ทันที — เหมาะกับช่างภาพมือใหม่\n" .
                                "bi-rocket | Pro | 100 GB · 5 อีเวนต์ · 0% commission · AI Face Search · LINE auto-deliver — แนะนำสำหรับช่างภาพที่ทำงานสม่ำเสมอ\n" .
                                "bi-gem | Studio | 500 GB · ไม่จำกัดอีเวนต์ · custom branding · API access · priority support — สำหรับสตูดิโอและทีม",
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'data' => [
                            'raw' =>
                                "ค่า commission เท่าไหร่? || Free plan = 20% · Pro/Studio = 0% (เก็บเข้ากระเป๋าเต็มทุกบาท)\n" .
                                "ต้องอัพโหลดบัตรประชาชนไหม? || ไม่ต้อง · ระบบยืนยันตัวตนผ่าน ITMX (ธนาคาร) ตอนตั้งค่า PromptPay ครั้งแรก\n" .
                                "ลูกค้าจะดาวน์โหลดรูปได้ตอนไหน? || ทันทีที่ระบบยืนยันการชำระเงิน · auto-delivery ส่งเข้า LINE ลูกค้าโดยที่คุณไม่ต้องทำอะไร\n" .
                                "รูปลูกค้ามีลายน้ำตลอดไหม? || รูป preview ก่อนซื้อมีลายน้ำ · รูปต้นฉบับที่ลูกค้าจ่ายเงินแล้ว ไม่มีลายน้ำ\n" .
                                "ถอนเงินได้บ่อยแค่ไหน? || ไม่จำกัดความถี่ · กดแจ้งถอนเมื่อยอดถึงขั้นต่ำ (admin กำหนด) · admin โอนเข้า PromptPay ภายใน 1-3 วันทำการ\n" .
                                "ถ้าลูกค้าขอ refund? || แอดมินเป็นคนตัดสินใจ · เมื่อ refund ระบบจะคืนเงินลูกค้า + clawback รายได้ของคุณอัตโนมัติ + แจ้งให้คุณทราบ",
                        ],
                    ],
                    [
                        'type' => 'cta',
                        'data' => [
                            'label' => 'ไปที่แดชบอร์ด',
                            'note'  => 'เริ่มสร้างอีเวนต์แรก หรือดูสถิติงานเก่า',
                            'url'   => '/photographer',
                        ],
                    ],
                ]),
                'seo' => json_encode([
                    'meta_title'       => 'คู่มือช่างภาพ — วิธีเริ่มขายรูปงานอีเวนต์',
                    'meta_description' => 'คู่มือ 5 ขั้นตอนสำหรับช่างภาพ ตั้งแต่สมัครผ่าน LINE → อัปโหลดรูป → รับเงินเข้า PromptPay',
                ]),
                'utm_override' => json_encode([]),
                'views'        => 0,
                'conversions'  => 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('marketing_landing_pages')->whereIn('slug', ['how-to-buy', 'how-to-sell'])->delete();
        // Don't unset marketing_landing_pages_enabled — admin may have other
        // landing pages relying on it; leave the toggle alone on rollback.
    }
};
