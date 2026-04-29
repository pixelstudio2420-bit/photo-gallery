<?php

namespace Database\Seeders;

use App\Models\DigitalProduct;
use Illuminate\Database\Seeder;

class DigitalProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Lightroom Preset — Warm Wedding',
                'slug' => 'lr-preset-warm-wedding',
                'description' => 'พรีเซ็ต Lightroom โทนอบอุ่นสำหรับงานแต่งงาน ปรับแต่งมาเพื่อโทนผิวคนไทยโดยเฉพาะ ใช้ได้ทั้ง Desktop และ Mobile รองรับทุกสภาพแสง',
                'short_description' => 'พรีเซ็ตโทนอบอุ่นสำหรับงานแต่งงาน',
                'price' => 290,
                'sale_price' => 190,
                'product_type' => 'preset',
                'file_format' => 'XMP, DNG',
                'features' => ['รองรับ Lightroom Desktop & Mobile', '10 พรีเซ็ตในเซ็ต', 'โทนผิวสวยเป็นธรรมชาติ', 'ปรับแต่งง่าย'],
                'is_featured' => true,
            ],
            [
                'name' => 'Lightroom Preset — Film Nostalgia',
                'slug' => 'lr-preset-film-nostalgia',
                'description' => 'พรีเซ็ตโทนฟิล์มย้อนยุค สไตล์ Kodak Portra 400 เหมาะสำหรับสตรีทโฟโต้ งานพอร์ตเทรต และภาพ Lifestyle ทุกประเภท',
                'short_description' => 'พรีเซ็ตโทนฟิล์มย้อนยุค Kodak Portra',
                'price' => 350,
                'sale_price' => null,
                'product_type' => 'preset',
                'file_format' => 'XMP, DNG',
                'features' => ['8 พรีเซ็ตในเซ็ต', 'โทน Kodak Portra 400', 'Grain ฟิล์มเนียนตา', 'รองรับ RAW + JPEG'],
                'is_featured' => false,
            ],
            [
                'name' => 'Photoshop Action — Skin Retouch Pro',
                'slug' => 'ps-action-skin-retouch',
                'description' => 'แอ็คชั่น Photoshop สำหรับรีทัชผิวแบบมืออาชีพ ลบสิว รอยแผลเป็น ปรับผิวเรียบเนียนแต่ยังคงเท็กซ์เจอร์ธรรมชาติ ใช้คลิกเดียวจบ',
                'short_description' => 'รีทัชผิวแบบมืออาชีพ คลิกเดียวจบ',
                'price' => 490,
                'sale_price' => 390,
                'product_type' => 'other',
                'file_format' => 'ATN, ABR',
                'features' => ['รีทัชผิวอัตโนมัติ', 'คงเท็กซ์เจอร์ธรรมชาติ', 'Dodge & Burn ในตัว', 'รองรับ PS CC 2020+'],
                'is_featured' => true,
            ],
            [
                'name' => 'Overlay Pack — Light Leak & Bokeh',
                'slug' => 'overlay-light-leak-bokeh',
                'description' => 'โอเวอร์เลย์ Light Leak และ Bokeh คุณภาพสูง 50 ชิ้น ถ่ายจากเลนส์จริง ความละเอียด 6K ใช้ซ้อนทับในโปรแกรมตัดต่อภาพและวิดีโอ',
                'short_description' => '50 โอเวอร์เลย์ Light Leak & Bokeh 6K',
                'price' => 590,
                'sale_price' => 450,
                'product_type' => 'overlay',
                'file_format' => 'PNG, JPEG',
                'features' => ['50 ไฟล์ความละเอียด 6K', 'ถ่ายจากเลนส์จริง', 'ใช้ได้ทั้งภาพและวิดีโอ', 'Transparent background'],
                'is_featured' => false,
            ],
            [
                'name' => 'LUT Pack — Cinematic Color Grade',
                'slug' => 'lut-cinematic-color',
                'description' => 'ชุด LUT สี Cinematic สำหรับวิดีโอ 20 แบบ สไตล์หนังฮอลลีวูด ใช้ได้กับ Premiere Pro, DaVinci Resolve, Final Cut Pro',
                'short_description' => '20 LUT สี Cinematic สำหรับวิดีโอ',
                'price' => 690,
                'sale_price' => null,
                'product_type' => 'other',
                'file_format' => 'CUBE, 3DL',
                'features' => ['20 LUT สไตล์หนัง', 'รองรับ Premiere, DaVinci, FCP', 'ปรับ Intensity ได้', 'Rec.709 Color Space'],
                'is_featured' => true,
            ],
            [
                'name' => 'eBook — เทคนิคถ่ายภาพงานแต่ง',
                'slug' => 'ebook-wedding-photography',
                'description' => 'หนังสือ eBook 120 หน้า รวมเทคนิคถ่ายภาพงานแต่งงานจากช่างภาพมืออาชีพ ตั้งแต่เตรียมตัว จัดไฟ จนถึงการรีทัช พร้อมภาพตัวอย่างกว่า 200 ภาพ',
                'short_description' => 'คู่มือถ่ายภาพงานแต่ง 120 หน้า',
                'price' => 399,
                'sale_price' => 299,
                'product_type' => 'other',
                'file_format' => 'PDF',
                'features' => ['120 หน้าเต็ม', 'ภาพตัวอย่าง 200+ ภาพ', 'เทคนิคจัดแสง Diagram', 'บทสัมภาษณ์ช่างภาพ 5 คน'],
                'is_featured' => false,
            ],
            [
                'name' => 'Template — Wedding Album PSD',
                'slug' => 'template-wedding-album',
                'description' => 'เทมเพลต Album งานแต่งงาน 30 หน้า ไฟล์ PSD พร้อมเลเยอร์แยก ปรับแต่งได้ทุกองค์ประกอบ ขนาด 12x12 นิ้ว 300 DPI พร้อมส่งพิมพ์',
                'short_description' => 'เทมเพลตอัลบั้มแต่งงาน 30 หน้า PSD',
                'price' => 890,
                'sale_price' => 690,
                'product_type' => 'template',
                'file_format' => 'PSD',
                'features' => ['30 หน้า Layout สวย', '12x12 นิ้ว 300 DPI', 'เลเยอร์แยกทุกชิ้น', 'Smart Object ใส่รูปง่าย'],
                'is_featured' => true,
            ],
            [
                'name' => 'Brush Set — Watercolor Splash',
                'slug' => 'brush-watercolor-splash',
                'description' => 'ชุดแปรง Photoshop ลายสีน้ำ 40 แบบ ความละเอียดสูง 5000px เหมาะสำหรับงาน Composite ตกแต่งกรอบรูป และงานดีไซน์',
                'short_description' => '40 แปรง Photoshop ลายสีน้ำ',
                'price' => 250,
                'sale_price' => null,
                'product_type' => 'other',
                'file_format' => 'ABR',
                'features' => ['40 แปรงความละเอียดสูง', '5000px Brush Size', 'ใช้กับ PS CC 2019+', 'Pressure Sensitivity'],
                'is_featured' => false,
            ],
            [
                'name' => 'Lightroom Preset — Moody Portrait',
                'slug' => 'lr-preset-moody-portrait',
                'description' => 'พรีเซ็ตโทนมู้ดดี้สำหรับถ่ายพอร์ตเทรต สีเข้ม คอนทราสต์สูง เงาโทนฟ้าเขียว ไฮไลท์โทนส้มอุ่น เน้นอารมณ์และบรรยากาศ',
                'short_description' => 'พรีเซ็ตโทนมู้ดดี้สำหรับพอร์ตเทรต',
                'price' => 320,
                'sale_price' => 250,
                'product_type' => 'preset',
                'file_format' => 'XMP, DNG',
                'features' => ['12 พรีเซ็ตในเซ็ต', 'โทน Moody ดราม่า', 'Split Toning สวย', 'Before/After Guide'],
                'is_featured' => false,
            ],
            [
                'name' => 'Video Course — Portrait Lighting Masterclass',
                'slug' => 'course-portrait-lighting',
                'description' => 'คอร์สออนไลน์สอนจัดแสงถ่ายพอร์ตเทรต 8 บทเรียน รวม 4 ชั่วโมง ครอบคลุมแสงธรรมชาติ แฟลชเดี่ยว 2 ดวง และสตูดิโอ พร้อม RAW ไฟล์ฝึกรีทัช',
                'short_description' => 'คอร์สจัดแสงถ่ายพอร์ตเทรต 8 บทเรียน',
                'price' => 1490,
                'sale_price' => 990,
                'product_type' => 'other',
                'file_format' => 'MP4, RAW',
                'features' => ['8 บทเรียน 4 ชั่วโมง', 'แสงธรรมชาติ + สตูดิโอ', 'RAW ไฟล์ฝึกรีทัช 20 ไฟล์', 'Certificate เมื่อจบคอร์ส'],
                'is_featured' => true,
            ],
        ];

        foreach ($products as $i => $p) {
            DigitalProduct::updateOrCreate(
                ['slug' => $p['slug']],
                $p + [
                    'status'               => 'active',
                    'sort_order'           => $i + 1,
                    'total_sales'          => 0,
                    'total_revenue'        => 0,
                    'download_limit'       => 5,
                    'download_expiry_days' => 30,
                ]
            );
        }
    }
}
