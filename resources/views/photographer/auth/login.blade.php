{{-- ════════════════════════════════════════════════════════════════════════
     /photographer/login — social-first sign-in.

     Layout
     ------
       Mobile (<lg):  single column, social cards stack on top
       Desktop (lg+): 2-column split — hero on left, form on right.
                      The 2-column form fits a 14"-15" laptop without
                      vertical scroll.

     Theming
     -------
     CSS custom properties drive both light + dark themes. Browser default
     follows `prefers-color-scheme`; nothing to toggle in-page (login UX
     should not feel like a settings panel).

     Why standalone HTML (not extends layouts.app)
     ---------------------------------------------
     The photographer hasn't authenticated yet, so the layout's navbar
     would render with empty user-state and look broken. Single page,
     focused on one decision: pick how to sign in.
═══════════════════════════════════════════════════════════════════════ --}}
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>เข้าสู่ระบบช่างภาพ · {{ $siteName ?? config('app.name', 'Loadroop') }}</title>
  <meta name="robots" content="noindex, nofollow">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  @vite(['resources/css/app.css'])
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <style>
    :root {
      /* ─── DARK theme (default) ─── */
      --lg-bg-1: rgba(99,102,241,0.30);
      --lg-bg-2: rgba(236,72,153,0.22);
      --lg-bg-3: rgba(6,199,85,0.18);
      --lg-bg-base: linear-gradient(160deg, #020617 0%, #0f172a 50%, #1e1b4b 100%);

      --card-bg: rgba(15, 23, 42, 0.78);
      --card-border: rgba(255, 255, 255, 0.10);

      /* High-contrast text colors. Original palette had several
         slate-400/500 elements that were hard to read on dark gradient
         — these are 1-2 steps lighter for AA contrast (>4.5:1). */
      --text-strong:  #f8fafc;   /* primary headings */
      --text-body:    #e2e8f0;   /* paragraph text  */
      --text-muted:   #cbd5e1;   /* helper text     */
      --text-faint:   #94a3b8;   /* fine-print only */

      --divider:      rgba(255,255,255,0.12);
      --input-bg:     rgba(255,255,255,0.06);
      --input-border: rgba(255,255,255,0.14);
      --input-focus:  rgba(99,102,241,0.65);
      --hint-bg:      rgba(255,255,255,0.04);
      --hint-border:  rgba(255,255,255,0.10);
      --link-accent:  #a5b4fc;
      --link-hover:   #c7d2fe;

      font-family: 'Noto Sans Thai', system-ui, sans-serif;
    }

    @media (prefers-color-scheme: light) {
      :root {
        /* ─── LIGHT theme ─── */
        --lg-bg-1: rgba(99,102,241,0.18);
        --lg-bg-2: rgba(236,72,153,0.14);
        --lg-bg-3: rgba(6,199,85,0.12);
        --lg-bg-base: linear-gradient(160deg, #f8fafc 0%, #eef2ff 50%, #fdf2f8 100%);

        --card-bg: rgba(255, 255, 255, 0.92);
        --card-border: rgba(15, 23, 42, 0.08);

        --text-strong:  #0f172a;
        --text-body:    #1e293b;
        --text-muted:   #475569;
        --text-faint:   #64748b;

        --divider:      rgba(15,23,42,0.10);
        --input-bg:     #ffffff;
        --input-border: rgba(15,23,42,0.14);
        --input-focus:  rgba(99,102,241,0.55);
        --hint-bg:      rgba(248,250,252,0.7);
        --hint-border:  rgba(15,23,42,0.08);
        --link-accent:  #4f46e5;
        --link-hover:   #4338ca;
      }
    }

    .lg-bg {
      background:
        radial-gradient(1100px 600px at 12% -10%, var(--lg-bg-1), transparent 65%),
        radial-gradient(900px  500px at 88%  10%, var(--lg-bg-2), transparent 65%),
        radial-gradient(800px  600px at 50% 110%, var(--lg-bg-3), transparent 65%),
        var(--lg-bg-base);
    }
    .lg-card {
      background: var(--card-bg);
      -webkit-backdrop-filter: blur(22px) saturate(180%);
      backdrop-filter: blur(22px) saturate(180%);
      border: 1px solid var(--card-border);
    }

    /* ── Provider cards ── */
    .provider-card {
      transition: transform .25s cubic-bezier(.34,1.56,.64,1),
                  box-shadow .25s ease, border-color .25s ease;
    }
    .provider-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 20px 44px -12px rgba(0,0,0,0.40);
    }
    @media (prefers-color-scheme: light) {
      .provider-card:hover { box-shadow: 0 20px 40px -16px rgba(99,102,241,0.30); }
    }

    .ribbon {
      position: absolute; top: -10px; right: 14px;
      background: linear-gradient(135deg, #fbbf24, #f59e0b);
      color: #78350f; font-weight: 800;
      padding: 3px 12px; border-radius: 999px;
      font-size: 10px; letter-spacing: .04em; text-transform: uppercase;
      box-shadow: 0 4px 14px -2px rgba(245, 158, 11, .55);
      z-index: 1;
    }

    @keyframes pulse-glow {
      0%, 100% { box-shadow: 0 0 0 0 var(--glow); }
      50%      { box-shadow: 0 0 0 16px transparent; }
    }
    .pulse-line   { --glow: rgba(6,199,85,0.32);   animation: pulse-glow 3s ease-in-out infinite; }
    .pulse-google { --glow: rgba(66,133,244,0.30); animation: pulse-glow 3s ease-in-out infinite; }

    @keyframes float-in {
      from { opacity: 0; transform: translateY(10px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    /* `forwards` not `both` — never starts hidden, just animates IN. The
       previous `both` left page invisible until JS-fired keyframes
       completed, which made screenshots/PageSpeed see a blank fold. */
    .anim-in    { animation: float-in .45s cubic-bezier(0.34,1.56,0.64,1) forwards; }
    .anim-in-1  { animation-delay: 0s; }
    .anim-in-2  { animation-delay: .06s; }
    .anim-in-3  { animation-delay: .12s; }
    .anim-in-4  { animation-delay: .18s; }
    /* Respect users with motion sensitivity (and screenshot/PSI bots). */
    @media (prefers-reduced-motion: reduce) {
      .anim-in { animation: none; }
    }

    /* ── Form inputs (high contrast both modes) ── */
    .email-input {
      background: var(--input-bg);
      border: 1px solid var(--input-border);
      color: var(--text-strong);
      transition: border-color .2s, background .2s;
    }
    .email-input:focus {
      outline: none;
      border-color: var(--input-focus);
      background: var(--input-bg);
      box-shadow: 0 0 0 3px color-mix(in oklab, var(--input-focus) 25%, transparent);
    }
    .email-input::placeholder { color: var(--text-faint); }

    .text-strong  { color: var(--text-strong); }
    .text-body    { color: var(--text-body); }
    .text-muted   { color: var(--text-muted); }
    .text-faint   { color: var(--text-faint); }
    .text-link    { color: var(--link-accent); }
    .text-link:hover { color: var(--link-hover); }
    .divider-bg   { background: var(--divider); }

    /* ── Hero column (desktop only) ── */
    .hero-feature {
      display: flex; gap: 12px; align-items: flex-start;
      padding: 12px 14px;
      border-radius: 14px;
      background: var(--hint-bg);
      border: 1px solid var(--hint-border);
    }
    .hero-feature-icon {
      flex: 0 0 auto; width: 38px; height: 38px;
      border-radius: 10px;
      display: inline-flex; align-items: center; justify-content: center;
      color: white;
      box-shadow: 0 6px 18px -4px var(--shadow-color, rgba(99,102,241,0.4));
    }
  </style>
</head>
<body class="lg-bg min-h-screen">

  <div class="relative min-h-screen flex items-center justify-center px-4 py-6 sm:py-10">
    <div class="w-full max-w-md lg:max-w-6xl mx-auto">

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10 items-stretch">

        {{-- ════════════════════════════════════════════════════════════
             LEFT COLUMN — hero / branding (desktop only)
             Hidden on mobile because the form column already has its own
             compact hero. Appearing on lg+ uses the wider canvas.
             ════════════════════════════════════════════════════════════ --}}
        <aside class="hidden lg:flex lg:col-span-6 xl:col-span-7 flex-col justify-center anim-in anim-in-1">

          <div class="inline-flex items-center gap-2 mb-5">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg"
                 style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#ec4899 100%);box-shadow:0 12px 30px -8px rgba(124,58,237,0.5);">
              <i class="bi bi-camera-fill text-white text-xl"></i>
            </div>
            <span class="text-strong font-extrabold text-lg tracking-tight">{{ $siteName ?? config('app.name', 'Loadroop') }}</span>
          </div>

          <h1 class="text-strong font-extrabold text-3xl xl:text-4xl leading-tight mb-3 tracking-tight">
            ลงชื่อเข้าใช้ในไม่กี่วินาที <br>
            <span class="bg-gradient-to-r from-indigo-500 via-violet-500 to-pink-500 bg-clip-text text-transparent">
              เริ่มจัดการอีเวนต์ + รายได้
            </span>
          </h1>
          <p class="text-body text-base mb-7 max-w-xl leading-relaxed">
            แพลตฟอร์มสำหรับช่างภาพไทย — ส่งรูปเข้า LINE อัตโนมัติ, ซิงค์ Calendar/Sheets,
            เก็บเงินผ่าน PromptPay/บัตรเครดิต, 0% commission ตั้งแต่ Starter
          </p>

          {{-- Hero feature list — code-truth: every bullet maps to a real
               service in app/Services/, not marketing fluff. --}}
          <div class="space-y-3 max-w-xl">
            <div class="hero-feature" style="--shadow-color: rgba(6,199,85,0.4);">
              <div class="hero-feature-icon" style="background:linear-gradient(135deg,#06C755,#00b04f);">
                <i class="bi bi-line text-xl"></i>
              </div>
              <div class="min-w-0">
                <div class="text-strong font-bold text-sm">LINE-native delivery</div>
                <div class="text-muted text-xs leading-relaxed">รูปทุกใบส่งให้ลูกค้าผ่าน LINE อัตโนมัติหลังจ่ายเงิน · ไม่ต้องตอบ chat</div>
              </div>
            </div>
            <div class="hero-feature" style="--shadow-color: rgba(66,133,244,0.4);">
              <div class="hero-feature-icon" style="background:linear-gradient(135deg,#4285F4,#1a73e8);">
                <i class="bi bi-calendar2-week-fill"></i>
              </div>
              <div class="min-w-0">
                <div class="text-strong font-bold text-sm">Google Calendar 2-way sync</div>
                <div class="text-muted text-xs leading-relaxed">สร้างคิวงานในเว็บ → ขึ้น Calendar ทันที · แก้ใน Calendar ก็ sync กลับ</div>
              </div>
            </div>
            <div class="hero-feature" style="--shadow-color: rgba(245,158,11,0.4);">
              <div class="hero-feature-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                <i class="bi bi-cash-coin"></i>
              </div>
              <div class="min-w-0">
                <div class="text-strong font-bold text-sm">0% commission · auto-payout</div>
                <div class="text-muted text-xs leading-relaxed">เก็บเต็ม 100% ใน paid tier · จ่ายเข้าบัญชีอัตโนมัติทุกวันจันทร์</div>
              </div>
            </div>
          </div>
        </aside>

        {{-- ════════════════════════════════════════════════════════════
             RIGHT COLUMN — auth form
             ════════════════════════════════════════════════════════════ --}}
        <main class="lg:col-span-6 xl:col-span-5 anim-in anim-in-2">
          <div class="lg-card rounded-3xl shadow-[0_30px_60px_-20px_rgba(0,0,0,0.5)]">
            <div class="p-6 sm:p-8">

              {{-- Compact hero — visible on ALL screens (the wide hero
                   on the left is desktop only, so mobile users still see
                   a brand strip here). --}}
              <header class="text-center mb-5 anim-in anim-in-2">
                <div class="lg:hidden inline-flex items-center justify-center w-14 h-14 rounded-2xl shadow-lg mb-3"
                     style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#ec4899 100%);box-shadow:0 12px 30px -8px rgba(124,58,237,0.5);">
                  <i class="bi bi-camera-fill text-white text-2xl"></i>
                </div>
                <h2 class="text-xl sm:text-2xl font-extrabold text-strong tracking-tight mb-1">
                  Photographer Panel
                </h2>
                <p class="text-muted text-sm">เข้าสู่ระบบจัดการอีเวนต์และรายได้</p>
              </header>

              {{-- ── Flash messages ──────────────────────────────────── --}}
              @php
                $_flashes = [
                    'logout_success' => ['icon' => 'check-circle-fill',          'rgb' => '16,185,129',  'text' => '#34d399'],
                    'success'        => ['icon' => 'check-circle-fill',          'rgb' => '16,185,129',  'text' => '#34d399'],
                    'info'           => ['icon' => 'info-circle-fill',           'rgb' => '14,165,233',  'text' => '#38bdf8'],
                    'warning'        => ['icon' => 'exclamation-triangle-fill',  'rgb' => '245,158,11',  'text' => '#fbbf24'],
                ];
              @endphp
              @foreach($_flashes as $_key => $_meta)
                @if(session($_key))
                  <div class="rounded-xl p-3 text-sm mb-3 anim-in anim-in-3 border"
                       style="background:rgba({{ $_meta['rgb'] }},0.12);
                              color:{{ $_meta['text'] }};
                              border-color:rgba({{ $_meta['rgb'] }},0.30);">
                    <i class="bi bi-{{ $_meta['icon'] }} me-1"></i>{{ session($_key) }}
                  </div>
                @endif
              @endforeach

              @if($errors->any())
                <div class="rounded-xl p-3 text-sm mb-3 anim-in anim-in-3 border"
                     style="background:rgba(239,68,68,0.12); color:#fca5a5; border-color:rgba(239,68,68,0.32);">
                  <i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first() }}
                </div>
              @endif

              {{-- ── Social login (PRIMARY) ──────────────────────────── --}}
              @php
                $primaryOAuth = \App\Models\AppSetting::get('photographer_primary_oauth_provider', 'both');
                $showGoogle = in_array($primaryOAuth, ['google', 'both'], true);
                $showLine   = in_array($primaryOAuth, ['line',   'both'], true);
              @endphp

              @if($showLine || $showGoogle)
              <div class="space-y-3 mb-5">

                @if($showLine)
                <a href="{{ route('photographer.auth.redirect', ['provider' => 'line']) }}"
                   class="provider-card anim-in anim-in-3 relative block p-4 rounded-2xl border pulse-line"
                   style="background:linear-gradient(165deg,rgba(6,199,85,.18) 0%,rgba(6,199,85,.05) 100%);border-color:rgba(6,199,85,.45);">
                  <div class="ribbon">แนะนำ</div>
                  <div class="flex items-center gap-3">
                    <div class="shrink-0 w-12 h-12 rounded-xl flex items-center justify-center shadow-lg"
                         style="background:linear-gradient(135deg,#06C755,#00b04f);">
                      <i class="bi bi-line text-white text-2xl"></i>
                    </div>
                    <div class="flex-1 min-w-0 text-left">
                      <div class="text-strong font-extrabold text-base leading-tight">เข้าสู่ระบบด้วย LINE</div>
                      <div class="text-muted text-[12px] font-medium mt-0.5">รับแจ้งเตือน · คุยกับลูกค้า · ส่งรูปอัตโนมัติ</div>
                    </div>
                    <i class="bi bi-arrow-right-circle-fill text-emerald-400 text-xl shrink-0"></i>
                  </div>
                </a>
                @endif

                @if($showGoogle)
                <a href="{{ route('photographer.auth.redirect', ['provider' => 'google']) }}"
                   class="provider-card anim-in anim-in-4 relative block p-4 rounded-2xl border pulse-google"
                   style="background:linear-gradient(165deg,rgba(66,133,244,.18) 0%,rgba(66,133,244,.05) 100%);border-color:rgba(66,133,244,.40);">
                  <div class="flex items-center gap-3">
                    <div class="shrink-0 w-12 h-12 rounded-xl flex items-center justify-center shadow-lg bg-white">
                      <svg viewBox="0 0 24 24" class="w-6 h-6" aria-hidden="true">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0 text-left">
                      <div class="text-strong font-extrabold text-base leading-tight">เข้าสู่ระบบด้วย Google</div>
                      <div class="text-muted text-[12px] font-medium mt-0.5">ซิงค์ Calendar · Export Sheets · SSO</div>
                    </div>
                    <i class="bi bi-arrow-right-circle-fill text-blue-400 text-xl shrink-0"></i>
                  </div>
                </a>
                @endif

              </div>
              @endif

              {{-- ── Divider ─────────────────────────────────────────── --}}
              <div class="flex items-center gap-3 my-5 anim-in anim-in-4">
                <div class="flex-1 h-px divider-bg"></div>
                <span class="text-faint text-[11px] font-bold tracking-wider uppercase">หรือใช้อีเมล</span>
                <div class="flex-1 h-px divider-bg"></div>
              </div>

              {{-- ── Email/Password (collapsible) ───────────────────── --}}
              <div class="anim-in anim-in-4"
                   x-data="{ open: {{ $errors->any() || old('email') ? 'true' : 'false' }} }">

                <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border text-body hover:bg-white/5 transition"
                        style="background:var(--hint-bg);border-color:var(--hint-border);">
                  <span class="text-sm font-semibold flex items-center gap-2">
                    <i class="bi bi-envelope-at-fill text-muted"></i>
                    เข้าสู่ระบบด้วยอีเมล
                  </span>
                  <i class="bi text-muted" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </button>

                <div x-show="open" x-collapse class="mt-3">
                  <form method="POST" action="{{ route('photographer.login.post') }}" class="space-y-3">
                    @csrf

                    <div>
                      <label class="block text-xs font-bold text-muted mb-1.5">อีเมล</label>
                      <div class="relative">
                        <i class="bi bi-envelope absolute left-3 top-1/2 -translate-y-1/2 text-faint"></i>
                        <input type="email" name="email" required
                               value="{{ old('email') }}"
                               autocomplete="email"
                               class="email-input w-full pl-10 pr-4 py-2.5 rounded-xl text-sm"
                               placeholder="you@example.com">
                      </div>
                    </div>

                    <div>
                      <div class="flex items-baseline justify-between mb-1.5">
                        <label class="block text-xs font-bold text-muted">รหัสผ่าน</label>
                        <a href="{{ route('photographer.password.request') }}"
                           class="text-[11px] text-link transition">ลืมรหัสผ่าน?</a>
                      </div>
                      <div class="relative" x-data="{ show: false }">
                        <i class="bi bi-lock absolute left-3 top-1/2 -translate-y-1/2 text-faint"></i>
                        <input :type="show ? 'text' : 'password'" name="password" required
                               autocomplete="current-password"
                               class="email-input w-full pl-10 pr-10 py-2.5 rounded-xl text-sm"
                               placeholder="••••••••">
                        <button type="button" @click="show = !show"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-faint hover:opacity-80">
                          <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                        </button>
                      </div>
                    </div>

                    <button type="submit"
                            class="w-full py-2.5 rounded-xl text-sm font-bold text-white shadow-lg transition hover:-translate-y-px"
                            style="background:linear-gradient(135deg,#4f46e5,#7c3aed);box-shadow:0 8px 24px -8px rgba(124,58,237,0.5);">
                      <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
                    </button>
                  </form>
                </div>
              </div>

              {{-- ── Footer links ───────────────────────────────────── --}}
              <div class="border-t mt-6 pt-4 text-center space-y-2 anim-in anim-in-4"
                   style="border-color: var(--divider);">
                <div class="text-sm">
                  <span class="text-muted">ยังไม่มีบัญชี?</span>
                  <a href="{{ route('photographer.register') }}" class="font-bold text-link transition ml-1">
                    สมัครเป็นช่างภาพ
                  </a>
                </div>
                <div>
                  <a href="{{ url('/') }}" class="text-[12px] text-faint hover:opacity-80 transition inline-flex items-center gap-1.5">
                    <i class="bi bi-arrow-left"></i> กลับหน้าเว็บไซต์
                  </a>
                </div>
              </div>

            </div>
          </div>
        </main>
      </div>
    </div>
  </div>

</body>
</html>
