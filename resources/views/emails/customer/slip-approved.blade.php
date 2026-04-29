@extends('emails.layout', ['title' => 'สลิปโอนเงินได้รับการอนุมัติ', 'preheader' => 'คำสั่งซื้อ #' . ($orderNumber ?? $orderId)])

@section('slot')
<h2>สลิปโอนเงินได้รับการอนุมัติแล้ว! ✅</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ข่าวดี! สลิปโอนเงินของคุณได้รับการตรวจสอบและอนุมัติเรียบร้อยแล้ว</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">เลขที่คำสั่งซื้อ</span>
    <span class="value">#{{ $orderNumber ?? $orderId }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดที่ชำระ</span>
    <span class="value">฿{{ number_format((float)$total, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">วันที่อนุมัติ</span>
    <span class="value">{{ $approvedAt ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-success">อนุมัติแล้ว</span></span>
  </div>
</div>

<div class="alert-box success">
  <p>🎉 <strong>ภาพของคุณพร้อมดาวน์โหลดแล้ว!</strong></p>
</div>

@if(!empty($downloadUrl))
<div class="btn-wrap">
  <a href="{{ $downloadUrl }}" class="btn btn-success">📥 ดาวน์โหลดภาพเลย</a>
</div>
@endif

<p>ขอบคุณที่ไว้วางใจใช้บริการ <strong>{{ $siteName }}</strong>!</p>
@endsection
