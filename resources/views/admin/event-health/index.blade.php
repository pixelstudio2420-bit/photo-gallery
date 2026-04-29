@extends('layouts.admin')
@section('title', 'Event Health Scorecard')

@php
    $gradeCls = [
        'A' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200 border-emerald-400',
        'B' => 'bg-sky-500/15 text-sky-700 dark:text-sky-200 border-sky-400',
        'C' => 'bg-amber-500/15 text-amber-700 dark:text-amber-200 border-amber-400',
        'D' => 'bg-orange-500/15 text-orange-700 dark:text-orange-200 border-orange-400',
        'F' => 'bg-rose-500/15 text-rose-700 dark:text-rose-200 border-rose-400',
    ];
@endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h4 class="font-bold mb-1 tracking-tight flex items-center gap-2">
            <i class="bi bi-clipboard2-pulse text-green-500"></i>
            Event Health Scorecard
            <span class="text-xs font-normal text-gray-400 ml-2">/ คุณภาพรายงาน</span>
        </h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 m-0">
            ให้คะแนนแต่ละ event ตาม moderation · dimensions · face_id coverage · engagement → ช่วยหา event ที่มีปัญหา
        </p>
    </div>
    <form method="GET" class="flex gap-2">
        <select name="status" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            <option value="">— ทุกสถานะ —</option>
            <option value="active" @selected($status === 'active')>active</option>
            <option value="draft" @selected($status === 'draft')>draft</option>
            <option value="archived" @selected($status === 'archived')>archived</option>
        </select>
        <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
            <i class="bi bi-funnel"></i>
        </button>
    </form>
</div>

<div class="grid grid-cols-5 gap-2 mb-4">
    @foreach(['A', 'B', 'C', 'D', 'F'] as $g)
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border-l-4 {{ $gradeCls[$g] }}">
            <div class="text-xs">Grade {{ $g }}</div>
            <div class="text-2xl font-bold mt-1">{{ $byGrade[$g] ?? 0 }}</div>
        </div>
    @endforeach
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Event</th>
                    <th class="px-4 py-3 text-center">Grade</th>
                    <th class="px-4 py-3 text-right">Score</th>
                    <th class="px-4 py-3 text-right">รูป</th>
                    <th class="px-4 py-3 text-right">Moderation</th>
                    <th class="px-4 py-3 text-right">Face</th>
                    <th class="px-4 py-3 text-right">Dims</th>
                    <th class="px-4 py-3 text-right">Orders / 100</th>
                    <th class="px-4 py-3">ปัญหา</th>
                    <th class="px-4 py-3 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($rows as $r)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $r['name'] }}</div>
                            <div class="text-[11px] text-gray-400">
                                {{ $r['shoot_date'] }} · {{ $r['status'] }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-block w-8 h-8 rounded-full {{ $gradeCls[$r['grade']] }} font-bold text-center leading-8 border">
                                {{ $r['grade'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-semibold">{{ $r['composite'] }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($r['photo_count']) }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="{{ $r['moderation']['score'] >= 90 ? 'text-emerald-600' : ($r['moderation']['score'] < 70 ? 'text-rose-500' : '') }}">
                                {{ $r['moderation']['score'] }}%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($r['face_enabled'])
                                {{ $r['face_coverage'] }}%
                            @else
                                <span class="text-gray-400 text-[11px]">disabled</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">{{ $r['dimensions_pct'] }}%</td>
                        <td class="px-4 py-3 text-right">{{ $r['orders_per_100'] }}</td>
                        <td class="px-4 py-3 text-xs text-rose-500">
                            @if(!empty($r['issues']))
                                {{ count($r['issues']) }} ปัญหา
                            @else
                                <span class="text-emerald-500">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.event-health.show', $r['event_id']) }}" class="px-2 py-1 text-xs border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-10 text-center text-gray-400">ไม่มี event</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
