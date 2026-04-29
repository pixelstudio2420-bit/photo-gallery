@extends('emails.layout', ['title' => 'ภาพของคุณถูกปฏิเสธ', 'preheader' => 'ผลการตรวจสอบภาพโดย AI + แอดมิน'])

@section('slot')
<h2>ภาพของคุณไม่ผ่านการตรวจสอบ ⚠️</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>
  ขออภัยที่ต้องแจ้งให้ทราบว่า ภาพที่คุณอัปโหลดในอีเวนต์ <strong>{{ $eventName }}</strong>
  ไม่ผ่านการตรวจสอบเนื้อหาตามมาตรฐานการใช้งานของ <strong>{{ $siteName }}</strong>
  และได้ถูกซ่อนจากหน้าเว็บเรียบร้อยแล้ว
</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">รหัสภาพ</span>
    <span class="value">#{{ $photoId }}</span>
  </div>
  @if(!empty($filename))
  <div class="info-row">
    <span class="label">ชื่อไฟล์</span>
    <span class="value">{{ $filename }}</span>
  </div>
  @endif
  <div class="info-row">
    <span class="label">อีเวนต์</span>
    <span class="value">{{ $eventName }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-danger">ปฏิเสธ</span></span>
  </div>
  <div class="info-row">
    <span class="label">ตัดสินเมื่อ</span>
    <span class="value">{{ $rejectedAt }}</span>
  </div>
</div>

@if(!empty($reason))
<div class="alert-box warning">
  <p><strong>📝 เหตุผลจากแอดมิน:</strong></p>
  <p style="margin-top:8px;">{{ $reason }}</p>
</div>
@endif

@if(!empty($labels) && is_array($labels))
<div class="info-box">
  <p style="margin:0 0 12px 0;"><strong>🤖 สิ่งที่ AI ตรวจพบในภาพ:</strong></p>
  @foreach(array_slice($labels, 0, 5) as $label)
    @php
      $labelName = is_array($label) ? ($label['Name'] ?? $label['name'] ?? '') : (string) $label;
      $conf = is_array($label) ? ($label['Confidence'] ?? $label['confidence'] ?? 0) : 0;
    @endphp
    @if($labelName)
    <div class="info-row">
      <span class="label">{{ $labelName }}</span>
      <span class="value">{{ number_format((float) $conf, 1) }}%</span>
    </div>
    @endif
  @endforeach
</div>
@endif

<div class="alert-box">
  <p><strong>💡 แนวทางการอัปโหลดภาพ:</strong></p>
  <p style="margin-top:8px;">
    ✅ ภาพที่ถ่ายในอีเวนต์ที่ได้รับอนุญาตเท่านั้น<br>
    ✅ หลีกเลี่ยงภาพที่มีเนื้อหาไม่เหมาะสม รุนแรง หรือผิดกฎหมาย<br>
    ✅ ไม่อัปโหลดภาพที่มีสัญลักษณ์ที่สร้างความเกลียดชัง<br>
    ✅ เคารพความเป็นส่วนตัวของผู้ที่อยู่ในภาพ<br>
    ✅ อ่านเงื่อนไขการใช้บริการให้ครบถ้วนก่อนอัปโหลด
  </p>
</div>

<p>
  หากคุณคิดว่าการตัดสินใจนี้ผิดพลาด หรือต้องการยื่นอุทธรณ์
  คุณสามารถติดต่อทีมงานเพื่อขอการพิจารณาใหม่ได้
</p>

@if(!empty($appealUrl))
<div class="btn-wrap">
  <a href="{{ $appealUrl }}" class="btn btn-outline">จัดการภาพของฉัน</a>
</div>
@endif

<p style="margin-top:20px; color:#6b7280; font-size:13px;">
  <em>การตรวจสอบนี้ใช้ระบบ AI วิเคราะห์ภาพร่วมกับการพิจารณาของแอดมินเพื่อรักษามาตรฐานเนื้อหาบนแพลตฟอร์ม
  ขอบคุณที่เข้าใจและให้ความร่วมมือ</em>
</p>
@endsection
