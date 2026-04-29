@extends('emails.layout', ['title' => 'แจ้งเตือน: รูปภาพจะถูกลบอัตโนมัติ', 'preheader' => 'อีเวนต์ใกล้หมดอายุ ลบภายใน 24 ชั่วโมง'])

@section('slot')
<h2>⏰ แจ้งเตือน: รูปในอีเวนต์ใกล้ถูกลบอัตโนมัติ</h2>

<p>สวัสดีคุณ <strong>{{ $name }}</strong>,</p>

<p>รูปภาพในอีเวนต์ของคุณจะถูกลบอัตโนมัติตามนโยบาย retention ของเว็บไซต์ <strong>{{ $siteName }}</strong> หากไม่ดำเนินการ</p>

<div class="alert-box warning">
  <p>⚠️ <strong>รูปภาพจะถูกลบภายใน {{ $hoursLeft }} ชั่วโมง</strong></p>
  <p style="margin:4px 0;">หลังลบแล้วไม่สามารถกู้คืนได้</p>
</div>

<h3>📋 อีเวนต์ที่ได้รับผลกระทบ</h3>

<div class="info-box">
  @foreach($events as $evt)
    <p style="margin:8px 0; padding:8px; background:#fafafa; border-radius:6px;">
      <strong>📸 {{ $evt['name'] }}</strong><br>
      <span style="color:#666; font-size:13px;">
        ลบเมื่อ: {{ $evt['delete_at'] }} &middot; รูปภาพ: {{ $evt['photo_count'] }} รูป
      </span>
    </p>
  @endforeach
</div>

<h3>💡 วิธีป้องกัน</h3>

<div class="info-box highlight">
  <p style="margin:4px 0;"><strong>1. ต่ออายุ retention</strong> — เข้าไปแก้ไขอีเวนต์ แล้วตั้งค่า <em>Keep Days</em> เพิ่ม</p>
  <p style="margin:4px 0;"><strong>2. ติดธง "ห้ามลบ"</strong> — เปิด <em>Auto-Delete Exempt</em> ในหน้าแก้ไขอีเวนต์</p>
  <p style="margin:4px 0;"><strong>3. อัปเกรดแพ็กเกจ</strong> — Seller/Pro ได้ retention นานขึ้น 4-12 เท่าอัตโนมัติ</p>
</div>

<div class="btn-wrap">
  <a href="{{ $dashboardUrl }}" class="btn btn-warning">🛠 จัดการอีเวนต์</a>
</div>

<p style="color:#777; font-size:13px; margin-top:20px;">
  💬 ถ้าต้องการเก็บรูปไว้นานกว่านี้ ลองอัปเกรดเป็น <strong>Seller</strong> (฿299/เดือน) หรือ <strong>Pro</strong> (฿999/เดือน) —
  เก็บได้นานกว่าและจ่าย commission น้อยกว่า <a href="{{ $upgradeUrl ?? url('/photographer/upgrade') }}">ดูแพ็กเกจ</a>
</p>
@endsection
