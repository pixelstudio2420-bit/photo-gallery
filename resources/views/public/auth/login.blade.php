@extends('layouts.app')

@section('title', 'เข้าสู่ระบบ')

@php
  $svc = app(\App\Services\Auth\SocialAuthService::class);
  $emailReg = $svc->isEmailRegistrationEnabled();
@endphp

@push('styles')
<style>
  .login-bg{
    background:
      radial-gradient(900px 500px at 15% -10%, rgba(99,102,241,.18), transparent 60%),
      radial-gradient(800px 500px at 85% 15%, rgba(236,72,153,.12), transparent 60%),
      linear-gradient(160deg,#f8fafc 0%,#f1f5f9 60%,#ede9fe 100%);
  }
  html.dark .login-bg{
    background:
      radial-gradient(900px 500px at 15% -10%, rgba(99,102,241,.26), transparent 60%),
      radial-gradient(800px 500px at 85% 15%, rgba(236,72,153,.18), transparent 60%),
      linear-gradient(160deg,#020617 0%,#0f172a 60%,#1e1b4b 100%);
  }
  .login-card{
    background:#fff; border-radius:28px; box-shadow:0 40px 80px -20px rgba(15,23,42,.2);
    border:1px solid rgba(99,102,241,.08); overflow:hidden;
  }
  html.dark .login-card{ background:#0f172a; border-color:rgba(255,255,255,.08); color:#e2e8f0; }
  .login-header{
    background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 45%,#ec4899 100%);
    padding:2.25rem 2rem 4rem; color:#fff; position:relative; overflow:hidden;
  }
  .login-header::after{
    content:''; position:absolute; left:0; right:0; bottom:-1px; height:40px;
    background:inherit; clip-path:polygon(0 0, 100% 30%, 100% 100%, 0 100%);
    display:none;
  }
  .login-header h2{ font-weight:800; letter-spacing:-0.02em; font-size:1.7rem; margin-bottom:.25rem; }
  .login-logo{
    width:52px; height:52px; border-radius:16px; background:rgba(255,255,255,.2); backdrop-filter:blur(8px);
    display:inline-flex; align-items:center; justify-content:center; font-size:1.6rem; color:#fff;
    border:1px solid rgba(255,255,255,.25);
  }
  .login-body{ padding:2rem 2rem 2.25rem; margin-top:-2rem; position:relative; z-index:1; background:inherit; border-radius:28px 28px 0 0; }
  .fancy-input{
    width:100%; padding:.75rem 1rem .75rem 2.6rem; border-radius:12px;
    border:1.5px solid #e5e7eb; font-size:.92rem; outline:none; background:#fff; color:#0f172a;
    transition:border-color .2s, box-shadow .2s;
  }
  html.dark .fancy-input{ background:#1e293b; color:#f1f5f9; border-color:rgba(255,255,255,.1); }
  .fancy-input:focus{ border-color:#6366f1; box-shadow:0 0 0 4px rgba(99,102,241,.12); }
  .input-wrap{ position:relative; }
  .input-wrap > i{ position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:#9ca3af; pointer-events:none; }
  .input-wrap .toggle-eye{ position:absolute; right:.5rem; top:50%; transform:translateY(-50%); background:none; border:none; color:#9ca3af; padding:.35rem .55rem; cursor:pointer; }
  .input-wrap .toggle-eye:hover{ color:#6366f1; }
  .btn-login{
    width:100%; padding:.85rem; border:none; border-radius:12px; font-weight:700;
    background:linear-gradient(135deg,#4f46e5,#6366f1); color:#fff; font-size:.97rem;
    box-shadow:0 10px 24px -6px rgba(99,102,241,.55); transition:transform .15s, box-shadow .2s;
  }
  .btn-login:hover{ transform:translateY(-1px); box-shadow:0 14px 30px -6px rgba(99,102,241,.7); }

  @keyframes lfade{ from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);} }
  .lanim{ animation:lfade .45s ease-out both; }
  .lanim.d1{ animation-delay:.05s; } .lanim.d2{ animation-delay:.15s; } .lanim.d3{ animation-delay:.25s; }
</style>
@endpush

@section('content-full')
<div class="login-bg min-h-[90vh] flex items-center justify-center py-10 px-4">
  <div class="w-full max-w-md lanim">

    <div class="login-card">
      {{-- Header --}}
      <div class="login-header">
        <div class="flex items-center gap-3">
          <span class="login-logo" aria-hidden="true"><i class="bi bi-camera2"></i></span>
          <div>
            <h2>เข้าสู่ระบบ</h2>
            <p class="text-white/85 text-sm mb-0">ยินดีต้อนรับกลับ — เข้าถึงภาพของคุณ</p>
          </div>
        </div>
      </div>

      <div class="login-body">
        {{-- Flash --}}
        @if(session('logout_success'))
          <div class="rounded-xl p-3 text-sm mb-4 bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-400/30 lanim d1">
            <i class="bi bi-check-circle mr-1"></i>{{ session('logout_success') }}
          </div>
        @endif
        @if(session('info'))
          <div class="rounded-xl p-3 text-sm mb-4 bg-sky-50 text-sky-700 border border-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:border-sky-400/30 lanim d1">
            <i class="bi bi-info-circle mr-1"></i>{{ session('info') }}
          </div>
        @endif
        @if(session('warning'))
          <div class="rounded-xl p-3 text-sm mb-4 bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-400/30 lanim d1">
            <i class="bi bi-clock-history mr-1"></i>{{ session('warning') }}
          </div>
        @endif
        @if($errors->any())
          <div class="rounded-xl p-3 text-sm mb-4 bg-red-50 text-red-700 border border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-400/30 lanim d1">
            <i class="bi bi-exclamation-circle mr-1"></i>{{ $errors->first() }}
          </div>
        @endif

        {{-- Social Login (prominent) --}}
        <div class="lanim d1">
          <x-social-buttons role="customer" size="md" intent="login" :hideRecommended="true"/>
        </div>

        {{-- Email form --}}
        @if($emailReg)
          <div class="flex items-center gap-3 my-5 lanim d2">
            <hr class="flex-1 border-gray-200 dark:border-white/10">
            <span class="text-xs text-gray-500 dark:text-gray-400 px-1">หรือเข้าสู่ระบบด้วยอีเมล</span>
            <hr class="flex-1 border-gray-200 dark:border-white/10">
          </div>

          <form method="POST" action="{{ route('auth.login.post') }}" class="lanim d3">
            @csrf
            <div class="mb-3">
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5" for="login-email">อีเมล</label>
              <div class="input-wrap">
                <i class="bi bi-envelope"></i>
                <input id="login-email" class="fancy-input" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="example@email.com" autocomplete="email">
              </div>
            </div>
            <div class="mb-3" x-data="{show:false}">
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5" for="login-pw">รหัสผ่าน</label>
              <div class="input-wrap">
                <i class="bi bi-lock"></i>
                <input id="login-pw" class="fancy-input pr-12" :type="show ? 'text' : 'password'" name="password" required placeholder="********" autocomplete="current-password">
                <button type="button" class="toggle-eye" @click="show = !show" :aria-label="show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'">
                  <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
              </div>
            </div>

            <div class="flex items-center justify-between mb-4">
              <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
                <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                จดจำฉัน
              </label>
              <a href="{{ route('password.request') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">ลืมรหัสผ่าน?</a>
            </div>

            <button type="submit" class="btn-login">
              <i class="bi bi-box-arrow-in-right mr-1"></i> เข้าสู่ระบบ
            </button>
          </form>
        @endif

        {{-- Sign up links --}}
        <div class="text-center mt-6 lanim d3 space-y-2">
          <div>
            <span class="text-sm text-gray-500 dark:text-gray-400">ยังไม่มีบัญชี?</span>
            <a href="{{ route('auth.register') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 ml-1">สมัครสมาชิก</a>
          </div>
          <div>
            <a href="{{ route('auth.register') }}#role-photographer-title"
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300">
              <i class="bi bi-camera-fill"></i>
              อยากขายภาพ? สมัครเป็นช่างภาพได้ที่นี่
              <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>
    </div>

    {{-- Trust badges --}}
    <div class="text-center mt-6 text-xs text-slate-500 dark:text-slate-400 lanim d3">
      <i class="bi bi-shield-lock mr-1"></i> เข้ารหัสการเชื่อมต่อด้วย SSL &middot;
      <a href="{{ url('/help') }}" class="hover:underline">ศูนย์ช่วยเหลือ</a>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  // ─── CSRF keep-alive ────────────────────────────────────────────
  // ถ้าผู้ใช้เปิดหน้า login ทิ้งไว้นาน session อาจหมดอายุ
  // เราจะ ping /csrf-refresh ทุก 15 นาที เพื่อ keep session alive
  // และอัปเดต _token ใน form ทั้งหมด
  const REFRESH_INTERVAL = 15 * 60 * 1000; // 15 min

  async function refreshCsrf() {
    try {
      const res = await fetch('{{ route('csrf.refresh') }}', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.token) return;

      // Update meta tag
      const meta = document.querySelector('meta[name="csrf-token"]');
      if (meta) meta.setAttribute('content', data.token);

      // Update every <input name="_token"> on the page
      document.querySelectorAll('input[name="_token"]').forEach(el => el.value = data.token);

      // Update global Axios/jQuery if present
      if (window.axios && window.axios.defaults) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = data.token;
      }
      window.__csrf = data.token;
    } catch (_) { /* silent */ }
  }

  // Refresh right when the page becomes visible again (user tabbed away and came back)
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') refreshCsrf();
  });

  setInterval(refreshCsrf, REFRESH_INTERVAL);
})();
</script>
@endpush
