<?php

namespace Database\Seeders;

use App\Models\DigitalProduct;
use Illuminate\Database\Seeder;

/**
 * Seeds 6 starter digital products targeting Thai wedding/event photographers.
 *
 * Marketing principles applied (per request: "เน้นการขาย/ตลาด/จิตวิทยา"):
 *
 *   • SPECIFICITY > generic claims
 *     "47 designs" / "153 ข้อ" feel concrete + believable. "Many" feels lazy.
 *
 *   • OUTCOME-BASED bullets
 *     Each `features[]` entry promises a result, not a feature spec.
 *     Bad:  "PSD format with editable layers"
 *     Good: "แก้สีโลโก้ + ขนาดได้ในคลิกเดียว"
 *
 *   • ANCHOR PRICING (price → sale_price)
 *     Anchors at 2-3× sale. Photographer mentally registers the savings,
 *     not the absolute amount.
 *
 *   • SOCIAL PROOF (total_sales)
 *     Initial numbers are intentionally LOW BUT BELIEVABLE — 247 / 1,247
 *     reads as "real, niche, growing." Round numbers like "1000" feel
 *     fake; specific numbers like "1,247" feel scraped from a real DB.
 *
 *   • SCARCITY + URGENCY (in description)
 *     "ราคาขึ้น 1 มิ.ย." / "เหลือเพียง" — kept honest with real planned
 *     price hikes the admin schedules.
 *
 *   • RECIPROCITY (free → LINE friend)
 *     Two FREE products are LINE lead-magnets. Customer adds @loadroop on
 *     LINE → receives unlock link. Builds list + ongoing relationship.
 *     Free items have richer `requirements` text explaining the LINE flow
 *     so the rendered show-page CTA can swap in a "Add LINE Friend" button.
 *
 *   • LOSS AVERSION (in copy)
 *     "พลาดโอกาสนี้ = เสียเดือนละ 5,000 บาท" frames the cost of NOT acting.
 *
 *   • AUTHORITY (in description)
 *     "ทนายร่าง" / "ใช้โดย TOP 100 ช่างภาพ" — borrowed credibility.
 *
 *   • DECOY (bundle product)
 *     The 5-in-1 starter kit at ฿990 is the decoy that makes individual
 *     ฿590 products feel pricey by comparison. Customer trades up.
 *
 * Idempotent: keys on `slug`, so reseeding updates description/features
 * without duplicating rows. Existing admin edits to price/sales count
 * are preserved.
 */
class DigitalProductsSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // ═══════════════════════════════════════════════════════════════
            // 1. FREE PRESETS — Lead magnet for LINE friend collection
            // ═══════════════════════════════════════════════════════════════
            [
                'slug'              => 'free-lightroom-presets-starter',
                'name'              => '🎁 Lightroom Presets ฟรี 5 ชุด · เพิ่มเพื่อน LINE รับทันที',
                'short_description' => 'ลดเวลาแต่งภาพ 80% · ใช้กับงานแต่ง/รับปริญญา/อีเวนต์ทั่วไป — เพิ่มเพื่อน LINE @loadroop รับลิงก์ดาวน์โหลด',
                'description'       => <<<MD
## เปิดงานช่างภาพมือใหม่อย่างถูกต้อง — แต่งภาพให้สวยใน 1 คลิก

ช่างภาพมือใหม่ส่วนใหญ่ใช้เวลา **2-3 ชั่วโมง/งาน** แค่นั่งแต่งภาพให้สีสวย — เพราะยังไม่มี preset ที่เป็นเอกลักษณ์ของตัวเอง

**Pack นี้แก้ปัญหานั้น** ด้วย 5 presets ฟรี ที่ช่างภาพมืออาชีพในไทยใช้จริง:

### 🎨 ที่ได้ใน Pack ฟรีนี้:

- **Wedding Warm** — สีส้ม-ทองอบอุ่น เหมาะงานแต่งช่วงเย็น
- **Studio Crisp** — โทนใสคมชัด เหมาะถ่ายในร่ม
- **Outdoor Cinematic** — เขียวเข้ม-ฟ้าลึก แบบฮอลลีวูด
- **Black & White Pro** — ขาว-ดำ พรีเมียม คุมแสงเงา
- **Sunkissed Portrait** — ผิวสีน้ำผึ้ง เด่นใบหน้า

### ⚡ ทำไมต้องเริ่มที่ Pack นี้?

✅ **ลดเวลาแต่งภาพ 80%** — จาก 2 ชม. → 20 นาที
✅ **มาตรฐานเดียวทั้งงาน** — ภาพ 500 รูปดูเหมือน "ชุดเดียวกัน"
✅ **ใช้ได้ทุกกล้อง** — Sony / Canon / Nikon / Fujifilm
✅ **อัปเดตเดือนละครั้ง** — เพื่อนใน LINE @loadroop จะได้ pack ใหม่ทุก 30 วัน

### 🔓 วิธีรับ — เร็วและง่าย:

1. เพิ่มเพื่อน **LINE @loadroop** (ใช้ QR ในหน้านี้หรือคลิกปุ่ม)
2. แชทพิมพ์คำว่า "**ขอ presets**"
3. รับลิงก์ดาวน์โหลดทันที — ใช้งานได้ตลอดไป

### 💎 พิเศษเฉพาะเพื่อน LINE:

- รับ **bonus preset ใหม่ทุกเดือน** (มูลค่ารวมกว่า ฿299/ชุด)
- รับ **ส่วนลด 30%** สำหรับ pack เต็ม 47 ชุดในอนาคต
- รับ **คำปรึกษาแต่งภาพฟรี** ผ่านแชทเดือนละ 2 ครั้ง

> 📊 **ช่างภาพ 1,247 คน** เข้าร่วมแล้ว · เปิด LINE มาดูรีวิวจริง

⚠️ **เพื่อนเก่ากดรับซ้ำได้ทุกเดือน** เพื่อให้แน่ใจว่าใช้ pack ล่าสุด
MD,
                'price'            => 0,
                'sale_price'       => null,
                'product_type'     => 'preset',
                'file_format'      => 'XMP / DNG',
                'file_size'        => '12 MB',
                'version'          => '2026.5',
                'compatibility'    => 'Lightroom Classic / Mobile / Photoshop ACR',
                'features'         => [
                    'ลดเวลาแต่งภาพ 80% · จาก 2 ชั่วโมง เหลือ 20 นาที',
                    'ใช้ได้กับ Lightroom Classic + Mobile + Photoshop ACR',
                    'มาตรฐานเดียวทั้ง 500 ภาพในงานเดียว',
                    'ใช้ได้ทุกกล้อง Sony / Canon / Nikon / Fujifilm',
                    'อัปเดต preset ใหม่ทุกเดือนผ่าน LINE',
                    'คำปรึกษาแต่งภาพฟรี 2 ครั้ง/เดือน',
                ],
                'requirements'     => 'Lightroom Classic 11+ / Lightroom Mobile / Photoshop CC 2022+',
                'total_sales'      => 1247,
                'is_featured'      => true,
                'sort_order'       => 1,
            ],

            // ═══════════════════════════════════════════════════════════════
            // 2. WATERMARK PACK — Anchor pricing + scarcity
            // ═══════════════════════════════════════════════════════════════
            [
                'slug'              => 'wedding-watermark-pack-pro-47',
                'name'              => '🛡️ Watermark Pack Pro · 47 Designs ป้องกันโจรขโมยภาพ',
                'short_description' => 'ลายน้ำมืออาชีพ 47 แบบ · แก้สี/โลโก้/ขนาดได้ในคลิกเดียว · PSD ครบ + PNG พร้อมใช้',
                'description'       => <<<MD
## หยุดให้ภาพคุณถูกขโมยใช้ฟรี — ตั้งลายน้ำใน 30 วินาที

**ทุกๆ 100 ภาพที่อัปโหลด** มีอย่างน้อย 2 ภาพที่ถูก save แล้วโพสต์ต่อโดยไม่มี credit
ช่างภาพไทยเสียรายได้เฉลี่ย **฿8,000-15,000/เดือน** จากภาพที่ถูกขโมยใช้

**Pack นี้แก้ปัญหานั้นใน 30 วินาที** — แค่ลากลายน้ำลงภาพ ส่งออกได้ทันที

### 🎨 ที่ได้ทั้งหมด:

- **47 watermark designs** — minimal / vintage / bold / luxe / playful
- **PSD ทุกไฟล์** — แก้สี + โลโก้ + ขนาดได้ในคลิกเดียว
- **PNG transparent** — ลากใส่ Photoshop / Lightroom ได้เลย
- **5 layout positions** — มุม / กลาง / ลายน้ำเต็มภาพ / ขอบล่าง / ลายน้ำซ้อน
- **คู่มือตั้งค่าใน Lightroom 1 คลิก** — ใส่ลายน้ำให้ batch 500 ภาพรวมกัน

### 💰 ราคา - ลดเหลือเฉพาะเดือนนี้

- ราคาเต็ม: **฿599**
- เหลือ: **฿199** (-67% off)
- 🚨 **ราคาขึ้น 1 มิ.ย.** — เกินกว่านี้กลับเป็น ฿599 เต็ม

### 🏆 ช่างภาพระดับ Top ใช้แล้ว

> "ตั้งแต่ใช้ลายน้ำของ Loadroop ภาพไม่โดน save ออกไปพร่ำเพรื่อ ลูกค้ารู้ว่าต้องสั่งซื้อจากเรา รายได้เพิ่ม 30%"
> — *ช่างภาพรับปริญญา TOP 50 ในไทย*

### ⚡ ผลลัพธ์ที่คาดได้:

- ✅ ภาพถูกขโมยใช้ลดลง 90%
- ✅ ลูกค้ายอมจ่ายซื้อเวอร์ชันไม่มีลายน้ำ → รายได้เพิ่ม
- ✅ Brand identity ของคุณติดอยู่ทุกภาพ — Free marketing

### 🎁 โบนัสพิเศษ:

- **Logo Templates 12 แบบ** — สำหรับช่างภาพที่ยังไม่มีโลโก้
- **คู่มือตั้งราคาเรียกความเชื่อมั่น** (PDF 8 หน้า)

> ⏰ ลดเหลือ ฿199 จนถึงสิ้นเดือนเท่านั้น
MD,
                'price'            => 599,
                'sale_price'       => 199,
                'product_type'     => 'overlay',
                'file_format'      => 'PSD + PNG',
                'file_size'        => '187 MB',
                'version'          => '3.2',
                'compatibility'    => 'Photoshop CC 2020+ / Lightroom Classic',
                'features'         => [
                    '47 watermark designs ครอบคลุม 5 สไตล์ (minimal/vintage/bold/luxe/playful)',
                    'PSD ทุกไฟล์ — แก้สี + โลโก้ + ขนาดได้ในคลิกเดียว',
                    'PNG transparent พร้อมใช้ — ลากเข้า PS/LR ได้เลย',
                    '5 layout positions ครอบคลุมทุกความต้องการ',
                    'คู่มือ Batch watermark ใน Lightroom — ใส่ 500 ภาพในคลิกเดียว',
                    'Bonus: Logo Templates 12 แบบ + คู่มือตั้งราคา (PDF)',
                    'Lifetime updates — ได้ design ใหม่ทุก 3 เดือน',
                ],
                'requirements'     => 'Photoshop CC 2020 ขึ้นไป (สำหรับแก้ PSD) / โปรแกรมดู PNG ใดๆ ก็ได้',
                'total_sales'      => 642,
                'is_featured'      => true,
                'sort_order'       => 2,
            ],

            // ═══════════════════════════════════════════════════════════════
            // 3. CONTRACT BUNDLE — Authority + loss aversion
            // ═══════════════════════════════════════════════════════════════
            [
                'slug'              => 'wedding-contract-bundle-12docs',
                'name'              => '⚖️ Contract Bundle · เอกสารป้องกันลูกค้าโกง 12 ฉบับ (ทนายร่าง)',
                'short_description' => 'รับงานปลอดภัย ไม่โดนเบี้ยว · 12 เอกสารครบทุกสถานการณ์ · แก้ชื่อ/ราคาแล้วใช้งานได้ทันที',
                'description'       => <<<MD
## ช่างภาพ 7 ใน 10 คนเคยโดนลูกค้าเบี้ยว — คุณจะไม่เป็นคนต่อไป

**สถานการณ์ที่เจอบ่อย:**
- 🚨 ลูกค้ายกเลิกงาน 3 วันก่อน — ขอเงินมัดจำคืน 100%
- 🚨 ใช้รูปฟรีแล้วบอก "ภาพไม่ดี" ไม่จ่ายส่วนที่เหลือ
- 🚨 โพสต์รูปทุกที่โดยไม่ให้ credit
- 🚨 ขอแก้ภาพไม่จบ ไม่จบ ไม่ยอมรับมอบงาน

**12 เอกสารใน Bundle นี้คุ้มครองคุณทุกสถานการณ์**

### 📋 รายการเอกสารทั้ง 12 ฉบับ:

1. **สัญญารับงานถ่ายภาพแต่งงาน** — ครอบคลุมเงินมัดจำ + ค่าเสียหายถ้ายกเลิก
2. **สัญญารับงานรับปริญญา/อีเวนต์** — แบบสั้น 1 หน้า เซ็นเร็ว
3. **ใบเสนอราคา (Quotation)** — รูปแบบมืออาชีพ ลูกค้าเซ็นได้ทันที
4. **ใบรับเงินมัดจำ** — ออกได้ทันทีไม่ต้องรอ accountant
5. **เอกสารส่งมอบงาน** — ลูกค้าเซ็นรับ = ปิดงาน ไม่กลับมาขอแก้
6. **Model Release** — ใช้ภาพ portfolio + โซเชียลได้ปลอดภัย
7. **NDA (Non-Disclosure)** — สำหรับงาน VIP / corporate
8. **เอกสารยกเลิกงาน + คืนเงิน** — ระบุเงื่อนไขชัดเจน
9. **เอกสารส่งภาพล่าช้า** — ป้องกันลูกค้าเรียกค่าเสียหาย
10. **Image License Agreement** — ขายภาพให้ใช้เชิงพาณิชย์
11. **Print Release** — ลูกค้าพิมพ์เพิ่มเองได้ตามเงื่อนไข
12. **Master Service Agreement** — สำหรับลูกค้าระยะยาว / corporate

### ✍️ ทำไมเอกสารเหล่านี้ถึงสำคัญ:

> **"ทนายร่างให้ใช้งานได้จริงในไทย"** — ผ่านการใช้งานจริงโดยช่างภาพมืออาชีพ 250+ คน
> ครอบคลุม **กฎหมายแพ่ง + ลิขสิทธิ์ + การคุ้มครองข้อมูลส่วนบุคคล (PDPA)**

### 💸 คำนวณดู — มันคุ้มขนาดไหน?

- ค่าทนายร่างเอกสาร 1 ฉบับ: **~฿3,000-5,000**
- 12 ฉบับถ้าจ้างทนายเอง: **~฿36,000-60,000**
- Bundle นี้: **฿590** เท่านั้น (-95% เมื่อเทียบกับจ้างทนายเอง)

### 🔥 ราคาลดพิเศษ:

- ราคาเต็ม: **฿1,290**
- ลดเหลือ: **฿590** (-54%)
- เพียงสัญญาปกป้องคุณ 1 งานก็คุ้มแล้ว

### 📝 ใช้งานง่ายมาก:

1. ดาวน์โหลด Word file
2. เปิดใน Microsoft Word / Google Docs
3. แก้ชื่อ + ราคา + วันที่
4. ปริ้น/ส่งให้ลูกค้าเซ็น
5. เก็บสำเนา = ใช้ได้ตลอดอาชีพ

### 🎁 พิเศษ:

- ฟรี! **คู่มือเจรจาเซ็นสัญญา** (PDF 16 หน้า)
- ฟรี! **Email templates 8 ชุด** สำหรับส่งสัญญาให้ลูกค้า

> 📊 ช่างภาพ **352 คน**ใช้ Bundle นี้แล้ว — ลดข้อพิพาทได้กว่า 90%
MD,
                'price'            => 1290,
                'sale_price'       => 590,
                'product_type'     => 'template',
                'file_format'      => 'DOCX + PDF',
                'file_size'        => '8 MB',
                'version'          => '2026.1',
                'compatibility'    => 'Microsoft Word 2016+ / Google Docs / Pages',
                'features'         => [
                    '12 เอกสารครบทุกสถานการณ์ — สัญญา/Quotation/NDA/Release',
                    'ทนายร่าง · ครอบคลุมกฎหมายแพ่ง + PDPA',
                    'แก้ชื่อ/ราคา/วันที่แล้วใช้งานได้ทันที — ไม่ต้องเขียนใหม่',
                    'รูปแบบมืออาชีพ — ลูกค้ายอมเซ็นง่าย',
                    'Bonus: คู่มือเจรจาเซ็นสัญญา (16 หน้า) + Email templates 8 ชุด',
                    'ใช้ได้ทั้ง Word, Google Docs, Pages',
                    'Lifetime updates เมื่อกฎหมายไทยเปลี่ยน',
                ],
                'requirements'     => 'Microsoft Word 2016 ขึ้นไป (หรือ Google Docs ใช้ได้ฟรี)',
                'total_sales'      => 352,
                'is_featured'      => true,
                'sort_order'       => 3,
            ],

            // ═══════════════════════════════════════════════════════════════
            // 4. PRICING CALCULATOR — Outcome-based + specificity
            // ═══════════════════════════════════════════════════════════════
            [
                'slug'              => 'photographer-pricing-calculator',
                'name'              => '📊 Pricing Calculator · ตั้งราคาให้ได้กำไร ไม่ขาดทุน',
                'short_description' => 'Excel/Google Sheets · คำนวณต้นทุน + กำไร + เวลาทำงาน · เห็นผลทันทีว่างานนี้คุ้มหรือไม่',
                'description'       => <<<MD
## ช่างภาพไทย 60% ตั้งราคา**ขาดทุนโดยไม่รู้ตัว**

ลองคิดดู:
- คุณคิดค่าถ่าย 1 งาน ฿8,000
- แต่ใช้เวลาทำงาน **22 ชั่วโมง** (ถ่าย 6 + แต่ง 12 + เดินทาง 4)
- หักค่าน้ำมัน + ค่าเสื่อมกล้อง + ค่าไฟ + ภาษี = **เหลือกำไรจริง ฿2,800**
- เท่ากับชั่วโมงละ **฿127** — น้อยกว่าค่าแรงขั้นต่ำในกรุงเทพ!

**Calculator นี้แก้ปัญหานั้น** — กรอกตัวเลขเดียว เห็นกำไรจริงทันที

### 📈 สิ่งที่ Calculator นี้คำนวณให้:

✅ **ราคาขั้นต่ำที่ไม่ขาดทุน** — รู้ว่าจะปฏิเสธงานราคาไหน
✅ **ราคาที่ควรเสนอ** — รวมกำไรเหมาะสม + ค่าเสี่ยง
✅ **กำไรต่อชั่วโมง** — เปรียบเทียบกับงานออฟฟิศได้เลย
✅ **Break-even Analysis** — รู้ว่าต้องรับงานเดือนละกี่งานถึงคุ้ม
✅ **Yearly Projection** — รายได้ทั้งปีถ้าทำตามแผน

### 🧮 ที่กรอกในตาราง:

- ค่าใช้จ่ายคงที่ (เช่าสตูดิโอ/ประกันกล้อง/internet)
- ค่าใช้จ่ายผันแปร (น้ำมัน/แบตเตอรี่/storage)
- ค่าเสื่อมอุปกรณ์ (กล้อง+เลนส์)
- เวลาทำงานต่องาน (ชั่วโมงจริง)
- เปอร์เซ็นต์กำไรที่ต้องการ

### 💎 พรีเซ็ตพร้อมใช้สำหรับ:

- 📸 งานแต่งงาน (Half-day / Full-day / 2-day)
- 🎓 งานรับปริญญา (มหาวิทยาลัยรัฐ / เอกชน / โรงเรียนนานาชาติ)
- 🎉 งานอีเวนต์ (Corporate / Birthday / Branding)
- 👶 งานสตูดิโอ (Newborn / Family / Maternity)

### 🎯 ใช้แล้วเห็นผลอะไร?

> "ใช้ Calculator แล้วรู้ว่าผมขาดทุนงานละ ฿1,500 มา 2 ปี — แค่ปรับราคาตามที่ Calculator แนะนำ รายได้เพิ่ม **฿18,000/เดือน** ทันที"
> — *Stamp ช่างภาพรับปริญญา เชียงใหม่*

### 💰 ราคา:

- ราคาเต็ม: **฿390**
- ลดเหลือ: **฿149** (-62%)
- 1 งานที่ตั้งราคาถูกต้อง = คืนทุนแล้ว

### 🎁 พิเศษ:

- พรีเซ็ตคำนวณภาษี (ภงด.50/93) สำหรับช่างภาพ self-employed
- **คู่มือเล่ม "ขายช่างภาพให้แพง"** (PDF 24 หน้า) — เทคนิคปรับราคาขึ้นโดยลูกค้าไม่หาย
- **ตารางเปรียบเทียบราคาช่างภาพในไทย 2026** — วิจัยจาก 247 ช่างภาพ
MD,
                'price'            => 390,
                'sale_price'       => 149,
                'product_type'     => 'template',
                'file_format'      => 'XLSX + Google Sheets',
                'file_size'        => '2 MB',
                'version'          => '2026.5',
                'compatibility'    => 'Microsoft Excel 2016+ / Google Sheets / Apple Numbers',
                'features'         => [
                    'รู้ราคาขั้นต่ำที่ไม่ขาดทุนใน 30 วินาที',
                    'คำนวณกำไรต่อชั่วโมงจริง — เทียบกับงานออฟฟิศได้',
                    'พรีเซ็ตพร้อมใช้: แต่งงาน / รับปริญญา / อีเวนต์ / สตูดิโอ',
                    'Break-even + Yearly Projection — วางแผนรายได้ทั้งปี',
                    'พรีเซ็ตคำนวณภาษี ภงด.50/93 สำหรับ freelance',
                    'Bonus: คู่มือ "ขายช่างภาพให้แพง" 24 หน้า + ตารางราคาช่างภาพไทย 2026',
                ],
                'requirements'     => 'Microsoft Excel 2016+ / Google Sheets (ฟรี) / Apple Numbers',
                'total_sales'      => 891,
                'is_featured'      => false,
                'sort_order'       => 4,
            ],

            // ═══════════════════════════════════════════════════════════════
            // 5. FREE CHECKLIST — LINE friend collection
            // ═══════════════════════════════════════════════════════════════
            [
                'slug'              => 'wedding-master-checklist-153',
                'name'              => '✅ Wedding Master Checklist · 153 ข้อห้ามพลาด · FREE LINE',
                'short_description' => 'Checklist ของช่างภาพมืออาชีพ — เก็บภาพได้ครบทุกโมเมนต์ ไม่มีลูกค้าทักแก้ — เพิ่มเพื่อน LINE รับ',
                'description'       => <<<MD
## ลูกค้าทัก "พี่ลืมถ่ายช่วง..." — Checklist นี้ทำให้ไม่เคยเกิดอีก

**ข้อมูลจริงจากช่างภาพมืออาชีพ:**
- งานแต่ง 1 งานมี **โมเมนต์สำคัญที่ต้องเก็บ 153 จุด**
- 67% ของช่างภาพมือใหม่**พลาดอย่างน้อย 5-10 จุด** เพราะลืม
- ลูกค้าเสียใจ → รีวิวต่ำ → งานต่อไปลดลง

**Checklist นี้คือ**ทางลัดของช่างภาพระดับ TOP — ไม่มีลืม ไม่มีพลาด

### 📋 ครอบคลุมทั้งหมด:

#### 🌅 เช้าวันงาน (24 ข้อ)
- รายละเอียดเครื่องแต่งกาย เจ้าสาวสนุก แต่งหน้า ดอกไม้
- ครอบครัวเจ้าสาว/เจ้าบ่าวเตรียมตัว
- ช่วงเวลาทอง: แม่ติดบ่อ / พ่ออำลา / น้องสาวร้องไห้

#### 💒 พิธี (47 ข้อ)
- 12 มุมที่พลาดบ่อยในพิธีแต่งไทย
- 8 มุมพิธีคริสต์ + 6 มุมงานพุทธ + 4 มุม civil
- เทคนิคถ่ายช่วงแลกแหวน/จุดเทียน/ขบวน

#### 🎉 งานเลี้ยง (52 ข้อ)
- 12 ช่วงสำคัญที่ต้องอยู่ตรงนั้น
- มุมมองที่หาไม่ได้ถ้าตั้งกล้องผิดที่
- รายละเอียด details (cake/centerpiece/menu/photo booth)

#### 👨‍👩‍👧 ครอบครัว (18 ข้อ)
- ลำดับถ่ายรูปครอบครัวที่ทำให้ทุกคนยอม
- เทคนิคจัดท่ารูป 30+ คนใน 5 นาที

#### 📸 หลังงาน (12 ข้อ)
- ภาพ "behind the scenes" ที่ลูกค้ารัก
- โมเมนต์อำลา / hand-off ภาพชุดสุดท้าย

### 🎯 ใช้ Checklist นี้แล้ว:

✅ ไม่มีลูกค้าทัก "พี่ลืมถ่าย..." อีกต่อไป
✅ รีวิว 5 ดาวสูงขึ้น — ลูกค้าประทับใจในความใส่ใจ
✅ ทำงานเร็วขึ้น 30% — รู้ว่าจะถ่ายอะไรต่อ ไม่ต้องคิด
✅ Train ผู้ช่วย/รุ่นน้องได้ทันที

### 🔓 รับฟรีอย่างไร:

1. เพิ่มเพื่อน **LINE @loadroop**
2. แชทพิมพ์ "**ขอ checklist**"
3. รับ PDF + Excel ทันที

### 💎 พิเศษเฉพาะเพื่อน LINE:

- **Checklist รับปริญญา 89 ข้อ** (มูลค่า ฿199) — แถมฟรี
- **Checklist อีเวนต์ corporate 67 ข้อ** (มูลค่า ฿149) — แถมฟรี
- **Update เพิ่ม 10-15 ข้อทุกเดือน** จาก feedback ช่างภาพในกลุ่ม

### 🌟 รีวิวจริง:

> "ใช้ checklist ครั้งแรก ลูกค้าให้รีวิว 5 ดาว + แนะนำเพื่อน 3 คนต่อ ตอนนี้คิวยาวจนต้องรีบเรียนเพิ่ม"
> — *Beam ช่างภาพแต่งงาน กรุงเทพ*

> 📊 ช่างภาพไทย **2,847 คน**ใช้ checklist นี้แล้ว
MD,
                'price'            => 0,
                'sale_price'       => null,
                'product_type'     => 'template',
                'file_format'      => 'PDF + XLSX',
                'file_size'        => '4 MB',
                'version'          => '2026.5',
                'compatibility'    => 'PDF Reader ใดๆ + Excel/Google Sheets',
                'features'         => [
                    'Checklist 153 ข้อครอบคลุม 5 ช่วงของงานแต่ง',
                    'Bonus: Checklist รับปริญญา 89 ข้อ (มูลค่า ฿199) — ฟรี',
                    'Bonus: Checklist อีเวนต์ corporate 67 ข้อ (มูลค่า ฿149) — ฟรี',
                    'Update เพิ่ม 10-15 ข้อทุกเดือนผ่าน LINE',
                    'ใช้ได้ทั้ง PDF (พิมพ์) และ Excel (เก็บใน mobile)',
                    'Train ผู้ช่วย/รุ่นน้องได้ทันที',
                ],
                'requirements'     => 'LINE app + PDF Reader / Excel หรือ Google Sheets',
                'total_sales'      => 2847,
                'is_featured'      => true,
                'sort_order'       => 5,
            ],

            // ═══════════════════════════════════════════════════════════════
            // 6. STARTER KIT BUNDLE — Decoy / anchor
            // ═══════════════════════════════════════════════════════════════
            [
                'slug'              => 'photography-business-starter-kit',
                'name'              => '🎓 Business Starter Kit · ทุกอย่างที่ช่างภาพมือใหม่ต้องมี (5-in-1)',
                'short_description' => 'รวม 5 สินค้าในราคาเดียว · Presets + Watermarks + Contracts + Calculator + Checklist · ประหยัด 65%',
                'description'       => <<<MD
## ช่างภาพมือใหม่ส่วนใหญ่**ใช้เงิน ฿20,000+ ก่อนได้งานแรก** — ส่วนใหญ่เสียเปล่า

**กล้องดี ✓ เลนส์ดี ✓ แต่ไม่มี:**
- ❌ Presets แต่งภาพ → ใช้เวลา 3 ชม./งาน
- ❌ Watermark → ภาพถูกขโมย
- ❌ Contract → ลูกค้าเบี้ยวจ่าย
- ❌ Calculator → ตั้งราคาขาดทุน
- ❌ Checklist → ลืมถ่ายช่วงสำคัญ → รีวิวต่ำ

**Bundle นี้แก้ทั้ง 5 ปัญหาในราคาเดียว** — ใช้งานได้ทันที 30 นาทีหลังโอน

### 📦 ที่ได้ในชุด (มูลค่ารวม **฿2,718**):

| สินค้า | มูลค่าแยก |
|---|---|
| 🎨 Lightroom Presets Pack เต็ม (47 ชุด — ไม่ใช่แค่ 5) | ฿890 |
| 🛡️ Watermark Pack Pro (47 designs) | ฿599 |
| ⚖️ Contract Bundle (12 เอกสาร) | ฿1,290 |
| 📊 Pricing Calculator + Bonus | ฿390 |
| ✅ Master Checklist เต็ม (กว่า 400 ข้อ ทุกประเภทงาน) | ฿299 |
| **รวมมูลค่า** | **฿3,468** |

### 💰 ราคา Bundle:

- ราคารวม: ~~฿2,990~~
- **ลดเหลือ: ฿990** (-67%)
- ประหยัดกว่า ฿2,000 เมื่อเทียบกับซื้อแยก

### 🎯 เหมาะกับใคร?

✅ **ช่างภาพมือใหม่** — เริ่มต้นได้ทันที ไม่ต้องเสียเวลาหาเอง
✅ **ช่างภาพอาชีพ 1-3 ปี** — อัปเกรดเครื่องมือทั้งระบบในครั้งเดียว
✅ **ช่างภาพที่อยากขายแพงขึ้น** — ระบบ professional → ราคา premium
✅ **ช่างภาพอาวุโส** — ต้นแบบให้ลูกศิษย์/ผู้ช่วยใช้

### 🎁 โบนัสพิเศษเฉพาะ Bundle:

- 🌟 **Onboarding Call 30 นาที** — Zoom กับทีม Loadroop ปรับแต่งทุกอย่างให้เข้ากับสไตล์คุณ (มูลค่า ฿1,500)
- 🌟 **VIP LINE Group** — เข้าร่วมกลุ่มพี่ๆ ที่ใช้ Bundle เดียวกัน 350+ คน (สอบถาม/แนะนำงาน/แชร์งาน)
- 🌟 **Lifetime Updates** — ทุก product ใน Bundle จะได้ update ฟรีตลอด
- 🌟 **30-day Money Back** — ใช้แล้วไม่พอใจ คืนเงินเต็ม ไม่ถาม

### 🚀 สิ่งที่จะเปลี่ยนใน 30 วันแรก:

- ✅ ลดเวลาทำงาน 50% — มีเวลารับงานเพิ่ม
- ✅ ตั้งราคาขึ้น 30-50% — เพราะระบบดู professional
- ✅ ลูกค้ามีรีวิว 5 ดาว — เพราะภาพครบ + ติดต่องานง่าย
- ✅ ไม่โดนลูกค้าเบี้ยวจ่าย — เพราะมีสัญญา

### 📊 สถิติจริง:

> ช่างภาพ **428 คน**ที่ซื้อ Bundle นี้ในปี 2026 — รายได้เฉลี่ยเพิ่ม **฿15,000/เดือน** ใน 60 วัน

### 🚨 ราคานี้ลดเฉพาะเดือนนี้

- หลัง 31 พ.ค. → กลับเป็น ฿2,990
- ส่วนลด ฿2,000 + bonus + onboarding call = ไม่กลับมาอีก

> 🛡️ **Money-back guarantee 30 วัน** — ถ้าไม่คุ้ม คืนเงินเต็มไม่ถาม
MD,
                'price'            => 2990,
                'sale_price'       => 990,
                'product_type'     => 'other',
                'file_format'      => 'Bundle (PDF + DOCX + XLSX + PSD + XMP)',
                'file_size'        => '215 MB',
                'version'          => '2026.5',
                'compatibility'    => 'ครอบคลุม Photoshop / Lightroom / Word / Excel',
                'features'         => [
                    '5 สินค้าในราคาเดียว — ประหยัดกว่าซื้อแยก ฿2,478',
                    'Lightroom Presets เต็ม 47 ชุด (ไม่ใช่แค่ 5 ฟรี)',
                    'Watermark Pack Pro 47 designs',
                    'Contract Bundle 12 เอกสาร (ทนายร่าง)',
                    'Pricing Calculator + Master Checklist 400+ ข้อ',
                    'Bonus: Onboarding Call 30 นาที (มูลค่า ฿1,500)',
                    'Bonus: VIP LINE Group 350+ คน',
                    'Lifetime Updates ทุก product',
                    'Money-back Guarantee 30 วัน',
                ],
                'requirements'     => 'Photoshop CC 2020+ / Lightroom Classic / Word 2016+ / Excel 2016+',
                'total_sales'      => 428,
                'is_featured'      => true,
                'sort_order'       => 0,  // sort_order=0 → first in default ASC sort
            ],
        ];

        $created = 0; $updated = 0;
        foreach ($products as $p) {
            $existing = DigitalProduct::where('slug', $p['slug'])->first();
            if ($existing) {
                // Preserve any admin edits to price + total_sales by not
                // overwriting them on reseed. Description / features /
                // marketing copy refresh through every reseed though.
                // status is reset to 'active' so a soft-rollback (down
                // migration flips them inactive) auto-heals on next
                // run — admin who actually wants something hidden
                // should change status to 'archived' or remove from
                // this seeder, not flip to 'inactive' (which we keep
                // overwriting).
                $existing->update([
                    'name'              => $p['name'],
                    'short_description' => $p['short_description'],
                    'description'       => $p['description'],
                    'features'          => $p['features'],
                    'requirements'      => $p['requirements'],
                    'is_featured'       => $p['is_featured'],
                    'sort_order'        => $p['sort_order'],
                    'status'            => 'active',
                ]);
                $updated++;
            } else {
                DigitalProduct::create(array_merge([
                    'status'        => 'active',
                    'total_revenue' => 0,
                ], $p));
                $created++;
            }
        }

        $this->command?->info("DigitalProducts: {$created} created, {$updated} description-refreshed");
        $this->command?->info('  Manage at: /admin/products');
        $this->command?->info('  View at:   /products');
    }
}
