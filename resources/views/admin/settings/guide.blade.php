@extends('layouts.admin')

@section('title', 'คู่มือการตั้งค่าระบบ')

@push('styles')
<style>
.guide-container { max-width: 960px; margin: 0 auto; }
.guide-header { letter-spacing: -0.02em; }
.guide-toc {
  background: #f8fafc;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  padding: 1.5rem 2rem;
}
.guide-toc ol { margin: 0; padding-left: 1.2rem; }
.guide-toc li { padding: 0.25rem 0; }
.guide-toc a { color: #4f46e5; text-decoration: none; font-weight: 500; font-size: 0.92rem; }
.guide-toc a:hover { text-decoration: underline; }

.guide-section {
  background: #fff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  padding: 2rem;
  margin-bottom: 1.5rem;
  scroll-margin-top: 80px;
}
.guide-section h3 {
  font-size: 1.15rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.6rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.guide-section h4 {
  font-size: 0.95rem;
  font-weight: 700;
  color: #374151;
  margin-top: 1.2rem;
  margin-bottom: 0.5rem;
  border-left: 3px solid #6366f1;
  padding-left: 0.7rem;
}
.guide-section p, .guide-section li {
  font-size: 0.88rem;
  line-height: 1.75;
  color: #4b5563;
}
.guide-section ul, .guide-section ol {
  padding-left: 1.3rem;
}
.field-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 0.85rem;
  margin: 0.8rem 0;
}
.field-table th {
  background: #f1f5f9;
  font-weight: 600;
  color: #374151;
  padding: 0.55rem 0.8rem;
  border-b: 1.5px solid #e5e7eb;
  text-align: left;
}
.field-table th:first-child { border-radius: 8px 0 0 0; }
.field-table th:last-child { border-radius: 0 8px 0 0; }
.field-table td {
  padding: 0.5rem 0.8rem;
  border-b: 1px solid #f1f5f9;
  vertical-align: top;
}
.field-table tr:last-child td { border-b: none; }
.field-table code {
  background: rgba(99,102,241,0.08);
  color: #4f46e5;
  padding: 0.15rem 0.4rem;
  border-radius: 4px;
  font-size: 0.82rem;
}

.tip-box {
  background: rgba(99,102,241,0.06);
  border-left: 3px solid #6366f1;
  border-radius: 0 10px 10px 0;
  padding: 0.75rem 1rem;
  margin: 0.8rem 0;
  font-size: 0.85rem;
  color: #4338ca;
}
.warn-box {
  background: rgba(245,158,11,0.08);
  border-left: 3px solid #f59e0b;
  border-radius: 0 10px 10px 0;
  padding: 0.75rem 1rem;
  margin: 0.8rem 0;
  font-size: 0.85rem;
  color: #92400e;
}
.danger-box {
  background: rgba(239,68,68,0.06);
  border-left: 3px solid #ef4444;
  border-radius: 0 10px 10px 0;
  padding: 0.75rem 1rem;
  margin: 0.8rem 0;
  font-size: 0.85rem;
  color: #991b1b;
}

.badge-field {
  display: inline-block;
  background: #e0e7ff;
  color: #3730a3;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  vertical-align: middle;
}
.back-to-top {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #6366f1;
  color: #fff;
  border: none;
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 10px rgba(99,102,241,0.3);
  cursor: pointer;
  z-index: 999;
  transition: opacity .2s;
}
.back-to-top:hover { background: #4f46e5; }
</style>
@endpush

@section('content')
<div class="guide-container">

{{-- Header --}}
<div class="flex items-center justify-between mb-4">
  <div>
    <h4 class="font-bold mb-0 guide-header">
      <i class="bi bi-book mr-2" style="color:#6366f1;"></i>
      คู่มือการตั้งค่าระบบ
    </h4>
    <p class="text-gray-500 small mb-0 mt-1">อธิบายรายละเอียดการตั้งค่าทุกหน้าในระบบแอดมิน</p>
  </div>
  <a href="{{ route('admin.settings.index') }}" class="text-sm px-3 py-1.5 rounded-lg btn-outline-secondary" style="border-radius:10px;">
    <i class="bi bi-arrow-left mr-1"></i>กลับหน้าตั้งค่า
  </a>
</div>

{{-- สารบัญ --}}
<div class="guide-toc mb-4" id="top">
  <h6 class="font-bold mb-3"><i class="bi bi-list-ol mr-1"></i> สารบัญ</h6>
  <ol>
    <li><a href="#general">ตั้งค่าทั่วไป (General)</a></li>
    <li><a href="#security">ความปลอดภัย (Security)</a></li>
    <li><a href="#2fa">ยืนยันตัวตน 2 ชั้น (2FA)</a></li>
    <li><a href="#source-protection">ป้องกันซอร์สโค้ด (Source Protection)</a></li>
    <li><a href="#proxy-shield">ป้องกัน Proxy/VPN (Proxy Shield)</a></li>
    <li><a href="#seo">SEO & Open Graph</a></li>
    <li><a href="#language">ภาษา (Language)</a></li>
    <li><a href="#watermark">ลายน้ำ (Watermark)</a></li>
    <li><a href="#image">ประมวลผลรูปภาพ (Image Processing)</a></li>
    <li><a href="#google-drive">Google Drive</a></li>
    <li><a href="#aws">AWS Cloud (S3 / CloudFront)</a></li>
    <li><a href="#cloudflare">Cloudflare CDN & R2 Storage</a></li>
    <li><a href="#payment-gateways">ช่องทางชำระเงิน (Payment Gateways)</a></li>
    <li><a href="#analytics">Analytics & Tracking</a></li>
    <li><a href="#line">LINE Messaging</a></li>
    <li><a href="#webhooks">Webhooks Monitor</a></li>
    <li><a href="#email-logs">ประวัติอีเมล (Email Logs)</a></li>
    <li><a href="#queue">คิวงาน (Queue Management)</a></li>
    <li><a href="#performance">ประสิทธิภาพ (Performance)</a></li>
    <li><a href="#backup">สำรองข้อมูล (Backup)</a></li>
    <li><a href="#reset">รีเซ็ตข้อมูล (System Reset)</a></li>
    <li><a href="#version">ข้อมูลเวอร์ชัน (Version Info)</a></li>
  </ol>
</div>

{{-- ═══════════════════════════════════════════════════
   1. ตั้งค่าทั่วไป
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="general">
  <h3><i class="bi bi-gear" style="color:#6366f1;"></i> 1. ตั้งค่าทั่วไป (General)</h3>
  <p>ตั้งค่าข้อมูลพื้นฐานของเว็บไซต์ เช่น ชื่อเว็บ อีเมลติดต่อ และอัตราค่าคอมมิชชัน</p>

  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th><th>ค่าเริ่มต้น</th></tr>
    <tr><td>Site Name</td><td>ชื่อเว็บไซต์ แสดงบนหัวเว็บและ title bar</td><td><code>Photo Gallery</code></td></tr>
    <tr><td>Site Description</td><td>คำอธิบายเว็บ ใช้ใน meta description สำหรับ SEO</td><td>ว่าง</td></tr>
    <tr><td>Contact Email</td><td>อีเมลรับติดต่อจากลูกค้า แสดงในหน้าติดต่อเรา</td><td>ว่าง</td></tr>
    <tr><td>Contact Phone</td><td>เบอร์โทรศัพท์ แสดงในหน้าติดต่อเรา</td><td>ว่าง</td></tr>
    <tr><td>Default Language</td><td>ภาษาเริ่มต้นของเว็บไซต์</td><td><code>th</code> (ไทย)</td></tr>
    <tr><td>PromptPay Number</td><td>เลขพร้อมเพย์สำหรับรับชำระเงิน</td><td>ว่าง</td></tr>
    <tr><td>PromptPay Name</td><td>ชื่อบัญชีพร้อมเพย์ แสดงให้ลูกค้าเห็น</td><td>ว่าง</td></tr>
    <tr><td>Photographer Commission (%)</td><td>เปอร์เซ็นต์ที่ช่างภาพได้รับจากยอดขาย เช่น 70 = ช่างภาพได้ 70%</td><td><code>70</code></td></tr>
  </table>

  <div class="tip-box">
    <i class="bi bi-lightbulb mr-1"></i>
    <strong>เคล็ดลับ:</strong> กรอก Site Name และ Contact Email ก่อนเป็นอันดับแรก เพราะจะถูกใช้ในอีเมลแจ้งเตือนและ footer ของเว็บ
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   2. ความปลอดภัย
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="security">
  <h3><i class="bi bi-shield-lock" style="color:#ef4444;"></i> 2. ความปลอดภัย (Security)</h3>
  <p>จัดการระบบรักษาความปลอดภัย ตั้งค่าการล็อกอิน การออกจากระบบอัตโนมัติ และดูประวัติการเข้าสู่ระบบ</p>

  <h4>Idle Auto-Logout (ออกจากระบบอัตโนมัติ)</h4>
  <p>เมื่อผู้ใช้ไม่ได้ใช้งานเว็บเป็นระยะเวลาที่กำหนด ระบบจะแจ้งเตือนและออกจากระบบอัตโนมัติเพื่อป้องกันการเข้าถึงโดยไม่ได้รับอนุญาต</p>

  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th><th>ค่าเริ่มต้น</th></tr>
    <tr><td>Idle Timeout Admin (นาที)</td><td>เวลาที่แอดมินไม่ได้ใช้งานก่อนถูกเตือน (0 = ปิด)</td><td><code>15</code> นาที</td></tr>
    <tr><td>Idle Timeout Photographer (นาที)</td><td>เวลาที่ช่างภาพไม่ได้ใช้งานก่อนถูกเตือน</td><td><code>30</code> นาที</td></tr>
    <tr><td>Idle Warning (วินาที)</td><td>เวลานับถอยหลังที่แสดง popup เตือนก่อนออกจากระบบ</td><td><code>60</code> วินาที</td></tr>
  </table>

  <h4>ประวัติการเข้าสู่ระบบ</h4>
  <p>แสดงรายการล็อกอินล่าสุด 20 รายการ พร้อมข้อมูล: วันเวลา, อีเมล, IP Address, สถานะ (สำเร็จ/ล้มเหลว) ช่วยตรวจจับการพยายามเข้าสู่ระบบที่ผิดปกติ</p>

  <h4>Security Logs</h4>
  <p>บันทึกเหตุการณ์ด้านความปลอดภัย เช่น การบล็อก IP, การตรวจพบ SQL Injection, การตรวจพบ XSS เป็นต้น</p>

  <div class="warn-box">
    <i class="bi bi-exclamation-triangle mr-1"></i>
    <strong>คำแนะนำ:</strong> ตรวจสอบ Security Logs เป็นประจำ หากพบ IP ที่พยายามโจมตีซ้ำๆ ควรบล็อกใน Proxy Shield
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   3. 2FA
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="2fa">
  <h3><i class="bi bi-phone" style="color:#10b981;"></i> 3. ยืนยันตัวตน 2 ชั้น (2FA)</h3>
  <p>เพิ่มความปลอดภัยให้บัญชีแอดมินด้วยรหัส 6 หลักจากแอป Authenticator (Google Authenticator, Authy ฯลฯ)</p>

  <h4>วิธีเปิดใช้งาน</h4>
  <ol>
    <li>คลิก <strong>"Set Up Two-Factor Auth"</strong></li>
    <li>สแกน <strong>QR Code</strong> ด้วยแอป Google Authenticator หรือ Authy</li>
    <li>กรอก <strong>รหัส 6 หลัก</strong> ที่แสดงในแอปเพื่อยืนยัน</li>
    <li>ระบบจะแสดง <strong>Backup Codes</strong> — บันทึกไว้ในที่ปลอดภัย ใช้ล็อกอินเมื่อโทรศัพท์หาย</li>
  </ol>

  <h4>วิธีปิดใช้งาน</h4>
  <ol>
    <li>กรอก <strong>รหัสผ่าน</strong> ของบัญชีแอดมิน</li>
    <li>คลิก <strong>"Disable Two-Factor Auth"</strong></li>
  </ol>

  <div class="danger-box">
    <i class="bi bi-exclamation-octagon mr-1"></i>
    <strong>สำคัญ:</strong> Backup Codes จะแสดงเพียงครั้งเดียวตอนเปิดใช้งาน หากไม่ได้บันทึกไว้ จะไม่สามารถกู้คืนได้
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   4. Source Protection
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="source-protection">
  <h3><i class="bi bi-code-slash" style="color:#8b5cf6;"></i> 4. ป้องกันซอร์สโค้ด (Source Protection)</h3>
  <p>ป้องกันไม่ให้ผู้เยี่ยมชมดู HTML source code, คลิกขวาคัดลอกรูป หรือเปิด DevTools ในหน้าเว็บสาธารณะ</p>

  <h4>ระดับการป้องกัน</h4>
  <table class="field-table">
    <tr><th>ระดับ</th><th>สิ่งที่ป้องกัน</th><th>เหมาะกับ</th></tr>
    <tr><td><strong>Light</strong></td><td>บล็อกคลิกขวา + ตรวจจับ DevTools</td><td>เว็บทั่วไป</td></tr>
    <tr><td><strong>Standard</strong> (แนะนำ)</td><td>Light + บล็อก View Source, Copy, Drag</td><td>เว็บขายรูปภาพ</td></tr>
    <tr><td><strong>Strict</strong></td><td>Standard + ซ่อน HTML + เตือนใน Console</td><td>ต้องการป้องกันสูงสุด</td></tr>
  </table>

  <h4>ตัวเลือกแยก</h4>
  <table class="field-table">
    <tr><th>ตัวเลือก</th><th>คำอธิบาย</th></tr>
    <tr><td>Disable Right-Click</td><td>บล็อกเมนูคลิกขวา (ป้องกันบันทึกรูป)</td></tr>
    <tr><td>Disable DevTools</td><td>ตรวจจับการเปิด Developer Tools ในเบราว์เซอร์</td></tr>
    <tr><td>Disable View Source</td><td>บล็อก Ctrl+U ดู source code</td></tr>
    <tr><td>Disable Drag</td><td>ป้องกันการลากรูปภาพ/ข้อความ</td></tr>
    <tr><td>Disable Copy</td><td>บล็อก Ctrl+C คัดลอกข้อความ</td></tr>
    <tr><td>Obfuscate HTML</td><td>ทำให้โค้ด HTML อ่านยาก</td></tr>
    <tr><td>Console Warning</td><td>แสดงข้อความเตือนใน Console ของเบราว์เซอร์</td></tr>
  </table>

  <div class="warn-box">
    <i class="bi bi-exclamation-triangle mr-1"></i>
    <strong>หมายเหตุ:</strong> ไม่ควรเปิด "Apply to Admin Pages" เพราะจะทำให้ใช้งานหน้าแอดมินลำบาก
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   5. Proxy Shield
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="proxy-shield">
  <h3><i class="bi bi-shield-exclamation" style="color:#f59e0b;"></i> 5. ป้องกัน Proxy/VPN (Proxy Shield)</h3>
  <p>ตรวจจับและจัดการ traffic ที่ผ่าน Proxy, VPN, TOR หรือ Datacenter เพื่อป้องกันการเข้าถึงที่ไม่พึงประสงค์</p>

  <h4>วิธีการตรวจจับ</h4>
  <table class="field-table">
    <tr><th>วิธีการ</th><th>คำอธิบาย</th></tr>
    <tr><td>HTTP Header Detection</td><td>ตรวจสอบ X-Forwarded-For headers ที่บ่งบอกว่าใช้ proxy</td></tr>
    <tr><td>TOR Exit Node</td><td>เทียบ IP กับรายชื่อ TOR exit nodes ที่รู้จัก</td></tr>
    <tr><td>VPN Detection</td><td>ตรวจสอบ IP ที่อยู่ในช่วงของผู้ให้บริการ VPN</td></tr>
    <tr><td>Datacenter IP</td><td>ตรวจสอบ IP จาก Cloud/Hosting providers (AWS, Azure ฯลฯ)</td></tr>
    <tr><td>Anomaly Detection</td><td>ตรวจจับพฤติกรรมผิดปกติ เช่น request ถี่เกินไป</td></tr>
  </table>

  <h4>การตอบสนองเมื่อตรวจพบ</h4>
  <ul>
    <li><strong>Monitor Only</strong> — บันทึก log แต่ยังอนุญาตให้เข้าใช้งาน (แนะนำช่วงแรก)</li>
    <li><strong>Block Access</strong> — บล็อกการเข้าถึงทันที</li>
  </ul>

  <div class="tip-box">
    <i class="bi bi-lightbulb mr-1"></i>
    <strong>เคล็ดลับ:</strong> เริ่มด้วย "Monitor Only" สัก 1-2 สัปดาห์เพื่อดูข้อมูลก่อน แล้วค่อยเปลี่ยนเป็น Block
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   6. SEO
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="seo">
  <h3><i class="bi bi-search" style="color:#10b981;"></i> 6. SEO & Open Graph</h3>
  <p>ตั้งค่า Search Engine Optimization เพื่อให้เว็บไซต์ติดอันดับ Google และแสดงผลสวยงามเมื่อแชร์ลิงก์บน Social Media</p>

  <h4>แท็บ General (ทั่วไป)</h4>
  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th><th>ตัวอย่าง</th></tr>
    <tr><td>Site Tagline</td><td>คำโปรยใต้ชื่อเว็บ แสดงในหน้าแรก</td><td><code>แกลเลอรีรูปภาพออนไลน์</code></td></tr>
    <tr><td>Site Description</td><td>คำอธิบายเว็บ 150-160 ตัวอักษร ใช้ใน meta description</td><td><code>บริการขายรูปภาพงานอีเวนต์...</code></td></tr>
    <tr><td>Title Separator</td><td>ตัวคั่นระหว่างชื่อหน้ากับชื่อเว็บ</td><td><code>—</code></td></tr>
    <tr><td>Default Keywords</td><td>คำค้นหา คั่นด้วยคอมมา (ใช้เป็น fallback)</td><td><code>รูปภาพ,อีเวนต์,งานแต่ง</code></td></tr>
    <tr><td>Default Robots</td><td>บอก Google ว่าจะ index หน้านี้หรือไม่</td><td><code>index, follow</code></td></tr>
    <tr><td>Theme Color</td><td>สีของ browser toolbar บนมือถือ</td><td><code>#6366f1</code></td></tr>
  </table>

  <h4>แท็บ Social & Open Graph</h4>
  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>OG Image</td><td>รูปที่แสดงเมื่อแชร์ลิงก์บน Facebook/LINE (แนะนำ 1200x630px)</td></tr>
    <tr><td>OG Type</td><td>ประเภทเว็บ (website, blog, business)</td></tr>
    <tr><td>Facebook App ID</td><td>App ID สำหรับ Facebook Insights (ไม่บังคับ)</td></tr>
    <tr><td>Twitter Handle</td><td>บัญชี Twitter เช่น @photogallery (ไม่บังคับ)</td></tr>
  </table>

  <h4>แท็บ Analytics</h4>
  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>Google Analytics ID</td><td>รหัส GA เช่น <code>G-XXXXXXXXXX</code></td></tr>
    <tr><td>Google Search Console</td><td>รหัสยืนยันสำหรับ Search Console</td></tr>
    <tr><td>Bing Webmaster Tools</td><td>รหัสยืนยันสำหรับ Bing</td></tr>
  </table>
</div>

{{-- ═══════════════════════════════════════════════════
   7. Language
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="language">
  <h3><i class="bi bi-translate" style="color:#3b82f6;"></i> 7. ภาษา (Language)</h3>
  <p>จัดการภาษาที่เว็บไซต์รองรับ และเปิด/ปิดตัวเลือกเปลี่ยนภาษาสำหรับผู้เยี่ยมชม</p>

  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>Enable Multi-language</td><td>เปิด/ปิดตัวเลือกเปลี่ยนภาษาบนเว็บ</td></tr>
    <tr><td>Default Language</td><td>ภาษาเริ่มต้น: ไทย, English หรือ 中文</td></tr>
    <tr><td>Enabled Languages</td><td>เลือกภาษาที่ต้องการเปิดให้ใช้ (checkbox)</td></tr>
  </table>
</div>

{{-- ═══════════════════════════════════════════════════
   8. Watermark
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="watermark">
  <h3><i class="bi bi-droplet-half" style="color:#ec4899;"></i> 8. ลายน้ำ (Watermark)</h3>
  <p>ตั้งค่าลายน้ำที่จะแสดงบนรูปภาพตัวอย่าง เพื่อป้องกันการนำรูปไปใช้โดยไม่ได้ซื้อ</p>

  <h4>ประเภทลายน้ำ</h4>
  <table class="field-table">
    <tr><th>ประเภท</th><th>คำอธิบาย</th><th>เหมาะกับ</th></tr>
    <tr><td><strong>Text</strong></td><td>ใช้ข้อความ เช่น "© Studio Name"</td><td>เริ่มต้นง่าย ไม่ต้องเตรียมรูป</td></tr>
    <tr><td><strong>Image</strong></td><td>ใช้รูปโลโก้ PNG/SVG/WebP (ไม่เกิน 2MB)</td><td>ต้องการแบรนด์ที่ชัดเจน</td></tr>
  </table>

  <h4>ตำแหน่งและรูปแบบ</h4>
  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th><th>ค่าเริ่มต้น</th></tr>
    <tr><td>Position</td><td>ตำแหน่งลายน้ำ: ทแยง, ซ้ำ (Tiled), กลาง, มุมล่างขวา ฯลฯ</td><td><code>Diagonal</code></td></tr>
    <tr><td>Opacity</td><td>ความโปร่งใส 5-100% (ยิ่งน้อยยิ่งจาง)</td><td><code>50%</code></td></tr>
    <tr><td>Size</td><td>ขนาดลายน้ำ 10-80% ของรูป</td><td><code>30%</code></td></tr>
  </table>

  <div class="tip-box">
    <i class="bi bi-lightbulb mr-1"></i>
    <strong>เคล็ดลับ:</strong> ใช้ตำแหน่ง "Diagonal" + Opacity 40-60% จะป้องกันการครอบตัดรูปได้ดีที่สุด โดยไม่บังรูปภาพมากเกินไป
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   9. Image Processing
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="image">
  <h3><i class="bi bi-image" style="color:#06b6d4;"></i> 9. ประมวลผลรูปภาพ (Image Processing)</h3>
  <p>ตั้งค่าการย่อขนาด แปลงฟอร์แมต และบีบอัดรูปภาพอัตโนมัติเมื่ออัปโหลด เพื่อประหยัดพื้นที่และเพิ่มความเร็วโหลดหน้าเว็บ</p>

  <h4>การตั้งค่าแยกตามประเภท</h4>
  <table class="field-table">
    <tr><th>ประเภท</th><th>ค่าแนะนำ</th><th>คำอธิบาย</th></tr>
    <tr><td><strong>Cover Images</strong></td><td>WebP, 85%, 1920x1080</td><td>รูปหน้าปกอีเวนต์</td></tr>
    <tr><td><strong>Avatar / Profile</strong></td><td>WebP, 80%, 400x400</td><td>รูปโปรไฟล์ผู้ใช้/ช่างภาพ</td></tr>
    <tr><td><strong>Payment Slips</strong></td><td>90%, 1200x1600</td><td>สลิปชำระเงิน (ต้องชัดเพื่อตรวจสอบ)</td></tr>
    <tr><td><strong>SEO / OG Images</strong></td><td>JPEG, 85%, 1200x630</td><td>รูปแชร์ Social Media</td></tr>
  </table>

  <h4>ตัวเลือกแต่ละประเภท</h4>
  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>Output Format</td><td>ฟอร์แมตไฟล์: WebP (เล็กที่สุด), JPEG (ใช้ได้ทั่วไป), PNG (คุณภาพสูง), Original (ไม่แปลง)</td></tr>
    <tr><td>Quality</td><td>คุณภาพการบีบอัด — ยิ่งสูงรูปยิ่งชัดแต่ไฟล์ใหญ่</td></tr>
    <tr><td>Max Width / Height</td><td>ขนาดสูงสุด — รูปที่ใหญ่กว่านี้จะถูกย่อลงอัตโนมัติ</td></tr>
  </table>

  <div class="tip-box">
    <i class="bi bi-lightbulb mr-1"></i>
    <strong>เคล็ดลับ:</strong> ใช้ <strong>WebP</strong> จะประหยัดพื้นที่ได้ 30-50% เทียบกับ JPEG โดยคุณภาพใกล้เคียงกัน
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   10. Google Drive
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="google-drive">
  <h3><i class="bi bi-google" style="color:#4285f4;"></i> 10. Google Drive</h3>
  <p>เชื่อมต่อ Google Drive เพื่อใช้เก็บและแสดงรูปภาพอีเวนต์ ช่างภาพสามารถใส่ลิงก์โฟลเดอร์ Drive แทนการอัปโหลดรูปเข้าเว็บโดยตรง</p>

  <h4>วิธีตั้งค่า</h4>
  <ol>
    <li>ไปที่ <a href="https://console.cloud.google.com" target="_blank" rel="noopener">Google Cloud Console</a></li>
    <li>สร้างโปรเจค → เปิด Google Drive API</li>
    <li>สร้าง OAuth 2.0 credentials (Client ID + Secret)</li>
    <li>สร้าง Service Account (สำหรับเข้าถึง Drive แบบ server-to-server)</li>
    <li>อัปโหลดไฟล์ JSON ของ Service Account ในหน้านี้</li>
    <li>สร้าง API Key สำหรับ public access (ดูรูป thumbnail)</li>
  </ol>

  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>Client ID</td><td>OAuth 2.0 Client ID จาก Google Cloud Console</td></tr>
    <tr><td>Client Secret</td><td>OAuth 2.0 Client Secret</td></tr>
    <tr><td>API Key</td><td>API Key สำหรับเข้าถึงไฟล์สาธารณะ</td></tr>
    <tr><td>Service Account JSON</td><td>ไฟล์ JSON สำหรับ server-to-server authentication</td></tr>
  </table>

  <div class="warn-box">
    <i class="bi bi-exclamation-triangle mr-1"></i>
    <strong>สำคัญ:</strong> ต้องแชร์โฟลเดอร์ Drive ให้กับ Service Account email (เช่น xxx@project.iam.gserviceaccount.com) จึงจะอ่านไฟล์ได้
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   11. AWS Cloud
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="aws">
  <h3><i class="bi bi-cloud" style="color:#f59e0b;"></i> 11. AWS Cloud (S3 / CloudFront)</h3>
  <p>ใช้ Amazon S3 เก็บรูปภาพ และ CloudFront CDN เร่งความเร็วการแสดงผลทั่วโลก</p>

  <h4>AWS Credentials</h4>
  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>Access Key ID</td><td>IAM Access Key สำหรับเข้าถึง AWS</td></tr>
    <tr><td>Secret Access Key</td><td>IAM Secret Key (เว้นว่างถ้าไม่ต้องการเปลี่ยน)</td></tr>
    <tr><td>Default Region</td><td>Region ที่ใกล้กลุ่มเป้าหมาย เช่น <code>ap-southeast-1</code> (Singapore)</td></tr>
  </table>

  <h4>S3 Storage</h4>
  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>Bucket Name</td><td>ชื่อ S3 bucket ที่จะเก็บไฟล์</td></tr>
    <tr><td>Folder Prefix</td><td>คำนำหน้า path เช่น <code>photos/</code></td></tr>
    <tr><td>Default Visibility</td><td>ไฟล์ใหม่จะเป็น public หรือ private</td></tr>
  </table>

  <h4>CloudFront CDN</h4>
  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>Enable CloudFront</td><td>เปิด/ปิดการใช้ CloudFront CDN URLs</td></tr>
    <tr><td>Distribution ID</td><td>รหัส CloudFront distribution</td></tr>
    <tr><td>Domain</td><td>โดเมน CDN เช่น <code>d1234.cloudfront.net</code></td></tr>
  </table>

  <div class="tip-box">
    <i class="bi bi-lightbulb mr-1"></i>
    <strong>เคล็ดลับ:</strong> เลือก region ที่ใกล้กลุ่มเป้าหมายมากที่สุด สำหรับประเทศไทยใช้ <code>ap-southeast-1</code> (Singapore)
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   12. Cloudflare CDN & R2
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="cloudflare">
  <h3><i class="bi bi-cloud-haze2" style="color:#f6821f;"></i> 12. Cloudflare CDN & R2 Storage</h3>
  <p>เชื่อมต่อ Cloudflare สำหรับ CDN, ล้าง cache และใช้ R2 เป็น storage สำหรับรูปภาพ (ทางเลือกแทน AWS S3)</p>

  <h4>Cloudflare CDN</h4>
  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>API Token</td><td>สร้างจาก Cloudflare Dashboard > My Profile > API Tokens ต้องมีสิทธิ์ <code>Zone:Read</code> + <code>Cache Purge</code></td></tr>
    <tr><td>Zone ID</td><td>รหัสโดเมน อยู่ในหน้า Overview ของ Cloudflare</td></tr>
    <tr><td>Enable CDN/Proxy</td><td>เปิดให้ traffic ผ่าน Cloudflare network</td></tr>
    <tr><td>Purge All Cache</td><td>ล้าง cache ทั้งหมดจาก edge ทั่วโลก (ใช้เมื่ออัปเดตรูปภาพแล้วไม่เปลี่ยน)</td></tr>
  </table>

  <h4>R2 Object Storage</h4>
  <p>Cloudflare R2 เป็น storage ที่เข้ากันได้กับ S3 ไม่มีค่า egress (ค่าดาวน์โหลด) เหมาะกับเว็บรูปภาพ</p>

  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>Enable R2</td><td>เปิดใช้ R2 เป็น storage หลักสำหรับรูปที่อัปโหลด</td></tr>
    <tr><td>Access Key ID</td><td>สร้างจาก R2 > Manage R2 API Tokens</td></tr>
    <tr><td>Secret Access Key</td><td>Secret key จาก R2 API Token (เว้นว่างเพื่อไม่เปลี่ยน)</td></tr>
    <tr><td>Bucket Name</td><td>ชื่อ R2 bucket ที่สร้างไว้</td></tr>
    <tr><td>S3 API Endpoint</td><td>รูปแบบ: <code>https://ACCOUNT_ID.r2.cloudflarestorage.com</code></td></tr>
    <tr><td>Public Bucket URL</td><td>URL สาธารณะ เช่น <code>https://pub-xxx.r2.dev</code> (เปิดใน R2 Settings)</td></tr>
    <tr><td>Custom Domain</td><td>โดเมนที่ผูกกับ R2 เช่น <code>cdn.example.com</code></td></tr>
  </table>

  <h4>วิธีตั้งค่า R2 (Step by Step)</h4>
  <ol>
    <li>เข้า <a href="https://dash.cloudflare.com" target="_blank" rel="noopener">Cloudflare Dashboard</a> > R2</li>
    <li>คลิก <strong>Create Bucket</strong> ตั้งชื่อ เช่น "photo-gallery"</li>
    <li>คลิก <strong>Manage R2 API Tokens</strong> > Create API Token</li>
    <li>คัดลอก Access Key ID + Secret Access Key มากรอกในหน้านี้</li>
    <li>คัดลอก <strong>S3 API Endpoint</strong> (แสดงในหน้า bucket settings)</li>
    <li>(ไม่บังคับ) เปิด <strong>Public Access</strong> ใน bucket settings จะได้ URL <code>pub-xxx.r2.dev</code></li>
    <li>กด <strong>Test Connection</strong> เพื่อทดสอบ</li>
    <li>เปิด toggle <strong>Enable R2</strong> แล้วกด Save</li>
  </ol>

  <div class="tip-box">
    <i class="bi bi-lightbulb mr-1"></i>
    <strong>ลำดับความสำคัญ:</strong> ระบบจะเลือก storage ตามลำดับ: <strong>R2 > S3 > Local</strong> — ถ้าเปิด R2 ไว้ รูปใหม่จะเก็บใน R2 โดยอัตโนมัติ
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   13. Payment Gateways
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="payment-gateways">
  <h3><i class="bi bi-credit-card-2-front" style="color:#6366f1;"></i> 13. ช่องทางชำระเงิน (Payment Gateways)</h3>
  <p>จัดการ API keys ของทุกช่องทางชำระเงินที่ระบบรองรับ เปิด/ปิดแต่ละช่องทาง และสลับโหมด Sandbox/Production</p>

  <h4>ช่องทางที่รองรับ</h4>
  <table class="field-table">
    <tr><th>ช่องทาง</th><th>รองรับ</th><th>เหมาะกับ</th></tr>
    <tr><td><strong>Stripe</strong></td><td>บัตรเครดิต/เดบิต ทั่วโลก</td><td>ลูกค้าต่างประเทศ</td></tr>
    <tr><td><strong>Omise</strong></td><td>บัตรเครดิต, Internet Banking ไทย</td><td>ลูกค้าในไทย</td></tr>
    <tr><td><strong>PayPal</strong></td><td>PayPal, บัตรนานาชาติ</td><td>ลูกค้าต่างประเทศ</td></tr>
    <tr><td><strong>LINE Pay</strong></td><td>ชำระผ่านแอป LINE</td><td>ลูกค้าที่ใช้ LINE</td></tr>
    <tr><td><strong>PromptPay</strong></td><td>QR Code พร้อมเพย์</td><td>ลูกค้าในไทย (ไม่มีค่าธรรมเนียม)</td></tr>
    <tr><td><strong>TrueMoney</strong></td><td>TrueMoney Wallet</td><td>ลูกค้าที่ใช้ TrueMoney</td></tr>
    <tr><td><strong>2C2P</strong></td><td>บัตร, e-wallets, banking (SEA)</td><td>ลูกค้าทั่ว Southeast Asia</td></tr>
  </table>

  <h4>ตัวเลือกแต่ละช่องทาง</h4>
  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>Enable</td><td>เปิด/ปิดช่องทางนี้ (ปิดจะไม่แสดงให้ลูกค้าเลือก)</td></tr>
    <tr><td>Public/Client Key</td><td>Key สาธารณะ ใช้ในฝั่ง frontend</td></tr>
    <tr><td>Secret Key</td><td>Key ลับ ใช้ในฝั่ง server เท่านั้น (เว้นว่างเพื่อไม่เปลี่ยน)</td></tr>
    <tr><td>Sandbox Mode</td><td>เปิด = ใช้ระบบทดสอบ, ปิด = ใช้จริง (production)</td></tr>
  </table>

  <div class="danger-box">
    <i class="bi bi-exclamation-octagon mr-1"></i>
    <strong>สำคัญมาก:</strong> ก่อนเปิดใช้จริง (ปิด Sandbox) ต้องแน่ใจว่าได้ทดสอบในโหมด Sandbox แล้ว และเปลี่ยน keys เป็น production keys แล้ว
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   14. Analytics
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="analytics">
  <h3><i class="bi bi-bar-chart" style="color:#06b6d4;"></i> 14. Analytics & Tracking</h3>
  <p>เชื่อมต่อ Google Analytics และ Facebook Pixel เพื่อติดตามพฤติกรรมผู้เยี่ยมชมเว็บไซต์</p>

  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>Google Analytics ID</td><td>รหัส GA4 เช่น <code>G-XXXXXXXXXX</code></td></tr>
    <tr><td>Facebook Pixel ID</td><td>รหัส Pixel สำหรับ Facebook Ads tracking</td></tr>
    <tr><td>Cookie Consent Required</td><td>แสดงแบนเนอร์ขอความยินยอม cookie ก่อน track</td></tr>
    <tr><td>Anonymize IPs</td><td>ซ่อน IP ของผู้เยี่ยมชมใน Analytics</td></tr>
    <tr><td>Privacy Policy URL</td><td>ลิงก์ไปหน้านโยบายความเป็นส่วนตัว</td></tr>
  </table>
</div>

{{-- ═══════════════════════════════════════════════════
   15. LINE Messaging
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="line">
  <h3><i class="bi bi-chat-dots" style="color:#06c755;"></i> 15. LINE Messaging</h3>
  <p>เชื่อมต่อ LINE Official Account เพื่อส่งแจ้งเตือนเมื่อมีออเดอร์ใหม่ สลิปใหม่ หรือสมาชิกใหม่</p>

  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th></tr>
    <tr><td>LINE Notify Token</td><td>Token สำหรับส่งข้อความแจ้งเตือนผ่าน LINE Notify</td></tr>
  </table>

  <h4>วิธีรับ LINE Notify Token</h4>
  <ol>
    <li>ไปที่ <a href="https://notify-bot.line.me" target="_blank" rel="noopener">LINE Notify</a></li>
    <li>ล็อกอิน > My page > Generate token</li>
    <li>เลือกกลุ่มหรือ "1-on-1 chat with LINE Notify"</li>
    <li>คัดลอก token มาวางในช่อง LINE Notify Token</li>
  </ol>
</div>

{{-- ═══════════════════════════════════════════════════
   16. Webhooks
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="webhooks">
  <h3><i class="bi bi-broadcast" style="color:#8b5cf6;"></i> 16. Webhooks Monitor</h3>
  <p>ตรวจสอบสถานะการรับ-ส่ง webhook จาก payment gateways ต่างๆ ดูว่า webhook ตอบกลับสำเร็จหรือไม่</p>

  <h4>ข้อมูลที่แสดง</h4>
  <ul>
    <li><strong>สถิติรวม</strong> — จำนวน webhook ทั้งหมด, สำเร็จ, ล้มเหลว, รอ retry</li>
    <li><strong>ตารางประวัติ</strong> — วันเวลา, Endpoint, HTTP Status, Response Time</li>
    <li><strong>รายละเอียด</strong> — คลิกขยายแถวเพื่อดู JSON payload เต็ม</li>
  </ul>
</div>

{{-- ═══════════════════════════════════════════════════
   17. Email Logs
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="email-logs">
  <h3><i class="bi bi-envelope-paper" style="color:#3b82f6;"></i> 17. ประวัติอีเมล (Email Logs)</h3>
  <p>ดูประวัติการส่งอีเมลทั้งหมด รวมถึงอีเมลที่ส่งสำเร็จ ล้มเหลว หรือข้าม</p>

  <h4>การกรอง</h4>
  <table class="field-table">
    <tr><th>ตัวกรอง</th><th>ตัวเลือก</th></tr>
    <tr><td>Status</td><td>All / Sent / Failed / Skipped</td></tr>
    <tr><td>Type</td><td>Password Reset / Order Confirmation / Welcome / ฯลฯ</td></tr>
    <tr><td>Search</td><td>ค้นหาจากอีเมลผู้รับหรือหัวข้อ</td></tr>
  </table>

  <div class="tip-box">
    <i class="bi bi-lightbulb mr-1"></i>
    <strong>เคล็ดลับ:</strong> ถ้าเห็นอีเมลสถานะ "Failed" บ่อย ให้ตรวจสอบการตั้งค่า SMTP ในหน้า General (MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD)
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   18. Queue Management
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="queue">
  <h3><i class="bi bi-collection" style="color:#f59e0b;"></i> 18. คิวงาน (Queue Management)</h3>
  <p>จัดการงานที่ทำงานเบื้องหลัง เช่น sync รูปภาพจาก Google Drive, ส่งอีเมล, ประมวลผลรูปภาพ</p>

  <h4>สถานะงาน</h4>
  <table class="field-table">
    <tr><th>สถานะ</th><th style="width:40px;">สี</th><th>คำอธิบาย</th></tr>
    <tr><td>Pending</td><td style="color:#f59e0b;">เหลือง</td><td>รอดำเนินการ</td></tr>
    <tr><td>Running</td><td style="color:#3b82f6;">น้ำเงิน</td><td>กำลังทำงาน</td></tr>
    <tr><td>Completed</td><td style="color:#10b981;">เขียว</td><td>เสร็จแล้ว</td></tr>
    <tr><td>Failed</td><td style="color:#ef4444;">แดง</td><td>ล้มเหลว (จะ retry อัตโนมัติ 3 ครั้ง)</td></tr>
  </table>

  <h4>ปุ่มจัดการ</h4>
  <table class="field-table">
    <tr><th>ปุ่ม</th><th>คำอธิบาย</th></tr>
    <tr><td>Process Now</td><td>สั่งประมวลผลงานที่รอทันที</td></tr>
    <tr><td>Retry All Failed</td><td>รีเซ็ตงานที่ล้มเหลวกลับไปเป็น pending เพื่อลองใหม่</td></tr>
    <tr><td>Clear Completed</td><td>ลบงานที่เสร็จแล้วเกิน 7 วัน</td></tr>
  </table>

  <div class="warn-box">
    <i class="bi bi-exclamation-triangle mr-1"></i>
    <strong>หมายเหตุ:</strong> ในโหมด production ต้องรัน <code>php artisan queue:work</code> หรือใช้ Supervisor เพื่อให้คิวทำงานต่อเนื่อง
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   19. Performance
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="performance">
  <h3><i class="bi bi-speedometer2" style="color:#10b981;"></i> 19. ประสิทธิภาพ (Performance)</h3>
  <p>จัดการ cache และปรับแต่งประสิทธิภาพเว็บไซต์</p>

  <h4>ปุ่มล้าง Cache</h4>
  <table class="field-table">
    <tr><th>ปุ่ม</th><th>คำอธิบาย</th><th>ใช้เมื่อไหร่</th></tr>
    <tr><td>ล้าง Cache ทั้งหมด</td><td>ล้าง cache ทุกประเภทในครั้งเดียว</td><td>หลังอัปเดตโค้ดหรือแก้ไขตั้งค่า</td></tr>
    <tr><td>View Cache</td><td>ล้าง blade template cache</td><td>หลังแก้ไขไฟล์ blade</td></tr>
    <tr><td>Drive Cache</td><td>ล้าง cache ของ Google Drive API</td><td>เมื่อเพิ่มรูปใหม่ในโฟลเดอร์ Drive แล้วไม่แสดง</td></tr>
    <tr><td>Settings Cache</td><td>ล้าง cache ของ app settings</td><td>หลังเปลี่ยนค่าตั้งค่าแล้วไม่มีผล</td></tr>
  </table>

  <h4>การตั้งค่าประสิทธิภาพ</h4>
  <table class="field-table">
    <tr><th>ช่อง</th><th>คำอธิบาย</th><th>ค่าเริ่มต้น</th></tr>
    <tr><td>Lazy Loading</td><td>โหลดรูปเมื่อเลื่อนหน้าจอถึง (ประหยัด bandwidth)</td><td>เปิด</td></tr>
    <tr><td>Image Quality</td><td>คุณภาพการบีบอัดรูป proxy (30-100%)</td><td><code>80%</code></td></tr>
    <tr><td>Cache TTL</td><td>เวลาเก็บ cache (นาที)</td><td><code>60</code></td></tr>
    <tr><td>Cache Grace Period</td><td>เวลาที่ยังแสดง cache เก่าขณะโหลดใหม่ (ชั่วโมง)</td><td><code>24</code></td></tr>
    <tr><td>Gallery Page Size</td><td>จำนวนรูปต่อหน้าใน gallery</td><td><code>50</code></td></tr>
    <tr><td>Minify HTML</td><td>บีบอัด HTML ลดขนาดหน้าเว็บ</td><td>ปิด</td></tr>
  </table>
</div>

{{-- ═══════════════════════════════════════════════════
   20. Backup
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="backup">
  <h3><i class="bi bi-download" style="color:#6366f1;"></i> 20. สำรองข้อมูล (Backup)</h3>
  <p>สร้างไฟล์สำรองฐานข้อมูล (SQL dump) เพื่อกู้คืนได้ในกรณีเกิดปัญหา</p>

  <h4>วิธีใช้</h4>
  <ol>
    <li>คลิก <strong>"สำรองเดี๋ยวนี้"</strong></li>
    <li>ระบบจะสร้างไฟล์ .sql และแสดงในรายการด้านล่าง</li>
    <li>คลิกชื่อไฟล์เพื่อดาวน์โหลดเก็บไว้</li>
  </ol>

  <div class="warn-box">
    <i class="bi bi-exclamation-triangle mr-1"></i>
    <strong>คำแนะนำ:</strong> ควรสำรองข้อมูลอย่างน้อยสัปดาห์ละ 1 ครั้ง และเก็บไฟล์ backup ไว้นอกเซิร์ฟเวอร์ (เช่น Google Drive, Dropbox)
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   21. System Reset
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="reset">
  <h3><i class="bi bi-arrow-counterclockwise" style="color:#ef4444;"></i> 21. รีเซ็ตข้อมูล (System Reset)</h3>
  <p>ลบข้อมูลบางส่วนออกจากระบบ ใช้ในกรณีต้องการเริ่มต้นใหม่หรือล้างข้อมูลทดสอบ</p>

  <h4>ตัวเลือกรีเซ็ต</h4>
  <table class="field-table">
    <tr><th>รายการ</th><th>สิ่งที่จะถูกลบ</th></tr>
    <tr><td><strong>Reset Orders</strong></td><td>ออเดอร์, รายการสั่งซื้อ, สลิป, ธุรกรรม, refunds, download tokens, payouts ทั้งหมด</td></tr>
    <tr><td><strong>Reset Photo Cache</strong></td><td>ล้าง cache รูปภาพจาก Google Drive ทั้งหมด (รูปจริงไม่หาย)</td></tr>
    <tr><td><strong>Reset Event Views</strong></td><td>รีเซ็ตจำนวนเข้าชมอีเวนต์ทั้งหมดเป็น 0</td></tr>
    <tr><td><strong>Clear Notifications</strong></td><td>ลบข้อความแจ้งเตือนเก่า</td></tr>
    <tr><td><strong>Clear Security Logs</strong></td><td>ลบประวัติ login attempts และ security logs</td></tr>
  </table>

  <div class="danger-box">
    <i class="bi bi-exclamation-octagon mr-1"></i>
    <strong>คำเตือน:</strong> การรีเซ็ตข้อมูล <strong>ไม่สามารถย้อนกลับได้!</strong> ต้องพิมพ์ "RESET" เพื่อยืนยัน ควรสำรองข้อมูลก่อนทุกครั้ง
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
   22. Version Info
═══════════════════════════════════════════════════ --}}
<div class="guide-section" id="version">
  <h3><i class="bi bi-info-circle" style="color:#6366f1;"></i> 22. ข้อมูลเวอร์ชัน (Version Info)</h3>
  <p>แสดงข้อมูลระบบและเวอร์ชันของซอฟต์แวร์ที่ใช้ ใช้ส่งให้ทีมพัฒนาเมื่อพบปัญหา</p>

  <h4>ข้อมูลที่แสดง</h4>
  <table class="field-table">
    <tr><th>หมวด</th><th>รายละเอียด</th></tr>
    <tr><td>Application</td><td>ชื่อ, เวอร์ชัน, Laravel version, Environment (production/local)</td></tr>
    <tr><td>Server</td><td>PHP version, Web Server, OS, Database version</td></tr>
    <tr><td>Extensions</td><td>GD Library, cURL, memory_limit, upload_max_filesize, max_execution_time</td></tr>
    <tr><td>Version History</td><td>ประวัติการอัปเดต, Changelog</td></tr>
  </table>
</div>

{{-- Back to top --}}
<button class="back-to-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="กลับด้านบน">
  <i class="bi bi-arrow-up"></i>
</button>

</div>{{-- /guide-container --}}
@endsection
