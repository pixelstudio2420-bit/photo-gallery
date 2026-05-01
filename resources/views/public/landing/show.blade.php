@extends('layouts.app')

@php
  $theme  = $page->themeData();
  $accent = $theme['accent'];
  $isEventLanding = $page->type === 'event';
  $sm = $page->source_meta ?? [];

  // For event landings, load the parent Event so we can pull cover_image,
  // shoot_date, photographer name, etc. directly into the hero.
  $sourceEvent = null;
  if ($isEventLanding && !empty($sm['event_id'])) {
    $sourceEvent = \App\Models\Event::with([
        'category:id,name,slug',
        'photographerProfile:user_id,display_name',
        'province:id,name_th,name_en',
    ])->find($sm['event_id']);
  }

  // Load full photographer profile for type='photographer' landings so
  // we can render avatar / bio / specialties / equipment / social
  // links in a dedicated profile-card section below the hero.
  $isPhotographerLanding = $page->type === 'photographer';
  $sourcePhotographer = null;
  if ($isPhotographerLanding && !empty($sm['photographer_id'])) {
    $sourcePhotographer = \App\Models\PhotographerProfile::with('province:id,name_th,name_en')
        ->where('user_id', $sm['photographer_id'])
        ->first();
  }
@endphp

@php
  // Resolve avatar URL once for the photographer section.
  $photographerAvatarUrl = null;
  if ($sourcePhotographer && $sourcePhotographer->avatar) {
      $av = $sourcePhotographer->avatar;
      if (preg_match('#^(?:https?:)?//#i', $av)) {
          $photographerAvatarUrl = $av;
      } else {
          try {
              $photographerAvatarUrl = (string) app(\App\Services\Media\R2MediaService::class)->url($av);
          } catch (\Throwable) {
              $photographerAvatarUrl = '/storage/' . ltrim($av, '/');
          }
      }
  }

  // Breadcrumb middle-segment label + link target. Different page types
  // funnel back to a different index — photographer/category/combo
  // pages link to /photographers (the search), location/event pages
  // link to /events (the listings).
  $sectionLabel = match($page->type) {
    'location'      => 'อีเวนต์ตามพื้นที่',
    'category'      => 'ช่างภาพ',
    'combo'         => 'ช่างภาพ',
    'photographer'  => 'ช่างภาพ',
    'event_archive' => 'อีเวนต์ทั้งหมด',
    'event'         => 'อีเวนต์',
    default         => 'หน้า',
  };
  $sectionUrl = match($page->type) {
    'location', 'event_archive', 'event' => url('/events'),
    'category', 'combo', 'photographer'  => url('/photographers'),
    default                               => url('/'),
  };
@endphp

@section('title', $page->title)
@section('meta_description', $page->meta_description)

