@extends('layouts.app')

@section('title', 'คำขอคืนเงินของฉัน')

@section('content')
<div class="max-w-4xl mx-auto">

  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">
        <i class="bi bi-arrow-counterclockwise text-indigo-500 mr-2"></i>คำขอคืนเงิน
      </h1>
      <p class="text-sm text-gray-500 mt-1">ติดตามสถานะคำขอคืนเงินของคุณ</p>
    </div>
    <a href="{{ route('orders.index') }}" class="px-4 py-2 border border-gray-200 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-50">
      <i class="bi bi-list"></i> คำสั่งซื้อของฉัน
    </a>
  </div>

  <div class="space-y-3">
    @forelse($requests as $r)
    <a href="{{ route('refunds.show', $r->id) }}" class="block bg-white border border-gray-100 rounded-2xl p-5 hover:border-indigo-200 hover:shadow-md transition">
      <div class="flex items-center justify-between gap-3 mb-2">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="font-mono text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $r->request_number }}</span>
          <span class="text-xs px-2 py-0.5 bg-{{ $r->status_color }}-100 text-{{ $r->status_color }}-700 rounded font-medium">{{ $r->status_label }}</span>
        </div>
        <div class="text-lg font-bold text-red-600">-฿{{ number_format($r->requested_amount, 2) }}</div>
      </div>

      <div class="text-sm text-gray-700 mb-2">
        <strong>เหตุผล:</strong> {{ $r->reason_label }}
      </div>
      <p class="text-sm text-gray-600 line-clamp-2">{{ $r->description }}</p>

      <div class="flex items-center justify-between text-xs text-gray-500 mt-3 pt-3 border-t border-gray-50">
        <span>Order #{{ $r->order->order_number ?? $r->order_id }}</span>
        <span>{{ $r->created_at->diffForHumans() }}</span>
      </div>
    </a>
    @empty
    <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center">
      <i class="bi bi-inbox text-4xl text-gray-300"></i>
      <p class="text-gray-500 mt-2">ยังไม่มีคำขอคืนเงิน</p>
    </div>
    @endforelse
  </div>

  @if($requests->hasPages())
  <div class="flex justify-center mt-6">{{ $requests->links() }}</div>
  @endif
</div>
@endsection
