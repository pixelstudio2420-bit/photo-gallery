@extends('layouts.admin')
@section('title', 'Quality: ' . $event->name)

@php
    $thumb = function($p) {
        if ($p->thumbnail_path) return \Illuminate\Support\Facades\Storage::disk($p->storage_disk ?? 'public')->url($p->thumbnail_path);
        if ($p->original_path) return \Illuminate\Support\Facades\Storage::disk($p->storage_disk ?? 'public')->url($p->original_path);
        return $p->thumbnail_link ?? '';
    };
    $scoreCls = fn ($s) =>
        $s === null ? 'bg-gray-200 text-gray-600'
        : ($s >= 75 ? 'bg-emerald-500/90 text-white'
        : ($s >= 40 ? 'bg-amber-500/90 text-white'
        : 'bg-rose-500/90 text-white'));
@endphp

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h4 class="font-bold tracking-tight flex items-center gap-2">
            <i class="bi bi-stars text-indigo-500"></i> {{ $event->name }}
        </h4>
        <div class="text-xs text-gray-400 font-mono">{{ $event->slug ?? '#' . $event->id }}</div>
    </div>
    <div class="flex items-center gap-2">
        <form action="{{ route('admin.photo-quality.rescore-event', $event) }}" method="POST">
            @csrf
            <button class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
                <i class="bi bi-arrow-repeat mr-1"></i>คำนวณใหม่
            </button>
        </form>
        <a href="{{ route('admin.photo-quality.index') }}" class="text-sm text-gray-500 hover:text-indigo-500">
            <i class="bi bi-arrow-left mr-1"></i>กลับ
        </a>
    </div>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif

<div class="mb-6">
    <h5 class="font-bold text-sm mb-2">
        <i class="bi bi-trophy text-amber-500 mr-1"></i>Top 24 (รูปคุณภาพสูงสุด)
    </h5>
    @if($top->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl p-6 border border-gray-100 dark:border-white/5 text-center text-gray-400 text-sm">
            ยังไม่มีคะแนน — กด "คำนวณใหม่"
        </div>
    @else
        <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2">
            @foreach($top as $p)
                <div class="relative group aspect-square overflow-hidden rounded-lg bg-slate-900/5">
                    <img src="{{ $thumb($p) }}" class="w-full h-full object-cover" loading="lazy" alt="">
                    <span class="absolute top-1 left-1 text-[10px] font-bold font-mono px-1.5 py-0.5 rounded {{ $scoreCls($p->quality_score) }}">
                        #{{ $p->rank_position }}
                    </span>
                    <span class="absolute bottom-1 right-1 text-[10px] font-bold font-mono px-1.5 py-0.5 rounded bg-black/70 text-white">
                        {{ number_format((float) $p->quality_score, 1) }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>

<div>
    <h5 class="font-bold text-sm mb-2">
        <i class="bi bi-exclamation-triangle text-rose-500 mr-1"></i>คุณภาพต่ำ (&lt;40) — เสนอลบ
    </h5>
    @if($bottom->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl p-6 border border-gray-100 dark:border-white/5 text-center text-emerald-500 text-sm">
            <i class="bi bi-check-circle mr-1"></i>ไม่มีรูปคุณภาพต่ำ
        </div>
    @else
        <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2">
            @foreach($bottom as $p)
                <div class="relative group aspect-square overflow-hidden rounded-lg bg-slate-900/5 ring-1 ring-rose-500/30">
                    <img src="{{ $thumb($p) }}" class="w-full h-full object-cover opacity-75" loading="lazy" alt="">
                    <span class="absolute bottom-1 right-1 text-[10px] font-bold font-mono px-1.5 py-0.5 rounded {{ $scoreCls($p->quality_score) }}">
                        {{ number_format((float) $p->quality_score, 1) }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
