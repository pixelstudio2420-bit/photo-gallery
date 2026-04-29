@extends('layouts.app')

@section('title', 'คำสั่งซื้อของฉัน')

{{-- =======================================================================
     PROFILE · ORDERS
     -------------------------------------------------------------------
     Design language matches public/profile/dashboard.blade.php:
       • max-w-6xl container, rounded-2xl cards, dark-mode first-class
       • gradient icon chip in the page header
       • tab navigation identical to the dashboard (border-b active state)
     ====================================================================== --}}
@section('content')
@php
  $statusMap = [
    'pending_payment' => ['bg' => 'bg-amber-100 dark:bg-amber-500/20',     'text' => 'text-amber-700 dark:text-amber-300',     'ring' => 'ring-amber-500/30',    'label' => 'รอชำระเงิน',   'icon' => 'bi-hourglass-split'],
    'pending_review'  => ['bg' => 'bg-blue-100 dark:bg-blue-500/20',        'text' => 'text-blue-700 dark:text-blue-300',       'ring' => 'ring-blue-500/30',     'label' => 'รอตรวจสอบ',   'icon' => 'bi-shield-check'],
    'paid'            => ['bg' => 'bg-emerald-100 dark:bg-emerald-500/20',  'text' => 'text-emerald-700 dark:text-emerald-300', 'ring' => 'ring-emerald-500/30',  'label' => 'ชำระแล้ว',     'icon' => 'bi-check-circle-fill'],
    'cancelled'       => ['bg' => 'bg-rose-100 dark:bg-rose-500/20',        'text' => 'text-rose-700 dark:text-rose-300',       'ring' => 'ring-rose-500/30',     'label' => 'ยกเลิก',       'icon' => 'bi-x-circle-fill'],
  ];

  // Count orders by status for the filter chips
  $filterCounts = [
    'all'             => \App\Models\Order::where('user_id', auth()->id())->count(),
    'pending_payment' => \App\Models\Order::where('user_id', auth()->id())->where('status', 'pending_payment')->count(),
    'pending_review'  => \App\Models\Order::where('user_id', auth()->id())->where('status', 'pending_review')->count(),
    'paid'            => \App\Models\Order::where('user_id', auth()->id())->where('status', 'paid')->count(),
    'cancelled'       => \App\Models\Order::where('user_id', auth()->id())->where('status', 'cancelled')->count(),
  ];
@endphp

