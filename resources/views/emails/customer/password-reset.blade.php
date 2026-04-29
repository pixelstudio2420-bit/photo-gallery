@extends('emails.layout', ['title' => 'รีเซ็ตรหัสผ่าน', 'preheader' => 'คำขอรีเซ็ตรหัสผ่านของคุณ'])

@section('slot')
<h2>รีเซ็ตรหัสผ่าน 🔐</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>เราได้รับคำขอรีเซ็ตรหัสผ่านสำหรับบัญชีของคุณที่ <strong>{{ $siteName }}</strong></p>

<div class="btn-wrap">
  <a href="{{ $resetUrl }}" class="btn btn-danger">รีเซ็ตรหัสผ่าน</a>
</div>

<div class="alert-box warning">
  <p>⏰ <strong>ลิงก์นี้จะหมดอายุใน 60 นาที</strong> เพื่อความปลอดภัย</p>
</div>

<div class="alert-box danger">
  <p>⚠️ หากคุณ<strong>ไม่ได้</strong>ขอรีเซ็ตรหัสผ่าน กรุณา:</p>
  <p style="margin-top:8px;">
    1. ละเว้นอีเมลนี้ (รหัสผ่านจะไม่เปลี่ยนแปลง)<br>
    2. เปลี่ยนรหัสผ่านของคุณเพื่อความปลอดภัย<br>
    3. ตรวจสอบการเข้าสู่ระบบล่าสุด
  </p>
</div>

<p style="font-size:12px;color:#9ca3af;">หากปุ่มไม่ทำงาน คัดลอก URL นี้ไปวางในเบราว์เซอร์:</p>
<p style="font-size:11px;word-break:break-all;color:#6366f1;">{{ $resetUrl }}</p>
@endsection
