@extends('layouts.app')

@section('title', 'คำสั่งซื้อดิจิทัล')

@push('styles')
<style>
  @keyframes order-fadein {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .order-enter { animation: order-fadein 0.4s cubic-bezier(0.16, 1, 0.3, 1) both; }

  /* Reuse Tailwind-style pagination from index page */
  .pagination-tw .pagination {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    list-style: none;
    padding: 0;
    margin: 0;
  }
  .pagination-tw .page-item .page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 38px;
    padding: 0 12px;
    border-radius: 10px;
    border: 1px solid rgb(226 232 240);
    background: #fff;
    color: rgb(71 85 105);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.15s;
  }
  .pagination-tw .page-item .page-link:hover {
    background: rgb(238 242 255);
    color: rgb(79 70 229);
    border-color: rgb(199 210 254);
  }
  .pagination-tw .page-item.active .page-link {
    background: linear-gradient(135deg, #6366f1, #a855f7);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
  }
  .pagination-tw .page-item.disabled .page-link {
    opacity: 0.4;
    cursor: not-allowed;
  }
  .dark .pagination-tw .page-item .page-link {
    background: rgb(30 41 59);
    border-color: rgb(51 65 85);
    color: rgb(203 213 225);
  }
  .dark .pagination-tw .page-item .page-link:hover {
    background: rgb(99 102 241 / 0.1);
    color: rgb(165 180 252);
  }
</style>
@endpush

@section('content')
@php
  $typeLabels = [
    'preset'   => ['label' => 'พรีเซ็ต',     'icon' => 'bi-sliders',  'color' => 'from-rose-500 to-pink-500'],
    'overlay'  => ['label' => 'โอเวอร์เลย์', 'icon' => 'bi-layers',   'color' => 'from-amber-500 to-orange-500'],
    'template' => ['label' => 'เทมเพลต',     'icon' => 'bi-grid-3x3', 'color' => 'from-emerald-500 to-teal-500'],
    'other'    => ['label' => 'อื่นๆ',         'icon' => 'bi-box-seam', 'color' => 'from-indigo-500 to-purple-500'],
  ];

  $statusMeta = [
    'pending_payment' => ['label' => 'รอชำระ',     'dot' => 'bg-amber-500',   'bg' => 'bg-amber-100 dark:bg-amber-500/20',   'text' => 'text-amber-700 dark:text-amber-300',  'icon' => 'bi-clock'],
    'pending_review'  => ['label' => 'รอตรวจสอบ',   'dot' => 'bg-blue-500',    'bg' => 'bg-blue-100 dark:bg-blue-500/20',     'text' => 'text-blue-700 dark:text-blue-300',    'icon' => 'bi-hourglass-split'],
    'paid'            => ['label' => 'สำเร็จ',      'dot' => 'bg-emerald-500', 'bg' => 'bg-emerald-100 dark:bg-emerald-500/20','text' => 'text-emerald-700 dark:text-emerald-300','icon' => 'bi-check-circle'],
    'cancelled'       => ['label' => 'ยกเลิก',      'dot' => 'bg-rose-500',    'bg' => 'bg-rose-100 dark:bg-rose-500/20',     'text' => 'text-rose-700 dark:text-rose-300',    'icon' => 'bi-x-circle'],
  ];

  $statusCounts = $stats ?? ['total' => 0, 'pending' => 0, 'paid' => 0, 'revenue' => 0];
  $currentFilter = $status ?? 'all';
@endphp

<div class="max-w-5xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
        <i class="bi bi-bag-check-fill"></i>
      </span>
      คำสั่งซื้อของฉัน
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">ติดตามสถานะและดาวน์โหลดสินค้าดิจิทัลที่คุณซื้อ</p>
  </div>

  {{-- Stats cards --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-6">
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-1">
        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-slate-500 to-slate-700 text-white flex items-center justify-center">
          <i class="bi bi-receipt text-sm"></i>
        </div>
        <span class="text-xs text-slate-500 dark:text-slate-400">ทั้งหมด</span>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($statusCounts['total']) }}</div>
    </div>
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-1">
        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center">
          <i class="bi bi-hourglass-split text-sm"></i>
        </div>
        <span class="text-xs text-slate-500 dark:text-slate-400">กำลังดำเนินการ</span>
      </div>
      <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($statusCounts['pending']) }}</div>
    </div>
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-1">
        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center">
          <i class="bi bi-check-circle text-sm"></i>
        </div>
        <span class="text-xs text-slate-500 dark:text-slate-400">สำเร็จ</span>
      </div>
      <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($statusCounts['paid']) }}</div>
    </div>
    <div class="rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md p-4">
      <div class="flex items-center gap-2 mb-1">
        <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
          <i class="bi bi-wallet2 text-sm"></i>
        </div>
        <span class="text-xs opacity-80">ยอดใช้จ่าย</span>
      </div>
      <div class="text-2xl font-bold">{{ number_format($statusCounts['revenue'], 0) }} <span class="text-sm font-medium">฿</span></div>
    </div>
  </div>

  {{-- Filter tabs --}}
  <div class="mb-5 flex items-center gap-2 overflow-x-auto pb-1">
    @php
      $filters = [
        'all'             => ['label' => 'ทั้งหมด',     'icon' => 'bi-grid'],
        'pending_payment' => ['label' => 'รอชำระ',       'icon' => 'bi-clock'],
        'pending_review'  => ['label' => 'รอตรวจสอบ',     'icon' => 'bi-hourglass-split'],
        'paid'            => ['label' => 'สำเร็จ',        'icon' => 'bi-check-circle'],
        'cancelled'       => ['label' => 'ยกเลิก',        'icon' => 'bi-x-circle'],
      ];
    @endphp
    @foreach($filters as $key => $f)
      <a href="{{ route('products.my-orders') }}{{ $key !== 'all' ? '?status=' . $key : '' }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition
            {{ $currentFilter === $key
                ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900 shadow-md'
                : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/5' }}">
        <i class="bi {{ $f['icon'] }}"></i> {{ $f['label'] }}
      </a>
    @endforeach
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       ORDERS LIST
       ═══════════════════════════════════════════════════════════════ --}}
  @if($orders->count() > 0)
    <div class="space-y-3">
      @foreach($orders as $order)
        @php
          $sm = $statusMeta[$order->status] ?? $statusMeta['pending_payment'];
          $tm = $typeLabels[$order->product->product_type ?? 'other'] ?? $typeLabels['other'];
        @endphp
        <a href="{{ route('products.order', $order->id) }}"
           style="animation-delay: {{ ($loop->index % 10) * 50 }}ms"
           class="order-enter group relative flex items-center gap-4 p-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm hover:shadow-lg hover:border-indigo-200 dark:hover:border-indigo-500/30 hover:-translate-y-0.5 transition-all duration-300">

          {{-- Left status stripe --}}
          <div class="absolute left-0 top-4 bottom-4 w-1 rounded-r-full {{ $sm['dot'] }} opacity-80"></div>

          {{-- Product thumbnail --}}
          <div class="flex-shrink-0 pl-2">
            @if($order->product && $order->product->cover_image)
              <img src="{{ $order->product->cover_image_url }}"
                   class="w-16 h-16 md:w-20 md:h-20 object-cover rounded-xl shadow-sm group-hover:scale-105 transition-transform"
                   alt="{{ $order->product->name }}">
            @else
              <div class="w-16 h-16 md:w-20 md:h-20 rounded-xl bg-gradient-to-br {{ $tm['color'] }} flex items-center justify-center shadow-sm">
                <i class="bi {{ $tm['icon'] }} text-white text-2xl opacity-60"></i>
              </div>
            @endif
          </div>

          {{-- Content --}}
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2 mb-1 flex-wrap">
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 mb-0.5">
                  <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-white/5 text-[10px] font-medium text-slate-600 dark:text-slate-400">
                    <i class="bi {{ $tm['icon'] }}"></i> {{ $tm['label'] }}
                  </span>
                </div>
                <h3 class="font-semibold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 line-clamp-1 leading-snug transition">
                  {{ $order->product->name ?? 'สินค้า' }}
                </h3>
              </div>

              {{-- Status badge --}}
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full {{ $sm['bg'] }} {{ $sm['text'] }} text-xs font-semibold flex-shrink-0">
                <span class="relative flex h-2 w-2">
                  @if(in_array($order->status, ['pending_payment','pending_review']))
                    <span class="absolute inline-flex h-full w-full rounded-full {{ $sm['dot'] }} opacity-75 animate-ping"></span>
                  @endif
                  <span class="relative inline-flex rounded-full h-2 w-2 {{ $sm['dot'] }}"></span>
                </span>
                {{ $sm['label'] }}
              </span>
            </div>

            {{-- Meta row --}}
            <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400 flex-wrap">
              <span class="font-mono">{{ $order->order_number }}</span>
              <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-slate-600"></span>
              <span>{{ $order->created_at->format('d/m/Y H:i') }}</span>
              @if($order->status === 'paid' && isset($order->downloads_remaining) && $order->downloads_remaining > 0)
                <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                <span class="text-emerald-600 dark:text-emerald-400 font-medium">
                  <i class="bi bi-download"></i> ดาวน์โหลดได้อีก {{ $order->downloads_remaining }} ครั้ง
                </span>
              @endif
            </div>
          </div>

          {{-- Price + arrow --}}
          <div class="text-right flex-shrink-0">
            <div class="text-lg md:text-xl font-bold text-indigo-600 dark:text-indigo-400">
              {{ number_format($order->amount, 0) }} <span class="text-xs font-medium">฿</span>
            </div>
            <div class="mt-1 inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 group-hover:bg-indigo-500 group-hover:text-white transition-all group-hover:translate-x-0.5">
              <i class="bi bi-arrow-right"></i>
            </div>
          </div>
        </a>
      @endforeach
    </div>

    {{-- Pagination --}}
    @if($orders->hasPages())
    <div class="mt-8 flex justify-center pagination-tw">
      {{ $orders->links() }}
    </div>
    @endif
  @else
  {{-- Empty state --}}
  <div class="text-center py-16 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-500/20 dark:to-purple-500/20 text-indigo-500 dark:text-indigo-400 mb-4">
      <i class="bi bi-bag text-3xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">
      @if($currentFilter === 'all')
        ยังไม่มีคำสั่งซื้อ
      @else
        ไม่พบคำสั่งซื้อที่ {{ $filters[$currentFilter]['label'] ?? '' }}
      @endif
    </h3>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">
      เลือกซื้อสินค้าดิจิทัลคุณภาพสูงจากช่างภาพมืออาชีพ
    </p>
    <a href="{{ route('products.index') }}"
       class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-md hover:shadow-lg transition-all">
      <i class="bi bi-shop"></i> เริ่มเลือกซื้อสินค้า
    </a>
  </div>
  @endif
</div>
@endsection
