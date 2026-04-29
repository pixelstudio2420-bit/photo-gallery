@extends('layouts.admin')

@section('title', 'Capacity Planner')

@php
    /**
     * Small helper to map a utilization percentage to a Tailwind color set.
     * Used for the what-if tier bars.
     */
    $statusClasses = [
        'ok'       => ['bar' => 'from-emerald-400 to-emerald-600', 'text' => 'text-emerald-600 dark:text-emerald-300', 'badge' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300'],
        'warn'     => ['bar' => 'from-amber-400 to-amber-600',     'text' => 'text-amber-600 dark:text-amber-300',     'badge' => 'bg-amber-500/15 text-amber-600 dark:text-amber-300'],
        'hot'      => ['bar' => 'from-orange-400 to-orange-600',   'text' => 'text-orange-600 dark:text-orange-300',   'badge' => 'bg-orange-500/15 text-orange-600 dark:text-orange-300'],
        'critical' => ['bar' => 'from-rose-500 to-rose-700',       'text' => 'text-rose-600 dark:text-rose-300',       'badge' => 'bg-rose-500/15 text-rose-600 dark:text-rose-300'],
    ];

    $gaugeColor = function (float $pct) {
        if ($pct >= 90) return 'rose';
        if ($pct >= 75) return 'orange';
        if ($pct >= 50) return 'amber';
        return 'emerald';
    };
@endphp

@section('content')
{{-- ═══ Header ═══════════════════════════════════════════════════════ --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h4 class="font-bold mb-1 tracking-tight flex items-center gap-2">
            <i class="bi bi-speedometer2 text-indigo-500"></i>
            Capacity Planner
            <span class="text-xs font-normal text-gray-400 ml-2">/ ความสามารถของเซิร์ฟเวอร์</span>
        </h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 m-0">
            คำนวณว่าเซิร์ฟเวอร์นี้รองรับผู้ใช้พร้อมกันได้กี่คน, bottleneck อยู่ที่ไหน, และอีกกี่วันต้อง scale
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.system.dashboard') }}"
           class="px-4 py-2 border border-gray-200 dark:border-white/5 dark:text-gray-200 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-slate-700">
            <i class="bi bi-activity mr-1"></i>System Monitor
        </a>
        <a href="{{ route('admin.system.readiness') }}"
           class="px-4 py-2 border border-gray-200 dark:border-white/5 dark:text-gray-200 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-slate-700">
            <i class="bi bi-rocket-takeoff mr-1"></i>Readiness
        </a>
        <form action="{{ route('admin.system.capacity.refresh') }}" method="POST" class="inline">
            @csrf
            <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
                <i class="bi bi-arrow-clockwise mr-1"></i>รีเฟรช
            </button>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">
        <i class="bi bi-check-circle mr-1"></i>{{ session('success') }}
    </div>
@endif

{{-- ═══ Top-line KPIs ═══════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    {{-- Safe concurrent capacity --}}
    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-xl p-4 shadow-lg shadow-indigo-500/20">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[0.7rem] uppercase tracking-wider opacity-80">รองรับพร้อมกัน (ปลอดภัย)</span>
            <i class="bi bi-shield-check text-lg opacity-70"></i>
        </div>
        <div class="text-3xl font-bold font-mono">{{ number_format($capacity['safe_concurrent']) }}</div>
        <div class="text-xs opacity-80 mt-1">สูงสุด {{ number_format($capacity['recommended_max']) }} / เผื่อ {{ $capacity['profile']['safety_headroom_pct'] }}%</div>
    </div>

    {{-- Bottleneck --}}
    @php
        $bt = $capacity['bottleneck_tier'];
        $btLabel = $tierLabels[$bt]['label'] ?? $bt;
        $btIcon  = $tierLabels[$bt]['icon']  ?? 'bi-exclamation-triangle';
    @endphp
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[0.7rem] uppercase tracking-wider text-gray-500 dark:text-gray-400">Bottleneck</span>
            <i class="bi {{ $btIcon }} text-lg text-rose-500"></i>
        </div>
        <div class="text-2xl font-bold text-rose-600 dark:text-rose-300 truncate">{{ $btLabel }}</div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">จะเต็มที่ {{ number_format($capacity['bottleneck_value']) }} คน</div>
    </div>

    {{-- Currently online --}}
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[0.7rem] uppercase tracking-wider text-gray-500 dark:text-gray-400">ออนไลน์ตอนนี้</span>
            <i class="bi bi-broadcast-pin text-lg text-emerald-500"></i>
        </div>
        <div class="text-3xl font-bold text-emerald-600 dark:text-emerald-300 font-mono">{{ number_format($load['online_users']) }}</div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            @if($capacity['safe_concurrent'] > 0)
                ใช้ {{ round($load['online_users'] / $capacity['safe_concurrent'] * 100, 1) }}% ของ capacity
            @else
                n/a
            @endif
        </div>
    </div>

    {{-- Days until capacity --}}
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[0.7rem] uppercase tracking-wider text-gray-500 dark:text-gray-400">จะเต็มในอีก</span>
            <i class="bi bi-calendar-event text-lg text-amber-500"></i>
        </div>
        @if($growth['days_until_capacity'] !== null)
            <div class="text-3xl font-bold font-mono
                {{ $growth['days_until_capacity'] < 30 ? 'text-rose-600 dark:text-rose-300' : ($growth['days_until_capacity'] < 90 ? 'text-amber-600 dark:text-amber-300' : 'text-slate-700 dark:text-gray-100') }}">
                {{ number_format($growth['days_until_capacity']) }}<span class="text-base font-normal ml-1">วัน</span>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">≈ {{ $growth['projected_hit_date'] }}</div>
        @else
            <div class="text-3xl font-bold text-slate-400">—</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">ข้อมูลไม่พอคำนวณ</div>
        @endif
    </div>
</div>

{{-- ═══ Server Specs ════════════════════════════════════════════════ --}}
<div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-5 mb-4">
    <h5 class="font-semibold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
        <i class="bi bi-hdd-rack text-indigo-500"></i>สเป็คเซิร์ฟเวอร์ / Hardware
    </h5>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <div class="border border-gray-100 dark:border-white/5 rounded-lg p-3">
            <div class="text-[0.65rem] uppercase tracking-wide text-gray-500 dark:text-gray-400">CPU Cores</div>
            <div class="text-xl font-bold font-mono mt-1"><i class="bi bi-cpu text-amber-500 mr-1"></i>{{ $specs['cpu_cores'] }}</div>
        </div>
        <div class="border border-gray-100 dark:border-white/5 rounded-lg p-3">
            <div class="text-[0.65rem] uppercase tracking-wide text-gray-500 dark:text-gray-400">RAM</div>
            <div class="text-xl font-bold font-mono mt-1"><i class="bi bi-memory text-rose-500 mr-1"></i>{{ $specs['total_ram_gb'] }} <span class="text-xs font-normal text-gray-500">GB</span></div>
        </div>
        <div class="border border-gray-100 dark:border-white/5 rounded-lg p-3">
            <div class="text-[0.65rem] uppercase tracking-wide text-gray-500 dark:text-gray-400">Disk</div>
            <div class="text-xl font-bold font-mono mt-1">
                <i class="bi bi-device-hdd text-{{ $specs['disk_used_pct'] > 85 ? 'rose' : 'sky' }}-500 mr-1"></i>{{ $specs['disk_free_gb'] }}<span class="text-xs font-normal text-gray-500">/{{ $specs['disk_total_gb'] }} GB</span>
            </div>
            <div class="text-[0.65rem] {{ $specs['disk_used_pct'] > 85 ? 'text-rose-500' : 'text-gray-500 dark:text-gray-400' }} mt-0.5">ใช้ {{ $specs['disk_used_pct'] }}%</div>
        </div>
        <div class="border border-gray-100 dark:border-white/5 rounded-lg p-3">
            <div class="text-[0.65rem] uppercase tracking-wide text-gray-500 dark:text-gray-400">PHP</div>
            <div class="text-xl font-bold font-mono mt-1"><i class="bi bi-filetype-php text-indigo-500 mr-1"></i>{{ $specs['php_version'] }}</div>
            <div class="text-[0.65rem] text-gray-500 dark:text-gray-400 mt-0.5">limit: {{ $specs['php_memory_limit_mb'] }} MB</div>
        </div>
        <div class="border border-gray-100 dark:border-white/5 rounded-lg p-3">
            <div class="text-[0.65rem] uppercase tracking-wide text-gray-500 dark:text-gray-400">MySQL</div>
            <div class="text-xl font-bold font-mono mt-1"><i class="bi bi-database text-emerald-500 mr-1"></i>{{ $specs['mysql_max_conn'] }}</div>
            <div class="text-[0.65rem] text-gray-500 dark:text-gray-400 mt-0.5">max_connections</div>
        </div>
        <div class="border border-gray-100 dark:border-white/5 rounded-lg p-3">
            <div class="text-[0.65rem] uppercase tracking-wide text-gray-500 dark:text-gray-400">OS</div>
            <div class="text-xl font-bold font-mono mt-1"><i class="bi bi-{{ strtolower($specs['os']) === 'windows' ? 'windows' : ($specs['os'] === 'Darwin' ? 'apple' : 'ubuntu') }} text-sky-500 mr-1"></i>{{ $specs['os'] }}</div>
            <div class="text-[0.65rem] text-gray-500 dark:text-gray-400 mt-0.5 truncate" title="{{ $specs['hostname'] }}">{{ $specs['hostname'] }}</div>
        </div>
    </div>
    <div class="mt-3 pt-3 border-t border-gray-100 dark:border-white/5 grid grid-cols-2 md:grid-cols-5 gap-2 text-xs">
        <div><span class="text-gray-500 dark:text-gray-400">Laravel:</span> <span class="font-mono text-slate-700 dark:text-gray-200">{{ $specs['laravel_version'] }}</span></div>
        <div><span class="text-gray-500 dark:text-gray-400">Cache:</span> <span class="font-mono text-slate-700 dark:text-gray-200">{{ $specs['cache_driver'] }}</span> {!! $specs['cache_driver'] === 'file' ? '<i class="bi bi-exclamation-triangle text-amber-500" title="ควรใช้ Redis"></i>' : '' !!}</div>
        <div><span class="text-gray-500 dark:text-gray-400">Queue:</span> <span class="font-mono text-slate-700 dark:text-gray-200">{{ $specs['queue_driver'] }}</span></div>
        <div><span class="text-gray-500 dark:text-gray-400">Session:</span> <span class="font-mono text-slate-700 dark:text-gray-200">{{ $specs['session_driver'] }}</span> {!! $specs['session_driver'] === 'file' ? '<i class="bi bi-exclamation-triangle text-amber-500" title="ควรใช้ Redis/Database"></i>' : '' !!}</div>
        <div><span class="text-gray-500 dark:text-gray-400">OPcache:</span>
            @if($specs['opcache_enabled'])
                <span class="text-emerald-600 dark:text-emerald-400"><i class="bi bi-check-circle-fill"></i> ON {{ $specs['opcache_hit_rate'] ? '('.$specs['opcache_hit_rate'].'%)' : '' }}</span>
            @else
                <span class="text-rose-600 dark:text-rose-400"><i class="bi bi-x-circle-fill"></i> OFF</span>
            @endif
        </div>
    </div>
</div>

{{-- ═══ Live Load Gauges ════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    @php
        $gauges = [
            ['label' => 'CPU',    'value' => $load['cpu_pct'],    'unit' => '%', 'icon' => 'bi-cpu',    'hint' => 'Load avg: ' . ($load['load_avg_1m'] !== null ? number_format($load['load_avg_1m'], 2) : 'n/a')],
            ['label' => 'RAM',    'value' => $load['memory_pct'], 'unit' => '%', 'icon' => 'bi-memory', 'hint' => 'PHP process'],
            ['label' => 'Disk',   'value' => $specs['disk_used_pct'], 'unit' => '%', 'icon' => 'bi-device-hdd', 'hint' => $specs['disk_free_gb'] . ' GB ว่าง'],
            ['label' => 'DB Conn','value' => $specs['mysql_max_conn'] > 0 ? round($load['db_connections'] / $specs['mysql_max_conn'] * 100, 1) : 0,
             'unit' => '%', 'icon' => 'bi-database', 'hint' => $load['db_connections'] . ' / ' . $specs['mysql_max_conn']],
        ];
    @endphp
    @foreach($gauges as $g)
        @php $color = $gaugeColor((float) ($g['value'] ?? 0)); @endphp
        <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-600 dark:text-gray-300"><i class="bi {{ $g['icon'] }} mr-1"></i>{{ $g['label'] }}</span>
                <span class="text-xs text-gray-400">{{ $g['hint'] }}</span>
            </div>
            <div class="flex items-baseline gap-1 mb-2">
                <span class="text-2xl font-bold font-mono text-{{ $color }}-600 dark:text-{{ $color }}-300">
                    {{ $g['value'] !== null ? $g['value'] : '—' }}
                </span>
                <span class="text-sm text-gray-400">{{ $g['unit'] }}</span>
            </div>
            <div class="h-1.5 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-{{ $color }}-400 to-{{ $color }}-600 rounded-full transition-all"
                     style="width: {{ min(100, max(2, (float)($g['value'] ?? 0))) }}%"></div>
            </div>
        </div>
    @endforeach
</div>

{{-- ═══ What-If Calculator ══════════════════════════════════════════ --}}
<div class="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-500/10 dark:to-purple-500/10 border border-indigo-100 dark:border-indigo-500/30 rounded-2xl p-5 mb-4">
    <h5 class="font-semibold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
        <i class="bi bi-calculator text-indigo-500"></i>What-if Calculator
        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">— ทดสอบว่าจะเกิดอะไรถ้ามีผู้ใช้ N คนพร้อมกัน</span>
    </h5>

    <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-indigo-700 dark:text-indigo-300 mb-1">
                <i class="bi bi-people mr-1"></i>จำนวนผู้ใช้พร้อมกัน (target)
            </label>
            <input type="number" name="target" min="0" value="{{ $target }}"
                   placeholder="เช่น 1000"
                   class="w-full border border-indigo-200 dark:border-indigo-500/30 dark:bg-slate-800 dark:text-gray-100 rounded-lg px-3 py-2 text-sm font-mono">
            <div class="flex flex-wrap gap-1 mt-1.5">
                @foreach($presets as $p)
                    <a href="?{{ http_build_query(array_merge(request()->query(), ['target' => $p['value']])) }}"
                       class="text-[0.65rem] px-2 py-0.5 rounded-full border border-indigo-200 dark:border-indigo-500/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20">
                        {{ $p['label'] }}
                    </a>
                @endforeach
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-indigo-700 dark:text-indigo-300 mb-1" title="Requests/user/minute">Req/ผู้ใช้/นาที</label>
            <input type="number" name="req_per_user" min="1" max="200" value="{{ $profile['avg_req_per_user_per_min'] ?? '' }}" placeholder="15"
                   class="w-full border border-indigo-200 dark:border-indigo-500/30 dark:bg-slate-800 dark:text-gray-100 rounded-lg px-3 py-2 text-sm font-mono">
        </div>
        <div>
            <label class="block text-xs font-medium text-indigo-700 dark:text-indigo-300 mb-1" title="Average request duration in milliseconds">Req duration (ms)</label>
            <input type="number" name="req_ms" min="10" max="2000" value="{{ $profile['avg_req_duration_ms'] ?? '' }}" placeholder="150"
                   class="w-full border border-indigo-200 dark:border-indigo-500/30 dark:bg-slate-800 dark:text-gray-100 rounded-lg px-3 py-2 text-sm font-mono">
        </div>
        <div>
            <label class="block text-xs font-medium text-indigo-700 dark:text-indigo-300 mb-1" title="Peak traffic multiplier">Peak × avg</label>
            <input type="number" name="peak_mult" min="1" max="10" value="{{ $profile['peak_multiplier'] ?? '' }}" placeholder="3"
                   class="w-full border border-indigo-200 dark:border-indigo-500/30 dark:bg-slate-800 dark:text-gray-100 rounded-lg px-3 py-2 text-sm font-mono">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">
                <i class="bi bi-lightning-charge mr-1"></i>คำนวณ
            </button>
        </div>
    </form>

    @if($whatIf)
        <div class="mt-4 p-4 rounded-xl bg-white dark:bg-slate-900/50 border {{ $whatIf['exceeds_max'] ? 'border-rose-300 dark:border-rose-500/50' : ($whatIf['reaches_limit'] ? 'border-amber-300 dark:border-amber-500/50' : 'border-emerald-300 dark:border-emerald-500/50') }}">
            <div class="flex items-start gap-3 mb-3">
                @if($whatIf['exceeds_max'])
                    <i class="bi bi-exclamation-octagon-fill text-rose-500 text-2xl"></i>
                    <div>
                        <div class="font-bold text-rose-700 dark:text-rose-300">❌ เกินความสามารถของเซิร์ฟเวอร์</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">ตอนนี้รับได้ประมาณ {{ number_format($whatIf['capacity_now']) }} คน (ปลอดภัย {{ number_format($whatIf['safe_now']) }} คน) — ต้อง scale ก่อน</div>
                    </div>
                @elseif($whatIf['reaches_limit'])
                    <i class="bi bi-exclamation-triangle-fill text-amber-500 text-2xl"></i>
                    <div>
                        <div class="font-bold text-amber-700 dark:text-amber-300">⚠ ใกล้ขีดจำกัด</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">เกินเลข safe ({{ number_format($whatIf['safe_now']) }}) แต่ยังไม่เกิน max ({{ number_format($whatIf['capacity_now']) }}) — เสี่ยงถ้ามี peak</div>
                    </div>
                @else
                    <i class="bi bi-check-circle-fill text-emerald-500 text-2xl"></i>
                    <div>
                        <div class="font-bold text-emerald-700 dark:text-emerald-300">✓ อยู่ในเกณฑ์ปลอดภัย</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">รับได้สบาย (safe cap = {{ number_format($whatIf['safe_now']) }} คน)</div>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-3">
                @foreach($whatIf['utilization'] as $tier => $u)
                    @php
                        $label = $tierLabels[$tier]['label'] ?? $tier;
                        $icon  = $tierLabels[$tier]['icon']  ?? 'bi-bar-chart';
                        $cls   = $statusClasses[$u['status']];
                    @endphp
                    <div class="flex items-center gap-3 p-2 rounded-lg bg-gray-50 dark:bg-slate-800/50">
                        <i class="bi {{ $icon }} text-lg {{ $cls['text'] }}"></i>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-medium text-slate-700 dark:text-gray-200 truncate">{{ $label }}</span>
                                <span class="font-mono {{ $cls['text'] }}">{{ $u['pct'] }}%</span>
                            </div>
                            <div class="h-1.5 bg-gray-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r {{ $cls['bar'] }}" style="width: {{ min(100, $u['pct']) }}%"></div>
                            </div>
                            <div class="text-[0.65rem] text-gray-500 dark:text-gray-400 mt-0.5">cap: {{ number_format($u['max']) }} คน</div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if(!empty($whatIf['recommendations']))
                <div class="border-t border-gray-200 dark:border-white/10 pt-3 mt-3">
                    <div class="text-xs font-semibold text-slate-700 dark:text-gray-200 mb-2 flex items-center gap-1">
                        <i class="bi bi-lightbulb text-amber-500"></i>คำแนะนำในการ Scale
                    </div>
                    <ul class="space-y-1 text-xs">
                        @foreach($whatIf['recommendations'] as $r)
                            <li class="flex items-start gap-2">
                                <span class="{{ $r['urgent'] ? 'text-rose-500' : 'text-amber-500' }} mt-0.5">→</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ $r['message'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @else
        <p class="text-xs text-indigo-700/70 dark:text-indigo-300/70 mt-3 flex items-center gap-1">
            <i class="bi bi-info-circle"></i>
            ใส่จำนวนผู้ใช้เป้าหมายเพื่อดูว่าจะเกิดอะไรขึ้น — หรือคลิกปุ่ม preset ด้านบน
        </p>
    @endif
</div>

{{-- ═══ Tier Breakdown ══════════════════════════════════════════════ --}}
<div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-5 mb-4">
    <h5 class="font-semibold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
        <i class="bi bi-layers text-emerald-500"></i>ความสามารถตามชั้น / Tier Breakdown
    </h5>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach($capacity['tiers'] as $tier => $max)
            @php
                $label = $tierLabels[$tier]['label'] ?? $tier;
                $icon  = $tierLabels[$tier]['icon']  ?? 'bi-box';
                $color = $tierLabels[$tier]['color'] ?? 'slate';
                $isBottleneck = $tier === $capacity['bottleneck_tier'];
            @endphp
            <div class="border {{ $isBottleneck ? 'border-rose-300 dark:border-rose-500/50 ring-2 ring-rose-500/20' : 'border-gray-100 dark:border-white/5' }} rounded-xl p-4 relative">
                @if($isBottleneck)
                    <span class="absolute -top-2 -right-2 text-[0.6rem] px-2 py-0.5 rounded-full bg-rose-500 text-white font-bold">BOTTLENECK</span>
                @endif
                <div class="flex items-center gap-2 mb-2">
                    <i class="bi {{ $icon }} text-xl text-{{ $color }}-500"></i>
                    <span class="font-semibold text-slate-700 dark:text-gray-200 text-sm">{{ $label }}</span>
                </div>
                <div class="text-2xl font-bold font-mono text-{{ $color }}-600 dark:text-{{ $color }}-300">{{ number_format($max) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">ผู้ใช้พร้อมกันสูงสุด</div>
            </div>
        @endforeach
    </div>

    {{-- Upload concurrency note --}}
    <div class="mt-4 p-3 rounded-lg bg-gray-50 dark:bg-slate-700/40 border border-gray-100 dark:border-white/5 text-xs text-gray-600 dark:text-gray-300 flex items-start gap-2">
        <i class="bi bi-cloud-upload text-sky-500 mt-0.5"></i>
        <div>
            <strong class="text-slate-700 dark:text-gray-200">รูปภาพ Upload concurrency:</strong>
            รองรับการอัปโหลดพร้อมกันประมาณ <strong class="text-sky-600 dark:text-sky-300 font-mono">{{ number_format($capacity['upload_concurrent']) }}</strong>
            ชุดภาพ (ถือว่าขนาดไฟล์เฉลี่ย {{ $capacity['profile']['avg_photo_size_mb'] }} MB, disk {{ $capacity['profile']['disk_write_mbps'] }} MB/s)
        </div>
    </div>
</div>

{{-- ═══ Growth Projection ═══════════════════════════════════════════ --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    {{-- 90-day trend --}}
    <div class="md:col-span-2 bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-5">
        <h5 class="font-semibold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
            <i class="bi bi-graph-up-arrow text-emerald-500"></i>การเติบโต 90 วัน
        </h5>
        @php
            $max3 = max(1, max($growth['users_last_30d'], $growth['users_prev_30d'], $growth['users_prev2_30d']));
            $bars = [
                ['label' => '0-30 วันที่แล้ว',  'value' => $growth['users_last_30d']],
                ['label' => '30-60 วันที่แล้ว', 'value' => $growth['users_prev_30d']],
                ['label' => '60-90 วันที่แล้ว', 'value' => $growth['users_prev2_30d']],
            ];
        @endphp
        <div class="space-y-2.5">
            @foreach($bars as $b)
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-gray-500 dark:text-gray-400">{{ $b['label'] }}</span>
                        <span class="font-mono font-semibold text-slate-700 dark:text-gray-200">+{{ number_format($b['value']) }} คน</span>
                    </div>
                    <div class="h-2 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-600 rounded-full" style="width: {{ max(2, $b['value']/$max3*100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-3 gap-3 mt-4 pt-4 border-t border-gray-100 dark:border-white/5">
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Photos (30d)</div>
                <div class="text-lg font-bold font-mono text-slate-700 dark:text-gray-100">{{ number_format($growth['photos_last_30d']) }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Orders (30d)</div>
                <div class="text-lg font-bold font-mono text-slate-700 dark:text-gray-100">{{ number_format($growth['orders_last_30d']) }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Events (30d)</div>
                <div class="text-lg font-bold font-mono text-slate-700 dark:text-gray-100">{{ number_format($growth['events_last_30d']) }}</div>
            </div>
        </div>
    </div>

    {{-- Projection box --}}
    <div class="bg-gradient-to-br from-amber-50 to-rose-50 dark:from-amber-500/10 dark:to-rose-500/10 border border-amber-100 dark:border-amber-500/30 rounded-xl p-5">
        <h5 class="font-semibold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
            <i class="bi bi-hourglass-split text-amber-500"></i>เมื่อไหร่จะเต็ม?
        </h5>
        <div class="space-y-2">
            <div class="flex justify-between text-xs">
                <span class="text-gray-600 dark:text-gray-300">ออนไลน์ตอนนี้</span>
                <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-300">{{ number_format($growth['current_online']) }}</span>
            </div>
            <div class="flex justify-between text-xs">
                <span class="text-gray-600 dark:text-gray-300">Safe capacity</span>
                <span class="font-mono font-semibold text-indigo-600 dark:text-indigo-300">{{ number_format($growth['safe_capacity']) }}</span>
            </div>
            <div class="flex justify-between text-xs">
                <span class="text-gray-600 dark:text-gray-300">Slack ว่าง</span>
                <span class="font-mono font-semibold text-slate-700 dark:text-gray-200">{{ number_format($growth['capacity_slack']) }}</span>
            </div>
            <div class="flex justify-between text-xs">
                <span class="text-gray-600 dark:text-gray-300">ผู้ใช้ใหม่/วัน</span>
                <span class="font-mono font-semibold text-slate-700 dark:text-gray-200">{{ $growth['avg_new_users_per_day'] }}</span>
            </div>
            @if($growth['growth_rate_pct'] !== null)
                <div class="flex justify-between text-xs">
                    <span class="text-gray-600 dark:text-gray-300">Growth rate (30d)</span>
                    <span class="font-mono font-semibold {{ $growth['growth_rate_pct'] >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' }}">
                        {{ $growth['growth_rate_pct'] >= 0 ? '+' : '' }}{{ $growth['growth_rate_pct'] }}%
                    </span>
                </div>
            @endif
        </div>
        @if($growth['days_until_capacity'] !== null)
            <div class="mt-4 p-3 rounded-lg bg-white/60 dark:bg-slate-900/30 text-center">
                <div class="text-[0.65rem] uppercase tracking-wider text-gray-500 dark:text-gray-400">คาดว่าจะเต็ม</div>
                <div class="text-2xl font-bold font-mono
                    {{ $growth['days_until_capacity'] < 30 ? 'text-rose-600 dark:text-rose-300' : ($growth['days_until_capacity'] < 90 ? 'text-amber-600 dark:text-amber-300' : 'text-slate-700 dark:text-gray-100') }}">
                    {{ $growth['projected_hit_date'] }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">อีก {{ number_format($growth['days_until_capacity']) }} วัน</div>
            </div>
        @else
            <div class="mt-4 p-3 rounded-lg bg-white/60 dark:bg-slate-900/30 text-center text-xs text-gray-500 dark:text-gray-400">
                ข้อมูลการเติบโตยังน้อยเกินไปสำหรับการคำนวณ
            </div>
        @endif
    </div>
</div>

{{-- ═══ Cost-per-User (tie to Business Expenses) ═══════════════════ --}}
<div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-5 mb-4">
    <div class="flex items-center justify-between mb-3">
        <h5 class="font-semibold text-slate-800 dark:text-gray-100 flex items-center gap-2">
            <i class="bi bi-cash-stack text-rose-500"></i>ต้นทุนต่อผู้ใช้ / Unit Economics
        </h5>
        <a href="{{ route('admin.business-expenses.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
            จัดการค่าใช้จ่าย →
        </a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="border border-gray-100 dark:border-white/5 rounded-lg p-4 text-center">
            <div class="text-[0.65rem] uppercase tracking-wide text-gray-500 dark:text-gray-400">รายจ่ายรวม/เดือน</div>
            <div class="text-2xl font-bold text-rose-500 dark:text-rose-300 font-mono mt-1">{{ number_format($cost['total_monthly'], 0) }}</div>
            <div class="text-[0.65rem] text-gray-400 mt-0.5">{{ number_format($cost['total_yearly'], 0) }} / ปี</div>
        </div>
        <div class="border border-gray-100 dark:border-white/5 rounded-lg p-4 text-center">
            <div class="text-[0.65rem] uppercase tracking-wide text-gray-500 dark:text-gray-400">ต่อผู้ใช้ (Active)</div>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-300 font-mono mt-1">{{ number_format($cost['cost_per_active'], 2) }}</div>
            <div class="text-[0.65rem] text-gray-400 mt-0.5">{{ number_format($cost['active_users']) }} active / {{ number_format($cost['users']) }} ทั้งหมด</div>
        </div>
        <div class="border border-gray-100 dark:border-white/5 rounded-lg p-4 text-center">
            <div class="text-[0.65rem] uppercase tracking-wide text-gray-500 dark:text-gray-400">ต่อรูป</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-300 font-mono mt-1">{{ number_format($cost['cost_per_photo'], 4) }}</div>
            <div class="text-[0.65rem] text-gray-400 mt-0.5">{{ number_format($cost['photos']) }} รูปใน DB</div>
        </div>
        <div class="border border-gray-100 dark:border-white/5 rounded-lg p-4 text-center">
            <div class="text-[0.65rem] uppercase tracking-wide text-gray-500 dark:text-gray-400">ต่อออเดอร์</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-300 font-mono mt-1">{{ number_format($cost['cost_per_order'], 2) }}</div>
            <div class="text-[0.65rem] text-gray-400 mt-0.5">{{ number_format($cost['orders']) }} ออเดอร์</div>
        </div>
    </div>

    {{-- Projection: cost at safe capacity --}}
    @if($capacity['safe_concurrent'] > 0 && $cost['total_monthly'] > 0)
        @php
            // Assume online ≈ 2% of registered users → back-calc registered at full cap
            $estRegisteredAtCap = (int) ($capacity['safe_concurrent'] * 50);
            $costPerUserAtCap = $cost['total_monthly'] / max(1, $estRegisteredAtCap);
        @endphp
        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-white/5 text-xs text-gray-600 dark:text-gray-300">
            <i class="bi bi-info-circle text-indigo-500 mr-1"></i>
            ถ้าใช้งานเต็ม capacity ({{ number_format($capacity['safe_concurrent']) }} concurrent ≈ {{ number_format($estRegisteredAtCap) }} registered users)
            ต้นทุนเฉลี่ย <strong class="font-mono text-emerald-600 dark:text-emerald-300">{{ number_format($costPerUserAtCap, 2) }} บาท/คน/เดือน</strong>
            — ซึ่งเป็น cost floor ถ้า scale ตาม base rate ปัจจุบัน
        </div>
    @endif
</div>

{{-- ═══ Workload Profile (Assumptions) ══════════════════════════════ --}}
<details class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-5 mb-4">
    <summary class="cursor-pointer font-semibold text-slate-800 dark:text-gray-100 flex items-center gap-2">
        <i class="bi bi-sliders text-slate-500"></i>สมมติฐานที่ใช้คำนวณ (Profile)
        <span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-2">คลิกเพื่อดู</span>
    </summary>
    <div class="mt-3 grid grid-cols-2 md:grid-cols-3 gap-3 text-xs">
        @foreach($capacity['profile'] as $k => $v)
            <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-slate-700/40 rounded border border-gray-100 dark:border-white/5">
                <span class="text-gray-600 dark:text-gray-300 font-mono text-[0.7rem]">{{ $k }}</span>
                <span class="font-bold font-mono text-slate-700 dark:text-gray-200">{{ $v }}</span>
            </div>
        @endforeach
    </div>
    <p class="text-[0.7rem] text-gray-500 dark:text-gray-400 mt-3 leading-relaxed">
        <i class="bi bi-info-circle"></i>
        ตัวเลขเป็น "orders of magnitude" — ไม่ใช่ benchmark จริง ถ้าจะ tune แบบแม่นยำแนะนำให้รัน load test (k6, Apache Bench, JMeter).
        ค่า default ใช้ profile ของเว็บไซต์อัลบั้มรูปทั่วไป — ถ้าเว็บหนัก AI/Face search ให้เพิ่ม <code>req_ms</code> หรือลด <code>req_per_user</code>.
    </p>
</details>
@endsection
