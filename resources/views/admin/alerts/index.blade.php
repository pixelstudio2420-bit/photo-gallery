@extends('layouts.admin')

@section('title', 'Alert Rules')

@php
    $sevBadge = [
        'info'     => 'bg-sky-500/15 text-sky-600 dark:text-sky-300 border-sky-300/40',
        'warn'     => 'bg-amber-500/15 text-amber-600 dark:text-amber-300 border-amber-300/40',
        'critical' => 'bg-rose-500/15 text-rose-600 dark:text-rose-300 border-rose-300/40',
    ];
@endphp

@section('content')
{{-- ═══ Header ═════════════════════════════════════════════════════ --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h4 class="font-bold mb-1 tracking-tight flex items-center gap-2">
            <i class="bi bi-bell-fill text-rose-500"></i>
            Alert Rules Engine
            <span class="text-xs font-normal text-gray-400 ml-2">/ ระบบแจ้งเตือนตามเงื่อนไข</span>
        </h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 m-0">
            ตั้ง rule จาก metric ของระบบ — ถ้าเกิน/ต่ำกว่า threshold จะแจ้งเตือนผ่าน Admin/Email/LINE/Push
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.alerts.events') }}"
           class="px-4 py-2 border border-gray-200 dark:border-white/5 dark:text-gray-200 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-slate-700">
            <i class="bi bi-clock-history mr-1"></i>ประวัติการแจ้งเตือน
        </a>
        <form action="{{ route('admin.alerts.run-now') }}" method="POST" class="inline">
            @csrf
            <button class="px-4 py-2 border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded-lg text-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                <i class="bi bi-arrow-clockwise mr-1"></i>ตรวจสอบทันที
            </button>
        </form>
        <a href="{{ route('admin.alerts.create') }}"
           class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
            <i class="bi bi-plus-lg mr-1"></i>เพิ่ม Rule
        </a>
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

{{-- ═══ Stats ═════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">Rules ทั้งหมด</div>
        <div class="text-2xl font-bold mt-1">{{ $stats['total'] }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">เปิดใช้งาน</div>
        <div class="text-2xl font-bold mt-1 text-emerald-600 dark:text-emerald-300">{{ $stats['active'] }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">กำลัง firing</div>
        <div class="text-2xl font-bold mt-1 {{ ($stats['firing'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-300' : '' }}">{{ $stats['firing'] ?? 0 }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">ตรงเงื่อนไขอยู่ตอนนี้</div>
        <div class="text-2xl font-bold mt-1 {{ $stats['triggering'] > 0 ? 'text-rose-600 dark:text-rose-300' : '' }}">{{ $stats['triggering'] }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">เหตุการณ์ 24 ชั่วโมง</div>
        <div class="text-2xl font-bold mt-1">{{ $stats['events_24h'] }}</div>
    </div>
</div>

{{-- ═══ State Machine Info Banner ═════════════════════════════════════ --}}
<div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-500/30 rounded-xl p-3 mb-4 text-xs text-indigo-800 dark:text-indigo-200 flex items-start gap-2">
    <i class="bi bi-info-circle mt-0.5"></i>
    <div>
        <strong>Fire-once-per-episode:</strong> ถ้า rule เริ่ม firing ระบบจะแจ้งเตือน <em>ครั้งเดียว</em> จนกว่าค่าจะกลับมาต่ำกว่า threshold (auto-resolve)
        หรือกดปุ่ม <i class="bi bi-check2-circle"></i> "รับทราบ" เพื่อเคลียร์ state ด้วยตัวเอง (แจ้งเตือนในกระดิ่งจะถูกล้างอัตโนมัติ)
    </div>
</div>

{{-- ═══ Rules table ═══════════════════════════════════════════════════ --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">ชื่อ / Metric</th>
                    <th class="px-4 py-3">State</th>
                    <th class="px-4 py-3">เงื่อนไข</th>
                    <th class="px-4 py-3">ค่า ณ ตอนนี้</th>
                    <th class="px-4 py-3">Severity</th>
                    <th class="px-4 py-3">Channels</th>
                    <th class="px-4 py-3">Cooldown</th>
                    <th class="px-4 py-3">ล่าสุด</th>
                    <th class="px-4 py-3 text-right">การจัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($rules as $r)
                    @php
                        $m = $metrics[$r->metric] ?? ['label' => $r->metric, 'unit' => ''];
                        $sevCls = $sevBadge[$r->severity] ?? $sevBadge['info'];
                    @endphp
                    <tr class="{{ $r->firing ? 'bg-rose-50/70 dark:bg-rose-900/20' : ($r->would_trigger ? 'bg-amber-50/50 dark:bg-amber-900/10' : '') }}">
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $r->name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $m['label'] }}</div>
                            @if($r->description)
                                <div class="text-[11px] text-gray-400 mt-0.5">{{ $r->description }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($r->firing)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] bg-rose-500/15 text-rose-600 dark:text-rose-300 border-rose-300/40 animate-pulse">
                                    <i class="bi bi-broadcast mr-1"></i>FIRING
                                </span>
                                @if($r->last_triggered_at)
                                    <div class="text-[10px] text-gray-400 mt-1" title="{{ $r->last_triggered_at }}">ตั้งแต่ {{ $r->last_triggered_at->diffForHumans() }}</div>
                                @endif
                            @elseif($r->resolved_at)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 border-emerald-300/40">
                                    <i class="bi bi-check2-circle mr-1"></i>Resolved
                                </span>
                                <div class="text-[10px] text-gray-400 mt-1" title="{{ $r->resolved_at }}">{{ $r->resolved_at->diffForHumans() }}</div>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-300 border-gray-200 dark:border-white/10">
                                    <i class="bi bi-circle mr-1"></i>Idle
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">
                            {{ $r->operator }} {{ rtrim(rtrim(number_format((float) $r->threshold, 2), '0'), '.') }} {{ $m['unit'] }}
                        </td>
                        <td class="px-4 py-3">
                            @if($r->current_value === null)
                                <span class="text-gray-400 text-xs">—</span>
                            @else
                                <span class="{{ $r->would_trigger ? 'text-rose-600 dark:text-rose-300 font-semibold' : '' }}">
                                    {{ rtrim(rtrim(number_format($r->current_value, 2), '0'), '.') }} {{ $m['unit'] }}
                                </span>
                                @if($r->would_trigger)
                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-rose-500/15 text-rose-600 dark:text-rose-300">FIRING</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] {{ $sevCls }}">
                                {{ $severities[$r->severity] ?? $r->severity }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach(($r->channels ?? []) as $ch)
                                    @php $cfg = $channelOptions[$ch] ?? ['label' => $ch, 'icon' => 'bi-circle']; @endphp
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 dark:bg-slate-700 text-[11px]">
                                        <i class="bi {{ $cfg['icon'] }} mr-0.5"></i>{{ $cfg['label'] }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            {{ $r->cooldown_minutes }} นาที
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            @if($r->last_triggered_at)
                                <div title="{{ $r->last_triggered_at }}">{{ $r->last_triggered_at->diffForHumans() }}</div>
                            @else
                                <span class="text-gray-400">ยังไม่เคย</span>
                            @endif
                            @if($r->last_checked_at)
                                <div class="text-[10px] text-gray-400">check: {{ $r->last_checked_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if($r->firing)
                                <form action="{{ route('admin.alerts.acknowledge', $r) }}" method="POST" class="inline"
                                      onsubmit="return confirm('รับทราบและเคลียร์ firing state? (แจ้งเตือนในกระดิ่งจะถูกล้างด้วย)')">
                                    @csrf
                                    <button class="px-2 py-1 text-xs border border-emerald-300 text-emerald-700 dark:text-emerald-200 dark:border-emerald-500/30 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900/20" title="รับทราบ / เคลียร์ firing">
                                        <i class="bi bi-check2-circle"></i>
                                    </button>
                                </form>
                            @endif
                            <form action="{{ route('admin.alerts.test', $r) }}" method="POST" class="inline">
                                @csrf
                                <button class="px-2 py-1 text-xs border border-amber-300 text-amber-700 dark:text-amber-200 dark:border-amber-500/30 rounded hover:bg-amber-50 dark:hover:bg-amber-900/20" title="ส่งทดสอบ">
                                    <i class="bi bi-send"></i>
                                </button>
                            </form>
                            <form action="{{ route('admin.alerts.toggle', $r) }}" method="POST" class="inline">
                                @csrf
                                <button class="px-2 py-1 text-xs border rounded {{ $r->is_active ? 'border-emerald-300 text-emerald-700 dark:text-emerald-200 dark:border-emerald-500/30' : 'border-gray-300 text-gray-600 dark:text-gray-300 dark:border-white/10' }} hover:bg-gray-50 dark:hover:bg-slate-700" title="{{ $r->is_active ? 'ปิด' : 'เปิด' }}">
                                    <i class="bi {{ $r->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                </button>
                            </form>
                            <a href="{{ route('admin.alerts.edit', $r) }}" class="inline-block px-2 py-1 text-xs border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('admin.alerts.destroy', $r) }}" method="POST" class="inline" onsubmit="return confirm('ลบ rule นี้?')">
                                @csrf @method('DELETE')
                                <button class="px-2 py-1 text-xs border border-rose-200 text-rose-700 dark:text-rose-200 dark:border-rose-500/30 rounded hover:bg-rose-50 dark:hover:bg-rose-900/20">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                            ยังไม่มี rule — กด "เพิ่ม Rule" ด้านบน หรือรัน <code class="bg-gray-100 dark:bg-slate-700 px-1 rounded">php artisan db:seed --class=AlertRuleSeeder</code>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
