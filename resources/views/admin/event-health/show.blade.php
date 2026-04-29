@extends('layouts.admin')
@section('title', 'Health: ' . $event->name)

@php
    $gradeCls = [
        'A' => 'bg-emerald-500 text-white',
        'B' => 'bg-sky-500 text-white',
        'C' => 'bg-amber-500 text-white',
        'D' => 'bg-orange-500 text-white',
        'F' => 'bg-rose-500 text-white',
    ];
@endphp

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-clipboard2-pulse text-green-500"></i>
        {{ $event->name }}
    </h4>
    <a href="{{ route('admin.event-health.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-500">
        <i class="bi bi-arrow-left mr-1"></i>กลับ
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-6 border border-gray-100 dark:border-white/5 text-center">
        <div class="text-xs text-gray-500 dark:text-gray-400">Composite Score</div>
        <div class="text-5xl font-bold mt-2">{{ $score['composite'] }}</div>
        <div class="mt-3">
            <span class="inline-block w-12 h-12 rounded-full {{ $gradeCls[$score['grade']] }} text-2xl font-bold leading-[3rem]">
                {{ $score['grade'] }}
            </span>
        </div>
    </div>
    <div class="md:col-span-2 bg-white dark:bg-slate-800 rounded-xl p-6 border border-gray-100 dark:border-white/5">
        <h5 class="font-semibold mb-3">Breakdown</h5>
        <div class="space-y-2">
            @php
                $bars = [
                    ['label' => 'Moderation approval', 'value' => $score['moderation']['score'], 'color' => 'emerald'],
                    ['label' => 'Dimensions filled', 'value' => $score['dimensions_pct'], 'color' => 'sky'],
                    ['label' => 'Engagement', 'value' => $score['engagement_score'], 'color' => 'indigo'],
                ];
                if ($score['face_enabled']) {
                    $bars[] = ['label' => 'Face coverage', 'value' => $score['face_coverage'], 'color' => 'purple'];
                }
            @endphp
            @foreach($bars as $b)
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span>{{ $b['label'] }}</span>
                        <span class="font-mono">{{ $b['value'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-2">
                        <div class="bg-{{ $b['color'] }}-500 h-2 rounded-full" style="width: {{ min(100, $b['value']) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">รูปทั้งหมด</div>
        <div class="text-xl font-bold">{{ number_format($score['photo_count']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">Approved / Flagged / Rejected</div>
        <div class="text-sm mt-1">{{ $score['moderation']['approved'] }} / <span class="text-amber-500">{{ $score['moderation']['flagged'] }}</span> / <span class="text-rose-500">{{ $score['moderation']['rejected'] }}</span></div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">ขนาดรูปเฉลี่ย</div>
        <div class="text-xl font-bold">{{ $score['avg_size_mb'] }} MB</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">Orders · Downloads</div>
        <div class="text-sm mt-1">{{ $score['orders'] }} · {{ $score['downloads'] }}</div>
        <div class="text-[10px] text-gray-400">{{ $score['orders_per_100'] }} orders / 100 รูป</div>
    </div>
</div>

@if(!empty($score['issues']))
<div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 rounded-xl p-4">
    <h5 class="font-semibold text-rose-700 dark:text-rose-200 mb-2"><i class="bi bi-exclamation-triangle mr-1"></i>ปัญหาที่พบ</h5>
    <ul class="list-disc list-inside text-sm text-rose-700 dark:text-rose-200 space-y-1">
        @foreach($score['issues'] as $issue)
            <li>{{ $issue }}</li>
        @endforeach
    </ul>
</div>
@else
<div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 rounded-xl p-4 text-center">
    <i class="bi bi-check-circle-fill text-emerald-500 text-3xl"></i>
    <div class="mt-2 font-semibold text-emerald-700 dark:text-emerald-200">ไม่พบปัญหา</div>
</div>
@endif
@endsection
