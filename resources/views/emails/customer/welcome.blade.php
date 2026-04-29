@extends('emails.layout', ['title' => 'ยินดีต้อนรับ', 'preheader' => 'ขอบคุณที่ร่วมเป็นส่วนหนึ่งกับเรา'])

@section('slot')
<h2>ยินดีต้อนรับสู่ {{ $siteName }}! 🎉</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ขอบคุณที่สมัครสมาชิกกับ <strong>{{ $siteName }}</strong> — เว็บไซต์ถ่ายภาพอีเวนต์ชั้นนำของไทย!</p>

<p>ตอนนี้คุณสามารถ:</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">✨ ค้นหาภาพ</span>
    <span class="value">จากอีเวนต์มากมายทั่วประเทศ</span>
  </div>
  <div class="info-row">
    <span class="label">📸 ดาวน์โหลด</span>
    <span class="value">ภาพคุณภาพสูงได้ทันที</span>
  </div>
  <div class="info-row">
    <span class="label">❤️ Wishlist</span>
    <span class="value">บันทึกภาพที่ชอบไว้ดูภายหลัง</span>
  </div>
  <div class="info-row">
    <span class="label">🤖 AI Face Search</span>
    <span class="value">ค้นหาภาพตัวเองจากใบหน้า</span>
  </div>
</div>

<div class="btn-wrap">
  <a href="{{ $loginUrl }}" class="btn">เริ่มต้นใช้งาน</a>
</div>

<div class="alert-box">
  <p>💡 <strong>เคล็ดลับ:</strong> เปิดใช้งาน 2FA ใน Profile เพื่อความปลอดภัยของบัญชี</p>
</div>

<p>หากมีข้อสงสัย หรือต้องการความช่วยเหลือ สามารถติดต่อเราได้ตลอดเวลา</p>

<p>ขอให้สนุกกับการค้นพบภาพสวยๆ นะครับ! 📷✨</p>
@endsection
