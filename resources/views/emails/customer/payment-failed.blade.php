@extends('emails.layout', ['title' => 'การชำระเงินไม่สำเร็จ', 'preheader' => 'คำสั่งซื้อ #' . ($orderNumber ?? $orderId)])

@section('slot')
<h2>การชำระเงินไม่สำเร็จ ❌</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ขออภัยที่การชำระเงินของคุณไม่สำเร็จ กรุณาตรวจสอบรายละเอียดด้านล่าง:</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">เลขที่คำสั่งซื้อ</span>
    <span class="value">#{{ $orderNumber ?? $orderId }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดที่ต้องชำระ</span>
    <span class="value">฿{{ number_format((float)$total, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">วิธีชำระเงิน</span>
    <span class="value">{{ $paymentMethod ?? 'ไม่ระบุ' }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-danger">ชำระเงินไม่สำเร็จ</span></span>
  </div>
  @if(!empty($failureReason))
  <div class="info-row">
    <span class="label">สาเหตุ</span>
    <span class="value">{{ $failureReason }}</span>
  </div>
  @endif
</div>

<div class="alert-box danger">
  <p><strong>สาเหตุที่เป็นไปได้:</strong></p>
  <p style="margin-top:8px;">
    • ยอดเงินในบัญชี/บัตรไม่เพียงพอ<br>
    • ข้อมูลการชำระเงินไม่ถูกต้อง<br>
    • การเชื่อมต่อกับธนาคารขาดหาย<br>
    • บัตรเครดิตหมดอายุหรือถูกบล็อก
  </p>
</div>

@if(!empty($retryUrl))
<div class="btn-wrap">
  <a href="{{ $retryUrl }}" class="btn btn-danger">🔄 ลองชำระเงินอีกครั้ง</a>
</div>
@endif

<p><strong>สิ่งที่ควรทำต่อไป:</strong></p>
<ol style="color:#4b5563;line-height:1.8;">
  <li>ตรวจสอบยอดเงินในบัญชีหรือบัตรของคุณ</li>
  <li>ลองชำระเงินใหม่ หรือเลือกวิธีชำระเงินอื่น</li>
  <li>หากปัญหายังคงอยู่ กรุณาติดต่อเราเพื่อขอความช่วยเหลือ</li>
</ol>

<p style="font-size:13px;color:#6b7280;">
  💡 คำสั่งซื้อของคุณจะถูกเก็บไว้ 24 ชั่วโมง สามารถกลับมาชำระเงินได้ตลอดเวลา
</p>
@endsection
