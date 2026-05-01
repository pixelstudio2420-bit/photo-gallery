@extends('layouts.admin')
@section('title', 'Photo Quality Ranking')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-stars text-indigo-500"></i> Photo Quality Ranking
        <span class="text-xs font-normal text-gray-400 ml-2">/ คะแนนคุณภาพภาพ</span>
    </h4>
    <form action="{{ route('admin.photo-quality.rescore-all') }}" method="POST"
          onsubmit="return confirm('คำนวณใหม่ทุกงาน? อาจใช้เวลาสักครู่')">
        @csrf
        <button class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
            <i class="bi bi-arrow-repeat mr-1"></i>คำนวณทั้งหมด
        </button>
    </form>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif

{{-- KPIs --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">รูปทั้งหมด</div>
        <div class="text-2xl font-bold">{{ number_format($kpis['total']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">คำนวณแล้ว</div>
        <div class="text-2xl font-bold text-indigo-500">{{ number_format($kpis['scored']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">คะแนนเฉลี่ย</div>
        <div class="text-2xl font-bold text-emerald-500">{{ $kpis['avg_score'] }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">ระดับสูง (≥75)</div>
        <div class="text-2xl font-bold text-emerald-500">{{ number_format($kpis['high']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">ต่ำ (&lt;40)</div>
        <div class="text-2xl font-bold text-rose-500">{{ number_format($kpis['low']) }}</div>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
            <tr>
                <th class="px-4 py-3">งาน</th>
                <th class="px-4 py-3">จำนวนรูป</th>
                <th class="px-4 py-3">คะแนนเฉลี่ย</th>
                <th class="px-4 py-3">สแกนล่าสุด</th>
                <th class="px-4 py-3 text-right">ตรวจสอบ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @forelse($events as $e)
                @php
                    $avg = $e->avg_score;
                    $badgeCls = $avg === null ? 'bg-gray-200 text-gray-600'
                        : ($avg >= 75 ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200'
                        : ($avg >= 40 ? 'bg-amber-500/15 text-amber-700 dark:text-amber-200'
                        : 'bg-rose-500/15 text-rose-700 dark:text-rose-200'));
                @endphp
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-semibold">{{ $e->name }}</div>
                        {{-- The schema uses `slug` (not the legacy event_code
                             column referenced before — see PhotoQualityController
                             update). Falls back to the numeric id for events
                             that haven't generated a slug yet. --}}
                        <div class="text-[11px] text-gray-400 font-mono">{{ $e->slug ?? '#' . $e->id }}</div>
                    </td>
                    <td class="px-4 py-3">{{ number_format($e->photo_count ?? 0) }}</td>
                    <td class="px-4 py-3">
                        @if($avg !== null)
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs {{ $badgeCls }}">
                                {{ $avg }}
                            </span>
                        @else
                            <span class="text-gray-400 text-xs">ยังไม่คำนวณ</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        {{ $e->last_scored_at ? \Carbon\Carbon::parse($e->last_scored_at)->diffForHumans() : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.photo-quality.show', $e->id) }}"
                           class="px-3 py-1 text-xs border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                            <i class="bi bi-eye mr-1"></i>ดู
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">ยังไม่มีงาน</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($events->hasPages())
        <div class="p-3 border-t border-gray-100 dark:border-white/5">{{ $events->links() }}</div>
    @endif
</div>
@endsection
