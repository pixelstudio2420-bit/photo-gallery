@extends('emails.layout', ['title' => 'ยอดขายใหม่!', 'preheader' => 'คุณมียอดขายใหม่ ฿' . number_format((float)$commission, 2)])

@section('slot')
<h2>💰 ยอดขายใหม่เข้ามาแล้ว!</h2>

<p>สวัสดีช่างภาพ <strong>{{ $name }}</strong>,</p>

<p>🎉 ข่าวดี! มีคนซื้อภาพของคุณ</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">ยอดขาย</span>
    <span class="value" style="color:#22c55e;font-size:18px;">฿{{ number_format((float)$saleAmount, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">ค่าคอมมิชชั่น ({{ $commissionRate ?? 70 }}%)</span>
    <span class="value" style="color:#22c55e;font-weight:700;">฿{{ number_format((float)$commission, 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">อีเวนต์</span>
    <span class="value">{{ $eventName ?? 'ไม่ระบุ' }}</span>
  </div>
  <div class="info-row">
    <span class="label">จำนวนภาพ</span>
    <span class="value">{{ $photoCount ?? 1 }} ภาพ</span>
  </div>
  <div class="info-row">
    <span class="label">ลูกค้า</span>
    <span class="value">{{ $customerName ?? 'ผู้ใช้' }}</span>
  </div>
  <div class="info-row">
    <span class="label">เวลา</span>
    <span class="value">{{ $saleDate ?? now()->format('d/m/Y H:i') }}</span>
  </div>
</div>

<div class="alert-box success">
  <p>💡 ค่าคอมมิชชั่น <strong>฿{{ number_format((float)$commission, 2) }}</strong> จะถูกโอนเข้าบัญชีธนาคารของคุณในรอบ payout ถัดไป</p>
</div>

<h3>📊 สถิติของคุณ</h3>

@if(!empty($stats))
<div class="info-box">
  <div class="info-row">
    <span class="label">ยอดขายวันนี้</span>
    <span class="value">฿{{ number_format((float)($stats['today'] ?? 0), 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดขายสัปดาห์นี้</span>
    <span class="value">฿{{ number_format((float)($stats['week'] ?? 0), 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดขายเดือนนี้</span>
    <span class="value">฿{{ number_format((float)($stats['month'] ?? 0), 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">รอ payout</span>
    <span class="value" style="color:#6366f1;">฿{{ number_format((float)($stats['pending'] ?? 0), 2) }}</span>
  </div>
</div>
@endif

<div class="btn-wrap">
  <a href="{{ $dashboardUrl }}" class="btn btn-success">📊 ดู Dashboard</a>
</div>

<p>ทำงานดีมาก! 👏 รักษามาตรฐานนี้ไว้นะครับ</p>
@endsection
