@extends('layouts.app')

@section('title', 'Unsubscribe')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center px-4 py-16">
    <div class="max-w-md w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 shadow-lg">
        @if($state === 'ask')
            <h1 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                <i class="bi bi-envelope-slash text-rose-500"></i>
                ยกเลิกการรับอีเมล
            </h1>
            <form method="GET" class="space-y-3">
                <label class="block text-sm text-slate-600 dark:text-slate-400">กรุณาระบุอีเมลที่ต้องการยกเลิก</label>
                <input type="email" name="email" required
                    class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                <button class="w-full px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold">
                    ดำเนินการต่อ
                </button>
            </form>

        @elseif($state === 'confirm')
            <h1 class="text-xl font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                <i class="bi bi-envelope-slash text-rose-500"></i>
                ยืนยันยกเลิก
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                ยืนยันว่าต้องการยกเลิกการรับอีเมลจาก <strong>{{ $email }}</strong>?
            </p>
            <form method="POST" class="space-y-3">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <textarea name="reason" rows="2" placeholder="เหตุผล (optional)"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none"></textarea>
                <div class="flex gap-2">
                    <a href="{{ url('/') }}" class="flex-1 text-center px-4 py-2 rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-900 dark:text-white text-sm">ไม่, ย้อนกลับ</a>
                    <button class="flex-1 px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold">
                        ใช่, ยกเลิก
                    </button>
                </div>
            </form>

        @else
            <div class="text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                    <i class="bi bi-envelope-slash text-3xl text-slate-500"></i>
                </div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white mb-2">ยกเลิกเรียบร้อย</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ $result['message'] ?? 'เสร็จสิ้น' }}</p>
                <p class="text-xs text-slate-500 mt-4">เราเสียดายที่ต้องเห็นคุณไป — สามารถกลับมา subscribe ใหม่ได้ทุกเมื่อ</p>
                <a href="{{ url('/') }}" class="inline-block mt-6 px-6 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">
                    กลับหน้าแรก
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
