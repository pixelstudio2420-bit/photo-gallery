@extends('layouts.app')

@section('title', 'จัดการข้อมูลส่วนบุคคล (PDPA)')

@php
    $statusCls = [
        'pending'    => 'bg-amber-500/15 text-amber-700 dark:text-amber-200',
        'processing' => 'bg-sky-500/15 text-sky-700 dark:text-sky-200',
        'ready'      => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200',
        'rejected'   => 'bg-rose-500/15 text-rose-700 dark:text-rose-200',
        'cancelled'  => 'bg-gray-300/40 text-gray-600 dark:text-gray-300',
    ];
    $types    = \App\Models\DataExportRequest::types();
    $statuses = \App\Models\DataExportRequest::statuses();
@endphp

@section('content')
<div class="max-w-3xl mx-auto py-8 px-4">
    <h2 class="text-2xl font-bold mb-2 flex items-center gap-2">
        <i class="bi bi-shield-lock text-teal-500"></i>จัดการข้อมูลส่วนบุคคลของคุณ
    </h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        ตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล (PDPA) คุณสามารถขอสำเนาข้อมูลของคุณ หรือขอให้เราลบข้อมูลของคุณได้
    </p>

    @if(session('success'))
        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 rounded-xl p-3 mb-4 text-sm">{{ session('error') }}</div>
    @endif

    <form action="{{ route('data-export.store') }}" method="POST"
          class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5 mb-6">
        @csrf
        <h3 class="font-semibold mb-3">ส่งคำขอใหม่</h3>
        <div class="space-y-3">
            <div class="flex gap-3">
                <label class="flex-1 flex items-center gap-2 border border-gray-200 dark:border-white/10 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700">
                    <input type="radio" name="request_type" value="export" checked>
                    <div>
                        <div class="text-sm font-semibold">ขอสำเนาข้อมูล</div>
                        <div class="text-[11px] text-gray-400">รับไฟล์ JSON ข้อมูลของคุณ</div>
                    </div>
                </label>
                <label class="flex-1 flex items-center gap-2 border border-gray-200 dark:border-white/10 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700">
                    <input type="radio" name="request_type" value="delete">
                    <div>
                        <div class="text-sm font-semibold">ขอลบข้อมูล</div>
                        <div class="text-[11px] text-gray-400">ลบบัญชีและข้อมูลทั้งหมด</div>
                    </div>
                </label>
            </div>
            <div>
                <label class="block text-sm mb-1">เหตุผล (ไม่บังคับ)</label>
                <textarea name="reason" rows="2"
                          class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm"
                          placeholder="เหตุผลของคุณ (ช่วยให้เราดำเนินการเร็วขึ้น)"></textarea>
            </div>
        </div>
        <button class="mt-4 w-full px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm">
            <i class="bi bi-send mr-1"></i>ส่งคำขอ
        </button>
        <p class="mt-2 text-[11px] text-gray-400 text-center">แอดมินจะตอบกลับภายใน 7 วัน</p>
    </form>

    <h3 class="font-semibold mb-3">ประวัติคำขอของฉัน</h3>
    <div class="space-y-2">
        @forelse($requests as $r)
            <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5 flex items-center gap-3">
                <div class="flex-1">
                    <div class="font-semibold">{{ $types[$r->request_type] ?? $r->request_type }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $r->created_at->format('d M Y H:i') }} · {{ $r->created_at->diffForHumans() }}</div>
                    @if($r->reason)
                        <div class="text-xs text-gray-500 mt-1">เหตุผล: {{ $r->reason }}</div>
                    @endif
                    @if($r->admin_note)
                        <div class="text-xs text-rose-600 mt-1">แอดมินแจ้ง: {{ $r->admin_note }}</div>
                    @endif
                </div>
                <span class="px-2 py-0.5 rounded-full text-[11px] {{ $statusCls[$r->status] }}">{{ $statuses[$r->status] ?? $r->status }}</span>
                @if($r->isReady() && $r->download_token)
                    <a href="{{ route('data-export.download', $r->download_token) }}"
                       class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">
                        <i class="bi bi-download mr-1"></i>ดาวน์โหลด
                    </a>
                @endif
                @if($r->status === 'pending')
                    <form action="{{ route('data-export.cancel', $r) }}" method="POST" onsubmit="return confirm('ยกเลิก?')">
                        @csrf
                        <button class="px-2 py-1 text-xs text-rose-500 hover:underline">ยกเลิก</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-center text-gray-500 text-sm py-8">ยังไม่มีคำขอ</p>
        @endforelse
    </div>
</div>
@endsection
