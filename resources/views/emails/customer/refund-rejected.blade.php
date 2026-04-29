@extends('emails.layout', ['title' => 'คำขอคืนเงินไม่ได้รับการอนุมัติ', 'preheader' => 'ทีมงานได้พิจารณาคำขอของคุณแล้ว'])

@section('slot')
<h2>ผลการพิจารณาคำขอคืนเงิน</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>เราได้พิจารณาคำขอคืนเงินของคุณสำหรับออเดอร์ <strong>#{{ $orderNumber }}</strong> เรียบร้อยแล้ว แต่เสียใจที่ต้องแจ้งว่าคำขอครั้งนี้ <strong>ไม่ได้รับการอนุมัติ</strong></p>

<div class="info-box">
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
    <span class="value">฿{{ number_format((float) ($requestedAmount ?? 0), 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">วันที่พิจารณา</span>
    <span class="value">{{ $rejectedAt ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-danger">ไม่อนุมัติ</span></span>
  </div>
</div>

<div class="alert-box danger">
  <p><strong>เหตุผลจากทีมงาน:</strong></p>
  <p style="margin-top:8px; white-space:pre-line;">{{ $reason ?: 'ไม่ได้ระบุเหตุผล' }}</p>
</div>

<h3>ขั้นตอนถัดไป</h3>

<p>หากคุณไม่เห็นด้วยกับผลการพิจารณา หรือมีข้อมูล/หลักฐานเพิ่มเติมที่อาจเปลี่ยนแปลงผลการพิจารณา กรุณาติดต่อทีมสนับสนุนของเราพร้อมแจ้ง:</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">• เลขที่คำขอ</span>
    <span class="value">{{ $requestNumber }}</span>
  </div>
  <div class="info-row">
    <span class="label">• เลขที่ออเดอร์</span>
    <span class="value">#{{ $orderNumber }}</span>
  </div>
  <div class="info-row">
    <span class="label">• เหตุผลหรือหลักฐาน</span>
    <span class="value">ส่งมาพร้อมกับข้อความ</span>
  </div>
</div>

<div class="btn-wrap">
  <a href="{{ url('/contact') }}" class="btn btn-outline">ติดต่อทีมสนับสนุน</a>
</div>

@if(!empty($supportEmail))
<p>หรือส่งอีเมลโดยตรงมาที่ <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>
@endif

<p>เราขออภัยในความไม่สะดวกที่เกิดขึ้น และขอบคุณที่เข้าใจ 🙏</p>

<p>ขอบคุณที่ใช้บริการ <strong>{{ $siteName }}</strong></p>
@endsection
