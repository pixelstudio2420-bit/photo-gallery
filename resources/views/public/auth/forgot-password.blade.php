@extends('layouts.app')

@section('title', 'ลืมรหัสผ่าน')

@section('content-full')
<div class="flex items-center justify-center py-12 px-4 min-h-[80vh] bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800 relative overflow-hidden">
  {{-- Decorative blobs --}}
  <div class="absolute w-[400px] h-[400px] rounded-full top-[-100px] right-[-50px] bg-gradient-radial from-indigo-400/10 to-transparent blur-3xl pointer-events-none"></div>
  <div class="absolute w-[300px] h-[300px] rounded-full bottom-[-80px] left-[-50px] bg-gradient-radial from-purple-400/10 to-transparent blur-3xl pointer-events-none"></div>

  <div class="relative w-full max-w-md">
    <div class="rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-xl overflow-hidden">
      {{-- Header --}}
      <div class="text-center pt-10 px-6 pb-4">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-lg mb-4">
          <i class="bi bi-key-fill text-3xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight mb-1">ลืมรหัสผ่าน?</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">กรอกอีเมลเพื่อรับลิงก์รีเซ็ตรหัสผ่าน</p>
      </div>

      <div class="px-6 md:px-8 pb-8 pt-2">
        @if(session('success'))
          <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-800 dark:text-emerald-300 text-sm flex items-start gap-2">
            <i class="bi bi-check-circle-fill mt-0.5"></i> {{ session('success') }}
          </div>
        @endif

        @if($errors->any())
          <div class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 text-sm flex items-start gap-2">
            <i class="bi bi-exclamation-circle-fill mt-0.5"></i> {{ $errors->first() }}
          </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
          @csrf
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">อีเมล</label>
            <div class="relative">
              <i class="bi bi-envelope absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
              <input type="email" name="email"
                     placeholder="your@email.com"
                     value="{{ old('email') }}"
                     required autofocus
                     class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
            </div>
          </div>

          <button type="submit"
                  class="w-full py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-semibold shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
            <i class="bi bi-send-fill"></i> ส่งลิงก์รีเซ็ต
          </button>
        </form>

        <div class="text-center mt-5">
          <a href="{{ route('login') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
            <i class="bi bi-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
