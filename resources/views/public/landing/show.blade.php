@extends('layouts.app')

@php
  // Theme palette — drives gradient + accent colour throughout the page.
  // The const map lives on the model so admin previews + page renders
  // share one source of truth.
  $theme  = $page->themeData();
  $accent = $theme['accent']; // e.g. 'rose', 'indigo', 'blue'
@endphp

{{-- Page-specific meta — overrides defaults from og-meta partial. --}}
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

  {{-- Schema.org JSON-LD — pre-built; just emit. --}}
  @if(!empty($schemaJson))
    <script type="application/ld+json">{!! json_encode($schemaJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
  @endif
@endpush

@section('content-full')

{{-- ╔════════════════════════════════════════════════════════════╗
     ║  HERO — full-bleed gradient with optional bg image          ║
     ╚════════════════════════════════════════════════════════════╝ --}}
<section class="relative overflow-hidden bg-gradient-to-br {{ $theme['from'] }} {{ $theme['via'] }} {{ $theme['to'] }} text-white">

  {{-- Background image overlay (when set). 30% black gradient over the
       photo so text stays legible no matter how bright the source. --}}
  @if($page->hero_image)
    <div class="absolute inset-0 bg-cover bg-center"
         style="background-image: url('{{ asset($page->hero_image) }}');"></div>
    <div class="absolute inset-0 bg-gradient-to-br from-black/60 via-black/40 to-black/60"></div>
  @endif

  {{-- Decorative blur dots — pure CSS, give the gradient texture. --}}
  <div class="absolute -top-20 -right-20 w-96 h-96 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="absolute -bottom-32 -left-20 w-[28rem] h-[28rem] bg-white/10 rounded-full blur-3xl pointer-events-none"></div>

  <div class="relative max-w-6xl mx-auto px-4 py-16 md:py-24 lg:py-28">

    {{-- Breadcrumb (visible — required for the BreadcrumbList schema) --}}
    <nav class="text-xs md:text-sm text-white/70 mb-4 flex items-center gap-1.5 flex-wrap" aria-label="Breadcrumb">
      <a href="{{ url('/') }}" class="hover:text-white transition">หน้าแรก</a>
      <i class="bi bi-chevron-right text-[10px]"></i>
      @php
        $sectionLabel = match($page->type) {
          'location'      => 'อีเวนต์ตามพื้นที่',
          'category'      => 'ประเภทช่างภาพ',
          'combo'         => 'ค้นหาช่างภาพ',
          'photographer'  => 'ช่างภาพ',
          'event_archive' => 'อีเวนต์ทั้งหมด',
          default         => 'หน้า',
        };
      @endphp
      <span class="hover:text-white">{{ $sectionLabel }}</span>
      <i class="bi bi-chevron-right text-[10px]"></i>
      <span class="text-white font-medium truncate">{{ $page->h1 ?? $page->title }}</span>
    </nav>

    {{-- Type badge with theme icon --}}
    <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/15 backdrop-blur-sm rounded-full text-xs font-bold mb-4">
      <i class="bi {{ $theme['icon'] }}"></i>
      <span>{{ $sectionLabel }}</span>
    </div>

    <h1 class="text-3xl md:text-5xl lg:text-6xl font-extrabold tracking-tight mb-4 leading-tight max-w-4xl">
      {{ $page->h1 ?? $page->title }}
    </h1>

    <p class="text-base md:text-lg text-white/90 max-w-3xl leading-relaxed mb-6">
      {{ $page->meta_description }}
    </p>

    {{-- Stats badges (when toggled on) --}}
    @if(($page->show_stats ?? true) && !empty($page->source_meta))
      @php $sm = $page->source_meta; @endphp
      <div class="flex flex-wrap gap-2 md:gap-3">
        @if(!empty($sm['data_count']))
          <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/15 backdrop-blur-sm rounded-xl">
            <i class="bi bi-collection text-xl"></i>
            <div>
              <div class="text-lg md:text-xl font-extrabold leading-none">{{ number_format($sm['data_count']) }}</div>
              <div class="text-[10px] uppercase tracking-wide text-white/70">รายการ</div>
            </div>
          </div>
        @endif
        @if(!empty($sm['photographer_count']))
          <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/15 backdrop-blur-sm rounded-xl">
            <i class="bi bi-person-badge text-xl"></i>
            <div>
              <div class="text-lg md:text-xl font-extrabold leading-none">{{ number_format($sm['photographer_count']) }}</div>
              <div class="text-[10px] uppercase tracking-wide text-white/70">ช่างภาพ</div>
            </div>
          </div>
        @endif
        <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/15 backdrop-blur-sm rounded-xl">
          <i class="bi bi-eye text-xl"></i>
          <div>
            <div class="text-lg md:text-xl font-extrabold leading-none">{{ number_format($page->view_count) }}</div>
            <div class="text-[10px] uppercase tracking-wide text-white/70">เข้าชม</div>
          </div>
        </div>
      </div>
    @endif

    {{-- Primary CTA --}}
    @if($page->cta_text && $page->cta_url)
      <div class="mt-7">
        <a href="{{ $page->cta_url }}"
           class="inline-flex items-center gap-2 px-6 py-3 bg-white hover:bg-yellow-100 text-slate-900 font-bold rounded-xl shadow-lg transition active:scale-95">
          <i class="bi bi-arrow-right-circle-fill"></i>
          <span>{{ $page->cta_text }}</span>
        </a>
      </div>
    @endif
  </div>
</section>

{{-- ╔════════════════════════════════════════════════════════════╗
     ║  BODY — markdown-rendered or HTML override                  ║
     ╚════════════════════════════════════════════════════════════╝ --}}
@if($page->body_html)
  <section class="max-w-4xl mx-auto px-4 py-10 md:py-12">
    <div class="prose prose-slate dark:prose-invert max-w-none text-base md:text-lg leading-relaxed">
      {!! nl2br(e($page->body_html)) !!}
    </div>
  </section>
@endif

{{-- ╔════════════════════════════════════════════════════════════╗
     ║  RELATED ITEMS GRID — events / photographers (toggleable)   ║
     ╚════════════════════════════════════════════════════════════╝ --}}
@if(($page->show_gallery ?? true) && $relatedItems && $relatedItems->count() > 0)
  <section class="max-w-7xl mx-auto px-4 py-10 md:py-12">
    <div class="flex items-center justify-between gap-3 mb-6 flex-wrap">
      <div>
        <h2 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white">
          @if(in_array($page->type, ['location', 'category', 'combo']))
            <i class="bi bi-images text-{{ $accent }}-500 mr-1"></i>
            อีเวนต์ที่เกี่ยวข้อง
          @elseif($page->type === 'photographer')
            <i class="bi bi-camera-fill text-{{ $accent }}-500 mr-1"></i>
            ผลงานของช่างภาพ
          @else
            <i class="bi bi-calendar-event text-{{ $accent }}-500 mr-1"></i>
            อีเวนต์ทั้งหมด
          @endif
          <span class="text-slate-400 font-normal text-base">({{ $relatedItems->count() }})</span>
        </h2>
        <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
          คลิกเพื่อดูรายละเอียดและจองช่างภาพ
        </p>
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4">
      @foreach($relatedItems as $item)
        <a href="{{ url('/events/' . ($item->slug ?: $item->id)) }}"
           class="group block bg-white dark:bg-slate-800 rounded-2xl overflow-hidden border border-slate-200 dark:border-white/[0.06] hover:shadow-xl hover:-translate-y-1 transition-all duration-200">
          <div class="aspect-[4/3] bg-slate-100 dark:bg-slate-700 overflow-hidden relative">
            @if($item->cover_image)
              <img src="{{ asset($item->cover_image) }}"
                   alt="{{ $item->name }}"
                   loading="lazy"
                   class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
            @else
              <div class="w-full h-full flex items-center justify-center text-slate-300 dark:text-slate-500">
                <i class="bi bi-camera text-4xl"></i>
              </div>
            @endif
            {{-- Hover overlay --}}
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
          </div>
          <div class="p-3 md:p-4">
            <div class="font-semibold text-sm md:text-base text-slate-900 dark:text-white line-clamp-1 group-hover:text-{{ $accent }}-600 transition">{{ $item->name }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-2">
              @if($item->shoot_date)
                <span class="inline-flex items-center gap-1">
                  <i class="bi bi-calendar-event"></i>
                  <span>{{ \Carbon\Carbon::parse($item->shoot_date)->format('d/m/Y') }}</span>
                </span>
              @endif
              @if(isset($item->photographer) && $item->photographer)
                <span class="ml-auto text-slate-400 truncate">{{ $item->photographer->display_name }}</span>
              @endif
            </div>
          </div>
        </a>
      @endforeach
    </div>
  </section>
@endif

{{-- ╔════════════════════════════════════════════════════════════╗
     ║  EXTRA SECTIONS — admin-defined custom blocks (FAQ etc.)    ║
     ╚════════════════════════════════════════════════════════════╝ --}}
@if(!empty($page->extra_sections) && is_array($page->extra_sections))
  <section class="max-w-4xl mx-auto px-4 py-10 md:py-12 space-y-6">
    @foreach($page->extra_sections as $section)
      @php
        $type  = $section['type']  ?? 'text';
        $title = $section['title'] ?? '';
        $body  = $section['body']  ?? '';
      @endphp
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/[0.06] p-5 md:p-7 shadow-sm">
        @if($title)
          <h3 class="text-lg md:text-xl font-bold mb-3 text-slate-900 dark:text-white flex items-center gap-2">
            @if($type === 'faq')
              <i class="bi bi-question-circle-fill text-{{ $accent }}-500"></i>
            @elseif($type === 'testimonial')
              <i class="bi bi-chat-quote-fill text-{{ $accent }}-500"></i>
            @else
              <i class="bi bi-stars text-{{ $accent }}-500"></i>
            @endif
            {{ $title }}
          </h3>
        @endif
        @if($body)
          <div class="prose prose-slate dark:prose-invert max-w-none text-sm md:text-base leading-relaxed">
            {!! nl2br(e($body)) !!}
          </div>
        @endif
      </div>
    @endforeach
  </section>
@endif

{{-- ╔════════════════════════════════════════════════════════════╗
     ║  AUTO FAQ — generated for high-intent pages                 ║
     ╚════════════════════════════════════════════════════════════╝ --}}
@if(($page->show_faq ?? false) && in_array($page->type, ['location', 'category', 'combo']))
  @php
    // Auto-generated FAQ from page metadata. Each Q+A is an inline
    // accordion item. Schema.org FAQPage rich-result is auto-emitted
    // (Google highlights these in SERP with collapsible cards).
    $faqs = [];
    $sm = $page->source_meta ?? [];

    if ($page->type === 'location' || $page->type === 'combo') {
        $faqs[] = [
            'q' => 'จองช่างภาพในพื้นที่นี้ใช้เวลาเท่าไหร่?',
            'a' => 'จองได้ทันทีออนไลน์ — เลือกช่างภาพ ส่งคำขอ และรอช่างภาพยืนยัน (ปกติ 24 ชม.)',
        ];
    }
    if (in_array($page->type, ['location', 'category', 'combo'])) {
        $faqs[] = [
            'q' => 'ราคาเริ่มต้นที่เท่าไหร่?',
            'a' => 'ราคาขึ้นอยู่กับช่างภาพและประเภทงาน — ดูจากหน้า event ของแต่ละงานได้เลย',
        ];
        $faqs[] = [
            'q' => 'ปลอดภัยไหม จะได้รูปจริงๆ ใช่ไหม?',
            'a' => 'ทุกการจองมีระบบ escrow — เงินจะปล่อยให้ช่างภาพหลังจากที่คุณได้รับรูปเรียบร้อยแล้ว',
        ];
    }
  @endphp

  @if(count($faqs) > 0)
    <section class="max-w-4xl mx-auto px-4 py-10 md:py-12">
      <h2 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
        <i class="bi bi-question-circle text-{{ $accent }}-500"></i>
        คำถามที่พบบ่อย
      </h2>
      <div class="space-y-3">
        @foreach($faqs as $faq)
          <details class="group bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-white/[0.06] overflow-hidden">
            <summary class="cursor-pointer px-5 py-4 flex items-center justify-between gap-3 list-none">
              <span class="font-semibold text-slate-900 dark:text-white">{{ $faq['q'] }}</span>
              <i class="bi bi-chevron-down text-slate-400 group-open:rotate-180 transition"></i>
            </summary>
            <div class="px-5 pb-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
              {{ $faq['a'] }}
            </div>
          </details>
        @endforeach
      </div>

      {{-- Schema.org FAQPage — built in PHP then json_encode'd to avoid
           Blade interpreting `@context` / `@type` keys as directives,
           which compiles into a broken view template. --}}
      @php
        $faqSchema = [
          '@context' => 'https://schema.org',
          '@type'    => 'FAQPage',
          'mainEntity' => array_map(fn($f) => [
            '@type' => 'Question',
            'name'  => $f['q'],
            'acceptedAnswer' => [
              '@type' => 'Answer',
              'text'  => $f['a'],
            ],
          ], $faqs),
        ];
      @endphp
      <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    </section>
  @endif
@endif

{{-- ╔════════════════════════════════════════════════════════════╗
     ║  INTERNAL LINKING — sibling pages of the same type          ║
     ╚════════════════════════════════════════════════════════════╝ --}}
@if(($page->show_related ?? true) && in_array($page->type, ['location', 'category', 'combo', 'event_archive']))
  @php
    $siblings = \App\Models\SeoLandingPage::published()
        ->ofType($page->type)
        ->where('id', '!=', $page->id)
        ->orderByDesc('view_count')
        ->limit(8)
        ->get();
  @endphp
  @if($siblings->count() > 0)
    <section class="max-w-7xl mx-auto px-4 py-10 md:py-12 border-t border-slate-200 dark:border-white/[0.06]">
      <h2 class="text-xl md:text-2xl font-bold text-slate-900 dark:text-white mb-5 flex items-center gap-2">
        <i class="bi bi-link-45deg text-{{ $accent }}-500"></i>
        หน้าที่เกี่ยวข้อง
      </h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 md:gap-3">
        @foreach($siblings as $s)
          @php $sTheme = $s->themeData(); @endphp
          <a href="{{ $s->url() }}"
             class="group block bg-white dark:bg-slate-800 hover:bg-{{ $sTheme['accent'] }}-50 dark:hover:bg-{{ $sTheme['accent'] }}-500/10 px-4 py-3 rounded-xl border border-slate-200 dark:border-white/[0.06] hover:border-{{ $sTheme['accent'] }}-300 dark:hover:border-{{ $sTheme['accent'] }}-500/30 transition">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-{{ $sTheme['accent'] }}-100 dark:bg-{{ $sTheme['accent'] }}-500/20 text-{{ $sTheme['accent'] }}-600 dark:text-{{ $sTheme['accent'] }}-400 flex items-center justify-center shrink-0">
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
              <i class="bi bi-arrow-right text-slate-400 group-hover:text-{{ $sTheme['accent'] }}-500 group-hover:translate-x-1 transition"></i>
            </div>
          </a>
        @endforeach
      </div>
    </section>
  @endif
@endif

{{-- ╔════════════════════════════════════════════════════════════╗
     ║  FINAL CTA — large gradient block to convert before scroll  ║
     ╚════════════════════════════════════════════════════════════╝ --}}
@if(($page->cta_text && $page->cta_url) || in_array($page->type, ['location', 'category', 'combo']))
  <section class="max-w-7xl mx-auto px-4 py-10 md:py-16">
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br {{ $theme['from'] }} {{ $theme['via'] }} {{ $theme['to'] }} p-8 md:p-12 text-white text-center shadow-2xl">
      <div class="absolute -top-12 -right-12 w-56 h-56 bg-white/10 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-12 -left-12 w-56 h-56 bg-white/10 rounded-full blur-3xl"></div>
      <div class="relative">
        <i class="bi {{ $theme['icon'] }} text-4xl mb-3 inline-block"></i>
        <h2 class="text-2xl md:text-3xl font-extrabold mb-3">
          พร้อมจองช่างภาพแล้วใช่ไหม?
        </h2>
        <p class="text-white/90 max-w-2xl mx-auto mb-6 text-sm md:text-base leading-relaxed">
          เริ่มดูผลงานช่างภาพและจองได้ทันที — ปลอดภัยด้วยระบบ escrow และการรีวิวจากลูกค้าจริง
        </p>
        <a href="{{ $page->cta_url ?? url('/events') }}"
           class="inline-flex items-center gap-2 px-6 md:px-8 py-3 md:py-4 bg-white hover:bg-yellow-100 text-slate-900 font-bold rounded-xl shadow-lg transition active:scale-95">
          <i class="bi bi-arrow-right-circle-fill"></i>
          <span>{{ $page->cta_text ?? 'ดูอีเวนต์ทั้งหมด' }}</span>
        </a>
      </div>
    </div>
  </section>
@endif

@endsection
