@extends('layouts.admin')

@section('title', 'Refund ' . $refund->request_number)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-5">

  <div class="space-y-4">
    <a href="{{ route('admin.refunds.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
      <i class="bi bi-chevron-left"></i> กลับไปรายการ
    </a>

    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
        <div>
          <span class="font-mono text-sm text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $refund->request_number }}</span>
          <h1 class="text-xl font-bold text-slate-800 mt-2">Refund Request</h1>
          <p class="text-xs text-gray-500 mt-1">{{ $refund->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div class="text-right">
          <div class="text-3xl font-bold text-red-600">-฿{{ number_format($refund->requested_amount, 2) }}</div>
          <span class="text-xs px-2 py-0.5 bg-{{ $refund->status_color }}-100 text-{{ $refund->status_color }}-700 rounded font-medium">{{ $refund->status_label }}</span>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 text-sm border-t pt-3">
        <div>
          <div class="text-xs text-gray-500">ลูกค้า</div>
          <div class="font-medium">{{ $refund->user->first_name }} {{ $refund->user->last_name }}</div>
          <div class="text-xs text-gray-500">{{ $refund->user->email }}</div>
        </div>
        <div>
          <div class="text-xs text-gray-500">คำสั่งซื้อ</div>
          <a href="{{ route('admin.orders.show', $refund->order_id) }}" class="text-indigo-600 hover:underline font-mono">
            #{{ $refund->order->order_number ?? $refund->order_id }}
          </a>
          <div class="text-xs text-gray-500">ยอด ฿{{ number_format($refund->order->total ?? 0, 2) }}</div>
        </div>
      </div>
    </div>

    {{-- Reason + Description --}}
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <h3 class="font-semibold text-slate-800 mb-3">รายละเอียด</h3>
      <div class="space-y-3">
        <div>
          <div class="text-xs text-gray-500 mb-1">เหตุผล</div>
          <span class="inline-block px-3 py-1 bg-amber-100 text-amber-700 rounded text-sm font-medium">{{ $refund->reason_label }}</span>
        </div>
        <div>
          <div class="text-xs text-gray-500 mb-1">คำอธิบายจากลูกค้า</div>
          <div class="bg-gray-50 p-3 rounded-lg text-sm whitespace-pre-wrap">{{ $refund->description }}</div>
        </div>
        @if($refund->admin_note)
        <div>
          <div class="text-xs text-gray-500 mb-1">หมายเหตุจาก Admin</div>
          <div class="bg-indigo-50 border-l-4 border-indigo-400 p-3 rounded-r-lg text-sm whitespace-pre-wrap">{{ $refund->admin_note }}</div>
        </div>
        @endif
        @if($refund->rejection_reason)
        <div>
          <div class="text-xs text-gray-500 mb-1">เหตุผลปฏิเสธ</div>
          <div class="bg-red-50 border-l-4 border-red-400 p-3 rounded-r-lg text-sm">{{ $refund->rejection_reason }}</div>
        </div>
        @endif
      </div>
    </div>

    {{-- Order Items --}}
    @if($refund->order && $refund->order->items)
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <h3 class="font-semibold text-slate-800 mb-3">สินค้าในคำสั่งซื้อ ({{ $refund->order->items->count() }})</h3>
      <div class="divide-y divide-gray-100">
        @foreach($refund->order->items->take(10) as $item)
        <div class="flex items-center gap-3 py-2 text-sm">
          @if($item->thumbnail_url)
            <img src="{{ $item->thumbnail_url }}" alt="" class="w-12 h-12 object-cover rounded">
          @endif
          <div class="flex-1 min-w-0">
            <div class="text-xs text-gray-500">Photo #{{ $item->photo_id }}</div>
          </div>
          <div class="text-right font-semibold">฿{{ number_format($item->price, 2) }}</div>
        </div>
        @endforeach
      </div>
    </div>
    @endif
  </div>

  {{-- Right: Actions --}}
  <div class="space-y-4">
    @if(in_array($refund->status, ['pending', 'under_review']))

    @if($refund->status === 'pending')
    <form method="POST" action="{{ route('admin.refunds.mark-review', $refund) }}">
      @csrf
      <button type="submit" class="w-full px-4 py-2.5 bg-blue-500 text-white rounded-xl font-medium hover:bg-blue-600">
        <i class="bi bi-search"></i> เริ่มพิจารณา
      </button>
    </form>
    @endif

    {{-- Approve Form --}}
    <div class="bg-white border border-emerald-200 rounded-2xl p-5">
      <h3 class="font-semibold text-emerald-700 mb-3">
        <i class="bi bi-check-circle"></i> อนุมัติคำขอ
      </h3>
      <form method="POST" action="{{ route('admin.refunds.approve', $refund) }}" class="space-y-3">
        @csrf
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">ยอดอนุมัติ (฿)</label>
          <input type="number" name="approved_amount" step="0.01" min="0" max="{{ $refund->requested_amount }}"
                 value="{{ $refund->requested_amount }}" required
                 class="w-full px-3 py-2 border border-emerald-200 rounded-lg">
          <p class="text-xs text-gray-500 mt-1">สูงสุด ฿{{ number_format($refund->requested_amount, 2) }}</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">หมายเหตุ (ไม่บังคับ)</label>
          <textarea name="admin_note" rows="3" maxlength="1000"
                    class="w-full px-3 py-2 border border-emerald-200 rounded-lg"></textarea>
        </div>
        <button type="submit" onclick="return confirm('อนุมัติและดำเนินการคืนเงิน?')"
                class="w-full px-4 py-2.5 bg-emerald-500 text-white rounded-xl font-medium hover:bg-emerald-600">
          <i class="bi bi-check2"></i> อนุมัติ
        </button>
      </form>
    </div>

    {{-- Reject Form --}}
    <div class="bg-white border border-red-200 rounded-2xl p-5">
      <h3 class="font-semibold text-red-700 mb-3">
        <i class="bi bi-x-circle"></i> ปฏิเสธคำขอ
      </h3>
      <form method="POST" action="{{ route('admin.refunds.reject', $refund) }}" class="space-y-3">
        @csrf
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">เหตุผลปฏิเสธ <span class="text-red-500">*</span></label>
          <textarea name="reason" rows="3" maxlength="500" required
                    placeholder="อธิบายเหตุผลที่ปฏิเสธ..."
                    class="w-full px-3 py-2 border border-red-200 rounded-lg"></textarea>
        </div>
        <button type="submit" onclick="return confirm('ปฏิเสธคำขอ?')"
                class="w-full px-4 py-2.5 bg-red-500 text-white rounded-xl font-medium hover:bg-red-600">
          <i class="bi bi-x"></i> ปฏิเสธ
        </button>
      </form>
    </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl p-4 text-xs space-y-1">
      <div class="flex justify-between"><span class="text-gray-500">สร้าง:</span><span>{{ $refund->created_at->format('d/m/Y H:i') }}</span></div>
      @if($refund->reviewed_at)<div class="flex justify-between"><span class="text-gray-500">พิจารณา:</span><span>{{ $refund->reviewed_at->format('d/m/Y H:i') }}</span></div>@endif
      @if($refund->resolved_at)<div class="flex justify-between"><span class="text-gray-500">จัดการเสร็จ:</span><span>{{ $refund->resolved_at->format('d/m/Y H:i') }}</span></div>@endif
      @if($refund->reviewedByAdmin)<div class="flex justify-between"><span class="text-gray-500">โดย:</span><span>{{ $refund->reviewedByAdmin->first_name ?? 'Admin' }}</span></div>@endif
    </div>
  </div>
</div>
@endsection
