@extends('emails.layout', ['title' => 'การสมัครไม่ได้รับการอนุมัติ', 'preheader' => 'รายละเอียดการตัดสินใจ'])

@section('slot')
<h2>การสมัครไม่ได้รับการอนุมัติ ⚠️</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ขอบคุณที่สนใจสมัครเป็นช่างภาพกับ <strong>{{ $siteName }}</strong> ขออภัยที่ต้องแจ้งว่าบัญชีของคุณยังไม่ได้รับการอนุมัติในครั้งนี้</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-danger">ไม่อนุมัติ</span></span>
  </div>
  @if(!empty($reason))
  <div class="info-row">
    <span class="label">เหตุผล</span>
    <span class="value">{{ $reason }}</span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">วันที่ตรวจสอบ</span>
    <span class="value">{{ now()->format('d/m/Y H:i') }}</span>
  </div>
</div>

<div class="alert-box warning">
  <p><strong>💡 คำแนะนำเพื่อปรับปรุงการสมัคร:</strong></p>
  <p style="margin-top:8px;">
    📸 เตรียมพอร์ตโฟลิโอ 15+ ภาพคุณภาพสูง<br>
    🎨 แสดงความชำนาญในสไตล์เฉพาะ<br>
    📝 กรอกข้อมูลส่วนตัวให้ครบถ้วน<br>
    🆔 อัพโหลดเอกสารยืนยันตัวตนให้ชัดเจน<br>
    💬 เขียน Bio ที่น่าสนใจและเป็นมืออาชีพ
  </p>
</div>

<p><strong>คุณสามารถสมัครใหม่ได้ภายหลัง</strong> — ปรับปรุงตามคำแนะนำด้านบนและลองสมัครใหม่ได้เมื่อพร้อม</p>

@if(!empty($contactUrl))
<div class="btn-wrap">
  <a href="{{ $contactUrl }}" class="btn btn-outline">ติดต่อทีมงาน</a>
</div>
@endif

<p>หากมีข้อสงสัยหรือต้องการคำแนะนำเพิ่มเติม กรุณาติดต่อเราได้ตลอดเวลา</p>

<p>ขอบคุณที่สนใจ และหวังว่าจะได้ร่วมงานกันในอนาคต!</p>
@endsection
