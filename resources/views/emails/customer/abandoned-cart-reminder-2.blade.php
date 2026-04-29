@extends('emails.layout', ['title' => 'รับส่วนลด 10% — กลับมาชอปกันเถอะ', 'preheader' => 'รีบหน่อย! สินค้าในตะกร้ารอคุณอยู่'])

@section('slot')
<h2>🎁 รับส่วนลด {{ (int) ($discountPct ?? 10) }}% — เฉพาะคุณ!</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ตะกร้าของคุณยังรออยู่ที่ <strong>{{ $siteName }}</strong> เราเลยมีของขวัญพิเศษมาให้ รับไปเลย <strong>ส่วนลด {{ (int) ($discountPct ?? 10) }}%</strong> เมื่อกลับมาทำรายการให้เสร็จภายใน 48 ชั่วโมง!</p>

<div class="info-box highlight" style="text-align:center; padding:26px;">
  <p style="margin:0; font-size:13px; color:#6b7280;">ใช้โค้ดส่วนลด</p>
  <p style="margin:8px 0 4px; font-size:28px; font-weight:800; color:#6366f1; letter-spacing:3px;">
    {{ $discountCode ?? 'COMEBACK10' }}
  </p>
  <p style="margin:0; font-size:12px; color:#6b7280;">ลด {{ (int) ($discountPct ?? 10) }}% ตอน checkout</p>
</div>

<div class="info-box">
  <div class="info-row">
    <span class="label">จำนวนสินค้าในตะกร้า</span>
    <span class="value">{{ (int) ($itemCount ?? 0) }} รายการ</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดรวมปกติ</span>
    <span class="value">฿{{ number_format((float) ($total ?? 0), 2) }}</span>
  </div>
  <div class="info-row total">
    <span class="label">ประหยัดได้</span>
    <span class="value" style="color:#22c55e;">฿{{ number_format(((float) ($total ?? 0)) * ((int) ($discountPct ?? 10) / 100), 2) }}</span>
  </div>
</div>

@if(!empty($items) && is_array($items))
  <h3>รายการที่คุณเลือกไว้</h3>
  <table class="items-table">
    <thead>
      <tr>
        <th>สินค้า</th>
        <th class="text-right">จำนวน</th>
        <th class="text-right">ราคา</th>
      </tr>
    </thead>
    <tbody>
      @foreach(array_slice($items, 0, 5) as $item)
        <tr>
          <td>{{ $item['name'] ?? 'Photo' }}</td>
          <td class="text-right">{{ (int) ($item['quantity'] ?? 1) }}</td>
          <td class="text-right">฿{{ number_format((float) ($item['price'] ?? 0), 2) }}</td>
        </tr>
      @endforeach
      @if(count($items) > 5)
        <tr>
          <td colspan="3" style="text-align:center; color:#9ca3af; font-size:13px;">
            และอีก {{ count($items) - 5 }} รายการ...
          </td>
        </tr>
      @endif
    </tbody>
  </table>
@endif

<div class="btn-wrap">
  <a href="{{ $recoveryUrl }}" class="btn btn-success">รับส่วนลด {{ (int) ($discountPct ?? 10) }}% ทันที →</a>
</div>

<div class="alert-box warning">
  <p>⏰ <strong>รีบหน่อย!</strong> โปรโมชันนี้มีจำกัดเวลาเพียง <strong>48 ชั่วโมง</strong> และจำนวนภาพที่เหลือมีจำกัด อย่าพลาดโอกาส!</p>
</div>

<p>ภาพความทรงจำดีๆ ไม่มีวันกลับมาอีกครั้ง — กลับมารับภาพของคุณก่อนที่จะสายเกินไป 📷💫</p>

<p style="font-size:12px; color:#9ca3af; margin-top:20px;">* โค้ดส่วนลดใช้ได้ครั้งเดียวต่อบัญชี และใช้ได้เฉพาะกับตะกร้านี้เท่านั้น</p>
@endsection
