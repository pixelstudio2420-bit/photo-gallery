@extends('layouts.admin')
@section('title', 'Photographer Onboarding')

@php
    $stageCls = [
        'draft'           => 'bg-gray-200 text-gray-600 dark:bg-slate-700 dark:text-gray-300',
        'submitted'       => 'bg-amber-500/15 text-amber-700 dark:text-amber-200',
        'under_review'    => 'bg-sky-500/15 text-sky-700 dark:text-sky-200',
        'approved'        => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200',
        'contract_signed' => 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-200',
        'active'          => 'bg-emerald-600 text-white',
        'rejected'        => 'bg-rose-500/15 text-rose-700 dark:text-rose-200',
    ];
@endphp

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-person-plus text-indigo-500"></i>
        Photographer Onboarding
        <span class="text-xs font-normal text-gray-400 ml-2">/ ใบสมัครช่างภาพ</span>
    </h4>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif

{{-- Stage filter chips --}}
<div class="flex flex-wrap gap-2 mb-4">
    <a href="{{ route('admin.photographer-onboarding.index') }}" class="px-3 py-1.5 rounded-full text-xs {{ !$stage ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300' }}">
        ทั้งหมด ({{ array_sum($counts) }})
    </a>
    @foreach($stages as $key => $label)
        <a href="{{ route('admin.photographer-onboarding.index', ['stage' => $key]) }}" class="px-3 py-1.5 rounded-full text-xs {{ $stage === $key ? 'bg-indigo-600 text-white' : ($stageCls[$key] ?? 'bg-gray-100 dark:bg-slate-700') }}">
            {{ $label }} ({{ $counts[$key] ?? 0 }})
        </a>
    @endforeach
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
            <tr>
                <th class="px-4 py-3">ผู้สมัคร</th>
                <th class="px-4 py-3">สถานะ</th>
                <th class="px-4 py-3">อัพเดตล่าสุด</th>
                <th class="px-4 py-3 text-right">การจัดการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @forelse($profiles as $p)
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-semibold">{{ $p->display_name }}</div>
                        <div class="text-[11px] text-gray-400">{{ $p->user?->email }}</div>
                        @if($p->photographer_code)
                            <span class="text-[10px] text-gray-400 font-mono">{{ $p->photographer_code }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded-full text-[11px] {{ $stageCls[$p->onboarding_stage] ?? 'bg-gray-200' }}">
                            {{ $stages[$p->onboarding_stage] ?? $p->onboarding_stage }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $p->updated_at?->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.photographer-onboarding.review', $p) }}" class="px-3 py-1 text-xs border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                            <i class="bi bi-eye mr-1"></i>ตรวจสอบ
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">ไม่มีใบสมัคร</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($profiles->hasPages())
        <div class="p-3 border-t border-gray-100 dark:border-white/5">{{ $profiles->links() }}</div>
    @endif
</div>
@endsection
