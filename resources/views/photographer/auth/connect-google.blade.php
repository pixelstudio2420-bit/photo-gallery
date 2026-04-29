{{-- ════════════════════════════════════════════════════════════════════════
     /photographer/connect-google — final onboarding gate.

     Why this page is *not* extending layouts.app:
     The photographer hasn't yet linked a social account, so the standard
     navbar (which shows the photographer's avatar/menu) would render with
     half-empty state and look broken. Standalone HTML keeps the visual
     focus on the single decision: pick LINE or Google.

     Feature lists below are SOURCE-OF-TRUTH from the codebase:
       LINE  →  LineNotifyService::queuePushToUser  (booking notify)
                LineWebhookProcessor inbound chat   (customer support)
                DeliverOrderViaLineJob              (auto photo delivery)
       Google → GoogleCalendarSyncService          (2-way booking sync)
                GoogleSheetsExportService          (auto booking spreadsheet)
                Photographer\SocialAuthController  (OAuth SSO)

     The previous copy claimed "ส่งใบเสร็จผ่าน Gmail" — that was wrong;
     receipts go through MailService (per-event SMTP), not per-photographer
     Gmail. Fixed in this revision.
═══════════════════════════════════════════════════════════════════════ --}}
<!DOCTYPE html>
<html lang="th" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>เชื่อมต่อบัญชี · {{ $siteName ?? config('app.name', 'Loadroop') }}</title>
  <meta name="robots" content="noindex, nofollow">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  @vite(['resources/css/app.css'])

  <style>
    :root { font-family: 'Noto Sans Thai', system-ui, sans-serif; }

    /* Animated dotted gradient background — performant (CSS-only, no JS).
       On mobile we scale down the radial gradients so they don't dominate
       the small viewport and cause GPU paint jank on lower-end phones. */
    .cg-bg {
      background:
        radial-gradient(1100px 600px at 12% -10%,  rgba(99,102,241,0.30), transparent 65%),
        radial-gradient(900px  500px at 88%  10%,  rgba(236,72,153,0.22), transparent 65%),
        radial-gradient(800px  600px at 50% 110%,  rgba(6,199,85,0.18),    transparent 65%),
        linear-gradient(160deg, #020617 0%, #0f172a 50%, #1e1b4b 100%);
    }
    @media (max-width: 480px) {
      .cg-bg {
        background:
          radial-gradient(700px 400px at 50% -10%,  rgba(99,102,241,0.30), transparent 65%),
          radial-gradient(600px 400px at 50% 110%,  rgba(6,199,85,0.18),   transparent 65%),
          linear-gradient(160deg, #020617 0%, #0f172a 50%, #1e1b4b 100%);
      }
    }

    /* Glass card — uses backdrop-filter where supported, falls back to
       plain dark background otherwise. */
    .cg-card {
      background: rgba(15, 23, 42, 0.78);
      -webkit-backdrop-filter: blur(22px) saturate(180%);
      backdrop-filter: blur(22px) saturate(180%);
      border: 1px solid rgba(255, 255, 255, 0.10);
    }

    /* Provider card hover lift — smooth + subtle shadow change. Disabled
       on touch devices because the tap-and-hold "hover" lift looks weird
       and doesn't release smoothly. */
    .provider-card {
      transition: transform .25s cubic-bezier(.34,1.56,.64,1),
                  box-shadow .25s ease,
                  border-color .25s ease;
    }
    @media (hover: hover) {
      .provider-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 22px 50px -12px rgba(0,0,0,0.5);
      }
    }
    .provider-card:active { transform: scale(0.98); }

    /* Recommended ribbon — anchored top-right of LINE card. Pulled in a
       bit on mobile so it doesn't clip the rounded card edge. */
    .ribbon {
      position: absolute; top: -10px; right: 14px;
      background: linear-gradient(135deg, #fbbf24, #f59e0b);
      color: #78350f; font-weight: 800;
      padding: 3px 12px; border-radius: 999px;
      font-size: 10px; letter-spacing: .04em; text-transform: uppercase;
      box-shadow: 0 4px 14px -2px rgba(245, 158, 11, .55);
      z-index: 1;
    }
    @media (max-width: 480px) {
      .ribbon { top: -8px; right: 10px; padding: 2px 10px; font-size: 9px; }
    }

    /* Pulse glow that hints clickability — different colors per brand.
       Reduced ring radius on mobile to keep the effect inside the
       viewport on cramped layouts. */
    @keyframes pulse-glow {
      0%, 100% { box-shadow: 0 0 0 0 var(--glow); }
      50%      { box-shadow: 0 0 0 18px transparent; }
    }
    @keyframes pulse-glow-sm {
      0%, 100% { box-shadow: 0 0 0 0 var(--glow); }
      50%      { box-shadow: 0 0 0 10px transparent; }
    }
    .pulse-line   { --glow: rgba(6,199,85,0.35);  animation: pulse-glow 3s ease-in-out infinite; }
    .pulse-google { --glow: rgba(66,133,244,0.32); animation: pulse-glow 3s ease-in-out infinite; }
    @media (max-width: 480px) {
      .pulse-line, .pulse-google { animation-name: pulse-glow-sm; }
    }

    /* Stagger entry. */
    @keyframes float-in {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .anim-in    { animation: float-in .55s cubic-bezier(0.34,1.56,0.64,1) both; }
    .anim-in-1  { animation-delay: .05s; }
    .anim-in-2  { animation-delay: .15s; }
    .anim-in-3  { animation-delay: .25s; }
    .anim-in-4  { animation-delay: .35s; }
    @media (prefers-reduced-motion: reduce) {
      .anim-in, .pulse-line, .pulse-google { animation: none !important; }
    }

    /* Feature row — scales down on small phones so 4 lines fit per card
       without forcing the user to scroll inside the card. */
    .feature-row {
      display: flex; align-items: flex-start; gap: 8px;
      font-size: 12.5px; line-height: 1.5;
      color: rgba(226, 232, 240, 0.88);
    }
    .feature-row i { font-size: 13px; margin-top: 2px; flex: 0 0 auto; }
    @media (max-width: 480px) {
      .feature-row    { font-size: 12px; gap: 7px; line-height: 1.45; }
      .feature-row i  { font-size: 12px; }
    }

    /* Connect button — guaranteed 44px touch target per Apple HIG and
       Material accessibility specs. */
    .connect-btn {
      min-height: 44px;
    }

    /* Trust strip — subtle bottom note about scopes. */
    .trust { background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.06); }
  </style>
</head>
<body class="cg-bg min-h-screen flex items-center justify-center py-4 px-3 sm:py-8 sm:px-4">

  <div class="relative z-10 w-full max-w-[700px] cg-card rounded-2xl sm:rounded-3xl shadow-[0_30px_60px_-20px_rgba(0,0,0,0.6)] anim-in">
    <div class="p-4 sm:p-6 md:p-8 lg:p-10">

      {{-- ── Hero ─────────────────────────────────────────────────── --}}
      <header class="text-center mb-5 sm:mb-7 anim-in anim-in-1">
        <div class="inline-flex items-center gap-1.5 sm:gap-2 mb-3 sm:mb-4">
          <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl flex items-center justify-center shadow-lg" style="background:linear-gradient(135deg,#06C755,#00b04f);box-shadow:0 10px 24px -6px rgba(6,199,85,0.5);">
            <svg viewBox="0 0 24 24" class="w-5 h-5 sm:w-6 sm:h-6" fill="#fff" aria-hidden="true"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zM24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
          </div>
          <span class="text-slate-500 text-base sm:text-xl font-light px-0.5 sm:px-1">หรือ</span>
          <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl flex items-center justify-center shadow-lg bg-white">
            <svg viewBox="0 0 24 24" class="w-5 h-5 sm:w-6 sm:h-6" aria-hidden="true">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
          </div>
        </div>

        <h1 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-white tracking-tight mb-1.5 sm:mb-2 leading-snug">
          @if($userName)
            สวัสดี <span class="bg-gradient-to-r from-emerald-400 to-sky-400 bg-clip-text text-transparent">{{ $userName }}</span>
          @else
            ขั้นตอนสุดท้าย — เกือบเสร็จแล้ว
          @endif
        </h1>
        <p class="text-slate-300 text-[13px] sm:text-sm md:text-base leading-relaxed max-w-md mx-auto px-2">
          เลือกเชื่อมต่อ <strong class="text-emerald-400">LINE</strong> หรือ <strong class="text-blue-300">Google</strong>
          เพื่อปลดล็อกฟีเจอร์ครบ
        </p>
        <p class="text-slate-500 text-[11px] sm:text-[12px] mt-1 px-2">
          เชื่อมแค่ <strong>1 อย่างก็ใช้งานได้</strong> · เพิ่มอีกอันภายหลังได้ทุกเมื่อ
        </p>
      </header>

      {{-- ── 2 Provider Cards ─────────────────────────────────────── --}}
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 mb-4 sm:mb-5">

        {{-- ───────── LINE ───────── --}}
        <a href="{{ route('photographer.auth.redirect', ['provider' => 'line']) }}"
           class="provider-card anim-in anim-in-2 relative block p-4 sm:p-5 rounded-xl sm:rounded-2xl border pulse-line"
           style="background:linear-gradient(165deg,rgba(6,199,85,.14) 0%,rgba(6,199,85,.04) 100%);border-color:rgba(6,199,85,.4);">

          <div class="ribbon">แนะนำ</div>

          <div class="flex items-center gap-2.5 sm:gap-3 mb-3 sm:mb-4">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl flex items-center justify-center shadow-lg shrink-0"
                 style="background:linear-gradient(135deg,#06C755,#00b04f);">
              <svg viewBox="0 0 24 24" class="w-5 h-5 sm:w-6 sm:h-6" fill="#fff" aria-hidden="true"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zM24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-white font-extrabold text-base sm:text-lg leading-tight">LINE</div>
              <div class="text-emerald-300/80 text-[11px] sm:text-[11.5px] font-medium">เหมาะกับช่างภาพไทย</div>
            </div>
          </div>

          <ul class="space-y-1.5 sm:space-y-2 mb-3 sm:mb-4">
            <li class="feature-row"><i class="bi bi-bell-fill text-emerald-400"></i><span>แจ้งเตือนทุก booking ใหม่ทาง LINE</span></li>
            <li class="feature-row"><i class="bi bi-chat-dots-fill text-emerald-400"></i><span>คุยกับลูกค้า · ตอบ chat ที่เข้าจาก LINE OA</span></li>
            <li class="feature-row"><i class="bi bi-camera-fill text-emerald-400"></i><span>ลูกค้ารับรูปทาง LINE อัตโนมัติหลังจ่ายเงิน</span></li>
            <li class="feature-row"><i class="bi bi-check2-circle text-emerald-400"></i><span>ยืนยันตัวตน 1 คลิก ไม่ต้องจำรหัส</span></li>
          </ul>

          <div class="connect-btn text-center py-3 sm:py-2.5 rounded-xl text-white font-bold text-sm shadow-lg flex items-center justify-center gap-2"
               style="background:linear-gradient(135deg,#06C755 0%,#00b04f 100%);box-shadow:0 8px 24px -6px rgba(6,199,85,0.55);">
            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="#fff" aria-hidden="true"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zM24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
            <span>เชื่อมต่อ LINE</span>
          </div>
        </a>

        {{-- ───────── Google ───────── --}}
        <a href="{{ route('photographer.auth.redirect', ['provider' => 'google']) }}"
           class="provider-card anim-in anim-in-3 relative block p-4 sm:p-5 rounded-xl sm:rounded-2xl border pulse-google"
           style="background:linear-gradient(165deg,rgba(66,133,244,.13) 0%,rgba(66,133,244,.04) 100%);border-color:rgba(66,133,244,.35);">

          <div class="flex items-center gap-2.5 sm:gap-3 mb-3 sm:mb-4">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl flex items-center justify-center shadow-lg bg-white shrink-0">
              <svg viewBox="0 0 24 24" class="w-5 h-5 sm:w-6 sm:h-6" aria-hidden="true">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-white font-extrabold text-base sm:text-lg leading-tight">Google</div>
              <div class="text-blue-200/80 text-[11px] sm:text-[11.5px] font-medium">workflow + productivity</div>
            </div>
          </div>

          <ul class="space-y-1.5 sm:space-y-2 mb-3 sm:mb-4">
            <li class="feature-row"><i class="bi bi-calendar2-week-fill text-sky-400"></i><span>ซิงค์คิวงานกับ Google Calendar (2 ทาง)</span></li>
            <li class="feature-row"><i class="bi bi-file-earmark-spreadsheet-fill text-sky-400"></i><span>Export booking ลง Google Sheets อัตโนมัติ</span></li>
            <li class="feature-row"><i class="bi bi-shield-check text-sky-400"></i><span>เข้าสู่ระบบด้วย Google · ไม่ต้องจำรหัส</span></li>
            <li class="feature-row"><i class="bi bi-arrow-counterclockwise text-sky-400"></i><span>แก้คิวใน Calendar → ระบบอัปเดตให้</span></li>
          </ul>

          <div class="connect-btn text-center py-3 sm:py-2.5 rounded-xl text-white font-bold text-sm shadow-lg flex items-center justify-center gap-2"
               style="background:linear-gradient(135deg,#4285F4 0%,#1a73e8 100%);box-shadow:0 8px 24px -6px rgba(66,133,244,0.55);">
            <svg viewBox="0 0 24 24" class="w-4 h-4" aria-hidden="true">
              <path fill="#fff" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" opacity=".95"/>
              <path fill="#fff" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" opacity=".95"/>
            </svg>
            <span>เชื่อมต่อ Google</span>
          </div>
        </a>
      </div>

      {{-- ── Comparison hint (helps undecided users) ──────────────── --}}
      <div class="trust rounded-lg sm:rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 mb-4 sm:mb-5 anim-in anim-in-4">
        <div class="flex items-start gap-2 sm:gap-3">
          <i class="bi bi-info-circle-fill text-amber-400 text-sm sm:text-base mt-0.5 shrink-0"></i>
          <div class="text-[12px] sm:text-[12.5px] text-slate-300 leading-relaxed">
            <strong class="text-white">เลือกอย่างไหนดี?</strong>
            ถ้างานหลักคือคุยกับลูกค้าไทย — เลือก <strong class="text-emerald-300">LINE</strong>.
            ถ้าต้องการจัดคิว/รายงานในรูปแบบสเปรดชีต — เลือก <strong class="text-blue-300">Google</strong>.
            <span class="block text-slate-500 mt-1 text-[11px] sm:text-[11.5px]">
              ทั้ง 2 ทางเข้าระบบได้เหมือนกัน — เพิ่มอีกอันเมื่อพร้อมได้ตลอด
            </span>
          </div>
        </div>
      </div>

      {{-- ── Privacy note ─────────────────────────────────────────── --}}
      <div class="text-center text-[11px] sm:text-[11.5px] text-slate-500 mb-4 sm:mb-5 anim-in anim-in-4 px-2 leading-relaxed">
        <i class="bi bi-shield-lock-fill"></i>
        ขออนุญาตเฉพาะข้อมูลที่จำเป็น · ไม่อ่านอีเมล/ไฟล์/แชทส่วนตัว · เพิกถอนได้ทุกเมื่อ
      </div>

      {{-- ── Footer ───────────────────────────────────────────────── --}}
      <div class="border-t border-white/[0.07] pt-3 sm:pt-4 mt-2 text-center anim-in anim-in-4">
        @if($userEmail)
          <div class="text-[11px] sm:text-[11.5px] text-slate-500 mb-2 break-all px-2">
            ลงชื่อเข้าใช้เป็น
            <span class="text-slate-300 font-medium">{{ $userEmail }}</span>
          </div>
        @endif
        <form method="POST" action="{{ route('photographer.logout') }}" class="inline">
          @csrf
          <button type="submit" class="text-[12px] text-slate-500 hover:text-rose-400 transition inline-flex items-center gap-1.5 py-1.5 px-2">
            <i class="bi bi-box-arrow-right"></i> ออกจากระบบ + เริ่มใหม่
          </button>
        </form>
      </div>
    </div>
  </div>

</body>
</html>
