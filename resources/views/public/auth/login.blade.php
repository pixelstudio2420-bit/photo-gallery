@extends('layouts.app')

@section('title', 'เข้าสู่ระบบ')

@php
  /** @var \App\Services\Auth\SocialAuthService $svc */
  $svc        = app(\App\Services\Auth\SocialAuthService::class);
  $emailReg   = $svc->isEmailRegistrationEnabled();
  $providers  = $svc->enabledProviders();
  // Pull LINE out so we can render a custom hero button — the
  // shared <x-social-buttons> component is great for the register
  // page, but for /auth/login we want LINE to feel like THE primary
  // path: bigger, with a benefit list, with a pulse glow.
  $lineEnabled = isset($providers['line']);
  $lineUrl     = $lineEnabled ? $svc->providerUrl('line') : null;
  // Other providers (Google / Facebook / Apple etc.) get a small
  // secondary row so customers who really want them can still use
  // them — just visually demoted vs the LINE CTA.
  $secondaryProviders = collect($providers)->except('line');
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

  /* ── LINE primary CTA — emphasised so customers gravitate here.
        Brand green, soft pulsing glow + arrow nudge on hover.
        Wrapper holds the floating "แนะนำ" badge so we can keep
        overflow:visible there without losing border-radius clipping
        on the button itself. ── */
  .btn-line-wrap{
    position:relative;        /* anchor for the floating badge */
    /* NO overflow:hidden — the badge sits OUTSIDE the button on
       purpose; clipping it is what made the badge "missing" before. */
  }
  .btn-line-primary{
    width:100%;
    display:flex; align-items:center; gap:.75rem;
    padding:1.05rem 1.1rem;
    background:linear-gradient(135deg,#06C755 0%,#00B043 100%);
    color:#fff; font-weight:800; font-size:1.05rem;
    border-radius:14px; border:none; text-decoration:none;
    box-shadow:0 14px 28px -8px rgba(6,199,85,.55), 0 0 0 0 rgba(6,199,85,.45);
    transition:transform .15s, box-shadow .2s;
    position:relative;
    animation:linePulse 2.6s ease-in-out infinite;
    /* NEVER overflow-hidden here — badge would get clipped. */
  }
  .btn-line-primary:hover{
    transform:translateY(-2px);
    box-shadow:0 22px 40px -10px rgba(6,199,85,.7), 0 0 0 6px rgba(6,199,85,.18);
    animation:none; /* stop pulsing once hovered — feels less anxious */
  }
  /* Icon + arrow must NOT shrink; only the text span flexes. */
  .btn-line-primary .line-icon{
    width:34px; height:34px; border-radius:9px;
    background:rgba(255,255,255,.18);
    display:inline-flex; align-items:center; justify-content:center;
    font-size:1.4rem; line-height:1;
    flex-shrink:0;
  }
  .btn-line-primary .line-text{
    flex:1 1 auto; min-width:0; text-align:left;
  }
  .btn-line-primary .line-text .line-sub{
    display:block; font-size:.72rem; font-weight:600; opacity:.92;
    margin-top:.05rem;
  }
  .btn-line-primary .line-arrow{ flex-shrink:0; opacity:.85; transition:transform .2s, opacity .2s; }
  .btn-line-primary:hover .line-arrow{ transform:translateX(4px); opacity:1; }

  /* Recommended badge — anchored to the WRAPPER (overflow:visible)
     so it never gets clipped no matter what the button does.
     z-index ensures it floats above the pulsing glow ring. */
  .btn-line-wrap .line-rec{
    position:absolute; top:-9px; right:14px; z-index:2;
    background:#fbbf24; color:#78350f; font-weight:800;
    font-size:.65rem; letter-spacing:.04em; text-transform:uppercase;
    padding:.2rem .55rem; border-radius:999px;
    box-shadow:0 4px 10px rgba(0,0,0,.18);
    pointer-events:none;
  }

  .btn-line-primary.is-loading{ opacity:.85; pointer-events:none; animation:none; }
  .btn-line-primary.is-loading::after{
    content:''; width:16px; height:16px; margin-left:8px;
    border:2.5px solid #fff; border-right-color:transparent; border-radius:50%;
    animation:sbspin .8s linear infinite; display:inline-block;
  }
  @keyframes linePulse{
    0%,100%{ box-shadow:0 14px 28px -8px rgba(6,199,85,.55), 0 0 0 0 rgba(6,199,85,.45); }
    50%    { box-shadow:0 14px 28px -8px rgba(6,199,85,.55), 0 0 0 10px rgba(6,199,85,0); }
  }
  @keyframes sbspin{ to{ transform:rotate(360deg); } }

  /* Mobile (≤ 360px): keep the button readable on narrow screens —
     drop the icon-square padding a little, allow the subtitle to wrap
     to a 2nd line, hide the right-arrow if space is genuinely tight. */
  @media (max-width: 380px){
    .btn-line-primary{ padding:.95rem .9rem; font-size:1rem; }
    .btn-line-primary .line-icon{ width:30px; height:30px; font-size:1.2rem; }
    .btn-line-primary .line-text .line-sub{ font-size:.68rem; }
  }

  /* Why-LINE benefit chips — small, green-tinted, sits below the
     primary button to justify the click without being too loud. */
  .line-benefits{
    background:rgba(6,199,85,.06);
    border:1px solid rgba(6,199,85,.18);
    border-radius:12px;
    padding:.75rem .85rem;
  }
  html.dark .line-benefits{ background:rgba(6,199,85,.10); border-color:rgba(6,199,85,.28); }
  .line-benefits li{
    display:flex; align-items:center; gap:.5rem;
    font-size:.82rem; color:#065f46;
  }
  html.dark .line-benefits li{ color:#a7f3d0; }
  .line-benefits li i{ color:#06C755; font-size:1rem; }

  /* Subtle alt-login link row */
  .alt-login{
    display:inline-flex; align-items:center; justify-content:center; gap:.4rem;
    font-size:.78rem; font-weight:600; color:#64748b;
    padding:.6rem .9rem; border-radius:10px; cursor:pointer;
    transition:background .15s, color .15s;
  }
  .alt-login:hover{ background:rgba(99,102,241,.08); color:#4f46e5; }
  html.dark .alt-login{ color:#94a3b8; }
  html.dark .alt-login:hover{ background:rgba(255,255,255,.06); color:#a5b4fc; }

  /* `<details>` for the email form — disable native marker */
  details.email-toggle > summary{ list-style:none; }
  details.email-toggle > summary::-webkit-details-marker{ display:none; }
  details.email-toggle[open] > summary .alt-login i.bi-chevron-down{ transform:rotate(180deg); }
  .alt-login i.bi-chevron-down{ transition:transform .2s; }

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

        {{-- ════════════════════════════════════════════════════════════
             PRIMARY CTA — LINE login. We hand-roll this instead of
             using <x-social-buttons> because we want it visually
             dominant: brand green, oversized, pulsing glow ring,
             "แนะนำ" badge, and a 3-bullet benefit panel underneath
             that explains WHY LINE in 5 seconds.

             Customers in TH default to LINE for everything; making
             this the obvious one-click path:
               • shrinks login friction (no email/password to type)
               • lines up with the post-purchase delivery flow
                 (rendered straight to LINE chat after checkout)
               • keeps Google/Facebook visible for the minority
                 who prefer them.
             ════════════════════════════════════════════════════════════ --}}
        @if($lineEnabled)
          <div class="lanim d1">
            {{-- Wrapper has position:relative + overflow:visible so the
                 floating "แนะนำ" badge stays painted even when the
                 button itself gets new transforms / shadows. Keeping
                 the badge here (NOT inside <a>) is what fixed the
                 "ปุ่มแสดงไม่ครบ ถูกบัง" report — overflow:hidden
                 on the button was clipping the badge's top half. --}}
            <div class="btn-line-wrap">
              <span class="line-rec" aria-hidden="true">แนะนำ</span>
              <a href="{{ $lineUrl }}"
                 class="btn-line-primary"
                 aria-label="เข้าสู่ระบบด้วย LINE"
                 data-provider="line"
                 onclick="this.classList.add('is-loading');">
                <span class="line-icon"><i class="bi bi-line"></i></span>
                <span class="line-text leading-tight">
                  <span class="block">เข้าสู่ระบบด้วย LINE</span>
                  <span class="line-sub">คลิกเดียว · ไม่ต้องใส่รหัส</span>
                </span>
                <i class="bi bi-arrow-right line-arrow"></i>
              </a>
            </div>

            {{-- Why-LINE benefits — short, punchy, all green-tinted
                 to reinforce the brand association. Acts as the
                 "social proof" justification for tapping the button. --}}
            <ul class="line-benefits mt-3 space-y-1.5 list-none p-0 m-0">
              <li><i class="bi bi-lightning-charge-fill"></i><span>เข้าได้ใน 3 วินาที — ไม่ต้องตั้ง/จำรหัสผ่าน</span></li>
              <li><i class="bi bi-chat-dots-fill"></i><span>รับรูปเข้า LINE หลังจ่ายเงินอัตโนมัติ</span></li>
              <li><i class="bi bi-shield-check"></i><span>ปลอดภัยผ่าน LINE Login Official</span></li>
            </ul>
          </div>
        @endif

        {{-- ── Secondary social providers (Google / Facebook / Apple)
             Smaller compact row — present for users who prefer them
             but visually subordinate to the LINE button above. ── --}}
        @if($secondaryProviders->isNotEmpty())
          <div class="lanim d2 mt-5">
            <div class="flex items-center gap-3 mb-3">
              <hr class="flex-1 border-gray-200 dark:border-white/10">
              <span class="text-[11px] uppercase tracking-wider text-gray-400 dark:text-gray-500">หรือใช้ช่องทางอื่น</span>
              <hr class="flex-1 border-gray-200 dark:border-white/10">
            </div>
            <div class="grid {{ $secondaryProviders->count() > 2 ? 'grid-cols-3' : 'grid-cols-' . $secondaryProviders->count() }} gap-2">
              @foreach($secondaryProviders as $name => $meta)
                <a href="{{ $svc->providerUrl($name) }}"
                   class="social-btn inline-flex items-center justify-center gap-1.5 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md {{ $meta['bg_class'] }} {{ $meta['text_class'] }}"
                   aria-label="เข้าสู่ระบบด้วย {{ $meta['label'] }}"
                   data-provider="{{ $name }}"
                   onclick="this.classList.add('is-loading');">
                  <i class="bi {{ $meta['icon'] }}"></i>
                  <span>{{ $meta['label'] }}</span>
                </a>
              @endforeach
            </div>
          </div>
        @endif

        {{-- ── Email login — collapsed behind a `<details>` toggle.
             Keeps the form available for users who registered with
             email originally, without crowding the page or
             tempting LINE-eligible users to type their password.

             Default-closed except when:
               • email registration is the only option (no providers)
               • the form just failed validation ($errors->any())
               • the user came back from a "ลืมรหัสผ่าน" flow with
                 prefilled email (old('email') set)
             ── --}}
        @if($emailReg)
          @php
            $emailOpen = $errors->any() || old('email') !== null
                || (!$lineEnabled && $secondaryProviders->isEmpty());
          @endphp
          <details class="email-toggle mt-5 lanim d3" {{ $emailOpen ? 'open' : '' }}>
            <summary>
              <span class="alt-login w-full">
                <i class="bi bi-envelope"></i>
                <span>เข้าด้วยอีเมล / รหัสผ่าน</span>
                <i class="bi bi-chevron-down"></i>
              </span>
            </summary>

            <form method="POST" action="{{ route('auth.login.post') }}" class="mt-3">
              @csrf
              <div class="mb-3">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5" for="login-email">อีเมล</label>
                <div class="input-wrap">
                  <i class="bi bi-envelope"></i>
                  <input id="login-email" class="fancy-input" type="email" name="email" value="{{ old('email') }}" required placeholder="example@email.com" autocomplete="email">
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
          </details>
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
