@extends('emails.layout', ['title' => 'ใบเสร็จอิเล็กทรอนิกส์', 'preheader' => 'ใบเสร็จสำหรับคำสั่งซื้อ #' . ($orderNumber ?? $orderId)])

@section('slot')
<h2>ใบเสร็จอิเล็กทรอนิกส์ 🧾</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>ขอบคุณที่ใช้บริการ! ด้านล่างคือใบเสร็จสำหรับคำสั่งซื้อของคุณ</p>

<div class="info-box">
  <div class="info-row">
    <span class="label">เลขที่ใบเสร็จ</span>
    <span class="value">INV-{{ $invoiceNumber ?? $orderId }}</span>
  </div>
  <div class="info-row">
    <span class="label">เลขที่คำสั่งซื้อ</span>
    <span class="value">#{{ $orderNumber ?? $orderId }}</span>
  </div>
  <div class="info-row">
    <span class="label">วันที่ออกใบเสร็จ</span>
    <span class="value">{{ $invoiceDate ?? now()->format('d/m/Y') }}</span>
  </div>
  @if(!empty($companyName))
  <div class="info-row">
    <span class="label">ในนาม</span>
    <span class="value">{{ $companyName }}</span>
  </div>
  @endif
  @if(!empty($taxId))
  <div class="info-row">
    <span class="label">เลขประจำตัวผู้เสียภาษี</span>
    <span class="value">{{ $taxId }}</span>
  </div>
  @endif
</div>

<h3>รายการสินค้า/บริการ</h3>

<table class="items-table">
  <thead>
    <tr>
      <th>รายการ</th>
      <th class="text-right">จำนวน</th>
      <th class="text-right">ราคา/หน่วย</th>
      <th class="text-right">รวม</th>
    </tr>
  </thead>
  <tbody>
    @foreach($items as $item)
    <tr>
      <td>{{ $item['name'] ?? 'รายการ' }}</td>
      <td class="text-right">{{ $item['quantity'] ?? 1 }}</td>
      <td class="text-right">฿{{ number_format((float)($item['price'] ?? 0), 2) }}</td>
      <td class="text-right">฿{{ number_format((float)($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="info-box">
  @if(!empty($subtotal))
  <div class="info-row">
    <span class="label">ยอดรวมก่อนภาษี</span>
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
    <span class="label">VAT 7%</span>
    <span class="value">฿{{ number_format((float)$tax, 2) }}</span>
  </div>
  @endif
  <div class="info-row total">
    <span class="label">ยอดชำระรวม</span>
    <span class="value" style="color:#6366f1;">฿{{ number_format((float)$total, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">สถานะการชำระ</span>
    <span class="value"><span class="badge badge-success">ชำระแล้ว</span></span>
  </div>
</div>

@if(!empty($invoicePdfUrl))
<div class="btn-wrap">
  <a href="{{ $invoicePdfUrl }}" class="btn">📄 ดาวน์โหลด PDF ใบเสร็จ</a>
</div>
@endif

<div class="divider"></div>

<p style="font-size:12px;color:#9ca3af;text-align:center;">
  ใบเสร็จนี้ออกโดย {{ $siteName }}<br>
  เอกสารอิเล็กทรอนิกส์ฉบับนี้ออกตามข้อบังคับของกรมสรรพากร<br>
  สามารถใช้เป็นหลักฐานทางภาษีได้
</p>
@endsection
