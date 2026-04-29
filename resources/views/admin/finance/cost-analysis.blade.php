@extends('layouts.admin')

@section('title', 'วิเคราะห์ต้นทุน-กำไร')

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ────────── PAGE HEADER ────────── --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h4 class="text-2xl font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-3 tracking-tight">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl shadow-md shadow-violet-500/30"
              style="background:linear-gradient(135deg,#7c3aed,#3b82f6);">
          <i class="bi bi-graph-up-arrow text-white text-xl"></i>
        </span>
        วิเคราะห์ต้นทุน-กำไร
      </h4>
      <p class="text-sm text-slate-500 dark:text-slate-400 ml-14">
        รายรับ-รายจ่าย-กำไรรวม รายวัน รายเดือน รายปี
      </p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <a href="{{ route('admin.finance.plan-profit') }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-violet-50 dark:bg-violet-500/15 border border-violet-200 dark:border-violet-500/30 text-violet-700 dark:text-violet-200 text-sm font-medium hover:bg-violet-100 dark:hover:bg-violet-500/25">
        <i class="bi bi-pie-chart-fill"></i> ดูกำไรต่อแผนสมัคร
      </a>
    </div>
  </div>

  @if(session('success'))
  <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 dark:bg-emerald-500/15 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-200 text-sm">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
  @endif

  {{-- ────────── PERIOD TOGGLE ────────── --}}
  <div class="flex items-center gap-2 mb-6 flex-wrap">
    <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider mr-2">ช่วงเวลา:</span>
    @foreach([
      'day'   => 'วันนี้',
      'month' => 'เดือนนี้',
      'year'  => 'ปีนี้',
    ] as $key => $label)
      @php $isActive = $period === $key; @endphp
      <a href="{{ route('admin.finance.cost-analysis', ['period' => $key]) }}"
         class="px-4 py-2 rounded-lg text-sm font-bold transition border
                {{ $isActive ? 'bg-gradient-to-r from-violet-600 to-indigo-600 text-white border-transparent shadow-md' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
        {{ $label }}
      </a>
    @endforeach
  </div>

  {{-- ────────── TOP-LEVEL P&L SUMMARY ────────── --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    {{-- Revenue --}}
    <div class="rounded-2xl p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="flex items-center gap-2 mb-1">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-300">
          <i class="bi bi-cash-coin"></i>
        </span>
        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">รายรับ</span>
      </div>
      <p class="text-2xl font-black text-slate-900 dark:text-white" style="font-variant-numeric:tabular-nums;">
        ฿{{ number_format($analysis['totals']['revenue'], 0) }}
      </p>
    </div>

    {{-- Cost --}}
    <div class="rounded-2xl p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="flex items-center gap-2 mb-1">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-rose-500/15 text-rose-600 dark:text-rose-300">
          <i class="bi bi-arrow-down-circle-fill"></i>
        </span>
        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">ต้นทุน</span>
      </div>
      <p class="text-2xl font-black text-slate-900 dark:text-white" style="font-variant-numeric:tabular-nums;">
        ฿{{ number_format($analysis['totals']['cost'], 0) }}
      </p>
    </div>

    {{-- Gross Profit --}}
    <div class="rounded-2xl p-5 text-white shadow-md" style="background:linear-gradient(135deg, {{ $analysis['totals']['gross_profit'] >= 0 ? '#10b981, #14b8a6' : '#ef4444, #f97316' }});">
      <div class="flex items-center gap-2 mb-1">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white/20 text-white">
          <i class="bi bi-{{ $analysis['totals']['gross_profit'] >= 0 ? 'graph-up-arrow' : 'graph-down-arrow' }}"></i>
        </span>
        <span class="text-xs font-bold uppercase tracking-wider opacity-90">{{ $analysis['totals']['gross_profit'] >= 0 ? 'กำไร' : 'ขาดทุน' }}</span>
      </div>
      <p class="text-2xl font-black" style="font-variant-numeric:tabular-nums;">
        {{ $analysis['totals']['gross_profit'] >= 0 ? '+' : '' }}฿{{ number_format($analysis['totals']['gross_profit'], 0) }}
      </p>
    </div>

    {{-- Margin --}}
    <div class="rounded-2xl p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="flex items-center gap-2 mb-1">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-violet-500/15 text-violet-600 dark:text-violet-300">
          <i class="bi bi-percent"></i>
        </span>
        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Margin</span>
      </div>
      <p class="text-2xl font-black {{ $analysis['totals']['margin_pct'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}" style="font-variant-numeric:tabular-nums;">
        {{ number_format($analysis['totals']['margin_pct'], 1) }}%
      </p>
    </div>
  </div>

  {{-- ────────── 3-COLUMN CARDS: today vs month vs year ────────── --}}
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    @foreach([
      ['data' => $today,     'label' => 'วันนี้',  'icon' => 'bi-calendar-day',   'color' => 'sky'],
      ['data' => $thisMonth, 'label' => 'เดือนนี้', 'icon' => 'bi-calendar-month', 'color' => 'violet'],
      ['data' => $thisYear,  'label' => 'ปีนี้',     'icon' => 'bi-calendar3',       'color' => 'amber'],
    ] as $card)
      @php $d = $card['data']; @endphp
      <div class="rounded-2xl p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
        <div class="flex items-center gap-2 mb-3 text-xs font-bold uppercase tracking-wider text-{{ $card['color'] }}-600 dark:text-{{ $card['color'] }}-300">
          <i class="bi {{ $card['icon'] }}"></i> {{ $card['label'] }}
        </div>
        <div class="space-y-1.5 text-sm">
          <div class="flex justify-between">
            <span class="text-slate-500 dark:text-slate-400">รายรับ</span>
            <span class="font-bold text-emerald-600 dark:text-emerald-400" style="font-variant-numeric:tabular-nums;">
              ฿{{ number_format($d['totals']['revenue'], 0) }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500 dark:text-slate-400">ต้นทุน</span>
            <span class="font-bold text-rose-600 dark:text-rose-400" style="font-variant-numeric:tabular-nums;">
              ฿{{ number_format($d['totals']['cost'], 0) }}
            </span>
          </div>
          <div class="flex justify-between pt-1.5 mt-1.5 border-t border-slate-200 dark:border-white/10">
            <span class="font-bold text-slate-900 dark:text-white">กำไร</span>
            <span class="font-black {{ $d['totals']['gross_profit'] >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}" style="font-variant-numeric:tabular-nums;">
              {{ $d['totals']['gross_profit'] >= 0 ? '+' : '' }}฿{{ number_format($d['totals']['gross_profit'], 0) }}
              <span class="text-xs font-normal opacity-75">({{ number_format($d['totals']['margin_pct'], 1) }}%)</span>
            </span>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- ────────── REVENUE + COST BREAKDOWN ────────── --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    {{-- Revenue --}}
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
        <h6 class="font-bold text-slate-900 dark:text-white m-0 flex items-center gap-2">
          <i class="bi bi-cash-coin text-emerald-500"></i> รายรับแยกหมวด
        </h6>
        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400" style="font-variant-numeric:tabular-nums;">
          ฿{{ number_format(array_sum($analysis['revenue']), 0) }}
        </span>
      </div>
      <div class="p-5 space-y-3">
        @php
          $revenueLabels = [
            'photo_orders'    => ['📸 ขายภาพ', '#0d9488'],
            'credit_packages' => ['💳 แพ็กเครดิต', '#3b82f6'],
            'subscriptions'   => ['⭐ แผนสมัครสมาชิกช่างภาพ', '#7c3aed'],
            'user_storage'    => ['☁️ พื้นที่เก็บข้อมูลลูกค้า', '#0ea5e9'],
            'gift_cards'      => ['🎁 บัตรของขวัญ', '#ec4899'],
          ];
          $totalRev = array_sum($analysis['revenue']) ?: 1;
        @endphp
        @foreach($revenueLabels as $key => [$label, $color])
          @php $value = $analysis['revenue'][$key] ?? 0; $pct = ($value / $totalRev) * 100; @endphp
          <div>
            <div class="flex justify-between items-baseline mb-1 text-sm">
              <span class="text-slate-700 dark:text-slate-200 font-medium">{{ $label }}</span>
              <span class="font-bold text-slate-900 dark:text-white" style="font-variant-numeric:tabular-nums;">
                ฿{{ number_format($value, 0) }}
                <span class="text-xs text-slate-400 ml-1">({{ number_format($pct, 1) }}%)</span>
              </span>
            </div>
            <div class="h-2 bg-slate-100 dark:bg-white/[0.06] rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all" style="width:{{ min(100, $pct) }}%;background:{{ $color }};"></div>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Cost --}}
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
        <h6 class="font-bold text-slate-900 dark:text-white m-0 flex items-center gap-2">
          <i class="bi bi-arrow-down-circle-fill text-rose-500"></i> ต้นทุนแยกหมวด
        </h6>
        <span class="text-xs font-bold text-rose-600 dark:text-rose-400" style="font-variant-numeric:tabular-nums;">
          ฿{{ number_format(array_sum($analysis['costs']), 0) }}
        </span>
      </div>
      <div class="p-5 space-y-3">
        @php
          $costLabels = [
            'photographer_payouts' => ['👤 จ่ายช่างภาพ', '#dc2626'],
            'gateway_fees'         => ['💳 ค่า gateway', '#ea580c'],
            'storage'              => ['☁️ Cloud Storage (R2/S3)', '#7c3aed'],
            'ai_rekognition'       => ['🤖 AWS Rekognition (Face/Quality)', '#9333ea'],
            'ai_captions'          => ['🤖 OpenAI/Anthropic (Captions)', '#a855f7'],
            'disbursement_fees'    => ['🏦 ค่าโอนเงิน (Bank/Omise)', '#f59e0b'],
            'email'                => ['✉️ Email (SES/Postmark)', '#0ea5e9'],
            'server_hosting'       => ['🖥️ Server / Hosting', '#475569'],
          ];
          $totalCost = array_sum($analysis['costs']) ?: 1;
        @endphp
        @foreach($costLabels as $key => [$label, $color])
          @php $value = $analysis['costs'][$key] ?? 0; $pct = ($value / $totalCost) * 100; @endphp
          <div>
            <div class="flex justify-between items-baseline mb-1 text-sm">
              <span class="text-slate-700 dark:text-slate-200 font-medium">{{ $label }}</span>
              <span class="font-bold text-slate-900 dark:text-white" style="font-variant-numeric:tabular-nums;">
                ฿{{ number_format($value, 0) }}
                <span class="text-xs text-slate-400 ml-1">({{ number_format($pct, 1) }}%)</span>
              </span>
            </div>
            <div class="h-2 bg-slate-100 dark:bg-white/[0.06] rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all" style="width:{{ min(100, $pct) }}%;background:{{ $color }};"></div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- ────────── TREND CHART (SVG bars) ────────── --}}
  @if(count($analysis['trend']) > 0)
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-5 mb-6">
    <h6 class="font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
      <i class="bi bi-bar-chart-line text-violet-500"></i>
      Trend ({{ count($analysis['trend']) }} จุด)
    </h6>
    @php
      $maxRev = max(array_map(fn($b) => max($b['revenue'], $b['cost']), $analysis['trend'])) ?: 1;
    @endphp
    <div class="flex items-end gap-1 overflow-x-auto pb-2" style="min-height:160px;">
      @foreach($analysis['trend'] as $bucket)
        @php
          $revH = ($bucket['revenue'] / $maxRev) * 130;
          $costH = ($bucket['cost'] / $maxRev) * 130;
          $profitColor = $bucket['profit'] >= 0 ? '#10b981' : '#ef4444';
        @endphp
        <div class="flex flex-col items-center" style="min-width:36px;">
          <div class="flex items-end gap-0.5" style="height:140px;">
            <div title="รายรับ ฿{{ number_format($bucket['revenue'], 0) }}" class="w-3 rounded-t" style="height:{{ max(2, $revH) }}px;background:linear-gradient(to top, #10b981, #34d399);"></div>
            <div title="ต้นทุน ฿{{ number_format($bucket['cost'], 0) }}" class="w-3 rounded-t" style="height:{{ max(2, $costH) }}px;background:linear-gradient(to top, #ef4444, #f87171);"></div>
          </div>
          <div class="text-[10px] font-bold mt-1" style="color:{{ $profitColor }};">
            {{ $bucket['profit'] >= 0 ? '+' : '' }}{{ number_format($bucket['profit'] / 1000, 1) }}k
          </div>
          <div class="text-[10px] text-slate-400 mt-0.5">{{ $bucket['label'] }}</div>
        </div>
      @endforeach
    </div>
    <div class="flex items-center gap-4 mt-3 text-xs">
      <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-400">
        <span class="w-3 h-3 rounded" style="background:#10b981;"></span> รายรับ
      </span>
      <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-400">
        <span class="w-3 h-3 rounded" style="background:#ef4444;"></span> ต้นทุน
      </span>
    </div>
  </div>
  @endif

  {{-- ────────── COST RATE ASSUMPTIONS PANEL ────────── --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-5">
    <details>
      <summary class="cursor-pointer flex items-center gap-2 font-bold text-slate-900 dark:text-white">
        <i class="bi bi-sliders text-violet-500"></i>
        ปรับสมมติฐานต้นทุน (Cost Rate Assumptions)
        <span class="ml-auto text-xs text-slate-400 font-normal">— ค่าเหล่านี้ใช้คำนวณตัวเลขด้านบน</span>
      </summary>
      <form method="POST" action="{{ route('admin.finance.cost-analysis.rates') }}" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
        @csrf
        @php
          $rateLabels = [
            'storage_per_gb_month'    => ['☁️ Storage R2/S3', 'บาท / GB / เดือน'],
            'server_monthly_baseline' => ['🖥️ Server baseline', 'บาท / เดือน'],
            'rekognition_per_face'    => ['🤖 AWS Rekognition', 'บาท / face indexed'],
            'ai_caption_per_call'     => ['🤖 AI Caption (OpenAI/Anthropic)', 'บาท / call'],
            'email_per_send'          => ['✉️ Email send', 'บาท / email'],
            'gateway_fee_pct'         => ['💳 Gateway fee', '% ของรายรับ'],
            'bank_transfer_fee'       => ['🏦 Bank transfer', 'บาท / disbursement'],
          ];
        @endphp
        @foreach($rateLabels as $key => [$label, $unit])
          <div>
            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">{{ $label }}</label>
            <div class="flex">
              <input type="number" step="0.01" min="0" name="rate_{{ $key }}" value="{{ $rates[$key] ?? '' }}"
                     class="flex-1 px-3 py-2 rounded-l-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-100 text-sm focus:ring-2 focus:ring-violet-300">
              <span class="px-3 py-2 rounded-r-lg bg-slate-100 dark:bg-slate-700 border border-l-0 border-slate-300 dark:border-white/10 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $unit }}</span>
            </div>
          </div>
        @endforeach
        <div class="md:col-span-2 flex justify-end">
          <button type="submit" class="px-5 py-2 rounded-lg text-white text-sm font-bold shadow-md"
                  style="background:linear-gradient(135deg,#7c3aed,#3b82f6);">
            <i class="bi bi-save-fill mr-1.5"></i> บันทึกค่าสมมติฐาน
          </button>
        </div>
      </form>
    </details>
  </div>
</div>
@endsection
