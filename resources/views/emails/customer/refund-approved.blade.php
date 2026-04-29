@extends('emails.layout', ['title' => 'คำขอคืนเงินได้รับการอนุมัติ', 'preheader' => 'คืนเงินจำนวน ฿' . number_format((float) ($approvedAmount ?? 0), 2)])

@section('slot')
<h2>🎉 คำขอคืนเงินได้รับการอนุมัติแล้ว!</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ข่าวดี! คำขอคืนเงินของคุณสำหรับออเดอร์ <strong>#{{ $orderNumber }}</strong> ได้รับการ<strong>อนุมัติ</strong>เรียบร้อยแล้ว</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">เลขที่คำขอ</span>
    <span class="value">{{ $requestNumber }}</span>
  </div>
  <div class="info-row">
    <span class="label">เลขที่ออเดอร์</span>
    <span class="value">#{{ $orderNumber }}</span>
  </div>
  <div class="info-row total">
    <span class="label">ยอดที่จะได้รับคืน</span>
    <span class="value" style="color:#22c55e;">฿{{ number_format((float) ($approvedAmount ?? 0), 2) }}</span>
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

@if(!empty($adminNote))
<div class="alert-box success">
  <p><strong>ข้อความจากทีมงาน:</strong></p>
  <p style="margin-top:6px; white-space:pre-line;">{{ $adminNote }}</p>
</div>
@endif

<h3>ระยะเวลาได้รับเงินคืน</h3>

<div class="info-box">
  <div class="info-row">
    <span class="label">พร้อมเพย์</span>
    <span class="value">ทันที - 1 วันทำการ</span>
  </div>
  <div class="info-row">
    <span class="label">โอนเข้าบัญชีธนาคาร</span>
    <span class="value">1-3 วันทำการ</span>
  </div>
  <div class="info-row">
    <span class="label">บัตรเครดิต/เดบิต</span>
    <span class="value">7-15 วันทำการ</span>
  </div>
  <div class="info-row">
    <span class="label">E-Wallet (TrueMoney ฯลฯ)</span>
    <span class="value">1-3 วันทำการ</span>
  </div>
</div>

<div class="alert-box success">
  <p>✅ เงินของคุณจะถูกส่งคืนไปยังช่องทางชำระเงินเดิม โปรดตรวจสอบยอดเงินตามระยะเวลาข้างต้น</p>
</div>

<p>หากไม่ได้รับเงินคืนภายในระยะเวลาที่ระบุ หรือมีข้อสงสัย กรุณาติดต่อทีมสนับสนุน พร้อมเลขที่คำขอ <strong>{{ $requestNumber }}</strong></p>

<p>ขอบคุณที่ใช้บริการ <strong>{{ $siteName }}</strong> หวังว่าจะได้บริการคุณอีกในโอกาสต่อไป 🙏</p>
@endsection
