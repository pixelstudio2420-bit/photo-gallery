@extends('layouts.admin')

@section('title', 'Unit Economics / LTV')

@php
    $fmtB = fn ($n) => number_format((float) $n, 2) . ' ฿';
    $maxMonthRev = collect($monthly)->max('revenue') ?: 1;
    $maxCohort = 0;
    foreach ($cohorts as $c) {
        foreach ($c['months'] as $v) { if ($v > $maxCohort) $maxCohort = $v; }
    }
    $maxCohort = $maxCohort ?: 1;

    $heatColor = function ($v) use ($maxCohort) {
        if ($v <= 0) return 'bg-gray-50 dark:bg-slate-800/60 text-gray-400';
        $intensity = min(1, $v / $maxCohort);
        if ($intensity > 0.8) return 'bg-emerald-600 text-white';
        if ($intensity > 0.5) return 'bg-emerald-500 text-white';
        if ($intensity > 0.25) return 'bg-emerald-300 text-emerald-900';
        return 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-200';
    };
@endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h4 class="font-bold mb-1 tracking-tight flex items-center gap-2">
            <i class="bi bi-graph-up-arrow text-emerald-500"></i>
            Unit Economics / LTV
            <span class="text-xs font-normal text-gray-400 ml-2">/ เศรษฐศาสตร์ต่อลูกค้า</span>
        </h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 m-0">
            AOV · LTV · Repeat Rate · CAC · Cohort — ตัวเลขที่บอกว่าธุรกิจคุ้มทุนรึยัง
        </p>
    </div>
    <div class="text-xs text-gray-400">
        window: rolling {{ $headline['window_days'] }} วัน
    </div>
</div>

