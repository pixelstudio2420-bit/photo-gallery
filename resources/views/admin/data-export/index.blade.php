@extends('layouts.admin')

@section('title', 'PDPA Data Export')

@php
    $statusCls = [
        'pending'    => 'bg-amber-500/15 text-amber-700 dark:text-amber-200',
        'processing' => 'bg-sky-500/15 text-sky-700 dark:text-sky-200',
        'ready'      => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200',
        'rejected'   => 'bg-rose-500/15 text-rose-700 dark:text-rose-200',
        'cancelled'  => 'bg-gray-300/40 text-gray-600 dark:text-gray-300',
    ];
    $statuses = \App\Models\DataExportRequest::statuses();
    $types = \App\Models\DataExportRequest::types();
@endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h4 class="font-bold mb-1 tracking-tight flex items-center gap-2">
            <i class="bi bi-shield-lock text-teal-500"></i>
            PDPA Data Export / Delete
            <span class="text-xs font-normal text-gray-400 ml-2">/ คำขอใช้สิทธิตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล</span>
        </h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 m-0">
            ผู้ใช้ขอสำเนาข้อมูล/ขอลบข้อมูล — แอดมินต้องดำเนินการภายใน 30 วันตามกฎหมาย
        </p>
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

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">รออนุมัติ</div>
        <div class="text-2xl font-bold mt-1 {{ $counts['pending'] > 0 ? 'text-amber-500' : '' }}">{{ $counts['pending'] }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">กำลังดำเนินการ</div>
        <div class="text-2xl font-bold mt-1">{{ $counts['processing'] }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">พร้อมดาวน์โหลด</div>
        <div class="text-2xl font-bold mt-1 text-emerald-600 dark:text-emerald-300">{{ $counts['ready'] }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">ปฏิเสธ</div>
        <div class="text-2xl font-bold mt-1">{{ $counts['rejected'] }}</div>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">ผู้ขอ</th>
                    <th class="px-4 py-3">ประเภท</th>
                    <th class="px-4 py-3">สถานะ</th>
                    <th class="px-4 py-3">เหตุผล</th>
                    <th class="px-4 py-3">ขอเมื่อ</th>
                    <th class="px-4 py-3 text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($requests as $r)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ trim(($r->user?->first_name ?? '') . ' ' . ($r->user?->last_name ?? '')) ?: $r->user?->email }}</div>
                            <div class="text-[11px] text-gray-400">{{ $r->user?->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-[11px] {{ $r->request_type === 'delete' ? 'bg-rose-500/15 text-rose-700 dark:text-rose-200' : 'bg-sky-500/15 text-sky-700 dark:text-sky-200' }}">
                                {{ $types[$r->request_type] ?? $r->request_type }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[11px] {{ $statusCls[$r->status] ?? 'bg-gray-200' }}">
                                {{ $statuses[$r->status] ?? $r->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-300 max-w-xs truncate" title="{{ $r->reason }}">{{ $r->reason ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <div>{{ $r->created_at->format('d M Y') }}</div>
                            <div class="text-[10px] text-gray-400">{{ $r->created_at->diffForHumans() }}</div>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.data-export.show', $r) }}"
                               class="px-2 py-1 text-xs border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                                <i class="bi bi-eye"></i> ดู
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">ยังไม่มีคำขอ</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())
        <div class="p-3 border-t border-gray-100 dark:border-white/5">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
