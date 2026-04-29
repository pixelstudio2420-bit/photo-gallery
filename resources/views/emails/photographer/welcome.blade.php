@extends('emails.layout', ['title' => 'ยินดีต้อนรับช่างภาพ', 'preheader' => 'บัญชีช่างภาพของคุณกำลังรอการอนุมัติ'])

@section('slot')
<h2>ยินดีต้อนรับสู่ครอบครัวช่างภาพ! 📸</h2>

<p>สวัสดีช่างภาพ <strong>{{ $name }}</strong>,</p>

<p>ขอบคุณที่สมัครเป็นช่างภาพกับ <strong>{{ $siteName }}</strong>! เรายินดีต้อนรับคุณ ✨</p>

<div class="alert-box warning">
  <p>⏳ <strong>บัญชีของคุณอยู่ระหว่างการตรวจสอบ</strong></p>
  <p style="margin-top:6px;">ทีมงานจะตรวจสอบข้อมูลและอนุมัติภายใน <strong>24-48 ชั่วโมง</strong></p>
</div>

<h3>🎯 สิ่งที่คุณจะได้รับเมื่อบัญชีได้รับการอนุมัติ</h3>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">💰 รายได้</span>
    <span class="value">{{ $commissionRate ?? 70 }}% ของยอดขาย</span>
  </div>
  <div class="info-row">
    <span class="label">📊 Dashboard</span>
    <span class="value">ติดตามยอดขาย สถิติเชิงลึก</span>
  </div>
  <div class="info-row">
    <span class="label">🖼️ อัพโหลด</span>
    <span class="value">ไม่จำกัดจำนวนภาพ/อีเวนต์</span>
  </div>
  <div class="info-row">
    <span class="label">💳 จ่ายเงิน</span>
    <span class="value">รายเดือน / ขั้นต่ำ 1,000฿</span>
  </div>
</div>

<h3>📝 ขั้นตอนต่อไปที่คุณต้องเตรียม</h3>

<ol style="color:#4b5563;line-height:1.8;">
  <li><strong>เตรียมตัวอย่างผลงาน</strong> - ภาพ 10-20 ภาพในสไตล์ที่ถนัด</li>
  <li><strong>เตรียมบัญชีธนาคาร</strong> - สำหรับรับเงินค่าคอมมิชชั่น</li>
  <li><strong>บัตรประชาชน</strong> - สำเนาพร้อมลายเซ็น (สำหรับยืนยันตัวตน)</li>
  <li><strong>ข้อมูลภาษี</strong> - เลขผู้เสียภาษี (ถ้ามี)</li>
</ol>

<div class="btn-wrap">
  <a href="{{ $dashboardUrl }}" class="btn">เข้าสู่ Dashboard</a>
</div>

<p>หากมีข้อสงสัย ทีมงานพร้อมช่วยเหลือคุณเสมอ!</p>

<p>ขอให้สนุกกับการร่วมงานครับ! 📸✨</p>
@endsection
