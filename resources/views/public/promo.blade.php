@extends('layouts.app')

@section('title', 'ทำไมต้องเลือกเรา · ขายรูปอีเวนต์ผ่าน LINE + AI หาใบหน้า')

{{-- =======================================================================
     PROMO LANDING PAGE — Thai-market marketing page (v2 redesign)
     -------------------------------------------------------------------
     Pitches the 3 killer USPs:
       USP 1 — LINE Delivery   (จ่ายเงิน → รับรูปเข้า LINE ทันที)
       USP 2 — Face Search AI  (อัปโหลด selfie → เจอตัวเองในงาน)
       USP 3 — Auto-Payout     (โอนเข้าบัญชีช่างภาพอัตโนมัติ)

     Design language: dark-mesh hero, bento mockup, glassmorphism cards,
     animated counters, compact spacing — modern 2025 SaaS aesthetic.
     ====================================================================== --}}

@push('styles')
<style>
  /* ── Mesh gradient hero (dark-mode-first, looks great in light too) ── */
  .pmesh {
    position: relative;
    background:
      radial-gradient(circle at 12% 18%, rgba(6,199,85,0.32), transparent 45%),
      radial-gradient(circle at 88% 12%, rgba(99,102,241,0.32), transparent 50%),
      radial-gradient(circle at 50% 100%, rgba(236,72,153,0.30), transparent 55%),
      linear-gradient(135deg, #0b1024 0%, #1a1d3a 60%, #2a1d4a 100%);
  }
  .pmesh-grid {
    position: absolute; inset: 0; pointer-events: none; opacity: 0.35;
    background-image:
      linear-gradient(rgba(255,255,255,0.06) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.06) 1px, transparent 1px);
    background-size: 36px 36px;
    mask-image: radial-gradient(ellipse 80% 60% at 50% 30%, #000 30%, transparent 80%);
  }
  @keyframes orb-float {
    0%, 100% { transform: translate(0,0) scale(1); }
    50%      { transform: translate(20px,-15px) scale(1.04); }
  }
  .pmesh-orb {
    position: absolute; border-radius: 50%; filter: blur(72px); pointer-events: none;
    animation: orb-float 14s ease-in-out infinite;
  }

  /* ── Glass / bento item ─────────────────────────────────────────── */
  .bento {
    position: relative; overflow: hidden;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border-radius: 24px;
    transition: transform .3s ease, border-color .3s ease, background .3s ease;
  }
  .bento:hover {
    transform: translateY(-3px);
    border-color: rgba(255,255,255,0.22);
    background: rgba(255,255,255,0.09);
  }
  .bento-shine::after {
    content:''; position:absolute; inset:-100% -100% auto auto; width:300%; height:200%;
    background: linear-gradient(45deg, transparent 40%, rgba(255,255,255,0.08) 50%, transparent 60%);
    transform: rotate(35deg); pointer-events:none;
    transition: transform .8s ease;
  }
  .bento:hover .bento-shine::after { transform: rotate(35deg) translateY(60%); }

  /* ── Animated gradient text ────────────────────────────────────── */
  @keyframes shine-text {
    0%, 100% { background-position: 0% 50%; }
    50%      { background-position: 100% 50%; }
  }
  .grad-text {
    background: linear-gradient(135deg, #6ee7b7 0%, #c4b5fd 40%, #f9a8d4 80%);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    animation: shine-text 6s ease-in-out infinite;
  }
  .grad-text-warm {
    background: linear-gradient(135deg, #fde68a 0%, #fca5a5 50%, #f9a8d4 100%);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    animation: shine-text 6s ease-in-out infinite;
  }

  /* ── USP card with subtle accent strip ─────────────────────────── */
  .usp {
    position: relative; overflow: hidden;
    background: white;
    border: 1px solid rgb(226 232 240);
    border-radius: 24px;
    padding: 1.5rem 1.75rem;
    transition: transform .3s ease, box-shadow .3s ease;
  }
  .dark .usp { background: rgb(15 23 42); border-color: rgba(255,255,255,0.08); }
  .usp:hover { transform: translateY(-4px); box-shadow: 0 24px 48px -16px var(--usp-glow, rgba(99,102,241,0.3)); }
  .usp::before {
    content:''; position:absolute; left:0; top:0; right:0; height:3px;
    background: var(--usp-accent, linear-gradient(90deg,#6366f1,#a855f7));
  }
  .usp-line   { --usp-accent: linear-gradient(90deg, #06C755, #00b04f, #6ee7b7); --usp-glow: rgba(6,199,85,0.35); }
  .usp-ai     { --usp-accent: linear-gradient(90deg, #6366f1, #a855f7, #ec4899); --usp-glow: rgba(168,85,247,0.35); }
  .usp-payout { --usp-accent: linear-gradient(90deg, #f59e0b, #ef4444, #f9a8d4); --usp-glow: rgba(245,158,11,0.35); }

  .usp-icon {
    width: 56px; height: 56px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 16px; color: white; font-size: 1.5rem;
    margin-bottom: 1rem;
  }
  .usp-line   .usp-icon { background: linear-gradient(135deg, #06C755, #00b04f); box-shadow: 0 8px 20px -4px rgba(6,199,85,0.5); }
  .usp-ai     .usp-icon { background: linear-gradient(135deg, #6366f1, #a855f7, #ec4899); box-shadow: 0 8px 20px -4px rgba(168,85,247,0.5); }
  .usp-payout .usp-icon { background: linear-gradient(135deg, #f59e0b, #ef4444); box-shadow: 0 8px 20px -4px rgba(245,158,11,0.5); }

  /* ── How-it-works step ─────────────────────────────────────────── */
  .step-dot {
    width: 56px; height: 56px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 50%; color: white; font-weight: 900; font-size: 1.4rem;
    flex-shrink: 0; position: relative;
    box-shadow: 0 12px 32px -8px var(--dot-shadow);
  }
  .step-dot::after {
    content:''; position:absolute; inset:-6px; border-radius:50%;
    border: 2px dashed currentColor; opacity: 0.3; animation: spin-slow 12s linear infinite;
  }
  @keyframes spin-slow { to { transform: rotate(360deg); } }

  /* ── Pricing card ──────────────────────────────────────────────── */
  .pcard {
    position: relative;
    background: white;
    border: 1px solid rgb(226 232 240);
    border-radius: 24px;
    padding: 2rem 1.75rem;
    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
  }
  .dark .pcard { background: rgb(15 23 42); border-color: rgba(255,255,255,0.08); }
  .pcard:hover {
    transform: translateY(-4px);
    box-shadow: 0 24px 48px -12px rgba(99,102,241,0.18);
    border-color: rgb(165 180 252);
  }
  .pcard.is-featured {
    background-image:
      linear-gradient(white, white),
      linear-gradient(135deg, #6366f1, #a855f7, #ec4899);
    background-origin: border-box;
    background-clip: padding-box, border-box;
    border: 2px solid transparent;
    transform: scale(1.02);
    box-shadow: 0 32px 60px -16px rgba(168,85,247,0.30);
  }
  .dark .pcard.is-featured {
    background-image:
      linear-gradient(rgb(15 23 42), rgb(15 23 42)),
      linear-gradient(135deg, #6366f1, #a855f7, #ec4899);
  }
  .pcard.is-featured::before {
    content: '⭐ ขายดีที่สุด';
    position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
    padding: 0.35rem 1rem; border-radius: 9999px;
    background: linear-gradient(135deg, #6366f1, #a855f7, #ec4899);
    color: white; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.06em; white-space: nowrap;
    box-shadow: 0 8px 20px -4px rgba(168,85,247,0.5);
  }

  /* ── Animated counter ──────────────────────────────────────────── */
  @keyframes counter-fade {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .counter { animation: counter-fade .6s ease-out; }

  /* ── Trust logos row ───────────────────────────────────────────── */
  .tlogo {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.65rem 1rem; border-radius: 12px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: white; font-weight: 600; font-size: 0.85rem;
    backdrop-filter: blur(12px); transition: all .25s ease;
  }
  .tlogo:hover { transform: translateY(-2px); background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); }

  /* ── Stagger animation ─────────────────────────────────────────── */
  @keyframes p-fade {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .p-anim { animation: p-fade .7s cubic-bezier(0.34,1.56,0.64,1) both; }
  .p-anim.d1 { animation-delay: 0s; }
  .p-anim.d2 { animation-delay: .1s; }
  .p-anim.d3 { animation-delay: .2s; }
  .p-anim.d4 { animation-delay: .3s; }
  .p-anim.d5 { animation-delay: .4s; }
  .p-anim.d6 { animation-delay: .5s; }

  /* ── Pulse + breath ────────────────────────────────────────────── */
  @keyframes breath {
    0%, 100% { transform: scale(1); opacity: 1; }
    50%      { transform: scale(1.06); opacity: 0.85; }
  }
  .breath { animation: breath 3s ease-in-out infinite; }

  /* ── QR mock ───────────────────────────────────────────────────── */
  .qr-mock {
    aspect-ratio: 1; border-radius: 12px; padding: 8px; background: white;
    background-image:
      linear-gradient(45deg, #000 25%, transparent 25%, transparent 75%, #000 75%),
      linear-gradient(45deg, #000 25%, transparent 25%, transparent 75%, #000 75%);
    background-size: 12px 12px; background-position: 0 0, 6px 6px;
  }

  /* ── Section heading ───────────────────────────────────────────── */
  .sec-tag {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.35rem 0.85rem; border-radius: 9999px;
    font-size: 0.7rem; font-weight: 800; letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  /* ── Wave divider ──────────────────────────────────────────────── */
  .wave-divider svg { display: block; width: 100%; height: auto; }
</style>
@endpush

@section('hero')
{{-- ════════════════════════════════════════════════════════════════════
     1. HERO — dark mesh + bento grid mockup + animated headline
     ════════════════════════════════════════════════════════════════════ --}}
<section class="pmesh relative overflow-hidden">
  <div class="pmesh-grid"></div>
  <div class="pmesh-orb" style="width:380px; height:380px; background:radial-gradient(circle,rgba(110,231,183,0.5),transparent 70%); top:-100px; left:5%;"></div>
  <div class="pmesh-orb" style="width:340px; height:340px; background:radial-gradient(circle,rgba(196,181,253,0.45),transparent 70%); bottom:-80px; right:8%; animation-delay:-5s;"></div>

  <div class="relative max-w-7xl mx-auto px-4 py-12 md:py-16 lg:py-20">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">

      {{-- LEFT: headline --}}
      <div class="lg:col-span-7 text-white p-anim d1">
        <span class="sec-tag bg-white/10 border border-white/20 text-emerald-300 mb-5 backdrop-blur-md">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 breath"></span>
          แพลตฟอร์มภาพอีเวนต์เพื่อคนไทย
        </span>

        <h1 class="font-extrabold text-4xl sm:text-5xl lg:text-6xl xl:text-[5rem] leading-[1.05] tracking-tight mb-5">
          จ่าย <span class="grad-text-warm">PromptPay</span><br>
          รับรูปเข้า <span class="grad-text">LINE</span><br>
          <span class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white/85 inline-flex items-center gap-2 mt-2">
            ใน 5 วินาที <i class="bi bi-lightning-charge-fill text-amber-300"></i>
          </span>
        </h1>

        <p class="text-base sm:text-lg text-white/75 mb-7 max-w-xl leading-relaxed">
          ระบบ <strong class="text-white">AI หาใบหน้า</strong> หารูปคุณในงานวิ่ง · งานแต่ง · รับปริญญา
          จากนับพันใบ ใช้เพียง selfie 1 ใบ
        </p>

        {{-- CTA buttons --}}
        <div class="flex flex-wrap items-center gap-3 mb-8">
          {{-- Unified primary CTA — see home.blade.php for the canonical
               pattern. Uses `rgb(255,255,255)` (not `#ffffff` or `bg-white`)
               to dodge darkmode.css's aggressive [style*="background:#fff"]
               and `.bg-white` !important overrides that re-tint white
               elements to slate-800 on dark mode. --}}
          <a href="{{ route('events.index') }}"
             class="group inline-flex items-center gap-2 px-6 py-3.5 rounded-xl font-bold text-base
                    ring-1 ring-inset ring-indigo-200/50
                    shadow-lg shadow-indigo-900/20
                    hover:-translate-y-0.5 hover:shadow-xl hover:shadow-indigo-900/30 hover:ring-indigo-300
                    transition"
             style="background:rgb(255,255,255);color:#4338ca;">
            <i class="bi bi-search"></i> เริ่มหารูปฟรี
            <i class="bi bi-arrow-right ml-1 group-hover:translate-x-0.5 transition-transform"></i>
          </a>
          <a href="{{ route('photographer.register') }}"
             class="inline-flex items-center gap-2 px-5 py-3.5 rounded-xl font-semibold text-white border border-white/25 bg-white/5 backdrop-blur-md hover:bg-white/10 hover:border-white/40 transition text-sm">
            <i class="bi bi-camera-reels"></i> สำหรับช่างภาพ
          </a>
        </div>

        {{-- Inline value strip --}}
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-white/70">
          <span class="flex items-center gap-1.5"><i class="bi bi-check-circle-fill text-emerald-400"></i> ไม่ต้องลงแอป</span>
          <span class="flex items-center gap-1.5"><i class="bi bi-check-circle-fill text-emerald-400"></i> 0% commission</span>
          <span class="flex items-center gap-1.5"><i class="bi bi-check-circle-fill text-emerald-400"></i> ใบกำกับภาษีอัตโนมัติ</span>
          <span class="flex items-center gap-1.5"><i class="bi bi-check-circle-fill text-emerald-400"></i> ยกเลิกได้ทุกเมื่อ</span>
        </div>
      </div>

      {{-- RIGHT: bento mockup grid (4 floating cards) --}}
      <div class="lg:col-span-5 p-anim d3">
        <div class="grid grid-cols-2 grid-rows-3 gap-3 h-[400px] md:h-[480px]">

          {{-- Card 1: LINE chat — spans 2 rows --}}
          <div class="bento bento-shine row-span-2 p-4 flex flex-col justify-between">
            <div>
              <div class="flex items-center gap-2 mb-3">
                <div class="w-9 h-9 rounded-xl bg-[#06C755] flex items-center justify-center text-white">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zM24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
                </div>
                <div class="text-white text-xs font-bold">{{ $siteName ?? config('app.name') }}</div>
              </div>
              <div class="space-y-2">
                <div class="bg-white rounded-xl rounded-bl-sm px-3 py-2 text-[11px] text-slate-800 shadow-sm">
                  <div class="font-bold text-emerald-600 mb-0.5">
                    <i class="bi bi-check-circle-fill"></i> ชำระแล้ว
                  </div>
                  ออเดอร์ #ORD-0142 ส่งรูปแล้วค่ะ
                </div>
                <div class="grid grid-cols-2 gap-1">
                  <div class="aspect-square rounded-lg bg-gradient-to-br from-amber-400 to-rose-500 flex items-center justify-center text-white"><i class="bi bi-image"></i></div>
                  <div class="aspect-square rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white"><i class="bi bi-image"></i></div>
                  <div class="aspect-square rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white"><i class="bi bi-image"></i></div>
                  <div class="aspect-square rounded-lg bg-gradient-to-br from-pink-500 to-fuchsia-600 flex items-center justify-center text-white"><i class="bi bi-image"></i></div>
                </div>
              </div>
            </div>
            <div class="text-white text-[10px] opacity-75 flex items-center gap-1">
              <i class="bi bi-chat-dots"></i> รูปเด้งเข้า LINE ทันที
            </div>
          </div>

          {{-- Card 2: PromptPay QR --}}
          <div class="bento bento-shine p-3 flex flex-col items-center justify-center">
            <div class="qr-mock w-12 mb-2"></div>
            <div class="text-white text-[10px] font-bold opacity-90">PromptPay</div>
            <div class="text-emerald-300 text-sm font-extrabold">฿599</div>
          </div>

          {{-- Card 3: Face AI --}}
          <div class="bento bento-shine p-3 flex flex-col items-center justify-center text-center">
            <div class="relative w-12 h-12 rounded-full bg-gradient-to-br from-violet-500 to-pink-500 flex items-center justify-center text-white mb-1.5 breath">
              <i class="bi bi-person-bounding-box text-lg"></i>
              <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-emerald-400 border-2 border-slate-900 flex items-center justify-center text-[8px] text-white font-bold">✓</span>
            </div>
            <div class="text-white text-[10px] font-bold">AI หาใบหน้า</div>
            <div class="text-emerald-300 text-[10px]">AWS Rekognition</div>
          </div>

          {{-- Card 4: Stat counter — spans 2 cols. Numbers come straight
               from the live aggregate query (HomeController::promo); the
               trailing "+" was removed because it implied "more than X"
               while the value is the exact count. Honest = exact. --}}
          <div class="bento bento-shine col-span-2 p-3 flex items-center justify-around"
               x-data="{ shown: false }"
               x-intersect.once="shown = true">
            <div class="text-center">
              <div class="text-xl md:text-2xl font-extrabold text-white" x-show="shown" x-transition>{{ number_format($stats['photographers']) }}</div>
              <div class="text-[9px] text-white/65 uppercase tracking-widest font-bold">ช่างภาพ</div>
            </div>
            <div class="w-px h-8 bg-white/15"></div>
            <div class="text-center">
              <div class="text-xl md:text-2xl font-extrabold text-white" x-show="shown" x-transition>{{ number_format($stats['events']) }}</div>
              <div class="text-[9px] text-white/65 uppercase tracking-widest font-bold">อีเวนต์</div>
            </div>
            <div class="w-px h-8 bg-white/15"></div>
            <div class="text-center">
              <div class="text-xl md:text-2xl font-extrabold text-white" x-show="shown" x-transition>{{ number_format($stats['orders']) }}</div>
              <div class="text-[9px] text-white/65 uppercase tracking-widest font-bold">ออเดอร์</div>
            </div>
          </div>

        </div>
      </div>

    </div>
  </div>

  {{-- Soft fade-out into content --}}
  <div class="absolute bottom-0 left-0 right-0 h-24 pointer-events-none bg-gradient-to-t from-white dark:from-slate-950 to-transparent"></div>
</section>
@endsection

@section('content')

{{-- ════════════════════════════════════════════════════════════════════
     2. THREE USP CARDS
     ════════════════════════════════════════════════════════════════════ --}}
<section class="mb-14 md:mb-20 -mt-4">
  <div class="text-center mb-8 md:mb-10 p-anim d1">
    <span class="sec-tag bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300">
      <i class="bi bi-stars"></i> 3 จุดเด่นของเรา
    </span>
    <h2 class="text-3xl md:text-4xl xl:text-5xl font-extrabold text-slate-900 dark:text-white mt-3 tracking-tight">
      ทำไมลูกค้าไทย<span class="bg-gradient-to-r from-emerald-500 via-violet-500 to-pink-500 bg-clip-text text-transparent">เลือกเรา</span>
    </h2>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">

    {{-- USP 1 — LINE delivery --}}
    <div class="usp usp-line p-anim d2">
      <div class="usp-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="white"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zM24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
      </div>
      <h3 class="text-xl font-extrabold text-slate-900 dark:text-white mb-2 leading-tight">รูปเข้า LINE ทันที</h3>
      <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
        จ่ายผ่าน <strong>PromptPay/LINE Pay</strong> → ลิงก์ดาวน์โหลดและรูปทั้งหมดเด้งเข้า LINE ภายใน 5 วินาที
      </p>
      <div class="mt-4 flex items-center gap-2 text-xs text-emerald-700 dark:text-emerald-400 font-semibold">
        <i class="bi bi-arrow-right-circle-fill"></i> Login + Push + Rich Menu ครบ
      </div>
    </div>

    {{-- USP 2 — AI Face Search --}}
    <div class="usp usp-ai p-anim d3">
      <div class="usp-icon">
        <i class="bi bi-person-bounding-box"></i>
      </div>
      <h3 class="text-xl font-extrabold text-slate-900 dark:text-white mb-2 leading-tight">AI หาใบหน้าตัวเอง</h3>
      <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
        อัปโหลด <strong>selfie 1 ใบ</strong> → ระบบ AI วิเคราะห์ภาพหารูปคุณในงาน 1,000+ ใบ ภายใน 10 วินาที
      </p>
      <div class="mt-4 flex items-center gap-2 text-xs text-violet-700 dark:text-violet-300 font-semibold">
        <i class="bi bi-arrow-right-circle-fill"></i> Auto-tag + คัดรูปเบลอ + Best Shot
      </div>
    </div>

    {{-- USP 3 — Auto-Payout --}}
    <div class="usp usp-payout p-anim d4">
      <div class="usp-icon">
        <i class="bi bi-cash-coin"></i>
      </div>
      <h3 class="text-xl font-extrabold text-slate-900 dark:text-white mb-2 leading-tight">โอนเข้าบัญชีไทยตามรอบ</h3>
      <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
        ช่างภาพ<strong>แจ้งถอน</strong>ได้เมื่อยอดถึงขั้นต่ำ — ระบบโอนเข้าบัญชีไทย พร้อม audit trail ทุกรายการ
      </p>
      <div class="mt-4 flex items-center gap-2 text-xs text-amber-700 dark:text-amber-400 font-semibold">
        <i class="bi bi-arrow-right-circle-fill"></i> 0% คอม (Pro/Studio) + ใบเสร็จออนไลน์ทุกออเดอร์
      </div>
    </div>
  </div>
</section>

{{-- ════════════════════════════════════════════════════════════════════
     3. HOW IT WORKS — single-row visual timeline
     ════════════════════════════════════════════════════════════════════ --}}
<section class="mb-14 md:mb-20">
  <div class="rounded-3xl bg-gradient-to-br from-slate-50 to-indigo-50/40 dark:from-slate-900/60 dark:to-indigo-950/40
              border border-slate-200 dark:border-white/10 p-7 md:p-10 lg:p-12 p-anim d1">
    <div class="text-center mb-8">
      <span class="sec-tag bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">
        <i class="bi bi-list-check"></i> ใช้งานง่ายใน 3 ขั้นตอน
      </span>
      <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white mt-3 tracking-tight">
        หา → จ่าย → รับรูปใน LINE
      </h2>
    </div>

    <div class="relative grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
      {{-- Connecting dotted line (desktop only) --}}
      <div class="hidden md:block absolute top-7 left-[16.66%] right-[16.66%] h-0.5 border-t-2 border-dashed border-slate-300 dark:border-white/20" aria-hidden="true"></div>

      <div class="relative text-center p-anim d2">
        <div class="step-dot bg-gradient-to-br from-indigo-500 to-violet-600 mb-4 mx-auto" style="--dot-shadow: rgba(99,102,241,0.45); color:#6366f1;">1</div>
        <h4 class="font-bold text-base text-slate-900 dark:text-white mb-1.5">อัปโหลด selfie</h4>
        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">AI หารูปทุกใบที่มีหน้าคุณใน 10 วินาที</p>
      </div>

      <div class="relative text-center p-anim d3">
        <div class="step-dot bg-gradient-to-br from-pink-500 to-rose-600 mb-4 mx-auto" style="--dot-shadow: rgba(236,72,153,0.45); color:#ec4899;">2</div>
        <h4 class="font-bold text-base text-slate-900 dark:text-white mb-1.5">สแกนจ่ายเงิน</h4>
        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">PromptPay / LINE Pay / บัตร — ตรวจสลิปอัตโนมัติ</p>
      </div>

      <div class="relative text-center p-anim d4">
        <div class="step-dot bg-gradient-to-br from-[#06C755] to-[#00b04f] mb-4 mx-auto" style="--dot-shadow: rgba(6,199,85,0.45); color:#06C755;">3</div>
        <h4 class="font-bold text-base text-slate-900 dark:text-white mb-1.5">รับรูปเข้า LINE</h4>
        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">รูป HD เด้งเข้า LINE พร้อมใบเสร็จใน 5 วินาที</p>
      </div>
    </div>
  </div>
</section>

{{-- ════════════════════════════════════════════════════════════════════
     4. PRICING — show only 3 most-popular tiers (compact comparison)
     ════════════════════════════════════════════════════════════════════ --}}
@if($photographerPlans->count() > 0)
@php
  // Filter to "showcase" tier set: free + lite + pro (3 cards = no overwhelm)
  // Falls back to first 3 by sort_order if those codes aren't present.
  $showcaseCodes = ['free', 'pro', 'business'];
  $showcase = $photographerPlans->filter(fn($p) => in_array($p->code, $showcaseCodes))->values();
  if ($showcase->count() < 3) $showcase = $photographerPlans->take(3);
@endphp
<section class="mb-14 md:mb-20" id="pricing">
  <div class="text-center mb-8 md:mb-10 p-anim d1">
    <span class="sec-tag bg-pink-100 dark:bg-pink-500/15 text-pink-700 dark:text-pink-300">
      <i class="bi bi-currency-dollar"></i> ราคาที่ตอบโจทย์ทุกงาน
    </span>
    <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white mt-3 tracking-tight">
      เลือกแผน<span class="bg-gradient-to-r from-pink-500 to-violet-500 bg-clip-text text-transparent">ที่ใช่</span>
    </h2>
    <p class="text-sm md:text-base text-slate-600 dark:text-slate-400 mt-2 max-w-xl mx-auto">
      ฟรีตลอดชีพ · 0% commission · ยกเลิกได้ทุกเมื่อ — ไม่มีสัญญาผูกมัด
    </p>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-5 lg:gap-6 max-w-5xl mx-auto">
    @foreach($showcase as $i => $plan)
      @php
        $features = is_string($plan->features_json) ? (json_decode($plan->features_json, true) ?: []) : ($plan->features_json ?: []);
        $isFeatured = $plan->code === 'pro';
      @endphp
      <div class="pcard {{ $isFeatured ? 'is-featured' : '' }} p-anim d{{ $i+2 }}">
        <div class="mb-4">
          <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $plan->name }}</h3>
          @if($plan->tagline)
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-snug">{{ $plan->tagline }}</p>
          @endif
        </div>

        <div class="mb-5 pb-5 border-b border-slate-200 dark:border-white/10">
          <div class="flex items-baseline gap-1">
            <span class="text-4xl font-extrabold text-slate-900 dark:text-white">฿{{ number_format($plan->price_thb) }}</span>
            <span class="text-sm text-slate-500 dark:text-slate-400">/เดือน</span>
          </div>
          @if($plan->price_annual_thb)
            <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-bold mt-1">
              <i class="bi bi-tag-fill"></i> ฿{{ number_format($plan->price_annual_thb) }}/ปี · ประหยัด 2 เดือน
            </div>
          @elseif($plan->price_thb == 0)
            <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium mt-1">ฟรีตลอดชีพ</div>
          @endif
        </div>

        <ul class="space-y-2.5 mb-6 text-sm">
          @foreach(array_slice($features, 0, 5) as $f)
            <li class="flex items-start gap-2 text-slate-700 dark:text-slate-300">
              <i class="bi bi-check-lg {{ $isFeatured ? 'text-violet-500 dark:text-violet-400' : 'text-emerald-500 dark:text-emerald-400' }} mt-0.5 shrink-0 text-base"></i>
              <span class="leading-snug">{{ $f }}</span>
            </li>
          @endforeach
        </ul>

        {{-- CTA — routes through /promo/checkout/{code} which figures out
             where to send the user based on auth state:
               • photographer logged in → subscription plans w/ pre-select
               • customer logged in     → register-as-photographer (claim)
               • anonymous              → register, plan stashed in session
             so the post-signup redirect lands on subscription checkout. --}}
        <a href="{{ route('promo.checkout', ['code' => $plan->code]) }}"
           class="block w-full text-center py-2.5 rounded-xl text-sm font-bold transition
                  {{ $isFeatured
                       ? 'text-white bg-gradient-to-br from-indigo-500 via-violet-500 to-pink-500 hover:shadow-xl hover:shadow-violet-500/40 hover:-translate-y-0.5'
                       : 'text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10' }}">
          {{ $plan->price_thb == 0 ? 'เริ่มฟรีเลย' : 'เลือกแผนนี้' }}
          <i class="bi bi-arrow-right ml-1"></i>
        </a>
      </div>
    @endforeach
  </div>

  @if($photographerPlans->count() > 3)
    <div class="text-center mt-6 p-anim d5">
      <a href="{{ route('photographer.register') }}#pricing"
         class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
        ดูแผนทั้งหมด ({{ $photographerPlans->count() }} แผน) <i class="bi bi-arrow-right"></i>
      </a>
    </div>
  @endif
</section>
@endif

{{-- ════════════════════════════════════════════════════════════════════
     5. TRUST + SOCIAL PROOF (combined, dark panel)
     ════════════════════════════════════════════════════════════════════ --}}
<section class="mb-14 md:mb-20 p-anim d1">
  <div class="relative rounded-3xl overflow-hidden
              bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900
              border border-white/10 px-6 md:px-10 py-10 md:py-12">
    <div class="absolute inset-0 pointer-events-none opacity-25"
         style="background-image: radial-gradient(circle at 10% 30%, rgba(99,102,241,0.4), transparent 50%), radial-gradient(circle at 90% 70%, rgba(6,199,85,0.3), transparent 50%);"></div>

    <div class="relative">
      <div class="text-center mb-8">
        <span class="sec-tag bg-white/10 text-white border border-white/20">
          <i class="bi bi-shield-check"></i> เชื่อถือได้
        </span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-white mt-3 tracking-tight">
          เชื่อมต่อกับระบบที่<span class="grad-text">คนไทยใช้จริง</span>
        </h2>
      </div>

      <div class="flex flex-wrap items-center justify-center gap-2 md:gap-3">
        <span class="tlogo"><i class="bi bi-qr-code-scan"></i> PromptPay</span>
        <span class="tlogo" style="background:rgba(6,199,85,0.15); border-color:rgba(6,199,85,0.35);">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zM24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
          LINE Pay
        </span>
        <span class="tlogo"><i class="bi bi-credit-card-2-front-fill"></i> Visa/Master</span>
        <span class="tlogo"><i class="bi bi-bank"></i> โอนผ่านธนาคาร</span>
        <span class="tlogo"><i class="bi bi-receipt"></i> ใบกำกับภาษี VAT</span>
        <span class="tlogo"><i class="bi bi-cpu-fill"></i> AI วิเคราะห์ภาพ</span>
      </div>

      {{-- Capability strip — replaces the previous fabricated testimonial
           row. Every claim here corresponds to a real implemented code
           path, with the file/class noted in comments so reviewers can
           verify nothing is invented:
             • Face search → FaceSearchService::searchByFaceInCollection
                 (AWS Rekognition, default threshold = 80%)
             • LINE auto-delivery → DeliverOrderViaLineJob (sends after
                 payment success)
             • Auto payout → PayoutService weekly cron, ฿500 minimum,
                 0% commission on Pro/Studio plans                     --}}
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-4 mt-10">
        @php
          $capabilities = [
            [
              'icon'  => 'bi-person-bounding-box',
              'tag'   => 'AI Face Search',
              'title' => 'หาใบหน้า · เกณฑ์ ≥ 80%',
              'body'  => 'อัปโหลดเซลฟี่ 1 ใบ — ระบบจับคู่ใบหน้าทุกภาพในอีเวนต์ผ่าน AWS Rekognition · ใช้เกณฑ์ความเหมือน 80% เป็นค่าเริ่มต้น (ปรับได้)',
              'colors'=> 'from-indigo-400 to-pink-400',
            ],
            [
              'icon'  => 'bi-line',
              'tag'   => 'LINE Auto-Delivery',
              'title' => 'ส่งรูปเข้าไลน์อัตโนมัติ',
              'body'  => 'ลูกค้าจ่ายเสร็จ ระบบส่งไฟล์เต็มเข้าไลน์ทันที — ไม่ต้องส่ง email หรือเปิดเว็บอีกขั้น (เปิดสำหรับช่างภาพแผน Pro ขึ้นไป)',
              'colors'=> 'from-emerald-400 to-cyan-400',
            ],
            [
              'icon'  => 'bi-bank',
              'tag'   => 'Auto Payout',
              'title' => 'เงินเข้าบัญชีอัตโนมัติ',
              'body'  => 'ขั้นต่ำ ฿500 → โอนเข้าบัญชีธนาคารไทยตามรอบ · 0% ค่าคอมมิชชั่นบนแผน Pro / Studio · ทุกออเดอร์มี audit trail',
              'colors'=> 'from-amber-400 to-rose-400',
            ],
          ];
        @endphp
        @foreach($capabilities as $cap)
          <div class="bento p-4 text-white">
            <div class="inline-flex items-center gap-1.5 mb-2 px-2 py-0.5 rounded-full bg-white/10 text-[10px] font-bold uppercase tracking-[0.12em] text-white/85">
              <i class="bi {{ $cap['icon'] }}"></i> {{ $cap['tag'] }}
            </div>
            <p class="text-sm font-bold text-white leading-tight mt-1">{{ $cap['title'] }}</p>
            <p class="text-xs text-white/75 leading-relaxed mt-2">{{ $cap['body'] }}</p>
            <div class="flex items-center gap-2 pt-3 mt-3 border-t border-white/10">
              <div class="w-6 h-6 rounded-full bg-gradient-to-br {{ $cap['colors'] }} flex items-center justify-center text-white">
                <i class="bi bi-check2-circle text-[11px]"></i>
              </div>
              <span class="text-[10px] font-semibold text-white/65 uppercase tracking-wider">เปิดใช้งานในระบบ</span>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>

{{-- ════════════════════════════════════════════════════════════════════
     6. FAQ — 4 most-asked questions only (compact accordion)
     ════════════════════════════════════════════════════════════════════ --}}
<section class="mb-14 md:mb-20" x-data="{ open: 0 }">
  <div class="text-center mb-8 p-anim d1">
    <span class="sec-tag bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400">
      <i class="bi bi-question-circle"></i> คำถามที่พบบ่อย
    </span>
    <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white mt-3 tracking-tight">
      มีอะไรอยากรู้เพิ่ม?
    </h2>
  </div>

  <div class="max-w-3xl mx-auto space-y-2.5">
    @php
      $faqs = [
        ['q' => 'AI หาใบหน้าแม่นยำแค่ไหน?', 'a' => 'ระบบใช้ AWS Rekognition (บริการ enterprise ของ Amazon) จับคู่ด้วยเกณฑ์ความเหมือน 80% เป็นค่าเริ่มต้น · ใส่หมวก / แว่น / มุมเฉียง ส่วนใหญ่ยังจับคู่ได้ · ปรับเกณฑ์ขั้นต่ำได้ตามต้องการ · ภาพ selfie ของคุณไม่ถูกเก็บไว้ — ใช้แล้วลบทิ้งทันที'],
        ['q' => 'จ่ายเงินทางไหนได้บ้าง?', 'a' => 'PromptPay QR (ทุกธนาคารไทย), LINE Pay, บัตรเครดิต/เดบิต, โอนผ่านธนาคาร, สลิปอัพโหลด · ทุกออเดอร์ได้ใบเสร็จออนไลน์ในระบบ · ใบกำกับภาษีนิติบุคคลขอได้เป็นรายกรณี'],
        ['q' => 'ช่างภาพได้เงินเร็วแค่ไหน?', 'a' => 'แจ้งถอนได้เมื่อยอดสะสมถึงขั้นต่ำที่แอดมินกำหนด (เช่น ฿500) · แอดมินตรวจและโอนเข้าบัญชีไทยตามรอบ · ทุกรายการมี audit trail ตรวจสอบย้อนหลังได้ · 0% คอมมิชชั่นบนแผน Pro/Studio'],
        ['q' => 'ยกเลิกแพ็กได้ตอนไหน?', 'a' => 'ยกเลิกได้ทุกเมื่อจาก dashboard · ไม่มีค่าปรับ · ใช้งานต่อได้จนถึงสิ้นรอบบิล · รูปและข้อมูลทั้งหมดยังอยู่'],
      ];
    @endphp
    @foreach($faqs as $i => $faq)
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 overflow-hidden p-anim d{{ min($i+2, 5) }}">
        <button type="button"
                @click="open = (open === {{ $i+1 }}) ? 0 : {{ $i+1 }}"
                class="w-full flex items-center justify-between gap-3 p-4 text-left hover:bg-slate-50 dark:hover:bg-white/5 transition">
          <span class="font-semibold text-sm md:text-base text-slate-900 dark:text-white pr-2 flex items-center gap-2.5">
            <span class="text-indigo-500 dark:text-indigo-400 font-mono text-xs">0{{ $i+1 }}</span>
            {{ $faq['q'] }}
          </span>
          <i class="bi bi-plus-lg text-slate-400 transition-transform shrink-0 text-lg"
             :class="open === {{ $i+1 }} ? 'rotate-45' : ''"></i>
        </button>
        <div x-show="open === {{ $i+1 }}" x-collapse>
          <div class="px-4 pb-4 pt-0 text-sm text-slate-600 dark:text-slate-400 leading-relaxed pl-12">
            {{ $faq['a'] }}
          </div>
        </div>
      </div>
    @endforeach
  </div>
</section>

{{-- ════════════════════════════════════════════════════════════════════
     7. FINAL CTA — minimal but punchy
     ════════════════════════════════════════════════════════════════════ --}}
<section class="mb-6 p-anim d1">
  <div class="relative rounded-3xl overflow-hidden p-8 md:p-12 lg:p-14 text-center
              bg-gradient-to-br from-indigo-600 via-violet-600 to-pink-600
              shadow-2xl shadow-violet-500/30">
    {{-- Animated mesh overlay --}}
    <div class="absolute inset-0 pointer-events-none"
         style="background:
           radial-gradient(circle at 20% 30%, rgba(255,255,255,0.25), transparent 40%),
           radial-gradient(circle at 80% 70%, rgba(255,255,255,0.18), transparent 45%);"></div>

    <div class="relative">
      <div class="inline-block w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white text-2xl mb-5 breath">
        <i class="bi bi-rocket-takeoff-fill"></i>
      </div>
      <h2 class="text-3xl md:text-5xl font-extrabold text-white mb-3 tracking-tight leading-tight">
        เริ่มใช้งานได้ <span class="grad-text-warm">วันนี้</span>
      </h2>
      <p class="text-base md:text-lg text-white/85 max-w-xl mx-auto mb-7">
        ฟรีตลอดชีพ · ไม่ต้องใช้บัตรเครดิต · อัปเกรดเมื่อพร้อม
      </p>
      <div class="flex flex-wrap items-center justify-center gap-3">
        {{-- Unified primary CTA — same pattern as the events CTA above.
             rgb(255,255,255) intentionally — see notes on first promo CTA. --}}
        <a href="{{ route('auth.register') }}"
           class="group inline-flex items-center gap-2 px-7 py-3.5 rounded-xl font-bold text-base
                  ring-1 ring-inset ring-indigo-200/50
                  shadow-lg shadow-indigo-900/20
                  hover:-translate-y-0.5 hover:shadow-xl hover:shadow-indigo-900/30 hover:ring-indigo-300
                  transition"
           style="background:rgb(255,255,255);color:#4338ca;">
          <i class="bi bi-person-plus-fill"></i> สมัครฟรี
          <i class="bi bi-arrow-right ml-1 group-hover:translate-x-1 transition-transform"></i>
        </a>
        <a href="{{ route('events.index') }}"
           class="inline-flex items-center gap-2 px-7 py-3.5 rounded-xl font-semibold text-white border border-white/30 bg-white/10 backdrop-blur-md hover:bg-white/20 transition text-base">
          <i class="bi bi-grid-3x3-gap-fill"></i> ดูอีเวนต์
        </a>
      </div>
    </div>
  </div>
</section>

@endsection
