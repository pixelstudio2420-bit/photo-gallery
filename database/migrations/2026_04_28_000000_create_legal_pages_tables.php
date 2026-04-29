<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Legal Pages CMS — Privacy Policy, Terms of Service, Refund Policy.
 *
 * Admin-editable, versioned, effective-date tracked.
 *
 * Two tables:
 *   legal_pages         — current live version (1 row per page, keyed by slug)
 *   legal_page_versions — append-only version history (one row per save)
 */
return new class extends Migration {
    public function up(): void
    {
        /* ───────────── Current live pages ───────────── */
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 100)->unique();          // privacy-policy / terms-of-service / refund-policy
            $table->string('title', 255);
            $table->longText('content')->nullable();         // HTML
            $table->string('version', 20)->default('1.0');   // semver-ish, bumped per publish
            $table->date('effective_date')->nullable();      // when the current version becomes effective
            $table->boolean('is_published')->default(true);  // unpublish to hide public view
            $table->string('meta_description', 500)->nullable();
            $table->unsignedBigInteger('last_updated_by')->nullable(); // admin_id
            $table->timestamps();

            $table->index('is_published');
        });

        /* ───────────── Version history (append-only) ───────────── */
        Schema::create('legal_page_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('legal_page_id');
            $table->string('version', 20);
            $table->string('title', 255);
            $table->longText('content')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->date('effective_date')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable(); // admin_id
            $table->string('change_note', 500)->nullable();       // optional "what changed" note
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('legal_page_id')->references('id')->on('legal_pages')->cascadeOnDelete();
            $table->index(['legal_page_id', 'created_at']);
        });

        /* ───────────── Seed 3 default pages ───────────── */
        $now       = now();
        $siteName  = config('app.name', 'Photo Gallery');
        $today     = $now->toDateString();

        $pages = [
            [
                'slug'             => 'privacy-policy',
                'title'            => 'นโยบายความเป็นส่วนตัว',
                'meta_description' => "นโยบายความเป็นส่วนตัวของ {$siteName} — วิธีการเก็บ ใช้ และคุ้มครองข้อมูลส่วนบุคคล",
                'content'          => $this->defaultPrivacyPolicy($siteName),
            ],
            [
                'slug'             => 'terms-of-service',
                'title'            => 'ข้อกำหนดการให้บริการ',
                'meta_description' => "ข้อกำหนดและเงื่อนไขการใช้บริการ {$siteName}",
                'content'          => $this->defaultTermsOfService($siteName),
            ],
            [
                'slug'             => 'refund-policy',
                'title'            => 'นโยบายการคืนเงิน',
                'meta_description' => "นโยบายการคืนเงินของ {$siteName} — เงื่อนไขและขั้นตอนการขอคืนเงิน",
                'content'          => $this->defaultRefundPolicy($siteName),
            ],
        ];

        foreach ($pages as $page) {
            $id = DB::table('legal_pages')->insertGetId([
                'slug'             => $page['slug'],
                'title'            => $page['title'],
                'content'          => $page['content'],
                'version'          => '1.0',
                'effective_date'   => $today,
                'is_published'     => true,
                'meta_description' => $page['meta_description'],
                'last_updated_by'  => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            DB::table('legal_page_versions')->insert([
                'legal_page_id'    => $id,
                'version'          => '1.0',
                'title'            => $page['title'],
                'content'          => $page['content'],
                'meta_description' => $page['meta_description'],
                'effective_date'   => $today,
                'updated_by'       => null,
                'change_note'      => 'Initial version (auto-seeded)',
                'created_at'       => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_page_versions');
        Schema::dropIfExists('legal_pages');
    }

    /* ───────────── Default content templates ───────────── */

    private function defaultPrivacyPolicy(string $siteName): string
    {
        return <<<HTML
<p class="lead">เอกสารนี้อธิบายวิธีที่ <strong>{$siteName}</strong> เก็บรวบรวม ใช้ เปิดเผย และคุ้มครองข้อมูลส่วนบุคคลของผู้ใช้บริการ</p>

<h2>1. ข้อมูลที่เราเก็บรวบรวม</h2>
<ul>
  <li><strong>ข้อมูลบัญชีผู้ใช้</strong> — ชื่อ นามสกุล อีเมล เบอร์โทรศัพท์ ที่อยู่สำหรับจัดส่ง</li>
  <li><strong>ข้อมูลการชำระเงิน</strong> — ประวัติคำสั่งซื้อ (เราไม่เก็บข้อมูลบัตรเครดิตเต็ม — จัดการผ่าน Payment Gateway ที่รองรับมาตรฐาน PCI-DSS)</li>
  <li><strong>ข้อมูลการใช้งาน</strong> — IP address, ประเภทเบราว์เซอร์, หน้าที่เข้าชม, เวลาเข้าใช้งาน</li>
  <li><strong>Cookies และ Local Storage</strong> — เพื่อรักษา session การเข้าสู่ระบบและปรับแต่งประสบการณ์</li>
</ul>

<h2>2. วัตถุประสงค์การใช้ข้อมูล</h2>
<ul>
  <li>ให้บริการซื้อ-ขาย-ดาวน์โหลดรูปภาพและสินค้าดิจิทัล</li>
  <li>ดำเนินการชำระเงิน ออกใบเสร็จ และคืนเงิน</li>
  <li>ติดต่อเรื่องคำสั่งซื้อ การสนับสนุน และแจ้งเตือนสำคัญ</li>
  <li>ป้องกันการฉ้อโกง และการใช้งานในทางที่ผิด</li>
  <li>ปรับปรุงบริการและวิเคราะห์สถิติการใช้งาน (แบบไม่ระบุตัวบุคคล)</li>
</ul>

<h2>3. การเปิดเผยข้อมูลแก่บุคคลที่สาม</h2>
<p>เราจะไม่ขาย ให้เช่า หรือแลกเปลี่ยนข้อมูลส่วนบุคคลของคุณกับบุคคลที่สาม ยกเว้นในกรณีต่อไปนี้:</p>
<ul>
  <li>ผู้ให้บริการที่จำเป็นต่อการดำเนินธุรกิจ (Payment Gateway, Cloud Storage, Email Service)</li>
  <li>เมื่อได้รับความยินยอมจากคุณ</li>
  <li>เมื่อกฎหมายกำหนดให้เปิดเผย</li>
</ul>

<h2>4. การคุ้มครองข้อมูล</h2>
<p>เราใช้มาตรการรักษาความปลอดภัยทางเทคนิคและเชิงองค์กรเพื่อป้องกันการเข้าถึงโดยไม่ได้รับอนุญาต ได้แก่ การเข้ารหัส HTTPS, การยืนยันตัวตนสองขั้นตอน (2FA) สำหรับบัญชีที่มีสิทธิ์สูง, การบันทึก Activity Log ทุกการเปลี่ยนแปลงสำคัญ</p>

<h2>5. สิทธิของเจ้าของข้อมูล (PDPA)</h2>
<p>ภายใต้ พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 คุณมีสิทธิต่อไปนี้:</p>
<ul>
  <li>สิทธิขอเข้าถึงข้อมูล</li>
  <li>สิทธิขอให้แก้ไขข้อมูล</li>
  <li>สิทธิขอให้ลบข้อมูล</li>
  <li>สิทธิคัดค้านการประมวลผล</li>
  <li>สิทธิขอให้โอนย้ายข้อมูล</li>
</ul>
<p>กรุณาติดต่อเราเพื่อใช้สิทธิของคุณ</p>

<h2>6. Cookies</h2>
<p>เราใช้ cookies เพื่อรักษา session การเข้าสู่ระบบ จดจำตัวเลือกภาษา และวิเคราะห์การใช้งาน คุณสามารถปิด cookies ได้ที่การตั้งค่าเบราว์เซอร์ แต่อาจทำให้ฟีเจอร์บางอย่างใช้งานไม่ได้</p>

<h2>7. การเก็บรักษาข้อมูล</h2>
<p>เราจะเก็บข้อมูลส่วนบุคคลไว้เท่าที่จำเป็นต่อการให้บริการ หรือตามที่กฎหมายกำหนด ข้อมูลธุรกรรมทางการเงินจะเก็บอย่างน้อย 5 ปีตามกฎหมายภาษี</p>

<h2>8. การเปลี่ยนแปลงนโยบาย</h2>
<p>เราอาจปรับปรุงนโยบายนี้เป็นระยะ การเปลี่ยนแปลงจะมีผลนับจากวันที่ประกาศบนหน้านี้ กรุณาตรวจสอบวันที่มีผลบังคับใช้ด้านบน</p>

<h2>9. ติดต่อเรา</h2>
<p>หากมีคำถามเกี่ยวกับนโยบายนี้ กรุณาติดต่อเราผ่านหน้า <a href="/contact">ติดต่อ</a></p>
HTML;
    }

    private function defaultTermsOfService(string $siteName): string
    {
        return <<<HTML
<p class="lead">ยินดีต้อนรับสู่ <strong>{$siteName}</strong> การใช้บริการของเราถือว่าคุณยอมรับข้อกำหนดและเงื่อนไขต่อไปนี้</p>

<h2>1. ขอบเขตการให้บริการ</h2>
<p>{$siteName} เป็นแพลตฟอร์มค้นหา ซื้อ และดาวน์โหลดภาพถ่ายจากงานอีเวนต์ที่ถ่ายโดยช่างภาพมืออาชีพ รวมถึงสินค้าดิจิทัลที่เกี่ยวข้อง</p>

<h2>2. บัญชีผู้ใช้</h2>
<ul>
  <li>ผู้ใช้ต้องให้ข้อมูลที่ถูกต้องและเป็นปัจจุบันในการสมัครสมาชิก</li>
  <li>ผู้ใช้มีหน้าที่รักษารหัสผ่านเป็นความลับ</li>
  <li>ผู้ใช้รับผิดชอบต่อกิจกรรมทั้งหมดที่เกิดขึ้นภายใต้บัญชีของตน</li>
  <li>ห้ามใช้บัญชีของผู้อื่นโดยไม่ได้รับอนุญาต</li>
</ul>

<h2>3. การซื้อและการชำระเงิน</h2>
<ul>
  <li>ราคาที่แสดงเป็นราคารวมภาษีมูลค่าเพิ่ม (ถ้ามี)</li>
  <li>การชำระเงินรองรับ PromptPay โอนเงินผ่านธนาคาร และบัตรเครดิต/เดบิต</li>
  <li>คำสั่งซื้อจะเสร็จสมบูรณ์เมื่อได้รับการยืนยันการชำระเงิน</li>
  <li>หลังชำระเงินคุณจะได้รับลิงก์ดาวน์โหลดที่มีอายุ 7 วัน</li>
</ul>

<h2>4. ลิขสิทธิ์และการใช้งานภาพ</h2>
<ul>
  <li>ภาพถ่ายทุกภาพเป็นลิขสิทธิ์ของช่างภาพ</li>
  <li>การซื้อภาพให้สิทธิผู้ซื้อใช้งานส่วนตัวเท่านั้น</li>
  <li>ห้ามจำหน่ายต่อ ทำซ้ำ หรือใช้ภาพเพื่อการค้าโดยไม่ได้รับอนุญาตเป็นลายลักษณ์อักษร</li>
  <li>ห้ามลบลายน้ำออกจากภาพตัวอย่าง</li>
</ul>

<h2>5. ข้อห้ามในการใช้งาน</h2>
<p>ผู้ใช้ตกลงว่าจะไม่:</p>
<ul>
  <li>ใช้บริการเพื่อกิจกรรมที่ผิดกฎหมาย</li>
  <li>พยายามเข้าถึงระบบหรือข้อมูลที่ไม่ได้รับอนุญาต</li>
  <li>ส่ง malware, spam หรือโค้ดอันตราย</li>
  <li>สร้างบัญชีปลอม ใช้ข้อมูลเท็จ หรือปลอมแปลงตัวตน</li>
  <li>ใช้ bot/scraper เพื่อดึงข้อมูลจากเว็บไซต์โดยไม่ได้รับอนุญาต</li>
  <li>รบกวนการทำงานของระบบ</li>
</ul>

<h2>6. ช่างภาพและการอัปโหลดเนื้อหา</h2>
<ul>
  <li>ช่างภาพต้องเป็นเจ้าของลิขสิทธิ์หรือได้รับอนุญาตในการจำหน่ายภาพที่อัปโหลด</li>
  <li>เนื้อหาต้องไม่ละเมิดสิทธิ์ของบุคคลอื่น</li>
  <li>เราสงวนสิทธิ์ในการลบเนื้อหาที่ไม่เหมาะสมโดยไม่ต้องแจ้งล่วงหน้า</li>
</ul>

<h2>7. การระงับบัญชี</h2>
<p>เราขอสงวนสิทธิ์ในการระงับหรือยกเลิกบัญชีที่ฝ่าฝืนข้อกำหนดนี้โดยไม่ต้องแจ้งล่วงหน้า</p>

<h2>8. ข้อจำกัดความรับผิด</h2>
<p>{$siteName} ให้บริการ "ตามสภาพ" ("as is") เราไม่รับประกันว่าบริการจะปราศจากข้อผิดพลาดหรือพร้อมใช้งานตลอดเวลา ในขอบเขตที่กฎหมายอนุญาต เราจะไม่รับผิดต่อความเสียหายทางอ้อม ความเสียหายสืบเนื่อง หรือการสูญเสียกำไร</p>

<h2>9. การเปลี่ยนแปลงข้อกำหนด</h2>
<p>เราอาจแก้ไขข้อกำหนดนี้เป็นระยะ การเปลี่ยนแปลงสำคัญจะแจ้งทางอีเมลหรือประกาศบนเว็บไซต์ การใช้บริการต่อหลังจากประกาศถือว่าคุณยอมรับข้อกำหนดใหม่</p>

<h2>10. กฎหมายที่บังคับใช้</h2>
<p>ข้อกำหนดนี้อยู่ภายใต้กฎหมายของประเทศไทย ข้อพิพาทใด ๆ ให้ขึ้นศาลในเขตอำนาจของประเทศไทย</p>

<h2>11. ติดต่อ</h2>
<p>หากมีคำถามเกี่ยวกับข้อกำหนดนี้ กรุณาติดต่อเราผ่านหน้า <a href="/contact">ติดต่อ</a></p>
HTML;
    }

    private function defaultRefundPolicy(string $siteName): string
    {
        return <<<HTML
<p class="lead">นโยบายนี้อธิบายเงื่อนไขการคืนเงินสำหรับสินค้าและบริการที่ซื้อจาก <strong>{$siteName}</strong></p>

<h2>1. หลักการทั่วไป</h2>
<p>เนื่องจากสินค้าของเราเป็นสินค้าดิจิทัล (ไฟล์ภาพ) ที่ส่งมอบผ่านลิงก์ดาวน์โหลด การคืนเงินจึงพิจารณาเป็นรายกรณีภายใต้เงื่อนไขที่กำหนด</p>

<h2>2. เงื่อนไขที่สามารถขอคืนเงินได้</h2>
<ul>
  <li>ไฟล์ภาพเสียหาย หรือไม่สามารถเปิดไฟล์ได้ และเราไม่สามารถแก้ไขได้ภายใน 48 ชั่วโมง</li>
  <li>ได้รับภาพผิดจากที่สั่งซื้อ และเราไม่สามารถส่งภาพที่ถูกต้องให้ได้</li>
  <li>เกิดการหักเงินซ้ำโดยระบบผิดพลาด</li>
  <li>ไม่ได้รับลิงก์ดาวน์โหลดภายใน 24 ชั่วโมงหลังชำระเงิน (สำหรับการโอนที่ยืนยันแล้ว)</li>
</ul>

<h2>3. กรณีที่ไม่สามารถคืนเงินได้</h2>
<ul>
  <li>ได้ดาวน์โหลดไฟล์แล้ว (ระบบจะตรวจจับการดาวน์โหลด)</li>
  <li>ลูกค้าเปลี่ยนใจหลังจากซื้อสำเร็จแล้ว</li>
  <li>ภาพไม่ตรงกับความคาดหวังส่วนตัวของลูกค้า (ขอให้ตรวจสอบภาพตัวอย่างก่อนซื้อ)</li>
  <li>คำขอคืนเงินเกิน 7 วันนับจากวันที่ชำระเงิน</li>
</ul>

<h2>4. ระยะเวลาการขอคืนเงิน</h2>
<p>ต้องยื่นคำขอคืนเงิน<strong>ภายใน 7 วัน</strong>นับจากวันที่ชำระเงิน มิฉะนั้นจะถือว่าลูกค้ายอมรับสินค้า</p>

<h2>5. ขั้นตอนการขอคืนเงิน</h2>
<ol>
  <li>เข้าสู่ระบบ และไปที่หน้า <a href="/refunds">คำขอคืนเงิน</a></li>
  <li>เลือกคำสั่งซื้อที่ต้องการขอคืนเงิน</li>
  <li>ระบุเหตุผลและแนบหลักฐาน (ถ้ามี)</li>
  <li>ส่งคำขอและรอการพิจารณาจากทีมงาน (ปกติภายใน 3 วันทำการ)</li>
</ol>

<h2>6. ระยะเวลาดำเนินการคืนเงิน</h2>
<ul>
  <li><strong>ชำระผ่านบัตรเครดิต/เดบิต</strong> — 7-15 วันทำการ (ขึ้นอยู่กับธนาคารผู้ออกบัตร)</li>
  <li><strong>ชำระผ่าน PromptPay / โอนธนาคาร</strong> — 3-5 วันทำการ</li>
  <li>จำนวนเงินที่คืนเท่ากับจำนวนที่ชำระ ไม่รวมค่าธรรมเนียม Payment Gateway ที่ไม่สามารถเรียกคืนได้</li>
</ul>

<h2>7. การยกเลิกคำสั่งซื้อ</h2>
<p>ลูกค้าสามารถยกเลิกคำสั่งซื้อที่ยัง<em>ไม่ได้ชำระเงิน</em>ได้ทันทีจากหน้าคำสั่งซื้อ หากชำระเงินแล้ว ให้ติดต่อตามขั้นตอนการขอคืนเงินข้างต้น</p>

<h2>8. ข้อพิพาทและการติดต่อ</h2>
<p>หากไม่เห็นด้วยกับผลการพิจารณา สามารถยื่นอุทธรณ์ผ่านหน้า <a href="/contact">ติดต่อ</a> ภายใน 7 วันนับจากวันที่ได้รับผล</p>

<h2>9. การเปลี่ยนแปลงนโยบาย</h2>
<p>เราอาจปรับปรุงนโยบายการคืนเงินนี้เป็นระยะ การเปลี่ยนแปลงจะมีผลนับจากวันที่ประกาศบนหน้านี้</p>
HTML;
    }
};