@push('head')
  <link rel="canonical" href="{{ $page->url() }}">
  <meta property="og:title" content="{{ $page->og_title ?? $page->title }}">
  <meta property="og:description" content="{{ $page->meta_description }}">
  <meta property="og:url" content="{{ $page->url() }}">
  <meta property="og:type" content="website">
  @if($page->og_image || $page->hero_image)
    <meta property="og:image" content="{{ asset($page->og_image ?? $page->hero_image) }}">
  @endif
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $page->og_title ?? $page->title }}">
  <meta name="twitter:description" content="{{ $page->meta_description }}">
  @if(!empty($schemaJson))
    <script type="application/ld+json">{!! json_encode($schemaJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
  @endif
  <style>
    /* Animated entrance for hero text */
    .lp-fade-up { animation: lpFadeUp .7s ease-out both; }
    .lp-fade-up-delay { animation: lpFadeUp .7s ease-out .15s both; }
    .lp-fade-up-delay-2 { animation: lpFadeUp .7s ease-out .3s both; }
    @keyframes lpFadeUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
    /* Masonry-ish grid for event photos */
    .lp-masonry { column-gap: 0.75rem; }
    @media (min-width: 640px) { .lp-masonry { column-count: 2; column-gap: 1rem; } }
    @media (min-width: 1024px) { .lp-masonry { column-count: 3; } }
    @media (min-width: 1280px) { .lp-masonry { column-count: 4; } }
    .lp-masonry > * { break-inside: avoid; margin-bottom: 1rem; }
  </style>
@endpush

@section('content-full')

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  HERO — full-bleed gradient with parallax-like cover image       ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
<section class="relative overflow-hidden bg-gradient-to-br {{ $theme['from'] }} {{ $theme['via'] }} {{ $theme['to'] }} text-white">

  {{-- Background photo (event landings ALWAYS use cover image; others
       use admin-set hero_image when available). 50% black gradient
       overlay so headlines stay legible no matter how busy the photo.
       Resolve R2/S3 object keys through the media service so we don't
       emit /system/avatars/... URLs that 404 against the local disk. --}}
  @php
    $heroBg = $page->hero_image ?? optional($sourceEvent)->cover_image;
    if ($heroBg && !preg_match('#^(?:https?:)?//#i', $heroBg)) {
        try {
            $heroBg = (string) app(\App\Services\Media\R2MediaService::class)->url($heroBg);
        } catch (\Throwable) {
            $heroBg = '/storage/' . ltrim($heroBg, '/');
        }
    }
  @endphp
  @if($heroBg)
    <div class="absolute inset-0 bg-cover bg-center scale-110 blur-[2px]"
         style="background-image: url('{{ $heroBg }}');"></div>
    <div class="absolute inset-0 bg-gradient-to-br from-black/70 via-black/40 to-black/70"></div>
  @endif

  {{-- Decorative blur orbs --}}
  <div class="absolute -top-32 -right-32 w-[28rem] h-[28rem] bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="absolute -bottom-40 -left-32 w-[32rem] h-[32rem] bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="absolute top-1/4 left-1/3 w-72 h-72 bg-white/5 rounded-full blur-3xl pointer-events-none"></div>

  <div class="relative max-w-7xl mx-auto px-4 py-16 md:py-24 lg:py-32">

    {{-- Breadcrumb --}}
    <nav class="text-xs md:text-sm text-white/70 mb-5 flex items-center gap-1.5 flex-wrap" aria-label="Breadcrumb">
      <a href="{{ url('/') }}" class="hover:text-white transition">หน้าแรก</a>
      <i class="bi bi-chevron-right text-[10px]"></i>
      {{-- Middle segment is now a real <a> linking to the relevant
           index (photographers / events / etc.) so users can step up
           one level instead of the dead-end span we had before. --}}
      <a href="{{ $sectionUrl }}" class="hover:text-white transition underline-offset-2 hover:underline">
        {{ $sectionLabel }}
      </a>
      <i class="bi bi-chevron-right text-[10px]"></i>
      <span class="text-white font-medium truncate max-w-[280px] md:max-w-none" aria-current="page">{{ $page->h1 ?? $page->title }}</span>
    </nav>

    {{-- Two-column hero: text left, glass card right --}}
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_minmax(0,420px)] gap-8 lg:gap-12 items-center">

      {{-- Left: Title + description + CTA --}}
      <div>
        {{-- Type badge --}}
        <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/15 backdrop-blur-md rounded-full text-xs font-bold mb-5 lp-fade-up border border-white/20">
          <i class="bi {{ $theme['icon'] }}"></i>
          <span>{{ $sectionLabel }}</span>
          @if($isEventLanding && $sourceEvent && $sourceEvent->shoot_date)
            <span class="opacity-70">·</span>
            <span class="opacity-90">{{ \Carbon\Carbon::parse($sourceEvent->shoot_date)->format('d M Y') }}</span>
          @endif
        </div>

        <h1 class="text-3xl md:text-5xl lg:text-6xl font-extrabold tracking-tight mb-5 leading-[1.1] lp-fade-up-delay">
          {{ $page->h1 ?? $page->title }}
        </h1>

        <p class="text-base md:text-lg text-white/90 max-w-2xl leading-relaxed mb-7 lp-fade-up-delay-2">
          {{ $page->meta_description }}
        </p>

        {{-- Primary CTA + Secondary action --}}
        <div class="flex flex-wrap gap-3 lp-fade-up-delay-2">
          @if($page->cta_text && $page->cta_url)
            <a href="{{ $page->cta_url }}"
               class="inline-flex items-center gap-2 px-6 py-3 bg-white hover:bg-yellow-50 text-slate-900 font-bold rounded-xl shadow-2xl shadow-black/20 transition active:scale-95">
              <i class="bi bi-arrow-right-circle-fill"></i>
              <span>{{ $page->cta_text }}</span>
            </a>
          @elseif($isEventLanding && $sourceEvent)
            <a href="{{ url('/events/' . ($sourceEvent->slug ?: $sourceEvent->id)) }}"
               class="inline-flex items-center gap-2 px-6 py-3 bg-white hover:bg-yellow-50 text-slate-900 font-bold rounded-xl shadow-2xl shadow-black/20 transition active:scale-95">
              <i class="bi bi-images"></i>
              <span>ดูรูปทั้งหมด ({{ $sm['data_count'] ?? 0 }} รูป)</span>
            </a>
          @endif

          @if(in_array($page->type, ['location', 'category', 'combo']))
            <a href="{{ url('/events') }}"
               class="inline-flex items-center gap-2 px-6 py-3 bg-white/10 backdrop-blur-md hover:bg-white/20 border border-white/30 text-white font-semibold rounded-xl transition">
              <i class="bi bi-grid-3x3-gap"></i>
              <span>ดูอีเวนต์ทั้งหมด</span>
            </a>
          @endif
        </div>
      </div>

      {{-- Right: Glass stats card (modern style) --}}
      @if(($page->show_stats ?? true) && (!empty($sm) || $isEventLanding))
        <div class="lp-fade-up-delay-2">
          <div class="bg-white/10 backdrop-blur-xl rounded-3xl border border-white/20 p-5 md:p-6 shadow-2xl">
            <div class="text-[11px] uppercase tracking-widest text-white/70 font-semibold mb-4">
              <i class="bi bi-graph-up mr-1"></i> สถิติ
            </div>

            <div class="grid grid-cols-2 gap-3">
              @if($isEventLanding && $sourceEvent)
                {{-- Event-specific stats --}}
                <div class="bg-white/10 rounded-2xl p-3 text-center">
                  <div class="text-3xl md:text-4xl font-extrabold leading-none">{{ number_format($sm['data_count'] ?? 0) }}</div>
                  <div class="text-[10px] uppercase tracking-wide text-white/70 mt-1">รูปทั้งหมด</div>
                </div>
                @if($sourceEvent->category)
                  <div class="bg-white/10 rounded-2xl p-3 text-center flex flex-col justify-center">
                    <i class="bi bi-tag-fill text-xl mb-0.5"></i>
                    <div class="text-xs font-semibold leading-tight">{{ $sourceEvent->category->name }}</div>
                  </div>
                @endif
                @if($sourceEvent->province)
                  <div class="bg-white/10 rounded-2xl p-3 text-center flex flex-col justify-center">
                    <i class="bi bi-geo-alt-fill text-xl mb-0.5"></i>
                    <div class="text-xs font-semibold leading-tight">{{ $sourceEvent->province->name_th }}</div>
                  </div>
                @endif
                @if($sourceEvent->photographerProfile)
                  <div class="bg-white/10 rounded-2xl p-3 text-center flex flex-col justify-center">
                    <i class="bi bi-camera-fill text-xl mb-0.5"></i>
                    <div class="text-xs font-semibold leading-tight truncate">{{ $sourceEvent->photographerProfile->display_name }}</div>
                  </div>
                @endif
              @else
                {{-- Aggregate stats for location/category/combo pages --}}
                @if(!empty($sm['data_count']))
                  <div class="bg-white/10 rounded-2xl p-3 text-center">
                    <div class="text-3xl md:text-4xl font-extrabold leading-none">{{ number_format($sm['data_count']) }}</div>
                    <div class="text-[10px] uppercase tracking-wide text-white/70 mt-1">รายการ</div>
                  </div>
                @endif
                @if(!empty($sm['photographer_count']))
                  <div class="bg-white/10 rounded-2xl p-3 text-center">
                    <div class="text-3xl md:text-4xl font-extrabold leading-none">{{ number_format($sm['photographer_count']) }}</div>
                    <div class="text-[10px] uppercase tracking-wide text-white/70 mt-1">ช่างภาพ</div>
                  </div>
                @endif
                <div class="bg-white/10 rounded-2xl p-3 text-center">
                  <div class="text-3xl md:text-4xl font-extrabold leading-none">{{ number_format($page->view_count) }}</div>
                  <div class="text-[10px] uppercase tracking-wide text-white/70 mt-1">เข้าชม</div>
                </div>
                <div class="bg-white/10 rounded-2xl p-3 text-center flex flex-col justify-center">
                  <i class="bi bi-shield-check text-2xl mb-0.5"></i>
                  <div class="text-[10px] uppercase tracking-wide text-white/70">ปลอดภัย 100%</div>
                </div>
              @endif
            </div>
          </div>
        </div>
      @endif
    </div>
  </div>
</section>

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  PHOTOGRAPHER PROFILE CARD — only for type='photographer'        ║
     ║  Shows avatar + headline + specialties + social links + stats    ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
@if($isPhotographerLanding && $sourcePhotographer)
  <section class="bg-white dark:bg-slate-950 -mt-12 md:-mt-16 relative z-10">
    <div class="max-w-5xl mx-auto px-4">
      <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-white/[0.06] p-6 md:p-8">
        <div class="grid grid-cols-1 md:grid-cols-[auto_1fr_auto] gap-5 md:gap-7 items-center">

          {{-- Avatar --}}
          <div class="flex justify-center md:justify-start">
            @if($photographerAvatarUrl)
              <img src="{{ $photographerAvatarUrl }}"
                   alt="{{ $sourcePhotographer->display_name }}"
                   class="w-28 h-28 md:w-36 md:h-36 rounded-full object-cover ring-4 ring-{{ $accent }}-200 dark:ring-{{ $accent }}-500/30 shadow-xl">
            @else
              <div class="w-28 h-28 md:w-36 md:h-36 rounded-full bg-gradient-to-br {{ $theme['from'] }} {{ $theme['to'] }} flex items-center justify-center text-white text-4xl font-bold ring-4 ring-{{ $accent }}-200 dark:ring-{{ $accent }}-500/30 shadow-xl">
                {{ mb_substr($sourcePhotographer->display_name ?? '?', 0, 1) }}
              </div>
            @endif
          </div>

          {{-- Bio + headline --}}
          <div class="text-center md:text-left">
            <h2 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white mb-1">
              {{ $sourcePhotographer->display_name }}
              @if($sourcePhotographer->accepts_bookings)
                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 rounded-full align-middle ml-1">
                  <i class="bi bi-check-circle-fill"></i> รับงาน
                </span>
              @endif
            </h2>
            @if($sourcePhotographer->headline)
              <div class="text-sm md:text-base text-{{ $accent }}-600 dark:text-{{ $accent }}-400 font-semibold mb-2">
                {{ $sourcePhotographer->headline }}
              </div>
            @endif
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 justify-center md:justify-start text-xs md:text-sm text-slate-500 dark:text-slate-400 mb-2">
              @if($sourcePhotographer->province)
                <span class="inline-flex items-center gap-1"><i class="bi bi-geo-alt-fill text-{{ $accent }}-500"></i>{{ $sourcePhotographer->province->name_th }}</span>
              @endif
              @if($sourcePhotographer->years_experience > 0)
                <span class="inline-flex items-center gap-1"><i class="bi bi-clock-history text-{{ $accent }}-500"></i>ประสบการณ์ {{ $sourcePhotographer->years_experience }} ปี</span>
              @endif
              @if($sourcePhotographer->response_time_hours)
                <span class="inline-flex items-center gap-1"><i class="bi bi-lightning-charge-fill text-{{ $accent }}-500"></i>ตอบใน {{ $sourcePhotographer->response_time_hours }} ชม.</span>
              @endif
            </div>
            @if($sourcePhotographer->bio)
              <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed mt-2 line-clamp-3 md:line-clamp-none">
                {{ $sourcePhotographer->bio }}
              </p>
            @endif
          </div>

          {{-- Social links column --}}
          <div class="flex md:flex-col items-center justify-center gap-2 flex-wrap">
            @if($sourcePhotographer->instagram_handle)
              <a href="https://instagram.com/{{ $sourcePhotographer->instagram_handle }}" target="_blank" rel="noopener nofollow"
                 class="w-11 h-11 rounded-xl bg-pink-100 hover:bg-pink-200 dark:bg-pink-500/15 dark:hover:bg-pink-500/25 text-pink-600 flex items-center justify-center transition" title="Instagram">
                <i class="bi bi-instagram"></i>
              </a>
            @endif
            @if($sourcePhotographer->facebook_url)
              <a href="{{ $sourcePhotographer->facebook_url }}" target="_blank" rel="noopener nofollow"
                 class="w-11 h-11 rounded-xl bg-blue-100 hover:bg-blue-200 dark:bg-blue-500/15 dark:hover:bg-blue-500/25 text-blue-600 flex items-center justify-center transition" title="Facebook">
                <i class="bi bi-facebook"></i>
              </a>
            @endif
            @if($sourcePhotographer->website_url)
              <a href="{{ $sourcePhotographer->website_url }}" target="_blank" rel="noopener nofollow"
                 class="w-11 h-11 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 flex items-center justify-center transition" title="Website">
                <i class="bi bi-globe"></i>
              </a>
            @endif
            @if($sourcePhotographer->portfolio_url)
              <a href="{{ $sourcePhotographer->portfolio_url }}" target="_blank" rel="noopener nofollow"
                 class="w-11 h-11 rounded-xl bg-amber-100 hover:bg-amber-200 dark:bg-amber-500/15 dark:hover:bg-amber-500/25 text-amber-600 flex items-center justify-center transition" title="Portfolio">
                <i class="bi bi-collection"></i>
              </a>
            @endif
          </div>
        </div>

        {{-- Specialties + Languages + Equipment chips --}}
        @php
          $specialties = is_array($sourcePhotographer->specialties) ? $sourcePhotographer->specialties : [];
          $languages   = is_array($sourcePhotographer->languages)   ? $sourcePhotographer->languages   : [];
          $equipment   = is_array($sourcePhotographer->equipment)   ? $sourcePhotographer->equipment   : [];
          $langLabels = ['th' => 'ไทย', 'en' => 'English', 'zh' => '中文', 'ja' => '日本語', 'ko' => '한국어'];
        @endphp
        @if(count($specialties) > 0 || count($languages) > 0 || count($equipment) > 0)
          <div class="mt-6 pt-5 border-t border-slate-100 dark:border-white/[0.06] space-y-3">
            @if(count($specialties) > 0)
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] uppercase tracking-wider text-slate-400 font-bold mr-1"><i class="bi bi-tags-fill"></i> ความเชี่ยวชาญ:</span>
                @foreach($specialties as $s)
                  <span class="text-xs px-2.5 py-1 rounded-full bg-{{ $accent }}-100 dark:bg-{{ $accent }}-500/15 text-{{ $accent }}-700 dark:text-{{ $accent }}-300 font-semibold">{{ $s }}</span>
                @endforeach
              </div>
            @endif
            @if(count($languages) > 0)
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] uppercase tracking-wider text-slate-400 font-bold mr-1"><i class="bi bi-translate"></i> ภาษา:</span>
                @foreach($languages as $l)
                  <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-semibold">{{ $langLabels[$l] ?? strtoupper($l) }}</span>
                @endforeach
              </div>
            @endif
            @if(count($equipment) > 0)
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] uppercase tracking-wider text-slate-400 font-bold mr-1"><i class="bi bi-camera-fill"></i> อุปกรณ์:</span>
                @foreach($equipment as $e)
                  <span class="text-xs px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium">{{ $e }}</span>
                @endforeach
              </div>
            @endif
          </div>
        @endif
      </div>
    </div>
  </section>
