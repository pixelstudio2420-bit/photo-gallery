@extends('layouts.admin')

@section('title', 'คำขอคืนเงิน')

@section('content')
<div class="space-y-5">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-arrow-counterclockwise text-indigo-500 mr-2"></i>คำขอคืนเงิน
      </h1>
      <p class="text-sm text-gray-500 mt-1">ตรวจสอบและดำเนินการกับคำขอคืนเงินจากลูกค้า</p>
    </div>
  </div>

  {{-- Stats --}}
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
    <div class="bg-white border border-gray-100 rounded-2xl p-3">
      <div class="text-xs text-gray-500">รอพิจารณา</div>
      <div class="text-2xl font-bold text-amber-600">{{ $stats['pending'] }}</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3">
      <div class="text-xs text-gray-500">กำลังพิจารณา</div>
      <div class="text-2xl font-bold text-blue-600">{{ $stats['under_review'] }}</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3">
      <div class="text-xs text-gray-500">อนุมัติ</div>
      <div class="text-2xl font-bold text-emerald-600">{{ $stats['approved'] }}</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3">
      <div class="text-xs text-gray-500">ปฏิเสธ</div>
      <div class="text-2xl font-bold text-red-600">{{ $stats['rejected'] }}</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3">
      <div class="text-xs text-gray-500">ขอทั้งหมด</div>
      <div class="text-lg font-bold">฿{{ number_format($stats['total_requested'], 0) }}</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3">
      <div class="text-xs text-gray-500">คืนไปแล้ว</div>
      <div class="text-lg font-bold text-red-600">-฿{{ number_format($stats['total_approved'], 0) }}</div>
    </div>
  </div>

  {{-- Filter --}}
  <form method="GET" class="bg-white border border-gray-100 rounded-2xl p-4 flex gap-3 flex-wrap">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="ค้นหา..."
           class="flex-1 min-w-[200px] px-3 py-2 border border-gray-200 rounded-lg text-sm">
    <select name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
      <option value="">รอดำเนินการ (default)</option>
      @foreach(\App\Models\RefundRequest::STATUSES as $k => $label)
      <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
    <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm font-medium">ค้นหา</button>
  </form>

  {{-- Table --}}
  <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-3 text-xs uppercase text-gray-600">Request</th>
            <th class="text-left p-3 text-xs uppercase text-gray-600">Customer</th>
            <th class="text-left p-3 text-xs uppercase text-gray-600">Order</th>
            <th class="text-left p-3 text-xs uppercase text-gray-600">Reason</th>
            <th class="text-right p-3 text-xs uppercase text-gray-600">Amount</th>
            <th class="text-center p-3 text-xs uppercase text-gray-600">Status</th>
            <th class="text-left p-3 text-xs uppercase text-gray-600">Created</th>
          </tr>
        </thead>
        <tbody>
          @forelse($refunds as $r)
          <tr class="border-t border-gray-50 hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('admin.refunds.show', $r) }}'">
            <td class="p-3 font-mono text-xs text-indigo-600">{{ $r->request_number }}</td>
            <td class="p-3">
              <div class="font-medium">{{ $r->user->first_name ?? 'N/A' }}</div>
              <div class="text-xs text-gray-500">{{ $r->user->email ?? 'N/A' }}</div>
            </td>
            <td class="p-3 text-xs">
              <a href="{{ route('admin.orders.show', $r->order_id) }}" class="text-indigo-600 hover:underline font-mono">
                #{{ $r->order->order_number ?? $r->order_id }}
              </a>
            </td>
            <td class="p-3 text-xs">{{ $r->reason_label }}</td>
            <td class="p-3 text-right font-bold text-red-600">-฿{{ number_format($r->requested_amount, 2) }}</td>
            <td class="p-3 text-center">
              <span class="text-xs px-2 py-0.5 bg-{{ $r->status_color }}-100 text-{{ $r->status_color }}-700 rounded font-medium">{{ $r->status_label }}</span>
            </td>
            <td class="p-3 text-xs text-gray-500">{{ $r->created_at->diffForHumans() }}</td>
          </tr>
          @empty
          <tr><td colspan="7" class="p-12 text-center text-gray-500"><i class="bi bi-inbox text-3xl"></i><p>ไม่พบคำขอคืนเงิน</p></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($refunds->hasPages())
  <div class="flex justify-center">{{ $refunds->links() }}</div>
  @endif
</div>
@endsection
