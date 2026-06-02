@extends('emails.layout', ['title' => 'บัญชีของคุณจะถูกลบ', 'preheader' => 'ล็อกอินภายใน ' . ($warnDays ?? 30) . ' วันเพื่อรักษาบัญชี'])

@section('slot')
<h2>⏰ บัญชีของคุณกำลังจะถูกลบ</h2>

<p>สวัสดีคุณ <strong>{{ $name }}</strong>,</p>

<p>เราตรวจพบว่าบัญชีของคุณบน <strong>{{ $siteName }}</strong> ไม่ได้ใช้งานมาเป็นเวลานาน — เข้าระบบล่าสุดเมื่อ {{ $lastLoginAt ?? 'ไม่เคย' }}</p>

<div class="alert-box warning">
  <p>⚠️ <strong>บัญชีของคุณจะถูกลบในวันที่ {{ $deleteAt }}</strong> ({{ $warnDays }} วันจากนี้)</p>
  <p style="margin:4px 0;">รวมถึงอีเวนต์ทั้งหมด รูปภาพ และข้อมูลที่เกี่ยวข้อง — ไม่สามารถกู้คืนได้</p>
</div>

<h3>💡 วิธีรักษาบัญชี</h3>

<div class="info-box highlight">
  <p><strong>เพียงล็อกอินบัญชีของคุณ</strong> — ระบบจะรีเซ็ตระยะเวลาทันที</p>
  <p style="margin:8px 0 0 0;">หรือ <strong>อัปเกรดเป็นแผน Pro/Seller</strong> เพื่อยกเว้นการลบอัตโนมัติถาวร</p>
</div>

<div style="text-align:center; margin:24px 0;">
  <a href="{{ $dashboardUrl }}" class="btn-primary" style="display:inline-block; padding:12px 28px; background:#6366f1; color:#fff; border-radius:8px; text-decoration:none; font-weight:600;">
    เข้าสู่ระบบทันที
  </a>
  <a href="{{ $upgradeUrl }}" style="display:inline-block; padding:12px 28px; background:transparent; color:#6366f1; border:1px solid #6366f1; border-radius:8px; text-decoration:none; font-weight:600; margin-left:8px;">
    ดูแผนอัปเกรด
  </a>
</div>

<p style="font-size:13px; color:#666;">
  หากมีคำถามหรือพบปัญหา ติดต่อทีมงานได้ที่ <a href="{{ url('/contact') }}">/contact</a>
</p>
@endsection
