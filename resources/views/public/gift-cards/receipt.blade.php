@extends('layouts.app')
@section('title', 'ซื้อบัตรของขวัญสำเร็จ')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-lg text-center">
        <div class="text-6xl text-emerald-500 mb-4"><i class="bi bi-check-circle-fill"></i></div>
        <h1 class="text-2xl font-bold mb-2">ออกบัตรให้แล้ว!</h1>
        <p class="text-gray-500 mb-6">โปรดเก็บรหัสนี้ไว้ — มอบให้ผู้รับเพื่อใช้งาน</p>

        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-2xl p-8 mb-6">
            <div class="text-xs uppercase tracking-widest opacity-70 mb-2">Gift Card Code</div>
            <div class="text-3xl font-bold font-mono tracking-wider">{{ $gc->code }}</div>
            <div class="mt-4 text-4xl font-bold">฿{{ number_format((float) $gc->initial_amount, 2) }}</div>
            <div class="text-xs opacity-70 mt-2">หมดอายุ {{ $gc->expires_at->format('d M Y') }}</div>
        </div>

        @if($gc->recipient_email)
            <div class="text-sm text-gray-500">
                <i class="bi bi-envelope mr-1"></i>
                จะแจ้งไปที่ {{ $gc->recipient_email }}
            </div>
        @endif

        <div class="flex justify-center gap-3 mt-6">
            <a href="{{ route('gift-cards.index') }}" class="px-6 py-2 bg-gray-100 dark:bg-slate-700 rounded-lg text-sm">ซื้อเพิ่ม</a>
            <a href="{{ url('/') }}" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">กลับหน้าหลัก</a>
        </div>
    </div>
</div>
@endsection