@endif

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  BODY — readable narrative                                       ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
@if($page->body_html)
  <section class="bg-white dark:bg-slate-950">
    <div class="max-w-3xl mx-auto px-4 py-12 md:py-16">
      <div class="prose prose-slate dark:prose-invert prose-lg max-w-none leading-relaxed">
        {!! nl2br(e($page->body_html)) !!}
      </div>
    </div>
  </section>
@endif

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  GALLERY                                                         ║
     ║  • event landing → masonry of EVENT PHOTOS                       ║
     ║  • everything else → grid of related events                      ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
@if(($page->show_gallery ?? true) && $relatedItems && $relatedItems->count() > 0)
  <section class="bg-slate-50 dark:bg-slate-900/40">
    <div class="max-w-7xl mx-auto px-4 py-12 md:py-16">
      <div class="flex items-end justify-between gap-3 mb-8 flex-wrap">
        <div>
          <div class="text-xs uppercase tracking-widest text-{{ $accent }}-500 font-bold mb-1">
            @if($isEventLanding) ภาพในอีเวนต์ @else ที่เกี่ยวข้อง @endif
          </div>
          <h2 class="text-2xl md:text-4xl font-bold text-slate-900 dark:text-white">
            @if($isEventLanding)
              ดูภาพถ่าย ({{ $relatedItems->count() }})
            @elseif(in_array($page->type, ['location', 'category', 'combo']))
              อีเวนต์ที่เกี่ยวข้อง
            @elseif($page->type === 'photographer')
              ผลงานของช่างภาพ
            @else
              อีเวนต์ทั้งหมด
            @endif
          </h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            @if($isEventLanding)
              ภาพตัวอย่างจากอีเวนต์ — คลิกเพื่อดูแบบเต็ม
            @else
              คลิกเพื่อดูรายละเอียดและจองช่างภาพ
            @endif
          </p>
        </div>
        @if($isEventLanding && $sourceEvent)
          <a href="{{ url('/events/' . ($sourceEvent->slug ?: $sourceEvent->id)) }}"
             class="inline-flex items-center gap-2 px-4 py-2 bg-{{ $accent }}-500 hover:bg-{{ $accent }}-600 text-white text-sm font-semibold rounded-xl shadow-lg transition">
            ดูทั้งหมด <i class="bi bi-arrow-right"></i>
          </a>
        @endif
      </div>

      @if($isEventLanding)
        {{-- Masonry photo gallery — visually stronger for event pages --}}
        <div class="lp-masonry">
          @foreach($relatedItems as $photo)
            @php
              // Resolve R2 keys (event_photos.thumbnail_path) into real
              // URLs. Same fix as the hero — `asset()` would emit a path
              // that doesn't exist on the local disk for R2-hosted files.
              $thumb = null;
              if ($photo->thumbnail_path) {
                  if (preg_match('#^(?:https?:)?//#i', $photo->thumbnail_path)) {
                      $thumb = $photo->thumbnail_path;
                  } else {
                      try {
                          $thumb = (string) app(\App\Services\Media\R2MediaService::class)->url($photo->thumbnail_path);
                      } catch (\Throwable) {
                          $thumb = '/storage/' . ltrim($photo->thumbnail_path, '/');
                      }
                  }
              }
              $aspect = ($photo->width && $photo->height) ? ($photo->height / $photo->width) * 100 : 75;
            @endphp
            <a href="{{ $sourceEvent ? url('/events/' . ($sourceEvent->slug ?: $sourceEvent->id)) : '#' }}"
               class="group block overflow-hidden rounded-2xl bg-slate-200 dark:bg-slate-800 shadow-md hover:shadow-2xl hover:scale-[1.02] transition-all duration-300 relative">
              @if($thumb)
                <img src="{{ $thumb }}" alt="{{ $photo->caption ?? '' }}"
                     loading="lazy"
                     class="w-full h-auto object-cover">
              @else
                <div style="padding-top: {{ $aspect }}%;" class="bg-gradient-to-br {{ $theme['from'] }} {{ $theme['to'] }} flex items-center justify-center relative">
                  <i class="bi bi-camera text-white/40 text-4xl absolute"></i>
                </div>
              @endif
              <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition flex items-end p-3">
                <i class="bi bi-zoom-in text-white text-2xl"></i>
              </div>
            </a>
          @endforeach
        </div>
      @else
        {{-- Card grid — for non-event landings (locations, categories, etc.) --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-5">
          @foreach($relatedItems as $item)
            <a href="{{ url('/events/' . ($item->slug ?: $item->id)) }}"
               class="group block bg-white dark:bg-slate-800 rounded-2xl overflow-hidden border border-slate-200 dark:border-white/[0.06] hover:shadow-2xl hover:-translate-y-1 transition-all duration-300">
              @php
                // Resolve event cover_image to a real URL — see the
                // hero comment above for context on the asset()/R2 mismatch.
                $coverUrl = null;
                if ($item->cover_image) {
                    if (preg_match('#^(?:https?:)?//#i', $item->cover_image)) {
                        $coverUrl = $item->cover_image;
                    } else {
                        try {
                            $coverUrl = (string) app(\App\Services\Media\R2MediaService::class)->url($item->cover_image);
                        } catch (\Throwable) {
                            $coverUrl = '/storage/' . ltrim($item->cover_image, '/');
                        }
                    }
                }
              @endphp
              <div class="aspect-[4/3] bg-slate-100 dark:bg-slate-700 overflow-hidden relative">
                @if($coverUrl)
                  <img src="{{ $coverUrl }}"
                       alt="{{ $item->name }}"
                       loading="lazy"
                       class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                @else
                  <div class="w-full h-full flex items-center justify-center text-slate-300 dark:text-slate-500">
                    <i class="bi bi-camera text-4xl"></i>
                  </div>
                @endif
                {{-- Gradient overlay --}}
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/0 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                {{-- Hover label --}}
                <div class="absolute bottom-0 left-0 right-0 p-3 text-white text-xs font-semibold opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all">
                  <i class="bi bi-arrow-right-circle-fill mr-1"></i> ดูรายละเอียด
                </div>
              </div>
              <div class="p-4">
                <div class="font-semibold text-sm md:text-base text-slate-900 dark:text-white line-clamp-1 group-hover:text-{{ $accent }}-600 transition">{{ $item->name }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 flex items-center gap-2">
                  @if($item->shoot_date)
                    <span class="inline-flex items-center gap-1">
                      <i class="bi bi-calendar-event"></i>
                      <span>{{ \Carbon\Carbon::parse($item->shoot_date)->format('d/m/Y') }}</span>
                    </span>
                  @endif
                  @if($item->photographerProfile?->display_name)
                    <span class="ml-auto text-slate-400 truncate">{{ $item->photographerProfile->display_name }}</span>
                  @endif
                </div>
              </div>
            </a>
          @endforeach
        </div>
      @endif
    </div>
  </section>
@endif

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  EXTRA SECTIONS — admin-defined custom blocks                    ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
@if(!empty($page->extra_sections) && is_array($page->extra_sections))
  <section class="bg-white dark:bg-slate-950">
    <div class="max-w-4xl mx-auto px-4 py-12 md:py-16 space-y-5">
      @foreach($page->extra_sections as $section)
        @php
          $type  = $section['type']  ?? 'text';
          $title = $section['title'] ?? '';
          $body  = $section['body']  ?? '';
        @endphp
        <div class="bg-gradient-to-br from-slate-50 to-white dark:from-slate-900 dark:to-slate-800/50 rounded-3xl border border-slate-200 dark:border-white/[0.06] p-6 md:p-8 shadow-sm">
          @if($title)
            <div class="flex items-center gap-3 mb-4">
              <div class="w-11 h-11 rounded-xl bg-{{ $accent }}-100 dark:bg-{{ $accent }}-500/15 text-{{ $accent }}-600 dark:text-{{ $accent }}-400 flex items-center justify-center shrink-0">
                <i class="bi
                  @if($type === 'faq') bi-question-circle-fill
                  @elseif($type === 'testimonial') bi-chat-quote-fill
                  @elseif($type === 'callout') bi-megaphone-fill
                  @else bi-stars
                  @endif text-lg"></i>
              </div>
              <h3 class="text-xl md:text-2xl font-bold text-slate-900 dark:text-white">{{ $title }}</h3>
            </div>
          @endif
          @if($body)
            <div class="prose prose-slate dark:prose-invert max-w-none text-base leading-relaxed">
              {!! nl2br(e($body)) !!}
            </div>
          @endif
        </div>
      @endforeach
    </div>
  </section>
@endif

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  AUTO FAQ — toggleable, with FAQPage schema                       ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
@if(($page->show_faq ?? false) && in_array($page->type, ['location', 'category', 'combo', 'event']))
  @php
    $faqs = [];
    if ($page->type === 'event') {
        $faqs[] = ['q' => 'จะดูรูปทั้งหมดได้ที่ไหน?', 'a' => 'คลิกปุ่ม "ดูรูปทั้งหมด" หรือเข้าหน้า event โดยตรง'];
        $faqs[] = ['q' => 'รูปจะหายไปเมื่อไหร่?', 'a' => 'ภาพในอีเวนต์มีระยะเวลาเก็บไว้จำกัด — รีบดาวน์โหลดก่อนที่ภาพจะหายอัตโนมัติ'];
        $faqs[] = ['q' => 'ราคาเริ่มต้นที่เท่าไหร่?', 'a' => 'ราคาขึ้นอยู่กับจำนวนภาพและช่างภาพ — ดูในหน้า event เพื่อรายละเอียด'];
    } else {
        if (in_array($page->type, ['location', 'combo'])) {
            $faqs[] = ['q' => 'จองช่างภาพในพื้นที่นี้ใช้เวลาเท่าไหร่?', 'a' => 'จองได้ทันทีออนไลน์ — เลือกช่างภาพ ส่งคำขอ และรอช่างภาพยืนยัน (ปกติ 24 ชม.)'];
        }
        $faqs[] = ['q' => 'ราคาเริ่มต้นที่เท่าไหร่?', 'a' => 'ราคาขึ้นอยู่กับช่างภาพและประเภทงาน — ดูจากหน้า event ของแต่ละงานได้เลย'];
        $faqs[] = ['q' => 'ปลอดภัยไหม จะได้รูปจริงๆ ใช่ไหม?', 'a' => 'ทุกการจองมีระบบ escrow — เงินจะปล่อยให้ช่างภาพหลังจากที่คุณได้รับรูปเรียบร้อยแล้ว'];
    }
  @endphp

  @if(count($faqs) > 0)
    <section class="bg-slate-50 dark:bg-slate-900/40">
      <div class="max-w-4xl mx-auto px-4 py-12 md:py-16">
        <div class="text-center mb-8">
          <div class="text-xs uppercase tracking-widest text-{{ $accent }}-500 font-bold mb-2">FAQ</div>
          <h2 class="text-2xl md:text-4xl font-bold text-slate-900 dark:text-white">คำถามที่พบบ่อย</h2>
        </div>
        <div class="space-y-3">
          @foreach($faqs as $faq)
            <details class="group bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/[0.06] overflow-hidden hover:shadow-md transition">
              <summary class="cursor-pointer px-5 py-4 flex items-center justify-between gap-3 list-none">
                <span class="font-semibold text-slate-900 dark:text-white">{{ $faq['q'] }}</span>
                <div class="w-7 h-7 rounded-full bg-{{ $accent }}-100 dark:bg-{{ $accent }}-500/20 text-{{ $accent }}-600 dark:text-{{ $accent }}-400 flex items-center justify-center group-open:rotate-180 transition shrink-0">
                  <i class="bi bi-chevron-down text-xs"></i>
                </div>
              </summary>
              <div class="px-5 pb-5 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">{{ $faq['a'] }}</div>
            </details>
          @endforeach
        </div>

        @php
          $faqSchema = [
            '@context' => 'https://schema.org',
            '@type'    => 'FAQPage',
            'mainEntity' => array_map(fn($f) => [
              '@type' => 'Question',
              'name'  => $f['q'],
              'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ], $faqs),
          ];
        @endphp
        <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
      </div>
    </section>
  @endif
@endif

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  INTERNAL LINKING — sibling pages                                ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
@if(($page->show_related ?? true) && in_array($page->type, ['location', 'category', 'combo', 'event_archive', 'event']))
  @php
    $siblings = \App\Models\SeoLandingPage::published()
        ->ofType($page->type)
        ->where('id', '!=', $page->id)
        ->orderByDesc('view_count')
        ->limit(8)
        ->get();
  @endphp
  @if($siblings->count() > 0)
    <section class="bg-white dark:bg-slate-950">
      <div class="max-w-7xl mx-auto px-4 py-12 md:py-16">
        <div class="text-center mb-8">
          <div class="text-xs uppercase tracking-widest text-{{ $accent }}-500 font-bold mb-2">EXPLORE</div>
          <h2 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white">หน้าที่เกี่ยวข้อง</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          @foreach($siblings as $s)
            @php $sTheme = $s->themeData(); @endphp
            <a href="{{ $s->url() }}"
               class="group flex items-center gap-3 bg-white dark:bg-slate-800 hover:bg-{{ $sTheme['accent'] }}-50 dark:hover:bg-{{ $sTheme['accent'] }}-500/10 px-4 py-3.5 rounded-2xl border border-slate-200 dark:border-white/[0.06] hover:border-{{ $sTheme['accent'] }}-300 dark:hover:border-{{ $sTheme['accent'] }}-500/30 transition shadow-sm hover:shadow-md">
              <div class="w-11 h-11 rounded-xl bg-gradient-to-br {{ $sTheme['from'] }} {{ $sTheme['to'] }} text-white flex items-center justify-center shrink-0 shadow-md">
                <i class="bi {{ $sTheme['icon'] }}"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-slate-900 dark:text-white truncate group-hover:text-{{ $sTheme['accent'] }}-600">
                  {{ $s->h1 ?? $s->title }}
                </div>
                @if(!empty($s->source_meta['data_count']))
                  <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ $s->source_meta['data_count'] }} รายการ</div>
                @endif
              </div>
              <i class="bi bi-arrow-right text-slate-400 group-hover:text-{{ $sTheme['accent'] }}-500 group-hover:translate-x-1 transition shrink-0"></i>
            </a>
          @endforeach
        </div>
      </div>
    </section>
  @endif
@endif

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  FINAL CTA — gradient block with side-image collage              ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
@if(($page->cta_text && $page->cta_url) || in_array($page->type, ['location', 'category', 'combo', 'event']))
  <section class="bg-slate-50 dark:bg-slate-900/40">
    <div class="max-w-7xl mx-auto px-4 py-12 md:py-16">
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br {{ $theme['from'] }} {{ $theme['via'] }} {{ $theme['to'] }} p-8 md:p-14 text-white text-center shadow-2xl">
        <div class="absolute -top-20 -right-20 w-72 h-72 bg-white/15 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-16 -left-16 w-72 h-72 bg-white/15 rounded-full blur-3xl"></div>
        <div class="relative max-w-2xl mx-auto">
          <i class="bi {{ $theme['icon'] }} text-5xl mb-4 inline-block"></i>
          <h2 class="text-3xl md:text-4xl font-extrabold mb-4 tracking-tight">
            @if($isEventLanding)
              พร้อมดูรูปอีเวนต์ตอนนี้?
            @else
              พร้อมจองช่างภาพแล้วใช่ไหม?
            @endif
          </h2>
          <p class="text-white/90 max-w-xl mx-auto mb-7 text-base md:text-lg leading-relaxed">
            @if($isEventLanding)
              คลิกดูรูปทั้งหมดในอีเวนต์ — ระบบ AI ค้นหารูปตัวคุณได้ทันที
            @else
              เริ่มดูผลงานช่างภาพและจองได้ทันที — ปลอดภัยด้วยระบบ escrow และรีวิวจากลูกค้าจริง
            @endif
          </p>
          <a href="{{ $page->cta_url ?? ($isEventLanding && $sourceEvent ? url('/events/' . ($sourceEvent->slug ?: $sourceEvent->id)) : url('/events')) }}"
             class="inline-flex items-center gap-2 px-7 md:px-10 py-3.5 md:py-4 bg-white hover:bg-yellow-50 text-slate-900 font-bold rounded-xl shadow-2xl shadow-black/20 transition active:scale-95 text-base md:text-lg">
            <i class="bi bi-arrow-right-circle-fill"></i>
            <span>{{ $page->cta_text ?? ($isEventLanding ? 'ดูรูปทั้งหมด' : 'ดูอีเวนต์ทั้งหมด') }}</span>
          </a>
        </div>
      </div>
    </div>
  </section>
@endif

@endsection
