@extends('layouts.app')
@section('title', 'Referral')
@section('content')
<div class="max-w-md mx-auto px-4 py-16 text-center">
    <i class="bi bi-people-fill text-5xl text-slate-400 mb-4"></i>
    <h1 class="text-xl font-bold text-slate-900 dark:text-white mb-2">ระบบแนะนำเพื่อนยังไม่เปิด</h1>
    <p class="text-sm text-slate-500">กรุณากลับมาเยี่ยมชมใหม่ภายหลัง</p>
    <a href="{{ url('/') }}" class="mt-6 inline-block px-6 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm">กลับหน้าแรก</a>
</div>
@endsection
