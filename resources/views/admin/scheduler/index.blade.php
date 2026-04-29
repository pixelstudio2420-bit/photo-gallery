@extends('layouts.admin')

@section('title', 'Scheduler & Queue Health')

@php
    $maxDay = collect($snapshot['failed']['trend_30d'])->max('count') ?: 1;
@endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h4 class="font-bold mb-1 tracking-tight flex items-center gap-2">
            <i class="bi bi-diagram-3 text-sky-500"></i>
            Scheduler & Queue Health
            <span class="text-xs font-normal text-gray-400 ml-2">/ สถานะงานเบื้องหลัง</span>
        </h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 m-0">
            งานที่ตั้งเวลา / คิวที่รอรัน / jobs ที่ล้มเหลว — ดูทุกอย่างในที่เดียว
        </p>
    </div>
    <div class="text-xs text-gray-400">
        queue connection: <code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-slate-700 text-[11px]">{{ $snapshot['queue_conn'] }}</code>
    </div>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">
        <i class="bi bi-check-circle mr-1"></i>{{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 rounded-xl p-3 mb-4 text-sm">
        <i class="bi bi-exclamation-triangle mr-1"></i>{{ session('error') }}
    </div>
@endif

{{-- ═══ KPI cards ══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
            <i class="bi bi-calendar-event"></i>Scheduled tasks
        </div>
        <div class="text-2xl font-bold mt-1">{{ count($snapshot['scheduler']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
            <i class="bi bi-hourglass-split"></i>Queue pending
        </div>
        <div class="text-2xl font-bold mt-1 {{ $snapshot['queue']['pending'] > 100 ? 'text-amber-500' : '' }}">{{ number_format($snapshot['queue']['pending']) }}</div>
        @if($snapshot['queue']['oldest_age_human'])
            <div class="text-[11px] text-gray-400 mt-1">เก่าสุด: {{ $snapshot['queue']['oldest_age_human'] }}</div>
        @endif
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
            <i class="bi bi-x-circle"></i>Failed (24h)
        </div>
        <div class="text-2xl font-bold mt-1 {{ $snapshot['failed']['last_24h'] > 0 ? 'text-rose-500' : '' }}">{{ number_format($snapshot['failed']['last_24h']) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">7d: {{ number_format($snapshot['failed']['last_7d']) }} · ทั้งหมด: {{ number_format($snapshot['failed']['total']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
            <i class="bi bi-clock"></i>อัพเดตล่าสุด
        </div>
        <div class="text-sm font-semibold mt-2">{{ \Carbon\Carbon::parse($snapshot['generated'])->format('d M H:i:s') }}</div>
        <div class="text-[11px] text-gray-400 mt-1">{{ \Carbon\Carbon::parse($snapshot['generated'])->diffForHumans() }}</div>
    </div>
</div>

{{-- ═══ Scheduled tasks ═══════════════════════════════════════════════ --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 mb-4">
    <div class="p-3 border-b border-gray-100 dark:border-white/5">
        <h5 class="font-semibold text-sm flex items-center gap-2"><i class="bi bi-calendar-event text-indigo-500"></i>Scheduled Tasks</h5>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Command</th>
                    <th class="px-4 py-3">Cron</th>
                    <th class="px-4 py-3">ถัดไป</th>
                    <th class="px-4 py-3">Options</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($snapshot['scheduler'] as $t)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs break-all">{{ $t['command'] }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $t['cron'] }}</td>
                        <td class="px-4 py-3">
                            <div class="text-xs">{{ $t['next_due_human'] ?? '—' }}</div>
                            <div class="text-[10px] text-gray-400">{{ $t['next_due_at'] ? \Carbon\Carbon::parse($t['next_due_at'])->format('d M H:i') : '' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @if($t['without_overlap'])
                                    <span class="px-1.5 py-0.5 rounded bg-sky-500/15 text-sky-600 dark:text-sky-300 text-[10px]">no-overlap</span>
                                @endif
                                @if($t['background'])
                                    <span class="px-1.5 py-0.5 rounded bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 text-[10px]">background</span>
                                @endif
                                @if($t['on_one_server'])
                                    <span class="px-1.5 py-0.5 rounded bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 text-[10px]">one-server</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">ยังไม่มี scheduled tasks</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═══ Queue breakdown ═══════════════════════════════════════════════ --}}
@if(!empty($snapshot['queue']['by_queue']))
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 mb-4">
    <div class="p-3 border-b border-gray-100 dark:border-white/5">
        <h5 class="font-semibold text-sm flex items-center gap-2"><i class="bi bi-hourglass-split text-amber-500"></i>Pending by queue</h5>
    </div>
    <div class="p-3 grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach($snapshot['queue']['by_queue'] as $q)
            <div class="bg-gray-50 dark:bg-slate-900 rounded-lg p-3 border border-gray-100 dark:border-white/5">
                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $q['queue'] }}</div>
                <div class="text-xl font-bold mt-1">{{ number_format($q['count']) }}</div>
                @if($q['oldest_at'])
                    <div class="text-[10px] text-gray-400 mt-1">เก่าสุด: {{ \Carbon\Carbon::parse($q['oldest_at'])->diffForHumans() }}</div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- ═══ Failed jobs trend chart ═══════════════════════════════════════ --}}
@if(!empty($snapshot['failed']['trend_30d']))
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 mb-4">
    <div class="p-3 border-b border-gray-100 dark:border-white/5">
        <h5 class="font-semibold text-sm flex items-center gap-2"><i class="bi bi-graph-up text-rose-500"></i>Failed jobs — 30 days</h5>
    </div>
    <div class="p-4">
        <div class="flex items-end gap-0.5 h-24">
            @foreach($snapshot['failed']['trend_30d'] as $d)
                @php $h = max(2, (int) round($d['count'] / $maxDay * 90)); @endphp
                <div class="flex-1 bg-gradient-to-t from-rose-600 to-rose-400 rounded-t" style="height: {{ $h }}px;"
                     title="{{ $d['date'] }}: {{ $d['count'] }}"></div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- ═══ Failed jobs — recent table ════════════════════════════════════ --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 mb-4">
    <div class="p-3 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
        <h5 class="font-semibold text-sm flex items-center gap-2"><i class="bi bi-x-circle text-rose-500"></i>Failed jobs (recent)</h5>
        <div class="flex gap-2">
            <form action="{{ route('admin.scheduler.retry-all') }}" method="POST" onsubmit="return confirm('Requeue failed jobs ทั้งหมด?')">
                @csrf
                <button class="px-3 py-1 text-xs border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                    <i class="bi bi-arrow-clockwise mr-1"></i>Retry all
                </button>
            </form>
            <form action="{{ route('admin.scheduler.flush-failed') }}" method="POST" onsubmit="return confirm('ล้าง failed_jobs ทั้งหมด? (ลบไม่สามารถกู้คืนได้)')">
                @csrf
                <button class="px-3 py-1 text-xs border border-rose-200 text-rose-700 dark:text-rose-200 dark:border-rose-500/30 rounded hover:bg-rose-50 dark:hover:bg-rose-900/20">
                    <i class="bi bi-trash mr-1"></i>Flush all
                </button>
            </form>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Failed at</th>
                    <th class="px-4 py-3">Queue</th>
                    <th class="px-4 py-3">Exception</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($snapshot['failed']['recent'] as $f)
                    <tr>
                        <td class="px-4 py-3 text-xs whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($f['failed_at'])->format('d M H:i') }}
                            <div class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($f['failed_at'])->diffForHumans() }}</div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $f['queue'] }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300 break-all">{{ $f['exception'] }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <form action="{{ route('admin.scheduler.retry', $f['uuid']) }}" method="POST" class="inline">
                                @csrf
                                <button class="px-2 py-1 text-xs border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </form>
                            <form action="{{ route('admin.scheduler.forget', $f['uuid']) }}" method="POST" class="inline">
                                @csrf @method('DELETE')
                                <button class="px-2 py-1 text-xs border border-rose-200 text-rose-700 dark:text-rose-200 dark:border-rose-500/30 rounded hover:bg-rose-50 dark:hover:bg-rose-900/20">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-emerald-500"><i class="bi bi-check-circle mr-1"></i>ไม่มี failed jobs</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
