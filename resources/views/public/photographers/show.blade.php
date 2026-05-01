@extends('layouts.app')

@section('title', $profile->display_name . ' · ช่างภาพมืออาชีพ')

@php
    /* ────────────────────────────────────────────────────────
     * Pre-compute presentational data so the template stays clean
     * ──────────────────────────────────────────────────────── */
    $specialtyLabels  = \App\Models\PhotographerProfile::specialtyOptions();
    $specialtyList    = is_array($profile->specialties) ? $profile->specialties : [];
    $portfolioSamples = is_array($profile->portfolio_samples) ? $profile->portfolio_samples : [];
    $reviewCount      = $reviews->count();
    $totalReviewCount = \App\Models\Review::where('photographer_id', $profile->user_id)
        ->where('is_visible', 1)->count();
    $isPro    = $profile->tier === \App\Models\PhotographerProfile::TIER_PRO;
    $isSeller = $profile->tier === \App\Models\PhotographerProfile::TIER_SELLER;

    /** Hero background image — first event cover wins, then portfolio
     *  sample, then null (forces gradient fallback). The hero overlays
     *  ALL of these with a dark gradient so legibility is guaranteed. */
    $heroImage = null;
    if ($events->count() > 0 && method_exists($events->first(), 'getAttribute')) {
        $heroImage = $events->first()->cover_image_url ?? null;
    }
    if (!$heroImage && !empty($portfolioSamples[0]['url'] ?? null)) {
        $heroImage = $portfolioSamples[0]['url'];
    }

    /** Photographer's primary booking URL — chat is gated by feature flag */
    $chatEnabled = (string) \App\Models\AppSetting::get('feature_chat_enabled', '0') === '1';

    $displayName    = $profile->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
    $tierBadgeColor = $isPro ? '#f59e0b' : ($isSeller ? '#0ea5e9' : '#64748b');
    $tierBadgeLabel = $isPro ? 'PRO' : ($isSeller ? 'SELLER' : 'CREATOR');

    /**
     * Resolve the avatar to a full URL. Direct `Storage::disk('r2')->url()`
     * can throw when the R2 driver hits config issues, an unsupported key
     * shape, or a presign failure — and a thrown exception inside @section
     * crashes the whole render with a 500 even though the rest of the
     * page is fine. Wrap it so any failure degrades to the initials
     * fallback instead of taking down the page.
     */
    $avatarUrl = null;
    if (!empty($profile->avatar)) {
        try {
            $avatarUrl = app(\App\Services\Media\R2MediaService::class)->url($profile->avatar);
            if (!$avatarUrl) {
                // R2MediaService logs + returns '' on failure → treat as none.
                $avatarUrl = null;
            }
        } catch (\Throwable) {
            $avatarUrl = null;
        }
    }
@endphp

{{-- ════════════════════════════════════════════════════════════════
     1. CINEMATIC HERO — full-bleed cover photo + photographer info
     ════════════════════════════════════════════════════════════════ --}}
