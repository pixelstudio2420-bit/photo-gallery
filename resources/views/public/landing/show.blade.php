@extends('layouts.app')

{{-- Page-specific meta — overrides any defaults set in og-meta partial.
     The pSEO row holds the resolved title + description, so we just
     stamp them straight into the head. --}}
@section('title', $page->title)
@section('meta_description', $page->meta_description)

@push('head')
  <link rel="canonical" href="{{ $page->url() }}">
  <meta property="og:title" content="{{ $page->og_title ?? $page->title }}">
  <meta property="og:description" content="{{ $page->meta_description }}">
  <meta property="og:url" content="{{ $page->url() }}">
  <meta property="og:type" content="website">
  @if($page->og_image)
    <meta property="og:image" content="{{ asset($page->og_image) }}">
  @endif
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $page->og_title ?? $page->title }}">
  <meta name="twitter:description" content="{{ $page->meta_description }}">

  {{-- Schema.org JSON-LD — Google rewards rich structured data with
       enhanced SERP results (image carousels, breadcrumb trails, etc).
       The schema_json on the page row is pre-built by PSeoSchemaBuilder
       at generation time so render is just a json_encode. --}}
  @if(!empty($schemaJson))
    <script type="application/ld+json">{!! json_encode($schemaJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
  @endif
@endpush

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 md:py-12">

  {{-- ── Breadcrumb (visible) ────────────────────────────────────────
       Schema.org breadcrumb above ALSO needs visible breadcrumbs in
       the HTML for users + Google to validate; otherwise it's a
       structured-data warning in Search Console. --}}
  <nav class="text-xs text-slate-500 dark:text-slate-400 mb-4 flex items-center gap-1.5 flex-wrap" aria-label="Breadcrumb">
    <a href="{{ url('/') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">หน้าแรก</a>
    <i class="bi bi-chevron-right text-[9px]"></i>
    @php
      $sectionLabel = match($page->type) {
        'location'      => 'อีเวนต์ตามพื้นที่',
        'category'      => 'ประเภทช่างภาพ',
        'combo'         => 'ค้นหาช่างภาพ',
        'photographer'  => 'ช่างภาพ',
        'event_archive' => 'อีเวนต์',
        default         => 'หน้า',
      };
    @endphp
    <span>{{ $sectionLabel }}</span>
    <i class="bi bi-chevron-right text-[9px]"></i>
    <span class="text-slate-700 dark:text-slate-300 font-medium truncate">{{ $page->h1 ?? $page->title }}</span>
  </nav>

  {{-- ── Hero / H1 ──────────────────────────────────────────────── --}}
  <header class="mb-8">
    <h1 class="text-3xl md:text-4xl lg:text-5xl font-extrabold tracking-tight text-slate-900 dark:text-white mb-3 leading-tight">
      {{ $page->h1 ?? $page->title }}
    </h1>
    <p class="text-base md:text-lg text-slate-600 dark:text-slate-300 max-w-3xl leading-relaxed">
      {{ $page->meta_description }}
    </p>
  </header>

  {{-- ── Body content (markdown-rendered or pre-built HTML) ─────── --}}
  @if($page->body_html)
    <div class="prose prose-slate dark:prose-invert max-w-none mb-10">
      {!! nl2br(e($page->body_html)) !!}
    </div>
  @endif

  {{-- ── Related items (events / photographers grid) ────────────── --}}
  @if($relatedItems && $relatedItems->count() > 0)
    <section class="mb-10">
      <h2 class="text-xl md:text-2xl font-bold mb-5 text-slate-900 dark:text-white">
        @if(in_array($page->type, ['location', 'category', 'combo']))
          อีเวนต์ที่เกี่ยวข้อง <span class="text-slate-400 font-normal text-base">({{ $relatedItems->count() }})</span>
        @elseif($page->type === 'photographer')
          ผลงานของช่างภาพ <span class="text-slate-400 font-normal text-base">({{ $relatedItems->count() }})</span>
        @else
          อีเวนต์ทั้งหมด <span class="text-slate-400 font-normal text-base">({{ $relatedItems->count() }})</span>
        @endif
      </h2>

      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach($relatedItems as $item)
          <a href="{{ url('/events/' . ($item->slug ?: $item->id)) }}"
             class="group bg-white dark:bg-slate-800 rounded-2xl overflow-hidden border border-slate-200 dark:border-white/[0.06] hover:shadow-lg hover:-translate-y-0.5 transition-all">
            <div class="aspect-[4/3] bg-slate-100 dark:bg-slate-700 overflow-hidden">
              @if($item->cover_image)
                <img src="{{ asset($item->cover_image) }}"
                     alt="{{ $item->name }}"
                     loading="lazy"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
              @else
                <div class="w-full h-full flex items-center justify-center text-slate-300 dark:text-slate-500">
                  <i class="bi bi-image text-4xl"></i>
                </div>
              @endif
            </div>
            <div class="p-3">
              <div class="font-semibold text-sm text-slate-900 dark:text-white truncate">{{ $item->name }}</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1.5">
                @if($item->shoot_date)
                  <i class="bi bi-calendar-event"></i>
                  <span>{{ \Carbon\Carbon::parse($item->shoot_date)->format('d/m/Y') }}</span>
                @endif
                @if($item->photographer)
                  <span class="ml-auto text-slate-400">{{ $item->photographer->display_name }}</span>
                @endif
              </div>
            </div>
          </a>
        @endforeach
      </div>
    </section>
  @endif

  {{-- ── Internal-linking footer (related pSEO pages) ────────────
       Pulls 8 sibling pages of the same type so Google's crawler
       can discover the rest of the pSEO graph. Skipped on
       photographer + custom pages where it'd feel forced. --}}
  @if(in_array($page->type, ['location', 'category', 'combo', 'event_archive']))
    @php
      $siblings = \App\Models\SeoLandingPage::published()
          ->ofType($page->type)
          ->where('id', '!=', $page->id)
          ->orderByDesc('view_count')
          ->limit(8)
          ->get();
    @endphp
    @if($siblings->count() > 0)
      <section class="mb-10 pt-8 border-t border-slate-200 dark:border-white/[0.06]">
        <h2 class="text-lg font-bold mb-4 text-slate-900 dark:text-white">
          <i class="bi bi-link-45deg mr-1 text-indigo-500"></i>หน้าที่เกี่ยวข้อง
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
          @foreach($siblings as $s)
            <a href="{{ $s->url() }}"
               class="block px-3 py-2 rounded-lg bg-slate-50 dark:bg-white/[0.04] hover:bg-indigo-50 dark:hover:bg-indigo-500/15 text-xs text-slate-700 dark:text-slate-300 hover:text-indigo-700 dark:hover:text-indigo-400 transition truncate">
              <i class="bi bi-arrow-right-short text-indigo-500"></i>
              {{ $s->h1 ?? $s->title }}
            </a>
          @endforeach
        </div>
      </section>
    @endif
  @endif

</div>
@endsection
