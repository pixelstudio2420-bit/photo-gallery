@extends('emails.layout', ['title' => 'ชำระเงินสำเร็จ', 'preheader' => 'คำสั่งซื้อ #' . ($orderNumber ?? $orderId) . ' ชำระเงินเรียบร้อย'])

@section('slot')
<h2>ชำระเงินเรียบร้อยแล้ว! ✅</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ขอบคุณที่ชำระเงิน! เราได้ยืนยันการชำระเงินของคุณเรียบร้อยแล้ว</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">เลขที่คำสั่งซื้อ</span>
    <span class="value">#{{ $orderNumber ?? $orderId }}</span>
  </div>
  <div class="info-row">
    <span class="label">วิธีชำระเงิน</span>
    <span class="value">{{ $paymentMethod ?? 'โอนเงิน' }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดชำระ</span>
    <span class="value">฿{{ number_format((float)$total, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">วันที่ชำระ</span>
    <span class="value">{{ $paidAt ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-success">ชำระเงินแล้ว</span></span>
  </div>
</div>

<div class="alert-box success">
  <p>🎉 <strong>ภาพของคุณพร้อมดาวน์โหลดแล้ว!</strong> คลิกปุ่มด้านล่างเพื่อเริ่มดาวน์โหลด</p>
</div>

@if(!empty($downloadUrl))
<div class="btn-wrap">
  <a href="{{ $downloadUrl }}" class="btn btn-success">📥 ดาวน์โหลดภาพ</a>
</div>
@endif

@if(!empty($orderUrl))
<div class="btn-wrap">
  <a href="{{ $orderUrl }}" class="btn btn-outline">ดูรายละเอียดคำสั่งซื้อ</a>
</div>
@endif

<div class="divider"></div>

<p style="font-size:13px;color:#6b7280;">
  📧 ใบเสร็จอิเล็กทรอนิกส์จะถูกส่งในอีเมลแยกภายใน 24 ชั่วโมง<br>
  🔒 ลิงก์ดาวน์โหลดจะใช้งานได้ 7 วัน โปรดดาวน์โหลดภายในเวลา
</p>

<p>ขอบคุณที่ไว้วางใจใช้บริการ <strong>{{ $siteName }}</strong>! 📸</p>
@endsection
