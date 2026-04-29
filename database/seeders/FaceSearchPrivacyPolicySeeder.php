<?php

namespace Database\Seeders;

use App\Models\LegalPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Insert (or refresh) the PDPA biometric-data privacy page.
 *
 * Required by PDPA B.E. 2562 §26:
 *   Biometric data (including facial images used for identification) is
 *   "sensitive personal data" — processing it requires explicit, informed
 *   consent that discloses:
 *     • What is processed            (a selfie uploaded by the user)
 *     • For what purpose             (match against event photos)
 *     • How long it is retained      (only for the duration of the request)
 *     • Who it's shared with         (AWS Rekognition — data processor)
 *     • The user's rights            (withdraw consent, request deletion)
 *
 * Idempotent: re-running just refreshes content + bumps `version` + snapshots
 * the previous body into `legal_page_versions` for the audit trail.
 */
class FaceSearchPrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        $slug = 'biometric-data-privacy';

        $content = <<<HTML
<div class="prose prose-slate dark:prose-invert max-w-none">
  <h2>นโยบายความเป็นส่วนตัวสำหรับข้อมูลชีวภาพ (ใบหน้า)</h2>
  <p class="text-sm text-gray-500">ตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 (PDPA) มาตรา 26</p>

  <h3>1. ข้อมูลที่เราประมวลผล</h3>
  <p>
    เมื่อท่านใช้งานฟีเจอร์ "ค้นหาด้วยใบหน้า" ท่านจะอัปโหลดรูปถ่ายตนเอง (selfie) เพื่อให้ระบบค้นหารูปของท่าน
    จากอัลบั้มงานที่เลือก รูปเซลฟี่ดังกล่าวจัดเป็น <strong>ข้อมูลชีวภาพ (biometric data)</strong>
    ซึ่งเป็นข้อมูลส่วนบุคคลที่มีความอ่อนไหวตามกฎหมาย
  </p>

  <h3>2. วัตถุประสงค์ของการประมวลผล</h3>
  <ul>
    <li>เพื่อจับคู่ใบหน้าของท่านกับรูปภาพภายในอัลบั้มงานที่ท่านเข้าร่วม</li>
    <li>เพื่อแสดงผลรูปที่ค้นพบให้ท่านบันทึกหรือสั่งซื้อ</li>
    <li>ไม่ใช้เพื่อการระบุตัวตน (authentication), การเฝ้าระวัง, การโฆษณา, หรือการขายต่อ</li>
  </ul>

  <h3>3. ฐานทางกฎหมาย</h3>
  <p>
    การประมวลผลนี้ดำเนินการภายใต้ฐาน <strong>ความยินยอมโดยชัดแจ้ง (explicit consent)</strong>
    ตามมาตรา 26(1) ท่านต้องกดยืนยันการยินยอมก่อนอัปโหลดรูปเซลฟี่ทุกครั้ง
    การใช้งานจะไม่ดำเนินการหากไม่ได้รับความยินยอม
  </p>

  <h3>4. ระยะเวลาการเก็บรักษา</h3>
  <ul>
    <li><strong>รูปเซลฟี่ของท่าน:</strong> เก็บในหน่วยความจำของเซิร์ฟเวอร์ระหว่างการค้นหา (ประมาณ 1–3 วินาที) และลบอัตโนมัติทันทีที่ตอบกลับผลการค้นหา ไม่บันทึกลงฐานข้อมูลหรือไฟล์ใด ๆ</li>
    <li><strong>ข้อมูลใบหน้าในอัลบั้มงาน (เฉพาะรูปที่ช่างภาพอัปโหลด):</strong> จัดเก็บเป็นลักษณะทางคณิตศาสตร์ (face template) ใน AWS Rekognition จนกว่างานจะถูกลบหรือเลยกำหนด retention</li>
    <li><strong>ไม่มีการรวมเวกเตอร์ใบหน้าข้ามอัลบั้ม</strong> — แต่ละงานมี collection แยกกัน</li>
  </ul>

  <h3>5. ผู้ประมวลผลข้อมูล (Data Processor)</h3>
  <p>
    ระบบใช้ <strong>AWS Rekognition</strong> (Amazon Web Services, Inc.) เป็นผู้ให้บริการประมวลผลใบหน้า
    ข้อมูลจะถูกส่งไปยังเซิร์ฟเวอร์ของ AWS ในภูมิภาค <code>ap-southeast-1 (สิงคโปร์)</code>
    ภายใต้สัญญา Data Processing Addendum ที่ AWS รับรองสอดคล้องกับ PDPA, GDPR, และ ISO 27001/27017/27018
  </p>

  <h3>6. สิทธิ์ของเจ้าของข้อมูล</h3>
  <p>ท่านมีสิทธิ์ดังนี้ตาม PDPA หมวด 3:</p>
  <ul>
    <li><strong>เพิกถอนความยินยอม</strong> ได้ทุกเมื่อ — การเพิกถอนไม่กระทบผลการประมวลผลก่อนหน้า</li>
    <li><strong>ขอให้ลบ</strong> ข้อมูลใบหน้าของท่านออกจาก collection ของงาน</li>
    <li><strong>ขอให้เปิดเผย</strong> รายการข้อมูลที่เราประมวลผลเกี่ยวกับท่าน</li>
    <li><strong>ร้องเรียน</strong> ต่อคณะกรรมการคุ้มครองข้อมูลส่วนบุคคล (PDPC) หากเห็นว่ามีการละเมิด</li>
  </ul>

  <h3>7. มาตรการความปลอดภัย</h3>
  <ul>
    <li>การส่งข้อมูลทั้งหมดใช้ HTTPS/TLS 1.2 ขึ้นไป</li>
    <li>ไม่มีการเก็บรูปเซลฟี่บนดิสก์ — อยู่ในหน่วยความจำเท่านั้น</li>
    <li>รหัสผ่านของผู้ประมวลผล (AWS credentials) เก็บในฐานข้อมูลเฉพาะและเข้าถึงได้เฉพาะผู้ดูแลระบบ</li>
    <li>ทุกครั้งที่มีการค้นหา ระบบจะบันทึก log (ไม่มีภาพ) เพื่อการตรวจสอบภายใน</li>
  </ul>

  <h3>8. การติดต่อ</h3>
  <p>
    หากท่านต้องการใช้สิทธิ์ตามข้อ 6 หรือมีคำถามเกี่ยวกับนโยบายนี้
    โปรดติดต่อผู้ควบคุมข้อมูลผ่านช่องทางที่ระบุไว้ใน <a href="/privacy-policy">นโยบายความเป็นส่วนตัวหลัก</a>
  </p>

  <p class="text-xs text-gray-500 mt-6">
    เอกสารนี้มีผลบังคับใช้ตั้งแต่วันที่ {{DATE}} — การเปลี่ยนแปลงจะแจ้งล่วงหน้า 30 วันผ่านหน้านี้
  </p>
