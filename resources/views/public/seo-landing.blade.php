@extends('layouts.app')

@section('title', $title)

@push('styles')
<style>
  /* Scoped styles — match the photographer landing's accent palette but
     theme to the niche's accent. The CSS variable is set inline below. */
  .sl-hero {
    background:
      radial-gradient(900px 500px at 15% -10%, color-mix(in oklab, var(--sl-accent) 25%, transparent), transparent 60%),
      radial-gradient(700px 500px at 85% 15%, color-mix(in oklab, var(--sl-accent) 18%, transparent), transparent 60%),
      linear-gradient(160deg,#f8fafc 0%,#f1f5f9 60%,#ede9fe 100%);
  }
  html.dark .sl-hero {
    background:
      radial-gradient(900px 500px at 15% -10%, color-mix(in oklab, var(--sl-accent) 30%, transparent), transparent 60%),
      radial-gradient(700px 500px at 85% 15%, color-mix(in oklab, var(--sl-accent) 22%, transparent), transparent 60%),
      linear-gradient(160deg,#020617 0%,#0f172a 60%,#1e1b4b 100%);
  }
  .sl-pill { background: color-mix(in oklab, var(--sl-accent) 15%, transparent); color: var(--sl-accent); }
  .sl-grad-text { color: var(--sl-accent); }
</style>
@endpush

@section('content-full')
<article style="--sl-accent: {{ $nicheCfg['accent_hex'] }};">

  {{-- ════════════════════════════════════════════════════════════════════
       HERO — H1 + breadcrumb + scoped USP. Headline includes both
       primary keyword (niche) AND geo modifier (province) — that's the
       single biggest signal Google uses to map a query to this page.
       ════════════════════════════════════════════════════════════════════ --}}
  <section class="sl-hero py-14 sm:py-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">

      {{-- Breadcrumb (HTML — JSON-LD version comes from SeoService) --}}
      <nav class="text-xs text-slate-500 dark:text-slate-400 mb-5" aria-label="breadcrumb">
        <a href="{{ route('home') }}" class="hover:underline">หน้าแรก</a>
        <span class="mx-1.5">›</span>
        <a href="{{ route('events.index') }}" class="hover:underline">ช่างภาพ</a>
        <span class="mx-1.5">›</span>
        <a href="{{ route('seo.landing.niche', ['niche' => $niche]) }}" class="hover:underline">{{ $nicheCfg['label'] }}</a>
        @if($provinceCfg)
          <span class="mx-1.5">›</span>
          <span class="text-slate-700 dark:text-slate-300">{{ $provinceCfg['label'] }}</span>
        @endif
      </nav>

      <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold sl-pill mb-4">
        <i class="bi {{ $nicheCfg['icon'] }}"></i>
        {{ $nicheCfg['plural'] }}{{ $provinceCfg ? ' · ' . $provinceCfg['short'] : ' ทั่วประเทศ' }}
      </span>

      <h1 class="font-extrabold leading-[1.1] tracking-tight text-3xl sm:text-5xl lg:text-6xl text-slate-900 dark:text-white mb-5">
        {{ str_replace(':scope:', $scope, $nicheCfg['h1_pat']) }}
        <span class="block text-2xl sm:text-3xl lg:text-4xl mt-2 sl-grad-text font-extrabold">
          ค้นหาด้วย AI · ส่งรูปเข้า LINE
        </span>
      </h1>

      <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 leading-relaxed mb-7 max-w-2xl">
        {{ $description }}
      </p>

      {{-- Functional CTAs: customer-action verbs, not generic browse links.
           Order = decreasing user-readiness:
             1. "หารูปตัวเองด้วย AI"   — solves their actual job-to-be-done
             2. "ดูอีเวนต์ทั้งหมด"      — for browsers who don't know which event yet
             3. "จองช่างภาพ"            — pre-event booking funnel --}}
      <div class="flex flex-wrap gap-3">
        {{-- Face Search lives PER-EVENT (route needs an event_id):
             /events/{id}/face-search. There's no global landing,
             so the SEO page directs the user to the events index
             with `?action=face-search` — the events index renders
             an instructional banner explaining "pick an event,
             then upload your selfie inside it" (added in the
             customer-search-button audit commits earlier). The
             previous href `/face-search` had no matching route
             and 404'd / 405'd. --}}
        <a href="{{ route('events.index') }}?action=face-search"
           class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl font-bold text-white shadow-xl"
           style="background: var(--sl-accent); box-shadow: 0 12px 30px -8px var(--sl-accent);">
          <i class="bi bi-person-bounding-box"></i>
          ค้นหาตัวเองด้วย AI
        </a>
        <a href="{{ route('events.index') }}{{ !empty($nicheCfg['category_slug']) ? '?category=' . $nicheCfg['category_slug'] : '' }}"
           class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl font-bold border-2 border-slate-300 dark:border-white/20 text-slate-700 dark:text-white bg-white/60 dark:bg-white/5 hover:bg-white dark:hover:bg-white/10 transition">
          <i class="bi bi-grid-3x3-gap"></i>
          ดูอีเวนต์ทั้งหมด
        </a>
        <a href="{{ url('/become-photographer') }}"
           class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl font-bold border-2 border-slate-300 dark:border-white/20 text-slate-700 dark:text-white bg-white/60 dark:bg-white/5 hover:bg-white dark:hover:bg-white/10 transition">
          <i class="bi bi-calendar-check"></i>
          จองช่างภาพ
        </a>
      </div>
    </div>
  </section>

  {{-- ════════════════════════════════════════════════════════════════════
       USP STRIP — universal selling points (config-driven so they don't
       drift across landing pages).
       ════════════════════════════════════════════════════════════════════ --}}
  <section class="py-12 bg-white dark:bg-slate-950">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
        @foreach($usp_bullets as $u)
        <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900/60 p-4">
          <i class="bi {{ $u['icon'] }} text-2xl mb-2" style="color: var(--sl-accent);"></i>
          <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-1">{{ $u['title'] }}</h3>
          <p class="text-xs text-slate-600 dark:text-slate-400 leading-snug">{{ $u['body'] }}</p>
        </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- ════════════════════════════════════════════════════════════════════
       MATCHING EVENTS — actual DB rows scoped to this niche/province.
       This is the page's "fresh content" signal — every render shows the
       latest events that match, so Google sees the page as living.
       ════════════════════════════════════════════════════════════════════ --}}
  @if($events->count() > 0)
  <section class="py-12 sm:py-16 bg-gradient-to-br from-slate-50 to-indigo-50/40 dark:from-slate-900 dark:to-indigo-950/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
      <div class="flex items-end justify-between mb-6">
        <div>
          <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white">
            อีเวนต์{{ $nicheCfg['label'] }}{{ $provinceCfg ? ' · ' . $provinceCfg['short'] : '' }}
          </h2>
          <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">งานล่าสุดที่ตรงกับการค้นหาของคุณ</p>
        </div>
        <a href="{{ route('events.index') }}" class="hidden sm:inline-flex items-center gap-1 text-sm font-semibold sl-grad-text hover:underline">
          ดูทั้งหมด <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($events as $event)
        <a href="{{ route('events.show', $event->slug ?: $event->id) }}"
           class="group rounded-2xl overflow-hidden bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 hover:-translate-y-1 transition">
          <div class="aspect-[4/3] bg-slate-100 dark:bg-slate-800 overflow-hidden relative">
            @if($event->cover_image)
              {{-- loading=lazy + fetchpriority=low keeps LCP focused on the H1 above --}}
              <img src="{{ $event->cover_image }}"
                   alt="{{ $event->name }}"
                   loading="lazy" decoding="async" fetchpriority="low"
                   class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
            @else
              <div class="w-full h-full flex items-center justify-center text-slate-400">
                <i class="bi {{ $nicheCfg['icon'] }} text-4xl"></i>
              </div>
            @endif
          </div>
          <div class="p-3">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-1 line-clamp-2 group-hover:underline">{{ $event->name }}</h3>
            @if($event->shoot_date)
              <p class="text-xs text-slate-500 dark:text-slate-400">
                <i class="bi bi-calendar3"></i>
                {{ \Carbon\Carbon::parse($event->shoot_date)->translatedFormat('j M Y') }}
              </p>
            @endif
          </div>
        </a>
        @endforeach
      </div>
    </div>
  </section>
  @endif

  {{-- ════════════════════════════════════════════════════════════════════
       FEATURED PHOTOGRAPHERS — Person schema candidates.
       ════════════════════════════════════════════════════════════════════ --}}
  @if($photographers->count() > 0)
  <section class="py-12 sm:py-16 bg-white dark:bg-slate-950">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
      <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mb-1">
        {{ $nicheCfg['plural'] }}แนะนำ{{ $provinceCfg ? ' · ' . $provinceCfg['short'] : '' }}
      </h2>
      <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">มืออาชีพที่ลูกค้าเลือกใช้ซ้ำมากที่สุด</p>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        @foreach($photographers as $p)
        @php
          // The avatar column stores an R2/S3 object key
          // ("system/avatars/user_3/uuid.png"), not a URL. Render it
          // through R2MediaService::url() so the <img src> resolves
          // to the R2 public hostname, not a 404 against this app.
          $avatarUrl = null;
          if (!empty($p->avatar)) {
              if (preg_match('#^(?:https?:)?//#i', $p->avatar)) {
                  $avatarUrl = $p->avatar;
              } else {
                  try {
                      $resolved = (string) app(\App\Services\Media\R2MediaService::class)->url($p->avatar);
                      $avatarUrl = preg_match('#^(?:https?:)?//#i', $resolved)
                          ? $resolved
                          : '/storage/' . ltrim($p->avatar, '/');
                  } catch (\Throwable) {
                      $avatarUrl = '/storage/' . ltrim($p->avatar, '/');
                  }
              }
          }
        @endphp
        <a href="{{ route('photographers.show', $p->user_id) }}"
           class="group rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-4 text-center hover:-translate-y-1 hover:shadow-lg transition">
          <div class="w-16 h-16 mx-auto rounded-full bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-500/20 dark:to-violet-500/20 mb-2 overflow-hidden flex items-center justify-center ring-2 ring-white dark:ring-slate-800 shadow-md">
            @if($avatarUrl)
              <img src="{{ $avatarUrl }}" alt="{{ $p->display_name }}" loading="lazy" class="w-full h-full object-cover">
            @else
              {{-- Fallback: first letter of name in a coloured circle so
                   the section never shows a blank icon when avatar
                   isn't set yet. --}}
              <span class="text-xl font-extrabold text-indigo-600">
                {{ mb_strtoupper(mb_substr($p->display_name ?? $p->photographer_code ?? '?', 0, 1, 'UTF-8'), 'UTF-8') }}
              </span>
            @endif
          </div>
          <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-0.5 line-clamp-1">{{ $p->display_name ?? $p->photographer_code }}</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $p->events_count }} อีเวนต์</p>
        </a>
        @endforeach
      </div>
    </div>
  </section>
  @endif

  {{-- ════════════════════════════════════════════════════════════════════
       FAQ — eligible for FAQ rich snippet (schema added in controller).
       ════════════════════════════════════════════════════════════════════ --}}
  <section class="py-12 sm:py-16 bg-slate-50 dark:bg-slate-900/40">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
      <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mb-1 text-center">คำถามที่พบบ่อย</h2>
      <p class="text-sm text-slate-600 dark:text-slate-400 mb-8 text-center">
        เกี่ยวกับ{{ $nicheCfg['label'] }}{{ $provinceCfg ? ' · ' . $provinceCfg['short'] : '' }}
      </p>
      <div class="space-y-3" x-data="{ open: 0 }">
        @foreach($faqs as $i => $faq)
        <div class="rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 overflow-hidden">
          <button type="button"
                  @click="open = (open === {{ $i }} ? null : {{ $i }})"
                  class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-white/5">
            <span class="font-semibold text-slate-900 dark:text-white">{{ $faq['q'] }}</span>
            <i class="bi" :class="open === {{ $i }} ? 'bi-dash-circle' : 'bi-plus-circle'" style="color: var(--sl-accent);"></i>
          </button>
          <div x-show="open === {{ $i }}" x-collapse class="px-5 pb-4 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
            {{ $faq['a'] }}
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- ════════════════════════════════════════════════════════════════════
       INTERNAL LINKING — sibling provinces and niches.
       Anchor text uses the actual destination keyword (no "click here")
       so each link transfers intent to the linked page.
       ════════════════════════════════════════════════════════════════════ --}}
  @if(!empty($related_provinces) || !empty($related_niches))
  <section class="py-12 bg-white dark:bg-slate-950 border-t border-slate-200 dark:border-white/10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 grid grid-cols-1 md:grid-cols-2 gap-8">

      @if(!empty($related_provinces))
      <div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">
          ดู{{ $nicheCfg['label'] }}ในจังหวัดอื่น
        </h3>
        <div class="flex flex-wrap gap-2">
          @foreach($related_provinces as $slug => $prov)
            <a href="{{ route('seo.landing.province', ['niche' => $niche, 'province' => $slug]) }}"
               class="inline-block px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-xs font-medium text-slate-700 dark:text-slate-300 transition">
              {{ $nicheCfg['label'] }} {{ $prov['short'] }}
            </a>
          @endforeach
        </div>
      </div>
      @endif

      @if(!empty($related_niches))
      <div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">
          ประเภทช่างภาพอื่น{{ $provinceCfg ? ' · ' . $provinceCfg['short'] : '' }}
        </h3>
        <div class="flex flex-wrap gap-2">
          @foreach($related_niches as $slug => $cfg)
            @php
              $relUrl = $provinceCfg
                ? route('seo.landing.province', ['niche' => $slug, 'province' => array_search($provinceCfg, config('seo_landings.provinces')) ?: ''])
                : route('seo.landing.niche', ['niche' => $slug]);
            @endphp
            <a href="{{ $relUrl }}"
               class="inline-block px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-xs font-medium text-slate-700 dark:text-slate-300 transition">
              <i class="bi {{ $cfg['icon'] }} mr-1"></i> {{ $cfg['label'] }}
            </a>
          @endforeach
        </div>
      </div>
      @endif
    </div>
  </section>
  @endif

  {{-- ════════════════════════════════════════════════════════════════════
       LONG-TAIL keyword footer — cleanly listed so Google can extract
       additional intents the page also covers. NOT a keyword stuff —
       these are real search variants from Google Trends.
       ════════════════════════════════════════════════════════════════════ --}}
  <section class="py-10 bg-slate-50 dark:bg-slate-900/40 border-t border-slate-200 dark:border-white/10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center">
      <p class="text-xs text-slate-500 dark:text-slate-500 leading-relaxed">
        คำค้นที่เกี่ยวข้อง:
        <span class="text-slate-700 dark:text-slate-300">
          @foreach(($nicheCfg['long_tail'] ?? []) as $i => $term)
            {{ $term }}@if(!$loop->last) · @endif
          @endforeach
          @if($provinceCfg)
            · {{ $nicheCfg['label'] }} {{ $provinceCfg['short'] }}
            · {{ $nicheCfg['pretty_keyword'] }} {{ $provinceCfg['short'] }}
          @endif
        </span>
      </p>
    </div>
  </section>

</article>
@endsection
