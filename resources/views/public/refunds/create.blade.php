@extends('layouts.app')

@section('title', 'ขอคืนเงิน')

@section('content')
<div class="max-w-2xl mx-auto">

  <a href="{{ route('orders.show', $order->id) }}" class="text-sm text-gray-500 hover:text-gray-700">
    <i class="bi bi-chevron-left"></i> กลับไปคำสั่งซื้อ
  </a>

  <div class="mt-3 mb-6">
    <h1 class="text-2xl font-bold text-slate-800">
      <i class="bi bi-arrow-counterclockwise text-indigo-500 mr-2"></i>ขอคืนเงิน
    </h1>
    <p class="text-sm text-gray-500 mt-1">สำหรับคำสั่งซื้อ <strong>#{{ $order->order_number }}</strong></p>
  </div>

  <form method="POST" action="{{ route('refunds.store', $order->id) }}" class="bg-white border border-gray-100 rounded-2xl p-6 space-y-5">
    @csrf

    {{-- Order summary --}}
    <div class="bg-indigo-50 rounded-xl p-4">
      <div class="flex items-center justify-between text-sm">
        <span class="text-gray-600">เลขที่คำสั่งซื้อ</span>
        <span class="font-bold text-indigo-700">#{{ $order->order_number }}</span>
      </div>
      <div class="flex items-center justify-between text-sm mt-1">
        <span class="text-gray-600">ยอดชำระ</span>
        <span class="font-bold text-indigo-700">฿{{ number_format($order->total, 2) }}</span>
      </div>
      <div class="flex items-center justify-between text-sm mt-1">
        <span class="text-gray-600">วันที่</span>
        <span class="text-gray-700">{{ $order->created_at->format('d/m/Y H:i') }}</span>
      </div>
    </div>

    {{-- Amount --}}
    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-1.5">ยอดเงินที่ขอคืน <span class="text-red-500">*</span></label>
      <div class="relative">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">฿</span>
        <input type="number" name="requested_amount" required step="0.01" min="1" max="{{ $order->total }}"
               value="{{ old('requested_amount', $order->total) }}"
               class="w-full pl-8 pr-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-200">
      </div>
      <p class="text-xs text-gray-500 mt-1">สูงสุด ฿{{ number_format($order->total, 2) }}</p>
    </div>

    {{-- Reason --}}
    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-1.5">เหตุผล <span class="text-red-500">*</span></label>
      <div class="space-y-2">
        @foreach(\App\Models\RefundRequest::REASONS as $key => $label)
        <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
          <input type="radio" name="reason" value="{{ $key }}" required {{ old('reason') === $key ? 'checked' : '' }}
                 class="text-indigo-600">
          <span class="text-sm">{{ $label }}</span>
        </label>
        @endforeach
      </div>
    </div>

    {{-- Description --}}
    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-1.5">รายละเอียด <span class="text-red-500">*</span></label>
      <textarea name="description" required rows="5" minlength="10" maxlength="2000"
                placeholder="กรุณาอธิบายเหตุผลโดยละเอียด..."
                class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-200">{{ old('description') }}</textarea>
      <p class="text-xs text-gray-500 mt-1">อย่างน้อย 10 ตัวอักษร — ยิ่งละเอียด พิจารณาได้เร็วขึ้น</p>
    </div>

    {{-- Policy Info --}}
    <div class="bg-amber-50 border-l-4 border-amber-400 rounded-r-lg p-4">
      <h3 class="font-semibold text-amber-800 text-sm mb-2">
        <i class="bi bi-info-circle-fill"></i> เงื่อนไขการคืนเงิน
      </h3>
      <ul class="text-xs text-amber-700 space-y-1 list-disc list-inside">
        <li>ขอคืนได้ภายใน 7 วันหลังชำระเงิน</li>
        <li>ทีมงานจะพิจารณาภายใน 3-5 วันทำการ</li>
        <li>หากอนุมัติ เงินจะคืนเข้าบัญชีเดิมภายใน 7-14 วัน</li>
        <li>ภาพที่ดาวน์โหลดไปแล้วต้องลบออกจากอุปกรณ์ทั้งหมด</li>
      </ul>
    </div>

    {{-- Submit --}}
    <div class="flex gap-3 pt-3">
      <a href="{{ route('orders.show', $order->id) }}" class="px-5 py-2.5 border border-gray-200 text-gray-700 rounded-xl font-medium hover:bg-gray-50">ยกเลิก</a>
      <button type="submit" class="flex-1 px-5 py-2.5 bg-gradient-to-br from-red-500 to-red-600 text-white rounded-xl font-medium hover:shadow-lg">
        <i class="bi bi-send mr-1"></i>ส่งคำขอคืนเงิน
      </button>
    </div>
  </form>
</div>
@endsection
