@extends('layouts.photographer')

@section('title', 'คิวงาน — Bookings')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<style>
  /* ══════════════════════════════════════════════════════════════
     Bookings index — responsive layout primitives
     ══════════════════════════════════════════════════════════════ */

  /* ── Hero gradient panel ───────────────────────────────────────
     Padding scales down on mobile; the gradient + radial accent
     stay because they're free (no extra paint cost on phones). */
  .bk-hero {
    border-radius: 20px;
    background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 50%, #8b5cf6 100%);
    color: white;
    padding: 1.25rem;
    box-shadow: 0 16px 40px -12px rgba(99,102,241,0.4);
    position: relative;
    overflow: hidden;
  }
  @media (min-width: 640px) {
    .bk-hero { border-radius: 24px; padding: 1.5rem; }
  }
  .bk-hero::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 100% 0%, rgba(255,255,255,0.18), transparent 50%);
    pointer-events: none;
  }

  /* ── Stat tile — readable on a 320px-wide phone ─────────────── */
  .bk-stat {
    background: rgba(255,255,255,0.12);
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 12px;
    padding: 0.65rem 0.5rem;
    text-align: center;
    line-height: 1.1;
  }
  @media (min-width: 640px) {
    .bk-stat { padding: 0.75rem 1rem; }
  }

  /* ── Pending booking card ──────────────────────────────────── */
  .bk-pending-card {
    background: white;
    border: 1px solid rgb(252 211 77);
    border-left: 4px solid #f59e0b;
    border-radius: 14px;
    padding: 1rem;
  }
  @media (min-width: 640px) {
    .bk-pending-card { padding: 1rem 1.25rem; }
  }
  .dark .bk-pending-card { background: rgb(15 23 42); border-color: rgba(245,158,11,0.4); }

  /* ── FullCalendar responsive overrides ─────────────────────────
     Mobile: tighter title, smaller buttons, swipe-friendly events.
     Desktop: keep default sizing. */
  .fc-theme-standard .fc-scrollgrid {
    border-color: #e2e8f0;
  }
  .dark .fc-theme-standard .fc-scrollgrid { border-color: rgba(255,255,255,0.1); }
  .dark .fc-theme-standard td,
  .dark .fc-theme-standard th { border-color: rgba(255,255,255,0.06); }
  .dark .fc { color: rgb(226 232 240); }
  .dark .fc-day-today { background: rgba(99,102,241,0.10) !important; }

  .fc-event {
    cursor: pointer;
    padding: 2px 4px;
    font-size: 11px;
    border-radius: 4px;
  }
  .fc-event-title { font-weight: 600; }
  .fc-toolbar-title { font-size: 1.1rem !important; font-weight: 700; }
  @media (min-width: 640px) { .fc-toolbar-title { font-size: 1.25rem !important; } }

  /* Stack the calendar toolbar on small phones so the title doesn't
     squeeze the day/week/list switcher off-screen. */
  @media (max-width: 540px) {
    .fc .fc-toolbar.fc-header-toolbar {
      flex-direction: column;
      gap: 0.5rem;
      align-items: stretch;
    }
    .fc .fc-toolbar-chunk { display: flex; justify-content: center; flex-wrap: wrap; gap: 0.25rem; }
    .fc .fc-button { font-size: 0.78rem; padding: 0.3rem 0.55rem; }
    .fc-toolbar-title { font-size: 0.95rem !important; text-align: center; }
    .fc-event { font-size: 10px; padding: 1px 3px; }
    /* Tighter day-cell padding so 31 days fit without horizontal scroll */
    .fc .fc-daygrid-day-frame { min-height: 4em; }
  }

  .dark .fc-button-primary {
    background: rgba(99,102,241,0.2);
    border-color: rgba(99,102,241,0.4);
    color: #c7d2fe;
  }
  .dark .fc-button-primary:hover { background: rgba(99,102,241,0.4); }

  /* Legend dots — keep them inline-flex on every viewport */
  .bk-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    white-space: nowrap;
    font-size: 11px;
  }
  .bk-legend-dot {
    width: 0.6rem; height: 0.6rem;
    border-radius: 3px;
    flex-shrink: 0;
  }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-12 sm:pb-16 px-3 sm:px-0">

  {{-- ═══════════════════════════════════════════════════════
       HERO + Stats
       ═══════════════════════════════════════════════════════ --}}
  <div class="bk-hero mb-4 sm:mb-5">
    <div class="relative grid grid-cols-1 lg:grid-cols-12 gap-3 sm:gap-4 items-center">

      {{-- Left: title + tagline --}}
      <div class="lg:col-span-7">
        <div class="text-[10px] uppercase tracking-[0.25em] text-white/75 font-bold mb-1.5 flex items-center gap-1.5">
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
          คิวงาน · Bookings
        </div>
        <h1 class="text-xl sm:text-2xl md:text-3xl font-extrabold leading-tight tracking-tight mb-1">
          ตารางงานถ่ายภาพ
        </h1>
        <p class="text-[13px] sm:text-sm text-white/85 leading-relaxed">
          จัดการคิวงาน · LINE reminder อัตโนมัติ 4 ครั้งก่อนวันงาน
        </p>
      </div>

      {{-- Right: 4 stat tiles. 2×2 on mobile, 4×1 on lg+. --}}
      <div class="lg:col-span-5 grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-2.5">
        <div class="bk-stat">
          <div class="text-lg sm:text-xl font-extrabold">{{ $stats['pending'] }}</div>
          <div class="text-[9px] sm:text-[10px] uppercase tracking-wider text-white/80 font-bold mt-0.5">รอยืนยัน</div>
        </div>
        <div class="bk-stat">
          <div class="text-lg sm:text-xl font-extrabold">{{ $stats['upcoming'] }}</div>
          <div class="text-[9px] sm:text-[10px] uppercase tracking-wider text-white/80 font-bold mt-0.5">กำลังจะมา</div>
        </div>
        <div class="bk-stat">
          <div class="text-lg sm:text-xl font-extrabold">{{ $stats['this_month'] }}</div>
          <div class="text-[9px] sm:text-[10px] uppercase tracking-wider text-white/80 font-bold mt-0.5">เดือนนี้</div>
        </div>
        <div class="bk-stat">
          <div class="text-lg sm:text-xl font-extrabold">{{ $stats['total'] }}</div>
          <div class="text-[9px] sm:text-[10px] uppercase tracking-wider text-white/80 font-bold mt-0.5">รวม</div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══ Flash messages ═══ --}}
  @if(session('success'))
    <div class="mb-4 p-3 sm:p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 flex items-start gap-2">
      <i class="bi bi-check-circle-fill mt-0.5 shrink-0"></i><span class="text-[13px] sm:text-sm">{{ session('success') }}</span>
    </div>
  @endif
  @if(session('error'))
    <div class="mb-4 p-3 sm:p-3.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 flex items-start gap-2">
      <i class="bi bi-exclamation-circle-fill mt-0.5 shrink-0"></i><span class="text-[13px] sm:text-sm">{{ session('error') }}</span>
    </div>
  @endif

  {{-- ═══════════════════════════════════════════════════════
       PENDING BOOKINGS — needs photographer action
       ═══════════════════════════════════════════════════════ --}}
  @if($pending->count() > 0)
    <div class="mb-5">
      <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white mb-2.5 flex items-center gap-2">
        <i class="bi bi-bell-fill text-amber-500 animate-pulse"></i>
        ต้องตอบรับ <span class="text-amber-600 dark:text-amber-400">{{ $pending->count() }}</span> คิว
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @foreach($pending as $b)
          <div class="bk-pending-card">
            <div class="flex items-start justify-between gap-2 mb-2">
              <div class="min-w-0 flex-1">
                <div class="font-bold text-sm text-slate-900 dark:text-white truncate">{{ $b->title }}</div>
                <div class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2 flex-wrap">
                  <span class="inline-flex items-center gap-1"><i class="bi bi-person"></i> {{ $b->customer?->first_name ?? '?' }}</span>
                  @if($b->customer_phone)
                    <span class="inline-flex items-center gap-1"><i class="bi bi-telephone"></i> {{ $b->customer_phone }}</span>
                  @endif
                </div>
              </div>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300 shrink-0 whitespace-nowrap">
                <i class="bi bi-hourglass-split"></i> รอยืนยัน
              </span>
            </div>

            {{-- Booking details — each on its own line for scanability --}}
            <div class="text-[12px] sm:text-xs text-slate-600 dark:text-slate-300 space-y-1 mb-3">
              <div class="flex items-start gap-1.5">
                <i class="bi bi-calendar-event mt-0.5 shrink-0"></i>
                <span>{{ $b->scheduled_at->format('d/m/Y H:i') }} <span class="text-slate-400">({{ $b->duration_minutes }} นาที)</span></span>
              </div>
              @if($b->location)
                <div class="flex items-start gap-1.5">
                  <i class="bi bi-geo-alt mt-0.5 shrink-0"></i>
                  <span class="truncate">{{ Str::limit($b->location, 60) }}</span>
                </div>
              @endif
              @if($b->agreed_price)
                <div class="flex items-start gap-1.5">
                  <i class="bi bi-cash-coin mt-0.5 shrink-0 text-emerald-500"></i>
                  <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($b->agreed_price) }} ฿</span>
                </div>
              @endif
            </div>

            {{-- Actions — full-width 2-column grid on mobile (better thumb tap),
                 inline pills on tablet+ --}}
            <div class="grid grid-cols-2 sm:flex sm:items-center gap-2">
              <form action="{{ route('photographer.bookings.confirm', $b->id) }}" method="POST" class="contents sm:inline">
                @csrf
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 sm:py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-500 hover:bg-emerald-600 active:scale-95 transition shadow-sm">
                  <i class="bi bi-check-circle"></i> ยืนยัน
                </button>
              </form>
              <a href="{{ route('photographer.bookings.show', $b->id) }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 sm:py-1.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-white/10 transition no-underline">
                <i class="bi bi-eye"></i> ดูรายละเอียด
              </a>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  {{-- ═══════════════════════════════════════════════════════
       CALENDAR
       ═══════════════════════════════════════════════════════ --}}
  <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-3 sm:p-4 lg:p-5 shadow-sm">

    {{-- Header — title + legend; legend wraps on mobile --}}
    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
      <h2 class="font-bold text-[15px] sm:text-base text-slate-900 dark:text-white flex items-center gap-2">
        <i class="bi bi-calendar3 text-indigo-500"></i> ปฏิทินงาน
      </h2>
      <div class="flex items-center gap-x-2.5 gap-y-1 flex-wrap">
        <span class="bk-legend-item text-slate-600 dark:text-slate-400">
          <span class="bk-legend-dot" style="background:#f59e0b;"></span> รอยืนยัน
        </span>
        <span class="bk-legend-item text-slate-600 dark:text-slate-400">
          <span class="bk-legend-dot" style="background:#10b981;"></span> ยืนยันแล้ว
        </span>
        <span class="bk-legend-item text-slate-600 dark:text-slate-400">
          <span class="bk-legend-dot" style="background:#6366f1;"></span> เสร็จสิ้น
        </span>
        <span class="bk-legend-item text-slate-600 dark:text-slate-400">
          <span class="bk-legend-dot" style="background:#ef4444;"></span> ยกเลิก
        </span>
      </div>
    </div>

    {{-- The calendar element. Mobile defaults to listMonth via JS below
         (FullCalendar's dayGridMonth view is unusable below 380px). --}}
    <div id="bookingCalendar"></div>
  </div>

  {{-- ═══════════════════════════════════════════════════════
       UPCOMING list — compact cards, tap → detail page
       ═══════════════════════════════════════════════════════ --}}
  @if($upcoming->count() > 0)
    <div class="mt-4 sm:mt-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 overflow-hidden shadow-sm">
      <div class="px-4 py-3 border-b border-slate-100 dark:border-white/5">
        <h2 class="font-bold text-[14px] sm:text-sm text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-clock-history text-violet-500"></i> งานที่กำลังจะมาถึง
          <span class="ml-auto text-[11px] font-medium text-slate-400 dark:text-slate-500">
            {{ $upcoming->count() }} รายการ
          </span>
        </h2>
      </div>
      <div class="divide-y divide-slate-100 dark:divide-white/5">
        @foreach($upcoming as $b)
          <a href="{{ route('photographer.bookings.show', $b->id) }}"
             class="flex items-center gap-3 px-3 sm:px-4 py-3 hover:bg-slate-50 dark:hover:bg-white/[0.03] transition no-underline">
            {{-- Date pill --}}
            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl flex flex-col items-center justify-center text-white shrink-0"
                 style="background:linear-gradient(135deg,{{ $b->color }},{{ $b->color }}99);">
              <div class="text-[8px] sm:text-[9px] uppercase font-bold opacity-80">{{ $b->scheduled_at->format('M') }}</div>
              <div class="text-[15px] sm:text-base font-extrabold leading-none">{{ $b->scheduled_at->format('d') }}</div>
            </div>

            {{-- Body --}}
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-[13px] sm:text-sm text-slate-900 dark:text-white truncate">{{ $b->title }}</div>
              <div class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-x-2 gap-y-0.5 flex-wrap">
                <span class="inline-flex items-center gap-1"><i class="bi bi-clock"></i> {{ $b->scheduled_at->format('H:i') }}</span>
                <span class="inline-flex items-center gap-1"><i class="bi bi-person"></i> {{ $b->customer?->first_name ?? '?' }}</span>
                @if($b->location)
                  <span class="inline-flex items-center gap-1 truncate max-w-[140px] sm:max-w-none">
                    <i class="bi bi-geo-alt"></i> {{ Str::limit($b->location, 30) }}
                  </span>
                @endif
              </div>
            </div>

            {{-- Status pill — smaller on mobile to fit in the row --}}
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] sm:text-[10px] font-bold whitespace-nowrap shrink-0"
                  style="background:{{ $b->color }}25; color:{{ $b->color }};">
              {{ $b->status_label }}
            </span>
          </a>
        @endforeach
      </div>
    </div>
  @endif

</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const el = document.getElementById('bookingCalendar');
  if (!el) return;

  // ── Pick the best default view based on viewport width ─────────
  // Below 540px the dayGridMonth grid is too cramped — single-letter
  // weekday headers and one-line events that wrap make scanning the
  // month worthless. listMonth gives a vertical agenda view that
  // works on phones and matches what most booking apps default to.
  const isPhone = window.matchMedia('(max-width: 540px)').matches;

  const cal = new FullCalendar.Calendar(el, {
    initialView: isPhone ? 'listMonth' : 'dayGridMonth',

    // Fewer toolbar buttons on phones so the title isn't crushed.
    headerToolbar: isPhone
      ? {
          left:   'prev,next',
          center: 'title',
          right:  'today',
        }
      : {
          left:   'prev,next today',
          center: 'title',
          right:  'dayGridMonth,timeGridWeek,listMonth',
        },

    locale: 'th',
    buttonText: {
      today: 'วันนี้',
      month: 'เดือน',
      week:  'สัปดาห์',
      list:  'รายการ',
    },

    // `aspectRatio` instead of fixed height — calendar squares scale
    // proportionally to the container width on every breakpoint.
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

  // Re-evaluate the view on viewport changes (rotate device,
  // resize browser) so users don't get stuck on a layout that
  // doesn't match their current screen.
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
