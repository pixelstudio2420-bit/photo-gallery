@extends('emails.layout', ['title' => 'สรุปรายงานประจำวัน', 'preheader' => 'ยอดขายวันที่ ' . ($date ?? now()->format('d/m/Y'))])

@section('slot')
<h2>📊 สรุปรายงานประจำวัน</h2>

<p>สวัสดี Admin,</p>

<p>สรุปภาพรวมการทำงานของระบบ <strong>{{ $date ?? now()->subDay()->format('d/m/Y') }}</strong></p>

<h3>💰 ยอดขาย</h3>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">ยอดขายวันนี้</span>
    <span class="value" style="color:#22c55e;font-size:20px;font-weight:700;">฿{{ number_format((float)($totalRevenue ?? 0), 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">จำนวนออเดอร์</span>
    <span class="value">{{ $orderCount ?? 0 }} ออเดอร์</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดเฉลี่ยต่อออเดอร์</span>
    <span class="value">฿{{ number_format((float)($avgOrderValue ?? 0), 2) }}</span>
  </div>
  <div class="info-row">
    <span class="label">ภาพที่ขายได้</span>
    <span class="value">{{ $photosSold ?? 0 }} ภาพ</span>
  </div>
  <div class="info-row">
    <span class="label">ค่าคอมมิชชั่นช่างภาพ</span>
    <span class="value" style="color:#ef4444;">-฿{{ number_format((float)($commissionPaid ?? 0), 2) }}</span>
  </div>
  <div class="info-row total">
    <span class="label">กำไรสุทธิ</span>
    <span class="value" style="color:#6366f1;">฿{{ number_format((float)(($totalRevenue ?? 0) - ($commissionPaid ?? 0)), 2) }}</span>
  </div>
</div>

<h3>👥 ผู้ใช้งาน</h3>

<div class="info-box">
  <div class="info-row">
    <span class="label">สมาชิกใหม่</span>
    <span class="value">{{ $newUsers ?? 0 }}</span>
  </div>
  <div class="info-row">
    <span class="label">ช่างภาพใหม่</span>
    <span class="value">{{ $newPhotographers ?? 0 }}</span>
  </div>
  <div class="info-row">
    <span class="label">ผู้ใช้ Active</span>
    <span class="value">{{ $activeUsers ?? 0 }}</span>
  </div>
</div>

<h3>📸 กิจกรรม</h3>

<div class="info-box">
  <div class="info-row">
    <span class="label">อีเวนต์ใหม่</span>
    <span class="value">{{ $newEvents ?? 0 }}</span>
  </div>
  <div class="info-row">
    <span class="label">ภาพที่อัพโหลด</span>
    <span class="value">{{ $photosUploaded ?? 0 }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดเข้าชมทั้งหมด</span>
    <span class="value">{{ number_format($totalViews ?? 0) }}</span>
  </div>
  <div class="info-row">
    <span class="label">รีวิวใหม่</span>
    <span class="value">{{ $newReviews ?? 0 }} (เฉลี่ย {{ number_format($avgRating ?? 0, 1) }}/5)</span>
  </div>
</div>

<h3>⚠️ รายการที่ต้องดำเนินการ</h3>

<div class="info-box">
  @if(($pendingSlips ?? 0) > 0)
  <div class="info-row">
    <span class="label">สลิปรอตรวจ</span>
    <span class="value"><span class="badge badge-warning">{{ $pendingSlips }} รายการ</span></span>
  </div>
  @endif
  @if(($pendingPhotographers ?? 0) > 0)
  <div class="info-row">
    <span class="label">ช่างภาพรออนุมัติ</span>
    <span class="value"><span class="badge badge-warning">{{ $pendingPhotographers }} คน</span></span>
  </div>
  @endif
  @if(($pendingContacts ?? 0) > 0)
  <div class="info-row">
    <span class="label">ข้อความรอตอบ</span>
    <span class="value"><span class="badge badge-warning">{{ $pendingContacts }} รายการ</span></span>
  </div>
  @endif
  @if(($pendingRefunds ?? 0) > 0)
  <div class="info-row">
    <span class="label">คำขอคืนเงินรอดำเนินการ</span>
    <span class="value"><span class="badge badge-danger">{{ $pendingRefunds }} รายการ</span></span>
  </div>
  @endif
</div>

@if(!empty($topPhotographer))
<h3>🏆 ช่างภาพยอดเยี่ยมวันนี้</h3>
<div class="info-box highlight">
  <div class="info-row">
    <span class="label">ช่างภาพ</span>
    <span class="value">{{ $topPhotographer['name'] ?? 'N/A' }}</span>
  </div>
  <div class="info-row">
    <span class="label">ยอดขาย</span>
    <span class="value">฿{{ number_format((float)($topPhotographer['revenue'] ?? 0), 2) }}</span>
  </div>
</div>
@endif

<div class="btn-wrap">
  <a href="{{ $dashboardUrl }}" class="btn">📊 ดู Dashboard เต็ม</a>
</div>
@endsection
