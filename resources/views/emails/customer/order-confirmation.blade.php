@extends('emails.layout', ['title' => 'ยืนยันคำสั่งซื้อ', 'preheader' => 'คำสั่งซื้อ #' . ($orderNumber ?? $orderId)])

@section('slot')
<h2>ได้รับคำสั่งซื้อของคุณแล้ว! 🎉</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ขอบคุณที่สั่งซื้อกับเรา! เราได้รับคำสั่งซื้อของคุณแล้ว ด้านล่างคือรายละเอียด:</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">เลขที่คำสั่งซื้อ</span>
    <span class="value">#{{ $orderNumber ?? $orderId }}</span>
  </div>
  <div class="info-row">
    <span class="label">วันที่สั่งซื้อ</span>
    <span class="value">{{ $orderDate ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-warning">รอการชำระเงิน</span></span>
  </div>
</div>

<h3>รายการสินค้า</h3>

<table class="items-table">
  <thead>
    <tr>
      <th>รายการ</th>
      <th class="text-right">จำนวน</th>
      <th class="text-right">ราคา</th>
    </tr>
  </thead>
  <tbody>
    @foreach($items as $item)
    <tr>
      <td>
        <strong>{{ $item['name'] ?? $item['title'] ?? 'รายการ' }}</strong>
        @if(!empty($item['event_name']))
          <br><small style="color:#6b7280;">{{ $item['event_name'] }}</small>
        @endif
      </td>
      <td class="text-right">{{ $item['quantity'] ?? 1 }}</td>
      <td class="text-right">฿{{ number_format((float)($item['price'] ?? 0), 2) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="info-box">
  @if(!empty($subtotal))
  <div class="info-row">
    <span class="label">ยอดรวม</span>
    <span class="value">฿{{ number_format((float)$subtotal, 2) }}</span>
  </div>
  @endif
  @if(!empty($discount) && $discount > 0)
  <div class="info-row">
    <span class="label">ส่วนลด</span>
    <span class="value" style="color:#22c55e;">-฿{{ number_format((float)$discount, 2) }}</span>
  </div>
  @endif
  @if(!empty($tax) && $tax > 0)
  <div class="info-row">
    <span class="label">ภาษี (VAT)</span>
    <span class="value">฿{{ number_format((float)$tax, 2) }}</span>
  </div>
  @endif
  <div class="info-row total">
    <span class="label">ยอดชำระรวม</span>
    <span class="value" style="color:#6366f1;">฿{{ number_format((float)$total, 2) }}</span>
  </div>
</div>

@if(!empty($paymentUrl))
<div class="btn-wrap">
  <a href="{{ $paymentUrl }}" class="btn">💳 ชำระเงินตอนนี้</a>
</div>

<div class="alert-box warning">
  <p>⏰ <strong>กรุณาชำระเงินภายใน 24 ชั่วโมง</strong> มิฉะนั้นคำสั่งซื้อจะถูกยกเลิกอัตโนมัติ</p>
</div>
@endif

<p>หลังจากชำระเงินแล้ว ระบบจะส่งลิงก์ดาวน์โหลดภาพให้คุณทางอีเมลโดยอัตโนมัติ</p>

<p>ขอบคุณที่เลือกใช้ <strong>{{ $siteName }}</strong>!</p>
@endsection
