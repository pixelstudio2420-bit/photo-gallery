@extends('layouts.photographer')

@section('title', 'คิวงาน — Bookings')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<style>
  /* ── FullCalendar themed to match the rest of the redesigned admin/
     photographer pages. Same blue/violet language as everything else. */
  .fc-theme-standard .fc-scrollgrid { border-color: rgb(226 232 240); border-radius: 12px; overflow: hidden; }
  .dark .fc-theme-standard .fc-scrollgrid { border-color: rgba(255,255,255,0.08); }
  .dark .fc-theme-standard td,
  .dark .fc-theme-standard th { border-color: rgba(255,255,255,0.05); }
  .dark .fc { color: rgb(226 232 240); }
  .dark .fc-day-today { background: rgba(99,102,241,0.10) !important; }

  .fc-event {
    cursor: pointer;
    padding: 2px 5px;
    font-size: 11px;
    border-radius: 6px;
    border: none !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
  }
  .fc-event:hover { transform: translateY(-1px); transition: transform 120ms; }
  .fc-event-title { font-weight: 600; }
  .fc-toolbar-title { font-size: 1.1rem !important; font-weight: 700; }
  @media (min-width: 640px) { .fc-toolbar-title { font-size: 1.25rem !important; } }

  /* Stack toolbar on small phones */
  @media (max-width: 540px) {
    .fc .fc-toolbar.fc-header-toolbar {
      flex-direction: column;
      gap: 0.5rem;
      align-items: stretch;
    }
    .fc .fc-toolbar-chunk { display: flex; justify-content: center; flex-wrap: wrap; gap: 0.25rem; }
    .fc .fc-button { font-size: 0.78rem; padding: 0.3rem 0.55rem; }
    .fc-toolbar-title { font-size: 0.95rem !important; text-align: center; }
    .fc-event { font-size: 10px; }
    .fc .fc-daygrid-day-frame { min-height: 4em; }
  }

  /* Theme calendar buttons */
  .fc-button-primary {
    background: white !important;
    border: 1px solid rgb(226 232 240) !important;
    color: rgb(71 85 105) !important;
    font-weight: 500 !important;
    transition: all 0.15s !important;
  }
  .fc-button-primary:hover { background: rgb(248 250 252) !important; border-color: rgb(99 102 241) !important; color: rgb(99 102 241) !important; }
  .fc-button-primary:not(:disabled).fc-button-active,
  .fc-button-primary:not(:disabled):active {
    background: linear-gradient(135deg, rgb(59 130 246), rgb(139 92 246)) !important;
    border-color: transparent !important;
    color: white !important;
    box-shadow: 0 2px 4px rgba(99,102,241,0.3) !important;
  }
  .dark .fc-button-primary {
    background: rgba(99,102,241,0.12) !important;
    border-color: rgba(99,102,241,0.25) !important;
    color: rgb(199 210 254) !important;
  }
</style>
@endpush

