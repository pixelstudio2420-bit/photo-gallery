@extends('emails.layout', ['title' => 'มีคำสั่งซื้อใหม่!', 'preheader' => 'คำสั่งซื้อใหม่ #' . ($orderNumber ?? $orderId) . ' ยอด ฿' . number_format((float)$total, 2)])

@section('slot')
<h2>🛒 มีคำสั่งซื้อใหม่เข้ามา!</h2>

<p>สวัสดี Admin,</p>

<p>🎉 มีคำสั่งซื้อใหม่เข้ามาในระบบ</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">เลขคำสั่งซื้อ</span>
    <span class="value">#{{ $orderNumber ?? $orderId }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดรวม</span>
    <span class="value" style="color:#22c55e;font-size:18px;font-weight:700;">฿{{ number_format((float)$total, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">ลูกค้า</span>
    <span class="value">{{ $customerName ?? 'ไม่ระบุ' }}</span>
  </div>
  <div class="info-row">
    <span class="label">อีเมล</span>
    <span class="value">{{ $customerEmail ?? 'N/A' }}</span>
  </div>
  <div class="info-row">
    <span class="label">เบอร์โทร</span>
    <span class="value">{{ $customerPhone ?? 'N/A' }}</span>
  </div>
  <div class="info-row">
    <span class="label">วิธีชำระเงิน</span>
    <span class="value">{{ $paymentMethod ?? 'รอเลือก' }}</span>
  </div>
  <div class="info-row">
    <span class="label">เวลา</span>
    <span class="value">{{ $orderDate ?? now()->format('d/m/Y H:i') }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะ</span>
    <span class="value"><span class="badge badge-warning">{{ $statusLabel ?? 'รอดำเนินการ' }}</span></span>
  </div>
</div>

<h3>📦 รายการสินค้า</h3>

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
      <td>{{ $item['name'] ?? 'รายการ' }}</td>
      <td class="text-right">{{ $item['quantity'] ?? 1 }}</td>
      <td class="text-right">฿{{ number_format((float)($item['price'] ?? 0), 2) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="btn-wrap">
  <a href="{{ $adminOrderUrl }}" class="btn">👀 ดูรายละเอียดใน Admin</a>
</div>

@if(($paymentMethod ?? '') === 'bank_transfer')
<div class="alert-box warning">
  <p>⏳ <strong>ลูกค้าเลือกโอนเงิน</strong> — รอการอัพโหลดสลิปเพื่อตรวจสอบ</p>
</div>
@endif
@endsection
