@extends('emails.layout', ['title' => 'ตอบกลับคำถามของคุณ', 'preheader' => 'ทีมงานได้ตอบกลับข้อความของคุณแล้ว'])

@section('slot')
<h2>ตอบกลับคำถามของคุณ 💬</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ขอบคุณที่ติดต่อเรา! ทีมงาน <strong>{{ $siteName }}</strong> ได้ตอบกลับข้อความของคุณแล้ว</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">หัวข้อ</span>
    <span class="value">{{ $subject }}</span>
  </div>
  @if(!empty($ticketId))
  <div class="info-row">
    <span class="label">หมายเลขเคส</span>
    <span class="value">#{{ $ticketId }}</span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">ตอบกลับโดย</span>
    <span class="value">{{ $repliedBy ?? 'ทีมงาน' }}</span>
  </div>
  <div class="info-row">
    <span class="label">วันที่ตอบกลับ</span>
    <span class="value">{{ $repliedAt ?? now()->format('d/m/Y H:i') }}</span>
  </div>
</div>

<h3>📩 คำตอบของเรา</h3>

<div class="alert-box">
  {!! nl2br(e($replyMessage)) !!}
</div>

@if(!empty($originalMessage))
<div class="divider"></div>
<h3 style="font-size:14px;color:#9ca3af;">📝 ข้อความเดิมของคุณ</h3>
<div style="background:#f8f9fa;padding:14px 18px;border-radius:8px;font-size:13px;color:#6b7280;">
  {!! nl2br(e($originalMessage)) !!}
</div>
@endif

<div class="divider"></div>

<p>หากคุณมีข้อสงสัยเพิ่มเติม สามารถตอบกลับอีเมลนี้ หรือติดต่อเราได้ทุกเมื่อ</p>

@if(!empty($contactUrl))
<div class="btn-wrap">
  <a href="{{ $contactUrl }}" class="btn btn-outline">ติดต่อเราอีกครั้ง</a>
</div>
@endif

<p>ขอบคุณที่ใช้บริการ <strong>{{ $siteName }}</strong>! 🙏</p>
@endsection
