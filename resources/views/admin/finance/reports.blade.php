@extends('layouts.admin')

@section('title', 'รายงานการเงิน')

@push('styles')
<style>
  .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(16,185,129,.14) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(20,184,166,.10) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(6,182,212,.12) 0px, transparent 50%);
  }
  .dark .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(16,185,129,.20) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(20,184,166,.16) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(6,182,212,.18) 0px, transparent 50%);
  }
  @media print {
    .no-print { display: none !important; }
    body { font-size: 12px; background: white !important; }
    .bar-chart-wrap { break-inside: avoid; }
    .gradient-mesh { background: none !important; }
  }
</style>
@endpush

@section('content')
<div class="max-w-[1400px] mx-auto pb-10 space-y-5" id="admin-table-area">

  {{-- ── HERO HEADER ────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-emerald-100 dark:border-emerald-500/20 gradient-mesh">
    <div class="relative p-6 md:p-7">
      <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-3 no-print">
        <a href="{{ route('admin.finance.index') }}" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">การเงิน</a>
        <i class="bi bi-chevron-right text-[0.6rem] opacity-50"></i>
        <span class="text-slate-700 dark:text-slate-200 font-medium">รายงานการเงิน</span>
      </nav>

      <div class="flex items-start justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-500 to-cyan-500 text-white flex items-center justify-center shadow-lg shrink-0">
            <i class="bi bi-file-earmark-bar-graph-fill text-2xl"></i>
          </div>
          <div class="min-w-0 flex-1">
            <h1 class="text-2xl md:text-[1.65rem] font-bold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">รายงานการเงิน</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
              สรุปรายรับ ออเดอร์ ค่าธรรมเนียม และรายได้ช่างภาพในช่วงเวลาที่เลือก
            </p>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                <i class="bi bi-calendar-range"></i> {{ $from }} → {{ $to }}
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300">
                <i class="bi bi-clock-history"></i>
                {{ $period === 'daily' ? 'รายวัน' : ($period === 'weekly' ? 'รายสัปดาห์' : 'รายเดือน') }}
              </span>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-2 no-print shrink-0">
          <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/[0.08] text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-sm font-medium transition">
            <i class="bi bi-printer"></i> พิมพ์
          </button>
          <a href="{{ route('admin.finance.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/[0.08] text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-sm font-medium transition">
            <i class="bi bi-arrow-left"></i> กลับ
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══ Date Range Filter ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm p-4 no-print" x-data="adminFilter()">
    <form method="GET" action="{{ route('admin.finance.reports') }}">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

        {{-- Date From --}}
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">จากวันที่</label>
          <input type="date" name="from" value="{{ $from }}"
              class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none transition">
        </div>

        {{-- Date To --}}
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">ถึงวันที่</label>
          <input type="date" name="to" value="{{ $to }}"
              class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none transition">
        </div>

        {{-- Period --}}
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">ช่วงเวลา</label>
          <select name="period"
              class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none transition">
            <option value="daily"   {{ $period === 'daily'   ? 'selected' : '' }}>รายวัน</option>
            <option value="weekly"  {{ $period === 'weekly'  ? 'selected' : '' }}>รายสัปดาห์</option>
            <option value="monthly" {{ $period === 'monthly' ? 'selected' : '' }}>รายเดือน</option>
          </select>
        </div>

        {{-- Actions --}}
        <div class="flex items-end gap-2">
          <div x-show="loading" x-cloak class="w-5 h-5 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
          <button type="button" @click="clearFilters()"
              class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-700/60 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-medium transition">
            <i class="bi bi-x-lg"></i> ล้าง
          </button>
        </div>

      </div>
    </form>
  </div>

  {{-- ═══ Summary Stats Cards ═══ --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
          <i class="bi bi-cash-stack text-lg"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">รายรับรวม</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">฿{{ number_format($totalRevenue, 2) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">total revenue</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-10 h-10 rounded-xl bg-teal-100 dark:bg-teal-500/15 text-teal-600 dark:text-teal-400 flex items-center justify-center">
          <i class="bi bi-cart-check text-lg"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">เฉลี่ย/ออเดอร์</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">฿{{ number_format($avgOrderValue, 2) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">avg order value</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-10 h-10 rounded-xl bg-cyan-100 dark:bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
          <i class="bi bi-receipt text-lg"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">จำนวนออเดอร์</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($totalOrders) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">total orders</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
          <i class="bi bi-percent text-lg"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ค่าธรรมเนียม</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">฿{{ number_format($totalCommission, 2) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">platform commission</div>
    </div>
  </div>

  {{-- ═══ Bar Chart: Revenue by Period ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden bar-chart-wrap">
    <div class="bg-gradient-to-r from-emerald-50/60 to-transparent dark:from-emerald-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06]">
      <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center shadow-md">
          <i class="bi bi-bar-chart-fill"></i>
        </div>
        รายรับตาม{{ $period === 'daily' ? 'วัน' : ($period === 'weekly' ? 'สัปดาห์' : 'เดือน') }}
      </h3>
    </div>
    <div class="p-5">
      @if($revenueByPeriod->count() > 0)
        <div class="flex flex-col gap-2.5">
          @foreach($revenueByPeriod as $row)
            @php $pct = $maxRevenue > 0 ? min(100, ($row->revenue / $maxRevenue) * 100) : 0; @endphp
            <div class="flex items-center gap-3">
              <div class="text-xs text-slate-600 dark:text-slate-400 font-medium" style="min-width:90px;text-align:right;">{{ $row->period_label }}</div>
              <div class="grow relative h-7 bg-slate-100 dark:bg-slate-900/40 rounded-lg overflow-hidden">
                <div class="h-full rounded-lg bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 transition-all duration-300" style="width:{{ number_format($pct,2) }}%;">
                  @if($pct > 12)
                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs font-semibold whitespace-nowrap">
                      ฿{{ number_format($row->revenue, 0) }}
                    </span>
                  @endif
                </div>
              </div>
              <div class="text-xs text-slate-600 dark:text-slate-400 font-medium" style="min-width:80px;">{{ number_format($row->orders_count) }} ออเดอร์</div>
            </div>
          @endforeach
        </div>
      @else
        <div class="py-8 text-center text-slate-500 dark:text-slate-400">
          <i class="bi bi-bar-chart text-4xl block mb-2 opacity-50"></i>
          <p class="text-sm mt-2 mb-0">ไม่มีข้อมูลในช่วงวันที่ที่เลือก</p>
        </div>
      @endif
    </div>
  </div>

  {{-- ═══ Two-column row: Payment Method + Payout Summary ═══ --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    {{-- Revenue by Payment Method --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
      <div class="bg-gradient-to-r from-emerald-50/60 to-transparent dark:from-emerald-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06]">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center shadow-md">
            <i class="bi bi-credit-card-fill"></i>
          </div>
          รายรับตามช่องทางชำระเงิน
        </h3>
      </div>
      <div>
        @if($revenueByMethod->count() > 0)
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50/80 dark:bg-slate-900/40 text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">
                <tr>
                  <th class="text-left px-4 py-3 font-semibold">ช่องทาง</th>
                  <th class="text-center px-4 py-3 font-semibold">ออเดอร์</th>
                  <th class="text-right px-4 py-3 font-semibold">รายรับ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200/60 dark:divide-white/[0.06]">
                @php $grandRevMethod = $revenueByMethod->sum('revenue'); @endphp
                @foreach($revenueByMethod as $row)
                  <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <td class="px-4 py-3">
                      <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                        {{ $row->payment_gateway ?: 'อื่นๆ' }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-center text-slate-700 dark:text-slate-300 tabular-nums">{{ number_format($row->orders_count) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">
                      <div class="font-semibold text-slate-800 dark:text-slate-100">฿{{ number_format($row->revenue, 2) }}</div>
                      @if($grandRevMethod > 0)
                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ number_format(($row->revenue/$grandRevMethod)*100,1) }}%</div>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
              <tfoot class="bg-slate-50/80 dark:bg-slate-900/40">
                <tr>
                  <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-100">รวม</td>
                  <td class="px-4 py-3 font-bold text-center text-slate-800 dark:text-slate-100 tabular-nums">{{ number_format($revenueByMethod->sum('orders_count')) }}</td>
                  <td class="px-4 py-3 font-bold text-right tabular-nums text-emerald-600 dark:text-emerald-400">฿{{ number_format($grandRevMethod, 2) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        @else
          <div class="p-8 text-center text-slate-500 dark:text-slate-400">
            <i class="bi bi-inbox text-3xl block mb-2 opacity-50"></i>
            <span class="text-sm">ไม่มีข้อมูล</span>
          </div>
        @endif
      </div>
    </div>

    {{-- Payout Summary --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
      <div class="bg-gradient-to-r from-teal-50/60 to-transparent dark:from-teal-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06]">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-teal-500 to-cyan-500 text-white flex items-center justify-center shadow-md">
            <i class="bi bi-send-fill"></i>
          </div>
          สรุปการจ่ายเงินช่างภาพ
        </h3>
      </div>
      <div class="p-5 space-y-3">
        <div class="flex items-center justify-between p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200/60 dark:border-emerald-500/20">
          <div>
            <div class="text-xs text-emerald-700 dark:text-emerald-300 font-semibold uppercase tracking-wider">จ่ายแล้ว</div>
            <div class="text-xl font-bold text-emerald-700 dark:text-emerald-300 mt-1">฿{{ number_format($payoutsPaid, 2) }}</div>
          </div>
          <div class="w-11 h-11 rounded-xl bg-emerald-500/20 dark:bg-emerald-500/25 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
            <i class="bi bi-check-circle-fill text-xl"></i>
          </div>
        </div>
        <div class="flex items-center justify-between p-4 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200/60 dark:border-amber-500/20">
          <div>
            <div class="text-xs text-amber-700 dark:text-amber-300 font-semibold uppercase tracking-wider">รอจ่าย</div>
            <div class="text-xl font-bold text-amber-700 dark:text-amber-300 mt-1">฿{{ number_format($payoutsPending, 2) }}</div>
          </div>
          <div class="w-11 h-11 rounded-xl bg-amber-500/20 dark:bg-amber-500/25 text-amber-600 dark:text-amber-400 flex items-center justify-center">
            <i class="bi bi-hourglass-split text-xl"></i>
          </div>
        </div>
        <div class="flex items-center justify-between p-4 rounded-xl bg-cyan-50 dark:bg-cyan-500/10 border border-cyan-200/60 dark:border-cyan-500/20">
          <div>
            <div class="text-xs text-cyan-700 dark:text-cyan-300 font-semibold uppercase tracking-wider">ค่าธรรมเนียมรวม</div>
            <div class="text-xl font-bold text-cyan-700 dark:text-cyan-300 mt-1">฿{{ number_format($totalCommission, 2) }}</div>
          </div>
          <div class="w-11 h-11 rounded-xl bg-cyan-500/20 dark:bg-cyan-500/25 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
            <i class="bi bi-percent text-xl"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══ Top 10 Events by Revenue ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-amber-50/60 to-transparent dark:from-amber-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06]">
      <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-md">
          <i class="bi bi-trophy-fill"></i>
        </div>
        10 อีเวนต์ยอดนิยม (ตามรายรับ)
      </h3>
    </div>
    <div>
      @if($topEvents->count() > 0)
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50/80 dark:bg-slate-900/40 text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">
              <tr>
                <th class="text-left px-4 py-3 font-semibold">#</th>
                <th class="text-left px-4 py-3 font-semibold">อีเวนต์</th>
                <th class="text-center px-4 py-3 font-semibold">ออเดอร์</th>
                <th class="text-right px-4 py-3 font-semibold">รายรับ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/60 dark:divide-white/[0.06]">
              @foreach($topEvents as $i => $event)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                  <td class="px-4 py-3 tabular-nums">
                    @if($i < 3)
                      <span class="font-bold" style="color:{{ ['#f59e0b','#94a3b8','#cd7c2f'][$i] }};">{{ $i+1 }}</span>
                    @else
                      <span class="text-slate-500 dark:text-slate-400">{{ $i+1 }}</span>
                    @endif
                  </td>
                  <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $event->event_name }}</td>
                  <td class="px-4 py-3 text-center">
                    <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                      {{ number_format($event->orders_count) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-600 dark:text-emerald-400">฿{{ number_format($event->revenue, 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="p-8 text-center text-slate-500 dark:text-slate-400">
          <i class="bi bi-inbox text-3xl block mb-2 opacity-50"></i>
          <span class="text-sm">ไม่มีข้อมูลอีเวนต์</span>
        </div>
      @endif
    </div>
  </div>

  {{-- ═══ Photographer Earnings Table ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-emerald-50/60 to-transparent dark:from-emerald-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06]">
      <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center shadow-md">
          <i class="bi bi-camera-fill"></i>
        </div>
        รายรับช่างภาพ
      </h3>
    </div>
    <div>
      @if($photographerEarnings->count() > 0)
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50/80 dark:bg-slate-900/40 text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">
              <tr>
                <th class="text-left px-4 py-3 font-semibold">ช่างภาพ</th>
                <th class="text-center px-4 py-3 font-semibold">ออเดอร์</th>
                <th class="text-right px-4 py-3 font-semibold">ยอดขายรวม</th>
                <th class="text-right px-4 py-3 font-semibold">ค่าธรรมเนียม</th>
                <th class="text-right px-4 py-3 font-semibold">รายรับสุทธิ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/60 dark:divide-white/[0.06]">
              @foreach($photographerEarnings as $pg)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                  <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $pg->photographer_name }}</td>
                  <td class="px-4 py-3 text-center text-slate-700 dark:text-slate-300 tabular-nums">{{ number_format($pg->payout_count) }}</td>
                  <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300 tabular-nums">฿{{ number_format($pg->total_sales, 2) }}</td>
                  <td class="px-4 py-3 text-right text-rose-600 dark:text-rose-400 tabular-nums">฿{{ number_format($pg->total_commission, 2) }}</td>
                  <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">฿{{ number_format($pg->net_earnings, 2) }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot class="bg-slate-50/80 dark:bg-slate-900/40">
              <tr>
                <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-100">รวม</td>
                <td class="px-4 py-3 font-bold text-center text-slate-800 dark:text-slate-100 tabular-nums">{{ number_format($photographerEarnings->sum('payout_count')) }}</td>
                <td class="px-4 py-3 font-bold text-right text-slate-800 dark:text-slate-100 tabular-nums">฿{{ number_format($photographerEarnings->sum('total_sales'), 2) }}</td>
                <td class="px-4 py-3 font-bold text-right text-rose-600 dark:text-rose-400 tabular-nums">฿{{ number_format($photographerEarnings->sum('total_commission'), 2) }}</td>
                <td class="px-4 py-3 font-bold text-right text-emerald-600 dark:text-emerald-400 tabular-nums">฿{{ number_format($photographerEarnings->sum('net_earnings'), 2) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      @else
        <div class="p-10 text-center">
          <i class="bi bi-camera text-4xl text-slate-300 dark:text-slate-600 block mb-2"></i>
          <p class="text-slate-500 dark:text-slate-400 text-sm">ไม่มีข้อมูลรายรับช่างภาพในช่วงวันที่ที่เลือก</p>
        </div>
      @endif
    </div>
  </div>

</div>
@endsection
