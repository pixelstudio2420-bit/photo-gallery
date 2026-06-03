@extends('layouts.admin')
@section('title', 'Route & Page Health')

@php
    $resultCls = [
        'ok'   => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200 border-emerald-400',
        'warn' => 'bg-amber-500/15 text-amber-700 dark:text-amber-200 border-amber-400',
        'fail' => 'bg-rose-500/15 text-rose-700 dark:text-rose-200 border-rose-400',
    ];
    $summary = $snapshot['summary'] ?? ['ok' => 0, 'warn' => 0, 'fail' => 0, 'slowest_ms' => 0, 'healthy' => null, 'total' => 0];
@endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h4 class="font-bold mb-1 tracking-tight flex items-center gap-2">
            <i class="bi bi-heart-pulse text-rose-500"></i>
            Route &amp; Page Health
            <span class="text-xs font-normal text-gray-400 ml-2">/ ยิงเช็คหน้าเว็บจริง จับ 500</span>
        </h4>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            @if($snapshot && !empty($snapshot['checked_at']))
                ตรวจล่าสุด: {{ \Illuminate\Support\Carbon::parse($snapshot['checked_at'])->diffForHumans() }}
                <span class="text-gray-400">({{ \Illuminate\Support\Carbon::parse($snapshot['checked_at'])->format('d/m/Y H:i') }})</span>
            @else
                ยังไม่เคยตรวจ — กด “ตรวจเดี๋ยวนี้”
            @endif
        </p>
    </div>
    <form method="POST" action="{{ route('admin.health.run') }}">
        @csrf
        <button type="submit"
                class="text-sm px-4 py-2 rounded-lg font-medium text-white"
                style="background:linear-gradient(135deg,#f43f5e,#e11d48);border:none;">
            <i class="bi bi-arrow-repeat mr-1"></i> ตรวจเดี๋ยวนี้
        </button>
    </form>
</div>

@if(session('success'))
    <div class="mb-4 p-3 rounded-lg" style="background:rgba(16,185,129,0.1);color:#059669;border:1px solid rgba(16,185,129,0.2);">
        <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="mb-4 p-3 rounded-lg" style="background:rgba(239,68,68,0.1);color:#dc2626;border:1px solid rgba(239,68,68,0.2);">
        <i class="bi bi-exclamation-triangle mr-1"></i> {{ session('error') }}
    </div>
@endif

