@extends('layouts.app')

@section('title', 'รีเซ็ตรหัสผ่าน')

@section('content-full')
<div class="flex items-center justify-center py-12 relative" style="min-height:80vh;background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);">
  <div class="absolute w-[400px] h-[400px] rounded-full top-[-100px] right-[-50px]" style="background:radial-gradient(circle,rgba(99,102,241,0.08) 0%,transparent 70%);"></div>

  <div class="relative w-full max-w-[440px] mx-4 bg-white rounded-3xl border border-gray-100 shadow-[0_25px_50px_-12px_rgba(0,0,0,0.08)]">
    <div class="text-center pt-10 px-6">
      <div class="flex items-center justify-center mx-auto mb-3 w-14 h-14 rounded-xl" style="background:linear-gradient(135deg,#10b981,#059669);">
        <i class="bi bi-shield-lock text-white text-2xl"></i>
      </div>
      <h4 class="font-bold mb-1 tracking-tight text-xl">ตั้งรหัสผ่านใหม่</h4>
      <p class="text-gray-500 text-sm">กรอกรหัสผ่านใหม่ที่ต้องการ</p>
    </div>

    <div class="px-6 md:px-8 pb-8 pt-2">
      @if($errors->any())
        <div class="rounded-xl p-3 text-sm mb-3" style="background:rgba(239,68,68,0.08);color:#dc2626;">
          <i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">รหัสผ่านใหม่</label>
          <div class="flex">
            <span class="px-3 py-2.5 bg-gray-50 border border-gray-200 border-r-0 text-gray-500 rounded-l-xl flex items-center"><i class="bi bi-lock text-gray-400"></i></span>
            <input type="password" name="password" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 border-l-0 rounded-r-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition outline-none" placeholder="อย่างน้อย 8 ตัวอักษร" required autofocus>
          </div>
        </div>

        <div class="mb-6">
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">ยืนยันรหัสผ่าน</label>
          <div class="flex">
            <span class="px-3 py-2.5 bg-gray-50 border border-gray-200 border-r-0 text-gray-500 rounded-l-xl flex items-center"><i class="bi bi-lock-fill text-gray-400"></i></span>
            <input type="password" name="password_confirmation" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 border-l-0 rounded-r-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition outline-none" placeholder="กรอกรหัสผ่านอีกครั้ง" required>
          </div>
        </div>

        <button type="submit" class="w-full py-2.5 text-white font-semibold rounded-xl border-none transition hover:opacity-90" style="background:linear-gradient(135deg,#10b981,#059669);">
          <i class="bi bi-check-lg me-1"></i> เปลี่ยนรหัสผ่าน
        </button>
      </form>
    </div>
  </div>
</div>
@endsection
