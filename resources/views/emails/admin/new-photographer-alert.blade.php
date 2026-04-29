@extends('emails.layout', ['title' => 'ช่างภาพใหม่สมัครเข้ามา', 'preheader' => 'รอการอนุมัติจาก Admin'])

@section('slot')
<h2>📸 มีช่างภาพใหม่สมัครเข้ามา</h2>

<p>สวัสดี Admin,</p>

<p>มีช่างภาพสมัครเข้ามาใหม่ในระบบ และกำลังรอการอนุมัติจากคุณ</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">ชื่อ-นามสกุล</span>
    <span class="value">{{ $photographerName }}</span>
  </div>
  <div class="info-row">
    <span class="label">อีเมล</span>
    <span class="value">{{ $email }}</span>
  </div>
  <div class="info-row">
    <span class="label">เบอร์โทร</span>
    <span class="value">{{ $phone ?? 'N/A' }}</span>
  </div>
  @if(!empty($portfolioUrl))
  <div class="info-row">
    <span class="label">Portfolio</span>
    <span class="value"><a href="{{ $portfolioUrl }}">ดูผลงาน</a></span>
  </div>
  @endif
  @if(!empty($bio))
  <div class="info-row">
    <span class="label">Bio</span>
    <span class="value">{{ Str::limit($bio, 100) }}</span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">วันที่สมัคร</span>
    <span class="value">{{ $registeredAt ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-warning">รอการอนุมัติ</span></span>
  </div>
</div>

<div class="alert-box warning">
  <p><strong>📋 สิ่งที่ควรตรวจสอบ:</strong></p>
  <p style="margin-top:6px;">
    ✓ ข้อมูลส่วนตัวครบถ้วน<br>
    ✓ เอกสารยืนยันตัวตน (บัตรประชาชน)<br>
    ✓ ตัวอย่างผลงาน (Portfolio)<br>
    ✓ ข้อมูลบัญชีธนาคาร
  </p>
</div>

<div class="btn-wrap">
  <a href="{{ $adminReviewUrl }}" class="btn">✅ ตรวจสอบใน Admin</a>
</div>

<p style="font-size:13px;color:#6b7280;">💡 แนะนำให้อนุมัติภายใน 48 ชั่วโมงเพื่อความประทับใจของผู้สมัคร</p>
@endsection
