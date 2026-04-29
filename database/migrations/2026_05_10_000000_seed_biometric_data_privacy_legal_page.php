<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the "biometric-data-privacy" legal page.
 *
 * This is the PDPA §26 consent page linked from the face-search feature
 * (public/events/face-search.blade.php → "อ่านนโยบายความเป็นส่วนตัว →").
 * Users must consent to biometric data processing before uploading a
 * selfie for face search — that consent needs a published policy page
 * to link to, and a 404 here means the consent flow is legally broken.
 *
 * Content mirrors database/seeders/FaceSearchPrivacyPolicySeeder.php but
 * lives in a migration so it runs automatically on `php artisan migrate`
 * in fresh environments — no extra `db:seed` step to remember.
 *
 * Idempotent: skips insertion if the slug already exists. To refresh
 * content later, use the admin CMS at /admin/legal/{id}/edit (which also
 * snapshots the previous version to legal_page_versions for audit).
 */
return new class extends Migration {
    public function up(): void
    {
        // Legal pages CMS must exist (created in 2026_04_28 migration).
        // If it doesn't — someone is running this out of order — skip
        // silently rather than blow up.
        if (!\Illuminate\Support\Facades\Schema::hasTable('legal_pages')) {
            return;
        }

        // Skip if admin or the standalone seeder already published this page.
        $existing = DB::table('legal_pages')->where('slug', 'biometric-data-privacy')->first();
        if ($existing) {
            return;
        }

        $now      = now();
        $today    = $now->toDateString();
        $title    = 'นโยบายความเป็นส่วนตัวสำหรับข้อมูลใบหน้า (PDPA §26)';
        $metaDesc = 'ข้อตกลงการประมวลผลข้อมูลชีวภาพ (ใบหน้า) ตาม PDPA §26 สำหรับฟีเจอร์ค้นหาด้วยใบหน้า';
        $content  = $this->policyContent($today);

        $id = DB::table('legal_pages')->insertGetId([
            'slug'             => 'biometric-data-privacy',
            'title'            => $title,
            'content'          => $content,
            'version'          => '1.0',
            'effective_date'   => $today,
            'is_published'     => true,
            'meta_description' => $metaDesc,
            'last_updated_by'  => null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        DB::table('legal_page_versions')->insert([
            'legal_page_id'    => $id,
            'version'          => '1.0',
            'title'            => $title,
            'content'          => $content,
            'meta_description' => $metaDesc,
            'effective_date'   => $today,
            'updated_by'       => null,
            'change_note'      => 'Initial version (auto-seeded from migration)',
            'created_at'       => $now,
        ]);
    }

    public function down(): void
    {
        // Belt-and-braces cleanup: FK cascade from legal_pages would
        // delete version rows too, but explicit delete makes the intent
        // unambiguous during a selective rollback.
        $page = DB::table('legal_pages')->where('slug', 'biometric-data-privacy')->first();
        if ($page) {
            DB::table('legal_page_versions')->where('legal_page_id', $page->id)->delete();
            DB::table('legal_pages')->where('id', $page->id)->delete();
        }
    }

    /**
     * PDPA §26 consent content. Kept verbatim in sync with
     * FaceSearchPrivacyPolicySeeder — if you edit one, update the other.
     *
     * The 8 sections are structured to satisfy PDPA §26(1) disclosure
     * requirements for sensitive personal data consent:
     *   1. What data        (selfie = biometric)
     *   2. Purpose          (match against event photos)
     *   3. Legal basis      (§26(1) explicit consent)
     *   4. Retention        (in-memory, 1–3s, not persisted)
     *   5. Data processor   (AWS Rekognition, ap-southeast-1)
     *   6. Data subject rights (withdraw / delete / access / complain)
     *   7. Security measures
     *   8. Contact for exercising rights
     */
    private function policyContent(string $effectiveDate): string
    {
        $human = \Carbon\Carbon::parse($effectiveDate)->translatedFormat('j F Y');

        return <<<HTML
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
    เอกสารนี้มีผลบังคับใช้ตั้งแต่วันที่ {$human} — การเปลี่ยนแปลงจะแจ้งล่วงหน้า 30 วันผ่านหน้านี้
  </p>
</div>
HTML;
    }
};
