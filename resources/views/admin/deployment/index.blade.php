{{-- Pick layout based on install state:
       • Install mode (no admin yet) → layouts.install (minimal, no sidebar)
       • Already admin (post-install) → layouts.admin (full admin chrome)
     The view body works the same in both cases. --}}
@extends($installActive && !auth('admin')->check() ? 'layouts.install' : 'layouts.admin')

@section('title', 'การตั้งค่าเซิร์ฟเวอร์ · Deployment')

{{-- =======================================================================
     ADMIN DEPLOYMENT SETTINGS
     -------------------------------------------------------------------
     Single page that wraps `.env` editing for non-technical admins:
       • Application (URL/Domain/Debug)
       • Database (MySQL/MariaDB credentials)
       • Mail (SMTP)
       • File Storage (Cloudflare R2 / AWS S3 / local)
       • System Health (PHP version, extensions, perms, live status)

     Each section has Test → Save flow so admins can verify creds without
     committing changes that could brick the live site.
     ====================================================================== --}}

@push('styles')
<style>
  /* ── Hero panel ─────────────────────────────────────────────────── */
  .dep-hero {
    position: relative;
    border-radius: 28px; overflow: hidden;
    background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 50%, #8b5cf6 100%);
    color: #fff;
    box-shadow: 0 20px 60px -20px rgba(99,102,241,0.45);
  }
  .dep-hero__pattern {
    position: absolute; inset: 0; pointer-events: none; opacity: 0.4;
    background-image:
      radial-gradient(circle at 15% 100%, rgba(255,255,255,0.18), transparent 45%),
      radial-gradient(circle at 90% 0%,  rgba(255,255,255,0.14), transparent 50%);
  }
  .dep-blob {
    position: absolute; border-radius: 50%; filter: blur(48px); pointer-events: none;
  }
  @keyframes dep-float {
    0%, 100% { transform: translate(0,0) scale(1); }
    50%      { transform: translate(30px,-20px) scale(1.05); }
  }

  /* ── Tab buttons ────────────────────────────────────────────────── */
  .dep-tabs {
    display: flex; gap: 0.25rem; padding: 0.35rem;
    background: rgb(241 245 249);
    border-radius: 14px; flex-wrap: wrap;
  }
  .dark .dep-tabs { background: rgba(255,255,255,0.04); }
  .dep-tab {
    flex: 1; min-width: 120px;
    padding: 0.65rem 0.85rem;
    border-radius: 10px;
    font-size: 0.8rem; font-weight: 600;
    color: rgb(71 85 105);
    transition: all .2s ease;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
  }
  .dark .dep-tab { color: rgb(148 163 184); }
  .dep-tab:hover { color: rgb(15 23 42); background: rgba(255,255,255,0.5); }
  .dark .dep-tab:hover { color: rgb(241 245 249); background: rgba(255,255,255,0.06); }
  .dep-tab.is-active {
    background: white; color: rgb(99 102 241);
    box-shadow: 0 2px 8px rgba(15,23,42,0.08);
  }
  .dark .dep-tab.is-active {
    background: rgb(30 41 59); color: rgb(165 180 252);
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
  }
  .dep-tab-dot {
    width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0;
  }
  .dep-tab-dot.ok    { background: rgb(16 185 129); }
  .dep-tab-dot.warn  { background: rgb(245 158 11); }
  .dep-tab-dot.fail  { background: rgb(239 68 68); }

  /* ── Card ───────────────────────────────────────────────────────── */
  .dep-card {
    position: relative;
    background: white;
    border: 1px solid rgb(226 232 240);
    border-radius: 22px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(15,23,42,0.04), 0 12px 28px rgba(15,23,42,0.05);
  }
  .dark .dep-card {
    background: rgb(15 23 42);
    border-color: rgba(255,255,255,0.08);
    box-shadow: 0 1px 3px rgba(0,0,0,0.4), 0 16px 36px rgba(0,0,0,0.25);
  }
  .dep-card::before {
    content: ''; position: absolute; left: 0; top: 0; right: 0; height: 3px;
    background: var(--dep-accent, linear-gradient(90deg, #6366f1, #a855f7));
  }
  .dep-card.app      { --dep-accent: linear-gradient(90deg, #06b6d4, #6366f1); }
  .dep-card.database { --dep-accent: linear-gradient(90deg, #10b981, #06b6d4); }
  .dep-card.mail     { --dep-accent: linear-gradient(90deg, #f59e0b, #ef4444); }
  .dep-card.storage  { --dep-accent: linear-gradient(90deg, #a855f7, #ec4899); }
  .dep-card.health   { --dep-accent: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899); }
  .dep-card.urls     { --dep-accent: linear-gradient(90deg, #14b8a6, #0ea5e9, #6366f1); }

  /* ── URL row (copy-able) ────────────────────────────────────────── */
  .url-row {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.75rem; border-radius: 10px;
    background: rgb(241 245 249); border: 1px solid rgb(226 232 240);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.72rem; color: rgb(30 41 59);
    transition: all .2s ease;
  }
  .dark .url-row { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); color: rgb(241 245 249); }
  .url-row:hover { border-color: rgb(165 180 252); }
  .url-row code { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .url-copy-btn {
    flex-shrink: 0;
    padding: 0.2rem 0.55rem; border-radius: 6px;
    font-size: 0.65rem; font-weight: 700;
    background: white; border: 1px solid rgb(226 232 240);
    color: rgb(71 85 105);
    transition: all .15s ease;
  }
  .dark .url-copy-btn { background: rgb(30 41 59); border-color: rgba(255,255,255,0.10); color: rgb(203 213 225); }
  .url-copy-btn:hover { background: rgb(99 102 241); color: white; border-color: rgb(99 102 241); }
  .url-copy-btn.is-copied { background: rgb(16 185 129); color: white; border-color: rgb(16 185 129); }

  /* ── Service card in URLs tab ──────────────────────────────────── */
  .svc-card {
    border-radius: 14px; border: 1px solid rgb(226 232 240);
    padding: 0.85rem 1rem;
    background: white;
    transition: border-color .2s ease, transform .2s ease;
  }
  .dark .svc-card { background: rgb(15 23 42); border-color: rgba(255,255,255,0.08); }
  .svc-card:hover { transform: translateY(-1px); }
  .svc-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: inline-flex; align-items: center; justify-content: center;
    color: white; font-size: 0.95rem;
    flex-shrink: 0;
  }

  /* ── Form input ─────────────────────────────────────────────────── */
  .dep-input, .dep-select, .dep-textarea {
    width: 100%; padding: 0.55rem 0.85rem; font-size: 0.85rem;
    border-radius: 10px; border: 1px solid rgb(226 232 240);
    background: white; color: rgb(15 23 42);
    transition: border-color .15s, box-shadow .15s;
  }
  .dark .dep-input, .dark .dep-select, .dark .dep-textarea {
    background: rgb(30 41 59); border-color: rgba(255,255,255,0.10); color: rgb(241 245 249);
  }
  .dep-input:focus, .dep-select:focus, .dep-textarea:focus {
    outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
  }
  .dep-input.is-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.8rem; }

  /* ── Button styles ──────────────────────────────────────────────── */
  .dep-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
    padding: 0.6rem 1.1rem; border-radius: 10px;
    font-size: 0.85rem; font-weight: 700; transition: all .2s ease;
    cursor: pointer; white-space: nowrap;
  }
  .dep-btn:disabled { opacity: 0.5; cursor: not-allowed; }
  .dep-btn-primary {
    color: white; background: linear-gradient(135deg, #6366f1, #8b5cf6);
    box-shadow: 0 8px 20px -4px rgba(99,102,241,0.45);
  }
  .dep-btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 12px 28px -4px rgba(99,102,241,0.55); }
  .dep-btn-secondary {
    color: rgb(71 85 105);
    background: rgb(241 245 249); border: 1px solid rgb(226 232 240);
  }
  .dark .dep-btn-secondary { color: rgb(203 213 225); background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); }
  .dep-btn-secondary:hover:not(:disabled) { background: rgb(226 232 240); }
  .dark .dep-btn-secondary:hover:not(:disabled) { background: rgba(255,255,255,0.08); }
  .dep-btn-test {
    color: rgb(5 150 105);
    background: rgb(209 250 229); border: 1px solid rgb(167 243 208);
  }
  .dark .dep-btn-test { color: rgb(110 231 183); background: rgba(16,185,129,0.10); border-color: rgba(16,185,129,0.30); }
  .dep-btn-test:hover:not(:disabled) { background: rgb(167 243 208); }
  .dark .dep-btn-test:hover:not(:disabled) { background: rgba(16,185,129,0.18); }

  /* ── Test result alert ──────────────────────────────────────────── */
  .dep-result {
    padding: 0.75rem 1rem; border-radius: 12px; font-size: 0.8rem;
    border: 1px solid;
  }
  .dep-result.ok    { background: rgb(209 250 229); color: rgb(5 95 70); border-color: rgb(167 243 208); }
  .dep-result.fail  { background: rgb(254 226 226); color: rgb(155 28 28); border-color: rgb(252 165 165); }
  .dark .dep-result.ok   { background: rgba(16,185,129,0.10); color: rgb(167 243 208); border-color: rgba(16,185,129,0.30); }
  .dark .dep-result.fail { background: rgba(239,68,68,0.10); color: rgb(252 165 165); border-color: rgba(239,68,68,0.30); }

  /* ── Health pills ───────────────────────────────────────────────── */
  .health-pill {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.35rem 0.75rem; border-radius: 9999px;
    font-size: 0.7rem; font-weight: 700; white-space: nowrap;
  }
  .health-pill.ok   { background: rgb(209 250 229); color: rgb(5 95 70); }
  .health-pill.fail { background: rgb(254 226 226); color: rgb(155 28 28); }
  .dark .health-pill.ok   { background: rgba(16,185,129,0.15); color: rgb(110 231 183); }
  .dark .health-pill.fail { background: rgba(239,68,68,0.15); color: rgb(252 165 165); }

  /* ── Animation ──────────────────────────────────────────────────── */
  @keyframes dep-fade {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .dep-anim { animation: dep-fade .4s cubic-bezier(0.34,1.56,0.64,1) both; }

  /* ── Spinner ────────────────────────────────────────────────────── */
  .dep-spin {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid currentColor; border-top-color: transparent;
    border-radius: 50%; animation: dep-rotate .7s linear infinite;
  }
  @keyframes dep-rotate { to { transform: rotate(360deg); } }
</style>
@endpush

@php
  // Compute per-section readiness for the hero progress + tab dots
  $appReady    = !empty($env['APP_URL']) && !empty($env['APP_KEY']);
  $dbReady     = ($health['database']['ok'] ?? false);
  $mailReady   = !empty($env['MAIL_HOST']) && !empty($env['MAIL_FROM_ADDRESS']);
  $storageReady = ($health['storage']['ok'] ?? false);
  $sysReady    = ($health['php']['meets_min'] ?? false)
              && ($health['permissions']['storage_writable'] ?? false)
              && ($health['permissions']['env_writable'] ?? false);
  $readyCount  = (int)$appReady + (int)$dbReady + (int)$mailReady + (int)$storageReady + (int)$sysReady;
  $readyPct    = (int) round(($readyCount / 5) * 100);
@endphp

@section('content')
<div class="max-w-7xl mx-auto pb-16"
     x-data="{ tab: '{{ request('tab', 'app') }}', testing: {}, results: {} }">

  {{-- ════════════════════════════════════════════════════════════════════
       HERO — overall readiness, quick actions
       ════════════════════════════════════════════════════════════════════ --}}
  <div class="dep-hero dep-anim mb-5 px-5 md:px-7 py-6 md:py-7 relative">
    <div class="dep-hero__pattern"></div>
    <div class="dep-blob" style="width:280px; height:280px; background:radial-gradient(circle,rgba(255,255,255,0.4),transparent 70%); top:-100px; right:-50px; animation:dep-float 18s ease-in-out infinite alternate;"></div>
    <div class="dep-blob" style="width:200px; height:200px; background:radial-gradient(circle,rgba(196,181,253,0.4),transparent 70%); bottom:-60px; left:30%; animation:dep-float 22s ease-in-out infinite alternate-reverse;"></div>

    <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6 items-center">
      <div class="lg:col-span-7 flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-xl shrink-0 shadow-lg shadow-black/20">
          <i class="bi bi-server"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-[10px] uppercase tracking-[0.25em] text-white/75 font-bold mb-1 flex items-center gap-1.5">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
            <span>Deployment Center · ตั้งค่าเซิร์ฟเวอร์</span>
          </div>
          <h1 class="font-bold text-xl md:text-2xl xl:text-3xl tracking-tight leading-tight mb-1">
            <span class="bg-gradient-to-r from-yellow-100 via-white to-emerald-100 bg-clip-text text-transparent">VPS · Domain · Database</span>
          </h1>
          <p class="text-xs md:text-sm text-white/80">
            แก้ไข <code class="bg-white/15 px-1.5 py-0.5 rounded text-[10px]">.env</code> ผ่านเว็บ — มี Test ก่อน Save + สำรองไฟล์อัตโนมัติทุกครั้ง
          </p>
        </div>
      </div>

      <div class="lg:col-span-5 flex flex-col gap-2.5 lg:items-end">
        {{-- Progress card --}}
        <div class="bg-white/15 backdrop-blur-md border border-white/25 rounded-xl p-3.5 w-full lg:max-w-sm shadow-lg shadow-black/10">
          <div class="flex items-center justify-between mb-1.5">
            <div class="text-[10px] uppercase tracking-widest text-white/75 font-bold">ความพร้อม</div>
            <div class="text-lg font-black tracking-tight">{{ $readyCount }}<span class="text-white/60 text-xs font-bold">/5</span></div>
          </div>
          <div class="h-1.5 rounded-full bg-white/20 overflow-hidden mb-2">
            <div class="h-full rounded-full bg-gradient-to-r from-emerald-200 to-yellow-200 transition-all"
                 style="width:{{ $readyPct }}%;"></div>
          </div>
          <div class="grid grid-cols-5 gap-1 text-[9px]">
            <span class="inline-flex items-center justify-center gap-0.5 {{ $appReady ? 'text-white' : 'text-white/45' }}"><i class="bi bi-{{ $appReady ? 'check-circle-fill' : 'circle' }}"></i>App</span>
            <span class="inline-flex items-center justify-center gap-0.5 {{ $dbReady ? 'text-white' : 'text-white/45' }}"><i class="bi bi-{{ $dbReady ? 'check-circle-fill' : 'circle' }}"></i>DB</span>
            <span class="inline-flex items-center justify-center gap-0.5 {{ $mailReady ? 'text-white' : 'text-white/45' }}"><i class="bi bi-{{ $mailReady ? 'check-circle-fill' : 'circle' }}"></i>Mail</span>
            <span class="inline-flex items-center justify-center gap-0.5 {{ $storageReady ? 'text-white' : 'text-white/45' }}"><i class="bi bi-{{ $storageReady ? 'check-circle-fill' : 'circle' }}"></i>Storage</span>
            <span class="inline-flex items-center justify-center gap-0.5 {{ $sysReady ? 'text-white' : 'text-white/45' }}"><i class="bi bi-{{ $sysReady ? 'check-circle-fill' : 'circle' }}"></i>Sys</span>
          </div>
        </div>

        {{-- Quick actions --}}
        <div class="flex items-center gap-2 flex-wrap">
          <a href="{{ route('admin.deployment.guide') }}"
             class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white text-indigo-700 hover:bg-indigo-50 transition no-underline shadow-md">
            <i class="bi bi-book-half"></i> คู่มือ Deployment
          </a>
          <form action="{{ route('admin.deployment.backup') }}" method="POST">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white/15 hover:bg-white/25 backdrop-blur-md border border-white/30 text-white transition">
              <i class="bi bi-shield-fill-check"></i> สำรอง .env
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       INSTALL MODE WIZARD — shown only during fresh install
       ════════════════════════════════════════════════════════════════════ --}}
  @if($installActive)
    <div class="dep-anim mb-5 rounded-2xl overflow-hidden border-2 border-amber-400 dark:border-amber-500/50
                bg-gradient-to-br from-amber-50 to-rose-50 dark:from-amber-500/15 dark:to-rose-500/10
                shadow-lg shadow-amber-500/20">
      {{-- Header --}}
      <div class="px-5 py-4 bg-gradient-to-r from-amber-500 to-rose-500 text-white flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-lg">
          <i class="bi bi-tools"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-[10px] uppercase tracking-[0.25em] text-white/80 font-bold">Install Mode</div>
          <div class="font-bold text-base">โหมดติดตั้งครั้งแรก — ทำตามขั้นตอนด้านล่างเพื่อเปิดใช้งาน</div>
        </div>
        <span class="text-[10px] uppercase tracking-wider font-black bg-white/20 backdrop-blur-md px-2.5 py-1 rounded-full">
          ไม่ต้อง Login
        </span>
      </div>

      {{-- Stage progress + reason --}}
      <div class="p-5">
        @if($installReason)
          <div class="mb-4 text-sm text-amber-900 dark:text-amber-100 flex items-start gap-2">
            <i class="bi bi-info-circle-fill mt-0.5 shrink-0"></i>
            <span>{{ $installReason }}</span>
          </div>
        @endif

        {{-- 4 stages: no_key → no_db → no_migrations → no_admin → installed --}}
        @php
          $stages = [
            'no_key'        => ['1', 'APP_KEY', 'bi-key-fill'],
            'no_db'         => ['2', 'Database', 'bi-database-fill'],
            'no_migrations' => ['3', 'Migrations', 'bi-arrow-repeat'],
            'no_admin'      => ['4', 'Admin User', 'bi-person-fill-gear'],
          ];
          $currentIdx = array_search($installStage, array_keys($stages), true);
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 mb-5">
          @foreach($stages as $key => [$num, $label, $icon])
            @php
              $idx = array_search($key, array_keys($stages), true);
              $state = $currentIdx === false ? 'done' : ($idx < $currentIdx ? 'done' : ($idx === $currentIdx ? 'current' : 'pending'));
            @endphp
            <div class="rounded-xl p-2.5 flex items-center gap-2.5 border-2
                        {{ $state === 'done'    ? 'border-emerald-300 bg-emerald-50 dark:bg-emerald-500/15 dark:border-emerald-500/40' : '' }}
                        {{ $state === 'current' ? 'border-amber-400 bg-amber-100 dark:bg-amber-500/15 dark:border-amber-500/50 shadow-md' : '' }}
                        {{ $state === 'pending' ? 'border-slate-200 dark:border-white/10 bg-white/40 dark:bg-white/5' : '' }}">
              <span class="w-7 h-7 rounded-full flex items-center justify-center text-white font-bold text-xs shrink-0
                           {{ $state === 'done' ? 'bg-emerald-500' : ($state === 'current' ? 'bg-amber-500' : 'bg-slate-300 dark:bg-slate-600') }}">
                @if($state === 'done')
                  <i class="bi bi-check-lg"></i>
                @else
                  {{ $num }}
                @endif
              </span>
              <div class="min-w-0">
                <div class="text-[10px] uppercase tracking-wider font-bold {{ $state === 'pending' ? 'text-slate-400' : 'text-slate-700 dark:text-slate-200' }}">{{ $label }}</div>
                <div class="text-[9px] text-slate-500 dark:text-slate-400">
                  {{ $state === 'done' ? '✓ เสร็จ' : ($state === 'current' ? '← ทำตอนนี้' : 'รอ') }}
                </div>
              </div>
            </div>
          @endforeach
        </div>

        {{-- Stage-specific action card --}}
        @if($installStage === 'no_key')
          <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-4">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-1">Step 1: สร้าง APP_KEY</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 mb-3">Laravel ใช้ key นี้เข้ารหัส session/cookie — ต้องมีก่อนอย่างอื่น</p>
            <form action="{{ route('admin.deployment.install.key') }}" method="POST">
              @csrf
              <button type="submit" class="dep-btn dep-btn-primary">
                <i class="bi bi-key-fill"></i> Generate APP_KEY
              </button>
            </form>
          </div>

        @elseif($installStage === 'no_db')
          <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-4">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-1">Step 2: ตั้งค่า Database</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 mb-3">
              ไปที่ Tab <strong>Database</strong> ด้านล่าง — ใส่ host/port/username/password →
              <button type="button" @click="tab='database'" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">[ทดสอบ]</button>
              → [บันทึก Database] แล้วกลับมาหน้านี้
            </p>
            <button type="button" @click="tab='database'" class="dep-btn dep-btn-primary">
              <i class="bi bi-database"></i> ไปยัง Database Tab
            </button>
          </div>

        @elseif($installStage === 'no_migrations')
          <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-4">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-1">Step 3: รัน Migrations</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 mb-3">สร้าง tables ทั้งหมด (~120+ tables) — ใช้เวลาประมาณ 30 วินาที</p>
            <form action="{{ route('admin.deployment.install.migrate') }}" method="POST"
                  onsubmit="this.querySelector('button').innerHTML='<span class=&quot;dep-spin&quot;></span> กำลังรัน migrations...';">
              @csrf
              <button type="submit" class="dep-btn dep-btn-primary">
                <i class="bi bi-arrow-repeat"></i> รัน php artisan migrate
              </button>
            </form>
          </div>

        @elseif($installStage === 'no_admin')
          <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-4">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-1">Step 4: สร้าง Admin User คนแรก</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 mb-4">บัญชี super-admin คนแรกของระบบ — หลังสร้างแล้วระบบจะ secure อัตโนมัติ</p>
            <form action="{{ route('admin.deployment.install.admin') }}" method="POST" class="space-y-3">
              @csrf
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-200 mb-1">ชื่อ</label>
                  <input type="text" name="admin_name" required maxlength="120"
                         value="{{ old('admin_name', 'Super Admin') }}"
                         class="dep-input">
                </div>
                <div>
                  <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-200 mb-1">Email</label>
                  <input type="email" name="admin_email" required maxlength="255"
                         value="{{ old('admin_email') }}"
                         class="dep-input is-mono" placeholder="admin@your-domain.com">
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-200 mb-1">Password (≥ 8 ตัว)</label>
                  <input type="password" name="admin_password" required minlength="8" maxlength="128"
                         class="dep-input is-mono" autocomplete="new-password">
                </div>
                <div>
                  <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-200 mb-1">Password (ยืนยัน)</label>
                  <input type="password" name="admin_password_confirmation" required minlength="8" maxlength="128"
                         class="dep-input is-mono" autocomplete="new-password">
                </div>
              </div>
              @error('admin_email')   <div class="text-[11px] text-rose-600">{{ $message }}</div> @enderror
              @error('admin_password') <div class="text-[11px] text-rose-600">{{ $message }}</div> @enderror
              <div class="flex justify-end pt-1">
                <button type="submit" class="dep-btn dep-btn-primary">
                  <i class="bi bi-person-plus-fill"></i> สร้าง Admin + เข้าสู่ระบบ
                </button>
              </div>
            </form>
          </div>
        @endif

        {{-- Security note --}}
        <div class="mt-4 px-3 py-2 rounded-lg bg-amber-100/60 dark:bg-amber-500/10 border border-amber-300/50 dark:border-amber-500/30 text-[11px] text-amber-900 dark:text-amber-200 flex items-start gap-2">
          <i class="bi bi-shield-fill-exclamation mt-0.5 shrink-0"></i>
          <span>
            <strong>Security:</strong> Install mode เปิดได้เฉพาะ localhost/private network/non-production
            หรือเมื่อ login admin แล้ว — ระบบจะ <strong>auto-secure</strong> ทันทีหลังสร้าง admin คนแรก
          </span>
        </div>
      </div>
    </div>
  @endif

  {{-- Status messages --}}
  @if(session('success'))
    <div class="dep-anim mb-4 p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 flex items-start gap-2">
      <i class="bi bi-check-circle-fill text-base mt-0.5 shrink-0"></i>
      <span class="text-sm">{{ session('success') }}</span>
    </div>
  @endif
  @if(session('error'))
    <div class="dep-anim mb-4 p-3.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 flex items-start gap-2">
      <i class="bi bi-exclamation-circle-fill text-base mt-0.5 shrink-0"></i>
      <span class="text-sm">{{ session('error') }}</span>
    </div>
  @endif
  @if(!$envWritable)
    <div class="dep-anim mb-4 p-3.5 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-800 dark:text-amber-200 flex items-start gap-2">
      <i class="bi bi-shield-fill-exclamation text-base mt-0.5 shrink-0"></i>
      <div class="text-sm">
        <strong>ไฟล์ .env ไม่สามารถเขียนได้</strong> — เพิ่ม permission ก่อน:
        <code class="text-[11px] bg-amber-200/40 px-1.5 py-0.5 rounded">chmod 664 .env && chown www-data:www-data .env</code>
      </div>
    </div>
  @endif

  {{-- ════════════════════════════════════════════════════════════════════
       TABS — switch between sections
       ════════════════════════════════════════════════════════════════════ --}}
  <div class="dep-tabs mb-5">
    <button type="button" @click="tab = 'app'" :class="tab === 'app' ? 'is-active' : ''" class="dep-tab">
      <span class="dep-tab-dot {{ $appReady ? 'ok' : 'warn' }}"></span>
      <i class="bi bi-globe2"></i> Application
    </button>
    <button type="button" @click="tab = 'database'" :class="tab === 'database' ? 'is-active' : ''" class="dep-tab">
      <span class="dep-tab-dot {{ $dbReady ? 'ok' : 'fail' }}"></span>
      <i class="bi bi-database"></i> Database
    </button>
    <button type="button" @click="tab = 'mail'" :class="tab === 'mail' ? 'is-active' : ''" class="dep-tab">
      <span class="dep-tab-dot {{ $mailReady ? 'ok' : 'warn' }}"></span>
      <i class="bi bi-envelope"></i> Mail
    </button>
    <button type="button" @click="tab = 'storage'" :class="tab === 'storage' ? 'is-active' : ''" class="dep-tab">
      <span class="dep-tab-dot {{ $storageReady ? 'ok' : 'warn' }}"></span>
      <i class="bi bi-hdd"></i> Storage
    </button>
    <button type="button" @click="tab = 'urls'" :class="tab === 'urls' ? 'is-active' : ''" class="dep-tab">
      <span class="dep-tab-dot {{ $appReady ? 'ok' : 'warn' }}"></span>
      <i class="bi bi-link-45deg"></i> URLs
    </button>
    <button type="button" @click="tab = 'health'" :class="tab === 'health' ? 'is-active' : ''" class="dep-tab">
      <span class="dep-tab-dot {{ $sysReady ? 'ok' : 'fail' }}"></span>
      <i class="bi bi-heart-pulse"></i> Health
    </button>
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       TAB: APPLICATION
       ════════════════════════════════════════════════════════════════════ --}}
  <div x-show="tab === 'app'" x-transition.opacity.duration.300ms class="dep-card app dep-anim p-5 lg:p-6 mb-5">
    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-white/5">
      <span class="w-9 h-9 rounded-lg bg-gradient-to-br from-cyan-500 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/30">
        <i class="bi bi-globe2"></i>
      </span>
      <div>
        <h2 class="font-bold text-base text-slate-900 dark:text-white leading-tight">Application & Domain</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">URL · ชื่อแอป · Timezone · Debug mode</p>
      </div>
    </div>

    <form action="{{ route('admin.deployment.save') }}" method="POST" class="space-y-4">
      @csrf
      <input type="hidden" name="section" value="app">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">ชื่อแอปพลิเคชัน</label>
          <input type="text" name="app_name" value="{{ old('app_name', $env['APP_NAME'] ?? config('app.name')) }}"
                 class="dep-input" placeholder="{{ $siteName ?? config('app.name') }}">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
            <i class="bi bi-link-45deg"></i> APP_URL <span class="text-rose-500">*</span>
          </label>
          <input type="url" name="app_url" value="{{ old('app_url', $env['APP_URL'] ?? config('app.url')) }}"
                 class="dep-input is-mono" placeholder="https://your-domain.com" required>
          <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">ใส่ URL จริงรวม https:// — ใช้สำหรับ webhook + email link + LINE OA callback</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Environment</label>
          <select name="app_env" class="dep-select">
            @foreach(['local', 'staging', 'production'] as $opt)
              <option value="{{ $opt }}" {{ ($env['APP_ENV'] ?? 'local') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Timezone</label>
          <select name="app_timezone" class="dep-select">
            @foreach(['Asia/Bangkok', 'UTC', 'Asia/Tokyo', 'America/Los_Angeles'] as $tz)
              <option value="{{ $tz }}" {{ ($env['APP_TIMEZONE'] ?? 'Asia/Bangkok') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Locale</label>
          <select name="app_locale" class="dep-select">
            @foreach(['th', 'en'] as $loc)
              <option value="{{ $loc }}" {{ ($env['APP_LOCALE'] ?? 'th') === $loc ? 'selected' : '' }}>{{ $loc === 'th' ? 'ไทย' : 'English' }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <label class="flex items-center gap-2 px-3.5 py-2.5 rounded-lg bg-rose-50/60 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 cursor-pointer hover:bg-rose-100/60 dark:hover:bg-rose-500/15 transition">
        <input type="checkbox" name="app_debug" value="1"
               {{ ($env['APP_DEBUG'] ?? 'false') === 'true' ? 'checked' : '' }}
               class="w-4 h-4 text-rose-500 rounded">
        <div class="flex-1">
          <div class="text-xs font-bold text-rose-800 dark:text-rose-200 leading-tight">APP_DEBUG (เปิดเฉพาะตอน dev เท่านั้น)</div>
          <div class="text-[10px] text-rose-700 dark:text-rose-300/80">⚠️ ห้ามเปิดบน production — แสดง stack trace + ข้อมูลละเอียดให้ผู้โจมตี</div>
        </div>
      </label>

      <div class="flex justify-end gap-2 pt-2">
        <button type="submit" class="dep-btn dep-btn-primary">
          <i class="bi bi-floppy-fill"></i> บันทึก Application
        </button>
      </div>
    </form>
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       TAB: DATABASE
       ════════════════════════════════════════════════════════════════════ --}}
  <div x-show="tab === 'database'" x-transition.opacity.duration.300ms class="dep-card database dep-anim p-5 lg:p-6 mb-5"
       x-data="{
         async test() {
           this.testing = true; this.result = null;
           try {
             const r = await fetch('{{ route('admin.deployment.test.database') }}', {
               method: 'POST',
               headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
               body: JSON.stringify({
                 db_host: document.querySelector('[name=db_host]').value,
                 db_port: document.querySelector('[name=db_port]').value,
                 db_database: document.querySelector('[name=db_database]').value,
                 db_username: document.querySelector('[name=db_username]').value,
                 db_password: document.querySelector('[name=db_password]').value,
               }),
             });
             this.result = await r.json();
           } catch (e) { this.result = { ok: false, message: 'เกิดข้อผิดพลาด: ' + e.message }; }
           finally { this.testing = false; }
         },
         testing: false, result: null,
       }">
    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-white/5">
      <span class="w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-500 to-cyan-600 flex items-center justify-center text-white shadow-md shadow-emerald-500/30">
        <i class="bi bi-database"></i>
      </span>
      <div class="flex-1">
        <h2 class="font-bold text-base text-slate-900 dark:text-white leading-tight">Database Connection</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">MySQL / MariaDB — ใส่ค่าจาก hosting provider</p>
      </div>
      @if($dbReady)
        <span class="health-pill ok"><i class="bi bi-check-circle-fill"></i> Connected · {{ $health['database']['version'] ?? '?' }} · {{ $health['database']['tables'] ?? 0 }} tables</span>
      @else
        <span class="health-pill fail"><i class="bi bi-x-circle-fill"></i> ยังไม่เชื่อมต่อ</span>
      @endif
    </div>

    <form action="{{ route('admin.deployment.save') }}" method="POST" class="space-y-4">
      @csrf
      <input type="hidden" name="section" value="database">

      <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
        <div class="sm:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Driver</label>
          <select name="db_connection" class="dep-select">
            <option value="mysql" {{ ($env['DB_CONNECTION'] ?? 'mysql') === 'mysql' ? 'selected' : '' }}>mysql</option>
            <option value="mariadb" {{ ($env['DB_CONNECTION'] ?? '') === 'mariadb' ? 'selected' : '' }}>mariadb</option>
            <option value="pgsql" {{ ($env['DB_CONNECTION'] ?? '') === 'pgsql' ? 'selected' : '' }}>pgsql</option>
            <option value="sqlite" {{ ($env['DB_CONNECTION'] ?? '') === 'sqlite' ? 'selected' : '' }}>sqlite</option>
          </select>
        </div>
        <div class="sm:col-span-6">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Host <span class="text-rose-500">*</span></label>
          <input type="text" name="db_host" value="{{ old('db_host', $env['DB_HOST'] ?? '127.0.0.1') }}"
                 class="dep-input is-mono" placeholder="127.0.0.1 หรือ db.example.com">
        </div>
        <div class="sm:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Port</label>
          <input type="number" name="db_port" value="{{ old('db_port', $env['DB_PORT'] ?? '3306') }}"
                 class="dep-input is-mono" placeholder="3306">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Database <span class="text-rose-500">*</span></label>
          <input type="text" name="db_database" value="{{ old('db_database', $env['DB_DATABASE'] ?? '') }}"
                 class="dep-input is-mono" placeholder="photo_gallery">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Username <span class="text-rose-500">*</span></label>
          <input type="text" name="db_username" value="{{ old('db_username', $env['DB_USERNAME'] ?? '') }}"
                 class="dep-input is-mono" placeholder="root">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
            Password
            <span class="text-[10px] text-slate-400">(เว้นว่างไว้ = ไม่เปลี่ยน)</span>
          </label>
          <input type="password" name="db_password" autocomplete="new-password"
                 class="dep-input is-mono" placeholder="••••••••">
        </div>
      </div>

      <div x-show="result" x-cloak class="dep-result" :class="result?.ok ? 'ok' : 'fail'">
        <div class="font-bold flex items-center gap-1.5">
          <i class="bi" :class="result?.ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill'"></i>
          <span x-text="result?.message"></span>
        </div>
        <template x-if="result?.details">
          <div class="mt-1 text-[10px] opacity-80 font-mono" x-text="JSON.stringify(result.details)"></div>
        </template>
      </div>

      <div class="flex justify-between gap-2 pt-2 flex-wrap">
        <button type="button" class="dep-btn dep-btn-test" @click="test" :disabled="testing">
          <span x-show="!testing"><i class="bi bi-plug-fill"></i> ทดสอบเชื่อมต่อ</span>
          <span x-show="testing" x-cloak><span class="dep-spin"></span> กำลังทดสอบ...</span>
        </button>
        <button type="submit" class="dep-btn dep-btn-primary">
          <i class="bi bi-floppy-fill"></i> บันทึก Database
        </button>
      </div>
    </form>
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       TAB: MAIL (SMTP)
       ════════════════════════════════════════════════════════════════════ --}}
  <div x-show="tab === 'mail'" x-transition.opacity.duration.300ms class="dep-card mail dep-anim p-5 lg:p-6 mb-5"
       x-data="{
         async test() {
           if (!this.toAddr) { alert('กรอกอีเมลปลายทางก่อน'); return; }
           this.testing = true; this.result = null;
           try {
             const r = await fetch('{{ route('admin.deployment.test.mail') }}', {
               method: 'POST',
               headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
               body: JSON.stringify({
                 to: this.toAddr,
                 host: document.querySelector('[name=mail_host]').value,
                 port: document.querySelector('[name=mail_port]').value,
                 username: document.querySelector('[name=mail_username]').value,
                 password: document.querySelector('[name=mail_password]').value,
                 encryption: document.querySelector('[name=mail_encryption]').value,
                 from_addr: document.querySelector('[name=mail_from_address]').value,
                 from_name: document.querySelector('[name=mail_from_name]').value,
               }),
             });
             this.result = await r.json();
           } catch (e) { this.result = { ok: false, message: 'เกิดข้อผิดพลาด: ' + e.message }; }
           finally { this.testing = false; }
         },
         testing: false, result: null, toAddr: '{{ auth()->user()?->email ?? '' }}',
       }">
    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-white/5">
      <span class="w-9 h-9 rounded-lg bg-gradient-to-br from-amber-500 to-rose-500 flex items-center justify-center text-white shadow-md shadow-amber-500/30">
        <i class="bi bi-envelope-fill"></i>
      </span>
      <div class="flex-1">
        <h2 class="font-bold text-base text-slate-900 dark:text-white leading-tight">Mail (SMTP)</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">ตั้งค่า SMTP — ใช้สำหรับ password reset, invoices, แจ้งเตือนทางอีเมล</p>
      </div>
    </div>

    <form action="{{ route('admin.deployment.save') }}" method="POST" class="space-y-4">
      @csrf
      <input type="hidden" name="section" value="mail">

      <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
        <div class="sm:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Mailer</label>
          <select name="mail_mailer" class="dep-select">
            @foreach(['smtp', 'log', 'sendmail', 'mailgun', 'ses'] as $m)
              <option value="{{ $m }}" {{ ($env['MAIL_MAILER'] ?? 'smtp') === $m ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
          </select>
        </div>
        <div class="sm:col-span-6">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">SMTP Host</label>
          <input type="text" name="mail_host" value="{{ old('mail_host', $env['MAIL_HOST'] ?? '') }}"
                 class="dep-input is-mono" placeholder="smtp.gmail.com">
        </div>
        <div class="sm:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Port</label>
          <input type="number" name="mail_port" value="{{ old('mail_port', $env['MAIL_PORT'] ?? '587') }}"
                 class="dep-input is-mono" placeholder="587">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Username</label>
          <input type="text" name="mail_username" value="{{ old('mail_username', $env['MAIL_USERNAME'] ?? '') }}"
                 class="dep-input is-mono" placeholder="user@example.com">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
            Password <span class="text-[10px] text-slate-400">(เว้นว่าง = ไม่เปลี่ยน)</span>
          </label>
          <input type="password" name="mail_password" autocomplete="new-password"
                 class="dep-input is-mono" placeholder="••••••••">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Encryption</label>
          <select name="mail_encryption" class="dep-select">
            <option value="tls" {{ ($env['MAIL_ENCRYPTION'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS (port 587)</option>
            <option value="ssl" {{ ($env['MAIL_ENCRYPTION'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL (port 465)</option>
            <option value="null" {{ empty($env['MAIL_ENCRYPTION']) ? 'selected' : '' }}>None</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">From Address</label>
          <input type="email" name="mail_from_address" value="{{ old('mail_from_address', $env['MAIL_FROM_ADDRESS'] ?? '') }}"
                 class="dep-input" placeholder="no-reply@your-domain.com">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">From Name</label>
          <input type="text" name="mail_from_name" value="{{ old('mail_from_name', $env['MAIL_FROM_NAME'] ?? config('app.name')) }}"
                 class="dep-input" placeholder="{{ $siteName ?? config('app.name') }}">
        </div>
      </div>

      <div class="rounded-lg p-3 bg-amber-50/60 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30">
        <label class="text-xs font-bold text-amber-800 dark:text-amber-200 flex items-center gap-1 mb-1">
          <i class="bi bi-send-check"></i> ทดสอบส่งอีเมลไปที่:
        </label>
        <input type="email" x-model="toAddr"
               class="dep-input is-mono" placeholder="your-email@example.com">
      </div>

      <div x-show="result" x-cloak class="dep-result" :class="result?.ok ? 'ok' : 'fail'">
        <div class="font-bold flex items-center gap-1.5">
          <i class="bi" :class="result?.ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill'"></i>
          <span x-text="result?.message"></span>
        </div>
      </div>

      <div class="flex justify-between gap-2 pt-2 flex-wrap">
        <button type="button" class="dep-btn dep-btn-test" @click="test" :disabled="testing">
          <span x-show="!testing"><i class="bi bi-send-fill"></i> ส่งอีเมลทดสอบ</span>
          <span x-show="testing" x-cloak><span class="dep-spin"></span> กำลังส่ง...</span>
        </button>
        <button type="submit" class="dep-btn dep-btn-primary">
          <i class="bi bi-floppy-fill"></i> บันทึก Mail
        </button>
      </div>
    </form>
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       TAB: STORAGE
       ════════════════════════════════════════════════════════════════════ --}}
  <div x-show="tab === 'storage'" x-transition.opacity.duration.300ms class="dep-card storage dep-anim p-5 lg:p-6 mb-5"
       x-data="{
         disk: '{{ $env['FILESYSTEM_DISK'] ?? 'local' }}',
         async test() {
           this.testing = true; this.result = null;
           try {
             const r = await fetch('{{ route('admin.deployment.test.storage') }}', {
               method: 'POST',
               headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
               body: JSON.stringify({ disk: this.disk }),
             });
             this.result = await r.json();
           } catch (e) { this.result = { ok: false, message: 'เกิดข้อผิดพลาด: ' + e.message }; }
           finally { this.testing = false; }
         },
         applyR2Preset() {
           document.querySelector('[name=aws_default_region]').value = 'auto';
           document.querySelector('[name=aws_use_path_style_endpoint]').checked = true;
           alert('R2 preset applied — กรอก AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET, AWS_ENDPOINT (รูปแบบ: https://<account-id>.r2.cloudflarestorage.com)');
         },
         testing: false, result: null,
       }">
    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-white/5">
      <span class="w-9 h-9 rounded-lg bg-gradient-to-br from-violet-500 to-pink-500 flex items-center justify-center text-white shadow-md shadow-violet-500/30">
        <i class="bi bi-hdd-fill"></i>
      </span>
      <div class="flex-1">
        <h2 class="font-bold text-base text-slate-900 dark:text-white leading-tight">File Storage</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Cloudflare R2 / AWS S3 / Local — ที่เก็บรูปและไฟล์ที่อัปโหลด</p>
      </div>
      @if($storageReady)
        <span class="health-pill ok"><i class="bi bi-check-circle-fill"></i> {{ $health['storage']['disk'] ?? '?' }} OK</span>
      @endif
    </div>

    <form action="{{ route('admin.deployment.save') }}" method="POST" class="space-y-4">
      @csrf
      <input type="hidden" name="section" value="storage">

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        @php
          $currentDisk = $env['FILESYSTEM_DISK'] ?? 'local';
        @endphp
        <label class="cursor-pointer">
          <input type="radio" name="filesystem_disk" value="local" {{ $currentDisk === 'local' ? 'checked' : '' }} class="peer sr-only" @change="disk='local'">
          <div class="rounded-xl border-2 border-slate-200 dark:border-white/10 peer-checked:border-violet-500 peer-checked:bg-violet-50/60 dark:peer-checked:bg-violet-500/10 p-3 text-center transition">
            <i class="bi bi-folder-fill text-2xl text-slate-500"></i>
            <div class="text-xs font-bold text-slate-800 dark:text-white mt-1.5">Local</div>
            <div class="text-[10px] text-slate-500">storage/app/public</div>
          </div>
        </label>
        <label class="cursor-pointer">
          <input type="radio" name="filesystem_disk" value="s3" {{ $currentDisk === 's3' ? 'checked' : '' }} class="peer sr-only" @change="disk='s3'">
          <div class="rounded-xl border-2 border-slate-200 dark:border-white/10 peer-checked:border-violet-500 peer-checked:bg-violet-50/60 dark:peer-checked:bg-violet-500/10 p-3 text-center transition">
            <i class="bi bi-cloud-fill text-2xl" style="color:#FF9900;"></i>
            <div class="text-xs font-bold text-slate-800 dark:text-white mt-1.5">AWS S3</div>
            <div class="text-[10px] text-slate-500">scalable / pay-as-you-go</div>
          </div>
        </label>
        <label class="cursor-pointer">
          <input type="radio" name="filesystem_disk" value="s3" {{ $currentDisk === 's3' ? 'checked' : '' }} class="peer sr-only" @click="applyR2Preset(); disk='s3';">
          <div class="rounded-xl border-2 border-slate-200 dark:border-white/10 peer-checked:border-violet-500 peer-checked:bg-violet-50/60 dark:peer-checked:bg-violet-500/10 p-3 text-center transition">
            <i class="bi bi-cloud-fill text-2xl" style="color:#F38020;"></i>
            <div class="text-xs font-bold text-slate-800 dark:text-white mt-1.5">Cloudflare R2</div>
            <div class="text-[10px] text-slate-500">ถูกกว่า S3 · ฟรี egress</div>
          </div>
        </label>
      </div>

      <div x-show="disk === 's3'" x-transition class="space-y-3 pt-2 border-t border-slate-100 dark:border-white/5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Access Key ID</label>
            <input type="text" name="aws_access_key_id" value="{{ old('aws_access_key_id', $env['AWS_ACCESS_KEY_ID'] ?? '') }}"
                   class="dep-input is-mono" placeholder="AKIAIOSFODNN7EXAMPLE">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
              Secret Access Key <span class="text-[10px] text-slate-400">(เว้นว่าง = ไม่เปลี่ยน)</span>
            </label>
            <input type="password" name="aws_secret_access_key" autocomplete="new-password"
                   class="dep-input is-mono" placeholder="••••••••">
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Region</label>
            <input type="text" name="aws_default_region" value="{{ old('aws_default_region', $env['AWS_DEFAULT_REGION'] ?? 'auto') }}"
                   class="dep-input is-mono" placeholder="ap-southeast-1 / auto">
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Bucket Name</label>
            <input type="text" name="aws_bucket" value="{{ old('aws_bucket', $env['AWS_BUCKET'] ?? '') }}"
                   class="dep-input is-mono" placeholder="my-photo-gallery">
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Endpoint URL <span class="text-[10px] text-slate-400">(เฉพาะ R2 / non-AWS)</span></label>
          <input type="url" name="aws_endpoint" value="{{ old('aws_endpoint', $env['AWS_ENDPOINT'] ?? '') }}"
                 class="dep-input is-mono" placeholder="https://<account-id>.r2.cloudflarestorage.com">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Public URL <span class="text-[10px] text-slate-400">(สำหรับ CDN public)</span></label>
            <input type="url" name="aws_url" value="{{ old('aws_url', $env['AWS_URL'] ?? '') }}"
                   class="dep-input is-mono" placeholder="https://cdn.your-domain.com">
          </div>
          <label class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 self-end cursor-pointer">
            <input type="checkbox" name="aws_use_path_style_endpoint" value="1"
                   {{ ($env['AWS_USE_PATH_STYLE_ENDPOINT'] ?? 'false') === 'true' ? 'checked' : '' }}
                   class="w-4 h-4">
            <span class="text-xs font-medium text-slate-700 dark:text-slate-200">Path Style Endpoint <span class="text-[10px] text-slate-400">(ต้องเปิดสำหรับ R2)</span></span>
          </label>
        </div>
      </div>

      <div x-show="result" x-cloak class="dep-result" :class="result?.ok ? 'ok' : 'fail'">
        <div class="font-bold flex items-center gap-1.5">
          <i class="bi" :class="result?.ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill'"></i>
          <span x-text="result?.message"></span>
        </div>
      </div>

      <div class="flex justify-between gap-2 pt-2 flex-wrap">
        <button type="button" class="dep-btn dep-btn-test" @click="test" :disabled="testing">
          <span x-show="!testing"><i class="bi bi-cloud-arrow-up-fill"></i> ทดสอบ Storage</span>
          <span x-show="testing" x-cloak><span class="dep-spin"></span> กำลังทดสอบ...</span>
        </button>
        <button type="submit" class="dep-btn dep-btn-primary">
          <i class="bi bi-floppy-fill"></i> บันทึก Storage
        </button>
      </div>
    </form>
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       TAB: URLs — webhook + OAuth callback URLs to register with providers
       ════════════════════════════════════════════════════════════════════ --}}
  <div x-show="tab === 'urls'" x-transition.opacity.duration.300ms class="dep-card urls dep-anim p-5 lg:p-6 mb-5"
       x-data="{
         async copy(text, evt) {
           const btn = evt.currentTarget;
           try {
             await navigator.clipboard.writeText(text);
             btn.classList.add('is-copied');
             const orig = btn.innerHTML;
             btn.innerHTML = '<i class=&quot;bi bi-check-lg&quot;></i> Copied';
             setTimeout(() => { btn.classList.remove('is-copied'); btn.innerHTML = orig; }, 1800);
           } catch (e) {
             const ta = document.createElement('textarea');
             ta.value = text; document.body.appendChild(ta); ta.select();
             document.execCommand('copy'); document.body.removeChild(ta);
             btn.innerHTML = '<i class=&quot;bi bi-check-lg&quot;></i> Copied';
             setTimeout(() => { btn.innerHTML = '<i class=&quot;bi bi-clipboard&quot;></i> Copy'; }, 1800);
           }
         }
       }">
    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-white/5">
      <span class="w-9 h-9 rounded-lg bg-gradient-to-br from-teal-500 via-sky-500 to-indigo-500 flex items-center justify-center text-white shadow-md shadow-sky-500/30">
        <i class="bi bi-link-45deg"></i>
      </span>
      <div class="flex-1">
        <h2 class="font-bold text-base text-slate-900 dark:text-white leading-tight">Webhook & OAuth Callback URLs</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">URLs ทุกอันที่ต้อง paste ใน dashboard ของผู้ให้บริการ — auto-generate จาก APP_URL</p>
      </div>
    </div>

    {{-- Important warning if APP_URL still local --}}
    @php $isLocal = str_contains($env['APP_URL'] ?? '', 'localhost') || str_contains($env['APP_URL'] ?? '', '127.0.0.1') || str_contains($env['APP_URL'] ?? '', '192.168.'); @endphp
    @if($isLocal)
      <div class="mb-5 p-3.5 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-800 dark:text-amber-200 flex items-start gap-2 text-xs">
        <i class="bi bi-exclamation-triangle-fill text-base mt-0.5 shrink-0"></i>
        <div>
          <strong>APP_URL ยังเป็น local IP</strong> — URLs ด้านล่างจะใช้กับ Production ไม่ได้ (provider ต้องการ HTTPS + public domain)
          <br>ไปแก้ APP_URL ในแท็บ Application ก่อน → URLs จะ auto-update
        </div>
      </div>
    @endif

    <div class="space-y-5">
      @foreach($urlGroups as $groupKey => $group)
        <div>
          <div class="flex items-baseline justify-between mb-2 flex-wrap gap-1">
            <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
              <i class="bi bi-collection-fill text-indigo-500"></i>
              {{ $group['title'] }}
            </h3>
            <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ count($group['items']) }} items</span>
          </div>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-3 leading-relaxed">{{ $group['description'] }}</p>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            @foreach($group['items'] as $item)
              <div class="svc-card">
                <div class="flex items-start gap-3 mb-2.5">
                  <span class="svc-icon" style="background:{{ $item['color'] }};">
                    <i class="bi {{ $item['icon'] }}"></i>
                  </span>
                  <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-slate-900 dark:text-white leading-tight">{{ $item['service'] }}</div>
                    <a href="{{ $item['register_at'] }}" target="_blank" rel="noopener"
                       class="text-[10px] text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-0.5">
                      ตั้งค่าที่ {{ $item['register_label'] }}
                      <i class="bi bi-box-arrow-up-right text-[8px]"></i>
                    </a>
                  </div>
                </div>
                <div class="text-[10px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                  {{ $item['url_label'] }}
                </div>
                <div class="url-row">
                  <code title="{{ $item['url'] }}">{{ $item['url'] }}</code>
                  <button type="button" class="url-copy-btn"
                          @click="copy('{{ $item['url'] }}', $event)">
                    <i class="bi bi-clipboard"></i> Copy
                  </button>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endforeach

      {{-- CORS info card --}}
      <div class="rounded-xl bg-gradient-to-br from-cyan-50/80 to-indigo-50/40 dark:from-cyan-500/10 dark:to-indigo-500/5
                  border border-cyan-200 dark:border-cyan-500/30 p-4">
        <div class="flex items-center gap-2 mb-2">
          <i class="bi bi-shield-fill-check text-cyan-600 dark:text-cyan-400"></i>
          <h3 class="font-bold text-sm text-slate-900 dark:text-white">CORS Configuration (สำหรับ S3 / Cloudflare R2 bucket)</h3>
        </div>
        <p class="text-[11px] text-slate-600 dark:text-slate-300 mb-3 leading-relaxed">
          ถ้า browser อัปโหลดรูปตรงไปยัง bucket (presigned upload) — ต้องตั้ง CORS บน bucket ให้รับ origin จาก domain ของคุณ
        </p>
        <div class="text-[10px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400 mb-1">
          CORS JSON (paste ใน R2/S3 bucket settings)
        </div>
        <div class="url-row" style="white-space:pre; overflow-x:auto;">
          <code id="corsJson">[{"AllowedOrigins":["{{ rtrim($env['APP_URL'] ?? config('app.url'), '/') }}"],"AllowedMethods":["GET","PUT","POST","HEAD"],"AllowedHeaders":["*"],"ExposeHeaders":["ETag"],"MaxAgeSeconds":3000}]</code>
          <button type="button" class="url-copy-btn"
                  @click='copy(document.getElementById(`corsJson`).textContent.trim(), $event)'>
            <i class="bi bi-clipboard"></i> Copy
          </button>
        </div>
      </div>

      {{-- Outbound-only services info --}}
      <div class="rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-4">
        <div class="flex items-center gap-2 mb-2">
          <i class="bi bi-arrow-up-right-circle text-emerald-500"></i>
          <h3 class="font-bold text-sm text-slate-900 dark:text-white">Outbound-only APIs (ไม่ต้องตั้ง URL)</h3>
        </div>
        <p class="text-[11px] text-slate-600 dark:text-slate-300 leading-relaxed">
          API พวกนี้ระบบเรียกออกอย่างเดียว — ไม่ต้อง register URL กลับ — แค่ใส่ credentials ใน .env หรือ <a href="{{ route('admin.settings.payment-gateways') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">หน้าตั้งค่าที่เกี่ยวข้อง</a>
        </p>
        <div class="flex flex-wrap gap-1.5 mt-2.5">
          <span class="health-pill" style="background:rgba(99,102,241,0.10); color:rgb(79 70 229);"><i class="bi bi-camera"></i> AWS Rekognition</span>
          <span class="health-pill" style="background:rgba(245,158,11,0.10); color:rgb(180 83 9);"><i class="bi bi-cloud"></i> AWS S3</span>
          <span class="health-pill" style="background:rgba(243,128,32,0.10); color:rgb(243 128 32);"><i class="bi bi-cloud"></i> Cloudflare R2</span>
          <span class="health-pill" style="background:rgba(239,68,68,0.10); color:rgb(185 28 28);"><i class="bi bi-envelope"></i> SMTP</span>
          <span class="health-pill" style="background:rgba(16,185,129,0.10); color:rgb(5 150 105);"><i class="bi bi-currency-dollar"></i> SlipOK API (verify)</span>
          <span class="health-pill" style="background:rgba(6,199,85,0.10); color:rgb(6 199 85);"><i class="bi bi-send"></i> LINE Push (outbound)</span>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       TAB: HEALTH
       ════════════════════════════════════════════════════════════════════ --}}
  <div x-show="tab === 'health'" x-transition.opacity.duration.300ms class="dep-card health dep-anim p-5 lg:p-6 mb-5">
    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-white/5">
      <span class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 via-violet-500 to-pink-500 flex items-center justify-center text-white shadow-md shadow-violet-500/30">
        <i class="bi bi-heart-pulse-fill"></i>
      </span>
      <div class="flex-1">
        <h2 class="font-bold text-base text-slate-900 dark:text-white leading-tight">System Health</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">PHP version · Extensions · Permissions · Live status</p>
      </div>
      <a href="{{ route('admin.deployment.index', ['tab' => 'health']) }}" class="dep-btn dep-btn-secondary text-xs">
        <i class="bi bi-arrow-clockwise"></i> Refresh
      </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      {{-- PHP --}}
      <div class="rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><i class="bi bi-code-slash"></i> PHP</span>
          @if($health['php']['meets_min'])
            <span class="health-pill ok">{{ $health['php']['version'] }}</span>
          @else
            <span class="health-pill fail">{{ $health['php']['version'] }} (ต้อง ≥ 8.2)</span>
          @endif
        </div>
        <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Required Extensions</div>
        <div class="flex flex-wrap gap-1.5">
          @foreach($health['extensions'] as $ext => $loaded)
            <span class="health-pill {{ $loaded ? 'ok' : 'fail' }}">
              <i class="bi {{ $loaded ? 'bi-check' : 'bi-x' }}"></i>{{ $ext }}
            </span>
          @endforeach
        </div>
      </div>

      {{-- Permissions --}}
      <div class="rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><i class="bi bi-shield-lock"></i> File Permissions</span>
        </div>
        <div class="space-y-1.5 text-xs">
          @foreach(['storage_writable' => 'storage/', 'bootstrap_writable' => 'bootstrap/cache/', 'env_writable' => '.env'] as $key => $label)
            <div class="flex items-center justify-between">
              <code class="text-[11px] text-slate-600 dark:text-slate-300">{{ $label }}</code>
              <span class="health-pill {{ $health['permissions'][$key] ? 'ok' : 'fail' }}">
                <i class="bi {{ $health['permissions'][$key] ? 'bi-check' : 'bi-x' }}"></i>
                {{ $health['permissions'][$key] ? 'writable' : 'read-only' }}
              </span>
            </div>
          @endforeach
        </div>
      </div>

      {{-- Database live --}}
      <div class="rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><i class="bi bi-database"></i> Database (live)</span>
          <span class="health-pill {{ $health['database']['ok'] ? 'ok' : 'fail' }}">
            <i class="bi {{ $health['database']['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}"></i>
            {{ $health['database']['ok'] ? 'connected' : 'down' }}
          </span>
        </div>
        @if($health['database']['ok'])
          <div class="space-y-1 text-[11px] font-mono text-slate-600 dark:text-slate-400">
            <div>driver: <span class="text-slate-900 dark:text-white">{{ $health['database']['driver'] }}</span></div>
            <div>version: <span class="text-slate-900 dark:text-white">{{ $health['database']['version'] }}</span></div>
            <div>database: <span class="text-slate-900 dark:text-white">{{ $health['database']['database'] }}</span></div>
            <div>tables: <span class="text-slate-900 dark:text-white">{{ $health['database']['tables'] }}</span></div>
          </div>
        @else
          <div class="text-[11px] text-rose-700 dark:text-rose-300">{{ $health['database']['error'] ?? 'Unknown error' }}</div>
        @endif
      </div>

      {{-- Cache + Storage live --}}
      <div class="rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><i class="bi bi-lightning"></i> Runtime</span>
        </div>
        <div class="space-y-2 text-xs">
          <div class="flex items-center justify-between">
            <span class="text-slate-600 dark:text-slate-300">Cache ({{ $health['cache']['driver'] ?? '?' }})</span>
            <span class="health-pill {{ ($health['cache']['ok'] ?? false) ? 'ok' : 'fail' }}">
              {{ ($health['cache']['ok'] ?? false) ? 'OK' : 'fail' }}
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-600 dark:text-slate-300">Storage ({{ $health['storage']['disk'] ?? '?' }})</span>
            <span class="health-pill {{ ($health['storage']['ok'] ?? false) ? 'ok' : 'fail' }}">
              {{ ($health['storage']['ok'] ?? false) ? 'OK' : 'fail' }}
            </span>
          </div>
          <div class="pt-1 border-t border-slate-200 dark:border-white/10 grid grid-cols-2 gap-1 text-[11px] font-mono">
            <span class="text-slate-500">Memory:</span> <span>{{ $health['limits']['memory_limit'] }}</span>
            <span class="text-slate-500">Upload:</span> <span>{{ $health['limits']['upload_max_filesize'] }}</span>
            <span class="text-slate-500">POST max:</span> <span>{{ $health['limits']['post_max_size'] }}</span>
            <span class="text-slate-500">Exec time:</span> <span>{{ $health['limits']['max_execution_time'] }}s</span>
          </div>
        </div>
      </div>
    </div>

    {{-- Backups list --}}
    @if(count($backups) > 0)
      <div class="mt-5 pt-4 border-t border-slate-100 dark:border-white/5">
        <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-2"><i class="bi bi-archive"></i> .env Backups (ล่าสุด {{ count($backups) }} ไฟล์)</h3>
        <div class="space-y-1.5">
          @foreach($backups as $b)
            <div class="flex items-center justify-between text-[11px] font-mono py-1.5 px-3 rounded-lg bg-slate-50 dark:bg-white/5">
              <span class="text-slate-700 dark:text-slate-300">{{ $b['name'] }}</span>
              <span class="text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::createFromTimestamp($b['mtime'])->diffForHumans() }} · {{ number_format($b['size']) }} bytes</span>
            </div>
          @endforeach
        </div>
      </div>
    @endif
  </div>

  {{-- Help footer --}}
  <div class="rounded-2xl p-4 bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 dep-anim">
    <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-2"><i class="bi bi-info-circle"></i> สิ่งที่หน้านี้ตั้งให้ไม่ได้ — ต้องทำบนเซิร์ฟเวอร์เอง</h3>
    <ul class="text-xs text-slate-600 dark:text-slate-400 space-y-1 list-disc list-inside">
      <li>ติดตั้ง <strong>PHP 8.2+, Composer, Node.js</strong> และ webserver (nginx/Apache)</li>
      <li>ตั้งค่า <strong>SSL Certificate</strong> (แนะนำ Let's Encrypt + Certbot)</li>
      <li>ชี้ <strong>DNS A record</strong> จาก domain ไปยัง VPS IP</li>
      <li>รัน <code class="bg-slate-200 dark:bg-white/10 px-1 rounded">composer install --no-dev --optimize-autoloader</code></li>
      <li>รัน <code class="bg-slate-200 dark:bg-white/10 px-1 rounded">php artisan key:generate</code> (ครั้งแรก) + <code class="bg-slate-200 dark:bg-white/10 px-1 rounded">php artisan migrate --force</code></li>
      <li>ตั้งค่า <strong>cron</strong> สำหรับ <code class="bg-slate-200 dark:bg-white/10 px-1 rounded">php artisan schedule:run</code> (ทุก 1 นาที)</li>
      <li>ตั้งค่า <strong>queue worker</strong> (supervisor) สำหรับ <code class="bg-slate-200 dark:bg-white/10 px-1 rounded">php artisan queue:work</code></li>
    </ul>
  </div>

</div>
@endsection
