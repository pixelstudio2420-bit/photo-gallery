@extends('emails.layout', ['title' => 'รีวิวประสบการณ์ของคุณ', 'preheader' => 'ช่วยเราพัฒนาบริการด้วยการให้คะแนน'])

@section('slot')
<h2>ช่วยเราให้ดีขึ้นด้วยรีวิวของคุณ! ⭐</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ขอบคุณที่ใช้บริการของเรา! เราอยากทราบความคิดเห็นของคุณเกี่ยวกับ:</p>

<div class="info-box">
  @if(!empty($eventName))
  <div class="info-row">
    <span class="label">อีเวนต์</span>
    <span class="value">{{ $eventName }}</span>
  </div>
  @endif
  @if(!empty($photographerName))
  <div class="info-row">
    <span class="label">ช่างภาพ</span>
    <span class="value">{{ $photographerName }}</span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">เลขที่คำสั่งซื้อ</span>
    <span class="value">#{{ $orderNumber ?? $orderId }}</span>
  </div>
</div>

<div class="alert-box">
  <p>⭐ <strong>การรีวิวของคุณช่วย:</strong></p>
  <p style="margin-top:6px;">
    • ช่วยผู้ซื้อคนอื่นตัดสินใจได้ง่ายขึ้น<br>
    • สนับสนุนช่างภาพที่ทำงานดี<br>
    • ช่วยเราพัฒนาบริการให้ดียิ่งขึ้น
  </p>
</div>

<div class="btn-wrap">
  <a href="{{ $reviewUrl }}" class="btn btn-warning">⭐ เขียนรีวิวเลย</a>
</div>

<p style="font-size:14px;color:#6b7280;">ใช้เวลาไม่ถึง 1 นาที — เพียงให้คะแนนดาวและเขียนความคิดเห็นสั้นๆ</p>

<p>🎁 <strong>พิเศษ:</strong> เขียนรีวิวครั้งแรกรับส่วนลด 10% สำหรับการซื้อครั้งต่อไป!</p>
@endsection
