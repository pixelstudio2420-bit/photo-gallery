@extends('emails.layout', ['title' => 'มีข้อความใหม่จากลูกค้า', 'preheader' => 'ข้อความจาก ' . ($senderName ?? 'ผู้ใช้')])

@section('slot')
<h2>📨 มีข้อความใหม่จากลูกค้า</h2>

<p>สวัสดี Admin,</p>

<p>มีผู้ใช้ติดต่อผ่านฟอร์ม Contact Us ของเว็บไซต์</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">จาก</span>
    <span class="value">{{ $senderName ?? 'ไม่ระบุ' }}</span>
  </div>
  <div class="info-row">
    <span class="label">อีเมล</span>
    <span class="value"><a href="mailto:{{ $senderEmail }}">{{ $senderEmail ?? 'N/A' }}</a></span>
  </div>
  @if(!empty($senderPhone))
  <div class="info-row">
    <span class="label">เบอร์โทร</span>
    <span class="value">{{ $senderPhone }}</span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">หัวข้อ</span>
    <span class="value">{{ $subject ?? 'ไม่ระบุ' }}</span>
  </div>
  @if(!empty($category))
  <div class="info-row">
    <span class="label">หมวดหมู่</span>
    <span class="value"><span class="badge badge-info">{{ $category }}</span></span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">เวลา</span>
    <span class="value">{{ $sentAt ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  @if(!empty($ipAddress))
  <div class="info-row">
    <span class="label">IP Address</span>
    <span class="value">{{ $ipAddress }}</span>
  </div>
  @endif
</div>

<h3>💬 ข้อความ</h3>

<div class="alert-box">
  {!! nl2br(e($message)) !!}
</div>

<div class="btn-wrap">
  <a href="{{ $adminMessageUrl }}" class="btn">📮 ตอบกลับใน Admin</a>
</div>

<p style="font-size:13px;color:#6b7280;">💡 แนะนำให้ตอบกลับภายใน 24 ชั่วโมงเพื่อรักษาความพึงพอใจของลูกค้า</p>
@endsection
