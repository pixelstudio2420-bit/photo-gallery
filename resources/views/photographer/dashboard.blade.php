@extends('layouts.photographer')

@section('title', 'Dashboard')

@push('styles')
<style>
/* ═══════════════════════════════════════════════════════════════════════
 * Photographer Dashboard — v3 (Bento + Studio)
 * ───────────────────────────────────────────────────────────────────────
 * Design goals:
 *   • "Bento grid" layout: each card is a self-contained cell, cleanly
 *     composed on a soft background canvas. No dense borders fighting
 *     each other — depth comes from shadows + subtle gradients only.
 *   • Hero: single oversized glass panel with layered aurora gradient,
 *     so the page opens with energy without reading as heavy.
 *   • KPI tiles: big-number typography with an expressive accent band
 *     that ties back to each metric's identity colour.
 *   • Revenue chart: smooth SVG area curve (not bars) — photographers
 *     tend to scan it for momentum, not for exact month-to-month values.
 *   • Dark mode: deep graphite canvas with a single violet halo behind
 *     the hero so the page feels lit from above, like an editing bay.
 * ═══════════════════════════════════════════════════════════════════ */
:root {
  --pg-bg:            #f5f7fb;
  --pg-bg-glow:       radial-gradient(1200px 500px at 50% -260px, rgba(124,58,237,0.10), transparent 60%),
                      radial-gradient(900px 400px at 100% 0%, rgba(14,165,233,0.06), transparent 55%);
  --pg-surface:       #ffffff;
  --pg-surface-2:     #f1f5f9;
  --pg-surface-3:     #e2e8f0;
  --pg-border:        rgba(15, 23, 42, 0.08);
  --pg-border-strong: rgba(15, 23, 42, 0.14);
  --pg-text:          #020617;
  --pg-text-soft:     #334155;
  --pg-text-mute:     #64748b;
  --pg-accent:        #6d28d9;
  --pg-accent-2:      #7c3aed;
  --pg-accent-3:      #a78bfa;
  --pg-accent-soft:   rgba(124, 58, 237, 0.10);
  --pg-teal:          #0d9488;
  --pg-teal-soft:     rgba(13, 148, 136, 0.10);
  --pg-rose:          #e11d48;
  --pg-amber:         #d97706;
  --pg-shadow:        0 1px 3px rgba(15,23,42,0.04), 0 8px 30px rgba(15,23,42,0.06);
  --pg-shadow-lg:     0 2px 6px rgba(15,23,42,0.05), 0 28px 60px rgba(15,23,42,0.10);
  --pg-shadow-hero:   0 20px 60px rgba(124, 58, 237, 0.30);
  --pg-hero-bg:       linear-gradient(135deg, #4c1d95 0%, #6d28d9 35%, #9333ea 70%, #0ea5e9 130%);
  --pg-chart-grid:    rgba(15, 23, 42, 0.06);
  --pg-chart-stroke:  #7c3aed;
  --pg-chart-fill-1:  rgba(124, 58, 237, 0.35);
  --pg-chart-fill-2:  rgba(124, 58, 237, 0);
}
html.dark {
  --pg-bg:            #080a14;
  --pg-bg-glow:       radial-gradient(1000px 460px at 50% -220px, rgba(124,58,237,0.22), transparent 60%),
                      radial-gradient(700px 300px at 95% 105%, rgba(14,165,233,0.10), transparent 60%);
  --pg-surface:       #10131e;
  --pg-surface-2:     #171b2a;
  --pg-surface-3:     #222839;
  --pg-border:        rgba(255, 255, 255, 0.07);
  --pg-border-strong: rgba(255, 255, 255, 0.14);
  --pg-text:          #ffffff;
  --pg-text-soft:     #e2e8f0;
  --pg-text-mute:     #94a3b8;
  --pg-accent:        #a78bfa;
  --pg-accent-2:      #c4b5fd;
  --pg-accent-3:      #ddd6fe;
  --pg-accent-soft:   rgba(167, 139, 250, 0.14);
  --pg-teal:          #2dd4bf;
  --pg-teal-soft:     rgba(45, 212, 191, 0.15);
  --pg-rose:          #fb7185;
  --pg-amber:         #fbbf24;
  --pg-shadow:        0 1px 2px rgba(0,0,0,0.40), 0 12px 30px rgba(0,0,0,0.35);
  --pg-shadow-lg:     0 2px 6px rgba(0,0,0,0.35), 0 30px 60px rgba(0,0,0,0.50);
  --pg-shadow-hero:   0 24px 70px rgba(109, 40, 217, 0.45);
  --pg-hero-bg:       linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #581c87 60%, #0c4a6e 100%);
  --pg-chart-grid:    rgba(255, 255, 255, 0.06);
  --pg-chart-stroke:  #a78bfa;
  --pg-chart-fill-1:  rgba(167, 139, 250, 0.30);
  --pg-chart-fill-2:  rgba(167, 139, 250, 0);
}

#photographer-content {
  background: var(--pg-bg-glow), var(--pg-bg) !important;
  color: var(--pg-text);
  min-height: 100vh;
}
#photographer-content h1,
#photographer-content h2,
#photographer-content h3,
#photographer-content h4,
#photographer-content h5,
#photographer-content h6 {
  color: var(--pg-text);
}

/* ── Building blocks ────────────────────────────────────────────────── */
.pg-card {
  background: var(--pg-surface);
  border: 1px solid var(--pg-border);
  border-radius: 20px;
  box-shadow: var(--pg-shadow);
  transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
  position: relative;
}
.pg-card--hover:hover {
  transform: translateY(-3px);
  box-shadow: var(--pg-shadow-lg);
  border-color: var(--pg-border-strong);
}

/* KPI tile — accent band on left, subtle gradient on right */
.pg-kpi {
  position: relative;
  padding: 1.35rem 1.5rem 1.4rem;
  overflow: hidden;
  isolation: isolate;
}
.pg-kpi::before {
  content: "";
  position: absolute;
  inset: 0 auto 0 0;
  width: 4px;
  background: var(--pg-kpi-accent, var(--pg-accent));
  border-radius: 20px 0 0 20px;
}
.pg-kpi::after {
  content: "";
  position: absolute;
  top: -30%; right: -20%;
  width: 55%; height: 160%;
  background: var(--pg-kpi-glow, transparent);
  filter: blur(40px);
  opacity: 0.35;
  z-index: -1;
  transition: opacity .3s;
}
.pg-kpi:hover::after { opacity: 0.55; }

