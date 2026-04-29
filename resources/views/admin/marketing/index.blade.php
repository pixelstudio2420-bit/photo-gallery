@extends('layouts.admin')

@section('title', 'Marketing Hub')

{{-- =======================================================================
     MARKETING HUB — REDESIGN
     -------------------------------------------------------------------
     • Full light/dark theme support (matched with /admin/settings/line).
     • Refined visual hierarchy: hero header, accent metrics, group cards.
     • Preserves existing data contract: $status, $groups, $metrics.
     • Toggle handler (marketingHub Alpine component) is unchanged.
     • Dynamic group colors are resolved through an explicit $palette map
       so Tailwind v4's @source scanner picks up every class literally.
     ====================================================================== --}}

@php
    // Explicit palette — every group color resolves to full class strings so
    // Tailwind scans them (JIT safety). Keys must match MarketingService
    // featureGroups()['color'] values.
    $palette = [
        'blue' => [
            'icon_bg'  => 'bg-blue-500/15 text-blue-600 dark:text-blue-300',
            'accent'   => 'text-blue-600 dark:text-blue-300',
            'ring'     => 'ring-blue-500/40',
            'gradient' => 'from-blue-500 to-sky-500',
            'count_bg' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
            'toggle'   => 'bg-blue-500',
            'border_t' => 'border-t-blue-500',
        ],
        'emerald' => [
            'icon_bg'  => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300',
            'accent'   => 'text-emerald-600 dark:text-emerald-300',
            'ring'     => 'ring-emerald-500/40',
            'gradient' => 'from-emerald-500 to-teal-500',
            'count_bg' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            'toggle'   => 'bg-emerald-500',
            'border_t' => 'border-t-emerald-500',
        ],
        'green' => [
            'icon_bg'  => 'bg-green-500/15 text-green-600 dark:text-green-300',
            'accent'   => 'text-green-600 dark:text-green-300',
            'ring'     => 'ring-green-500/40',
            'gradient' => 'from-green-500 to-emerald-500',
            'count_bg' => 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300',
            'toggle'   => 'bg-green-500',
            'border_t' => 'border-t-green-500',
        ],
        'indigo' => [
            'icon_bg'  => 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-300',
            'accent'   => 'text-indigo-600 dark:text-indigo-300',
            'ring'     => 'ring-indigo-500/40',
            'gradient' => 'from-indigo-500 to-violet-500',
            'count_bg' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
            'toggle'   => 'bg-indigo-500',
            'border_t' => 'border-t-indigo-500',
        ],
        'violet' => [
            'icon_bg'  => 'bg-violet-500/15 text-violet-600 dark:text-violet-300',
            'accent'   => 'text-violet-600 dark:text-violet-300',
            'ring'     => 'ring-violet-500/40',
            'gradient' => 'from-violet-500 to-fuchsia-500',
            'count_bg' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
            'toggle'   => 'bg-violet-500',
            'border_t' => 'border-t-violet-500',
        ],
        'amber' => [
            'icon_bg'  => 'bg-amber-500/15 text-amber-600 dark:text-amber-300',
            'accent'   => 'text-amber-600 dark:text-amber-300',
            'ring'     => 'ring-amber-500/40',
            'gradient' => 'from-amber-500 to-orange-500',
            'count_bg' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            'toggle'   => 'bg-amber-500',
            'border_t' => 'border-t-amber-500',
        ],
    ];

    // Deep-link URLs for each group so the "Configure →" button always works.
    $deepLinks = [
        'tracking' => ['url' => route('admin.marketing.pixels'),        'label' => 'ตั้งค่า Pixel IDs'],
        'seo'      => ['url' => route('admin.marketing.seo'),           'label' => 'SEO / Social Settings'],
        'line'     => ['url' => route('admin.marketing.line'),          'label' => 'LINE Settings + Broadcast'],
        'email'    => ['url' => route('admin.marketing.campaigns.index'), 'label' => 'Campaigns + Subscribers'],
        'growth'   => ['url' => route('admin.marketing.referral'),      'label' => 'Referral / Loyalty / Landing'],
        'insights' => ['url' => route('admin.marketing.analytics'),     'label' => 'Marketing Analytics'],
    ];

    // Totals for the hero header summary
    $totalFeatures      = count($status['features']);
    $enabledTotal       = 0;
    $unconfiguredTotal  = 0;
    foreach ($status['features'] as $f) {
        if ($f['enabled']) $enabledTotal++;
        if (!$f['configured']) $unconfiguredTotal++;
    }

    // Metrics config — icon + accent color per metric
    $metricCards = [
        ['key' => 'subscribers',      'label' => 'Subscribers',      'icon' => 'bi-envelope-paper',    'color' => 'indigo'],
        ['key' => 'utm_visits_7d',    'label' => 'UTM Visits (7d)',  'icon' => 'bi-bullseye',          'color' => 'blue'],
        ['key' => 'campaigns_sent',   'label' => 'Campaigns Sent',   'icon' => 'bi-send-check',        'color' => 'emerald'],
        ['key' => 'referral_codes',   'label' => 'Referral Codes',   'icon' => 'bi-share',             'color' => 'violet'],
        ['key' => 'loyalty_accounts', 'label' => 'Loyalty Members',  'icon' => 'bi-award',             'color' => 'amber'],
    ];
