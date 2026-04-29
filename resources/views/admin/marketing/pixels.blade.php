@extends('layouts.admin')

@section('title', 'Pixels & Analytics')

@push('styles')
<style>
  /* Brand colors for platform groups */
  .platform-meta    { --brand: #1877F2; --brand-2: #0866FF; --brand-bg: rgba(24,119,242,.08); }
  .platform-google  { --brand: #4285F4; --brand-2: #EA4335; --brand-bg: rgba(66,133,244,.08); }
  .platform-line    { --brand: #06C755; --brand-2: #00B04F; --brand-bg: rgba(6,199,85,.08); }
  .platform-tiktok  { --brand: #FE2C55; --brand-2: #25F4EE; --brand-bg: rgba(254,44,85,.08); }

  .pixel-card {
    transition: transform .2s cubic-bezier(.34,1.56,.64,1), box-shadow .25s, border-color .25s;
  }
  .pixel-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 28px -10px rgba(0,0,0,.18);
  }
  .pixel-card.is-on { border-color: var(--brand); }
  .pixel-card.is-on .pixel-icon-bg {
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
    color: white;
    box-shadow: 0 6px 18px -4px rgba(0,0,0,.25);
  }

  .toggle-switch {
    position: relative; width: 44px; height: 24px;
    border-radius: 9999px;
    transition: background .25s ease;
    cursor: pointer;
  }
  .toggle-switch::after {
    content: ''; position: absolute;
    top: 3px; left: 3px;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: white;
    box-shadow: 0 2px 6px rgba(0,0,0,.2);
    transition: transform .25s cubic-bezier(.34,1.56,.64,1);
  }
  .toggle-switch.is-on::after { transform: translateX(20px); }

  /* Platform group section header */
  .platform-section {
    background: linear-gradient(90deg, var(--brand-bg) 0%, transparent 100%);
    border-left: 3px solid var(--brand);
  }

  /* Pending changes pulse */
  @keyframes pending-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,.4); }
    50%      { box-shadow: 0 0 0 6px transparent; }
  }
  .has-changes-pulse { animation: pending-glow 1.8s ease-in-out infinite; }

  /* Mesh gradient hero */
  .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(24,119,242,.12) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(66,133,244,.08) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(254,44,85,.10) 0px, transparent 50%);
  }
  .dark .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(24,119,242,.18) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(66,133,244,.14) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(254,44,85,.16) 0px, transparent 50%);
  }
</style>
@endpush