<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  {{-- ───────────── Header ───────────── --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
        <i class="bi bi-receipt"></i>
      </span>
      คำสั่งซื้อของฉัน
    </h1>
    <a href="{{ route('events.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 text-sm font-medium transition">
      <i class="bi bi-camera"></i> เลือกซื้อรูปเพิ่ม
    </a>
  </div>

  {{-- ───────────── Tab Navigation ───────────── --}}
  <div class="mb-6 flex items-center gap-1 overflow-x-auto pb-1 border-b border-slate-200 dark:border-white/10">
    @foreach([
      ['route' => route('profile'),           'label' => 'ภาพรวม',      'icon' => 'bi-grid',     'active' => false],
      ['route' => route('profile.orders'),    'label' => 'คำสั่งซื้อ',   'icon' => 'bi-receipt',  'active' => true],
      ['route' => route('profile.downloads'), 'label' => 'ดาวน์โหลด', 'icon' => 'bi-download', 'active' => false],
      ['route' => route('profile.reviews'),   'label' => 'รีวิว',        'icon' => 'bi-star',     'active' => false],
      ['route' => route('wishlist.index'),    'label' => 'รายการโปรด','icon' => 'bi-heart',    'active' => false],
      ['route' => route('profile.referrals'), 'label' => 'แนะนำเพื่อน', 'icon' => 'bi-people-fill','active' => false],
    ] as $tab)
      <a href="{{ $tab['route'] }}"
         class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition
            {{ $tab['active']
                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
        <i class="bi {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  {{-- ───────────── Status Filter Chips ───────────── --}}
  <div class="mb-5 flex flex-wrap items-center gap-2">
    @php
      $chips = [
        ['key' => null,              'label' => 'ทั้งหมด',   'icon' => 'bi-list',              'count' => $filterCounts['all'],             'tone' => 'slate'],
        ['key' => 'pending_payment', 'label' => 'รอชำระ',     'icon' => 'bi-hourglass-split',   'count' => $filterCounts['pending_payment'], 'tone' => 'amber'],
        ['key' => 'pending_review',  'label' => 'รอตรวจสอบ', 'icon' => 'bi-shield-check',      'count' => $filterCounts['pending_review'],  'tone' => 'blue'],
        ['key' => 'paid',            'label' => 'ชำระแล้ว',   'icon' => 'bi-check-circle-fill', 'count' => $filterCounts['paid'],            'tone' => 'emerald'],
        ['key' => 'cancelled',       'label' => 'ยกเลิก',     'icon' => 'bi-x-circle-fill',     'count' => $filterCounts['cancelled'],       'tone' => 'rose'],
      ];
      // Active-state class map — keep Tailwind happy by using concrete class lists
      $activeCls = [
        'slate'   => 'bg-slate-800 dark:bg-white text-white dark:text-slate-900 border-slate-800 dark:border-white',
        'amber'   => 'bg-amber-500 text-white border-amber-500',
        'blue'    => 'bg-blue-500 text-white border-blue-500',
        'emerald' => 'bg-emerald-500 text-white border-emerald-500',
        'rose'    => 'bg-rose-500 text-white border-rose-500',
      ];
    @endphp

    @foreach($chips as $chip)
      @php $isActive = ($status ?? null) === $chip['key']; @endphp
      <a href="{{ $chip['key'] ? route('profile.orders', ['status' => $chip['key']]) : route('profile.orders') }}"
         class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full border text-xs md:text-sm font-medium transition
            {{ $isActive
                ? $activeCls[$chip['tone']]
                : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-400' }}">
        <i class="bi {{ $chip['icon'] }}"></i>
        {{ $chip['label'] }}
        <span class="inline-flex items-center justify-center min-w-[22px] h-[18px] px-1.5 rounded-full text-[10px] font-bold
            {{ $isActive ? 'bg-white/25 text-white' : 'bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400' }}">
          {{ $chip['count'] }}
        </span>
      </a>
    @endforeach
  </div>

  {{-- ───────────── Orders Table ───────────── --}}
  @if($orders->isEmpty())
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm text-center py-16 px-6">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-500/20 dark:to-purple-500/20 text-indigo-500 dark:text-indigo-400 mb-4">
        <i class="bi bi-receipt text-3xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">
        {{ $status ? 'ไม่พบคำสั่งซื้อในสถานะนี้' : 'ยังไม่มีคำสั่งซื้อ' }}
      </h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">
        @if($status)
          ลองเปลี่ยนตัวกรองหรือดูคำสั่งซื้อทั้งหมด
        @else
          เริ่มต้นเลือกซื้อรูปภาพจากอีเวนต์ที่คุณชื่นชอบ
        @endif
      </p>
      <div class="flex items-center justify-center gap-2 flex-wrap">
        @if($status)
          <a href="{{ route('profile.orders') }}"
             class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10 text-sm font-medium transition">
            <i class="bi bi-arrow-counterclockwise"></i> ล้างตัวกรอง
          </a>
        @endif
        <a href="{{ route('events.index') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white text-sm font-semibold shadow-md transition">
          <i class="bi bi-camera"></i> เลือกซื้อรูปภาพ
        </a>
      </div>
    </div>
  @else
    {{-- Desktop table --}}
    <div class="hidden md:block rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-white/5">
            <tr>
              <th class="pl-5 pr-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">เลขที่</th>
              <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">อีเวนต์</th>
              <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">รายการ</th>
              <th class="px-3 py-3 text-right text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ยอดรวม</th>
              <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">สถานะ</th>
              <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">วันที่</th>
              <th class="pr-5 py-3" style="width:100px;"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-white/5">
            @foreach($orders as $order)
              @php $sc = $statusMap[$order->status] ?? ['bg' => 'bg-slate-100 dark:bg-slate-500/20', 'text' => 'text-slate-700 dark:text-slate-300', 'ring' => 'ring-slate-500/30', 'label' => ucfirst($order->status), 'icon' => 'bi-circle']; @endphp
              <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition">
                <td class="pl-5 pr-3 py-3.5">
                  <a href="{{ route('orders.show', $order->id) }}" class="font-mono font-semibold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                    #{{ $order->order_number ?? $order->id }}
                  </a>
                </td>
                <td class="px-3 py-3.5">
                  <div class="text-slate-800 dark:text-slate-200 truncate max-w-[240px]">{{ $order->event->name ?? '-' }}</div>
                </td>
                <td class="px-3 py-3.5 text-center">
                  <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-xs font-semibold">
                    {{ $order->items_count }} รายการ
                  </span>
                </td>
                <td class="px-3 py-3.5 text-right">
                  <span class="font-bold text-slate-900 dark:text-white">{{ number_format($order->net_amount ?? $order->total_amount ?? 0, 0) }}</span>
                  <span class="text-xs text-slate-500 dark:text-slate-400 ml-0.5">฿</span>
                </td>
                <td class="px-3 py-3.5">
                  <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full {{ $sc['bg'] }} {{ $sc['text'] }} text-xs font-semibold">
                    <i class="bi {{ $sc['icon'] }}"></i> {{ $sc['label'] }}
                  </span>
                </td>
                <td class="px-3 py-3.5 text-xs text-slate-500 dark:text-slate-400">
                  <div>{{ $order->created_at->format('d/m/Y') }}</div>
                  <div class="text-[10px] text-slate-400 dark:text-slate-500">{{ $order->created_at->format('H:i') }}</div>
                </td>
                <td class="pr-5 py-3.5">
                  <div class="flex items-center gap-1.5 justify-end">
                    <a href="{{ route('orders.show', $order->id) }}"
                       class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500 hover:text-white dark:hover:bg-indigo-500 transition"
                       title="ดูรายละเอียด">
                      <i class="bi bi-eye text-[12px]"></i>
                    </a>
                    @if($order->status === 'pending_payment')
                      <a href="{{ route('payment.checkout', $order->id) }}"
                         class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 hover:bg-amber-500 hover:text-white transition"
                         title="ชำระเงิน">
                        <i class="bi bi-credit-card text-[12px]"></i>
                      </a>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- Mobile cards --}}
    <div class="md:hidden space-y-3">
      @foreach($orders as $order)
        @php $sc = $statusMap[$order->status] ?? ['bg' => 'bg-slate-100 dark:bg-slate-500/20', 'text' => 'text-slate-700 dark:text-slate-300', 'ring' => 'ring-slate-500/30', 'label' => ucfirst($order->status), 'icon' => 'bi-circle']; @endphp
        <a href="{{ route('orders.show', $order->id) }}"
           class="block rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-500/40 transition p-4">
          <div class="flex items-start justify-between gap-3 mb-2">
            <div class="min-w-0 flex-1">
              <div class="font-mono font-semibold text-slate-900 dark:text-white text-sm">#{{ $order->order_number ?? $order->id }}</div>
              <div class="text-sm text-slate-700 dark:text-slate-300 truncate mt-0.5">{{ $order->event->name ?? '-' }}</div>
            </div>
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full {{ $sc['bg'] }} {{ $sc['text'] }} text-[11px] font-semibold flex-shrink-0">
              <i class="bi {{ $sc['icon'] }}"></i> {{ $sc['label'] }}
            </span>
          </div>
          <div class="flex items-center justify-between gap-3 mt-3 pt-3 border-t border-slate-100 dark:border-white/5">
            <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
              <span><i class="bi bi-image mr-1"></i>{{ $order->items_count }} รายการ</span>
              <span><i class="bi bi-calendar3 mr-1"></i>{{ $order->created_at->format('d/m/Y') }}</span>
            </div>
            <div class="font-bold text-indigo-600 dark:text-indigo-400 text-sm">
              {{ number_format($order->net_amount ?? $order->total_amount ?? 0, 0) }} ฿
            </div>
          </div>
          @if($order->status === 'pending_payment')
            <div class="mt-3">
              <a href="{{ route('payment.checkout', $order->id) }}"
                 onclick="event.stopPropagation();"
                 class="inline-flex w-full items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-semibold shadow-sm">
                <i class="bi bi-credit-card"></i> ชำระเงินเดี๋ยวนี้
              </a>
            </div>
          @endif
        </a>
      @endforeach
    </div>

    {{-- Pagination --}}
    @if($orders->hasPages())
      <div class="mt-6 flex justify-center">
        {{ $orders->withQueryString()->links() }}
      </div>
    @endif
  @endif
</div>
@endsection
