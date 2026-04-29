<?php

namespace Database\Seeders;

use App\Models\PhotographerPreset;
use Illuminate\Database\Seeder;

/**
 * 8 system-shipped presets.
 *
 * These appear to every photographer in the preset picker (read-only —
 * they can't edit them, but they CAN duplicate-then-edit to make their
 * own variant).
 *
 * The numbers are tuned for a "noticeable but not overdone" look on
 * typical wedding/event/portrait photos. Each preset hits a different
 * use case so the photographer has a starting point for almost any
 * scenario.
 */
class BuiltInPresetsSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            [
                'name'        => 'Natural',
                'description' => 'ปรับเล็กน้อยให้สีดูสด ใช้กับงานทั่วไปได้ทุกแบบ',
                'sort_order'  => 1,
                'settings'    => [
                    'exposure'   => 0.05,
                    'contrast'   => 5,
                    'highlights' => -10,
                    'shadows'    => 10,
                    'vibrance'   => 10,
                    'sharpness'  => 25,
                ],
            ],
            [
                'name'        => 'Vivid',
                'description' => 'สีจัดจ้าน เหมาะกับภาพ event ที่ต้องการความสดใส',
                'sort_order'  => 2,
                'settings'    => [
                    'exposure'   => 0.10,
                    'contrast'   => 20,
                    'highlights' => -20,
                    'shadows'    => 20,
                    'vibrance'   => 35,
                    'saturation' => 10,
                    'clarity'    => 15,
                    'sharpness'  => 35,
                ],
            ],
            [
                'name'        => 'Black & White',
                'description' => 'ขาวดำ contrast สูง — เหมาะกับงาน portrait artistic',
                'sort_order'  => 3,
                'settings'    => [
                    'grayscale'  => true,
                    'contrast'   => 30,
                    'highlights' => -25,
                    'shadows'    => 25,
                    'whites'     => 15,
                    'blacks'     => -15,
                    'clarity'    => 20,
                    'sharpness'  => 30,
                ],
            ],
            [
                'name'        => 'Warm Tones',
                'description' => 'โทนอุ่น สีทอง — เหมาะกับ sunset / golden hour',
                'sort_order'  => 4,
                'settings'    => [
                    'temperature' => 25,
                    'tint'        => 5,
                    'highlights'  => -15,
                    'shadows'     => 15,
                    'vibrance'    => 20,
                    'saturation'  => -5,
                    'clarity'     => 5,
                ],
            ],
            [
                'name'        => 'Cool Tones',
                'description' => 'โทนเย็น สีน้ำเงิน — เหมาะกับ urban / fashion',
                'sort_order'  => 5,
                'settings'    => [
                    'temperature' => -20,
                    'tint'        => -5,
                    'contrast'    => 15,
                    'highlights'  => -15,
                    'shadows'     => 5,
                    'vibrance'    => 10,
                    'saturation'  => -10,
                    'clarity'     => 10,
                ],
            ],
            [
                'name'        => 'Cinematic',
                'description' => 'ภาพยนตร์ — เงาลึก ไฮไลต์เย็น มาตรฐาน social media',
                'sort_order'  => 6,
                'settings'    => [
                    'contrast'    => 25,
                    'highlights'  => -35,
                    'shadows'     => -15,
                    'whites'      => 5,
                    'blacks'      => -25,
                    'vibrance'    => 15,
                    'saturation'  => -15,
                    'temperature' => -10,
                    'clarity'     => 15,
                    'vignette'    => -25,
                ],
            ],
            [
                'name'        => 'Vintage',
                'description' => 'ฟิล์ม retro — สีนุ่ม contrast ต่ำ ขอบเข้ม',
                'sort_order'  => 7,
                'settings'    => [
                    'exposure'    => 0.05,
                    'contrast'    => -15,
                    'highlights'  => -10,
                    'shadows'     => 20,
                    'whites'      => -10,
                    'blacks'      => 10,
                    'vibrance'    => -10,
                    'saturation'  => -20,
                    'temperature' => 15,
                    'tint'        => 10,
                    'vignette'    => -30,
                ],
            ],
            [
                'name'        => 'Portrait',
                'description' => 'สีผิวสวย โทนอุ่นเล็กน้อย — เหมาะกับงานแต่งงาน / family',
                'sort_order'  => 8,
                'settings'    => [
                    'exposure'    => 0.10,
                    'contrast'    => 10,
                    'highlights'  => -20,
                    'shadows'     => 25,
                    'whites'      => 5,
                    'blacks'      => -5,
                    'vibrance'    => 25,
                    'saturation'  => 5,
                    'temperature' => 10,
                    'tint'        => 5,
                    'clarity'     => 10,
                    'sharpness'   => 30,
                ],
            ],
        ];

        foreach ($presets as $p) {
            PhotographerPreset::updateOrCreate(
                ['name' => $p['name'], 'is_system' => true],
                [
                    'photographer_id' => null,
                    'description'     => $p['description'],
                    'settings'        => $p['settings'],
                    'is_system'       => true,
                    'is_active'       => true,
                    'sort_order'      => $p['sort_order'],
                ]
            );
        }

        $this->command?->info('Seeded '.count($presets).' built-in presets.');
    }
}