</div>
HTML;

        // Substitute the effective date placeholder
        $content = str_replace('{{DATE}}', now()->format('j F Y'), $content);

        // Snapshot existing content BEFORE update so we have an audit trail.
        // Uses the model helper so we stay aligned with the schema
        // (legal_page_versions: legal_page_id, version, title, content,
        //  meta_description, effective_date, updated_by, change_note).
        $existing = LegalPage::where('slug', $slug)->first();
        if ($existing) {
            try {
                $existing->snapshotCurrent(null, 'Auto-seeded update from FaceSearchPrivacyPolicySeeder');
            } catch (\Throwable $e) {
                // Don't block the seed on a snapshot failure
            }
        }

        $nextVersion = LegalPage::bumpVersion($existing?->version);

        LegalPage::updateOrCreate(
            ['slug' => $slug],
            [
                'title'            => 'นโยบายความเป็นส่วนตัวสำหรับข้อมูลใบหน้า (PDPA §26)',
                'content'          => $content,
                'version'          => $nextVersion,
                'effective_date'   => now()->toDateString(),
                'is_published'     => true,
                'meta_description' => 'ข้อตกลงการประมวลผลข้อมูลชีวภาพ (ใบหน้า) ตาม PDPA §26 สำหรับฟีเจอร์ค้นหาด้วยใบหน้า',
            ]
        );

        $this->command?->info("✓ Seeded legal page /legal/{$slug}");
    }
}