@endphp

@push('styles')
<style>
  /* ── Tailwind-only toggle switch ───────────────────────────────── */
  .mh-toggle {
    position: relative; display: inline-block;
    width: 2.6rem; height: 1.4rem; flex-shrink: 0;
    border-radius: 9999px; cursor: pointer;
    transition: background-color .25s ease;
    background: rgb(226 232 240); /* slate-200 */
  }
  .dark .mh-toggle { background: rgb(51 65 85); /* slate-700 */ }
  .mh-toggle::before {
    content: ''; position: absolute;
    top: 3px; left: 3px;
    width: 1.1rem; height: 1.1rem;
    background: #fff;
    border-radius: 9999px;
    box-shadow: 0 1px 3px rgba(0,0,0,.25);
    transition: transform .25s ease;
  }
  .mh-toggle.on::before { transform: translateX(1.15rem); }
  .mh-toggle.on { background: linear-gradient(135deg, #10b981, #059669); }
  .mh-toggle.disabled { opacity: .4; cursor: not-allowed; }

  /* Master switch — larger variant */
  .mh-toggle-lg {
    width: 3.5rem; height: 1.85rem;
  }
  .mh-toggle-lg::before {
    width: 1.45rem; height: 1.45rem;
    top: 4px; left: 4px;
  }
  .mh-toggle-lg.on::before { transform: translateX(1.6rem); }

  /* ── Status pill ───────────────────────────────────────────────── */
  .mh-pill {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: 0.15rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.68rem; font-weight: 600;
    white-space: nowrap;
  }
  .mh-pill::before {
    content: ''; width: 6px; height: 6px; border-radius: 50%;
    background: currentColor;
  }
  .mh-pill.on {
    background: rgb(209 250 229); color: rgb(4 120 87);
  }
  .dark .mh-pill.on {
    background: rgba(16,185,129,.15); color: rgb(110 231 183);
  }
  .mh-pill.off {
    background: rgb(241 245 249); color: rgb(71 85 105);
  }
  .dark .mh-pill.off {
    background: rgba(148,163,184,.12); color: rgb(148 163 184);
  }
  .mh-pill.warn {
    background: rgb(254 243 199); color: rgb(146 64 14);
  }
  .dark .mh-pill.warn {
    background: rgba(245,158,11,.15); color: rgb(252 211 77);
  }

  /* ── Feature row ──────────────────────────────────────────────── */
  .mh-feature-row {
    display: flex; align-items: center; gap: .75rem;
    padding: 0.625rem 0.75rem;
    border-radius: 0.625rem;
    background: rgb(248 250 252); /* slate-50 */
    transition: background-color .15s ease;
  }
  .dark .mh-feature-row {
    background: rgba(15, 23, 42, .35); /* slate-900/35 */
  }
  .mh-feature-row:hover {
    background: rgb(241 245 249); /* slate-100 */
  }
  .dark .mh-feature-row:hover {
    background: rgba(15, 23, 42, .6);
  }

  /* ── Metric card hover lift ──────────────────────────────────── */
  .mh-metric { transition: transform .2s ease, box-shadow .2s ease; }
  .mh-metric:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -10px rgba(15,23,42,.15);
  }
  .dark .mh-metric:hover {
    box-shadow: 0 10px 25px -10px rgba(0,0,0,.5);
  }

  /* ── Decorative hero gradient ─────────────────────────────────── */
  .mh-hero {
    background:
      radial-gradient(1200px 400px at 0% 0%,  rgba(99,102,241,.12), transparent 60%),
      radial-gradient(1000px 400px at 100% 0%, rgba(236,72,153,.10), transparent 60%);
  }
  .dark .mh-hero {
    background:
      radial-gradient(1200px 400px at 0% 0%,  rgba(99,102,241,.18), transparent 60%),
      radial-gradient(1000px 400px at 100% 0%, rgba(236,72,153,.15), transparent 60%);
  }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16" x-data="marketingHub()">

  {{-- ══════════════════════════════════════════════════════════════
       HERO HEADER — title, summary chips, master switch
       ══════════════════════════════════════════════════════════════ --}}
  <div class="mh-hero rounded-3xl border border-slate-200 dark:border-white/10
              bg-white dark:bg-slate-900/60 backdrop-blur
              px-5 md:px-7 py-6 mb-5 relative overflow-hidden">

    <div class="flex flex-wrap items-start justify-between gap-5 relative z-10">
      {{-- Left: title + subtitle + summary pills --}}
      <div class="min-w-0 flex-1">
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3 tracking-tight">
          <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl shadow-md shadow-indigo-500/30"
                style="background:linear-gradient(135deg,#6366f1,#a855f7);">
            <i class="bi bi-megaphone-fill text-white text-xl"></i>
          </span>
          Marketing Hub
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 ml-14">
          ศูนย์รวมฟีเจอร์การตลาด — toggle เปิด/ปิดแต่ละฟีเจอร์ได้แบบ production-safe
        </p>

        {{-- Summary chips --}}
        <div class="flex flex-wrap items-center gap-2 mt-4 ml-14">
          <span class="mh-pill on">
            {{ $enabledTotal }} เปิดใช้งาน
          </span>
          <span class="mh-pill off">
            {{ $totalFeatures - $enabledTotal }} ปิดอยู่
          </span>
          @if($unconfiguredTotal > 0)
            <span class="mh-pill warn">
              <i class="bi bi-exclamation-triangle text-[9px]"></i>
              {{ $unconfiguredTotal }} ยังไม่ตั้งค่า
            </span>
          @endif
        </div>
      </div>

      {{-- Right: Master switch card --}}
      <div class="flex items-center gap-4 px-5 py-3.5 rounded-2xl border-2 transition-all
                  {{ $status['master']
                      ? 'border-emerald-300 dark:border-emerald-500/40 bg-emerald-50 dark:bg-emerald-500/10'
                      : 'border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-950/40' }}">
        <div class="flex flex-col min-w-0">
          <span class="text-[10px] uppercase tracking-[0.18em] font-bold
                      {{ $status['master'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500' }}">
            Marketing System
          </span>
          <span class="text-slate-900 dark:text-white font-bold text-lg flex items-center gap-1.5">
            @if($status['master'])
              <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              ONLINE
            @else
              <span class="inline-block w-2 h-2 rounded-full bg-slate-400"></span>
              OFFLINE
            @endif
          </span>
        </div>
        <button type="button"
                @click="toggleFeature('master', {{ $status['master'] ? 'false' : 'true' }})"
                class="mh-toggle mh-toggle-lg {{ $status['master'] ? 'on' : '' }}"
                aria-label="Toggle master switch"></button>
      </div>
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════
       FLASH MESSAGES
       ══════════════════════════════════════════════════════════════ --}}
  @if(session('success'))
    <div class="mb-5 px-4 py-3 rounded-xl flex items-start gap-2
                bg-emerald-50 dark:bg-emerald-500/10
                border border-emerald-200 dark:border-emerald-500/30
                text-emerald-800 dark:text-emerald-300">
      <i class="bi bi-check-circle-fill mt-0.5"></i>
      <div class="flex-1 text-sm">{{ session('success') }}</div>
    </div>
  @endif
  @if(session('error'))
    <div class="mb-5 px-4 py-3 rounded-xl flex items-start gap-2
                bg-rose-50 dark:bg-rose-500/10
                border border-rose-200 dark:border-rose-500/30
                text-rose-800 dark:text-rose-300">
      <i class="bi bi-exclamation-triangle-fill mt-0.5"></i>
      <div class="flex-1 text-sm">{{ session('error') }}</div>
    </div>
  @endif

  {{-- ══════════════════════════════════════════════════════════════
       MASTER-OFF WARNING BANNER
       ══════════════════════════════════════════════════════════════ --}}
  @if(!$status['master'])
    <div class="mb-6 p-4 rounded-2xl
                bg-amber-50 dark:bg-amber-500/10
                border border-amber-200 dark:border-amber-500/30">
      <div class="flex items-start gap-3">
        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl
                     bg-amber-100 dark:bg-amber-500/20 shrink-0">
          <i class="bi bi-exclamation-triangle-fill text-amber-600 dark:text-amber-300 text-lg"></i>
        </span>
        <div class="flex-1 text-sm">
          <p class="font-semibold text-amber-900 dark:text-amber-200 mb-1">Marketing System ปิดอยู่</p>
          <p class="text-amber-700 dark:text-amber-300/80 text-[13px] leading-relaxed">
            แม้คุณจะเปิด feature ใดๆ ด้านล่าง จะไม่มีผลจนกว่า master switch ด้านบนจะเปิด
            — ออกแบบเพื่อ safety ในการเตรียมค่าก่อน go-live
          </p>
        </div>
      </div>
    </div>
  @endif

  {{-- ══════════════════════════════════════════════════════════════
       QUICK METRICS — 5 stat cards with icon + accent
       ══════════════════════════════════════════════════════════════ --}}
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
    @foreach($metricCards as $m)
      @php $p = $palette[$m['color']]; @endphp
      <div class="mh-metric relative overflow-hidden rounded-2xl
                  border border-slate-200 dark:border-white/10
                  bg-white dark:bg-slate-900/60
                  shadow-sm shadow-slate-900/5 dark:shadow-black/20
                  p-4 border-t-2 {{ $p['border_t'] }}">
        <div class="flex items-center justify-between">
          <div class="text-[10px] uppercase tracking-[0.14em] font-bold text-slate-500 dark:text-slate-400 leading-tight">
            {{ $m['label'] }}
          </div>
          <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg {{ $p['icon_bg'] }}">
            <i class="bi {{ $m['icon'] }} text-sm"></i>
          </span>
        </div>
        <div class="text-2xl md:text-[26px] font-bold text-slate-900 dark:text-white mt-2 leading-none tracking-tight">
          {{ number_format($metrics[$m['key']] ?? 0) }}
        </div>
      </div>
    @endforeach
  </div>

  {{-- ══════════════════════════════════════════════════════════════
       FEATURE GROUPS — 2-col grid, each card holds a category
       ══════════════════════════════════════════════════════════════ --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    @foreach($groups as $groupKey => $group)
      @php
        $p = $palette[$group['color']] ?? $palette['indigo'];
        $enabledCount = 0;
        foreach ($group['features'] as $fKey) {
          if ($status['features'][$fKey]['enabled'] ?? false) $enabledCount++;
        }
        $totalCount = count($group['features']);
        $progress   = $totalCount > 0 ? round(($enabledCount / $totalCount) * 100) : 0;
      @endphp
      <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900/60
                  border border-slate-200 dark:border-white/10
                  shadow-sm shadow-slate-900/5 dark:shadow-black/20
                  flex flex-col">

        {{-- ── Card header: icon + label + count ──────────────── --}}
        <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
          <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl {{ $p['icon_bg'] }} shrink-0">
            <i class="bi {{ $group['icon'] }} text-lg"></i>
          </span>
          <div class="flex-1 min-w-0">
            <h2 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">
              {{ $group['label'] }}
            </h2>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
              {{ $totalCount }} ฟีเจอร์ทั้งหมด
            </div>
          </div>
          <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold {{ $p['count_bg'] }}">
            {{ $enabledCount }} / {{ $totalCount }} ON
          </span>
        </div>

        {{-- ── Progress bar ──────────────────────────────────── --}}
        <div class="h-1 w-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
          <div class="h-full bg-gradient-to-r {{ $p['gradient'] }} transition-all duration-500"
               style="width: {{ $progress }}%"></div>
        </div>

        {{-- ── Feature rows ──────────────────────────────────── --}}
        <div class="p-3 space-y-1.5 flex-1">
          @foreach($group['features'] as $feature)
            @php
              $f = $status['features'][$feature] ?? null;
              if (!$f) continue;
              $isOn      = (bool) ($f['enabled'] ?? false);
              $isConfig  = (bool) ($f['configured'] ?? false);
            @endphp
            <div class="mh-feature-row">
              <div class="flex-1 min-w-0">
                <div class="text-sm text-slate-900 dark:text-white font-medium truncate">
                  {{ $f['label'] }}
                </div>
                <div class="mt-0.5">
                  @if($isOn)
                    <span class="mh-pill on">
                      <i class="bi bi-check2 text-[10px]"></i>
                      ใช้งานอยู่
                    </span>
                  @elseif(!$isConfig)
                    <span class="mh-pill warn">
                      <i class="bi bi-exclamation-triangle text-[9px]"></i>
                      ยังไม่ได้ตั้งค่า
                    </span>
                  @else
                    <span class="mh-pill off">ปิดอยู่</span>
                  @endif
                </div>
              </div>

              {{-- Toggle --}}
              <button type="button"
                      @click="toggleFeature('{{ $feature }}', {{ $isOn ? 'false' : 'true' }})"
                      class="mh-toggle shrink-0
                             {{ $isOn ? 'on' : '' }}
                             {{ !$status['master'] ? 'disabled' : '' }}"
                      @if(!$status['master']) disabled @endif
                      aria-label="Toggle {{ $f['label'] }}"></button>
            </div>
          @endforeach
        </div>

        {{-- ── Deep-link button ──────────────────────────────── --}}
        @if(isset($deepLinks[$groupKey]))
          <a href="{{ $deepLinks[$groupKey]['url'] }}"
             class="group mx-3 mb-3 mt-1 inline-flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-xs font-semibold transition
                    bg-slate-50 dark:bg-slate-800/50
                    border border-slate-200 dark:border-white/10
                    text-slate-700 dark:text-slate-300
                    hover:bg-white dark:hover:bg-slate-800
                    hover:border-slate-300 dark:hover:border-white/20
                    hover:text-slate-900 dark:hover:text-white">
            <i class="bi bi-sliders {{ $p['accent'] }}"></i>
            {{ $deepLinks[$groupKey]['label'] }}
            <i class="bi bi-arrow-right transition-transform group-hover:translate-x-0.5"></i>
          </a>
        @endif
      </div>
    @endforeach
  </div>

  {{-- ══════════════════════════════════════════════════════════════
       INTEGRATION GUIDE — numbered rollout steps
       ══════════════════════════════════════════════════════════════ --}}
  <div class="mt-6 rounded-2xl bg-white dark:bg-slate-900/60
              border border-slate-200 dark:border-white/10
              shadow-sm shadow-slate-900/5 dark:shadow-black/20
              overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl
                   bg-amber-500/15 text-amber-600 dark:text-amber-300 shrink-0">
        <i class="bi bi-lightbulb-fill text-lg"></i>
      </span>
      <div>
        <h3 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">
          แนะนำลำดับเปิด features
        </h3>
        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
          เรียงตาม ROI จากสูงไปต่ำ — ทำตามลำดับเพื่อได้ผลตอบแทนเร็วสุด
        </div>
      </div>
    </div>
    <div class="p-4 md:p-5">
      <ol class="space-y-2.5">
        @foreach([
          ['title' => 'UTM Tracking + SEO Schema + OG Tags', 'desc' => 'เปิดก่อน (low-cost, on by default) — ติดตามทราฟฟิกและปรับแต่งการแชร์'],
          ['title' => 'Meta Pixel + GA4',                      'desc' => 'ก่อนรัน Ads ต้องมี — เก็บ event สำหรับ optimize'],
          ['title' => 'Meta Conversions API',                  'desc' => 'bypass iOS 14.5+ limitation — match rate เพิ่ม 30–50%'],
          ['title' => 'LINE Messaging',                        'desc' => 'CAC ต่ำสุดในไทย — broadcast ไปยัง followers'],
          ['title' => 'Newsletter + Referral',                 'desc' => 'retention + organic growth — ขยายฐานผู้ใช้เอง'],
          ['title' => 'Loyalty Points',                        'desc' => 'เปิดเมื่อมี repeat buyer เยอะแล้ว — ไม่งั้นจะเป็น cost เปล่า'],
        ] as $idx => $step)
          <li class="flex items-start gap-3 p-3 rounded-xl
                     hover:bg-slate-50 dark:hover:bg-slate-800/40 transition group">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg
                         bg-gradient-to-br from-indigo-500 to-violet-500
                         text-white text-xs font-bold shadow-md shadow-indigo-500/30
                         shrink-0 group-hover:scale-110 transition-transform">
              {{ $idx + 1 }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-semibold text-slate-900 dark:text-white">
                {{ $step['title'] }}
              </div>
              <div class="text-[12px] text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                {{ $step['desc'] }}
              </div>
            </div>
          </li>
        @endforeach
      </ol>
    </div>
  </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     Alpine handler — unchanged from original
     ══════════════════════════════════════════════════════════════ --}}
<script>
function marketingHub() {
    return {
        async toggleFeature(feature, enabled) {
            try {
                const resp = await fetch('{{ route('admin.marketing.toggle') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ feature, enabled }),
                });
                if (!resp.ok) throw new Error('toggle failed');
                // Reload to re-render state
                location.reload();
            } catch (e) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true, position: 'top-end',
                        icon: 'error',
                        title: 'เปลี่ยนสถานะไม่สำเร็จ',
                        text: e.message,
                        showConfirmButton: false,
                        timer: 3000,
                    });
                } else {
                    alert('เปลี่ยนสถานะไม่สำเร็จ: ' + e.message);
                }
            }
        },
    }
}
</script>
@endsection