@section('content')
<div x-data="pixelsForm({{ json_encode([
        'fb_pixel_enabled'           => (bool) ($status['features']['fb_pixel']['enabled'] ?? false),
        'fb_conversions_api_enabled' => (bool) ($status['features']['fb_capi']['enabled'] ?? false),
        'ga4_enabled'                => (bool) ($status['features']['ga4']['enabled'] ?? false),
        'gtm_enabled'                => (bool) ($status['features']['gtm']['enabled'] ?? false),
        'google_ads_enabled'         => (bool) ($status['features']['google_ads']['enabled'] ?? false),
        'line_tag_enabled'           => (bool) ($status['features']['line_tag']['enabled'] ?? false),
        'tiktok_pixel_enabled'       => (bool) ($status['features']['tiktok_pixel']['enabled'] ?? false),
     ]) }})"
     class="max-w-[1100px] mx-auto pb-24 space-y-5">

  {{-- ── HERO HEADER ────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-blue-100 dark:border-blue-500/15 gradient-mesh">
    <div class="relative p-6 md:p-7">
      {{-- Breadcrumb --}}
      <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-3">
        <a href="{{ route('admin.marketing.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">Marketing Hub</a>
        <i class="bi bi-chevron-right text-[0.6rem] opacity-50"></i>
        <span class="text-slate-700 dark:text-slate-200 font-medium">Pixels & Analytics</span>
      </nav>

      <div class="flex items-start justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 via-violet-500 to-pink-500 text-white flex items-center justify-center shadow-lg shrink-0">
            <i class="bi bi-activity text-2xl"></i>
          </div>
          <div class="min-w-0 flex-1">
            <h1 class="text-2xl md:text-[1.65rem] font-bold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">
              Pixels &amp; Analytics
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
              ติดตั้ง tracking pixels เพื่อวัดผล ads · retargeting · conversion ครบทุก platform
            </p>

            {{-- Live stats --}}
            <div class="flex items-center gap-2 mt-3 flex-wrap">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span x-text="enabledCount + ' / ' + totalCount + ' active'"></span>
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                <i class="bi bi-meta"></i> Meta
                <span x-text="(form.fb_pixel_enabled?1:0) + (form.fb_conversions_api_enabled?1:0) + '/2'"></span>
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                <i class="bi bi-google"></i> Google
                <span x-text="(form.ga4_enabled?1:0) + (form.gtm_enabled?1:0) + (form.google_ads_enabled?1:0) + '/3'"></span>
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                <i class="bi bi-chat-heart"></i> LINE
                <span x-text="(form.line_tag_enabled?1:0) + '/1'"></span>
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                <i class="bi bi-tiktok"></i> TikTok
                <span x-text="(form.tiktok_pixel_enabled?1:0) + '/1'"></span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2 anim-in"
         x-data="{ show: true }" x-show="show">
      <i class="bi bi-check-circle-fill text-emerald-500"></i>
      <span class="flex-1">{{ session('success') }}</span>
      <button type="button" @click="show = false" class="text-emerald-600/60 hover:text-emerald-700 dark:text-emerald-400/60 dark:hover:text-emerald-300">
        <i class="bi bi-x-lg text-sm"></i>
      </button>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.marketing.pixels.update') }}" @submit="hasChanges = false" class="space-y-5">
    @csrf

    {{-- ═══ META (Facebook + Instagram) ═══ --}}
    <div class="platform-meta">
      <div class="platform-section rounded-r-xl px-4 py-2.5 mb-3">
        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <i class="bi bi-meta" style="color: var(--brand);"></i>
          Meta Pixel <span class="text-xs font-normal text-slate-500">— Facebook + Instagram Ads</span>
        </h2>
      </div>

      <div class="space-y-3">
        @include('admin.marketing.partials.pixel-card', [
          'platform' => 'meta',
          'title' => 'Meta Pixel (Browser-side)',
          'icon' => 'bi-facebook',
          'toggleName' => 'fb_pixel_enabled',
          'toggleValue' => $status['features']['fb_pixel']['enabled'] ?? false,
          'fields' => [
            ['name' => 'fb_pixel_id', 'label' => 'Pixel ID', 'placeholder' => '1234567890123456', 'value' => $settings['fb_pixel_id']],
          ],
          'help' => 'หา Pixel ID ได้จาก Meta Events Manager → Data Sources',
        ])

        @include('admin.marketing.partials.pixel-card', [
          'platform' => 'meta',
          'title' => 'Meta Conversions API (Server-side)',
          'icon' => 'bi-cloud-upload',
          'toggleName' => 'fb_conversions_api_enabled',
          'toggleValue' => $status['features']['fb_capi']['enabled'] ?? false,
          'fields' => [
            ['name' => 'fb_conversions_api_token', 'label' => 'CAPI Access Token', 'placeholder' => 'EAAxxxxxxxx...', 'value' => $settings['fb_conversions_api_token'], 'type' => 'password'],
            ['name' => 'fb_test_event_code', 'label' => 'Test Event Code (optional)', 'placeholder' => 'TEST12345', 'value' => $settings['fb_test_event_code']],
          ],
          'help' => 'Server-side events ข้าม iOS 14.5+ tracking restriction — match rate +30-50%',
          'badge' => ['label' => 'Recommended', 'color' => 'emerald'],
        ])
      </div>
    </div>

    {{-- ═══ GOOGLE ═══ --}}
    <div class="platform-google">
      <div class="platform-section rounded-r-xl px-4 py-2.5 mb-3">
        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <svg viewBox="0 0 24 24" class="w-4 h-4">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          Google Tracking <span class="text-xs font-normal text-slate-500">— Analytics + Tag Manager + Ads</span>
        </h2>
      </div>

      <div class="space-y-3">
        @include('admin.marketing.partials.pixel-card', [
          'platform' => 'google',
          'title' => 'Google Analytics 4',
          'icon' => 'bi-graph-up',
          'toggleName' => 'ga4_enabled',
          'toggleValue' => $status['features']['ga4']['enabled'] ?? false,
          'fields' => [
            ['name' => 'ga4_measurement_id', 'label' => 'Measurement ID', 'placeholder' => 'G-XXXXXXXXXX', 'value' => $settings['ga4_measurement_id']],
            ['name' => 'ga4_api_secret', 'label' => 'API Secret (server-side Measurement Protocol)', 'placeholder' => 'xxxxxxxxxxxxxxxxx', 'value' => $settings['ga4_api_secret'], 'type' => 'password'],
          ],
          'help' => 'หาได้จาก GA4 → Admin → Data Streams',
        ])

        @include('admin.marketing.partials.pixel-card', [
          'platform' => 'google',
          'title' => 'Google Tag Manager',
          'icon' => 'bi-tags',
          'toggleName' => 'gtm_enabled',
          'toggleValue' => $status['features']['gtm']['enabled'] ?? false,
          'fields' => [
            ['name' => 'gtm_container_id', 'label' => 'Container ID', 'placeholder' => 'GTM-XXXXXXX', 'value' => $settings['gtm_container_id']],
          ],
          'help' => 'ถ้าใช้ GTM แล้ว ไม่จำเป็นต้องเปิด GA4 หรือ Google Ads แยก (GTM จะ manage ให้)',
          'badge' => ['label' => 'All-in-one', 'color' => 'indigo'],
        ])

        @include('admin.marketing.partials.pixel-card', [
          'platform' => 'google',
          'title' => 'Google Ads Conversion Tracking',
          'icon' => 'bi-google',
          'toggleName' => 'google_ads_enabled',
          'toggleValue' => $status['features']['google_ads']['enabled'] ?? false,
          'fields' => [
            ['name' => 'google_ads_conversion_id', 'label' => 'Conversion ID', 'placeholder' => 'AW-123456789', 'value' => $settings['google_ads_conversion_id']],
            ['name' => 'google_ads_conversion_label', 'label' => 'Conversion Label', 'placeholder' => 'AbCdEfGhIjKl', 'value' => $settings['google_ads_conversion_label']],
          ],
          'help' => 'สำหรับ track การซื้อจาก Google Ads campaigns',
        ])
      </div>
    </div>

    {{-- ═══ LINE ═══ --}}
    <div class="platform-line">
      <div class="platform-section rounded-r-xl px-4 py-2.5 mb-3">
        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <i class="bi bi-line" style="color: var(--brand);"></i>
          LINE Tag <span class="text-xs font-normal text-slate-500">— LINE Ads Platform</span>
        </h2>
      </div>

      @include('admin.marketing.partials.pixel-card', [
        'platform' => 'line',
        'title' => 'LINE Tag (LAP)',
        'icon' => 'bi-chat-heart',
        'toggleName' => 'line_tag_enabled',
        'toggleValue' => $status['features']['line_tag']['enabled'] ?? false,
        'fields' => [
          ['name' => 'line_tag_id', 'label' => 'LINE Tag ID', 'placeholder' => 'xxxxxxxxxxxx', 'value' => $settings['line_tag_id']],
        ],
        'help' => 'สำหรับ track การซื้อจาก LINE Ads (LAP) — โอกาสเข้าถึงผู้ใช้ไทยสูง',
        'badge' => ['label' => 'ไทย', 'color' => 'amber'],
      ])
    </div>

    {{-- ═══ TIKTOK ═══ --}}
    <div class="platform-tiktok">
      <div class="platform-section rounded-r-xl px-4 py-2.5 mb-3">
        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <i class="bi bi-tiktok" style="color: var(--brand);"></i>
          TikTok Pixel <span class="text-xs font-normal text-slate-500">— TikTok Ads Manager</span>
        </h2>
      </div>

      @include('admin.marketing.partials.pixel-card', [
        'platform' => 'tiktok',
        'title' => 'TikTok Pixel',
        'icon' => 'bi-tiktok',
        'toggleName' => 'tiktok_pixel_enabled',
        'toggleValue' => $status['features']['tiktok_pixel']['enabled'] ?? false,
        'fields' => [
          ['name' => 'tiktok_pixel_id', 'label' => 'Pixel Code', 'placeholder' => 'XXXXXXXXXXXXXXXXXXXX', 'value' => $settings['tiktok_pixel_id']],
        ],
        'help' => 'สำหรับ track การซื้อจาก TikTok Ads — ผู้ชมหลัก Gen Z + Millennials',
      ])
    </div>

    {{-- ── STICKY SAVE BAR ────────────────────────────────────────── --}}
    <div class="fixed bottom-0 left-0 right-0 lg:left-[260px] lg:[.lg\:ml-\[72px\]_&]:left-[72px] z-30 transition-all"
         :class="hasChanges ? 'translate-y-0' : 'translate-y-full lg:translate-y-0'">
      <div class="bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl border-t border-slate-200/60 dark:border-white/[0.06] shadow-[0_-8px_24px_-12px_rgba(0,0,0,0.15)]">
        <div class="max-w-full px-4 lg:px-6 py-3 flex items-center justify-between gap-3 flex-wrap">
          <div class="text-xs">
            <span x-show="hasChanges" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 font-semibold has-changes-pulse">
              <i class="bi bi-exclamation-circle-fill"></i> มีการเปลี่ยนแปลง
            </span>
            <span x-show="!hasChanges" x-cloak class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">
              <i class="bi bi-check-circle"></i> ไม่มีการเปลี่ยนแปลง
            </span>
          </div>
          <div class="flex items-center gap-2">
            <a href="{{ route('admin.marketing.index') }}"
               class="px-4 py-2 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300
                      hover:bg-slate-100 dark:hover:bg-slate-700/50 transition">ยกเลิก</a>
            <button type="submit"
                    :disabled="!hasChanges"
                    :class="hasChanges
                      ? 'bg-gradient-to-br from-blue-500 via-violet-500 to-pink-500 text-white shadow-lg shadow-blue-500/30 hover:shadow-xl hover:-translate-y-0.5'
                      : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-500 cursor-not-allowed'"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold transition-all duration-200">
              <i class="bi bi-check2"></i>
              <span x-text="hasChanges ? 'บันทึกการเปลี่ยนแปลง' : 'บันทึกแล้ว'"></span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

@push('scripts')
<script>
function pixelsForm(initial) {
  return {
    form: { ...initial },
    hasChanges: false,
    get totalCount() { return Object.keys(this.form).length; },
    get enabledCount() { return Object.values(this.form).filter(v => v).length; },
    markChanged() { this.hasChanges = true; },
  };
}

// Detect any input change → mark form dirty
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form[action$="/pixels"]');
  if (!form) return;
  form.addEventListener('input', () => {
    const root = document.querySelector('[x-data*="pixelsForm"]');
    if (root && root._x_dataStack) root._x_dataStack[0].hasChanges = true;
  });
});
</script>
@endpush
@endsection
