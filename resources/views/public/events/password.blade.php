@extends('layouts.app')

@section('content')
<div class="flex items-center justify-center py-12 px-4 min-h-[80vh]">
  <div class="w-full max-w-md">
    <div class="rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-xl overflow-hidden">

      {{-- Header with gradient --}}
      <div class="text-center py-8 px-6 bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 relative overflow-hidden">
        <div class="absolute inset-0 opacity-20 pointer-events-none">
          <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/30 rounded-full blur-2xl"></div>
          <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-pink-400/40 rounded-full blur-2xl"></div>
        </div>
        <div class="relative">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-md shadow-lg mb-3">
            <i class="bi bi-lock-fill text-white text-2xl"></i>
          </div>
          <h1 class="text-white font-bold text-xl mb-1">อีเวนต์นี้ป้องกันด้วยรหัสผ่าน</h1>
          <p class="text-white/80 text-sm">กรุณากรอกรหัสผ่านเพื่อเข้าชมอีเวนต์</p>
        </div>
      </div>

      <div class="p-6">
        {{-- Event name --}}
        <div class="text-center mb-5 pb-5 border-b border-slate-100 dark:border-white/5">
          <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">อีเวนต์</p>
          <h2 class="font-semibold text-slate-900 dark:text-white">{{ $event->name }}</h2>
        </div>

        @if($errors->any())
          <div class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 text-sm flex items-start gap-2">
            <i class="bi bi-exclamation-circle-fill mt-0.5"></i>
            <span>{{ $errors->first('password') }}</span>
          </div>
        @endif

        <form method="POST" action="{{ route('events.verify-password', $event->id) }}">
          @csrf
          <div class="mb-5">
            <label for="password" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
              <i class="bi bi-key-fill text-indigo-500"></i> รหัสผ่าน
            </label>
            <div class="flex gap-0">
              <input
                type="password"
                id="password"
                name="password"
                placeholder="กรอกรหัสผ่านเพื่อเข้าชม"
                autofocus
                autocomplete="current-password"
                class="w-full px-3 py-2.5 rounded-l-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('password') border-rose-500 focus:ring-rose-500 @enderror"
              >
              <button
                type="button"
                class="px-4 rounded-r-xl border border-l-0 border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 transition"
                onclick="togglePassword()"
                tabindex="-1"
                aria-label="แสดง/ซ่อนรหัสผ่าน">
                <i class="bi bi-eye" id="toggleIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit"
                  class="w-full py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
            <i class="bi bi-unlock-fill"></i> เข้าชมอีเวนต์
          </button>
        </form>
      </div>

      {{-- Footer --}}
      <div class="px-6 py-3 text-center bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-white/5">
        <a href="{{ route('events.index') }}" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
          <i class="bi bi-arrow-left"></i> กลับไปยังรายการอีเวนต์
        </a>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
function togglePassword() {
  const input = document.getElementById('password');
  const icon = document.getElementById('toggleIcon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('bi-eye', 'bi-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('bi-eye-slash', 'bi-eye');
  }
}
</script>
@endpush
