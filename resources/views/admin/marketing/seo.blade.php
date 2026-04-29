@extends('layouts.admin')

@section('title', 'SEO & Social')

@push('styles')
<style>
  .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(59,130,246,.14) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(99,102,241,.10) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(139,92,246,.12) 0px, transparent 50%);
  }
  .dark .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(59,130,246,.20) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(99,102,241,.16) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(139,92,246,.18) 0px, transparent 50%);
  }
  @keyframes pending-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,.4); }
    50%      { box-shadow: 0 0 0 6px transparent; }
  }
  .has-changes-pulse { animation: pending-glow 1.8s ease-in-out infinite; }
</style>
@endpush

@section('content')
@php
    $marketingSvc = app(\App\Services\Marketing\MarketingService::class);
    $schemaEnabled = \App\Models\AppSetting::get('marketing_schema_markup_enabled', '1') === '1';
    $ogAutoEnabled = \App\Models\AppSetting::get('marketing_og_auto_enabled', '1') === '1';
@endphp

<div x-data="{ hasChanges: false }" class="max-w-[1100px] mx-auto pb-24 space-y-5">

  {{-- ── HERO HEADER ────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-blue-100 dark:border-blue-500/20 gradient-mesh">
    <div class="relative p-6 md:p-7">
      <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-3">
        <a href="{{ route('admin.marketing.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">Marketing Hub</a>
        <i class="bi bi-chevron-right text-[0.6rem] opacity-50"></i>
        <span class="text-slate-700 dark:text-slate-200 font-medium">SEO & Social</span>
      </nav>

      <div class="flex items-start justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 via-indigo-500 to-violet-500 text-white flex items-center justify-center shadow-lg shrink-0">
            <i class="bi bi-search-heart-fill text-2xl"></i>
          </div>
          <div class="min-w-0 flex-1">
            <h1 class="text-2xl md:text-[1.65rem] font-bold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">SEO &amp; Social</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
              Schema.org JSON-LD + Open Graph meta tags — ช่วยให้ Google/FB/LINE แสดงผล rich preview ของเว็บได้สวยขึ้น
            </p>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $schemaEnabled ? 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-400' }}">
                <i class="bi bi-{{ $schemaEnabled ? 'check-circle-fill' : 'toggle-off' }}"></i>
                Schema.org {{ $schemaEnabled ? 'ON' : 'OFF' }}
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $ogAutoEnabled ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-400' }}">
                <i class="bi bi-{{ $ogAutoEnabled ? 'check-circle-fill' : 'toggle-off' }}"></i>
                Open Graph {{ $ogAutoEnabled ? 'ON' : 'OFF' }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2"
         x-data="{ show: true }" x-show="show">
      <i class="bi bi-check-circle-fill text-emerald-500"></i>
      <span class="flex-1">{{ session('success') }}</span>
      <button type="button" @click="show = false" class="text-emerald-600/60 hover:text-emerald-700 dark:text-emerald-400/60 dark:hover:text-emerald-300">
        <i class="bi bi-x-lg text-sm"></i>
      </button>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.marketing.seo.update') }}" @submit="hasChanges = false" @input="hasChanges = true" class="space-y-4">
    @csrf

    {{-- ═══ Schema.org Markup ═══ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
      <div class="bg-gradient-to-r from-violet-50/60 to-transparent dark:from-violet-500/5 flex items-center gap-3 p-5 border-b border-slate-200/60 dark:border-white/[0.06]">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-purple-500 text-white flex items-center justify-center shadow-md shrink-0">
          <i class="bi bi-code-square text-lg"></i>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-bold text-slate-800 dark:text-slate-100">Schema.org Structured Data (JSON-LD)</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Google ใช้ data นี้แสดง rich results (rating stars, breadcrumbs, event cards, FAQ ใน search)
          </p>
        </div>
        <label class="flex items-center gap-2 cursor-pointer shrink-0">
          <input type="hidden" name="schema_markup_enabled" value="0">
          <input type="checkbox" name="schema_markup_enabled" value="1"
                 class="peer sr-only" {{ $schemaEnabled ? 'checked' : '' }}>
          <span class="relative w-11 h-6 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-violet-500 transition-all">
            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-md transition-all peer-checked:translate-x-5"></span>
          </span>
        </label>
      </div>
      <div class="p-5 text-xs text-slate-600 dark:text-slate-400 space-y-2.5">
        <div class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
          <span>Organization (logo, ชื่อ, contact) — ฝังใน footer ทุกหน้า</span>
        </div>
        <div class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
          <span>WebSite + SearchAction — ให้ Google แสดง search box ใต้ listing</span>
        </div>
        <div class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
          <span>Event, Product, Review, BreadcrumbList — ตาม context ของแต่ละหน้า</span>
        </div>
        <div class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
          <span>FAQPage — สำหรับหน้า FAQ / support</span>
        </div>
        <p class="mt-3 pt-3 border-t border-slate-200/60 dark:border-white/[0.06] text-[0.7rem] text-slate-500 dark:text-slate-400">
          <i class="bi bi-info-circle"></i> ทดสอบได้ที่
          <a href="https://search.google.com/test/rich-results" target="_blank" class="text-violet-600 dark:text-violet-400 hover:underline">Google Rich Results Test</a>
        </p>
      </div>
    </div>

    {{-- ═══ Open Graph / Social Cards ═══ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
      <div class="bg-gradient-to-r from-sky-50/60 to-transparent dark:from-sky-500/5 flex items-center gap-3 p-5 border-b border-slate-200/60 dark:border-white/[0.06]">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-blue-500 text-white flex items-center justify-center shadow-md shrink-0">
          <i class="bi bi-share-fill text-lg"></i>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-bold text-slate-800 dark:text-slate-100">Open Graph + Twitter Cards</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            เมื่อ paste link ใน Facebook / LINE / Twitter / Discord — จะแสดง preview card สวยๆ
          </p>
        </div>
        <label class="flex items-center gap-2 cursor-pointer shrink-0">
          <input type="hidden" name="og_auto_enabled" value="0">
          <input type="checkbox" name="og_auto_enabled" value="1"
                 class="peer sr-only" {{ $ogAutoEnabled ? 'checked' : '' }}>
          <span class="relative w-11 h-6 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-sky-500 transition-all">
            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-md transition-all peer-checked:translate-x-5"></span>
          </span>
        </label>
      </div>
      <div class="p-5 space-y-3">
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Default OG Image URL (fallback)</label>
          <input type="text" name="og_default_image" value="{{ $settings['og_default_image'] ?? '' }}"
                 placeholder="/images/og-default.jpg หรือ https://cdn.domain.com/og.jpg"
                 class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-sky-500/40 focus:border-sky-500 outline-none transition">
          <p class="text-[0.7rem] text-slate-500 dark:text-slate-400 mt-1">
            <i class="bi bi-info-circle"></i> แนะนำ 1200x630 px, &lt; 1MB. ใช้เป็น fallback เมื่อหน้านั้นไม่มีรูปเฉพาะ
          </p>
        </div>
        <div class="text-xs pt-3 border-t border-slate-200/60 dark:border-white/[0.06]">
          <p class="font-semibold text-slate-700 dark:text-slate-300 mb-2">Auto-generated tags ต่อหน้า:</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5 text-slate-600 dark:text-slate-400">
            <span class="inline-flex items-center gap-1.5"><i class="bi bi-dot text-sky-500 text-lg"></i> og:title, og:description</span>
            <span class="inline-flex items-center gap-1.5"><i class="bi bi-dot text-sky-500 text-lg"></i> og:image, og:url</span>
            <span class="inline-flex items-center gap-1.5"><i class="bi bi-dot text-sky-500 text-lg"></i> og:type (event/product/article)</span>
            <span class="inline-flex items-center gap-1.5"><i class="bi bi-dot text-sky-500 text-lg"></i> twitter:card, twitter:image</span>
          </div>
        </div>
        <p class="text-[0.7rem] text-slate-500 dark:text-slate-400 pt-3 border-t border-slate-200/60 dark:border-white/[0.06]">
          <i class="bi bi-check2-circle"></i> Debug ได้ที่
          <a href="https://developers.facebook.com/tools/debug/" target="_blank" class="text-sky-600 dark:text-sky-400 hover:underline">Facebook Sharing Debugger</a>
          &middot;
          <a href="https://cards-dev.twitter.com/validator" target="_blank" class="text-sky-600 dark:text-sky-400 hover:underline">Twitter Card Validator</a>
        </p>
      </div>
    </div>

    {{-- ═══ UTM Tracking (read-only info) ═══ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
      <div class="bg-gradient-to-r from-indigo-50/60 to-transparent dark:from-indigo-500/5 flex items-center gap-3 p-5 border-b border-slate-200/60 dark:border-white/[0.06]">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-500 text-white flex items-center justify-center shadow-md shrink-0">
          <i class="bi bi-link-45deg text-lg"></i>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-bold text-slate-800 dark:text-slate-100">UTM Attribution Tracking</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Auto-capture: utm_source / medium / campaign / content / term + gclid / fbclid / lineclid
          </p>
        </div>
        <a href="{{ route('admin.marketing.analytics') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline shrink-0">
          ดู analytics <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <div class="p-5">
        <p class="text-xs text-slate-700 dark:text-slate-300 mb-2 font-medium">
          <i class="bi bi-tag-fill text-indigo-500"></i> URL pattern ตัวอย่าง:
        </p>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200/60 dark:border-white/[0.06] p-3 font-mono text-[0.7rem] text-indigo-700 dark:text-indigo-300 break-all">
          {{ url('/') }}/?utm_source=line&utm_medium=oa_broadcast&utm_campaign=new_year_2026
        </div>
        <p class="mt-3 text-[0.7rem] text-slate-500 dark:text-slate-400">
          <i class="bi bi-info-circle"></i> First-touch attribution: เก็บ source แรกที่ user เข้ามาตลอด session — attach order เมื่อซื้อ
        </p>
      </div>
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
               class="px-4 py-2 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition">ยกเลิก</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold bg-gradient-to-br from-blue-500 via-indigo-500 to-violet-500 text-white shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
              <i class="bi bi-check2"></i> บันทึก
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
