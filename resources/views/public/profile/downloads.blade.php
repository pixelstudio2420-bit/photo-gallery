@extends('layouts.app')

@section('title', 'ประวัติดาวน์โหลด')

{{-- =======================================================================
     PROFILE · DOWNLOADS
     -------------------------------------------------------------------
     Grid of download token cards. Design matches the profile dashboard.
     Each card shows: event name, order #, usage progress, expiry,
     big download CTA (disabled state if expired / limit reached).
     ====================================================================== --}}
@section('content')
@php
  // Aggregate stats for the header
  $activeCount = $downloads->getCollection()->filter(function ($d) {
      $expired = $d->expires_at && $d->expires_at->isPast();
      $limit   = $d->max_downloads && $d->download_count >= $d->max_downloads;
      return !$expired && !$limit;
  })->count();
@endphp

<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  {{-- ───────────── Header ───────────── --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 text-white shadow-md">
          <i class="bi bi-download"></i>
        </span>
        ประวัติดาวน์โหลด
      </h1>
      @if($downloads->total() > 0)
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 ml-1">
          ทั้งหมด <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $downloads->total() }}</span> รายการ
          · ใช้งานได้ <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $activeCount }}</span>
        </p>
      @endif
    </div>
    <a href="{{ route('profile.orders') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-500/20 text-sm font-medium transition">
      <i class="bi bi-receipt"></i> ดูคำสั่งซื้อ
    </a>
  </div>

  {{-- ───────────── Tab Navigation ───────────── --}}
  <div class="mb-6 flex items-center gap-1 overflow-x-auto pb-1 border-b border-slate-200 dark:border-white/10">
    @foreach([
      ['route' => route('profile'),           'label' => 'ภาพรวม',      'icon' => 'bi-grid',     'active' => false],
      ['route' => route('profile.orders'),    'label' => 'คำสั่งซื้อ',   'icon' => 'bi-receipt',  'active' => false],
      ['route' => route('profile.downloads'), 'label' => 'ดาวน์โหลด', 'icon' => 'bi-download', 'active' => true],
      ['route' => route('profile.reviews'),   'label' => 'รีวิว',        'icon' => 'bi-star',     'active' => false],
      ['route' => route('wishlist.index'),    'label' => 'รายการโปรด','icon' => 'bi-heart',    'active' => false],
      ['route' => route('profile.referrals'), 'label' => 'แนะนำเพื่อน', 'icon' => 'bi-people-fill','active' => false],
    ] as $tab)
      <a href="{{ $tab['route'] }}"
         class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition
            {{ $tab['active']
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
        <i class="bi {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  {{-- ───────────── Downloads Grid ───────────── --}}
  @if($downloads->isEmpty())
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm text-center py-16 px-6">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-blue-100 to-cyan-100 dark:from-blue-500/20 dark:to-cyan-500/20 text-blue-500 dark:text-blue-400 mb-4">
        <i class="bi bi-cloud-download text-3xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">ยังไม่มีประวัติดาวน์โหลด</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">
        ลิงก์ดาวน์โหลดจะปรากฏที่นี่หลังจากชำระเงินสำเร็จ
      </p>
      <a href="{{ route('events.index') }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 text-white text-sm font-semibold shadow-md transition">
        <i class="bi bi-camera"></i> เริ่มเลือกซื้อรูปภาพ
      </a>
    </div>
  @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      @foreach($downloads as $dl)
        @php
          $isExpired   = $dl->expires_at && $dl->expires_at->isPast();
          $limitHit    = $dl->max_downloads && $dl->download_count >= $dl->max_downloads;
          $isActive    = !$isExpired && !$limitHit;
          $progress    = $dl->max_downloads ? min(100, round(($dl->download_count / $dl->max_downloads) * 100)) : 0;
          $expiringSoon = $isActive && $dl->expires_at && $dl->expires_at->diffInHours(now()) < 24;
        @endphp

        <div class="relative rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden
                    {{ $isActive ? 'hover:shadow-md hover:border-blue-300 dark:hover:border-blue-500/40' : 'opacity-70' }} transition">

          {{-- Top accent bar --}}
          <div class="h-1 {{ $isActive ? 'bg-gradient-to-r from-blue-500 to-cyan-500' : ($isExpired ? 'bg-rose-400' : 'bg-slate-400') }}"></div>

          <div class="p-4">
            {{-- Header: icon + event name --}}
            <div class="flex items-start gap-3 mb-3">
              <div class="w-11 h-11 rounded-xl flex items-center justify-center shadow-sm flex-shrink-0
                  {{ $isActive
                      ? 'bg-gradient-to-br from-blue-500 to-cyan-500 text-white'
                      : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400' }}">
                <i class="bi bi-image text-lg"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-sm text-slate-900 dark:text-white truncate">
                  {{ $dl->order->event->name ?? 'รูปภาพ' }}
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5 truncate">
                  <i class="bi bi-receipt mr-0.5"></i>
                  @if($dl->order)
                    <a href="{{ route('orders.show', $dl->order->id) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                      #{{ $dl->order->order_number ?? $dl->order_id }}
                    </a>
                  @else
                    #{{ $dl->order_id }}
                  @endif
                </div>
              </div>
              {{-- Status pill --}}
              @if($isActive && !$expiringSoon)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-[10px] font-semibold flex-shrink-0">
                  <span class="relative flex h-1.5 w-1.5">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-500 opacity-75 animate-ping"></span>
                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                  </span>
                  Active
                </span>
              @elseif($expiringSoon)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-[10px] font-semibold flex-shrink-0">
                  <i class="bi bi-exclamation-triangle-fill"></i> ใกล้หมด
                </span>
              @elseif($isExpired)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 text-[10px] font-semibold flex-shrink-0">
                  <i class="bi bi-clock-fill"></i> หมดอายุ
                </span>
              @else
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 text-[10px] font-semibold flex-shrink-0">
                  <i class="bi bi-check-all"></i> ครบแล้ว
                </span>
              @endif
            </div>

            {{-- Usage progress --}}
            @if($dl->max_downloads)
              <div class="mb-3">
                <div class="flex items-center justify-between text-[11px] mb-1">
                  <span class="text-slate-500 dark:text-slate-400">การใช้งาน</span>
                  <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $dl->download_count }}/{{ $dl->max_downloads }} ครั้ง</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-100 dark:bg-white/5 overflow-hidden">
                  <div class="h-full rounded-full transition-all
                        {{ $isActive ? 'bg-gradient-to-r from-blue-500 to-cyan-500' : ($isExpired ? 'bg-rose-400' : 'bg-slate-400') }}"
                       style="width: {{ $progress }}%;"></div>
                </div>
              </div>
            @else
              <div class="mb-3 text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                <i class="bi bi-infinity"></i>
                ดาวน์โหลดได้ไม่จำกัดครั้ง (ใช้ไปแล้ว {{ $dl->download_count }} ครั้ง)
              </div>
            @endif

            {{-- Expiry info --}}
            <div class="flex items-center gap-1.5 text-[11px] mb-3
                {{ $isExpired ? 'text-rose-600 dark:text-rose-400' : ($expiringSoon ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400') }}">
              <i class="bi {{ $dl->expires_at ? 'bi-clock-history' : 'bi-infinity' }}"></i>
              @if($dl->expires_at)
                {{ $isExpired ? 'หมดอายุเมื่อ' : 'หมดอายุ' }}
                <span class="font-medium">{{ $dl->expires_at->format('d/m/Y H:i') }}</span>
                @if(!$isExpired)
                  <span class="text-slate-400 dark:text-slate-500">· {{ $dl->expires_at->diffForHumans() }}</span>
                @endif
              @else
                ไม่มีวันหมดอายุ
              @endif
            </div>

            {{-- Action --}}
            @if($isActive)
              <a href="{{ route('download.show', $dl->token) }}"
                 class="inline-flex w-full items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 active:scale-[0.98] text-white text-sm font-semibold shadow-md transition">
                <i class="bi bi-download"></i> ดาวน์โหลดรูปภาพ
              </a>
            @else
              <button type="button" disabled
                      class="inline-flex w-full items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-slate-500 text-sm font-medium cursor-not-allowed">
                <i class="bi {{ $isExpired ? 'bi-clock-history' : 'bi-check-all' }}"></i>
                {{ $isExpired ? 'ลิงก์หมดอายุแล้ว' : 'ดาวน์โหลดครบจำนวน' }}
              </button>
            @endif
          </div>
        </div>
      @endforeach
    </div>

    {{-- Pagination --}}
    @if($downloads->hasPages())
      <div class="mt-6 flex justify-center">
        {{ $downloads->withQueryString()->links() }}
      </div>
    @endif
  @endif
</div>
@endsection
