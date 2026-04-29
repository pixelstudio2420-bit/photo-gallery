@extends('emails.layout', ['title' => 'ลูกค้ารีวิวคุณ!', 'preheader' => 'รีวิวใหม่ ' . str_repeat('⭐', (int)($rating ?? 5))])

@section('slot')
<h2>⭐ มีรีวิวใหม่!</h2>

<p>สวัสดีช่างภาพ <strong>{{ $name }}</strong>,</p>

<p>🎉 ลูกค้าได้รีวิวผลงานของคุณแล้ว!</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">คะแนน</span>
    <span class="value" style="font-size:18px;">
      @for($i = 1; $i <= 5; $i++)
        @if($i <= ($rating ?? 0))⭐@else☆@endif
      @endfor
      ({{ $rating ?? 0 }}/5)
    </span>
  </div>
  <div class="info-row">
    <span class="label">ลูกค้า</span>
    <span class="value">{{ $customerName ?? 'ผู้ใช้' }}</span>
  </div>
  @if(!empty($eventName))
  <div class="info-row">
    <span class="label">อีเวนต์</span>
    <span class="value">{{ $eventName }}</span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">วันที่รีวิว</span>
    <span class="value">{{ $reviewDate ?? now()->format('d/m/Y H:i') }}</span>
  </div>
</div>

@if(!empty($comment))
<h3>💬 ความคิดเห็น</h3>
<div class="alert-box">
  <p style="margin:0;font-style:italic;font-size:15px;">"{{ $comment }}"</p>
</div>
@endif

@if(($rating ?? 0) >= 4)
<div class="alert-box success">
  <p>👏 <strong>ทำได้ดีมาก!</strong> รีวิวดีๆ แบบนี้ช่วยให้คุณขายภาพได้มากขึ้น</p>
</div>
@elseif(($rating ?? 0) <= 3)
<div class="alert-box warning">
  <p>💡 <strong>ลองดูความคิดเห็นของลูกค้า</strong> เพื่อพัฒนาและปรับปรุงบริการ</p>
</div>
@endif

@if(!empty($replyUrl))
<div class="btn-wrap">
  <a href="{{ $replyUrl }}" class="btn btn-warning">💬 ตอบกลับรีวิว</a>
</div>
@endif

<p style="font-size:13px;color:#6b7280;">💡 การตอบกลับรีวิวเป็นมารยาทที่ดี และช่วยสร้างความน่าเชื่อถือ</p>
@endsection