@section('hero')
<header class="relative isolate overflow-hidden"
        style="min-height:580px;
               @if($heroImage) background:linear-gradient(0deg, rgba(15,23,42,0.85), rgba(15,23,42,0.55)), url('{{ $heroImage }}') center/cover no-repeat;
               @else          background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#581c87 100%);
               @endif">

    {{-- Decorative blur orbs (gradient hero only — adds visual depth) --}}
    @if(!$heroImage)
        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
            <div class="absolute -top-40 -left-20 w-[28rem] h-[28rem] rounded-full opacity-40"
                 style="background:radial-gradient(closest-side,#6366f1,transparent);"></div>
            <div class="absolute -bottom-40 -right-20 w-[32rem] h-[32rem] rounded-full opacity-35"
                 style="background:radial-gradient(closest-side,#a855f7,transparent);"></div>
            <div class="absolute top-1/3 right-1/3 w-72 h-72 rounded-full opacity-25"
                 style="background:radial-gradient(closest-side,#06b6d4,transparent);"></div>
        </div>
    @endif

    {{-- Soft bottom gradient fade so the hero blends into the page bg.
         Light: page bg is white. Dark: layout uses slate-800 — match
         that exact shade so there's no visible seam between hero + body. --}}
    <div aria-hidden="true" class="absolute bottom-0 inset-x-0 h-32 bg-gradient-to-b from-transparent to-white dark:to-slate-800 pointer-events-none"></div>

    <div class="relative max-w-6xl mx-auto px-4 md:px-6 py-16 md:py-24 z-10">
        <div class="flex flex-col items-center text-center">
            {{-- Avatar (centered, prominent) --}}
            <div class="relative mb-5">
                <div class="absolute -inset-2 rounded-full blur-md opacity-70"
                     style="background:linear-gradient(135deg,#6366f1,#a855f7);"></div>
                <div class="relative w-28 h-28 md:w-32 md:h-32 rounded-full overflow-hidden ring-4 ring-white/30 shadow-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white text-4xl font-extrabold flex items-center justify-center">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}"
                             alt="" class="w-full h-full object-cover">
                    @else
                        {{ mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8') }}
                    @endif
                </div>
                @if($isPro)
                    <div class="absolute -bottom-1 -right-1 w-9 h-9 rounded-full flex items-center justify-center shadow-lg"
                         style="background:linear-gradient(135deg,#f59e0b,#d97706);border:3px solid #1e1b4b;"
                         title="Pro Verified">
                        <i class="bi bi-patch-check-fill text-white text-base"></i>
                    </div>
                @endif
            </div>

            {{-- Name + tier --}}
            <div class="flex flex-wrap items-center justify-center gap-2 mb-3">
                <h1 class="text-3xl md:text-5xl font-extrabold text-white tracking-tight drop-shadow">
                    {{ $displayName }}
                </h1>
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider text-white shadow"
                      style="background:{{ $tierBadgeColor }};">
                    {{ $isPro ? '⭐' : '' }} {{ $tierBadgeLabel }}
                </span>
            </div>

            {{-- Photographer code + location --}}
            <div class="flex flex-wrap items-center justify-center gap-3 text-sm text-white/80 mb-5">
                @if($profile->photographer_code)
                    <code class="px-2 py-0.5 rounded bg-white/15 backdrop-blur font-mono text-xs">
                        {{ $profile->photographer_code }}
                    </code>
                @endif
                @if($profile->province)
                    <span><i class="bi bi-geo-alt-fill"></i> {{ $profile->province->name_th }}</span>
                @endif
                @if($profile->years_experience)
                    <span><i class="bi bi-clock-history"></i> ประสบการณ์ {{ $profile->years_experience }} ปี</span>
                @endif
            </div>

            {{-- Bio (short) --}}
            @if($profile->bio)
                <p class="max-w-2xl text-white/90 text-base md:text-lg leading-relaxed mb-6">
                    {{ \Illuminate\Support\Str::limit($profile->bio, 180) }}
                </p>
            @endif

            {{-- Specialty chips --}}
            @if(!empty($specialtyList))
                <div class="flex flex-wrap items-center justify-center gap-2 mb-7">
                    @foreach(array_slice($specialtyList, 0, 6) as $sp)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold text-white backdrop-blur"
                              style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);">
                            {{ $specialtyLabels[$sp] ?? $sp }}
                        </span>
                    @endforeach
                </div>
            @endif

            {{-- Inline stats strip --}}
            <div class="flex flex-wrap items-center justify-center gap-4 md:gap-8 text-white/95 mb-8">
                <div class="text-center">
                    <div class="text-2xl md:text-3xl font-extrabold">{{ number_format($totalEvents) }}</div>
                    <div class="text-xs uppercase tracking-wider opacity-75">อีเวนต์</div>
                </div>
                <div class="w-px h-10 bg-white/20"></div>
                <div class="text-center">
                    <div class="text-2xl md:text-3xl font-extrabold">{{ number_format($totalViews) }}</div>
                    <div class="text-xs uppercase tracking-wider opacity-75">การเข้าชม</div>
                </div>
                @if($avgRating)
                    <div class="w-px h-10 bg-white/20"></div>
                    <div class="text-center">
                        <div class="text-2xl md:text-3xl font-extrabold flex items-center gap-1 justify-center">
                            <i class="bi bi-star-fill" style="color:#fbbf24;font-size:0.85em;"></i>
                            {{ number_format($avgRating, 1) }}
                        </div>
                        <div class="text-xs uppercase tracking-wider opacity-75">{{ number_format($totalReviewCount) }} รีวิว</div>
                    </div>
                @endif
            </div>

            {{-- Primary CTAs --}}
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="#portfolio"
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-base shadow-lg transition hover:-translate-y-0.5 ring-1 ring-inset ring-indigo-200/50"
                   style="background:rgb(255,255,255);color:#4338ca;">
                    <i class="bi bi-images"></i> ดูผลงาน
                </a>
                <a href="{{ route('bookings.create', $profile->user_id) }}"
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-base text-white border border-white/30 backdrop-blur-md hover:bg-white/15 transition"
                   style="background:rgba(255,255,255,0.10);">
                    <i class="bi bi-calendar-check"></i> จองคิวงาน
                </a>
                @if($chatEnabled && auth()->check() && auth()->id() !== $profile->user_id)
                    <form method="POST" action="{{ route('chat.start', $profile->user_id) }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-base text-white border border-white/30 backdrop-blur-md hover:bg-white/15 transition"
                                style="background:rgba(255,255,255,0.10);">
                            <i class="bi bi-chat-dots"></i> ส่งข้อความ
                        </button>
                    </form>
                @endif
            </div>

            {{-- Scroll indicator --}}
            <a href="#portfolio" class="mt-12 text-white/50 hover:text-white transition animate-bounce" aria-hidden="true">
                <i class="bi bi-chevron-double-down text-2xl"></i>
            </a>
        </div>
    </div>
</header>
@endsection

@section('content')

{{-- ════════════════════════════════════════════════════════════════
     2. ABOUT — bio + specialties as visual cards
     ════════════════════════════════════════════════════════════════ --}}
<section id="about" class="max-w-6xl mx-auto px-4 md:px-6 py-12">
    <div class="grid md:grid-cols-3 gap-8">
        <div class="md:col-span-2">
            <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight mb-4">
                เกี่ยวกับ{{ $displayName }}
            </h2>
            @if($profile->bio)
                <p class="text-slate-600 dark:text-slate-300 text-base leading-relaxed mb-5 whitespace-pre-line">{{ $profile->bio }}</p>
            @else
                <p class="text-slate-400 italic">ช่างภาพคนนี้ยังไม่ได้เขียนแนะนำตัว</p>
            @endif

            @if(!empty($specialtyList))
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mt-8 mb-3">ความเชี่ยวชาญ</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($specialtyList as $sp)
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium ring-1 ring-indigo-200/60 text-indigo-700 dark:text-indigo-300"
                              style="background:rgba(99,102,241,0.10);">
                            {{ $specialtyLabels[$sp] ?? $sp }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Quick-fact card --}}
        <aside class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm ring-1 ring-slate-100 dark:ring-slate-700 p-5">
            <h3 class="font-bold text-slate-900 dark:text-white mb-3">ข้อมูลด่วน</h3>
            <dl class="space-y-3 text-sm">
                @if($profile->years_experience)
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500"><i class="bi bi-clock-history mr-1.5"></i>ประสบการณ์</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ $profile->years_experience }} ปี</dd>
                    </div>
                @endif
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500"><i class="bi bi-images mr-1.5"></i>อีเวนต์ทำเสร็จ</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">{{ number_format($totalEvents) }}</dd>
                </div>
                @if($profile->province)
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500"><i class="bi bi-geo-alt mr-1.5"></i>พื้นที่</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white truncate ml-2 max-w-[10rem] text-right">{{ $profile->province->name_th }}</dd>
                    </div>
                @endif
                @if($avgRating)
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500"><i class="bi bi-star mr-1.5"></i>คะแนนเฉลี่ย</dt>
                        <dd class="font-semibold text-amber-600">
                            <i class="bi bi-star-fill mr-1"></i>{{ number_format($avgRating, 1) }}/5
                        </dd>
                    </div>
                @endif
                @if($categories->count() > 0)
                    <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-700">
                        <div class="text-slate-500 text-xs mb-2">หมวดที่ถ่าย</div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($categories as $cat)
                                <span class="px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                    {{ $cat->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </dl>
        </aside>
    </div>
</section>

{{-- ════════════════════════════════════════════════════════════════
     3. PORTFOLIO — visual gallery of recent + past work
     ════════════════════════════════════════════════════════════════ --}}
<section id="portfolio" class="max-w-6xl mx-auto px-4 md:px-6 py-12 scroll-mt-20">
    <div class="text-center mb-10">
        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 mb-3"
              style="background:rgba(99,102,241,0.12);">
            ✨ ผลงานเด่น
        </span>
        <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight bg-clip-text text-transparent"
            style="background-image:linear-gradient(135deg,#4f46e5,#a855f7,#ec4899);">
            ผลงานที่ผ่านมา
        </h2>
        <p class="text-slate-500 dark:text-slate-400 mt-2 max-w-xl mx-auto">
            ภาพถ่ายจากงานจริง · คุณภาพระดับมืออาชีพ · ส่งมอบทุกครั้ง
        </p>
    </div>

    @php
        // Combine portfolio archives + active events for the gallery so even
        // a brand-new photographer with no archives shows their current work.
        $galleryItems = $portfolio->concat($events->getCollection())->take(9);
    @endphp

    @if($galleryItems->count() > 0)
        @php
            // Layout strategy: a single hero tile (col-span-2 row-span-2)
            // only makes visual sense when there are at least 4 items so
            // the remaining 5 fill the grid completely. With 1-3 items
            // we use a uniform 3-column layout that center-feels balanced.
            $useFeatured = $galleryItems->count() >= 4;
            $gridClass   = $galleryItems->count() === 1
                ? 'grid grid-cols-1 max-w-2xl mx-auto'
                : ($galleryItems->count() === 2
                    ? 'grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-5 max-w-4xl mx-auto'
                    : 'grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-5');
        @endphp
        <div class="{{ $gridClass }}">
            @foreach($galleryItems as $i => $item)
                <a href="{{ route('events.show', $item->slug ?? $item->id) }}"
                   class="group relative overflow-hidden rounded-2xl shadow-sm hover:shadow-2xl transition-all duration-300
                          {{ ($useFeatured && $i === 0) ? 'col-span-2 row-span-2' : '' }}"
                   style="aspect-ratio: {{ ($useFeatured && $i === 0) ? '4/3' : ($galleryItems->count() === 1 ? '16/9' : '1/1') }};">
                    {{-- Cover --}}
                    @if($item->cover_image_url)
                        <img src="{{ $item->cover_image_url }}"
                             alt="{{ $item->name }}"
                             class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    @else
                        <div class="absolute inset-0 bg-gradient-to-br
                            @switch($i % 4)
                                @case(0) from-indigo-500 via-violet-500 to-pink-500 @break
                                @case(1) from-emerald-500 via-teal-500 to-cyan-500 @break
                                @case(2) from-amber-500 via-orange-500 to-rose-500 @break
                                @default from-purple-500 via-pink-500 to-rose-500
                            @endswitch
                        "></div>
                    @endif

                    {{-- Dark gradient for text legibility --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>

                    {{-- Hover ring effect --}}
                    <div class="absolute inset-0 ring-2 ring-transparent group-hover:ring-white/40 rounded-2xl transition"></div>

                    {{-- Content overlay --}}
                    <div class="absolute inset-x-0 bottom-0 p-4 md:p-5 text-white">
                        @if($item->category)
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/25 backdrop-blur mb-2">
                                {{ $item->category->name }}
                            </span>
                        @endif
                        <h3 class="font-bold text-base md:text-lg leading-tight line-clamp-2 mb-1
                                   {{ $i === 0 ? 'md:text-2xl' : '' }}">
                            {{ $item->name }}
                        </h3>
                        <div class="flex items-center justify-between text-xs opacity-90">
                            <span>
                                @if($item->shoot_date)
                                    <i class="bi bi-calendar3"></i>
                                    {{ \Carbon\Carbon::parse($item->shoot_date)->format('d M Y') }}
                                @endif
                            </span>
                            <span class="opacity-0 group-hover:opacity-100 transition">
                                ดูทั้งหมด <i class="bi bi-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/50 p-12 text-center">
            <i class="bi bi-camera text-5xl text-slate-300 block mb-3"></i>
            <p class="text-slate-500">ช่างภาพคนนี้ยังไม่มีผลงานสาธารณะ</p>
        </div>
    @endif
</section>

{{-- ════════════════════════════════════════════════════════════════
     4. ACTIVE EVENTS — currently selling
     ════════════════════════════════════════════════════════════════ --}}
@if($events->count() > 0)
    <section id="events" class="max-w-6xl mx-auto px-4 md:px-6 py-12 scroll-mt-20">
        <div class="flex items-end justify-between flex-wrap gap-3 mb-6">
            <div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    📸 อีเวนต์ที่กำลังเปิดขาย
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    เลือกซื้อภาพจากอีเวนต์ที่ยังเปิดจำหน่าย
                </p>
            </div>
            @if($events->total() > $events->count())
                <div class="text-sm text-slate-500">
                    {{ $events->total() }} อีเวนต์
                </div>
            @endif
        </div>

        {{-- Category filter chips --}}
        @php
            // "All" chip — link back to the profile root WITHOUT the
            // ?category query param. We can't always use route(...show.slug)
            // here: when the photographer has no slug (early-stage profile)
            // UrlGenerator throws "Missing parameter [slug]" and torches
            // the whole page. Fall back to the legacy numeric URL when
            // the slug isn't set.
            $allChipUrl = !empty($profile->slug)
                ? route('photographers.show.slug', ['slug' => $profile->slug])
                : route('photographers.show', ['id' => $profile->user_id]);
        @endphp
        @if($categories->count() > 0)
            <div class="flex flex-wrap gap-2 mb-6">
                <a href="{{ $allChipUrl }}"
                   class="px-3 py-1.5 rounded-full text-sm font-semibold transition
                          {{ !request('category') ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                    ทั้งหมด
                </a>
                @foreach($categories as $cat)
                    <a href="?category={{ $cat->id }}"
                       class="px-3 py-1.5 rounded-full text-sm font-semibold transition
                              {{ request('category') == $cat->id ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                        {{ $cat->name }}
                    </a>
                @endforeach
            </div>
        @endif

        @php
            // Same edge-case handling as the portfolio: max-w + responsive
            // grid degrades gracefully from 1 → 2 → 3 columns based on item
            // count. Without this, a single event card sits alone in a
            // 3-col grid with two empty columns to its right — looks broken.
            $eventsGridClass = $events->count() === 1
                ? 'grid grid-cols-1 max-w-md mx-auto gap-5'
                : ($events->count() === 2
                    ? 'grid grid-cols-1 sm:grid-cols-2 gap-5 max-w-3xl mx-auto'
                    : 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5');
        @endphp
        <div class="{{ $eventsGridClass }}">
            @foreach($events as $ev)
                <a href="{{ route('events.show', $ev->slug ?? $ev->id) }}"
                   class="block bg-white dark:bg-slate-800 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 ring-1 ring-slate-100 dark:ring-slate-700">
                    <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-700 dark:to-slate-800">
                        @if($ev->cover_image_url)
                            <img src="{{ $ev->cover_image_url }}"
                                 alt="" class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                        @else
                            <div class="absolute inset-0 flex items-center justify-center text-white text-5xl font-extrabold opacity-30">
                                {{ mb_substr($ev->name, 0, 2, 'UTF-8') }}
                            </div>
                        @endif
                        @if($ev->category)
                            <span class="absolute top-3 left-3 inline-block px-2 py-1 rounded-full text-xs font-bold text-white bg-black/40 backdrop-blur">
                                {{ $ev->category->name }}
                            </span>
                        @endif
                        @if($ev->is_free)
                            <span class="absolute top-3 right-3 inline-block px-2 py-1 rounded-full text-xs font-bold text-white bg-emerald-500">
                                ฟรี
                            </span>
                        @endif
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold text-slate-900 dark:text-white line-clamp-2 mb-1">{{ $ev->name }}</h3>
                        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 mt-2">
                            <span>
                                @if($ev->shoot_date)
                                    <i class="bi bi-calendar3"></i> {{ \Carbon\Carbon::parse($ev->shoot_date)->format('d M Y') }}
                                @endif
                            </span>
                            @if(!$ev->is_free && $ev->price_per_photo)
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                    ฿{{ number_format($ev->price_per_photo, 0) }}/ภาพ
                                </span>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @if($events->hasPages())
            <div class="mt-8">{{ $events->links() }}</div>
        @endif
    </section>
@endif

{{-- ════════════════════════════════════════════════════════════════
     5. REVIEWS — social proof
     ════════════════════════════════════════════════════════════════ --}}
@if($reviewCount > 0)
    <section id="reviews" class="max-w-6xl mx-auto px-4 md:px-6 py-12 scroll-mt-20">
        <div class="text-center mb-10">
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300 mb-3"
                  style="background:rgba(245,158,11,0.15);">
                <i class="bi bi-star-fill"></i> คำชมจากลูกค้า
            </span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                {{ number_format($totalReviewCount) }} รีวิว
                @if($avgRating)
                    <span class="text-amber-500">· {{ number_format($avgRating, 1) }} ★</span>
                @endif
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($reviews->take(6) as $review)
                @php
                    $reviewerName = $review->user
                        ? trim(($review->user->first_name ?? '') . ' ' . ($review->user->last_name ?? ''))
                        : 'ลูกค้า';
                    $reviewerName = $reviewerName ?: 'ลูกค้า';
                    $initials = collect(explode(' ', $reviewerName))
                        ->filter()->take(2)
                        ->map(fn($w) => mb_substr($w, 0, 1, 'UTF-8'))
                        ->implode('');
                @endphp
                <article class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700 hover:shadow-md transition">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="shrink-0 w-11 h-11 rounded-full flex items-center justify-center font-bold text-white text-sm"
                             style="background:linear-gradient(135deg,#6366f1,#a855f7);">
                            {{ $initials ?: 'U' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-slate-900 dark:text-white truncate">{{ $reviewerName }}</div>
                            <div class="flex items-center gap-1 text-xs text-slate-400 mt-0.5">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }}"
                                       style="color:{{ $i <= $review->rating ? '#f59e0b' : '#cbd5e1' }};"></i>
                                @endfor
                                <span class="ml-1">{{ $review->created_at->format('d M Y') }}</span>
                            </div>
                        </div>
                    </div>
                    @if($review->comment)
                        <p class="text-slate-600 dark:text-slate-300 text-sm leading-relaxed line-clamp-4">
                            "{{ $review->comment }}"
                        </p>
                    @endif
                    @if($review->event)
                        <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 text-xs text-slate-400 truncate">
                            <i class="bi bi-camera"></i> {{ $review->event->name }}
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif

{{-- ════════════════════════════════════════════════════════════════
     6. BOOKING CTA — closes the page with a clear next step
     ════════════════════════════════════════════════════════════════ --}}
<section class="max-w-6xl mx-auto px-4 md:px-6 py-12 mb-8">
    <div class="relative rounded-3xl overflow-hidden p-10 md:p-14 text-center shadow-2xl"
         style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#ec4899 100%);">
        {{-- Decorative orbs --}}
        <div aria-hidden="true" class="absolute inset-0 pointer-events-none">
            <div class="absolute -top-20 -left-20 w-80 h-80 rounded-full opacity-30"
                 style="background:radial-gradient(closest-side,#fff,transparent);"></div>
            <div class="absolute -bottom-20 -right-20 w-80 h-80 rounded-full opacity-25"
                 style="background:radial-gradient(closest-side,#fbbf24,transparent);"></div>
        </div>

        <div class="relative">
            <div class="inline-block w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white text-2xl mb-5">
                <i class="bi bi-calendar-check-fill"></i>
            </div>
            <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-3 tracking-tight leading-tight">
                พร้อมจองคิวงานของคุณ?
            </h2>
            <p class="text-base md:text-lg text-white/85 max-w-xl mx-auto mb-7">
                ตอบกลับภายใน 24 ชม. · ทำงานทั่วประเทศ · จ่ายเงินผ่าน PromptPay หลังตกลง
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('bookings.create', $profile->user_id) }}"
                   class="inline-flex items-center gap-2 px-7 py-3.5 rounded-xl font-bold text-base
                          ring-1 ring-inset ring-indigo-200/50 shadow-lg hover:-translate-y-0.5 transition"
                   style="background:rgb(255,255,255);color:#4338ca;">
                    <i class="bi bi-calendar-check"></i> จองคิวงาน
                    <i class="bi bi-arrow-right"></i>
                </a>
                @if($chatEnabled && auth()->check() && auth()->id() !== $profile->user_id)
                    <form method="POST" action="{{ route('chat.start', $profile->user_id) }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-7 py-3.5 rounded-xl font-semibold text-base text-white border border-white/30 bg-white/10 backdrop-blur-md hover:bg-white/20 transition">
                            <i class="bi bi-chat-dots"></i> ส่งข้อความ
                        </button>
                    </form>
                @elseif($profile->portfolio_url)
                    <a href="{{ $profile->portfolio_url }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 px-7 py-3.5 rounded-xl font-semibold text-base text-white border border-white/30 bg-white/10 backdrop-blur-md hover:bg-white/20 transition">
                        <i class="bi bi-link-45deg"></i> ดูพอร์ตโฟลิโอเพิ่ม
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>

@endsection
