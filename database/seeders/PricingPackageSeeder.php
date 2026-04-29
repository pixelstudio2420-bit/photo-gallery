<?php

namespace Database\Seeders;

use App\Models\PricingPackage;
use Illuminate\Database\Seeder;

/**
 * Seeds production-default pricing packages used as TEMPLATES.
 *
 * Packages with `event_id = NULL` are admin-managed global templates — the
 * admin UI copies them into specific events when creating/editing a gallery.
 * Photographers see them on the "apply template" dropdown.
 *
 * Strategy
 * --------
 *   • Photo-count packages cover the typical customer journey:
 *       5  → small share (one keeper)
 *       10 → event highlights
 *       25 → an afternoon's worth
 *       50 → full-day coverage
 *       100 → wedding / multi-day pass
 *   • Prices assume a 130 THB/photo floor and apply a tiered discount:
 *       5 @ 130 = 650 but sells at 590  (-9%)
 *       10 @ 130 = 1,300 → 1,100        (-15%)
 *       25 @ 130 = 3,250 → 2,500        (-23%)
 *       50 @ 130 = 6,500 → 4,500        (-31%)
 *       100 @ 130 = 13,000 → 8,500      (-35%)
 *   • A "Full Event" unlimited-download template at 12,500 THB rounds out the
 *     high-end.
 *
 * Idempotent: runs via `updateOrCreate` keyed on `event_id IS NULL + name`.
 */
class PricingPackageSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name'        => 'เริ่มต้น · 5 ภาพ',
                'photo_count' => 5,
                'price'       => 590.00,
                'description' => 'แพ็กเกจเริ่มต้น เหมาะสำหรับชมภาพและเก็บเป็นที่ระลึก 1-2 ภาพสำคัญ (ประหยัด 9%)',
            ],
            [
                'name'        => 'ไฮไลท์ · 10 ภาพ',
                'photo_count' => 10,
                'price'       => 1100.00,
                'description' => 'รวมภาพไฮไลท์ของงานไว้ให้เลือก 10 ภาพ (ประหยัด 15%)',
            ],
            [
                'name'        => 'ซูมคู่ · 25 ภาพ',
                'photo_count' => 25,
                'price'       => 2500.00,
                'description' => 'เหมาะกับการเก็บภาพงานครึ่งวัน เน้นโมเมนต์สำคัญครบถ้วน (ประหยัด 23%)',
            ],
            [
                'name'        => 'ออลอีเวนต์ · 50 ภาพ',
                'photo_count' => 50,
                'price'       => 4500.00,
                'description' => 'เก็บภาพเต็มวันอย่างครบถ้วน ทุกมุม ทุกบรรยากาศ (ประหยัด 31%)',
            ],
            [
                'name'        => 'พรีเมียม · 100 ภาพ',
                'photo_count' => 100,
                'price'       => 8500.00,
                'description' => 'เหมาะกับงานแต่งงาน งานใหญ่ หรือต้องการรูปเยอะพิเศษ (ประหยัด 35%)',
            ],
            [
                'name'        => 'ฟูลอีเวนต์ · ไม่จำกัด (อัปเกรด)',
                'photo_count' => 9999,
                'price'       => 12500.00,
                'description' => 'รับภาพทั้งหมดจากอีเวนต์โดยไม่จำกัดจำนวน พร้อมไฟล์ต้นฉบับความละเอียดเต็ม',
            ],
        ];

        foreach ($templates as $t) {
            PricingPackage::updateOrCreate(
                [
                    'event_id' => null,
                    'name'     => $t['name'],
                ],
                [
                    'photo_count' => $t['photo_count'],
                    'price'       => $t['price'],
                    'description' => $t['description'],
                    'is_active'   => true,
                ]
            );
        }

        $this->command?->info('✓ Pricing packages seeded: ' . count($templates) . ' global templates.');
    }
}
