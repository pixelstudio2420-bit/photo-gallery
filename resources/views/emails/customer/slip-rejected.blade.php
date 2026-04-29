@extends('emails.layout', ['title' => 'สลิปโอนเงินไม่ผ่าน', 'preheader' => 'คำสั่งซื้อ #' . ($orderNumber ?? $orderId)])

@section('slot')
<h2>สลิปโอนเงินไม่ผ่านการตรวจสอบ ⚠️</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ขออภัย สลิปโอนเงินของคุณสำหรับคำสั่งซื้อ <strong>#{{ $orderNumber ?? $orderId }}</strong> ไม่ผ่านการตรวจสอบ</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">เลขที่คำสั่งซื้อ</span>
    <span class="value">#{{ $orderNumber ?? $orderId }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-danger">ไม่ผ่าน</span></span>
  </div>
  <div class="info-row">
    <span class="label">เหตุผล</span>
    <span class="value">{{ $reason ?? 'ไม่ระบุ' }}</span>
  </div>
</div>

<div class="alert-box warning">
  <p><strong>ข้อแนะนำการอัพโหลดสลิปใหม่:</strong></p>
  <p style="margin-top:8px;">
    📸 ถ่ายภาพสลิปให้ชัดเจน เห็นทุกรายละเอียด<br>
    💰 ยอดเงินต้องตรงกับยอดคำสั่งซื้อ<br>
    📅 วันที่โอนต้องไม่เก่าเกิน 7 วัน<br>
    🏦 ตรวจสอบว่าโอนเข้าบัญชีที่ถูกต้อง
  </p>
</div>

@if(!empty($retryUrl))
<div class="btn-wrap">
  <a href="{{ $retryUrl }}" class="btn btn-warning">📤 อัพโหลดสลิปใหม่</a>
</div>
@endif

<p>หากคุณมีข้อสงสัยหรือต้องการความช่วยเหลือ กรุณาติดต่อทีมงานของเรา</p>

<p style="font-size:13px;color:#6b7280;">
  💡 <strong>หมายเหตุ:</strong> คำสั่งซื้อของคุณยังเปิดอยู่ สามารถอัพโหลดสลิปใหม่ได้ภายใน 24 ชั่วโมง
</p>
@endsection
