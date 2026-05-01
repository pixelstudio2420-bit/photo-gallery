<?php

namespace Database\Seeders;

use App\Models\SeoPageTemplate;
use Illuminate\Database\Seeder;

/**
 * Seed the default pSEO templates so the dashboard isn't empty on
 * fresh installs. Idempotent — uses updateOrCreate by `type` so re-running
 * refreshes pattern wording without duplicating templates.
 */
class PSeoTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'type'                     => 'location',
                'name'                     => 'หน้าตามจังหวัด (อีเวนต์ในพื้นที่)',
                'is_auto_enabled'          => true,
                'min_data_points'          => 3,
                'title_pattern'            => 'ช่างภาพและอีเวนต์ใน{location} {year} | {brand}',
                'meta_description_pattern' => 'พบช่างภาพ {photographer_count} คน และอีเวนต์ {event_count} งาน ในจังหวัด{location} จองช่างภาพอย่างมั่นใจกับ {brand}',
                'h1_pattern'               => 'ช่างภาพใน{location}',
                'body_template'            => "{brand} รวบรวมช่างภาพมืออาชีพและอีเวนต์ในพื้นที่{location} ให้คุณค้นหาง่าย จองสะดวก ดูผลงานและรีวิวได้ทันที\n\nมีช่างภาพ {photographer_count} คน และอีเวนต์ {event_count} งานพร้อมให้บริการ ครอบคลุมทั้งงานแต่ง งานสัมมนา งานสปอร์ต และอื่นๆ",
                'schema_type'              => 'CollectionPage',
                'linking_config'           => json_encode(['include_related' => true, 'max_links' => 8]),
            ],
            [
                'type'                     => 'category',
                'name'                     => 'หน้าตามประเภท (เช่น wedding-photographers)',
                'is_auto_enabled'          => true,
                'min_data_points'          => 3,
                'title_pattern'            => 'ช่างภาพ{category} {year} — รวมช่างภาพมืออาชีพ | {brand}',
                'meta_description_pattern' => 'จองช่างภาพ{category}จาก {photographer_count} ช่างภาพมืออาชีพ ผลงานจริง รีวิวจากลูกค้า {event_count} งาน เลือกได้ตามใจ',
                'h1_pattern'               => 'ช่างภาพ{category}',
                'body_template'            => "ค้นหาช่างภาพ{category}ที่ตอบโจทย์งานของคุณ — มีช่างภาพมืออาชีพ {photographer_count} คน พร้อมผลงานจริงจาก {event_count} งานให้คุณเลือกชม\n\nดูพอร์ต รีวิว ราคา และจองได้ทันทีบน {brand}",
                'schema_type'              => 'CollectionPage',
                'linking_config'           => json_encode(['include_related' => true, 'max_links' => 8]),
            ],
            [
                'type'                     => 'combo',
                'name'                     => 'หน้า ประเภท × พื้นที่ (highest-traffic)',
                'is_auto_enabled'          => true,
                'min_data_points'          => 2,
                'title_pattern'            => 'ช่างภาพ{category}ใน{location} {year} | {brand}',
                'meta_description_pattern' => 'จองช่างภาพ{category}ใน{location} จาก {photographer_count} ช่างภาพมืออาชีพ ผลงาน {event_count} งาน รีวิวจริง — {brand}',
                'h1_pattern'               => 'ช่างภาพ{category}ใน{location}',
                'body_template'            => "เลือกช่างภาพ{category}ที่{location} — ช่างภาพ {photographer_count} คนผ่านการคัดสรร ผลงานจาก {event_count} งานให้ดูประกอบการตัดสินใจ\n\n{brand} ช่วยคุณค้นหาช่างภาพที่ใช่สำหรับงาน{category}ในพื้นที่{location} ดูราคา ดูรีวิว และจองได้ทันที",
                'schema_type'              => 'CollectionPage',
                'linking_config'           => json_encode(['include_related' => true, 'max_links' => 8]),
            ],
            [
                'type'                     => 'photographer',
                'name'                     => 'หน้าโปรไฟล์ช่างภาพ (enhanced SEO)',
                'is_auto_enabled'          => true,
                'min_data_points'          => 1,
                'title_pattern'            => '{name} — {headline} ใน{location} | {brand}',
                'meta_description_pattern' => '{name} ช่างภาพ{specialties} ประสบการณ์ {experience} ปี ผลงาน {event_count} งาน — จองช่างภาพมืออาชีพบน {brand}',
                'h1_pattern'               => '{name}',
                'body_template'            => "{name} เป็นช่างภาพมืออาชีพใน{location} เชี่ยวชาญ{specialties} มีประสบการณ์ {experience} ปี และผลงานคุณภาพ {event_count} งาน\n\n{bio}\n\nติดต่อจองงานได้ทันทีบน {brand} — มีระบบรีวิว ระบบจ่ายเงินที่ปลอดภัย",
                'schema_type'              => 'Person',
                'linking_config'           => json_encode(['include_related' => true, 'max_links' => 4]),
            ],
            [
                'type'                     => 'event_archive',
                'name'                     => 'หน้ารวมอีเวนต์ทั้งหมด',
                'is_auto_enabled'          => true,
                'min_data_points'          => 5,
                'title_pattern'            => 'รวมอีเวนต์ทั้งหมด {year} — {event_count} งานพร้อมให้บริการ | {brand}',
                'meta_description_pattern' => 'ดูอีเวนต์ทั้งหมด {event_count} งานบน {brand} — งานแต่ง งานสปอร์ต งานสัมมนา งานครบรอบ พร้อมช่างภาพมืออาชีพ',
                'h1_pattern'               => 'อีเวนต์ทั้งหมด {year}',
                'body_template'            => "ดูอีเวนต์ทุกประเภทที่ลงทะเบียนกับ {brand} — รวม {event_count} งานพร้อมช่างภาพและรายละเอียดการจอง",
                'schema_type'              => 'CollectionPage',
                'linking_config'           => json_encode(['include_related' => true, 'max_links' => 12]),
            ],
            [
                'type'                     => 'event',
                'name'                     => 'หน้าต่ออีเวนต์ (per-event landing)',
                'is_auto_enabled'          => true,
                'min_data_points'          => 5, // need at least 5 photos to justify a landing
                'title_pattern'            => '{event_name} {event_date} — รูปงานคุณภาพ {photo_count} รูป | {brand}',
                'meta_description_pattern' => 'ดูรูป{event_name} ถ่ายโดย{photographer} {photo_count} รูปคุณภาพสูง — ค้นหาตัวเองด้วย AI Face Search ได้ทันที',
                'h1_pattern'               => '{event_name}',
                'body_template'            => "ภาพถ่ายจาก{event_name} — {photo_count} รูปคุณภาพสูง ครอบคลุมตลอดงาน\n\n{description}\n\nค้นหารูปของคุณได้ง่ายๆ ด้วย AI Face Search — อัปโหลดรูปเซลฟี่ครั้งเดียว ระบบจะหารูปทั้งหมดของคุณให้",
                'schema_type'              => 'Event',
                'linking_config'           => json_encode(['include_related' => true, 'max_links' => 8]),
            ],
        ];

        foreach ($templates as $tpl) {
            SeoPageTemplate::updateOrCreate(['type' => $tpl['type']], $tpl);
        }

        $this->command?->info('Seeded ' . count($templates) . ' pSEO templates');
    }
}