{{-- ═══ KPI cards row 1 ═════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">AOV (Avg Order Value)</div>
        <div class="text-2xl font-bold mt-1">{{ $fmtB($headline['aov']) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">{{ $headline['orders_90d'] }} orders / 90d</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">LTV (ประมาณการ)</div>
        <div class="text-2xl font-bold mt-1 text-emerald-600 dark:text-emerald-300">{{ $fmtB($headline['ltv']) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">{{ $headline['avg_orders_per_user'] }} orders/user × AOV</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">Repeat Rate</div>
        <div class="text-2xl font-bold mt-1 {{ $headline['repeat_rate_pct'] >= 30 ? 'text-emerald-600 dark:text-emerald-300' : ($headline['repeat_rate_pct'] >= 15 ? 'text-amber-500' : '') }}">
            {{ number_format($headline['repeat_rate_pct'], 1) }}%
        </div>
        <div class="text-[11px] text-gray-400 mt-1">paying customers ≥ 2 orders</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">CAC / LTV Ratio</div>
        <div class="text-2xl font-bold mt-1">
            @if($headline['ltv_cac_ratio'])
                {{ $headline['ltv_cac_ratio'] }}x
            @else
                <span class="text-gray-400 text-lg">—</span>
            @endif
        </div>
        <div class="text-[11px] text-gray-400 mt-1">
            @if($headline['cac'])
                CAC ≈ {{ $fmtB($headline['cac']) }}
            @else
                ยังไม่มีข้อมูล ad spend
            @endif
        </div>
    </div>
</div>

{{-- ═══ KPI cards row 2 ═════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">ลูกค้าทั้งหมด</div>
        <div class="text-2xl font-bold mt-1">{{ number_format($headline['total_users']) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">{{ $headline['paying_users'] }} จ่ายเงินแล้ว ({{ $headline['conversion_pct'] }}%)</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">รายได้ 90 วัน</div>
        <div class="text-2xl font-bold mt-1">{{ $fmtB($headline['revenue_90d']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">Gross Margin (เดือนนี้)</div>
        <div class="text-2xl font-bold mt-1 {{ $headline['gross_margin_pct'] > 30 ? 'text-emerald-600 dark:text-emerald-300' : ($headline['gross_margin_pct'] > 0 ? 'text-amber-500' : 'text-rose-500') }}">
            {{ number_format($headline['gross_margin_pct'], 1) }}%
        </div>
        <div class="text-[11px] text-gray-400 mt-1">rev {{ $fmtB($headline['monthly_revenue']) }} − exp {{ $fmtB($headline['monthly_expense']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">Break-Even (ออเดอร์/เดือน)</div>
        <div class="text-2xl font-bold mt-1">
            @if($headline['break_even_orders'])
                {{ number_format($headline['break_even_orders']) }}
            @else
                <span class="text-gray-400 text-lg">—</span>
            @endif
        </div>
        <div class="text-[11px] text-gray-400 mt-1">ที่ AOV ปัจจุบัน</div>
    </div>
</div>

{{-- ═══ Monthly revenue chart ══════════════════════════════════════════ --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 mb-4">
    <div class="p-3 border-b border-gray-100 dark:border-white/5">
        <h5 class="font-semibold text-sm flex items-center gap-2"><i class="bi bi-bar-chart text-indigo-500"></i>รายได้รายเดือน (12 เดือน)</h5>
    </div>
    <div class="p-4">
        @if(empty($monthly))
            <p class="text-gray-500 text-sm text-center py-8">ยังไม่มีข้อมูล orders ที่จ่ายแล้ว</p>
        @else
            <div class="flex items-end gap-2 h-32">
                @foreach($monthly as $m)
                    @php $h = max(4, (int) round($m['revenue'] / $maxMonthRev * 120)); @endphp
                    <div class="flex-1 flex flex-col items-center justify-end">
                        <div class="bg-gradient-to-t from-indigo-600 to-indigo-400 rounded-t w-full" style="height: {{ $h }}px;"
                             title="{{ $m['month'] }}: {{ $fmtB($m['revenue']) }} ({{ $m['orders'] }} orders)"></div>
                        <div class="text-[10px] text-gray-400 mt-1">{{ substr($m['month'], 5) }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- ═══ Cohort heatmap ═════════════════════════════════════════════════ --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 mb-4">
    <div class="p-3 border-b border-gray-100 dark:border-white/5">
        <h5 class="font-semibold text-sm flex items-center gap-2"><i class="bi bi-grid-3x3 text-amber-500"></i>Cohort Revenue (by first-order month)</h5>
    </div>
    <div class="overflow-x-auto p-3">
        @if(empty($cohorts))
            <p class="text-gray-500 text-sm text-center py-8">ยังไม่มี cohort data</p>
        @else
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-gray-500 dark:text-gray-400">
                        <th class="px-2 py-2 text-left">Cohort</th>
                        <th class="px-2 py-2 text-right">ขนาด</th>
                        <th class="px-2 py-2 text-right">Total</th>
                        @for($i = 0; $i < 12; $i++)
                            <th class="px-2 py-2 text-center">M{{ $i }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach($cohorts as $c)
                        <tr>
                            <td class="px-2 py-1 font-mono text-[11px]">{{ $c['cohort'] }}</td>
                            <td class="px-2 py-1 text-right">{{ $c['size'] }}</td>
                            <td class="px-2 py-1 text-right font-semibold">{{ $fmtB($c['revenue_total']) }}</td>
                            @for($i = 0; $i < 12; $i++)
                                @php $val = $c['months'][$i] ?? 0; @endphp
                                <td class="px-1 py-1 text-center">
                                    <div class="rounded px-1 py-0.5 {{ $heatColor($val) }}">
                                        {{ $val > 0 ? number_format($val, 0) : '—' }}
                                    </div>
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- ═══ Top customers ══════════════════════════════════════════════════ --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 mb-4">
    <div class="p-3 border-b border-gray-100 dark:border-white/5">
        <h5 class="font-semibold text-sm flex items-center gap-2"><i class="bi bi-trophy text-yellow-500"></i>Top 15 Customers (by revenue)</h5>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">ลูกค้า</th>
                    <th class="px-4 py-3">Orders</th>
                    <th class="px-4 py-3">Revenue</th>
                    <th class="px-4 py-3">AOV</th>
                    <th class="px-4 py-3">First / Last</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($customers as $c)
                    @php $aov = $c['orders'] > 0 ? $c['revenue'] / $c['orders'] : 0; @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $c['name'] }}</div>
                            <div class="text-[11px] text-gray-400">{{ $c['email'] }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $c['orders'] }}</td>
                        <td class="px-4 py-3 font-semibold text-emerald-600 dark:text-emerald-300">{{ $fmtB($c['revenue']) }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $fmtB($aov) }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <div>{{ \Carbon\Carbon::parse($c['first_at'])->format('d M Y') }}</div>
                            <div class="text-gray-400">ล่าสุด: {{ \Carbon\Carbon::parse($c['last_at'])->diffForHumans() }}</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">ยังไม่มีลูกค้าที่จ่ายเงิน</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
