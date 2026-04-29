@extends('emails.layout', ['title' => 'ยืนยันอีเมล', 'preheader' => 'คลิกเพื่อยืนยันอีเมลของคุณ'])

@section('slot')
<h2>ยืนยันอีเมลของคุณ ✉️</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ขอบคุณที่สมัครสมาชิก! เพียงคลิกปุ่มด้านล่างเพื่อยืนยันอีเมลและเปิดใช้งานบัญชีของคุณ</p>

<div class="btn-wrap">
  <a href="{{ $verifyUrl }}" class="btn btn-success">✓ ยืนยันอีเมล</a>
</div>

<div class="alert-box warning">
  <p>⏰ <strong>ลิงก์นี้จะหมดอายุใน 60 นาที</strong></p>
</div>

<p>หากปุ่มไม่สามารถกดได้ ให้คัดลอก URL นี้ไปวางในเบราว์เซอร์:</p>

<div class="info-box">
  <p style="margin:0;word-break:break-all;color:#6366f1;font-size:12px;">{{ $verifyUrl }}</p>
</div>

<p style="font-size:13px;color:#6b7280;">หากคุณไม่ได้สมัครสมาชิก สามารถละเว้นอีเมลนี้ได้</p>
@endsection
