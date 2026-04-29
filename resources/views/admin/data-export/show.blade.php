@extends('layouts.admin')

@section('title', 'คำขอ PDPA #' . $request->id)

@php
    $types = \App\Models\DataExportRequest::types();
    $statuses = \App\Models\DataExportRequest::statuses();
@endphp

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-shield-lock text-teal-500"></i>
        คำขอ #{{ $request->id }} — {{ $types[$request->request_type] ?? $request->request_type }}
    </h4>
    <a href="{{ route('admin.data-export.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-500">
        <i class="bi bi-arrow-left mr-1"></i>กลับ
    </a>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 rounded-xl p-3 mb-4 text-sm">{{ session('error') }}</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    {{-- Left: request detail --}}
    <div class="md:col-span-2 bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5 space-y-3">
        <h5 class="font-semibold">ข้อมูลคำขอ</h5>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <dt class="text-gray-500">ผู้ขอ</dt>
            <dd class="font-semibold">{{ trim(($request->user?->first_name ?? '') . ' ' . ($request->user?->last_name ?? '')) ?: $request->user?->email }}</dd>
            <dt class="text-gray-500">Email</dt>
            <dd>{{ $request->user?->email }}</dd>
            <dt class="text-gray-500">ประเภท</dt>
            <dd>{{ $types[$request->request_type] ?? $request->request_type }}</dd>
            <dt class="text-gray-500">สถานะ</dt>
            <dd>{{ $statuses[$request->status] ?? $request->status }}</dd>
            <dt class="text-gray-500">ขอเมื่อ</dt>
            <dd>{{ $request->created_at->format('d M Y H:i') }}</dd>
            @if($request->processed_at)
                <dt class="text-gray-500">ดำเนินการเมื่อ</dt>
                <dd>{{ $request->processed_at->format('d M Y H:i') }}</dd>
                <dt class="text-gray-500">โดย</dt>
                <dd>{{ $request->processor?->username ?? ('#' . $request->processed_by) }}</dd>
            @endif
            @if($request->file_path)
                <dt class="text-gray-500">ขนาดไฟล์</dt>
                <dd>{{ number_format(($request->file_size_bytes ?? 0) / 1024, 1) }} KB</dd>
                <dt class="text-gray-500">หมดอายุ</dt>
                <dd>{{ $request->expires_at?->diffForHumans() }}</dd>
            @endif
        </dl>
        @if($request->reason)
            <div>
                <div class="text-sm text-gray-500">เหตุผลของผู้ใช้</div>
                <div class="mt-1 bg-gray-50 dark:bg-slate-900 p-3 rounded text-sm">{{ $request->reason }}</div>
            </div>
        @endif
        @if($request->admin_note)
            <div>
                <div class="text-sm text-gray-500">บันทึกของแอดมิน</div>
                <div class="mt-1 bg-amber-50 dark:bg-amber-900/20 p-3 rounded text-sm">{{ $request->admin_note }}</div>
            </div>
        @endif
    </div>

    {{-- Right: actions --}}
    <div class="space-y-3">
        @if($request->request_type === 'export')
            @if(in_array($request->status, ['pending', 'processing'], true))
                <form action="{{ route('admin.data-export.process', $request) }}" method="POST"
                      class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5">
                    @csrf
                    <h5 class="font-semibold mb-2">สร้างไฟล์ Export</h5>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        รวบรวมข้อมูลส่วนบุคคลทุก table ที่เกี่ยวกับผู้ใช้นี้ และสร้างไฟล์ JSON ให้ดาวน์โหลด
                    </p>
                    <button class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm">
                        <i class="bi bi-file-earmark-zip mr-1"></i>สร้างไฟล์ Export
                    </button>
                </form>
            @endif

            @if($request->isReady())
                <a href="{{ route('admin.data-export.download', $request) }}"
                   class="block bg-white dark:bg-slate-800 rounded-xl p-5 border border-emerald-300 dark:border-emerald-500/30 text-center hover:bg-emerald-50 dark:hover:bg-emerald-900/20">
                    <i class="bi bi-download text-emerald-600 text-2xl"></i>
                    <div class="font-semibold mt-2">ดาวน์โหลดไฟล์</div>
                    <div class="text-[11px] text-gray-400">{{ number_format(($request->file_size_bytes ?? 0) / 1024, 1) }} KB</div>
                </a>
            @endif
        @else
            {{-- delete request --}}
            <div class="bg-rose-50 dark:bg-rose-900/20 rounded-xl p-5 border border-rose-200 dark:border-rose-500/30">
                <h5 class="font-semibold text-rose-700 dark:text-rose-200">ขอลบข้อมูล</h5>
                <p class="text-xs text-rose-600 dark:text-rose-200 mt-2">
                    การลบข้อมูลเป็นเรื่องซีเรียส — ตรวจสอบก่อนว่าผู้ใช้ไม่มีคำสั่งซื้อค้าง / เครดิตค้าง / ข้อพิพาท
                    แล้วดำเนินการ "ลบผู้ใช้" ในหน้า Users จะเป็นขั้นตอนที่ถูกต้อง (เพราะจะ cascade ลบทุกอย่าง)
                </p>
                @if($request->user)
                    <a href="{{ route('admin.users.edit', $request->user) }}" class="mt-3 inline-block px-3 py-1.5 bg-rose-600 text-white rounded-lg text-sm">
                        <i class="bi bi-person-x mr-1"></i>ไปหน้าผู้ใช้
                    </a>
                @endif
            </div>
        @endif

        @if(in_array($request->status, ['pending', 'processing'], true))
            <form action="{{ route('admin.data-export.reject', $request) }}" method="POST"
                  class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5">
                @csrf
                <h5 class="font-semibold mb-2">ปฏิเสธคำขอ</h5>
                <textarea name="admin_note" required rows="3"
                          class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm"
                          placeholder="เหตุผล (ต้องระบุ)"></textarea>
                <button class="mt-2 w-full px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm">
                    <i class="bi bi-x-lg mr-1"></i>ปฏิเสธ
                </button>
            </form>
        @endif

        <form action="{{ route('admin.data-export.destroy', $request) }}" method="POST"
              onsubmit="return confirm('ลบคำขอนี้ถาวร? (ไฟล์ export จะถูกลบด้วย)')">
            @csrf @method('DELETE')
            <button class="w-full px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-sm">
                <i class="bi bi-trash mr-1"></i>ลบคำขอ
            </button>
        </form>
    </div>
</div>
@endsection
