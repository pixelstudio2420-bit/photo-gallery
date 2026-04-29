@extends('emails.layout', ['title' => 'การคืนเงินได้รับการดำเนินการ', 'preheader' => 'คืนเงิน ฿' . number_format((float)$amount, 2)])

@section('slot')
<h2>การคืนเงินได้รับการดำเนินการแล้ว 💸</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>คำขอคืนเงินของคุณได้รับการอนุมัติและดำเนินการเรียบร้อยแล้ว</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">เลขที่คำสั่งซื้อ</span>
    <span class="value">#{{ $orderNumber ?? $orderId }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดคืนเงิน</span>
    <span class="value" style="color:#22c55e;">฿{{ number_format((float)$amount, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">ช่องทางคืนเงิน</span>
    <span class="value">{{ $refundMethod ?? 'บัญชีธนาคาร' }}</span>
  </div>
  <div class="info-row">
    <span class="label">วันที่ดำเนินการ</span>
    <span class="value">{{ $processedAt ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  @if(!empty($reason))
  <div class="info-row">
    <span class="label">เหตุผล</span>
    <span class="value">{{ $reason }}</span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-success">คืนเงินแล้ว</span></span>
  </div>
</div>

<div class="alert-box success">
  <p>⏰ <strong>ระยะเวลาการได้รับเงินคืน:</strong></p>
  <p style="margin-top:6px;">
    • โอนผ่านพร้อมเพย์: <strong>ทันที - 1 วันทำการ</strong><br>
    • โอนเข้าบัญชีธนาคาร: <strong>1-3 วันทำการ</strong><br>
    • บัตรเครดิต: <strong>7-15 วันทำการ</strong>
  </p>
</div>

<p>หากคุณไม่ได้รับเงินคืนภายในระยะเวลาที่กำหนด กรุณาติดต่อทีมงานของเรา เพื่อตรวจสอบสถานะ</p>

<p>ขออภัยในความไม่สะดวก และขอบคุณที่ใช้บริการ <strong>{{ $siteName }}</strong></p>
@endsection
