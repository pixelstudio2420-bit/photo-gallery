@extends('layouts.photographer')

@section('title', 'รีเซ็ตรหัสผ่าน')

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
      <div class="flex items-center justify-center mx-auto mb-3 w-[52px] h-[52px] rounded-xl" style="background:linear-gradient(135deg,#10b981,#059669);">
        <i class="bi bi-shield-lock text-white text-xl"></i>
      </div>
      <h5 class="font-bold text-lg">ตั้งรหัสผ่านใหม่</h5>
    </div>

    @if($errors->any())
      <div class="rounded-lg p-3 text-sm mb-3" style="background:rgba(239,68,68,0.08);color:#dc2626;">
        <i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first() }}
      </div>
    @endif

    <form method="POST" action="{{ route('photographer.password.update') }}">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <input type="hidden" name="email" value="{{ $email }}">
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">รหัสผ่านใหม่</label>
        <input type="password" name="password" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition outline-none" required autofocus>
      </div>
      <div class="mb-6">
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">ยืนยันรหัสผ่าน</label>
        <input type="password" name="password_confirmation" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition outline-none" required>
      </div>
      <button type="submit" class="w-full py-2.5 text-white font-semibold rounded-lg border-none transition hover:opacity-90" style="background:linear-gradient(135deg,#10b981,#059669);">
        <i class="bi bi-check-lg me-1"></i> เปลี่ยนรหัสผ่าน
      </button>
    </form>
  </div>
</div>
@endsection
