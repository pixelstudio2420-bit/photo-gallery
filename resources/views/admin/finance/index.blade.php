@extends('layouts.admin')

@section('title', 'การเงิน')

{{-- =======================================================================
     FINANCE HUB — LIGHT/DARK DUAL-THEME REDESIGN
     -------------------------------------------------------------------
     • Data contract unchanged: $totalRevenue, $totalPlatformFees,
       $totalPayouts, $pendingPayouts.
     • Routes unchanged: admin.finance.{transactions|reports|refunds}.
     • Hero-style header + accent metric cards + navigation cards with
       gradient icon badges matching marketing hub pattern.
     ====================================================================== --}}

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ────────── PAGE HEADER ────────── --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h4 class="text-2xl font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-3 tracking-tight">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl shadow-md shadow-emerald-500/30"
              style="background:linear-gradient(135deg,#10b981,#14b8a6);">
          <i class="bi bi-graph-up-arrow text-white text-xl"></i>
        </span>
        การเงิน
      </h4>
      <p class="text-sm text-slate-500 dark:text-slate-400 ml-14">
        ภาพรวมรายรับ-รายจ่าย การจ่ายช่างภาพ และการขอคืนเงิน
      </p>
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════
       METRICS — 4 stat cards
       ══════════════════════════════════════════════════════════════ --}}
  @php
    $metricCards = [
      [
        'label' => 'รายรับทั้งหมด',
        'value' => $totalRevenue ?? 0,
        'icon'  => 'bi-cash-stack',
        'color' => 'indigo',
        'icon_bg' => 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-300',
        'border'  => 'border-t-indigo-500',
        'prefix' => '฿',
      ],
      [
        'label' => 'ค่าธรรมเนียมแพลตฟอร์ม',
        'value' => $totalPlatformFees ?? 0,
        'icon'  => 'bi-percent',
        'color' => 'emerald',
        'icon_bg' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300',
        'border'  => 'border-t-emerald-500',
        'prefix' => '฿',
      ],
      [
        'label' => 'ยอดจ่ายออกทั้งหมด',
        'value' => $totalPayouts ?? 0,
        'icon'  => 'bi-send-check',
        'color' => 'blue',
        'icon_bg' => 'bg-blue-500/15 text-blue-600 dark:text-blue-300',
        'border'  => 'border-t-blue-500',
        'prefix' => '฿',
      ],
      [
        'label' => 'รอจ่ายออก',
        'value' => $pendingPayouts ?? 0,
        'icon'  => 'bi-hourglass-split',
        'color' => 'amber',
        'icon_bg' => 'bg-amber-500/15 text-amber-600 dark:text-amber-300',
        'border'  => 'border-t-amber-500',
        'prefix' => '฿',
      ],
    ];
  @endphp

  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach($metricCards as $m)
      <div class="group relative overflow-hidden rounded-2xl
                  border border-slate-200 dark:border-white/10
                  bg-white dark:bg-slate-900
                  shadow-sm shadow-slate-900/5 dark:shadow-black/20
                  p-5 border-t-2 {{ $m['border'] }}
                  transition-all hover:-translate-y-0.5 hover:shadow-md">
        <div class="flex items-start justify-between mb-3">
          <div class="text-[11px] uppercase tracking-[0.14em] font-bold text-slate-500 dark:text-slate-400 leading-tight">
            {{ $m['label'] }}
          </div>
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl {{ $m['icon_bg'] }}">
            <i class="bi {{ $m['icon'] }} text-base"></i>
          </span>
        </div>
        <div class="text-[26px] font-bold text-slate-900 dark:text-white leading-none tracking-tight">
          <span class="text-base font-semibold text-slate-400 dark:text-slate-500 mr-0.5">{{ $m['prefix'] }}</span>{{ number_format($m['value'], 2) }}
        </div>
      </div>
    @endforeach
  </div>

  {{-- ══════════════════════════════════════════════════════════════
       NAVIGATION CARDS
       ══════════════════════════════════════════════════════════════ --}}
  @php
    $navCards = [
      [
        'url'         => route('admin.finance.transactions'),
        'icon'        => 'bi-receipt',
        'icon_bg'     => 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-300',
        'ring'        => 'hover:ring-indigo-500/30',
        'title'       => 'รายการธุรกรรม',
        'description' => 'ดูรายการธุรกรรมทั้งหมดในระบบ — filter ตามสถานะ / วิธีชำระ / วันที่',
      ],
      [
        'url'         => route('admin.finance.reports'),
        'icon'        => 'bi-file-earmark-bar-graph-fill',
        'icon_bg'     => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300',
        'ring'        => 'hover:ring-emerald-500/30',
        'title'       => 'รายงานการเงิน',
        'description' => 'สรุปรายรับ-รายจ่าย รายวัน/สัปดาห์/เดือน พร้อม export Excel',
      ],
      [
        'url'         => route('admin.finance.refunds'),
        'icon'        => 'bi-arrow-counterclockwise',
        'icon_bg'     => 'bg-amber-500/15 text-amber-600 dark:text-amber-300',
        'ring'        => 'hover:ring-amber-500/30',
        'title'       => 'คำขอคืนเงิน',
        'description' => 'จัดการคำขอคืนเงินจากลูกค้า — อนุมัติ / ปฏิเสธ / แจ้งเหตุผล',
      ],
    ];
  @endphp

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    @foreach($navCards as $n)
      <a href="{{ $n['url'] }}"
         class="group block rounded-2xl
                bg-white dark:bg-slate-900
                border border-slate-200 dark:border-white/10
                shadow-sm shadow-slate-900/5 dark:shadow-black/20
                p-6 transition-all
                hover:-translate-y-1 hover:shadow-lg hover:shadow-slate-900/10 dark:hover:shadow-black/40
                hover:ring-2 {{ $n['ring'] }}">

        <div class="flex items-start gap-4">
          <span class="inline-flex items-center justify-center w-14 h-14 rounded-2xl {{ $n['icon_bg'] }} shrink-0
                       group-hover:scale-110 transition-transform">
            <i class="bi {{ $n['icon'] }} text-2xl"></i>
          </span>
          <div class="flex-1 min-w-0">
            <h6 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
              {{ $n['title'] }}
              <i class="bi bi-arrow-right text-slate-400 dark:text-slate-500 text-sm
                        transition-transform group-hover:translate-x-1 group-hover:text-slate-900 dark:group-hover:text-white"></i>
            </h6>
            <p class="text-[13px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
              {{ $n['description'] }}
            </p>
          </div>
        </div>
      </a>
    @endforeach
  </div>
</div>
@endsection
