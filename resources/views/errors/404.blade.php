@extends('layouts.app')

@section('title', '404 — ไม่พบหน้าที่คุณค้นหา')

@push('styles')
<style>
  /* ══════════════════════════════════════════════════════════════
     404 page — photography-themed, on-brand with the gradient
     palette used elsewhere on the site (indigo → violet → pink).
     ══════════════════════════════════════════════════════════════ */

  /* Page background — soft radial bloom in the brand colours so the
     hero sits on a recognisable, not-blank canvas. */
  .err-404-bg {
    background:
      radial-gradient(900px 500px at 15% -10%, rgba(99,102,241,.18), transparent 65%),
      radial-gradient(750px 500px at 85% 110%, rgba(236,72,153,.14), transparent 60%),
      radial-gradient(600px 400px at 50% 50%, rgba(139,92,246,.10), transparent 65%);
    min-height: calc(100vh - 80px);
  }
  .dark .err-404-bg {
    background:
      radial-gradient(900px 500px at 15% -10%, rgba(99,102,241,.20), transparent 65%),
      radial-gradient(750px 500px at 85% 110%, rgba(236,72,153,.18), transparent 60%),
      radial-gradient(600px 400px at 50% 50%, rgba(139,92,246,.12), transparent 65%),
      linear-gradient(160deg, #0f172a 0%, #1e1b4b 60%, #0f172a 100%);
  }

  /* The big "404" — gradient text fill driven by background-clip so
     the digits inherit the same brand palette as the rest of the site. */
  .err-404-num {
    font-weight: 900;
    line-height: 1;
    letter-spacing: -0.04em;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 45%, #ec4899 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    text-shadow: 0 30px 60px rgba(99,102,241,0.25);
  }

  /* Glass card behind the polaroid stack for depth without a hard
     border that would clash with the gradient bg. */
  .err-404-card {
    background: rgba(255, 255, 255, 0.65);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.55);
  }
  .dark .err-404-card {
    background: rgba(15, 23, 42, 0.65);
    border-color: rgba(255, 255, 255, 0.06);
  }

  /* "Polaroid" ornaments — three photo frames angled around the 404
     to drive the photography theme home. The middle one peeks out
     behind the digits; outer pair tilts left/right for motion. */
  .err-404-polaroid {
    background: #ffffff;
    border-radius: 8px;
    padding: 12px 12px 32px 12px;
    box-shadow:
      0 20px 40px -10px rgba(0,0,0,0.18),
      0 8px 20px -6px rgba(99,102,241,0.18);
    transition: transform .35s cubic-bezier(.34,1.56,.64,1);
  }
  .err-404-polaroid:hover { transform: rotate(0deg) scale(1.03) translateY(-4px); }
  .err-404-polaroid .frame {
    aspect-ratio: 1 / 1;
    border-radius: 4px;
    background: linear-gradient(135deg, #c7d2fe, #f5d0fe);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    font-size: 2rem;
  }
  .dark .err-404-polaroid {
    background: rgb(248 250 252);  /* keep polaroids white in dark mode */
  }

  /* Floating drift — slow vertical bob so the page doesn't feel
     completely static. Disabled for users who prefer reduced motion. */
  @keyframes err-float {
    0%, 100% { transform: translateY(0) rotate(var(--tilt, 0deg)); }
    50%      { transform: translateY(-10px) rotate(var(--tilt, 0deg)); }
  }
  .err-404-float-1 { --tilt: -8deg;  animation: err-float 6s ease-in-out infinite; }
  .err-404-float-2 { --tilt:  4deg;  animation: err-float 7s ease-in-out infinite .8s; }
  .err-404-float-3 { --tilt: -3deg;  animation: err-float 8s ease-in-out infinite 1.4s; }
  @media (prefers-reduced-motion: reduce) {
    .err-404-float-1, .err-404-float-2, .err-404-float-3 { animation: none; }
  }

  /* Camera-shutter blink — sits below the 404 as a subtle nod to the
     photography theme. Pure CSS, no JS. */
  @keyframes err-shutter {
    0%, 90%, 100% { opacity: 0; }
    93%, 97%      { opacity: 1; }
  }
  .err-404-shutter { animation: err-shutter 4s ease-in-out infinite; }

  /* Suggestion cards — outline style so the hero stays the main
     visual focus. Hover lifts and tints with the link's accent. */
  .err-404-suggestion {
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgb(226 232 240);
    border-radius: 16px;
    padding: 1rem 1.25rem;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    text-decoration: none;
    display: block;
  }
  .dark .err-404-suggestion {
    background: rgba(30, 41, 59, 0.6);
    border-color: rgba(255, 255, 255, 0.08);
  }
  @media (hover: hover) {
    .err-404-suggestion:hover {
      transform: translateY(-3px);
      box-shadow: 0 18px 35px -12px rgba(99,102,241,0.30);
      border-color: rgb(165 180 252);
    }
    .dark .err-404-suggestion:hover {
      border-color: rgba(165, 180, 252, 0.5);
      box-shadow: 0 18px 35px -12px rgba(99,102,241,0.45);
    }
  }
</style>
@endpush

@section('content')
<div class="err-404-bg flex items-center justify-center px-4 py-12 sm:py-16">
  <div class="max-w-3xl w-full">

    {{-- ── Hero ───────────────────────────────────────────────────── --}}
    <div class="text-center relative">

      {{-- Floating polaroids — three offset photo frames behind the 404
           to anchor the photography theme. Hidden on small phones to
           keep the hero clean. --}}
      <div class="hidden sm:block pointer-events-none select-none">
        <div class="err-404-polaroid err-404-float-1 absolute"
             style="top: -10px; left: 8%; width: 92px;">
          <div class="frame"><i class="bi bi-camera-fill"></i></div>
        </div>
        <div class="err-404-polaroid err-404-float-2 absolute"
             style="top: 80px; right: 6%; width: 82px;">
          <div class="frame"
               style="background:linear-gradient(135deg,#fce7f3,#fed7aa);color:#ec4899;">
            <i class="bi bi-image-fill"></i>
          </div>
        </div>
        <div class="err-404-polaroid err-404-float-3 absolute"
             style="bottom: -8px; left: 14%; width: 76px;">
          <div class="frame"
               style="background:linear-gradient(135deg,#ddd6fe,#c7d2fe);color:#8b5cf6;">
            <i class="bi bi-aperture"></i>
          </div>
        </div>
      </div>

      {{-- "Page not found" eyebrow --}}
      <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full
                  bg-white/70 dark:bg-slate-800/60 border border-slate-200 dark:border-white/10
                  text-xs font-bold uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-300 mb-6
                  backdrop-blur-sm">
        <span class="inline-flex w-1.5 h-1.5 rounded-full bg-indigo-500 err-404-shutter"></span>
        Error 404
      </div>

      {{-- Big 404 — the eye magnet --}}
      <h1 class="err-404-num text-[8rem] sm:text-[10rem] md:text-[12rem]
                 leading-none mb-2 sm:mb-4 select-none">
        404
      </h1>

      {{-- Headline + sub-copy. Photography-themed playful Thai copy
           keeps the brand voice consistent with the marketplace tone
           of the rest of the site. --}}
      <h2 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight mb-3">
        หน้านี้หายไปจากกล้อง
      </h2>
      <p class="text-slate-600 dark:text-slate-300 text-sm sm:text-base max-w-xl mx-auto leading-relaxed mb-8 px-2">
        หน้าที่คุณค้นหาอาจถูกย้าย เปลี่ยนชื่อ หรือไม่เคยมีอยู่
        — ลองกลับไปหน้าหลัก ค้นหาอีเวนต์ใหม่ๆ หรือใช้ลิงก์ด้านล่างได้เลย
      </p>

      {{-- ── Primary CTAs ─────────────────────────────────────────── --}}
      <div class="flex items-center justify-center gap-2 sm:gap-3 flex-wrap mb-10 sm:mb-14">
        <a href="{{ url('/') }}"
           class="inline-flex items-center justify-center gap-2
                  px-5 sm:px-6 py-3 rounded-xl text-sm font-bold text-white
                  bg-gradient-to-r from-indigo-600 via-violet-600 to-pink-500
                  hover:shadow-xl hover:shadow-violet-500/30
                  hover:-translate-y-0.5 transition-all duration-200
                  no-underline">
          <i class="bi bi-house-fill"></i>
          กลับหน้าแรก
        </a>
        <a href="{{ route('events.index') }}"
           class="inline-flex items-center justify-center gap-2
                  px-5 sm:px-6 py-3 rounded-xl text-sm font-bold
                  bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200
                  border border-slate-200 dark:border-white/10
                  hover:border-indigo-300 dark:hover:border-indigo-500/40
                  hover:text-indigo-600 dark:hover:text-indigo-300
                  hover:-translate-y-0.5 transition-all duration-200
                  no-underline">
          <i class="bi bi-search"></i>
          ค้นหาอีเวนต์
        </a>
      </div>
    </div>

    {{-- ── Helpful suggestions card ───────────────────────────────── --}}
    <div class="err-404-card rounded-2xl sm:rounded-3xl p-5 sm:p-7 shadow-xl">
      <div class="flex items-center gap-2 mb-4">
        <i class="bi bi-lightbulb-fill text-amber-500"></i>
        <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base">
          ลองไปที่นี่แทน
        </h3>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @php
          // Suggested destinations — kept short so the card stays
          // scannable. Each one points to a real route on the site.
          $links = [
            [
              'href'  => route('events.index'),
              'icon'  => 'bi-calendar-event',
              'tone'  => 'text-rose-500 dark:text-rose-400',
              'title' => 'อีเวนต์ทั้งหมด',
              'desc'  => 'เลือกดูภาพจากงานต่างๆ',
            ],
            [
              'href'  => route('photographers.index'),
              'icon'  => 'bi-camera2',
              'tone'  => 'text-indigo-500 dark:text-indigo-400',
              'title' => 'ช่างภาพ',
              'desc'  => 'รวมช่างภาพทุกแนว',
            ],
            [
              'href'  => route('promo'),
              'icon'  => 'bi-stars',
              'tone'  => 'text-amber-500 dark:text-amber-400',
              'title' => 'โปรโมชัน',
              'desc'  => 'ส่วนลด · แพ็กเกจ',
            ],
            [
              'href'  => url('/help'),
              'icon'  => 'bi-question-circle-fill',
              'tone'  => 'text-emerald-500 dark:text-emerald-400',
              'title' => 'ช่วยเหลือ',
              'desc'  => 'คำถามที่พบบ่อย · ติดต่อทีมงาน',
            ],
          ];
        @endphp

        @foreach($links as $link)
          <a href="{{ $link['href'] }}" class="err-404-suggestion group">
            <div class="flex items-start gap-3">
              <div class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center
                          bg-slate-50 dark:bg-slate-700/40 transition group-hover:scale-110">
                <i class="bi {{ $link['icon'] }} {{ $link['tone'] }} text-lg"></i>
              </div>
              <div class="min-w-0">
                <div class="font-bold text-slate-800 dark:text-slate-100 text-sm">
                  {{ $link['title'] }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                  {{ $link['desc'] }}
                </div>
              </div>
              <i class="bi bi-arrow-right text-slate-300 dark:text-slate-600
                        group-hover:text-indigo-500 dark:group-hover:text-indigo-400
                        group-hover:translate-x-1 transition ml-auto"></i>
            </div>
          </a>
        @endforeach
      </div>

      {{-- Footer hint — back button for users who landed here from a
           bookmark / external link and have nowhere obvious to retreat. --}}
      <div class="mt-5 pt-5 border-t border-slate-200 dark:border-white/10
                  flex items-center justify-between flex-wrap gap-3">
        <button type="button" onclick="history.back()"
                class="inline-flex items-center gap-1.5 text-xs font-semibold
                       text-slate-500 dark:text-slate-400
                       hover:text-indigo-600 dark:hover:text-indigo-300
                       transition cursor-pointer bg-transparent border-0">
          <i class="bi bi-arrow-left-circle"></i>
          ย้อนกลับหน้าก่อน
        </button>
        <span class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">
          ERR_404 · {{ now()->format('Y-m-d H:i') }}
        </span>
      </div>
    </div>

  </div>
</div>
@endsection