@php
  // Pre-bake the full Tailwind class strings — JIT can't construct
  // class names from variables (`bg-{$color}-500` won't compile).
  $statusMeta = [
    'pending'   => [
      'label'     => 'รอยืนยัน',
      'icon'      => 'bi-hourglass-split',
      'pill'      => 'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300',
      'pillSolid' => 'bg-amber-500 text-white',
      'gradient'  => 'bg-gradient-to-br from-amber-500 to-amber-600',
      'dot'       => 'bg-amber-500',
    ],
    'confirmed' => [
      'label'     => 'ยืนยันแล้ว',
      'icon'      => 'bi-check-circle-fill',
      'pill'      => 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300',
      'pillSolid' => 'bg-emerald-500 text-white',
      'gradient'  => 'bg-gradient-to-br from-emerald-500 to-emerald-600',
      'dot'       => 'bg-emerald-500',
    ],
    'completed' => [
      'label'     => 'เสร็จสิ้น',
      'icon'      => 'bi-flag-fill',
      'pill'      => 'bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300',
      'pillSolid' => 'bg-indigo-500 text-white',
      'gradient'  => 'bg-gradient-to-br from-indigo-500 to-indigo-600',
      'dot'       => 'bg-indigo-500',
    ],
    'cancelled' => [
      'label'     => 'ยกเลิก',
      'icon'      => 'bi-x-circle-fill',
      'pill'      => 'bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300',
      'pillSolid' => 'bg-rose-500 text-white',
      'gradient'  => 'bg-gradient-to-br from-rose-500 to-rose-600',
      'dot'       => 'bg-rose-500',
    ],
    'no_show'   => [
      'label'     => 'ไม่มาตามนัด',
      'icon'      => 'bi-dash-circle-fill',
      'pill'      => 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400',
      'pillSolid' => 'bg-slate-500 text-white',
      'gradient'  => 'bg-gradient-to-br from-slate-500 to-slate-600',
      'dot'       => 'bg-slate-500',
    ],
  ];

  // Filter pill class strings (ทั้งหมด has its own slate variant)
  $filterPillClasses = [
    'all'       => ['active' => 'bg-slate-700 text-white shadow-sm',         'inactive' => 'bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10'],
    'pending'   => ['active' => 'bg-amber-500 text-white shadow-sm',         'inactive' => 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 hover:bg-amber-200 dark:hover:bg-amber-500/25'],
    'confirmed' => ['active' => 'bg-emerald-500 text-white shadow-sm',       'inactive' => 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-500/25'],
    'completed' => ['active' => 'bg-indigo-500 text-white shadow-sm',        'inactive' => 'bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 dark:hover:bg-indigo-500/25'],
    'cancelled' => ['active' => 'bg-rose-500 text-white shadow-sm',          'inactive' => 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 hover:bg-rose-200 dark:hover:bg-rose-500/25'],
  ];
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-5">

  {{-- ════════════════════════════════════════════════════════
       HEADER — gradient avatar + title + actions
       ════════════════════════════════════════════════════════ --}}
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-500 to-violet-600 text-white flex items-center justify-center shadow-md shadow-blue-500/30">
        <i class="bi bi-calendar3 text-xl"></i>
      </div>
      <div>
        <h1 class="text-xl md:text-2xl font-bold text-slate-900 dark:text-white tracking-tight leading-tight">คิวงานถ่ายภาพ</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          จัดการการจอง · LINE reminder อัตโนมัติ 4 ครั้งก่อนวันงาน
        </p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <button type="button" onclick="window.print()" title="พิมพ์ตารางงาน"
              class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/10 px-3.5 py-2 text-sm font-medium transition">
        <i class="bi bi-printer"></i>
        <span class="hidden sm:inline">พิมพ์</span>
      </button>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
       FLASH MESSAGES
       ════════════════════════════════════════════════════════ --}}
  @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm flex items-start gap-2">
      <i class="bi bi-check-circle-fill mt-0.5 shrink-0"></i><span>{{ session('success') }}</span>
    </div>
  @endif
  @if(session('error'))
    <div class="rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 px-4 py-3 text-sm flex items-start gap-2">
      <i class="bi bi-exclamation-triangle-fill mt-0.5 shrink-0"></i><span>{{ session('error') }}</span>
    </div>
  @endif

  {{-- ════════════════════════════════════════════════════════
       KPI TILES — 5 cards
       ════════════════════════════════════════════════════════ --}}
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
    {{-- Today --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm relative overflow-hidden
                {{ $stats['today_count'] > 0 ? 'ring-2 ring-blue-200 dark:ring-blue-500/30' : '' }}">
      @if($stats['today_count'] > 0)
        <span class="absolute top-2 right-2 flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
        </span>
      @endif
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-blue-500/30">
          <i class="bi bi-stars text-xl"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-slate-900 dark:text-white tabular-nums">{{ $stats['today_count'] }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">งานวันนี้</div>
        </div>
      </div>
    </div>
    {{-- Pending --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm relative overflow-hidden
                {{ $stats['pending'] > 0 ? 'ring-2 ring-amber-200 dark:ring-amber-500/30' : '' }}">
      @if($stats['pending'] > 0)
        <span class="absolute top-2 right-2 flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
        </span>
      @endif
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-bell-fill text-xl"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-amber-600 dark:text-amber-400 tabular-nums">{{ $stats['pending'] }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">รอยืนยัน</div>
        </div>
      </div>
    </div>
    {{-- Upcoming --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-clock-history text-xl"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-emerald-600 dark:text-emerald-400 tabular-nums">{{ $stats['upcoming'] }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">กำลังจะมา</div>
        </div>
      </div>
    </div>
    {{-- This month --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-calendar-month text-xl"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-violet-600 dark:text-violet-400 tabular-nums">{{ $stats['this_month'] }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">เดือนนี้</div>
        </div>
      </div>
    </div>
    {{-- Revenue --}}
    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white rounded-2xl shadow-md shadow-emerald-500/30 col-span-2 md:col-span-1">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center flex-shrink-0">
          <i class="bi bi-currency-exchange text-xl"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none tabular-nums">฿{{ number_format($stats['revenue_this_month'], 0) }}</div>
          <div class="text-[11px] opacity-90 mt-1">รายได้เดือนนี้</div>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
       TODAY'S BOOKINGS — high-priority surface
       ════════════════════════════════════════════════════════ --}}
  @if($today->count() > 0)
    <div class="rounded-2xl bg-gradient-to-br from-blue-500 via-violet-600 to-purple-600 text-white shadow-xl shadow-blue-500/40 p-5 relative overflow-hidden">
      <div class="absolute -top-12 -right-12 w-48 h-48 rounded-full bg-white/10 blur-2xl"></div>
      <div class="relative">
        <div class="flex items-center gap-2 mb-3">
          <i class="bi bi-stars text-xl"></i>
          <h2 class="font-bold text-lg">วันนี้ มี {{ $today->count() }} งาน</h2>
          <span class="ml-auto text-xs opacity-80">{{ now()->format('d M Y') }}</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          @foreach($today as $b)
            @php $sm = $statusMeta[$b->status] ?? $statusMeta['confirmed']; @endphp
            <a href="{{ route('photographer.bookings.show', $b->id) }}"
               class="group bg-white/10 backdrop-blur rounded-xl border border-white/20 p-3 hover:bg-white/20 transition no-underline">
              <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-lg bg-white text-blue-700 flex flex-col items-center justify-center shrink-0 shadow-md">
                  <div class="text-[9px] uppercase font-bold opacity-70 leading-none">{{ $b->scheduled_at->format('H:i') }}</div>
                  <div class="text-[15px] font-bold leading-none mt-0.5">⏰</div>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-bold text-sm truncate">{{ $b->title }}</div>
                  <div class="text-[11px] opacity-85 mt-0.5 flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center gap-1"><i class="bi bi-person"></i>{{ $b->customer?->first_name ?? '?' }}</span>
                    @if($b->location)
                      <span class="inline-flex items-center gap-1 truncate max-w-[140px]"><i class="bi bi-geo-alt"></i>{{ \Illuminate\Support\Str::limit($b->location, 30) }}</span>
                    @endif
                  </div>
                  @if($b->agreed_price)
                    <div class="text-[11px] opacity-90 mt-1 inline-flex items-center gap-1">
                      <i class="bi bi-cash-coin"></i> ฿{{ number_format($b->agreed_price) }}
                    </div>
                  @endif
                </div>
                <i class="bi bi-arrow-right opacity-60 group-hover:translate-x-1 transition"></i>
              </div>
            </a>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- ════════════════════════════════════════════════════════
       PENDING BOOKINGS — needs decision
       ════════════════════════════════════════════════════════ --}}
  @if($pending->count() > 0)
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
          <i class="bi bi-bell-fill animate-pulse"></i>
        </div>
        <div>
          <h2 class="font-semibold text-slate-900 dark:text-white text-sm">ต้องตอบรับ {{ $pending->count() }} คิว</h2>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">ตอบเร็วช่วยปิดดีลไว — เกิน 24 ชม.ลูกค้ามักไปจองคนอื่น</p>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-px bg-slate-100 dark:bg-white/5">
        @foreach($pending as $b)
          <div class="bg-white dark:bg-slate-800 p-4 border-l-4 border-amber-500">
            <div class="flex items-start justify-between gap-2 mb-2.5">
              <div class="min-w-0 flex-1">
                <a href="{{ route('photographer.bookings.show', $b->id) }}" class="font-bold text-sm text-slate-900 dark:text-white truncate hover:text-blue-600 dark:hover:text-blue-400 transition">{{ $b->title }}</a>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2 flex-wrap">
                  <span class="inline-flex items-center gap-1"><i class="bi bi-person"></i>{{ $b->customer?->first_name ?? '?' }}</span>
                  @if($b->customer_phone)
                    <a href="tel:{{ $b->customer_phone }}" class="inline-flex items-center gap-1 hover:text-blue-600 transition"><i class="bi bi-telephone"></i>{{ $b->customer_phone }}</a>
                  @endif
                </div>
              </div>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300 shrink-0 whitespace-nowrap">
                <i class="bi bi-hourglass-split"></i>รอตอบ
              </span>
            </div>

            <div class="text-[12px] text-slate-600 dark:text-slate-300 space-y-1 mb-3 bg-slate-50 dark:bg-white/[0.02] rounded-lg p-2">
              <div class="flex items-start gap-1.5">
                <i class="bi bi-calendar-event mt-0.5 shrink-0 text-blue-500"></i>
                <span><strong>{{ $b->scheduled_at->format('d/m/Y H:i') }}</strong> <span class="text-slate-400">({{ $b->duration_minutes }} นาที)</span></span>
              </div>
              @if($b->location)
                <div class="flex items-start gap-1.5">
                  <i class="bi bi-geo-alt mt-0.5 shrink-0 text-emerald-500"></i>
                  <span class="truncate">{{ \Illuminate\Support\Str::limit($b->location, 60) }}</span>
                </div>
              @endif
              @if($b->agreed_price)
                <div class="flex items-start gap-1.5">
                  <i class="bi bi-cash-coin mt-0.5 shrink-0 text-emerald-500"></i>
                  <span class="font-bold text-emerald-600 dark:text-emerald-400">฿{{ number_format($b->agreed_price) }}</span>
                  @if($b->deposit_paid > 0)
                    <span class="text-emerald-500 text-[11px]">· มัดจำ ฿{{ number_format($b->deposit_paid) }}</span>
                  @endif
                </div>
              @endif
            </div>

            <div class="grid grid-cols-2 sm:flex sm:items-center gap-2">
              <form action="{{ route('photographer.bookings.confirm', $b->id) }}" method="POST" class="contents sm:inline">
                @csrf
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 shadow-md shadow-emerald-500/30 active:scale-95 transition">
                  <i class="bi bi-check-circle-fill"></i> ยืนยัน
                </button>
              </form>
              <a href="{{ route('photographer.bookings.show', $b->id) }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10 transition no-underline">
                <i class="bi bi-eye"></i> ดูรายละเอียด
              </a>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  {{-- ════════════════════════════════════════════════════════
       CALENDAR
       ════════════════════════════════════════════════════════ --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center justify-between flex-wrap gap-3">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
          <i class="bi bi-calendar3"></i>
        </div>
        <div>
          <h2 class="font-semibold text-slate-900 dark:text-white text-sm">ปฏิทินงาน</h2>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">คลิกที่งานเพื่อดูรายละเอียด</p>
        </div>
      </div>
      {{-- Status legend --}}
      <div class="flex items-center gap-2 flex-wrap">
        @foreach(['pending', 'confirmed', 'completed', 'cancelled'] as $key)
          @php $sm = $statusMeta[$key]; @endphp
          <span class="inline-flex items-center gap-1 text-[11px] text-slate-600 dark:text-slate-400">
            <span class="w-2.5 h-2.5 rounded-full {{ $sm['dot'] }}"></span>
            {{ $sm['label'] }}
          </span>
        @endforeach
      </div>
    </div>
    <div class="p-3 sm:p-4 lg:p-5">
      <div id="bookingCalendar"></div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
       UPCOMING LIST — with search + status filter
       ════════════════════════════════════════════════════════ --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center justify-between gap-3 flex-wrap">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center">
          <i class="bi bi-list-ul"></i>
        </div>
        <div>
          <h2 class="font-semibold text-slate-900 dark:text-white text-sm">งานที่กำลังจะมาถึง</h2>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $upcoming->count() }} รายการ</p>
        </div>
      </div>
    </div>

    {{-- Search + filter row --}}
    <form method="GET" class="px-5 py-3 border-b border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.02]">
      <div class="flex items-center gap-2 flex-wrap">
        <div class="relative flex-1 min-w-[200px]">
          <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 pointer-events-none"></i>
          <input type="text" name="q" value="{{ $search }}"
                 placeholder="ค้นหาชื่องาน / ลูกค้า / สถานที่..."
                 class="w-full pl-10 pr-3 py-2 rounded-xl text-sm
                        bg-white dark:bg-slate-900
                        border border-slate-200 dark:border-white/10
                        focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none transition">
        </div>
        <button type="submit" title="ค้นหา"
                class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-white bg-blue-500 hover:bg-blue-600 shadow-sm transition">
          <i class="bi bi-search"></i>
        </button>
        @if($search !== '' || $statusFilter !== 'all')
          <a href="{{ route('photographer.bookings') }}" title="ล้างตัวกรอง"
             class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-600 hover:bg-slate-200 transition">
            <i class="bi bi-x-lg"></i>
          </a>
        @endif
      </div>
      {{-- Status filter pills (pre-baked classes for Tailwind JIT) --}}
      <div class="mt-3 flex items-center gap-1.5 flex-wrap">
        @foreach([
          'all'       => 'ทั้งหมด',
          'pending'   => 'รอยืนยัน',
          'confirmed' => 'ยืนยันแล้ว',
          'completed' => 'เสร็จสิ้น',
          'cancelled' => 'ยกเลิก',
        ] as $key => $label)
          @php
            $isActive = $statusFilter === $key;
            $cls = $filterPillClasses[$key][$isActive ? 'active' : 'inactive'];
          @endphp
          <a href="{{ request()->fullUrlWithQuery(['status' => $key]) }}"
             class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition {{ $cls }}">
            {{ $label }}
          </a>
        @endforeach
      </div>
    </form>

    {{-- List --}}
    @if($upcoming->count() > 0)
    <div class="divide-y divide-slate-100 dark:divide-white/5">
      @foreach($upcoming as $b)
        @php $sm = $statusMeta[$b->status] ?? $statusMeta['confirmed']; @endphp
        <a href="{{ route('photographer.bookings.show', $b->id) }}"
           class="flex items-center gap-3 px-4 sm:px-5 py-3 hover:bg-slate-50 dark:hover:bg-white/[0.03] transition no-underline group">
          {{-- Date pill — themed by status color (pre-baked class) --}}
          <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex flex-col items-center justify-center text-white shrink-0 shadow-md {{ $sm['gradient'] }}">
            <div class="text-[8px] sm:text-[9px] uppercase font-bold opacity-90 leading-none">{{ $b->scheduled_at->format('M') }}</div>
            <div class="text-[16px] sm:text-lg font-extrabold leading-none mt-0.5">{{ $b->scheduled_at->format('d') }}</div>
            <div class="text-[8px] opacity-80 mt-0.5">{{ $b->scheduled_at->format('H:i') }}</div>
          </div>

          {{-- Body --}}
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-sm text-slate-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">{{ $b->title }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-x-2 gap-y-0.5 flex-wrap">
              <span class="inline-flex items-center gap-1"><i class="bi bi-person"></i>{{ $b->customer?->first_name ?? '?' }}</span>
              @if($b->customer_phone)
                <span class="text-slate-300">·</span>
                <span class="inline-flex items-center gap-1"><i class="bi bi-telephone"></i>{{ $b->customer_phone }}</span>
              @endif
              @if($b->location)
                <span class="text-slate-300">·</span>
                <span class="inline-flex items-center gap-1 truncate max-w-[160px]">
                  <i class="bi bi-geo-alt"></i>{{ \Illuminate\Support\Str::limit($b->location, 30) }}
                </span>
              @endif
            </div>
            @if($b->agreed_price)
            <div class="mt-1 text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold inline-flex items-center gap-1">
              <i class="bi bi-cash-coin"></i> ฿{{ number_format($b->agreed_price) }}
              @if($b->deposit_paid > 0)
                <span class="text-slate-400 dark:text-slate-500 font-normal">· มัดจำ ฿{{ number_format($b->deposit_paid) }}</span>
              @endif
            </div>
            @endif
          </div>

          {{-- Status pill (pre-baked class) --}}
          <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold whitespace-nowrap shrink-0 {{ $sm['pill'] }}">
            <i class="bi {{ $sm['icon'] }}"></i>{{ $sm['label'] }}
          </span>
        </a>
      @endforeach
    </div>
    @else
    {{-- Empty state for the list --}}
    <div class="py-12 text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-3xl bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-700 dark:to-slate-800 text-slate-500 dark:text-slate-400 mb-3">
        <i class="bi bi-calendar-x text-2xl"></i>
      </div>
      @if($search !== '' || $statusFilter !== 'all')
        <h3 class="font-bold text-slate-900 dark:text-white">ไม่เจอตามเงื่อนไข</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 mb-4">ลองเปลี่ยนคำค้นหรือล้างตัวกรอง</p>
        <a href="{{ route('photographer.bookings') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10 px-4 py-2 text-sm font-medium transition">
          <i class="bi bi-arrow-counterclockwise"></i> ล้างตัวกรอง
        </a>
      @else
        <h3 class="font-bold text-slate-900 dark:text-white">ยังไม่มีงานที่กำลังจะมา</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">เมื่อมีลูกค้าจองคิว จะแสดงที่นี่</p>
      @endif
    </div>
    @endif
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const el = document.getElementById('bookingCalendar');
  if (!el) return;

  const isPhone = window.matchMedia('(max-width: 540px)').matches;

  const cal = new FullCalendar.Calendar(el, {
    initialView: isPhone ? 'listMonth' : 'dayGridMonth',

    headerToolbar: isPhone
      ? { left: 'prev,next', center: 'title', right: 'today' }
      : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },

    locale: 'th',
    buttonText: { today: 'วันนี้', month: 'เดือน', week: 'สัปดาห์', list: 'รายการ' },

    aspectRatio: isPhone ? 1.0 : 1.5,
    height: 'auto',

    events: {
      url: '{{ route('photographer.bookings.feed') }}',
      method: 'GET',
      failure: () => alert('โหลดข้อมูลปฏิทินไม่สำเร็จ'),
    },

    eventClick: function (info) {
      info.jsEvent.preventDefault();
      if (info.event.url) window.location.href = info.event.url;
    },

    eventDidMount: function (info) {
      const props = info.event.extendedProps || {};
      const tip = [
        props.status_label || '',
        props.customer_name ? '👤 ' + props.customer_name : '',
        props.location ? '📍 ' + props.location : '',
        props.price ? '💰 ' + Number(props.price).toLocaleString() + ' ฿' : '',
      ].filter(Boolean).join('  ·  ');
      info.el.title = tip;
    },
  });
  cal.render();

  // Re-evaluate view on viewport change
  let lastIsPhone = isPhone;
  window.addEventListener('resize', () => {
    const nowIsPhone = window.matchMedia('(max-width: 540px)').matches;
    if (nowIsPhone !== lastIsPhone) {
      lastIsPhone = nowIsPhone;
      cal.changeView(nowIsPhone ? 'listMonth' : 'dayGridMonth');
      cal.setOption('aspectRatio', nowIsPhone ? 1.0 : 1.5);
    }
  });
});
</script>
@endsection
