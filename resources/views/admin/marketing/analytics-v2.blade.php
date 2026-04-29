@extends('layouts.admin')
@section('title', 'Marketing Analytics v2')
@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="bi bi-graph-up-arrow text-indigo-500"></i> Marketing Analytics v2
            </h1>
            <p class="text-sm text-slate-500 mt-1">Funnel · Cohort · LTV · ROAS</p>
        </div>
        <div class="flex items-center gap-2">
            @if($enabled)
                <span class="px-2 py-0.5 text-xs rounded bg-emerald-500/20 text-emerald-500 border border-emerald-500/30">Tracking ON</span>
            @else
                <span class="px-2 py-0.5 text-xs rounded bg-slate-500/20 text-slate-500 border border-slate-500/30">Tracking OFF</span>
            @endif

            <form method="GET" class="inline-flex items-center gap-1">
                <select name="days" onchange="this.form.submit()" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                    @foreach([7,14,30,60,90] as $d)
                        <option value="{{ $d }}" @selected($days==$d)>{{ $d }} วัน</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Settings form --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
        <form method="POST" action="{{ route('admin.marketing.analytics-v2.settings') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <label class="flex items-center gap-2">
                <input type="checkbox" name="analytics_enabled" value="1"
                       {{ $enabled ? 'checked' : '' }} class="rounded">
                <span class="text-sm font-semibold">เปิดการเก็บ event</span>
            </label>
            <div>
                <label class="block text-xs mb-1">เก็บ event นานกี่วัน</label>
                <input type="number" name="event_retention_days" min="7" max="3650"
                       value="{{ \App\Models\AppSetting::get('marketing_event_retention_days', 180) }}"
                       class="px-3 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm w-28">
            </div>
            <button class="px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm">บันทึก</button>
        </form>
    </div>

    {{-- Overview tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
            <div class="text-xs text-slate-500">Page views today</div>
            <div class="text-xl font-bold text-indigo-500">{{ number_format($overview['page_views_today']) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
            <div class="text-xs text-slate-500">Page views 7d</div>
            <div class="text-xl font-bold text-indigo-500">{{ number_format($overview['page_views_week']) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
            <div class="text-xs text-slate-500">Signups 30d</div>
            <div class="text-xl font-bold text-emerald-500">{{ number_format($overview['signups_month']) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
            <div class="text-xs text-slate-500">Purchases 30d</div>
            <div class="text-xl font-bold text-pink-500">{{ number_format($overview['purchases_month']) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
            <div class="text-xs text-slate-500">Revenue 30d</div>
            <div class="text-xl font-bold text-amber-500">฿{{ number_format($overview['revenue_month'], 0) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
            <div class="text-xs text-slate-500">Top source</div>
            <div class="text-xl font-bold text-slate-900 dark:text-white">{{ $overview['top_source'] }}</div>
        </div>
    </div>

    {{-- Funnel --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">
            <i class="bi bi-funnel text-indigo-500"></i> Purchase Funnel ({{ $days }} วัน, unique sessions)
        </h3>
        <div class="space-y-2">
            @foreach($funnel as $step)
                @php
                    $labels = [
                        'page_view' => 'Page View',
                        'view_product' => 'View Product',
                        'add_to_cart' => 'Add to Cart',
                        'begin_checkout' => 'Begin Checkout',
                        'purchase' => 'Purchase',
                    ];
                    $width = max(5, $step['rate_from_first']);
                @endphp
                <div class="space-y-1">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold">{{ $labels[$step['name']] ?? $step['name'] }}</span>
                        <span class="font-mono text-xs">
                            <span class="text-indigo-500">{{ number_format($step['count']) }}</span>
                            <span class="text-slate-500">· {{ $step['rate_from_first'] }}%</span>
                            <span class="text-slate-400">(prev {{ $step['rate_from_prev'] }}%)</span>
                        </span>
                    </div>
                    <div class="h-6 rounded bg-slate-100 dark:bg-slate-950 overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-indigo-500 to-purple-500" style="width: {{ $width }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Daily series --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">
                <i class="bi bi-eye text-indigo-500"></i> Page Views ({{ $days }} วัน)
            </h3>
            @php $max = max(1, collect($series['page_view'])->max('count')); @endphp
            <div class="flex items-end gap-0.5 h-32">
                @foreach($series['page_view'] as $d)
                    <div class="flex-1 bg-indigo-500/60 hover:bg-indigo-500 rounded-t transition" style="height: {{ ($d['count']/$max)*100 }}%" title="{{ $d['date'] }}: {{ $d['count'] }}"></div>
                @endforeach
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">
                <i class="bi bi-cart-check text-pink-500"></i> Purchases ({{ $days }} วัน)
            </h3>
            @php $max = max(1, collect($series['purchase'])->max('count')); @endphp
            <div class="flex items-end gap-0.5 h-32">
                @foreach($series['purchase'] as $d)
                    <div class="flex-1 bg-pink-500/60 hover:bg-pink-500 rounded-t transition" style="height: {{ ($d['count']/$max)*100 }}%" title="{{ $d['date'] }}: {{ $d['count'] }}"></div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ROAS table --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-x-auto">
        <div class="p-5 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                <i class="bi bi-cash-coin text-emerald-500"></i> ROAS — Revenue by Source × Medium × Campaign
            </h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-700">
                <tr class="text-left text-xs uppercase text-slate-500">
                    <th class="px-4 py-2">Source</th>
                    <th class="px-4 py-2">Medium</th>
                    <th class="px-4 py-2">Campaign</th>
                    <th class="px-4 py-2 text-right">Purchases</th>
                    <th class="px-4 py-2 text-right">Revenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse($roas as $r)
                    <tr>
                        <td class="px-4 py-2 font-semibold">{{ $r->utm_source ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $r->utm_medium ?? '—' }}</td>
                        <td class="px-4 py-2 text-xs text-slate-500">{{ $r->utm_campaign ?? '—' }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ number_format($r->purchases) }}</td>
                        <td class="px-4 py-2 text-right font-mono text-emerald-500">฿{{ number_format($r->revenue, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">ยังไม่มีข้อมูล — เก็บ event สักพักแล้วกลับมาดู</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- LTV by source --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-x-auto">
        <div class="p-5 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                <i class="bi bi-piggy-bank text-amber-500"></i> Lifetime Value by Acquisition Source
            </h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-700">
                <tr class="text-left text-xs uppercase text-slate-500">
                    <th class="px-4 py-2">Source</th>
                    <th class="px-4 py-2 text-right">Customers</th>
                    <th class="px-4 py-2 text-right">Total Revenue</th>
                    <th class="px-4 py-2 text-right">Avg Order</th>
                    <th class="px-4 py-2 text-right">LTV / customer</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse($ltv as $row)
                    <tr>
                        <td class="px-4 py-2 font-semibold">{{ $row->utm_source }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ number_format($row->customers) }}</td>
                        <td class="px-4 py-2 text-right font-mono">฿{{ number_format($row->total_revenue, 2) }}</td>
                        <td class="px-4 py-2 text-right font-mono">฿{{ number_format($row->avg_order, 2) }}</td>
                        <td class="px-4 py-2 text-right font-mono text-amber-500">฿{{ number_format($row->ltv, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">ยังไม่มีข้อมูล</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Cohort --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-x-auto">
        <div class="p-5 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                <i class="bi bi-people-fill text-teal-500"></i> Weekly Cohort Retention (purchase)
            </h3>
        </div>
        <table class="w-full text-xs">
            <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-700">
                <tr class="text-left uppercase text-slate-500">
                    <th class="px-4 py-2">Cohort</th>
                    <th class="px-4 py-2 text-right">Size</th>
                    <th class="px-4 py-2 text-right">W0</th>
                    <th class="px-4 py-2 text-right">W1</th>
                    <th class="px-4 py-2 text-right">W2</th>
                    <th class="px-4 py-2 text-right">W3</th>
                    <th class="px-4 py-2 text-right">W4</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse($cohort as $key => $row)
                    <tr>
                        <td class="px-4 py-2 font-mono">{{ $key }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ $row['size'] }}</td>
                        @foreach(['w0','w1','w2','w3','w4'] as $w)
                            @php $v = $row[$w]; $op = min(100, max(5, $v)); @endphp
                            <td class="px-4 py-2 text-right font-mono" style="background: rgba(20,184,166,{{ $op/100 }})">{{ $v }}%</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">ยังไม่มี cohort data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
