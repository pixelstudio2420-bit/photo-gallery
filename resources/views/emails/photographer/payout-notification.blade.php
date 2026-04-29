@extends('emails.layout', ['title' => 'รายได้โอนเข้าบัญชีแล้ว', 'preheader' => 'ยอด ฿' . number_format((float)$amount, 2) . ' ได้ถูกโอนแล้ว'])

@section('slot')
<h2>💰 รายได้ของคุณถูกโอนแล้ว!</h2>

<p>สวัสดีช่างภาพ <strong>{{ $name }}</strong>,</p>

<p>เงินค่าคอมมิชชั่นของคุณสำหรับรอบ {{ $period ?? 'นี้' }} ได้ถูกโอนเข้าบัญชีธนาคารของคุณเรียบร้อยแล้ว</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">ยอดโอน</span>
    <span class="value" style="color:#22c55e;font-size:20px;font-weight:700;">฿{{ number_format((float)$amount, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">จำนวนออเดอร์</span>
    <span class="value">{{ $orderCount ?? 0 }} ออเดอร์</span>
  </div>
  <div class="info-row">
    <span class="label">ระยะเวลา</span>
    <span class="value">{{ $period ?? 'ไม่ระบุ' }}</span>
  </div>
  <div class="info-row">
    <span class="label">ธนาคารปลายทาง</span>
    <span class="value">{{ $bankName ?? 'บัญชีที่ลงทะเบียน' }}</span>
  </div>
  @if(!empty($accountLast4))
  <div class="info-row">
    <span class="label">เลขบัญชี</span>
    <span class="value">XXX-X-XX{{ $accountLast4 }}-X</span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">วันที่โอน</span>
    <span class="value">{{ $transferDate ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  <div class="info-row">
    <span class="label">เลขอ้างอิง</span>
    <span class="value">{{ $referenceNumber ?? 'N/A' }}</span>
  </div>
</div>

<div class="alert-box success">
  <p>⏰ <strong>เงินจะเข้าบัญชีภายใน 1-3 วันทำการ</strong> ขึ้นอยู่กับธนาคารของคุณ</p>
</div>

<h3>📊 สรุปรายการ</h3>

<div class="info-box">
  <div class="info-row">
    <span class="label">ยอดขายรวม (Gross)</span>
    <span class="value">฿{{ number_format((float)($grossSales ?? 0), 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">ค่าคอมมิชชั่นแพลตฟอร์ม</span>
    <span class="value" style="color:#ef4444;">-฿{{ number_format((float)($platformFee ?? 0), 2) }}</span>
  </div>
  @if(!empty($adjustments) && $adjustments != 0)
  <div class="info-row">
    <span class="label">ปรับปรุง</span>
    <span class="value" style="color:{{ $adjustments > 0 ? '#22c55e' : '#ef4444' }};">
      {{ $adjustments > 0 ? '+' : '' }}฿{{ number_format((float)$adjustments, 2) }}
    </span>
  </div>
  @endif
  <div class="info-row total">
    <span class="label">ยอดสุทธิ</span>
    <span class="value" style="color:#6366f1;">฿{{ number_format((float)$amount, 2) }}</span>
  </div>
</div>

@if(!empty($statementUrl))
<div class="btn-wrap">
  <a href="{{ $statementUrl }}" class="btn">📄 ดาวน์โหลด Statement (PDF)</a>
</div>
@endif

<p>ขอบคุณที่ร่วมงานกับ <strong>{{ $siteName }}</strong>! เรายินดีที่มีช่างภาพมือดีอย่างคุณ 📸✨</p>
@endsection
