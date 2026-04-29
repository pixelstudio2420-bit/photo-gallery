@include('emails.base', ['title' => 'ยืนยันอีเมล'])

@section('email-content')
<div class="email-body">
  <h2 style="margin:0 0 8px;font-size:20px;">สวัสดี {{ $name }},</h2>
  <p style="color:#6b7280;font-size:15px;line-height:1.6;">
    กรุณากดปุ่มด้านล่างเพื่อยืนยันอีเมลของคุณ
  </p>

  <div style="text-align:center;margin:28px 0;">
    <a href="{{ $verifyUrl }}" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-4 py-2 rounded-lg transition" style="color:#fff !important;">
      ยืนยันอีเมล
    </a>
  </div>

  <div class="info-box">
    <p style="margin:0;font-size:13px;color:#6b7280;">
      ลิงก์นี้จะหมดอายุภายใน 24 ชั่วโมง หากคุณไม่ได้สมัครสมาชิก กรุณาเพิกเฉยอีเมลนี้
    </p>
  </div>
</div>
@endsection
