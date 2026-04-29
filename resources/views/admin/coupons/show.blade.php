@extends('layouts.admin')

@section('title', 'รายละเอียดคูปอง')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-ticket-perforated mr-2 text-indigo-500"></i>รายละเอียดคูปอง
  </h4>
  <div class="flex gap-2">
    <a href="{{ route('admin.coupons.edit', $coupon->id) }}" class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-5 py-2 inline-flex items-center gap-1 transition hover:from-indigo-600 hover:to-indigo-700">
      <i class="bi bi-pencil mr-1"></i> แก้ไข
    </a>
    <a href="{{ route('admin.coupons.index') }}" class="bg-gray-100 text-gray-500 rounded-lg font-medium px-5 py-2 inline-flex items-center gap-1 transition hover:bg-gray-200">
      <i class="bi bi-arrow-left mr-1"></i> กลับ
    </a>
  </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
  <div class="p-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">รหัสคูปอง</label>
          <div class="mt-1">
            <code class="bg-indigo-50 text-indigo-600 px-3 py-1 rounded-lg text-base">{{ $coupon->code }}</code>
          </div>
        </div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">ชื่อ</label>
          <div class="font-medium mt-1">{{ $coupon->name }}</div>
        </div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">รายละเอียด</label>
          <div class="mt-1">{{ $coupon->description ?? '-' }}</div>
        </div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">ประเภท</label>
          <div class="mt-1">
            @if($coupon->type === 'percent')
              <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-600">เปอร์เซ็นต์</span>
            @else
              <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full bg-green-50 text-green-600">จำนวนเงิน</span>
            @endif
          </div>
        </div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">มูลค่า</label>
          <div class="font-semibold mt-1 text-lg">
            {{ $coupon->type === 'percent' ? $coupon->value . '%' : number_format($coupon->value, 2) . ' ฿' }}
          </div>
        </div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">สถานะ</label>
          <div class="mt-1">
            @if($coupon->is_active)
              <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full" style="background:rgba(16,185,129,0.1);color:#10b981;">ใช้งาน</span>
            @else
              <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full" style="background:rgba(107,114,128,0.1);color:#6b7280;">ปิดใช้งาน</span>
            @endif
          </div>
        </div>
      </div>
      <div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">ยอดสั่งซื้อขั้นต่ำ</label>
          <div class="mt-1">{{ $coupon->min_order ? number_format($coupon->min_order, 2) . ' ฿' : '-' }}</div>
        </div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">ส่วนลดสูงสุด</label>
          <div class="mt-1">{{ $coupon->max_discount ? number_format($coupon->max_discount, 2) . ' ฿' : '-' }}</div>
        </div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">จำนวนการใช้</label>
          <div class="mt-1">{{ $coupon->usage_count ?? 0 }} / {{ $coupon->usage_limit ?? '∞' }}</div>
        </div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">จำกัดต่อผู้ใช้</label>
          <div class="mt-1">{{ $coupon->per_user_limit ?? '-' }}</div>
        </div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">วันเริ่มต้น</label>
          <div class="mt-1">{{ $coupon->start_date ? \Carbon\Carbon::parse($coupon->start_date)->format('d/m/Y H:i') : '-' }}</div>
        </div>
        <div class="mb-3">
          <label class="text-gray-500 text-xs uppercase tracking-wider font-semibold">วันหมดอายุ</label>
          <div class="mt-1">{{ $coupon->end_date ? \Carbon\Carbon::parse($coupon->end_date)->format('d/m/Y H:i') : '-' }}</div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
