@extends('layouts.app')

@section('title', 'คำขอคืนเงิน ' . $refundRequest->request_number)

@section('content')
<div class="max-w-3xl mx-auto space-y-4">

  <a href="{{ route('refunds.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
    <i class="bi bi-chevron-left"></i> กลับไปรายการ
  </a>

  {{-- Header --}}
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div>
        <div class="flex items-center gap-2 flex-wrap mb-1">
          <span class="font-mono text-sm text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $refundRequest->request_number }}</span>
          <span class="text-xs px-2 py-0.5 bg-{{ $refundRequest->status_color }}-100 text-{{ $refundRequest->status_color }}-700 rounded font-medium">{{ $refundRequest->status_label }}</span>
        </div>
        <h1 class="text-xl font-bold text-slate-800">คำขอคืนเงิน</h1>
        <p class="text-xs text-gray-500 mt-1">ส่งเมื่อ {{ $refundRequest->created_at->format('d/m/Y H:i') }}</p>
      </div>
      <div class="text-right">
        <div class="text-2xl font-bold text-red-600">-฿{{ number_format($refundRequest->requested_amount, 2) }}</div>
        @if($refundRequest->approved_amount && $refundRequest->approved_amount != $refundRequest->requested_amount)
        <div class="text-xs text-emerald-600">อนุมัติ ฿{{ number_format($refundRequest->approved_amount, 2) }}</div>
        @endif
      </div>
    </div>
  </div>

  {{-- Status Timeline --}}
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <h3 class="font-semibold text-slate-800 mb-4">ขั้นตอนการพิจารณา</h3>
    <div class="space-y-3">
      @php
        $steps = [
          ['status' => 'pending',       'label' => 'ส่งคำขอ',       'icon' => 'send'],
          ['status' => 'under_review',  'label' => 'กำลังพิจารณา',   'icon' => 'search'],
          ['status' => 'approved',      'label' => 'อนุมัติแล้ว',    'icon' => 'check-circle'],
          ['status' => 'completed',     'label' => 'คืนเงินเรียบร้อย','icon' => 'cash-coin'],
        ];
        $currentIdx = match ($refundRequest->status) {
          'pending' => 0, 'under_review' => 1,
          'approved' => 2, 'processing' => 2,
          'completed' => 3,
          'rejected' => -1, 'cancelled' => -1,
          default => 0,
        };
      @endphp

      @if(in_array($refundRequest->status, ['rejected', 'cancelled']))
        <div class="flex items-center gap-3 p-3 bg-red-50 border border-red-200 rounded-lg">
          <i class="bi bi-x-circle-fill text-red-500 text-xl"></i>
          <div>
            <div class="font-semibold text-red-800">{{ $refundRequest->status === 'rejected' ? 'ถูกปฏิเสธ' : 'ยกเลิก' }}</div>
            @if($refundRequest->rejection_reason)
              <div class="text-xs text-red-700 mt-1">{{ $refundRequest->rejection_reason }}</div>
            @endif
          </div>
        </div>
      @else
        @foreach($steps as $i => $s)
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0
                      {{ $i <= $currentIdx ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-400' }}">
            <i class="bi bi-{{ $s['icon'] }}"></i>
          </div>
          <div class="flex-1">
            <div class="font-semibold {{ $i <= $currentIdx ? 'text-emerald-700' : 'text-gray-500' }}">{{ $s['label'] }}</div>
          </div>
          @if($i <= $currentIdx)
            <i class="bi bi-check-circle-fill text-emerald-500"></i>
          @endif
        </div>
        @endforeach
      @endif
    </div>
  </div>

  {{-- Details --}}
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <h3 class="font-semibold text-slate-800 mb-3">รายละเอียดคำขอ</h3>
    <div class="space-y-3 text-sm">
      <div class="flex justify-between">
        <span class="text-gray-600">คำสั่งซื้อ</span>
        <a href="{{ route('orders.show', $refundRequest->order_id) }}" class="font-mono text-indigo-600 hover:underline">
          #{{ $refundRequest->order->order_number ?? $refundRequest->order_id }}
        </a>
      </div>
      <div class="flex justify-between">
        <span class="text-gray-600">เหตุผล</span>
        <span class="font-medium">{{ $refundRequest->reason_label }}</span>
      </div>
      <div>
        <div class="text-gray-600 mb-1">รายละเอียด</div>
        <p class="text-gray-700 bg-gray-50 rounded-lg p-3 whitespace-pre-wrap">{{ $refundRequest->description }}</p>
      </div>

      @if($refundRequest->admin_note)
      <div>
        <div class="text-gray-600 mb-1">หมายเหตุจากทีมงาน</div>
        <p class="text-gray-700 bg-indigo-50 border-l-4 border-indigo-400 rounded-r-lg p-3">{{ $refundRequest->admin_note }}</p>
      </div>
      @endif

      @if($refundRequest->reviewed_at)
      <div class="flex justify-between text-xs text-gray-500">
        <span>พิจารณาเมื่อ</span>
        <span>{{ $refundRequest->reviewed_at->format('d/m/Y H:i') }}</span>
      </div>
      @endif
    </div>
  </div>

  {{-- Cancel Button --}}
  @if($refundRequest->canBeCancelledByUser())
  <form method="POST" action="{{ route('refunds.cancel', $refundRequest->id) }}"
        onsubmit="return confirm('ยกเลิกคำขอคืนเงินนี้?')">
    @csrf
    @method('DELETE')
    <button type="submit" class="w-full px-4 py-2.5 border border-red-200 text-red-600 rounded-xl font-medium hover:bg-red-50">
      <i class="bi bi-x-lg"></i> ยกเลิกคำขอ
    </button>
  </form>
  @endif
</div>
@endsection