.pg-kpi-events   { --pg-kpi-accent: linear-gradient(180deg, #7c3aed, #4f46e5); --pg-kpi-glow: radial-gradient(circle, #7c3aed, transparent 60%); }
.pg-kpi-photos   { --pg-kpi-accent: linear-gradient(180deg, #0ea5e9, #0369a1); --pg-kpi-glow: radial-gradient(circle, #0ea5e9, transparent 60%); }
.pg-kpi-sales    { --pg-kpi-accent: linear-gradient(180deg, #0d9488, #047857); --pg-kpi-glow: radial-gradient(circle, #10b981, transparent 60%); }
.pg-kpi-earnings { --pg-kpi-accent: linear-gradient(180deg, #f59e0b, #b45309); --pg-kpi-glow: radial-gradient(circle, #f59e0b, transparent 60%); }

.pg-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  letter-spacing: 0.02em;
}

.pg-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  font-size: 0.875rem;
  padding: 0.6rem 1.1rem;
  border-radius: 12px;
  transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease, color .15s ease;
  white-space: nowrap;
  border: 1px solid transparent;
}
.pg-btn:hover { transform: translateY(-1px); }
.pg-btn--primary {
  background: linear-gradient(135deg, var(--pg-accent), var(--pg-accent-2));
  color: #fff;
  box-shadow: 0 4px 14px rgba(109, 40, 217, 0.40);
}
html.dark .pg-btn--primary {
  box-shadow: 0 4px 14px rgba(167, 139, 250, 0.35);
}
.pg-btn--ghost {
  background: var(--pg-surface-2);
  color: var(--pg-text);
  border-color: var(--pg-border);
}
.pg-btn--ghost:hover {
  background: var(--pg-surface-3);
}

/* ── Hero greeting — aurora panel ───────────────────────────────────── */
.pg-hero {
  position: relative;
  border-radius: 28px;
  overflow: hidden;
  background: var(--pg-hero-bg);
  color: #fff;
  box-shadow: var(--pg-shadow-hero);
}
.pg-hero__pattern {
  position: absolute; inset: 0;
  background-image:
    radial-gradient(circle at 10% 110%, rgba(236, 72, 153, 0.35), transparent 40%),
    radial-gradient(circle at 85% -20%, rgba(96, 165, 250, 0.28), transparent 45%),
    radial-gradient(circle at 50% 50%, rgba(255,255,255,0.04), transparent 60%);
  pointer-events: none;
}
.pg-hero__grid {
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
  background-size: 56px 56px;
  pointer-events: none;
  mask-image: linear-gradient(180deg, black 40%, transparent);
  -webkit-mask-image: linear-gradient(180deg, black 40%, transparent);
}

/* ── Tier stepper ──────────────────────────────────────────────────── */
.pg-stepper {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
.pg-step {
  flex: 1;
  height: 8px;
  border-radius: 999px;
  background: var(--pg-surface-3);
  overflow: hidden;
  position: relative;
}
.pg-step--done {
  background: linear-gradient(90deg, #10b981, #14b8a6);
}
.pg-step--current {
  background: linear-gradient(90deg, var(--pg-accent), var(--pg-accent-2));
}
.pg-step--current::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.7), transparent);
  animation: pg-shimmer 2.4s ease-in-out infinite;
}
@keyframes pg-shimmer {
  0%   { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

/* ── Area chart (SVG) ──────────────────────────────────────────────── */
.pg-chart-wrap {
  position: relative;
  width: 100%;
  height: 220px;
}
.pg-chart-svg {
  width: 100%;
  height: 100%;
  display: block;
}
.pg-chart-grid line {
  stroke: var(--pg-chart-grid);
  stroke-width: 1;
  stroke-dasharray: 3 4;
}
.pg-chart-fill {
  fill: url(#pg-chart-gradient);
  opacity: 0.9;
}
.pg-chart-line {
  fill: none;
  stroke: var(--pg-chart-stroke);
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-linejoin: round;
  filter: drop-shadow(0 3px 6px rgba(124,58,237,0.25));
}
.pg-chart-dot {
  fill: var(--pg-surface);
  stroke: var(--pg-chart-stroke);
  stroke-width: 2.5;
}
.pg-chart-dot--last {
  fill: var(--pg-chart-stroke);
  stroke: var(--pg-surface);
  stroke-width: 3;
  r: 6;
}
.pg-chart-label {
  font-size: 11px;
  font-weight: 600;
  fill: var(--pg-text-mute);
  text-anchor: middle;
}
.pg-chart-value {
  font-size: 10.5px;
  font-weight: 700;
  fill: var(--pg-text);
  text-anchor: middle;
}

/* Table row hover */
.pg-row:hover {
  background: var(--pg-surface-2);
}
html.dark .pg-row:hover {
  background: var(--pg-surface-3);
}

/* Delta chip */
.pg-delta-up   { color: #059669; background: rgba(16, 185, 129, 0.12); }
.pg-delta-down { color: #dc2626; background: rgba(239, 68, 68, 0.12); }
html.dark .pg-delta-up   { color: #6ee7b7; background: rgba(16, 185, 129, 0.18); }
html.dark .pg-delta-down { color: #fca5a5; background: rgba(239, 68, 68, 0.20); }

/* Quick-action pill */
.pg-quick {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1rem;
  border-radius: 14px;
  font-size: 0.825rem;
  font-weight: 600;
  background: var(--pg-surface-2);
  color: var(--pg-text);
  border: 1px solid var(--pg-border);
  transition: all .2s;
}
.pg-quick i {
  font-size: 1.05rem;
  color: var(--pg-accent);
  transition: color .2s, transform .2s;
}
.pg-quick:hover {
  background: linear-gradient(135deg, var(--pg-accent), var(--pg-accent-2));
  color: #fff;
  border-color: transparent;
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(109, 40, 217, 0.30);
}
.pg-quick:hover i { color: #fff; transform: scale(1.1); }

/* Text helpers */
.pg-text      { color: var(--pg-text)      !important; }
.pg-text-soft { color: var(--pg-text-soft) !important; }
.pg-text-mute { color: var(--pg-text-mute) !important; }
.pg-divide    { border-color: var(--pg-border) !important; }
.pg-bg-2      { background: var(--pg-surface-2); }

/* Empty state */
.pg-empty i {
  color: var(--pg-text-mute);
  opacity: 0.5;
}

/* Spinner dot for live indicators */
.pg-dot-pulse {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  position: relative;
  display: inline-block;
}
.pg-dot-pulse::before {
  content: "";
  position: absolute;
  inset: -4px;
  border-radius: 50%;
  background: #10b981;
  opacity: 0.35;
  animation: pg-pulse 1.6s ease-out infinite;
}
@keyframes pg-pulse {
  0%   { transform: scale(0.8); opacity: 0.6; }
  100% { transform: scale(2.2); opacity: 0; }
}

/* Rank badge for top events */
.pg-rank {
  width: 34px; height: 34px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 10px;
  font-weight: 800;
  font-size: 0.78rem;
  flex-shrink: 0;
}
.pg-rank--1 { background: linear-gradient(135deg, #fde047, #ca8a04); color: #fff; box-shadow: 0 4px 12px rgba(234, 179, 8, 0.35); }
.pg-rank--2 { background: linear-gradient(135deg, #cbd5e1, #64748b); color: #fff; }
.pg-rank--3 { background: linear-gradient(135deg, #fb923c, #c2410c); color: #fff; }
.pg-rank--default { background: var(--pg-surface-3); color: var(--pg-text-soft); }
</style>
@endpush

@section('content')
@php
  use App\Models\PhotographerProfile;

  $user        = Auth::user();
  $displayName = $user->photographerProfile?->display_name ?? $user->first_name ?? 'ช่างภาพ';
  $pending     = $pendingPayout ?? 0;
  $modPending  = $moderationPending ?? 0;
  $drivePending= $driveSyncPending ?? 0;

  // Time-of-day greeting
  $hour = (int) now()->format('G');
  if ($hour < 12)      { $greeting = 'อรุณสวัสดิ์'; $greetIcon = 'bi-sunrise'; }
  elseif ($hour < 17)  { $greeting = 'สวัสดีตอนบ่าย'; $greetIcon = 'bi-brightness-high'; }
  elseif ($hour < 20)  { $greeting = 'สวัสดีตอนเย็น'; $greetIcon = 'bi-sunset'; }
  else                 { $greeting = 'สวัสดีตอนดึก'; $greetIcon = 'bi-moon-stars'; }

  // Tier progression state
  $tier              = $profile?->tier ?? 'creator';
  $tierLabels        = PhotographerProfile::tierLabels();
  $completeness      = $profile?->completenessPercent() ?? 0;
  $hasPromptPay      = !empty($profile?->promptpay_number);
  $isPromptPayVerified = $profile?->isPromptPayVerified() ?? false;
  $isActive          = ($profile?->onboarding_stage ?? '') === 'active';
  $proTierEnabled    = PhotographerProfile::isProTierEnabled();

  $tierIndex = match($tier) {
      PhotographerProfile::TIER_PRO    => 2,
      PhotographerProfile::TIER_SELLER => 1,
      default                          => 0,
  };

  // Next-step CTA — Pro is admin-approved now, so we just inform.
  $nextStep = null;
  if (!$hasPromptPay) {
      $nextStep = ['icon'=>'bi-wallet2','title'=>'ปลดล็อก Seller','desc'=>'เพิ่มหมายเลข PromptPay เพื่อเริ่มรับเงินอัตโนมัติหลังลูกค้าชำระ','cta'=>'ตั้งค่า PromptPay','url'=>route('photographer.setup-bank')];
  } elseif (!$isPromptPayVerified) {
      $nextStep = ['icon'=>'bi-shield-check','title'=>'รอการยืนยันจากธนาคาร','desc'=>'ชื่อบัญชีจะถูกยืนยันอัตโนมัติเมื่อโอนเงินครั้งแรกผ่าน ITMX','cta'=>'ดูรายละเอียด','url'=>route('photographer.setup-bank')];
  } elseif ($proTierEnabled && !$isActive) {
      $nextStep = ['icon'=>'bi-patch-check','title'=>'เตรียมตัวอัปเกรด Pro','desc'=>'Pro เป็นระดับที่ได้รับการอนุมัติโดยแอดมิน — สร้างผลงานอย่างต่อเนื่องแล้วแจ้งแอดมินเมื่อพร้อม','cta'=>'ดูโปรไฟล์','url'=>route('photographer.profile')];
  }

  // Chart geometry setup (SVG area chart)
  $trendValues = array_column($monthlyTrend ?? [], 'value');
  $trendLabels = array_column($monthlyTrend ?? [], 'label');
  if (empty($trendValues)) {
      $trendValues = [0,0,0,0,0,0];
      $trendLabels = ['-','-','-','-','-','-'];
  }
  $chartMax = max(1, max($trendValues));
  $chartW   = 600;
  $chartH   = 180;
  $chartPad = ['top' => 20, 'right' => 20, 'bottom' => 30, 'left' => 20];
  $plotW    = $chartW - $chartPad['left'] - $chartPad['right'];
  $plotH    = $chartH - $chartPad['top'] - $chartPad['bottom'];

  $chartPoints = [];
  foreach ($trendValues as $i => $v) {
      $x = $chartPad['left'] + (count($trendValues) > 1 ? ($plotW * $i / (count($trendValues) - 1)) : $plotW / 2);
      $y = $chartPad['top'] + $plotH - ($v / $chartMax) * $plotH;
      $chartPoints[] = [
          'x'     => $x,
          'y'     => $y,
          'value' => $v,
          'label' => $trendLabels[$i] ?? '',
      ];
  }

  // Build smooth curve using Catmull-Rom→Bezier conversion for a natural flow.
  $pathLine = '';
  $pathArea = '';
  if (!empty($chartPoints)) {
      $pathLine = 'M ' . round($chartPoints[0]['x'], 2) . ' ' . round($chartPoints[0]['y'], 2);
      for ($i = 0; $i < count($chartPoints) - 1; $i++) {
          $p0 = $chartPoints[max(0, $i - 1)];
          $p1 = $chartPoints[$i];
          $p2 = $chartPoints[$i + 1];
          $p3 = $chartPoints[min(count($chartPoints) - 1, $i + 2)];
          $cp1x = $p1['x'] + ($p2['x'] - $p0['x']) / 6;
          $cp1y = $p1['y'] + ($p2['y'] - $p0['y']) / 6;
          $cp2x = $p2['x'] - ($p3['x'] - $p1['x']) / 6;
          $cp2y = $p2['y'] - ($p3['y'] - $p1['y']) / 6;
          $pathLine .= ' C ' . round($cp1x, 2) . ' ' . round($cp1y, 2)
                     . ', ' . round($cp2x, 2) . ' ' . round($cp2y, 2)
                     . ', ' . round($p2['x'], 2) . ' ' . round($p2['y'], 2);
      }
      $baseline = $chartPad['top'] + $plotH;
      $pathArea = $pathLine
                . ' L ' . round(end($chartPoints)['x'], 2) . ' ' . round($baseline, 2)
                . ' L ' . round($chartPoints[0]['x'], 2) . ' ' . round($baseline, 2)
                . ' Z';
  }
@endphp

{{-- ════════════════════════════════════════════════════════════════════
 1. HERO — aurora greeting panel

 Layout split (lg+):
   ┌──────────────────────────────┬─────────────────────────┐
   │  greeting + identity pills   │  revenue + CTA buttons  │
   │  (col-span 7/12)             │  (col-span 5/12)        │
   └──────────────────────────────┴─────────────────────────┘
 On mobile/tablet they stack vertically. The right column also stacks
 internally so the revenue card takes priority above the action
 buttons (priority for "what's my income" over "post a new event").

 NB on the pills: "ระดับ" (tier) and "แผน" (plan) are intentionally
 SEPARATE concepts in this codebase:
   • tier  = creator/seller/pro — verification level (set by us/admin)
   • plan  = free/starter/pro/business/studio — paid subscription
 Both can be "pro" and they used to read identically in the UI which
 caused confusion. We now prefix each pill so the meaning is obvious.
 ═════════════════════════════════════════════════════════════════════ --}}
@php
  // Translate the tier code to a Thai-friendly short name so the pill
  // doesn't read "pro" (English) right next to a plan pill that might
  // ALSO read "Pro — 100 GB". Keeps semantics distinct at a glance.
  $tierLabelShort = match($tier) {
      PhotographerProfile::TIER_PRO    => 'มืออาชีพ',
      PhotographerProfile::TIER_SELLER => 'ผู้ขาย',
      default                          => 'ผู้สร้าง',
  };
  $tierIconClass = match($tier) {
      PhotographerProfile::TIER_PRO    => 'bi-patch-check-fill text-amber-300',
      PhotographerProfile::TIER_SELLER => 'bi-shield-check text-emerald-300',
      default                          => 'bi-person-vcard text-sky-300',
  };
@endphp
<div class="pg-hero px-6 md:px-10 py-9 md:py-10 mb-6">
  <div class="pg-hero__grid"></div>
  <div class="pg-hero__pattern"></div>

  <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start lg:flex-1 w-full">

    {{-- ─── LEFT: greeting + identity pills (7/12) ─── --}}
    <div class="lg:col-span-7 xl:col-span-8 flex items-start gap-5 min-w-0">
      <div class="w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-md border border-white/25 flex items-center justify-center text-3xl shrink-0 shadow-xl shadow-black/20">
        <i class="bi {{ $greetIcon }}"></i>
      </div>

      <div class="flex-1 min-w-0">
        <div class="text-[11px] uppercase tracking-[0.3em] text-white/65 font-bold mb-2 flex items-center gap-2">
          <span class="pg-dot-pulse"></span>
          <span>Photographer Studio</span>
        </div>
        <h1 class="font-bold text-2xl md:text-3xl xl:text-4xl tracking-tight mb-2 leading-tight">
          {{ $greeting }},<br class="hidden sm:block"> <span class="bg-gradient-to-r from-amber-200 via-pink-200 to-violet-200 bg-clip-text text-transparent">{{ $displayName }}</span>
        </h1>

        {{-- Identity pill row — date + photographer code + ระดับ + แผน --}}
        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-2 text-sm text-white/80">
          <span class="inline-flex items-center gap-1.5">
            <i class="bi bi-calendar3"></i>{{ now()->translatedFormat('l ที่ j F Y') }}
          </span>

          @if($profile?->photographer_code)
            <span class="inline-flex items-center gap-1.5 text-xs bg-white/10 backdrop-blur border border-white/20 rounded-lg px-2.5 py-1 font-medium">
              <i class="bi bi-person-badge"></i>{{ $profile->photographer_code }}
            </span>
          @endif

          {{-- ระดับ (tier) pill — verification level set by us/admin.
               Distinct from "แผน" below: this answers "what permissions
               does this user have", not "what plan are they paying for". --}}
          <span class="inline-flex items-center gap-1.5 text-xs bg-white/10 backdrop-blur border border-white/20 rounded-lg px-2.5 py-1 font-medium"
                title="ระดับการยืนยันตัวตน (creator → seller → pro)">
            <i class="bi {{ $tierIconClass }}"></i>
            <span class="text-white/60 font-bold uppercase tracking-wider">ระดับ</span>
            <span class="font-bold">{{ $tierLabelShort }}</span>
          </span>

          {{-- แผน (subscription plan) pill — clickable, opens manage page --}}
          @if($photographerPlan ?? null)
            <a href="{{ route('photographer.subscription.index') }}"
               class="inline-flex items-center gap-1.5 text-xs bg-white/15 hover:bg-white/25 backdrop-blur border border-white/25 rounded-lg px-2.5 py-1 font-medium text-white no-underline transition"
               title="แผนสมัครสมาชิก: {{ $photographerPlan->name }} — คลิกเพื่อจัดการแผน">
              <i class="bi {{ $photographerPlan->iconClass() }}"
                 style="color: {{ in_array($photographerPlan->code, ['business','studio','pro']) ? '#fde047' : '#fff' }};"></i>
              <span class="text-white/60 font-bold uppercase tracking-wider">แผน</span>
              <span class="font-bold">{{ $photographerPlan->name }}</span>
            </a>
          @endif
        </div>
      </div>
    </div>

    {{-- ─── RIGHT: revenue glance + CTA buttons (pinned to far right)
         The grid track still reserves 5/12 (lg) / 4/12 (xl) for layout
         alignment with the rest of the page, but we cap the inner block
         at 320px and use `justify-self-end` so the revenue card and
         CTA buttons hug the right edge of the hero — leaving extra
         breathing room between them and the greeting block. ─── --}}
    <div class="lg:col-span-5 xl:col-span-4 lg:justify-self-end w-full lg:max-w-[320px] flex flex-col gap-3">
      {{-- Revenue card — full width inside its column so it visually
           anchors the right side regardless of screen size. --}}
      <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 shadow-xl shadow-black/10">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <div class="text-[11px] uppercase tracking-widest text-white/65 font-bold mb-1">รายได้เดือนนี้</div>
            <div class="text-2xl md:text-3xl font-black tracking-tight text-white leading-none">
              ฿{{ number_format($stats['earnings']['this_mo'] ?? 0, 0) }}
            </div>
            @if(($stats['earnings']['delta_pct'] ?? null) !== null)
              @php $d = $stats['earnings']['delta_pct']; @endphp
              <div class="text-xs mt-2 flex items-center gap-1 {{ $d >= 0 ? 'text-emerald-200' : 'text-rose-200' }}">
                <i class="bi {{ $d >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                <span class="font-semibold">{{ $d >= 0 ? '+' : '' }}{{ number_format($d, 1) }}%</span>
                <span class="text-white/60">vs เดือนที่แล้ว</span>
              </div>
            @endif
          </div>
          {{-- Subtle revenue icon to balance the card visually --}}
          <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center text-amber-200 shrink-0">
            <i class="bi bi-cash-stack text-xl"></i>
          </div>
        </div>
      </div>

      {{-- CTA buttons — split 50/50 below the revenue card --}}
      <div class="grid grid-cols-2 gap-2">
        <a href="{{ route('photographer.events.create') }}"
           class="inline-flex items-center justify-center gap-2 bg-white text-violet-800 hover:bg-violet-50 font-bold text-sm px-4 py-3 rounded-2xl shadow-xl shadow-black/20 transition hover:-translate-y-0.5">
          <i class="bi bi-plus-circle-fill"></i><span>สร้าง Event</span>
        </a>
        <a href="{{ route('photographer.events.index') }}"
           class="inline-flex items-center justify-center gap-2 bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/25 text-white font-semibold text-sm px-4 py-3 rounded-2xl transition hover:-translate-y-0.5">
          <i class="bi bi-cloud-upload"></i><span>อัปโหลดรูป</span>
        </a>
      </div>
    </div>

  </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════
 2. TIER PROGRESSION — collapses when already at top tier
 ═════════════════════════════════════════════════════════════════════ --}}
@php $topTierIndex = $proTierEnabled ? 2 : 1; @endphp
@if($profile && $tierIndex < $topTierIndex)
<div class="pg-card mb-6 p-5 md:p-6">
  <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl text-white shadow-lg"
           style="background: linear-gradient(135deg, var(--pg-accent), var(--pg-accent-2)); box-shadow: 0 8px 20px var(--pg-accent-soft);">
        <i class="bi bi-rocket-takeoff-fill"></i>
      </div>
      <div>
        <div class="text-[11px] uppercase tracking-[0.22em] font-bold pg-text-mute mb-0.5">เส้นทางการอัปเกรด</div>
        <div class="text-base md:text-lg font-black pg-text leading-tight">{{ $tierLabels[$tier] ?? $tier }}</div>
        <div class="text-xs pg-text-mute mt-1">
          <span class="inline-flex items-center gap-1">
            <i class="bi bi-speedometer2"></i>โปรไฟล์สมบูรณ์ <strong class="pg-text">{{ $completeness }}%</strong>
          </span>
        </div>
      </div>
    </div>
    @if($nextStep)
    <a href="{{ $nextStep['url'] }}" class="pg-btn pg-btn--primary">
      <i class="bi {{ $nextStep['icon'] }}"></i>{{ $nextStep['cta'] }}
    </a>
    @endif
  </div>

  <div class="pg-stepper mb-4">
    <div class="pg-step {{ $tierIndex >= 0 ? ($tierIndex === 0 ? 'pg-step--current' : 'pg-step--done') : '' }}"></div>
    <div class="pg-step {{ $tierIndex >= 1 ? ($tierIndex === 1 ? 'pg-step--current' : 'pg-step--done') : '' }}"></div>
    @if($proTierEnabled)
    <div class="pg-step {{ $tierIndex >= 2 ? 'pg-step--done' : '' }}"></div>
    @endif
  </div>

  <div class="grid {{ $proTierEnabled ? 'grid-cols-3' : 'grid-cols-2' }} gap-3 text-xs">
    <div>
      <div class="inline-flex items-center gap-1.5 font-bold text-sm {{ $tierIndex >= 0 ? 'pg-text' : 'pg-text-mute' }}">
        <i class="bi {{ $tierIndex > 0 ? 'bi-check-circle-fill text-emerald-500' : 'bi-pencil-square' }}"></i>
        Creator
      </div>
      <div class="pg-text-mute text-[11px] mt-0.5">สมัคร + สร้าง event</div>
    </div>
    <div>
      <div class="inline-flex items-center gap-1.5 font-bold text-sm {{ $tierIndex >= 1 ? 'pg-text' : 'pg-text-mute' }}">
        <i class="bi {{ $tierIndex > 1 ? 'bi-check-circle-fill text-emerald-500' : ($tierIndex === 1 ? 'bi-shop text-violet-500' : 'bi-lock-fill') }}"></i>
        Seller
      </div>
      <div class="pg-text-mute text-[11px] mt-0.5">
        {{ $proTierEnabled ? 'PromptPay + เริ่มขาย' : 'PromptPay + ขายไม่ลิมิต' }}
      </div>
    </div>
    @if($proTierEnabled)
    <div>
      <div class="inline-flex items-center gap-1.5 font-bold text-sm {{ $tierIndex >= 2 ? 'pg-text' : 'pg-text-mute' }}">
        <i class="bi {{ $tierIndex >= 2 ? 'bi-patch-check-fill text-amber-500' : 'bi-lock-fill' }}"></i>
        Pro
      </div>
      <div class="pg-text-mute text-[11px] mt-0.5">แอดมินอนุมัติ = ไม่ลิมิต</div>
    </div>
    @endif
  </div>

  @if($nextStep)
  <div class="mt-5 p-4 rounded-2xl flex items-start gap-3 border"
       style="background: var(--pg-accent-soft); border-color: var(--pg-border);">
    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 text-white"
         style="background: linear-gradient(135deg, var(--pg-accent), var(--pg-accent-2));">
      <i class="bi {{ $nextStep['icon'] }}"></i>
    </div>
    <div class="flex-1 min-w-0">
      <div class="font-bold text-sm pg-text">{{ $nextStep['title'] }}</div>
      <div class="text-xs pg-text-soft mt-0.5 leading-relaxed">{{ $nextStep['desc'] }}</div>
    </div>
  </div>
  @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════
 3. ALERT STRIP — shown only when actionable
 ═════════════════════════════════════════════════════════════════════ --}}
@if($pending > 0 || $modPending > 0 || $drivePending > 0)
<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
  @if($pending > 0)
  <a href="{{ route('photographer.earnings') }}"
     class="group flex items-center gap-4 p-4 rounded-2xl border transition hover:-translate-y-0.5
            bg-amber-50 border-amber-200 hover:bg-amber-100 hover:shadow-lg
            dark:bg-amber-500/10 dark:border-amber-500/30 dark:hover:bg-amber-500/20">
    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 text-white flex items-center justify-center text-lg shrink-0 shadow-lg shadow-amber-500/30">
      <i class="bi bi-hourglass-split"></i>
    </div>
    <div class="flex-1 min-w-0">
      <div class="font-bold text-amber-900 dark:text-amber-100 text-sm">รอโอนเงิน</div>
      <div class="text-xs text-amber-800/80 dark:text-amber-200/80 mt-0.5">
        <strong class="text-base font-black">฿{{ number_format($pending, 0) }}</strong> กำลังดำเนินการ
      </div>
    </div>
    <i class="bi bi-chevron-right text-amber-600 dark:text-amber-300 opacity-40 group-hover:opacity-100 group-hover:translate-x-0.5 transition"></i>
  </a>
  @endif

  @if($modPending > 0)
  <a href="{{ route('photographer.events.index') }}"
     class="group flex items-center gap-4 p-4 rounded-2xl border transition hover:-translate-y-0.5
            bg-rose-50 border-rose-200 hover:bg-rose-100 hover:shadow-lg
            dark:bg-rose-500/10 dark:border-rose-500/30 dark:hover:bg-rose-500/20">
    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-rose-400 to-rose-600 text-white flex items-center justify-center text-lg shrink-0 shadow-lg shadow-rose-500/30">
      <i class="bi bi-shield-exclamation"></i>
    </div>
    <div class="flex-1 min-w-0">
      <div class="font-bold text-rose-900 dark:text-rose-100 text-sm">ภาพรอตรวจสอบ</div>
      <div class="text-xs text-rose-800/80 dark:text-rose-200/80 mt-0.5">
        <strong class="text-base font-black">{{ number_format($modPending) }}</strong> รูป · โปรดตรวจสอบ
      </div>
    </div>
    <i class="bi bi-chevron-right text-rose-600 dark:text-rose-300 opacity-40 group-hover:opacity-100 group-hover:translate-x-0.5 transition"></i>
  </a>
  @endif

  @if($drivePending > 0)
  <div class="flex items-center gap-4 p-4 rounded-2xl border
              bg-sky-50 border-sky-200
              dark:bg-sky-500/10 dark:border-sky-500/30">
    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-sky-400 to-sky-600 text-white flex items-center justify-center text-lg shrink-0 shadow-lg shadow-sky-500/30 animate-pulse">
      <i class="bi bi-arrow-repeat"></i>
    </div>
    <div class="flex-1 min-w-0">
      <div class="font-bold text-sky-900 dark:text-sky-100 text-sm">กำลังซิงก์จาก Drive</div>
      <div class="text-xs text-sky-800/80 dark:text-sky-200/80 mt-0.5">
        <strong class="text-base font-black">{{ $drivePending }}</strong> รายการในคิว
      </div>
    </div>
  </div>
  @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════
 3.5 SUBSCRIPTION + STORAGE (unified) · CREDITS widgets
 ─────────────────────────────────────────────────────────────────────
 The subscription widget is now the single source of truth for storage
 + plan info (replaces the old standalone quota-widget which used the
 deprecated tier-based numbers and duplicated the storage display).
 ═════════════════════════════════════════════════════════════════════ --}}
@include('photographer.partials.subscription-widget')
@include('photographer.partials.credits-widget')

{{-- ════════════════════════════════════════════════════════════════════
 4. KPI TILES — 4-col bento grid with accent bands
 ═════════════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

  {{-- Events --}}
  <div class="pg-card pg-card--hover pg-kpi pg-kpi-events">
    <div class="flex items-start justify-between mb-3">
      <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-md"
           style="background: linear-gradient(135deg, rgba(124, 58, 237, 0.15), rgba(79, 70, 229, 0.25)); color: var(--pg-accent);">
        <i class="bi bi-calendar-event"></i>
      </div>
      @if(($stats['events']['delta_pct'] ?? null) !== null)
        @php $d = $stats['events']['delta_pct']; @endphp
        <span class="pg-chip {{ $d >= 0 ? 'pg-delta-up' : 'pg-delta-down' }}">
          <i class="bi {{ $d >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
          {{ $d >= 0 ? '+' : '' }}{{ number_format($d, 1) }}%
        </span>
      @endif
    </div>
    <div class="text-[11px] font-black uppercase tracking-wider pg-text-mute mb-1">Events</div>
    <div class="text-3xl xl:text-4xl font-black pg-text leading-none mb-2 tracking-tight">
      {{ number_format($stats['events']['total']) }}
    </div>
    <div class="text-xs pg-text-soft">
      <span class="font-bold">{{ $stats['events']['active'] }}</span> เปิดขาย ·
      <span class="font-bold">{{ $stats['events']['this_mo'] }}</span> เดือนนี้
    </div>
  </div>

  {{-- Photos --}}
  <div class="pg-card pg-card--hover pg-kpi pg-kpi-photos">
    <div class="flex items-start justify-between mb-3">
      <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-md
                  bg-gradient-to-br from-sky-100 to-sky-200 text-sky-600
                  dark:from-sky-500/15 dark:to-sky-600/20 dark:text-sky-300">
        <i class="bi bi-images"></i>
      </div>
      @if($stats['photos']['pending_moderation'] > 0)
        <span class="pg-chip bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
          <i class="bi bi-exclamation-circle-fill"></i>{{ $stats['photos']['pending_moderation'] }}
        </span>
      @else
        <span class="pg-chip bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
          <i class="bi bi-check-circle-fill"></i>OK
        </span>
      @endif
    </div>
    <div class="text-[11px] font-black uppercase tracking-wider pg-text-mute mb-1">Photos</div>
    <div class="text-3xl xl:text-4xl font-black pg-text leading-none mb-2 tracking-tight">
      {{ number_format($stats['photos']['total']) }}
    </div>
    <div class="text-xs pg-text-soft">
      <span class="font-bold">{{ number_format($stats['photos']['active']) }}</span> แสดงต่อสาธารณะ
    </div>
  </div>

  {{-- Sales --}}
  <div class="pg-card pg-card--hover pg-kpi pg-kpi-sales">
    <div class="flex items-start justify-between mb-3">
      <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-md
                  bg-gradient-to-br from-emerald-100 to-teal-200 text-emerald-600
                  dark:from-emerald-500/15 dark:to-teal-600/20 dark:text-emerald-300">
        <i class="bi bi-bag-check"></i>
      </div>
      @if(($stats['sales']['delta_pct'] ?? null) !== null)
        @php $d = $stats['sales']['delta_pct']; @endphp
        <span class="pg-chip {{ $d >= 0 ? 'pg-delta-up' : 'pg-delta-down' }}">
          <i class="bi {{ $d >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
          {{ $d >= 0 ? '+' : '' }}{{ number_format($d, 1) }}%
        </span>
      @endif
    </div>
    <div class="text-[11px] font-black uppercase tracking-wider pg-text-mute mb-1">Sales</div>
    <div class="text-3xl xl:text-4xl font-black pg-text leading-none mb-2 tracking-tight">
      {{ number_format($stats['sales']['total']) }}
    </div>
    <div class="text-xs pg-text-soft">
      AOV <span class="font-bold">฿{{ number_format($stats['sales']['aov'], 0) }}</span> ·
      <span class="font-bold">{{ $stats['sales']['this_mo'] }}</span> เดือนนี้
    </div>
  </div>

  {{-- Earnings --}}
  <div class="pg-card pg-card--hover pg-kpi pg-kpi-earnings">
    <div class="flex items-start justify-between mb-3">
      <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-md
                  bg-gradient-to-br from-amber-100 to-orange-200 text-amber-600
                  dark:from-amber-500/15 dark:to-orange-600/20 dark:text-amber-300">
        <i class="bi bi-cash-stack"></i>
      </div>
      @if(($stats['earnings']['delta_pct'] ?? null) !== null)
        @php $d = $stats['earnings']['delta_pct']; @endphp
        <span class="pg-chip {{ $d >= 0 ? 'pg-delta-up' : 'pg-delta-down' }}">
          <i class="bi {{ $d >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
          {{ $d >= 0 ? '+' : '' }}{{ number_format($d, 1) }}%
        </span>
      @endif
    </div>
    <div class="text-[11px] font-black uppercase tracking-wider pg-text-mute mb-1">Earnings</div>
    <div class="text-3xl xl:text-4xl font-black pg-text leading-none mb-2 tracking-tight">
      ฿{{ number_format($stats['earnings']['total'], 0) }}
    </div>
    <div class="text-xs pg-text-soft flex items-center gap-2">
      <span class="text-emerald-600 dark:text-emerald-300 font-bold">฿{{ number_format($stats['earnings']['paid'], 0) }}</span>รับแล้ว
      <span class="pg-text-mute">·</span>
      <span class="text-amber-600 dark:text-amber-300 font-bold">฿{{ number_format($stats['earnings']['pending'], 0) }}</span>รอ
    </div>
  </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════
 5. QUICK ACTIONS
 ═════════════════════════════════════════════════════════════════════ --}}
<div class="pg-card p-4 mb-6">
  <div class="flex flex-wrap gap-2 items-center">
    <span class="text-[11px] font-black uppercase tracking-[0.22em] pg-text-mute px-2 inline-flex items-center gap-1.5">
      <i class="bi bi-lightning-charge-fill text-amber-500"></i>ทางลัด
    </span>
    <a href="{{ route('photographer.events.create') }}" class="pg-quick"><i class="bi bi-plus-circle"></i>สร้าง Event</a>
    <a href="{{ route('photographer.events.index') }}" class="pg-quick"><i class="bi bi-cloud-upload"></i>อัปโหลดรูป</a>
    <a href="{{ route('photographer.earnings') }}" class="pg-quick"><i class="bi bi-cash"></i>ดูรายได้</a>
    <a href="{{ route('photographer.analytics') }}" class="pg-quick"><i class="bi bi-graph-up-arrow"></i>Analytics</a>
    <a href="{{ route('photographer.reviews') }}" class="pg-quick"><i class="bi bi-star"></i>รีวิว</a>
    <a href="{{ route('photographer.profile') }}" class="pg-quick"><i class="bi bi-person-gear"></i>โปรไฟล์</a>
    <a href="{{ route('photographer.setup-bank') }}" class="pg-quick"><i class="bi bi-bank"></i>บัญชีธนาคาร</a>
  </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════
 6. REVENUE AREA CHART + TOP EVENTS (2/3 + 1/3)
 ═════════════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

  {{-- Revenue area chart --}}
  <div class="lg:col-span-2 pg-card overflow-hidden">
    <div class="flex items-start justify-between px-6 py-5 border-b pg-divide flex-wrap gap-2">
      <div>
        <h3 class="text-sm font-black pg-text inline-flex items-center gap-2 mb-1">
          <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white"
                style="background: linear-gradient(135deg, var(--pg-accent), var(--pg-accent-2));">
            <i class="bi bi-graph-up-arrow text-xs"></i>
          </span>
          รายได้ย้อนหลัง 6 เดือน
        </h3>
        <p class="text-xs pg-text-mute ml-10">
          รวม <span class="font-black pg-text">฿{{ number_format($stats['revenue']['all'], 0) }}</span> ตั้งแต่เริ่มต้น
        </p>
      </div>
      @if(($stats['revenue']['delta_pct'] ?? null) !== null && $stats['revenue']['delta_pct'] !== 0.0)
        @php $d = $stats['revenue']['delta_pct']; @endphp
        <span class="pg-chip {{ $d >= 0 ? 'pg-delta-up' : 'pg-delta-down' }}">
          <i class="bi {{ $d >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
          เดือนนี้ {{ $d > 0 ? '+' : '' }}{{ number_format($d, 1) }}%
        </span>
      @endif
    </div>
    <div class="p-5">
      <div class="pg-chart-wrap">
        <svg class="pg-chart-svg" viewBox="0 0 {{ $chartW }} {{ $chartH }}" preserveAspectRatio="none">
          <defs>
            <linearGradient id="pg-chart-gradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="var(--pg-chart-stroke)" stop-opacity="0.35"/>
              <stop offset="100%" stop-color="var(--pg-chart-stroke)" stop-opacity="0"/>
            </linearGradient>
          </defs>

          {{-- Gridlines --}}
          <g class="pg-chart-grid">
            @for($g = 0; $g <= 4; $g++)
              @php $gy = $chartPad['top'] + ($plotH * $g / 4); @endphp
              <line x1="{{ $chartPad['left'] }}" y1="{{ $gy }}" x2="{{ $chartW - $chartPad['right'] }}" y2="{{ $gy }}"/>
            @endfor
          </g>

          {{-- Filled area --}}
          @if($pathArea)<path class="pg-chart-fill" d="{{ $pathArea }}"/>@endif
          {{-- Line --}}
          @if($pathLine)<path class="pg-chart-line" d="{{ $pathLine }}"/>@endif

          {{-- Dots + labels --}}
          @foreach($chartPoints as $i => $pt)
            <circle class="pg-chart-dot {{ $i === count($chartPoints) - 1 ? 'pg-chart-dot--last' : '' }}"
                    cx="{{ round($pt['x'], 2) }}" cy="{{ round($pt['y'], 2) }}"
                    r="{{ $i === count($chartPoints) - 1 ? 6 : 4 }}"/>
            <text class="pg-chart-label" x="{{ round($pt['x'], 2) }}" y="{{ $chartH - 8 }}">{{ $pt['label'] }}</text>
            @if($pt['value'] > 0 && $i === count($chartPoints) - 1)
              <text class="pg-chart-value" x="{{ round($pt['x'], 2) }}" y="{{ max(14, round($pt['y'], 2) - 14) }}">฿{{ number_format($pt['value'], 0) }}</text>
            @endif
          @endforeach
        </svg>
      </div>
    </div>
  </div>

  {{-- Top Events leaderboard --}}
  <div class="pg-card overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b pg-divide">
      <h3 class="text-sm font-black pg-text inline-flex items-center gap-2">
        <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white bg-gradient-to-br from-amber-400 to-amber-600">
          <i class="bi bi-trophy-fill text-xs"></i>
        </span>
        Top Events
      </h3>
    </div>
    <div class="p-2">
      @forelse(($topEvents ?? []) as $i => $ev)
        <a href="{{ route('photographer.events.edit', $ev->id) }}"
           class="pg-row flex items-center gap-3 px-3 py-2.5 rounded-xl transition">
          <div class="pg-rank {{ $i === 0 ? 'pg-rank--1' : ($i === 1 ? 'pg-rank--2' : ($i === 2 ? 'pg-rank--3' : 'pg-rank--default')) }}">
            #{{ $i + 1 }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-bold pg-text truncate">{{ $ev->name }}</div>
            <div class="text-xs pg-text-mute">
              {{ $ev->shoot_date?->format('d M Y') ?? 'ไม่ระบุวันที่' }}
            </div>
          </div>
          <div class="text-right shrink-0">
            <div class="text-sm font-black text-emerald-600 dark:text-emerald-300">
              ฿{{ number_format((float) $ev->total_revenue, 0) }}
            </div>
          </div>
        </a>
      @empty
        <div class="pg-empty text-center py-10 px-4 text-sm pg-text-mute">
          <i class="bi bi-trophy text-3xl block mb-2"></i>
          ยังไม่มียอดขาย<br>
          <span class="text-xs">เริ่มสร้าง Event แรกเพื่อเริ่มต้น</span>
        </div>
      @endforelse
    </div>
  </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════
 7. RECENT EVENTS TABLE + SIDEBAR
 ═════════════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

  {{-- Recent Events Table --}}
  <div class="lg:col-span-7 pg-card overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b pg-divide">
      <h3 class="text-sm font-black pg-text inline-flex items-center gap-2">
        <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white"
              style="background: linear-gradient(135deg, var(--pg-accent), var(--pg-accent-2));">
          <i class="bi bi-calendar-event text-xs"></i>
        </span>
        Event ล่าสุด
      </h3>
      <a href="{{ route('photographer.events.index') }}"
         class="text-xs font-bold pg-text-soft hover:pg-text inline-flex items-center gap-1 transition">
        ดูทั้งหมด <i class="bi bi-arrow-right"></i>
      </a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead style="background: var(--pg-surface-2);">
          <tr class="text-left text-[11px] uppercase tracking-widest pg-text-mute">
            <th class="px-5 py-3 font-black">ชื่องาน</th>
            <th class="px-5 py-3 font-black">วันที่งาน</th>
            <th class="px-5 py-3 font-black">ราคา/รูป</th>
            <th class="px-5 py-3 font-black">ออเดอร์</th>
            <th class="px-5 py-3 font-black">สถานะ</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentEvents ?? [] as $event)
          <tr class="pg-row border-t pg-divide transition">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                @if($event->cover_image)
                  <img src="{{ $event->cover_image_url }}" alt=""
                       class="w-11 h-11 rounded-xl object-cover shrink-0 border pg-divide shadow-sm">
                @else
                  <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 shadow-sm"
                       style="background: var(--pg-accent-soft); color: var(--pg-accent);">
                    <i class="bi bi-image"></i>
                  </div>
                @endif
                <div class="font-bold pg-text truncate max-w-[220px]" title="{{ $event->name }}">
                  {{ $event->name }}
                </div>
              </div>
            </td>
            <td class="px-5 py-3 pg-text-mute whitespace-nowrap">
              {{ $event->shoot_date?->format('d M Y') ?? '-' }}
            </td>
            <td class="px-5 py-3 whitespace-nowrap">
              @if($event->is_free)
                <span class="pg-chip bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">ฟรี</span>
              @else
                <span class="font-black pg-text">{{ number_format((float) $event->price_per_photo, 0) }}</span>
                <span class="text-xs pg-text-mute">฿</span>
              @endif
            </td>
            <td class="px-5 py-3">
              <span class="font-bold pg-text">
                {{ number_format((int) ($event->order_count ?? 0)) }}
              </span>
            </td>
            <td class="px-5 py-3">
              @php
                $stMap = [
                  'active'    => ['bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'เปิดขาย'],
                  'published' => ['bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'เผยแพร่'],
                  'draft'     => ['bg-slate-200 text-slate-700 dark:bg-white/10 dark:text-slate-300', 'Draft'],
                  'archived'  => ['bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300', 'เก็บถาวร'],
                  'closed'    => ['bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300', 'ปิด'],
                  'hidden'    => ['bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300', 'ซ่อน'],
                ];
                $st = $stMap[$event->status ?? 'draft'] ?? ['bg-slate-200 text-slate-700 dark:bg-white/10 dark:text-slate-300', ucfirst((string) $event->status)];
              @endphp
              <span class="pg-chip {{ $st[0] }}">{{ $st[1] }}</span>
            </td>
            <td class="px-5 py-3 text-right">
              <a href="{{ route('photographer.events.edit', $event->id) }}"
                 class="pg-btn pg-btn--ghost text-xs py-1.5 px-3">
                <i class="bi bi-pencil"></i>แก้ไข
              </a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-12 pg-empty">
              <i class="bi bi-calendar-plus text-4xl block mb-3"></i>
              <p class="text-sm pg-text-mute mb-3">ยังไม่มี Event</p>
              <a href="{{ route('photographer.events.create') }}" class="pg-btn pg-btn--primary">
                <i class="bi bi-plus-lg"></i>สร้าง Event แรก
              </a>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Right column: Earnings + Orders + Reviews stack --}}
  <div class="lg:col-span-5 space-y-4">

    {{-- Earnings summary --}}
    <div class="pg-card overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b pg-divide">
        <h3 class="text-sm font-black pg-text inline-flex items-center gap-2">
          <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white bg-gradient-to-br from-emerald-400 to-teal-600">
            <i class="bi bi-wallet2 text-xs"></i>
          </span>
          รายได้ล่าสุด
        </h3>
        <a href="{{ route('photographer.earnings') }}"
           class="text-xs font-bold text-emerald-600 dark:text-emerald-300 hover:underline">
          ดูทั้งหมด
        </a>
      </div>
      <div class="px-5 py-4 grid grid-cols-2 gap-4 border-b pg-divide">
        <div>
          <div class="text-[11px] uppercase tracking-widest font-black pg-text-mute mb-1">รับแล้ว</div>
          <div class="text-xl font-black text-emerald-600 dark:text-emerald-300 tracking-tight">
            ฿{{ number_format($stats['earnings']['paid'], 0) }}
          </div>
        </div>
        <div class="pl-4 border-l pg-divide">
          <div class="text-[11px] uppercase tracking-widest font-black pg-text-mute mb-1">รอโอน</div>
          <div class="text-xl font-black text-amber-600 dark:text-amber-300 tracking-tight">
            ฿{{ number_format($stats['earnings']['pending'], 0) }}
          </div>
        </div>
      </div>
      <div class="divide-y pg-divide">
        @forelse(($recentEarnings ?? []) as $e)
          <div class="flex items-center gap-3 px-5 py-3 pg-row transition">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm shrink-0
                        bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-md shadow-emerald-500/20">
              <i class="bi bi-receipt"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-bold pg-text truncate">
                {{ $e->event_title ?? ('Order #' . $e->order_id) }}
              </div>
              <div class="text-xs pg-text-mute">
                {{ $e->created_at?->diffForHumans() }}
                @if($e->status === 'pending')
                  · <span class="text-amber-600 dark:text-amber-300 font-semibold">รอโอน</span>
                @elseif($e->status === 'paid')
                  · <span class="text-emerald-600 dark:text-emerald-300 font-semibold">โอนแล้ว</span>
                @endif
              </div>
            </div>
            <div class="text-sm font-black text-emerald-600 dark:text-emerald-300 shrink-0">
              +฿{{ number_format($e->amount, 0) }}
            </div>
          </div>
        @empty
          <div class="pg-empty text-center py-8 px-4 text-sm pg-text-mute">
            <i class="bi bi-wallet2 text-3xl block mb-2"></i>
            ยังไม่มีรายได้
          </div>
        @endforelse
      </div>
    </div>

    {{-- Recent Orders --}}
    @if(($recentOrders ?? collect())->isNotEmpty())
    <div class="pg-card overflow-hidden">
      <div class="px-5 py-4 border-b pg-divide">
        <h3 class="text-sm font-black pg-text inline-flex items-center gap-2">
          <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white bg-gradient-to-br from-sky-400 to-sky-600">
            <i class="bi bi-bag-check-fill text-xs"></i>
          </span>
          ออเดอร์ล่าสุด
        </h3>
      </div>
      <div class="divide-y pg-divide">
        @foreach($recentOrders as $o)
          <div class="pg-row px-5 py-3 flex items-center gap-3 transition">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0
                        bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-300">
              <i class="bi bi-person-check-fill"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm pg-text truncate">
                <span class="font-bold">{{ trim(($o->user?->first_name ?? '') . ' ' . ($o->user?->last_name ?? '')) ?: 'ลูกค้า' }}</span>
                <span class="pg-text-mute mx-1">·</span>
                <span class="text-xs pg-text-mute truncate">{{ $o->event?->name ?? '—' }}</span>
              </div>
              <div class="text-[11px] pg-text-mute mt-0.5">
                {{ $o->created_at?->diffForHumans() }}
              </div>
            </div>
            <div class="text-sm font-black text-emerald-600 dark:text-emerald-300 shrink-0">
              ฿{{ number_format((float) $o->total, 0) }}
            </div>
          </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Recent Reviews --}}
    @if(($recentReviews ?? collect())->isNotEmpty())
    <div class="pg-card overflow-hidden">
      <div class="px-5 py-4 border-b pg-divide flex items-center justify-between">
        <h3 class="text-sm font-black pg-text inline-flex items-center gap-2">
          <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white bg-gradient-to-br from-amber-400 to-amber-600">
            <i class="bi bi-star-fill text-xs"></i>
          </span>
          รีวิวล่าสุด
        </h3>
        <a href="{{ route('photographer.reviews') }}"
           class="text-xs font-bold text-amber-600 dark:text-amber-300 hover:underline">
          ดูทั้งหมด
        </a>
      </div>
      <div class="divide-y pg-divide">
        @foreach($recentReviews as $r)
          <div class="px-5 py-3">
            <div class="flex items-center gap-2 mb-1">
              <div class="flex gap-0.5 text-amber-500">
                @for($i = 1; $i <= 5; $i++)
                  <i class="bi bi-star{{ $i <= (int) ($r->rating ?? 0) ? '-fill' : '' }} text-xs"></i>
                @endfor
              </div>
              <span class="text-xs pg-text-mute">
                {{ trim(($r->user?->first_name ?? '') . ' ' . ($r->user?->last_name ?? '')) ?: 'ลูกค้า' }}
                · {{ $r->created_at?->diffForHumans() }}
              </span>
            </div>
            @if(!empty($r->comment))
              <div class="text-xs pg-text-soft line-clamp-2">
                {{ \Illuminate\Support\Str::limit($r->comment, 140) }}
              </div>
            @endif
          </div>
        @endforeach
      </div>
    </div>
    @endif

  </div>
</div>

@endsection
