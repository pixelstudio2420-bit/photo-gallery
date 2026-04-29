@extends('emails.layout', ['title' => 'Test Email', 'preheader' => 'การตั้งค่าอีเมลของคุณทำงานถูกต้อง'])

@section('slot')
<h2>✅ Test Email สำเร็จ!</h2>

<p>สวัสดี Admin,</p>

<p>นี่คืออีเมลทดสอบจาก <strong>{{ $siteName }}</strong> เพื่อยืนยันว่าการตั้งค่าอีเมลของคุณทำงานถูกต้อง</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">ส่งไปยัง</span>
    <span class="value">{{ $to }}</span>
  </div>
  <div class="info-row">
    <span class="label">Driver</span>
    <span class="value">{{ $driver ?? 'N/A' }}</span>
  </div>
  <div class="info-row">
    <span class="label">เวลาส่ง</span>
    <span class="value">{{ $sentAt ?? now()->format('Y-m-d H:i:s') }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-success">✓ OK</span></span>
  </div>
</div>

<div class="alert-box success">
  <p>🎉 หากคุณได้รับอีเมลนี้ แสดงว่าการตั้งค่าอีเมลของคุณพร้อมใช้งานแล้ว!</p>
</div>

<h3>📝 รายการตรวจสอบ</h3>

<div class="info-box">
  <p style="margin:4px 0;">✅ SMTP ทำงานได้</p>
  <p style="margin:4px 0;">✅ Template rendering ถูกต้อง</p>
  <p style="margin:4px 0;">✅ ตัวอักษรไทยแสดงผลได้</p>
  <p style="margin:4px 0;">✅ รูปแบบ Responsive ใช้งานได้</p>
</div>

<p style="font-size:13px;color:#6b7280;">หากพบปัญหาใดๆ กรุณาตรวจสอบการตั้งค่า SMTP ในหน้า Admin → Settings → Mail</p>
@endsection
