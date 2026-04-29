@extends('emails.layout', ['title' => 'การโอนเงินไม่สำเร็จ', 'preheader' => 'กรุณาตรวจสอบข้อมูลบัญชีธนาคาร'])

@section('slot')
<h2>⚠️ การโอนเงินรายได้ไม่สำเร็จ</h2>

<p>สวัสดีช่างภาพ <strong>{{ $name }}</strong>,</p>

<p>ขออภัย ระบบไม่สามารถโอนเงินค่าคอมมิชชั่นของคุณเข้าบัญชีธนาคารได้</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">ยอดที่ต้องโอน</span>
    <span class="value">฿{{ number_format((float)$amount, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">ระยะเวลา</span>
    <span class="value">{{ $period ?? 'ไม่ระบุ' }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-danger">ไม่สำเร็จ</span></span>
  </div>
  @if(!empty($reason))
  <div class="info-row">
    <span class="label">สาเหตุ</span>
    <span class="value">{{ $reason }}</span>
  </div>
  @endif
</div>

<div class="alert-box danger">
  <p><strong>📝 สาเหตุที่เป็นไปได้:</strong></p>
  <p style="margin-top:8px;">
    • เลขบัญชีธนาคารไม่ถูกต้อง<br>
    • ชื่อบัญชีไม่ตรงกับชื่อที่ลงทะเบียน<br>
    • บัญชีธนาคารถูกระงับ<br>
    • ข้อมูลธนาคารไม่ครบถ้วน
  </p>
</div>

<h3>🔧 สิ่งที่ต้องทำ</h3>

<ol style="color:#4b5563;line-height:1.8;">
  <li><strong>เข้าสู่ระบบ</strong> — เข้า Dashboard ช่างภาพ</li>
  <li><strong>ไปที่ Profile → ข้อมูลธนาคาร</strong></li>
  <li><strong>ตรวจสอบ/แก้ไข</strong>เลขบัญชีและชื่อบัญชีให้ถูกต้อง</li>
  <li><strong>ติดต่อทีมงาน</strong> เพื่อให้โอนเงินใหม่</li>
</ol>

@if(!empty($updateUrl))
<div class="btn-wrap">
  <a href="{{ $updateUrl }}" class="btn btn-warning">แก้ไขข้อมูลธนาคาร</a>
</div>
@endif

<p>หลังจากแก้ไขข้อมูลแล้ว ทีมงานจะโอนเงินให้ใหม่ภายใน 1-2 วันทำการ</p>

<p>ขออภัยในความไม่สะดวก หากต้องการความช่วยเหลือ กรุณาติดต่อเราได้ตลอดเวลา</p>
@endsection
