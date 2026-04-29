@extends('layouts.admin')

@section('title', 'ตั้งค่า LINE')

{{-- =======================================================================
     LINE SETTINGS — LAYOUT-ONLY REDESIGN
     -------------------------------------------------------------------
     • Form fields, IDs, names, JS functions, and form action are unchanged.
     • All prior broken CSS selectors removed; replaced with pure Tailwind.
     • Explicit light / dark surface tokens, same convention as the rest of
       the admin settings pages (language.blade.php).
     ====================================================================== --}}

@push('styles')
<style>
  /* ═══════════════════════════════════════════════════════════════════════
   * LINE Settings — Premium Theme Redesign
   * ─────────────────────────────────────────────────────────────────────
   * Color story per channel:
   *   A — LINE Login         #06C755 (LINE official green)
   *   B — Messaging API      #7c3aed (violet, like our admin theme)
   *   C — Admin Alerts       #f59e0b (amber — Messaging API multicast,
   *                           replaces dead LINE Notify killed 31 Mar 2025)
   *
   * Visual hierarchy:
   *   Hero panel    — LINE-green gradient, animated blobs, status pills
   *   Architecture  — 3-pillar flow with arrows showing data flow
   *   Each section  — coloured top-strip + numbered hero badge + status
   * ═══════════════════════════════════════════════════════════════════ */

  /* ── Toggle switch (existing — unchanged behavior) ─────────────── */
  .tw-switch { position: relative; display: inline-block; width: 2.75rem; height: 1.5rem; flex-shrink: 0; }
  .tw-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
  .tw-switch .slider {
    position: absolute; inset: 0;
    background: rgb(226 232 240);
    border-radius: 9999px; cursor: pointer; transition: background-color .2s;
  }
  .dark .tw-switch .slider { background: rgb(51 65 85); }
  .tw-switch .slider::before {
    content: ''; position: absolute;
    height: 1.125rem; width: 1.125rem; left: 3px; top: 3px;
    background: #fff; border-radius: 9999px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.25);
    transition: transform .2s;
  }
  .tw-switch input:checked + .slider { background: linear-gradient(135deg, #6366f1, #818cf8); }
  .tw-switch input:checked + .slider::before { transform: translateX(1.25rem); }
  .tw-switch input:focus-visible + .slider { box-shadow: 0 0 0 3px rgba(99,102,241,0.35); }
  .tw-switch input:disabled + .slider { opacity: 0.5; cursor: not-allowed; }

  /* ── Status pill ──────────────────────────────────────────────── */
  .status-dot {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-size: 0.75rem; font-weight: 600;
    padding: 0.3rem 0.75rem; border-radius: 9999px;
    white-space: nowrap; backdrop-filter: blur(8px);
  }
  .status-dot::before {
    content: ''; width: 7px; height: 7px; border-radius: 50%;
    background: currentColor; flex-shrink: 0;
    animation: pulse-dot 2s ease-in-out infinite;
  }
  @keyframes pulse-dot {
    0%,100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(0.85); }
  }
  .status-dot.connected    { background: rgb(209 250 229); color: rgb(4 120 87); }
  .status-dot.disconnected { background: rgb(254 226 226); color: rgb(185 28 28); }
  .status-dot.unknown      { background: rgb(226 232 240); color: rgb(71 85 105); }
  .dark .status-dot.connected    { background: rgba(16,185,129,0.15); color: rgb(110 231 183); }
  .dark .status-dot.disconnected { background: rgba(239,68,68,0.15);  color: rgb(252 165 165); }
  .dark .status-dot.unknown      { background: rgba(148,163,184,0.15); color: rgb(203 213 225); }

  /* ── Inline loading spinner ───────────────────────────────────── */
  .tw-spinner {
    display: inline-block; width: 0.9rem; height: 0.9rem;
    border: 2px solid currentColor; border-top-color: transparent;
    border-radius: 50%; animation: twspin 0.7s linear infinite;
    margin-right: 0.35rem; vertical-align: -2px;
  }
  @keyframes twspin { to { transform: rotate(360deg); } }

  /* ── Copy-btn copied state ────────────────────────────────────── */
  .copy-btn.copied {
    border-color: rgb(16 185 129) !important;
    color: rgb(5 150 105) !important;
    background: rgb(209 250 229) !important;
  }
  .dark .copy-btn.copied {
    border-color: rgb(16 185 129) !important;
    color: rgb(110 231 183) !important;
    background: rgba(16,185,129,0.15) !important;
  }

  /* ── HERO panel — LINE-themed gradient banner ─────────────────── */
  .line-hero {
    position: relative;
    border-radius: 28px;
    overflow: hidden;
    background: linear-gradient(135deg, #00b900 0%, #06C755 35%, #00b04f 100%);
    color: #fff;
    box-shadow: 0 20px 60px -20px rgba(6,199,85,0.35), 0 8px 24px -8px rgba(0,176,79,0.25);
  }
  .dark .line-hero {
    background: linear-gradient(135deg, #064e2c 0%, #065f3a 35%, #047a45 100%);
    box-shadow: 0 24px 70px -20px rgba(6,199,85,0.40);
  }
  .line-hero__pattern {
    position: absolute; inset: 0; pointer-events: none;
    background-image:
      radial-gradient(circle at 15% 110%, rgba(255,255,255,0.18), transparent 45%),
      radial-gradient(circle at 90% -10%, rgba(255,255,255,0.12), transparent 50%),
      radial-gradient(circle at 50% 50%, rgba(255,255,255,0.04), transparent 60%);
  }
  .line-hero__grid {
    position: absolute; inset: 0; pointer-events: none; opacity: 0.4;
    background-image:
      linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
    background-size: 28px 28px;
    mask-image: radial-gradient(ellipse 70% 50% at 50% 0%, #000 35%, transparent 80%);
  }
  .line-hero__blob {
    position: absolute; border-radius: 50%; filter: blur(48px); pointer-events: none;
  }
  .line-hero__blob-1 {
    width: 320px; height: 320px;
    background: radial-gradient(circle, rgba(255,255,255,0.4), transparent 70%);
    top: -120px; right: -80px;
    animation: float 18s ease-in-out infinite alternate;
  }
  .line-hero__blob-2 {
    width: 240px; height: 240px;
    background: radial-gradient(circle, rgba(160,255,200,0.3), transparent 70%);
    bottom: -100px; left: 30%;
    animation: float 22s ease-in-out infinite alternate-reverse;
  }
  @keyframes float {
    0% { transform: translate(0,0) scale(1); }
    100% { transform: translate(30px,-20px) scale(1.05); }
  }

  /* ── Section card — top accent strip + glow on hover ──────────── */
  .ls-card {
    position: relative;
    border-radius: 22px; overflow: hidden;
    background: #fff;
    border: 1px solid rgb(226 232 240);
    box-shadow: 0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05);
    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
  }
  .dark .ls-card {
    background: rgb(15 23 42);
    border-color: rgba(255,255,255,0.08);
    box-shadow: 0 1px 3px rgba(0,0,0,0.4), 0 16px 36px rgba(0,0,0,0.25);
  }
  .ls-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 1px 3px rgba(15,23,42,0.04), 0 22px 50px rgba(15,23,42,0.08);
  }
  .ls-card::before {
    content: ''; position: absolute; left: 0; top: 0; right: 0; height: 4px;
    background: var(--ls-accent, #06C755);
  }
  .ls-card.is-login   { --ls-accent: linear-gradient(90deg, #06C755, #00b04f, #00b900); }
  .ls-card.is-message { --ls-accent: linear-gradient(90deg, #6366f1, #7c3aed, #a855f7); }
  .ls-card.is-notify  { --ls-accent: linear-gradient(90deg, #f59e0b, #d97706, #b45309); }
  .ls-card.is-toggles { --ls-accent: linear-gradient(90deg, #0ea5e9, #6366f1, #ec4899); }

  /* ── Numbered hero badge per section ──────────────────────────── */
  .ls-badge {
    width: 52px; height: 52px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 14px; color: #fff;
    font-weight: 900; font-size: 1.4rem; letter-spacing: -0.02em;
    box-shadow: 0 8px 20px -4px var(--ls-badge-shadow, rgba(6,199,85,0.45));
    flex-shrink: 0;
  }
  .ls-badge.is-login   { background: linear-gradient(135deg, #06C755, #00b04f); --ls-badge-shadow: rgba(6,199,85,0.45); }
  .ls-badge.is-message { background: linear-gradient(135deg, #7c3aed, #6366f1); --ls-badge-shadow: rgba(124,58,237,0.45); }
  .ls-badge.is-notify  { background: linear-gradient(135deg, #f59e0b, #d97706); --ls-badge-shadow: rgba(245,158,11,0.40); }
  .ls-badge.is-toggles { background: linear-gradient(135deg, #0ea5e9, #6366f1); --ls-badge-shadow: rgba(14,165,233,0.40); }

  /* ── Section header bar ───────────────────────────────────────── */
  .ls-section-head {
    padding: 1.5rem 1.5rem 1.25rem;
    border-bottom: 1px solid rgba(15,23,42,0.06);
    display: flex; align-items: flex-start; gap: 1rem; flex-wrap: wrap;
    background: linear-gradient(180deg, var(--ls-head-bg, transparent), transparent);
  }
  .dark .ls-section-head { border-bottom-color: rgba(255,255,255,0.05); }
  .ls-card.is-login .ls-section-head    { --ls-head-bg: rgba(6,199,85,0.04); }
  .ls-card.is-message .ls-section-head  { --ls-head-bg: rgba(124,58,237,0.04); }
  .ls-card.is-notify .ls-section-head   { --ls-head-bg: rgba(245,158,11,0.04); }
  .ls-card.is-toggles .ls-section-head  { --ls-head-bg: rgba(14,165,233,0.04); }
  .dark .ls-card.is-login .ls-section-head    { --ls-head-bg: rgba(6,199,85,0.10); }
  .dark .ls-card.is-message .ls-section-head  { --ls-head-bg: rgba(124,58,237,0.10); }
  .dark .ls-card.is-notify .ls-section-head   { --ls-head-bg: rgba(245,158,11,0.10); }
  .dark .ls-card.is-toggles .ls-section-head  { --ls-head-bg: rgba(14,165,233,0.10); }

  /* ── Stagger fade-in ──────────────────────────────────────────── */
  @keyframes ls-fade {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .ls-anim { animation: ls-fade .5s cubic-bezier(0.34,1.56,0.64,1) both; }
  .ls-anim.d1 { animation-delay: .05s; }
  .ls-anim.d2 { animation-delay: .12s; }
  .ls-anim.d3 { animation-delay: .19s; }
  .ls-anim.d4 { animation-delay: .26s; }
  .ls-anim.d5 { animation-delay: .33s; }

  /* ── Pillar (architecture diagram) ────────────────────────────── */
  .ls-pillar {
    position: relative;
    border-radius: 16px;
    background: #fff;
    border: 1px solid rgba(15,23,42,0.08);
    padding: 1rem;
    transition: transform .2s ease, box-shadow .2s ease;
  }
  .dark .ls-pillar {
    background: rgb(15 23 42);
    border-color: rgba(255,255,255,0.08);
  }
  .ls-pillar:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(15,23,42,0.08);
  }
  .ls-pillar-num {
    width: 28px; height: 28px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 8px; color: #fff; font-weight: 900; font-size: 0.78rem;
  }

  /* ── Float labels for inputs (hint floats above) ───────────────── */
  .ls-input-wrap { position: relative; }

  /* ── Save bar (sticky-feel footer) ────────────────────────────── */
  .ls-save-bar {
    background: linear-gradient(135deg, #6366f1, #7c3aed, #a855f7);
    background-size: 200% 200%;
    animation: ls-shine 6s ease-in-out infinite;
  }
  @keyframes ls-shine {
    0%, 100% { background-position: 0% 50%; }
    50%      { background-position: 100% 50%; }
  }
</style>
@endpush

@section('content')
@php
  // Compute "completion" status per section so the hero shows progress
  // and each section header can show a green-check or "ยังไม่ตั้ง" pill.
  // Note: LINE Notify was removed (deprecated by LINE 31 Mar 2025) — admin
  // alerts now flow via Messaging API multicast to line_admin_user_ids.
  $loginConfigured     = !empty($settings['line_login_channel_id']) && !empty($settings['line_login_channel_secret']);
  $messagingConfigured = !empty($settings['line_channel_access_token']);
  $adminAlertsReady    = $messagingConfigured && !empty($settings['line_admin_user_ids']);
  $configuredCount     = (int) $loginConfigured + (int) $messagingConfigured + (int) $adminAlertsReady;
@endphp
<div class="max-w-7xl mx-auto pb-16">

  {{-- ════════════════════════════════════════════════════════════════════
       1. HERO PANEL — premium LINE-themed greeting with completion stats
       ════════════════════════════════════════════════════════════════════ --}}
  <div class="line-hero ls-anim mb-6 px-6 md:px-10 py-8 md:py-10">
    <div class="line-hero__pattern"></div>
    <div class="line-hero__grid"></div>
    <div class="line-hero__blob line-hero__blob-1"></div>
    <div class="line-hero__blob line-hero__blob-2"></div>

    <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
      {{-- Left: LINE icon + title + subtitle --}}
      <div class="lg:col-span-7 flex items-start gap-5">
        <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-3xl shrink-0 shadow-xl shadow-black/20">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="white" aria-hidden="true">
            <path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.105.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.281.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-[11px] uppercase tracking-[0.3em] text-white/75 font-bold mb-2 flex items-center gap-2">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
            <span>Integration Center</span>
          </div>
          <h1 class="font-bold text-2xl md:text-3xl xl:text-4xl tracking-tight mb-2 leading-tight">
            ตั้งค่า <span class="bg-gradient-to-r from-yellow-100 via-white to-emerald-100 bg-clip-text text-transparent">LINE</span>
          </h1>
          <p class="text-sm md:text-base text-white/85">
            จัดการ Login (OAuth) · Messaging API — credential แยกชัดเจน + แจ้งเตือนแอดมินผ่าน Messaging API
          </p>
        </div>
      </div>

      {{-- Right: progress + actions --}}
      <div class="lg:col-span-5 flex flex-col gap-3 lg:items-end">
        {{-- Completion progress card --}}
        <div class="bg-white/15 backdrop-blur-md border border-white/25 rounded-2xl p-4 w-full lg:max-w-xs shadow-xl shadow-black/10">
          <div class="flex items-center justify-between mb-2">
            <div class="text-[11px] uppercase tracking-widest text-white/75 font-bold">ความพร้อมใช้งาน</div>
            <div class="text-xl font-black tracking-tight">{{ $configuredCount }}<span class="text-white/60 text-sm font-bold">/3</span></div>
          </div>
          <div class="h-1.5 rounded-full bg-white/20 overflow-hidden mb-2.5">
            <div class="h-full rounded-full bg-gradient-to-r from-yellow-200 to-emerald-200 transition-all"
                 style="width:{{ $configuredCount === 0 ? 5 : ($configuredCount / 3) * 100 }}%;"></div>
          </div>
          <div class="grid grid-cols-3 gap-1 text-[10px]">
            <span class="inline-flex items-center gap-1 {{ $loginConfigured ? 'text-white' : 'text-white/50' }}">
              <i class="bi bi-{{ $loginConfigured ? 'check-circle-fill' : 'circle' }}"></i>Login
            </span>
            <span class="inline-flex items-center gap-1 {{ $messagingConfigured ? 'text-white' : 'text-white/50' }}">
              <i class="bi bi-{{ $messagingConfigured ? 'check-circle-fill' : 'circle' }}"></i>Push
            </span>
            <span class="inline-flex items-center gap-1 {{ $adminAlertsReady ? 'text-white' : 'text-white/50' }}">
              <i class="bi bi-{{ $adminAlertsReady ? 'check-circle-fill' : 'circle' }}"></i>Admin Alerts
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
          <a href="{{ route('admin.settings.line-richmenu') }}"
             class="inline-flex items-center gap-2 bg-white text-emerald-700 hover:bg-emerald-50 font-bold text-sm px-4 py-2.5 rounded-xl shadow-lg shadow-black/20 transition hover:-translate-y-0.5 no-underline">
            <i class="bi bi-list"></i> Rich Menu
          </a>
          <a href="{{ route('admin.settings.line-test') }}"
             class="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 backdrop-blur-md border border-white/25 text-white font-semibold text-sm px-4 py-2.5 rounded-xl transition no-underline">
            <i class="bi bi-clipboard-check"></i> ทดสอบ
          </a>
          <a href="{{ route('admin.settings.index') }}"
             class="inline-flex items-center gap-1.5 bg-white/15 hover:bg-white/25 backdrop-blur-md border border-white/25 text-white font-semibold text-sm px-4 py-2.5 rounded-xl transition no-underline">
            <i class="bi bi-arrow-left"></i> กลับ
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       2. ARCHITECTURE FLOW DIAGRAM — visual flow of the 3 services
       ════════════════════════════════════════════════════════════════════ --}}
  <div class="ls-anim d1 mb-6 rounded-2xl overflow-hidden
              bg-white dark:bg-slate-900
              border border-slate-200 dark:border-white/10
              shadow-sm">
    <div class="px-5 py-3.5 border-b border-slate-100 dark:border-white/5 flex items-center gap-2.5
                bg-gradient-to-r from-emerald-50 via-white to-violet-50
                dark:from-emerald-500/5 dark:via-slate-900 dark:to-violet-500/5">
      <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-violet-500 text-white shadow-sm">
        <i class="bi bi-diagram-3-fill text-sm"></i>
      </span>
      <div class="flex-1">
        <div class="font-bold text-sm text-slate-900 dark:text-white">โครงสร้างของระบบ LINE — 3 channel แยกกัน ใช้ credential คนละชุด</div>
        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">สร้าง A และ B ภายใต้ <strong>Provider เดียวกัน</strong> เพื่อให้ <code>userId</code> ตรงกัน</div>
      </div>
    </div>

    <div class="p-5">
      <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr_auto_1fr] gap-3 md:gap-2 items-stretch">
        {{-- Pillar A: Login --}}
        <div class="ls-pillar">
          <div class="flex items-center gap-2 mb-2">
            <span class="ls-pillar-num" style="background:linear-gradient(135deg,#06C755,#00b04f);">A</span>
            <span class="font-bold text-sm text-slate-900 dark:text-white">LINE Login</span>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-2">
            ลูกค้า / ช่างภาพคลิก "เข้าสู่ระบบด้วย LINE" → ได้ <code class="text-emerald-600 dark:text-emerald-400">userId</code> มาเก็บใน DB
          </p>
          <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">ใช้: ID + Secret</div>
        </div>

        {{-- Arrow 1 --}}
        <div class="hidden md:flex items-center justify-center text-slate-300 dark:text-slate-600">
          <i class="bi bi-arrow-right text-xl"></i>
        </div>

        {{-- Pillar B: Messaging --}}
        <div class="ls-pillar">
          <div class="flex items-center gap-2 mb-2">
            <span class="ls-pillar-num" style="background:linear-gradient(135deg,#7c3aed,#6366f1);">B</span>
            <span class="font-bold text-sm text-slate-900 dark:text-white">Messaging API</span>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-2">
            ระบบยิง <code class="text-violet-600 dark:text-violet-400">push(userId, ...)</code> ไปหาลูกค้าที่ add OA เป็นเพื่อน
          </p>
          <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">ใช้: Access Token + Webhook</div>
        </div>

        {{-- Arrow 2 (vertical on small, horizontal on md+) --}}
        <div class="hidden md:flex items-center justify-center text-slate-300 dark:text-slate-600">
          <i class="bi bi-three-dots text-xl"></i>
        </div>

        {{-- Pillar C: Admin Alerts (delivered via Messaging API multicast) --}}
        <div class="ls-pillar">
          <div class="flex items-center gap-2 mb-2">
            <span class="ls-pillar-num" style="background:linear-gradient(135deg,#f59e0b,#d97706);">C</span>
            <span class="font-bold text-sm text-slate-900 dark:text-white">Admin Alerts</span>
            <span class="ml-auto px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">ใหม่</span>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-2">
            แจ้งเตือนแอดมินผ่าน Messaging API multicast — ทดแทน LINE Notify ที่ถูกปิด
          </p>
          <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">ใช้: Channel B + Admin User IDs</div>
        </div>
      </div>

      <div class="mt-4 flex items-center gap-2 px-3 py-2.5 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-xs text-amber-800 dark:text-amber-200">
        <i class="bi bi-lightbulb-fill text-amber-500 shrink-0"></i>
        <span>
          <strong>เคล็ดลับ:</strong> Channel A + B ต้องอยู่ <strong>Provider เดียวกัน</strong> ใน LINE Developer Console เพื่อให้ <code>userId</code> ของลูกค้าตรงกัน — ระบบส่งรูปหลังจ่ายเงินจึงจะทำงานได้
        </span>
      </div>
    </div>
  </div>

  {{-- ────────── FLASH MESSAGE ────────── --}}
  @if(session('success'))
  <div class="mb-5 px-4 py-3 rounded-xl flex items-start gap-2
              bg-emerald-50 dark:bg-emerald-500/10
              border border-emerald-200 dark:border-emerald-500/30
              text-emerald-800 dark:text-emerald-300">
    <i class="bi bi-check-circle-fill mt-0.5"></i>
    <div class="flex-1 text-sm">{{ session('success') }}</div>
    <button type="button" onclick="this.parentElement.remove()"
            class="text-emerald-500 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300">
      <i class="bi bi-x-lg text-xs"></i>
    </button>
  </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.line.update') }}" id="lineSettingsForm">
    @csrf

    {{-- ══════════════════════════════════════════════════════════════
         SECTION A — LINE LOGIN CHANNEL (OAuth)
         ══════════════════════════════════════════════════════════════ --}}
    <div class="ls-card is-login ls-anim d2 mb-5">
      <div class="ls-section-head">
        <div class="ls-badge is-login">A</div>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <h6 class="font-bold text-base text-slate-900 dark:text-white leading-tight m-0">
              <i class="bi bi-box-arrow-in-right text-emerald-500 mr-1"></i>
              LINE Login Channel
            </h6>
            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">OAuth</span>
            @if($loginConfigured)
              <span class="status-dot connected"><i class="bi bi-check-circle-fill"></i> ตั้งค่าแล้ว</span>
            @else
              <span class="status-dot unknown">ยังไม่ตั้งค่า</span>
            @endif
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mt-1">
            สำหรับลูกค้า / ช่างภาพล็อกอินด้วยบัญชี LINE — ใช้กับ
            <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-xs text-emerald-700 dark:text-emerald-300">/auth/line</code>
            และ
            <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-xs text-emerald-700 dark:text-emerald-300">/photographer/auth/line</code>
          </p>
        </div>
      </div>

      <div class="p-5 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="lineLoginChannelId">
              Login Channel ID
            </label>
            <input type="text" id="lineLoginChannelId"
                   name="line_login_channel_id"
                   value="{{ $settings['line_login_channel_id'] ?? '' }}"
                   placeholder="เช่น 1234567890"
                   class="w-full px-3.5 py-2.5 rounded-lg text-sm font-mono
                          bg-white dark:bg-slate-800
                          border border-slate-200 dark:border-white/10
                          text-slate-900 dark:text-slate-100
                          placeholder-slate-400 dark:placeholder-slate-500
                          focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 transition">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="lineLoginChannelSecret">
              Login Channel Secret
            </label>
            <div class="flex">
              <input type="password" id="lineLoginChannelSecret"
                     name="line_login_channel_secret"
                     value="{{ $settings['line_login_channel_secret'] ?? '' }}"
                     placeholder="Channel Secret ของ Login channel"
                     class="flex-1 min-w-0 px-3.5 py-2.5 rounded-l-lg text-sm font-mono
                            bg-white dark:bg-slate-800
                            border border-slate-200 dark:border-white/10
                            text-slate-900 dark:text-slate-100
                            placeholder-slate-400 dark:placeholder-slate-500
                            focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 transition">
              <button type="button"
                      onclick="togglePassword('lineLoginChannelSecret', 'eyeLoginSecret')"
                      title="แสดง/ซ่อน"
                      class="px-3 rounded-r-lg text-sm
                             bg-slate-50 dark:bg-slate-800
                             border border-l-0 border-slate-200 dark:border-white/10
                             text-slate-500 dark:text-slate-400
                             hover:text-emerald-600 dark:hover:text-emerald-300
                             hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                <i class="bi bi-eye" id="eyeLoginSecret"></i>
              </button>
            </div>
          </div>
        </div>

        {{-- Callback URLs (read-only — copy to LINE Developer Console) --}}
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            Callback URLs (ไปวางใน LINE Developer Console → Callback URL)
          </label>
          <div class="space-y-2">
            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-300 dark:border-white/10">
              <span class="text-[10px] uppercase font-bold text-slate-500 dark:text-slate-400 shrink-0">ลูกค้า</span>
              <code class="flex-1 text-[11px] md:text-[12px] font-mono break-all text-slate-700 dark:text-slate-200">{{ rtrim(config('app.url'), '/') }}/auth/line/callback</code>
            </div>
            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-300 dark:border-white/10">
              <span class="text-[10px] uppercase font-bold text-slate-500 dark:text-slate-400 shrink-0">ช่างภาพ</span>
              <code class="flex-1 text-[11px] md:text-[12px] font-mono break-all text-slate-700 dark:text-slate-200">{{ rtrim(config('app.url'), '/') }}/photographer/auth/line/callback</code>
            </div>
          </div>
          <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5">
            <i class="bi bi-info-circle mr-1"></i>
            เพิ่มทั้ง 2 URLs ใน Login Channel → LINE Login → Callback URL (รองรับหลาย URLs)
          </p>
        </div>

        {{-- Setup guide --}}
        <details class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
          <summary class="cursor-pointer px-4 py-2.5 font-semibold text-sm text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5">
            <i class="bi bi-lightbulb-fill"></i> วิธีสร้าง Login Channel
          </summary>
          <ol class="text-xs text-emerald-700 dark:text-emerald-200/80 list-decimal ml-8 mr-4 mt-1 mb-3 space-y-1 leading-relaxed">
            <li>เข้า <a href="https://developers.line.biz/console/" target="_blank" class="underline font-bold">LINE Developer Console</a> → เลือก Provider (หรือสร้างใหม่)</li>
            <li>กด <strong>Create new channel</strong> → เลือก type = <strong>"LINE Login"</strong></li>
            <li>กรอกชื่อ + region (Asia/Bangkok) → ติ๊กยอมรับ TOS</li>
            <li>เปิด tab <strong>"LINE Login"</strong> → เพิ่ม Callback URL ทั้ง 2 URLs ด้านบน</li>
            <li>เปิด tab <strong>"Basic settings"</strong> → ติ๊ก <strong>Email address permission</strong> (ขออีเมลด้วย)</li>
            <li>คัดลอก <strong>Channel ID</strong> + <strong>Channel secret</strong> มาวางในฟอร์มด้านบน</li>
          </ol>
        </details>
      </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         SECTION B — LINE MESSAGING API CHANNEL (Push + OA + Webhook)
         ══════════════════════════════════════════════════════════════ --}}
    <div class="ls-card is-message ls-anim d3 mb-5">
      <div class="ls-section-head">
        <div class="ls-badge is-message">B</div>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <h6 class="font-bold text-base text-slate-900 dark:text-white leading-tight m-0">
              <i class="bi bi-chat-dots-fill text-violet-500 mr-1"></i>
              LINE Messaging API Channel
            </h6>
            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-violet-100 dark:bg-violet-500/20 text-violet-700 dark:text-violet-300">Push</span>
            @if($messagingConfigured)
              <span class="status-dot connected"><i class="bi bi-check-circle-fill"></i> ตั้งค่าแล้ว</span>
            @else
              <span class="status-dot unknown">ยังไม่ตั้งค่า</span>
            @endif
            <span id="messagingStatus" class="status-dot unknown ml-1">ยังไม่ทดสอบ</span>
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mt-1">
            ส่งข้อความ / รูปไปยังลูกค้าผ่าน LINE Official Account
            <strong class="text-violet-700 dark:text-violet-300">— คนละ channel</strong> กับ Login channel ด้านบน
          </p>
        </div>
        <label class="tw-switch shrink-0" title="เปิด/ปิดใช้งาน Messaging API">
          <input type="checkbox" name="line_messaging_enabled" id="lineMessagingEnabled"
                 value="1" {{ ($settings['line_messaging_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
          <span class="slider"></span>
        </label>
      </div>

      <div class="p-5 space-y-4">
        {{-- Channel ID --}}
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="lineChannelId">Messaging Channel ID</label>
          <input type="text" id="lineChannelId"
                 name="line_channel_id"
                 value="{{ $settings['line_channel_id'] ?? '' }}"
                 placeholder="เช่น 9876543210 (ของ Messaging API channel)"
                 class="w-full px-3.5 py-2.5 rounded-lg text-sm font-mono
                        bg-white dark:bg-slate-800
                        border border-slate-200 dark:border-white/10
                        text-slate-900 dark:text-slate-100
                        placeholder-slate-400 dark:placeholder-slate-500
                        focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 transition">
        </div>

        {{-- Channel Secret + Access Token --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="lineChannelSecret">Messaging Channel Secret</label>
            <div class="flex">
              <input type="password" id="lineChannelSecret"
                     name="line_channel_secret"
                     value="{{ $settings['line_channel_secret'] ?? '' }}"
                     placeholder="Channel Secret (verify webhook signature)"
                     class="flex-1 min-w-0 px-3.5 py-2.5 rounded-l-lg text-sm font-mono
                            bg-white dark:bg-slate-800
                            border border-slate-200 dark:border-white/10
                            text-slate-900 dark:text-slate-100
                            placeholder-slate-400 dark:placeholder-slate-500
                            focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 transition">
              <button type="button"
                      onclick="togglePassword('lineChannelSecret', 'eyeSecret')"
                      title="แสดง/ซ่อน"
                      class="px-3 rounded-r-lg text-sm
                             bg-slate-50 dark:bg-slate-800
                             border border-l-0 border-slate-200 dark:border-white/10
                             text-slate-500 dark:text-slate-400
                             hover:text-violet-600 dark:hover:text-violet-300
                             hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                <i class="bi bi-eye" id="eyeSecret"></i>
              </button>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="lineChannelAccessToken">
              Channel Access Token <span class="text-rose-500">*</span>
            </label>
            <div class="flex">
              <input type="password" id="lineChannelAccessToken"
                     name="line_channel_access_token"
                     value="{{ $settings['line_channel_access_token'] ?? '' }}"
                     placeholder="Long-lived Access Token (ส่ง push ทุกครั้งใช้ตัวนี้)"
                     class="flex-1 min-w-0 px-3.5 py-2.5 rounded-l-lg text-sm font-mono
                            bg-white dark:bg-slate-800
                            border border-slate-200 dark:border-white/10
                            text-slate-900 dark:text-slate-100
                            placeholder-slate-400 dark:placeholder-slate-500
                            focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 transition">
              <button type="button"
                      onclick="togglePassword('lineChannelAccessToken', 'eyeToken')"
                      title="แสดง/ซ่อน"
                      class="px-3 rounded-r-lg text-sm
                             bg-slate-50 dark:bg-slate-800
                             border border-l-0 border-slate-200 dark:border-white/10
                             text-slate-500 dark:text-slate-400
                             hover:text-violet-600 dark:hover:text-violet-300
                             hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                <i class="bi bi-eye" id="eyeToken"></i>
              </button>
            </div>
          </div>
        </div>

        {{-- Webhook URL --}}
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Webhook URL (รับข้อความจากลูกค้า)</label>
          <div class="flex items-center gap-2 px-3 py-2.5 rounded-lg
                      bg-slate-50 dark:bg-slate-800/50
                      border border-dashed border-slate-300 dark:border-white/10">
            <i class="bi bi-link-45deg text-slate-400 dark:text-slate-500 shrink-0"></i>
            <code id="webhookUrlText"
                  class="flex-1 min-w-0 text-xs md:text-[13px] font-mono break-all
                         text-slate-700 dark:text-slate-200">{{ rtrim(config('app.url'), '/') }}/api/webhooks/line</code>
            <button type="button" id="copyWebhookBtn" onclick="copyWebhookUrl()"
                    class="copy-btn shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold transition
                           bg-white dark:bg-slate-700
                           border border-slate-300 dark:border-white/10
                           text-slate-600 dark:text-slate-200
                           hover:border-violet-400 dark:hover:border-violet-500/50
                           hover:text-violet-600 dark:hover:text-violet-300">
              <i class="bi bi-clipboard"></i> คัดลอก
            </button>
          </div>
          <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5">
            <i class="bi bi-arrow-right-circle mr-1"></i>
            ไปวางใน <strong class="text-slate-600 dark:text-slate-300">Messaging API channel → Webhook URL</strong> + เปิด "Use webhook"
          </p>
        </div>

        {{-- Direct test --}}
        <div class="mb-2">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            LINE User ID (สำหรับทดสอบแบบตรง)
            <span class="font-normal text-slate-400 dark:text-slate-500">— ไม่บังคับ</span>
          </label>
          <input type="text" id="lineUserIdDirect"
                 placeholder="Uxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx (32 hex)"
                 autocomplete="off" spellcheck="false"
                 class="w-full px-3 py-2 rounded-lg text-xs font-mono
                        bg-white dark:bg-slate-800
                        border border-slate-300 dark:border-white/10
                        text-slate-700 dark:text-slate-200
                        placeholder:text-slate-400 dark:placeholder:text-slate-500
                        focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
          <button type="button" id="btnTestMessaging"
                  onclick="testLineConnection('messaging')"
                  class="inline-flex items-center justify-center gap-1.5 py-2.5 rounded-lg text-sm font-semibold transition
                         bg-violet-50 dark:bg-violet-500/10
                         border border-violet-200 dark:border-violet-500/30
                         text-violet-700 dark:text-violet-300
                         hover:bg-violet-100 dark:hover:bg-violet-500/20
                         active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed">
            <i class="bi bi-send"></i> ทดสอบส่งข้อความ
          </button>
          <a href="{{ route('admin.settings.line-test') }}"
             class="inline-flex items-center justify-center gap-1.5 py-2.5 rounded-lg text-sm font-semibold transition
                    bg-emerald-50 dark:bg-emerald-500/10
                    border border-emerald-200 dark:border-emerald-500/30
                    text-emerald-700 dark:text-emerald-300
                    hover:bg-emerald-100 dark:hover:bg-emerald-500/20 no-underline">
            <i class="bi bi-clipboard-check"></i> ทดสอบส่งภาพ + Replay order
          </a>
        </div>

        {{-- Setup guide --}}
        <details class="rounded-xl bg-violet-50 dark:bg-violet-500/10 border border-violet-200 dark:border-violet-500/30">
          <summary class="cursor-pointer px-4 py-2.5 font-semibold text-sm text-violet-800 dark:text-violet-300 flex items-center gap-1.5">
            <i class="bi bi-lightbulb-fill"></i> วิธีสร้าง Messaging API Channel
          </summary>
          <ol class="text-xs text-violet-700 dark:text-violet-200/80 list-decimal ml-8 mr-4 mt-1 mb-3 space-y-1 leading-relaxed">
            <li>เข้า <a href="https://developers.line.biz/console/" target="_blank" class="underline font-bold">LINE Developer Console</a> → <strong>เลือก Provider เดียวกับ Login channel</strong></li>
            <li>กด <strong>Create new channel</strong> → เลือก type = <strong>"Messaging API"</strong></li>
            <li>กรอกชื่อ Bot + อัปโหลด icon → ติ๊ก TOS</li>
            <li>เปิด tab <strong>"Messaging API"</strong>:
              <ul class="list-disc ml-4 mt-0.5">
                <li>คลิก <strong>"Issue"</strong> ที่ Channel access token (long-lived)</li>
                <li>วาง Webhook URL ด้านบน → เปิด "Use webhook" toggle</li>
                <li>ปิด "Auto-reply messages" ถ้าจะส่งเอง</li>
              </ul>
            </li>
            <li>เปิด tab <strong>"Basic settings"</strong> → คัดลอก Channel ID + Channel secret มาวางในฟอร์ม</li>
            <li>ลูกค้าต้อง <strong>add OA เป็นเพื่อน</strong> ก่อน push ถึงทำงาน — แชร์ QR ใน "Messaging API" tab</li>
          </ol>
        </details>
      </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         SECTION C — ADMIN ALERTS (via Messaging API multicast)
         Replaces dead LINE Notify (killed 31 Mar 2025). Admins paste
         their LINE userIds; system pushes alerts via Messaging API.
         ══════════════════════════════════════════════════════════════ --}}
    <div class="ls-card is-notify ls-anim d4 mb-5">
      <div class="ls-section-head">
        <div class="ls-badge is-notify">C</div>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <h6 class="font-bold text-base text-slate-900 dark:text-white leading-tight m-0">
              <i class="bi bi-bell-fill text-amber-500 mr-1"></i>
              แจ้งเตือนแอดมิน
            </h6>
            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300">Admin Alerts</span>
            <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">via Messaging API</span>
            @if($adminAlertsReady)
              <span class="status-dot connected"><i class="bi bi-check-circle-fill"></i> พร้อมใช้</span>
            @endif
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mt-1">
            ส่งแจ้งเตือนถึงแอดมินแบบเจาะจง (multicast) — ใช้ Messaging API ในการส่ง
            <span class="text-[11px] text-slate-400 dark:text-slate-500">(LINE Notify ถูกปิด 31 มี.ค. 2025)</span>
          </p>
        </div>
      </div>

      <div class="p-5 space-y-4">
        @php
          $adminIdsRaw = (string) ($settings['line_admin_user_ids'] ?? '');
          $adminIdsCount = collect(preg_split('/[\s,;]+/', $adminIdsRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->filter(fn ($id) => preg_match('/^U[0-9a-f]{32}$/i', trim($id)))
            ->count();
        @endphp
        <div>
          <label class="flex items-center justify-between mb-1.5">
            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300" for="lineAdminUserIds">
              <i class="bi bi-people-fill mr-1 text-amber-500"></i>
              Admin LINE User IDs
            </span>
            @if($adminIdsCount > 0)
              <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">
                <i class="bi bi-check-circle-fill"></i> {{ $adminIdsCount }} รายชื่อพร้อมรับแจ้งเตือน
              </span>
            @else
              <span class="text-[10px] font-bold uppercase tracking-wide text-rose-600 dark:text-rose-400">ยังไม่ได้ตั้ง</span>
            @endif
          </label>
          <textarea id="lineAdminUserIds"
                    name="line_admin_user_ids"
                    rows="3"
                    placeholder="U1234abcd...&#10;U5678efgh...&#10;(วาง LINE User IDs — 1 บรรทัด/คน หรือคั่นด้วย comma)"
                    class="w-full px-3.5 py-2.5 rounded-lg text-sm font-mono
                           bg-white dark:bg-slate-800
                           border border-slate-200 dark:border-white/10
                           text-slate-900 dark:text-slate-100
                           placeholder-slate-400 dark:placeholder-slate-500
                           focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500
                           transition resize-y">{{ $adminIdsRaw }}</textarea>
          <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5 leading-relaxed">
            <i class="bi bi-info-circle mr-1"></i>
            แต่ละแอดมินต้อง <strong>add OA เป็นเพื่อน</strong> ก่อน — รหัสรูปแบบ <code class="text-amber-700 dark:text-amber-400">U[hex 32 ตัว]</code> ดูจาก
            <a href="{{ route('admin.settings.webhooks') }}" class="text-amber-700 dark:text-amber-400 font-medium hover:underline">Webhook log</a>
            หรือ LINE Official Account Manager
          </p>
        </div>

        <a href="{{ route('admin.settings.line-test') }}"
           class="w-full inline-flex items-center justify-center gap-1.5 py-2.5 rounded-lg text-sm font-semibold transition
                  bg-amber-50 dark:bg-amber-500/10
                  border border-amber-200 dark:border-amber-500/30
                  text-amber-700 dark:text-amber-300
                  hover:bg-amber-100 dark:hover:bg-amber-500/20 no-underline">
          <i class="bi bi-send"></i> ทดสอบส่งแจ้งเตือนแอดมิน
        </a>
      </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         ROW 2 — Full-width notification settings (Admin + User sub-cols)
         ══════════════════════════════════════════════════════════════ --}}
    <div class="ls-card is-toggles ls-anim d5 mb-5">
      <div class="ls-section-head">
        <span class="ls-badge is-toggles">D</span>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap mb-1.5">
            <h2 class="font-bold text-[17px] text-slate-900 dark:text-white leading-tight">
              การตั้งค่าการแจ้งเตือน
            </h2>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase
                         bg-cyan-100 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300 border border-cyan-200/80 dark:border-cyan-500/25">
              <i class="bi bi-toggles2"></i> Toggles
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase
                         bg-pink-100 text-pink-700 dark:bg-pink-500/15 dark:text-pink-300 border border-pink-200/80 dark:border-pink-500/25">
              <i class="bi bi-broadcast"></i> Events
            </span>
          </div>
          <p class="text-sm text-slate-600 dark:text-slate-400 mt-0.5">
            เลือกเหตุการณ์ที่ต้องการให้ระบบส่งการแจ้งเตือนผ่าน LINE — ปรับแยกฝั่ง Admin และ User ได้
          </p>
        </div>
      </div>

      <div class="p-5 md:p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

          {{-- ADMIN NOTIFICATIONS --}}
          <div class="rounded-2xl p-4 md:p-5
                      bg-slate-50/70 dark:bg-slate-800/40
                      border border-slate-200 dark:border-white/10
                      shadow-sm shadow-slate-900/5 dark:shadow-black/20">
            <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-200 dark:border-white/10">
              <span class="w-9 h-9 flex items-center justify-center rounded-xl shadow-md
                           bg-gradient-to-br from-indigo-500 to-violet-600 text-white
                           shadow-indigo-500/30">
                <i class="bi bi-person-gear"></i>
              </span>
              <div class="flex-1 min-w-0">
                <h6 class="font-bold text-[14px] text-slate-900 dark:text-white leading-tight">การแจ้งเตือนสำหรับแอดมิน</h6>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">แจ้งผ่าน Messaging API multicast — ไปยัง admin user IDs</p>
              </div>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-white/5">
              @php
                $adminToggles = [
                  ['name' => 'line_admin_notify_orders',       'id' => 'notifyOrders',       'label' => 'แจ้งเตือนออเดอร์ใหม่',   'hint' => 'มีออเดอร์ใหม่เข้ามาในระบบ',           'default' => '1'],
                  ['name' => 'line_admin_notify_payouts',      'id' => 'notifyPayouts',      'label' => 'แจ้งเตือนสลิปใหม่',       'hint' => 'มีการอัปโหลดสลิปการชำระเงิน',          'default' => '1'],
                  ['name' => 'line_admin_notify_registration', 'id' => 'notifyRegistration', 'label' => 'แจ้งเตือนสมาชิกใหม่',   'hint' => 'มีผู้ใช้ลงทะเบียนใหม่',                'default' => '1'],
                  ['name' => 'line_admin_notify_cancellation', 'id' => 'notifyCancellation', 'label' => 'แจ้งเตือนการคืนเงิน',     'hint' => 'มีคำขอคืนเงินหรือยกเลิกออเดอร์',       'default' => '1'],
                  ['name' => 'line_admin_notify_events',       'id' => 'notifyEvents',       'label' => 'แจ้งเตือนอีเวนต์ใหม่',  'hint' => 'มีการสร้างอีเวนต์ถ่ายภาพใหม่',         'default' => '1'],
                  ['name' => 'line_admin_notify_contact',      'id' => 'notifyContact',      'label' => 'แจ้งเตือนข้อความติดต่อ','hint' => 'มีข้อความจากผู้ใช้ส่งมา',              'default' => '1'],
                ];
              @endphp
              @foreach($adminToggles as $t)
                <div class="flex items-center justify-between gap-3 py-3">
                  <div class="min-w-0">
                    <div class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $t['label'] }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $t['hint'] }}</div>
                  </div>
                  <label class="tw-switch shrink-0">
                    <input type="checkbox"
                           name="{{ $t['name'] }}" id="{{ $t['id'] }}"
                           value="1"
                           {{ ($settings[$t['name']] ?? $t['default']) === '1' ? 'checked' : '' }}>
                    <span class="slider"></span>
                  </label>
                </div>
              @endforeach
            </div>
          </div>

          {{-- USER PUSH NOTIFICATIONS --}}
          <div class="rounded-2xl p-4 md:p-5
                      bg-slate-50/70 dark:bg-slate-800/40
                      border border-slate-200 dark:border-white/10
                      shadow-sm shadow-slate-900/5 dark:shadow-black/20">
            <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-200 dark:border-white/10">
              <span class="w-9 h-9 flex items-center justify-center rounded-xl shadow-md
                           bg-gradient-to-br from-cyan-500 to-sky-600 text-white
                           shadow-cyan-500/30">
                <i class="bi bi-people-fill"></i>
              </span>
              <div class="flex-1 min-w-0">
                <h6 class="font-bold text-[14px] text-slate-900 dark:text-white leading-tight">Push Message ไปยังผู้ใช้</h6>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">ผ่าน Messaging API — ผู้ใช้ต้อง add OA และผูก LINE</p>
              </div>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-white/5">
              @php
                $userToggles = [
                  ['name' => 'line_user_push_enabled',   'id' => 'userPushEnabled',   'label' => 'เปิดใช้งาน Push Message',        'hint' => 'ส่งข้อความหาผู้ใช้ผ่าน Messaging API',         'default' => '1'],
                  ['name' => 'line_user_push_download', 'id' => 'userPushDownload', 'label' => 'แจ้งเตือนลิงก์ดาวน์โหลด',        'hint' => 'ส่งลิงก์ดาวน์โหลดรูปเมื่อชำระเงินสำเร็จ',  'default' => '1'],
                  ['name' => 'line_user_push_events',   'id' => 'userPushEvents',   'label' => 'แจ้งเตือนอีเวนต์ใหม่',             'hint' => 'แจ้งผู้ใช้เมื่อมีอีเวนต์ถ่ายภาพใหม่',          'default' => '1'],
                  ['name' => 'line_user_push_payout',   'id' => 'userPushPayout',   'label' => 'แจ้งเตือนอนุมัติการโอน',            'hint' => 'แจ้งช่างภาพเมื่อยอดโอนได้รับการอนุมัติ',     'default' => '1'],
                  ['name' => 'line_webhook_log',         'id' => 'webhookLog',         'label' => 'บันทึก Webhook Log',                 'hint' => 'เก็บ log ข้อความ Webhook ที่รับเข้ามา',          'default' => '0'],
                  ['name' => 'line_webhook_auto_reply', 'id' => 'webhookAutoReply', 'label' => 'ตอบกลับอัตโนมัติ (Auto Reply)',    'hint' => 'ตอบกลับข้อความอัตโนมัติเมื่อรับ Webhook',     'default' => '1'],
                ];
              @endphp
              @foreach($userToggles as $t)
                <div class="flex items-center justify-between gap-3 py-3">
                  <div class="min-w-0">
                    <div class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $t['label'] }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $t['hint'] }}</div>
                  </div>
                  <label class="tw-switch shrink-0">
                    <input type="checkbox"
                           name="{{ $t['name'] }}" id="{{ $t['id'] }}"
                           value="1"
                           {{ ($settings[$t['name']] ?? $t['default']) === '1' ? 'checked' : '' }}>
                    <span class="slider"></span>
                  </label>
                </div>
              @endforeach
            </div>
          </div>

        </div>
      </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         FOOTER ACTIONS — premium animated save bar
         ══════════════════════════════════════════════════════════════ --}}
    <div class="ls-save-bar ls-anim d5 rounded-2xl p-1 shadow-2xl shadow-indigo-500/30 dark:shadow-violet-500/30">
      <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md rounded-[14px] px-4 md:px-6 py-4
                  flex items-center justify-between gap-3 flex-wrap">
        {{-- Left: status summary + helper --}}
        <div class="flex items-center gap-3 min-w-0">
          <span class="w-11 h-11 rounded-xl flex items-center justify-center text-white shrink-0
                       bg-gradient-to-br from-indigo-500 via-violet-500 to-fuchsia-500
                       shadow-lg shadow-indigo-500/40 text-lg">
            <i class="bi bi-cloud-arrow-up-fill"></i>
          </span>
          <div class="min-w-0">
            <div class="text-[13px] md:text-sm font-bold text-slate-900 dark:text-white leading-tight">
              พร้อมบันทึกการตั้งค่า
            </div>
            <div class="text-[11px] md:text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">
              บันทึกแยก 3 ช่อง (Login / Push / Admin Alerts) แล้วทดสอบจาก
              <a href="{{ route('admin.settings.line-test') }}" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">หน้า Test Console</a>
            </div>
          </div>
        </div>

        {{-- Right: action buttons --}}
        <div class="flex items-center gap-2 flex-wrap">
          <a href="{{ route('admin.settings.webhooks') }}"
             class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-lg text-sm font-medium transition
                    bg-white dark:bg-slate-800
                    border border-slate-200 dark:border-white/10
                    text-slate-700 dark:text-slate-300
                    hover:bg-slate-50 dark:hover:bg-slate-700
                    hover:border-slate-300 dark:hover:border-white/20">
            <i class="bi bi-activity"></i>
            <span class="hidden sm:inline">Webhook Monitor</span>
          </a>
          <a href="{{ route('admin.settings.line-test') }}"
             class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-lg text-sm font-medium transition
                    bg-emerald-50 dark:bg-emerald-500/10
                    border border-emerald-200 dark:border-emerald-500/30
                    text-emerald-700 dark:text-emerald-300
                    hover:bg-emerald-100 dark:hover:bg-emerald-500/20">
            <i class="bi bi-bug"></i>
            <span class="hidden sm:inline">Test Console</span>
          </a>
          <button type="submit"
                  class="inline-flex items-center gap-2 px-5 md:px-6 py-2.5 rounded-lg text-sm font-bold text-white transition
                         bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-600
                         shadow-lg shadow-indigo-500/40
                         hover:shadow-xl hover:shadow-indigo-500/50 hover:-translate-y-0.5
                         active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed
                         relative overflow-hidden">
            <i class="bi bi-floppy-fill text-base"></i>
            <span>บันทึกการตั้งค่า</span>
          </button>
        </div>
      </div>
    </div>

  </form>
</div>
@endsection

@push('scripts')
<script>
// ─── Toggle Password Visibility ───────────────────────────────────────────
function togglePassword(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  if (!input || !icon) return;
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}

// ─── Copy Webhook URL ─────────────────────────────────────────────────────
function copyWebhookUrl() {
  const url = document.getElementById('webhookUrlText').textContent.trim();
  const btn = document.getElementById('copyWebhookBtn');
  const reset = () => {
    btn.classList.remove('copied');
    btn.innerHTML = '<i class="bi bi-clipboard"></i> คัดลอก';
  };
  const success = () => {
    btn.classList.add('copied');
    btn.innerHTML = '<i class="bi bi-check2"></i> คัดลอกแล้ว';
    setTimeout(reset, 2000);
  };
  navigator.clipboard.writeText(url).then(success).catch(() => {
    // fallback
    const el = document.createElement('textarea');
    el.value = url;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    success();
  });
}

// ─── Test LINE Connection ─────────────────────────────────────────────────
// Only "messaging" action is supported now — Notify was removed (LINE killed it
// 31 Mar 2025). Admin alerts are tested via the Test Console (separate page).
function testLineConnection(action) {
  const btn      = document.getElementById('btnTestMessaging');
  const statusEl = document.getElementById('messagingStatus');
  if (!btn || !statusEl) return;

  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="tw-spinner"></span>กำลังทดสอบ...';
  statusEl.className = 'status-dot unknown';
  statusEl.textContent = 'กำลังทดสอบ...';

  // Optionally include a raw LINE User ID so the backend can push directly
  // without requiring a linked user account.
  const payload = { action: 'messaging' };
  const directInput = document.getElementById('lineUserIdDirect');
  const lineUserId  = directInput ? directInput.value.trim() : '';
  if (lineUserId !== '') {
    payload.line_user_id = lineUserId;
  }

  fetch('{{ route("admin.api.admin.line-test") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
    },
    body: JSON.stringify(payload),
  })
  .then(r => r.json())
  .then(data => {
    const ok = data.success === true;
    statusEl.className = 'status-dot ' + (ok ? 'connected' : 'disconnected');
    statusEl.textContent = ok ? 'เชื่อมต่อสำเร็จ' : 'เชื่อมต่อไม่ได้';

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        toast: true, position: 'top-end',
        icon: ok ? 'success' : 'error',
        title: ok ? (data.message || 'ทดสอบสำเร็จ!') : (data.message || 'ทดสอบล้มเหลว'),
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
      });
    }
  })
  .catch(() => {
    statusEl.className = 'status-dot disconnected';
    statusEl.textContent = 'เกิดข้อผิดพลาด';
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        toast: true, position: 'top-end',
        icon: 'error',
        title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ',
        showConfirmButton: false,
        timer: 3000,
      });
    }
  })
  .finally(() => {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
  });
}

// ─── Form Submit: inline loading on save button ───────────────────────────
document.getElementById('lineSettingsForm').addEventListener('submit', function() {
  const btn = this.querySelector('[type=submit]');
  if (!btn) return;
  const original = btn.innerHTML;
  btn.innerHTML = '<span class="tw-spinner"></span>กำลังบันทึก...';
  btn.disabled = true;
  // Re-enable after 8s in case the page doesn't redirect (server error).
  setTimeout(() => {
    btn.innerHTML = original;
    btn.disabled = false;
  }, 8000);
});
</script>
@endpush
