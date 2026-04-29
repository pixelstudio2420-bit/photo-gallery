@extends('emails.layout', ['title' => 'มีสลิปรอตรวจสอบ', 'preheader' => 'คำสั่งซื้อ #' . ($orderNumber ?? $orderId) . ' รอตรวจสลิป'])

@section('slot')
<h2>📄 มีสลิปโอนเงินรอตรวจสอบ</h2>

<p>สวัสดี Admin,</p>

<p>ลูกค้าได้อัพโหลดสลิปโอนเงินเรียบร้อยแล้ว กำลังรอการตรวจสอบจากคุณ</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">เลขคำสั่งซื้อ</span>
    <span class="value">#{{ $orderNumber ?? $orderId }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดเงิน</span>
    <span class="value" style="color:#6366f1;font-weight:700;">฿{{ number_format((float)$total, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">ลูกค้า</span>
    <span class="value">{{ $customerName ?? 'N/A' }}</span>
  </div>
  @if(!empty($bankName))
  <div class="info-row">
    <span class="label">โอนมาจากธนาคาร</span>
    <span class="value">{{ $bankName }}</span>
  </div>
  @endif
  @if(!empty($refCode))
  <div class="info-row">
    <span class="label">เลขอ้างอิง</span>
    <span class="value">{{ $refCode }}</span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">อัพโหลดเมื่อ</span>
    <span class="value">{{ $uploadedAt ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  @if(!empty($slipVerification))
  <div class="info-row">
    <span class="label">ผลการตรวจ AI</span>
    <span class="value">
      @if($slipVerification === 'verified')
        <span class="badge badge-success">ตรวจผ่านแล้ว</span>
      @elseif($slipVerification === 'failed')
        <span class="badge badge-danger">ไม่ผ่าน</span>
      @else
        <span class="badge badge-warning">รอตรวจ</span>
      @endif
    </span>
  </div>
  @endif
</div>

<div class="alert-box">
  <p><strong>🔍 สิ่งที่ต้องตรวจสอบ:</strong></p>
  <p style="margin-top:6px;">
    ✓ ยอดเงินตรงกับคำสั่งซื้อ<br>
    ✓ วันที่โอนถูกต้อง<br>
    ✓ โอนเข้าบัญชีที่ถูกต้อง<br>
    ✓ สลิปชัดเจน ไม่ถูกตัดต่อ
  </p>
</div>

<div class="btn-wrap">
  <a href="{{ $adminSlipUrl }}" class="btn btn-warning">📋 ตรวจสอบสลิป</a>
</div>
@endsection
