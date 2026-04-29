@extends('emails.layout', ['title' => 'แจ้งเตือนความปลอดภัย', 'preheader' => 'ตรวจพบเหตุการณ์ที่ต้องให้ความสนใจ'])

@section('slot')
<h2>🚨 แจ้งเตือนความปลอดภัย</h2>

<p>สวัสดี Admin,</p>

<p>ระบบตรวจพบเหตุการณ์ที่อาจเกี่ยวข้องกับความปลอดภัย กรุณาตรวจสอบ</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">ประเภท</span>
    <span class="value">
      @switch($alertType ?? 'unknown')
        @case('failed_login')<span class="badge badge-danger">Failed Login</span>@break
        @case('brute_force')<span class="badge badge-danger">Brute Force</span>@break
        @case('suspicious_ip')<span class="badge badge-warning">Suspicious IP</span>@break
        @case('rate_limit')<span class="badge badge-warning">Rate Limit</span>@break
        @case('admin_login_new_location')<span class="badge badge-warning">New Admin Login Location</span>@break
        @default<span class="badge badge-info">{{ $alertType ?? 'Unknown' }}</span>
      @endswitch
    </span>
  </div>
  <div class="info-row">
    <span class="label">ระดับความรุนแรง</span>
    <span class="value">
      @switch($severity ?? 'medium')
        @case('critical')<span class="badge badge-danger">🔴 Critical</span>@break
        @case('high')<span class="badge badge-danger">🟠 High</span>@break
        @case('medium')<span class="badge badge-warning">🟡 Medium</span>@break
        @case('low')<span class="badge badge-info">🔵 Low</span>@break
        @default<span class="badge badge-info">{{ $severity }}</span>
      @endswitch
    </span>
  </div>
  @if(!empty($ipAddress))
  <div class="info-row">
    <span class="label">IP Address</span>
    <span class="value">{{ $ipAddress }}</span>
  </div>
  @endif
  @if(!empty($country))
  <div class="info-row">
    <span class="label">ประเทศ</span>
    <span class="value">{{ $country }}</span>
  </div>
  @endif
  @if(!empty($userAgent))
  <div class="info-row">
    <span class="label">User Agent</span>
    <span class="value" style="font-size:12px;">{{ Str::limit($userAgent, 60) }}</span>
  </div>
  @endif
  @if(!empty($attemptedAccount))
  <div class="info-row">
    <span class="label">บัญชีที่ถูกเข้าถึง</span>
    <span class="value">{{ $attemptedAccount }}</span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">เวลา</span>
    <span class="value">{{ $detectedAt ?? now()->format('d/m/Y H:i:s') }}</span>
  </div>
</div>

@if(!empty($description))
<h3>📋 รายละเอียด</h3>
<div class="alert-box danger">
  <p style="margin:0;">{{ $description }}</p>
</div>
@endif

<h3>🛡️ การดำเนินการที่แนะนำ</h3>

<div class="info-box">
  @if(($alertType ?? '') === 'brute_force')
    <p style="margin:4px 0;">🚫 Block IP address ชั่วคราว</p>
    <p style="margin:4px 0;">🔒 บังคับเปลี่ยนรหัสผ่านบัญชีที่ถูกโจมตี</p>
    <p style="margin:4px 0;">📝 ตรวจสอบ Activity Log ของบัญชีนั้นๆ</p>
  @elseif(($alertType ?? '') === 'failed_login')
    <p style="margin:4px 0;">🔍 ตรวจสอบว่าเป็นเจ้าของบัญชีจริงหรือไม่</p>
    <p style="margin:4px 0;">📧 แจ้งเตือนเจ้าของบัญชี</p>
  @elseif(($alertType ?? '') === 'admin_login_new_location')
    <p style="margin:4px 0;">✅ ยืนยันว่าเป็นคุณหรือไม่</p>
    <p style="margin:4px 0;">🔐 เปลี่ยนรหัสผ่านทันทีถ้าไม่ใช่</p>
    <p style="margin:4px 0;">🛡️ เปิดใช้งาน 2FA</p>
  @else
    <p style="margin:4px 0;">🔍 ตรวจสอบ Activity Log</p>
    <p style="margin:4px 0;">🛡️ ตรวจสอบ Firewall rules</p>
  @endif
</div>

<div class="btn-wrap">
  <a href="{{ $securityDashboardUrl }}" class="btn btn-danger">🚨 ตรวจสอบ Security Dashboard</a>
</div>

<p style="font-size:12px;color:#9ca3af;">อีเมลนี้ถูกส่งโดยระบบ Security Monitoring</p>
@endsection
