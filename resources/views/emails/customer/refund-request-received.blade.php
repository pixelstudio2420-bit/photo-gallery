@extends('emails.layout', ['title' => 'ได้รับคำขอคืนเงินแล้ว', 'preheader' => 'ทีมงานจะพิจารณาและแจ้งผลภายใน 3-5 วันทำการ'])

@section('slot')
<h2>✅ ได้รับคำขอคืนเงินของคุณแล้ว</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>เราได้รับคำขอคืนเงินสำหรับออเดอร์ <strong>#{{ $orderNumber }}</strong> เรียบร้อยแล้ว และอยู่ในขั้นตอนการพิจารณา</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">เลขที่คำขอ</span>
    <span class="value">{{ $requestNumber }}</span>
  </div>
  <div class="info-row">
    <span class="label">เลขที่ออเดอร์</span>
    <span class="value">#{{ $orderNumber }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดที่ขอคืน</span>
    <span class="value" style="color:#6366f1;">฿{{ number_format((float) ($requestedAmount ?? 0), 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">เหตุผล</span>
    <span class="value">{{ $reason ?? '-' }}</span>
  </div>
  <div class="info-row">
    <span class="label">วันที่ส่งคำขอ</span>
    <span class="value">{{ $createdAt ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-warning">รอพิจารณา</span></span>
  </div>
</div>

@if(!empty($description))
<div class="alert-box">
  <p><strong>รายละเอียดเพิ่มเติมจากคุณ:</strong></p>
  <p style="margin-top:6px; white-space:pre-line;">{{ $description }}</p>
</div>
@endif

<h3>ขั้นตอนถัดไป</h3>

<div class="info-box">
  <div class="info-row">
    <span class="label">1. รอพิจารณา</span>
    <span class="value"><span class="badge badge-warning">ขั้นตอนปัจจุบัน</span></span>
  </div>
  <div class="info-row">
    <span class="label">2. ตรวจสอบ</span>
    <span class="value">ทีมงานจะตรวจสอบคำขอและเอกสารประกอบ</span>
  </div>
  <div class="info-row">
    <span class="label">3. ตัดสินผล</span>
    <span class="value">อนุมัติ/ปฏิเสธ พร้อมแจ้งผลให้คุณทราบ</span>
  </div>
  <div class="info-row">
    <span class="label">4. คืนเงิน</span>
    <span class="value">หากอนุมัติ เงินจะถูกโอนภายใน 1-15 วันทำการ</span>
  </div>
</div>

<div class="alert-box warning">
  <p>⏰ ทีมงานจะพิจารณาและแจ้งผลภายใน <strong>3-5 วันทำการ</strong> โปรดตรวจสอบอีเมลของคุณเป็นระยะ</p>
</div>

<p>หากคุณมีข้อมูลเพิ่มเติมที่ต้องการส่งให้ทีมงาน กรุณาตอบกลับอีเมลนี้หรือติดต่อทีมสนับสนุน</p>

<p>ขอบคุณสำหรับความไว้วางใจใน <strong>{{ $siteName }}</strong> 🙏</p>
@endsection
