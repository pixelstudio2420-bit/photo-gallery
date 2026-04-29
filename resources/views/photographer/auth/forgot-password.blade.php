@extends('layouts.photographer')

@section('title', 'ลืมรหัสผ่าน')

@push('styles')
<style>
.photographer-sidebar { display: none !important; }
.photographer-main { margin-left: 0 !important; }
.photographer-topbar { display: none !important; }
</style>
@endpush

@section('content')
<div class="flex items-center justify-center" style="min-height:80vh;">
  <div class="w-full max-w-[420px] bg-white rounded-2xl shadow-[0_10px_40px_rgba(0,0,0,0.08)] p-8">
    <div class="text-center mb-6">
      <div class="flex items-center justify-center mx-auto mb-3 w-[52px] h-[52px] rounded-xl" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
        <i class="bi bi-key text-white text-xl"></i>
      </div>
      <h5 class="font-bold text-lg">ลืมรหัสผ่าน</h5>
      <p class="text-gray-500 text-sm">กรอกอีเมลเพื่อรับลิงก์รีเซ็ตรหัสผ่าน</p>
    </div>

    @if(session('success'))
      <div class="rounded-lg p-3 text-sm mb-3" style="background:rgba(16,185,129,0.08);color:#059669;">
        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
      </div>
    @endif
    @if($errors->any())
      <div class="rounded-lg p-3 text-sm mb-3" style="background:rgba(239,68,68,0.08);color:#dc2626;">
        <i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first() }}
      </div>
    @endif

    <form method="POST" action="{{ route('photographer.password.email') }}">
      @csrf
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">อีเมล</label>
        <input type="email" name="email" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition outline-none" value="{{ old('email') }}" required autofocus>
      </div>
      <button type="submit" class="w-full py-2.5 text-white font-semibold rounded-lg border-none transition hover:opacity-90" style="background:linear-gradient(135deg,#1e40af,#2563eb);">
        <i class="bi bi-send me-1"></i> ส่งลิงก์รีเซ็ต
      </button>
    </form>
    <div class="text-center mt-4">
      <a href="{{ route('photographer.login') }}" class="text-gray-500 text-sm hover:text-gray-700"><i class="bi bi-arrow-left me-1"></i>กลับไปหน้าเข้าสู่ระบบ</a>
    </div>
  </div>
</div>
@endsection
