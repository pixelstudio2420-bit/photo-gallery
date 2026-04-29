@extends('layouts.app')

@section('title', 'คำสั่งซื้อของฉัน')

@section('content')
@php
  $statusMeta = [
    'paid'            => ['label' => 'ชำระแล้ว',    'dot' => 'bg-emerald-500', 'bg' => 'bg-emerald-100 dark:bg-emerald-500/20', 'text' => 'text-emerald-700 dark:text-emerald-300', 'icon' => 'bi-check-circle-fill'],
    'pending'         => ['label' => 'รอชำระ',      'dot' => 'bg-amber-500',   'bg' => 'bg-amber-100 dark:bg-amber-500/20',     'text' => 'text-amber-700 dark:text-amber-300',     'icon' => 'bi-clock'],
    'pending_payment' => ['label' => 'รอชำระเงิน',   'dot' => 'bg-amber-500',   'bg' => 'bg-amber-100 dark:bg-amber-500/20',     'text' => 'text-amber-700 dark:text-amber-300',     'icon' => 'bi-clock'],
    'pending_review'  => ['label' => 'กำลังตรวจสอบ',   'dot' => 'bg-blue-500',    'bg' => 'bg-blue-100 dark:bg-blue-500/20',       'text' => 'text-blue-700 dark:text-blue-300',       'icon' => 'bi-hourglass-split'],
    'cancelled'       => ['label' => 'ยกเลิก',       'dot' => 'bg-rose-500',    'bg' => 'bg-rose-100 dark:bg-rose-500/20',       'text' => 'text-rose-700 dark:text-rose-300',       'icon' => 'bi-x-circle-fill'],
    'refunded'        => ['label' => 'คืนเงินแล้ว',    'dot' => 'bg-slate-500',   'bg' => 'bg-slate-100 dark:bg-slate-500/20',     'text' => 'text-slate-700 dark:text-slate-300',     'icon' => 'bi-arrow-counterclockwise'],
  ];
@endphp

<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
          <i class="bi bi-receipt"></i>
        </span>
        คำสั่งซื้อของฉัน
      </h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">ดูประวัติและสถานะการสั่งซื้อทั้งหมด</p>
    </div>
    <a href="{{ route('events.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 text-sm transition">
      <i class="bi bi-plus-circle"></i> สั่งซื้อใหม่
    </a>
  </div>

  @if($orders->count() > 0)
    {{-- ═══════════════ ORDERS TABLE ═══════════════ --}}
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-white/5">
            <tr>
              <th class="pl-5 pr-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">คำสั่งซื้อ</th>
              <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">รายการ</th>
              <th class="px-3 py-3 text-right text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ยอดรวม</th>
              <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">สถานะ</th>
              <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden md:table-cell">วันที่</th>
              <th class="pr-5 py-3" style="width:60px;"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-white/5">
            @foreach($orders as $order)
            @php $sm = $statusMeta[$order->status] ?? $statusMeta['pending']; @endphp
            <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition">
              <td class="pl-5 pr-3 py-4">
                <div class="font-mono font-semibold text-sm text-slate-900 dark:text-white">#{{ $order->id }}</div>
                <div class="md:hidden text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $order->created_at->format('d/m/Y') }}</div>
              </td>
              <td class="px-3 py-4">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-xs font-medium">
                  <i class="bi bi-images"></i> {{ $order->items_count ?? $order->items->count() }} รายการ
                </span>
              </td>
              <td class="px-3 py-4 text-right">
                <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($order->total, 0) }} ฿</span>
              </td>
              <td class="px-3 py-4">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full {{ $sm['bg'] }} {{ $sm['text'] }} text-xs font-semibold">
                  <span class="relative flex h-2 w-2">
                    @if(in_array($order->status, ['pending','pending_payment','pending_review']))
                      <span class="absolute inline-flex h-full w-full rounded-full {{ $sm['dot'] }} opacity-75 animate-ping"></span>
                    @endif
                    <span class="relative inline-flex rounded-full h-2 w-2 {{ $sm['dot'] }}"></span>
                  </span>
                  {{ $sm['label'] }}
                </span>
              </td>
              <td class="px-3 py-4 text-slate-500 dark:text-slate-400 hidden md:table-cell text-xs">{{ $order->created_at->format('d/m/Y H:i') }}</td>
              <td class="pr-5 py-4">
                <div class="flex items-center justify-end gap-1.5">
                  {{-- Review button: only on paid orders that aren't reviewed yet --}}
                  @if($order->status === 'paid')
                    @if(in_array($order->id, $reviewedOrderIds ?? []))
                      <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[11px] font-semibold"
                            title="คุณได้รีวิวคำสั่งซื้อนี้แล้ว">
                        <i class="bi bi-check-circle-fill text-[10px]"></i>
                        รีวิวแล้ว
                      </span>
                    @else
                      <a href="{{ route('reviews.create', $order->id) }}"
                         class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 hover:bg-amber-500 hover:text-white dark:hover:bg-amber-500 transition text-[11px] font-semibold"
                         title="เขียนรีวิว">
                        <i class="bi bi-star-fill text-[10px]"></i>
                        <span class="hidden sm:inline">เขียนรีวิว</span>
                      </a>
                    @endif
                  @endif
                  <a href="{{ route('orders.show', $order->id) }}"
                     class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500 hover:text-white dark:hover:bg-indigo-500 transition"
                     title="ดูรายละเอียด">
                    <i class="bi bi-eye text-xs"></i>
                  </a>
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    @if($orders->hasPages())
      <div class="mt-8 flex justify-center">
        {{ $orders->withQueryString()->links() }}
      </div>
    @endif
  @else
    {{-- ═══════════════ EMPTY STATE ═══════════════ --}}
    <div class="text-center py-20 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-500/20 dark:to-purple-500/20 text-indigo-500 dark:text-indigo-400 mb-4">
        <i class="bi bi-receipt text-3xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">ยังไม่มีคำสั่งซื้อ</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">เริ่มต้นเลือกซื้อภาพถ่ายสวยๆ กันเถอะ</p>
      <a href="{{ route('events.index') }}"
         class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-md hover:shadow-lg transition-all">
        <i class="bi bi-images"></i> เลือกซื้อภาพถ่าย
      </a>
    </div>
  @endif
</div>
@endsection
