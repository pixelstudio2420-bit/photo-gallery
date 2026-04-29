@extends('layouts.admin')

@section('title', 'กำไร-ต้นทุนต่อแผน')

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ────────── HEADER ────────── --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h4 class="text-2xl font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-3 tracking-tight">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl shadow-md shadow-violet-500/30"
              style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
          <i class="bi bi-pie-chart-fill text-white text-xl"></i>
        </span>
        กำไร-ต้นทุนต่อแผนสมัครสมาชิก
      </h4>
      <p class="text-sm text-slate-500 dark:text-slate-400 ml-14">
        แผนไหนยอดนิยม / กำไรเท่าไร / ต้นทุนต่อแผน + ฟีเจอร์
      </p>
    </div>
    <a href="{{ route('admin.finance.cost-analysis') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-200 text-sm font-medium hover:bg-slate-200 dark:hover:bg-slate-600">
      <i class="bi bi-arrow-left"></i> กลับวิเคราะห์ต้นทุน
    </a>
  </div>

  {{-- ────────── PERIOD ────────── --}}
  <div class="text-xs text-slate-500 dark:text-slate-400 mb-6">
    <i class="bi bi-calendar3 mr-1"></i>
    ช่วง: <strong class="text-slate-700 dark:text-slate-200">{{ \Carbon\Carbon::parse($report['from'])->format('d/m/Y') }}</strong>
    – <strong class="text-slate-700 dark:text-slate-200">{{ \Carbon\Carbon::parse($report['to'])->format('d/m/Y') }}</strong>
    <span class="text-slate-400 ml-2">({{ number_format($report['period_months'], 1) }} เดือน)</span>
  </div>

  {{-- ────────── HIGHLIGHTS ────────── --}}
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    {{-- Most popular --}}
    @if($report['most_popular'])
    <div class="rounded-2xl p-5 text-white shadow-md" style="background:linear-gradient(135deg,#10b981,#059669);">
      <div class="text-xs font-bold uppercase tracking-wider opacity-90 mb-1">⭐ ยอดนิยมที่สุด</div>
      <p class="text-xl font-black m-0 mb-1">{{ $report['most_popular']['plan_name'] }}</p>
      <p class="text-sm opacity-90 m-0">{{ number_format($report['most_popular']['subscribers']) }} subscribers</p>
    </div>
    @endif

    {{-- Total revenue --}}
    <div class="rounded-2xl p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">รายรับจากแผนรวม</div>
      <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400" style="font-variant-numeric:tabular-nums;">
        ฿{{ number_format($report['totals']['revenue'], 0) }}
      </p>
    </div>

    {{-- Total cost --}}
    <div class="rounded-2xl p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">ต้นทุน Cost-to-Serve</div>
      <p class="text-2xl font-black text-rose-600 dark:text-rose-400" style="font-variant-numeric:tabular-nums;">
        ฿{{ number_format($report['totals']['cost'], 0) }}
      </p>
    </div>

    {{-- Profit --}}
    <div class="rounded-2xl p-5 text-white shadow-md" style="background:linear-gradient(135deg, {{ $report['totals']['profit'] >= 0 ? '#7c3aed,#3b82f6' : '#ef4444,#f97316' }});">
      <div class="text-xs font-bold uppercase tracking-wider opacity-90 mb-1">{{ $report['totals']['profit'] >= 0 ? '💰 กำไรรวม' : '⚠️ ขาดทุน' }}</div>
      <p class="text-2xl font-black m-0" style="font-variant-numeric:tabular-nums;">
        {{ $report['totals']['profit'] >= 0 ? '+' : '' }}฿{{ number_format($report['totals']['profit'], 0) }}
      </p>
      <p class="text-xs opacity-90 mt-1">Margin {{ number_format($report['totals']['margin'], 1) }}%</p>
    </div>
  </div>

  {{-- ────────── PER-PLAN TABLE ────────── --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
      <h6 class="font-bold text-slate-900 dark:text-white m-0 flex items-center gap-2">
        <i class="bi bi-list-ul text-violet-500"></i>
        รายแผน — เรียงตามยอดนิยม
      </h6>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-900/50">
          <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <th class="px-5 py-3">แผน</th>
            <th class="px-5 py-3 text-right">ราคา</th>
            <th class="px-5 py-3 text-center">Subscribers</th>
            <th class="px-5 py-3 text-right">รายรับ</th>
            <th class="px-5 py-3 text-right">ต้นทุน</th>
            <th class="px-5 py-3 text-right">กำไร</th>
            <th class="px-5 py-3 text-right">Margin</th>
            <th class="px-5 py-3 text-right">ARPU</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.06]">
          @foreach($report['plans'] as $plan)
            <tr class="hover:bg-slate-50/60 dark:hover:bg-white/[0.02] transition">
              <td class="px-5 py-3">
                <div class="font-bold text-slate-900 dark:text-white">{{ $plan['plan_name'] }}</div>
                <div class="text-xs text-slate-400">{{ $plan['plan_code'] }}</div>
              </td>
              <td class="px-5 py-3 text-right text-slate-700 dark:text-slate-200" style="font-variant-numeric:tabular-nums;">
                @if($plan['is_free'])
                  <span class="text-emerald-600 dark:text-emerald-400 font-bold">ฟรี</span>
                @else
                  ฿{{ number_format($plan['price_monthly'], 0) }}<span class="text-xs text-slate-400">/เดือน</span>
                @endif
              </td>
              <td class="px-5 py-3 text-center">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $plan['subscribers'] > 0 ? 'bg-violet-100 dark:bg-violet-500/20 text-violet-700 dark:text-violet-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500' }}">
                  {{ number_format($plan['subscribers']) }}
                </span>
              </td>
              <td class="px-5 py-3 text-right text-emerald-600 dark:text-emerald-400 font-bold" style="font-variant-numeric:tabular-nums;">
                ฿{{ number_format($plan['revenue'], 0) }}
              </td>
              <td class="px-5 py-3 text-right text-rose-600 dark:text-rose-400" style="font-variant-numeric:tabular-nums;">
                ฿{{ number_format($plan['cost'], 0) }}
              </td>
              <td class="px-5 py-3 text-right font-black {{ $plan['profit'] >= 0 ? 'text-violet-700 dark:text-violet-300' : 'text-rose-700 dark:text-rose-300' }}" style="font-variant-numeric:tabular-nums;">
                {{ $plan['profit'] >= 0 ? '+' : '' }}฿{{ number_format($plan['profit'], 0) }}
              </td>
              <td class="px-5 py-3 text-right">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $plan['margin_pct'] >= 50 ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' : ($plan['margin_pct'] >= 0 ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300' : 'bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300') }}">
                  {{ number_format($plan['margin_pct'], 1) }}%
                </span>
              </td>
              <td class="px-5 py-3 text-right text-slate-700 dark:text-slate-200 text-xs" style="font-variant-numeric:tabular-nums;">
                ฿{{ number_format($plan['arpu'], 0) }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- ────────── PER-PLAN COST BREAKDOWN ────────── --}}
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($report['plans'] as $plan)
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
          <div>
            <h6 class="font-bold text-slate-900 dark:text-white m-0">{{ $plan['plan_name'] }}</h6>
            <p class="text-xs text-slate-400 m-0">{{ number_format($plan['subscribers']) }} subs · {{ number_format($plan['storage_gb_total'], 1) }} GB used</p>
          </div>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $plan['margin_pct'] >= 50 ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700' : ($plan['margin_pct'] >= 0 ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-700' : 'bg-rose-100 dark:bg-rose-500/20 text-rose-700') }}">
            {{ number_format($plan['margin_pct'], 1) }}%
          </span>
        </div>

        <div class="p-5 space-y-2 text-sm">
          @php
            $cb = $plan['cost_breakdown'];
            $totalC = max(0.01, array_sum($cb));
          @endphp
          @foreach([
            'storage'      => ['☁️ Storage', '#0ea5e9'],
            'ai'           => ['🤖 AI services', '#9333ea'],
            'gateway_fees' => ['💳 Gateway fee', '#f59e0b'],
            'server_share' => ['🖥️ Server share', '#64748b'],
          ] as $key => [$label, $color])
            @php $val = $cb[$key] ?? 0; $pct = ($val / $totalC) * 100; @endphp
            <div>
              <div class="flex justify-between text-xs">
                <span class="text-slate-600 dark:text-slate-400">{{ $label }}</span>
                <span class="font-bold text-slate-900 dark:text-white" style="font-variant-numeric:tabular-nums;">
                  ฿{{ number_format($val, 0) }}
                </span>
              </div>
              <div class="h-1.5 bg-slate-100 dark:bg-white/[0.06] rounded-full mt-1">
                <div class="h-full rounded-full" style="width:{{ min(100, $pct) }}%;background:{{ $color }};"></div>
              </div>
            </div>
          @endforeach

          @if(count($plan['feature_costs']) > 0)
            <div class="pt-3 mt-3 border-t border-slate-200 dark:border-white/[0.06]">
              <p class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">ต้นทุนต่อฟีเจอร์ AI</p>
              <div class="grid grid-cols-2 gap-1.5 text-xs">
                @foreach($plan['feature_costs'] as $feature => $cost)
                  <div class="flex justify-between">
                    <span class="text-slate-600 dark:text-slate-400 truncate">{{ $feature }}</span>
                    <span class="font-bold text-rose-600 dark:text-rose-400">฿{{ number_format($cost, 0) }}</span>
                  </div>
                @endforeach
              </div>
            </div>
          @endif

          <div class="pt-3 mt-3 border-t border-slate-200 dark:border-white/[0.06] grid grid-cols-2 gap-2 text-xs">
            <div>
              <div class="text-slate-400">รายรับ</div>
              <div class="font-bold text-emerald-600 dark:text-emerald-400" style="font-variant-numeric:tabular-nums;">฿{{ number_format($plan['revenue'], 0) }}</div>
            </div>
            <div>
              <div class="text-slate-400">กำไร / sub</div>
              <div class="font-bold {{ $plan['arpu'] - $plan['cost_per_sub'] >= 0 ? 'text-violet-700 dark:text-violet-300' : 'text-rose-700 dark:text-rose-300' }}" style="font-variant-numeric:tabular-nums;">
                ฿{{ number_format($plan['arpu'] - $plan['cost_per_sub'], 0) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- ────────── EXPLAINER ────────── --}}
  <div class="mt-6 px-4 py-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-white/10 text-xs text-slate-600 dark:text-slate-400">
    <i class="bi bi-info-circle text-violet-500 mr-1"></i>
    ตัวเลขกำไร/ต้นทุนคำนวณจากค่าสมมติฐานต้นทุน
    (<a href="{{ route('admin.finance.cost-analysis') }}#rates" class="text-violet-600 dark:text-violet-300 hover:underline">ปรับได้ที่หน้าวิเคราะห์ต้นทุน</a>) —
    Storage = bytes ที่ใช้จริง × อัตรา R2/S3 / AI = AiTask ที่ done × อัตรา / Gateway = % ของรายรับ / Server = แชร์ตามสัดส่วน subscriber
  </div>
</div>
@endsection
