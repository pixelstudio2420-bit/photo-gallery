@extends('layouts.app')

@section('title', 'Newsletter Confirmation')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center px-4 py-16">
    <div class="max-w-md w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 text-center shadow-lg">
        @if($result['ok'])
            <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 dark:bg-emerald-500/10 flex items-center justify-center mb-4">
                <i class="bi bi-check-circle-fill text-3xl text-emerald-500"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">ยืนยันสำเร็จ 🎉</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ $result['message'] }}</p>
            <p class="text-xs text-slate-500 mt-4">เราจะส่งจดหมายข่าว + promotion ดีๆ ให้คุณทางอีเมลที่ยืนยันแล้ว</p>
        @else
            <div class="w-16 h-16 mx-auto rounded-full bg-rose-100 dark:bg-rose-500/10 flex items-center justify-center mb-4">
                <i class="bi bi-exclamation-triangle-fill text-3xl text-rose-500"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">ยืนยันไม่สำเร็จ</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ $result['message'] }}</p>
        @endif
        <a href="{{ url('/') }}" class="inline-block mt-6 px-6 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">
            กลับหน้าแรก
        </a>
    </div>
</div>
@endsection