{{-- ── Top cards ──────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <div class="rounded-2xl border p-4 {{ ($summary['healthy'] ?? false) ? 'border-emerald-300 dark:border-emerald-500/40' : ($summary['fail'] > 0 ? 'border-rose-300 dark:border-rose-500/40' : 'border-slate-200 dark:border-white/10') }} bg-white dark:bg-slate-800/60">
        <div class="text-[11px] uppercase tracking-wider text-gray-500 font-semibold mb-1">สถานะรวม</div>
        @if($snapshot === null)
            <div class="text-2xl font-bold text-slate-400">—</div>
        @elseif($summary['fail'] > 0)
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-300">{{ $summary['fail'] }} ล้ม</div>
        @else
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-300">ปกติ</div>
        @endif
        <div class="text-[11px] text-gray-500 mt-0.5">{{ $summary['total'] }} เป้าหมาย</div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-white/10 p-4 bg-white dark:bg-slate-800/60">
        <div class="text-[11px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Uptime 30 วัน</div>
        <div class="text-2xl font-bold text-slate-800 dark:text-white tabular-nums">
            {{ $uptime30['uptime_pct'] !== null ? $uptime30['uptime_pct'] . '%' : '—' }}
        </div>
        <div class="text-[11px] text-gray-500 mt-0.5">{{ number_format($uptime30['total']) }} checks · {{ $uptime30['fail'] }} fail</div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-white/10 p-4 bg-white dark:bg-slate-800/60">
        <div class="text-[11px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Uptime 7 วัน</div>
        <div class="text-2xl font-bold text-slate-800 dark:text-white tabular-nums">
            {{ $uptime7['uptime_pct'] !== null ? $uptime7['uptime_pct'] . '%' : '—' }}
        </div>
        <div class="text-[11px] text-gray-500 mt-0.5">{{ number_format($uptime7['total']) }} checks · {{ $uptime7['fail'] }} fail</div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-white/10 p-4 bg-white dark:bg-slate-800/60">
        <div class="text-[11px] uppercase tracking-wider text-gray-500 font-semibold mb-1">ช้าสุดรอบนี้</div>
        <div class="text-2xl font-bold text-slate-800 dark:text-white tabular-nums">{{ $summary['slowest_ms'] }}<span class="text-sm font-normal text-gray-400">ms</span></div>
        <div class="text-[11px] text-gray-500 mt-0.5">{{ $summary['ok'] }} ok · {{ $summary['warn'] }} warn</div>
    </div>
</div>

{{-- ── Route health table ─────────────────────────────────────── --}}
<div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800/60 overflow-hidden mb-5">
    <div class="px-5 pt-4 pb-3 border-b border-slate-100 dark:border-white/5">
        <h6 class="font-semibold text-sm text-slate-900 dark:text-white">
            <i class="bi bi-list-check mr-1 text-rose-500"></i> ผลตรวจรายหน้า
        </h6>
    </div>
    @if($snapshot === null || empty($snapshot['results']))
        <div class="p-8 text-center text-sm text-gray-500">
            <i class="bi bi-inbox text-2xl block mb-2 text-gray-300"></i>
            ยังไม่มีผลตรวจ — กด “ตรวจเดี๋ยวนี้” ด้านบน
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-white/10 text-[11px] uppercase tracking-wider text-gray-500">
                        <th class="text-left pl-5 pr-3 py-2 font-semibold">หน้า</th>
                        <th class="text-left px-3 py-2 font-semibold">Path</th>
                        <th class="text-center px-3 py-2 font-semibold">HTTP</th>
                        <th class="text-right px-3 py-2 font-semibold">เวลา</th>
                        <th class="text-center px-3 py-2 font-semibold">ผล</th>
                        <th class="text-left pr-5 pl-3 py-2 font-semibold">หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @foreach($snapshot['results'] as $r)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 {{ $r['result'] === 'fail' ? 'bg-rose-50/50 dark:bg-rose-500/5' : '' }}">
                            <td class="pl-5 pr-3 py-2.5">
                                <div class="font-medium text-slate-900 dark:text-white text-[13px]">{{ $r['label'] }}</div>
                                <div class="text-[10px] text-gray-400">{{ $r['kind'] }} · {{ $r['key'] }}</div>
                            </td>
                            <td class="px-3 py-2.5">
                                <code class="text-[11px] text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($r['path'], 48) }}</code>
                            </td>
                            <td class="px-3 py-2.5 text-center tabular-nums text-[12px] {{ $r['status'] >= 500 ? 'text-rose-600 font-bold' : ($r['status'] >= 400 ? 'text-amber-600' : 'text-slate-600 dark:text-slate-300') }}">
                                {{ $r['status'] }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-[12px] {{ $r['duration_ms'] > 2000 ? 'text-amber-600 font-semibold' : 'text-slate-500' }}">
                                {{ $r['duration_ms'] }}ms
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full border {{ $resultCls[$r['result']] ?? '' }}">
                                    {{ strtoupper($r['result']) }}
                                </span>
                            </td>
                            <td class="pr-5 pl-3 py-2.5 text-[11px] text-gray-500">{{ $r['error'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    {{-- ── Recent runs timeline ───────────────────────────────── --}}
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800/60 overflow-hidden">
        <div class="px-5 pt-4 pb-3 border-b border-slate-100 dark:border-white/5">
            <h6 class="font-semibold text-sm text-slate-900 dark:text-white">
                <i class="bi bi-clock-history mr-1 text-sky-500"></i> ประวัติการตรวจล่าสุด
            </h6>
        </div>
        @if(empty($recent))
            <div class="p-6 text-center text-sm text-gray-500">ยังไม่มีประวัติ</div>
        @else
            <div class="divide-y divide-slate-100 dark:divide-white/5 max-h-80 overflow-y-auto">
                @foreach($recent as $run)
                    <div class="flex items-center justify-between px-5 py-2.5">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-block w-2.5 h-2.5 rounded-full {{ $run['result'] === 'fail' ? 'bg-rose-500' : ($run['result'] === 'warn' ? 'bg-amber-400' : 'bg-emerald-500') }}"></span>
                            <span class="text-[12px] text-slate-700 dark:text-slate-200">
                                {{ \Illuminate\Support\Carbon::parse($run['checked_at'])->format('d/m H:i') }}
                            </span>
                        </div>
                        <div class="text-[11px] text-gray-500 tabular-nums">
                            @if($run['fail'] > 0)<span class="text-rose-600 font-semibold">{{ $run['fail'] }} fail</span> · @endif
                            @if($run['warn'] > 0)<span class="text-amber-600">{{ $run['warn'] }} warn</span> · @endif
                            {{ $run['total'] }} checks · {{ $run['slowest_ms'] }}ms
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Related monitors (link out, don't duplicate) ───────── --}}
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800/60 overflow-hidden">
        <div class="px-5 pt-4 pb-3 border-b border-slate-100 dark:border-white/5">
            <h6 class="font-semibold text-sm text-slate-900 dark:text-white">
                <i class="bi bi-diagram-3 mr-1 text-indigo-500"></i> มอนิเตอร์อื่นที่เกี่ยวข้อง
            </h6>
            <p class="text-[10px] text-gray-400 mt-0.5">หน้านี้ดู “หน้าเว็บใช้งานได้ไหม” — ส่วนอื่นดู infra / cron / event</p>
        </div>
        <div class="p-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
            @php
                $links = [
                    ['admin.system.dashboard',  'bi-activity',          'System Monitor',     'server / db / cache / queue'],
                    ['admin.system.readiness',  'bi-rocket-takeoff',    'Production Readiness','pre-launch checklist'],
                    ['admin.scheduler.index',   'bi-diagram-3',         'Scheduler & Queue',  'cron ทำงานครบไหม'],
                    ['admin.event-health.index','bi-clipboard2-pulse',  'Event Health',       'คุณภาพรายงาน event'],
                    ['admin.system.capacity',   'bi-speedometer2',      'Capacity Planner',   'รองรับ user ได้เท่าไร'],
                    ['admin.alerts.index',      'bi-bell-fill',         'Alert Rules',        'กฎเตือนอัตโนมัติ'],
                ];
            @endphp
            @foreach($links as [$route, $icon, $title, $desc])
                @if(\Illuminate\Support\Facades\Route::has($route))
                    <a href="{{ route($route) }}"
                       class="flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-100 dark:border-white/5 hover:border-indigo-300 dark:hover:border-indigo-500/40 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                        <i class="bi {{ $icon }} text-indigo-500 mt-0.5"></i>
                        <div class="min-w-0">
                            <div class="text-[12px] font-semibold text-slate-800 dark:text-white">{{ $title }}</div>
                            <div class="text-[10px] text-gray-400 truncate">{{ $desc }}</div>
                        </div>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>

<p class="text-[11px] text-gray-400 mt-4">
    <i class="bi bi-info-circle mr-1"></i>
    ตรวจอัตโนมัติทุกวัน 06:10 น. (<code>routes:health</code>) — ถ้าเจอ 5xx จะแจ้งเตือนที่กระดิ่งแอดมินทันที
</p>
@endsection
