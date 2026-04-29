@extends('emails.layout', ['title' => 'คำขอคืนเงินใหม่', 'preheader' => 'ลูกค้าขอคืนเงิน ฿' . number_format((float)$amount, 2)])

@section('slot')
<h2>💸 มีคำขอคืนเงินใหม่</h2>

<p>สวัสดี Admin,</p>

<p>ลูกค้าได้ส่งคำขอคืนเงินเข้ามาในระบบ รอการพิจารณา</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">เลขคำสั่งซื้อ</span>
    <span class="value">#{{ $orderNumber ?? $orderId }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดคืน</span>
    <span class="value" style="color:#ef4444;font-weight:700;">฿{{ number_format((float)$amount, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">ลูกค้า</span>
    <span class="value">{{ $customerName ?? 'N/A' }}</span>
  </div>
  <div class="info-row">
    <span class="label">อีเมล</span>
    <span class="value">{{ $customerEmail ?? 'N/A' }}</span>
  </div>
  <div class="info-row">
    <span class="label">วิธีชำระเดิม</span>
    <span class="value">{{ $paymentMethod ?? 'N/A' }}</span>
  </div>
  <div class="info-row">
    <span class="label">วันที่ขอ</span>
    <span class="value">{{ $requestedAt ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-warning">รอการพิจารณา</span></span>
  </div>
</div>

<h3>📝 เหตุผลการขอคืนเงิน</h3>

<div class="alert-box">
  <p style="margin:0;">{{ $reason ?? 'ไม่ระบุเหตุผล' }}</p>
</div>

<div class="alert-box warning">
  <p><strong>⚠️ สิ่งที่ต้องตรวจสอบก่อนอนุมัติ:</strong></p>
  <p style="margin-top:6px;">
    ✓ เหตุผลสมเหตุสมผล<br>
    ✓ ลูกค้าไม่ได้ดาวน์โหลดภาพแล้ว (หรือตามนโยบาย)<br>
    ✓ ยังอยู่ในระยะเวลาคืนเงิน<br>
    ✓ การชำระเงินยังไม่ได้ถูก split ให้ช่างภาพ
  </p>
</div>

<div class="btn-wrap">
  <a href="{{ $adminRefundUrl }}" class="btn btn-danger">💸 พิจารณาคำขอ</a>
</div>
@endsection
