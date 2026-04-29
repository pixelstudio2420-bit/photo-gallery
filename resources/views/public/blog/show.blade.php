@extends('layouts.app')

@section('title', $post->meta_title ?? $post->title)

@section('og-meta')
@include('layouts.partials.og-meta', [
  'ogTitle'       => ($post->meta_title ?? $post->title) . ' | ' . config('app.name'),
  'ogDescription' => $post->meta_description ?? $post->excerpt ?? Str::limit(strip_tags($post->content), 160),
  'ogImage'       => $post->og_image ? asset('storage/' . $post->og_image) : ($post->featured_image ? asset('storage/' . $post->featured_image) : ''),
  'ogType'        => 'article',
])
@endsection

@section('hero')
{{-- Article Hero / Featured Image Banner --}}
<div class="relative overflow-hidden" style="min-height: 360px;">
  @if($post->featured_image)
    <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}" class="w-full h-full object-cover absolute inset-0" style="min-height: 360px; max-height: 520px;">
  @else
    <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-900"></div>
  @endif
  <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/45 to-black/15"></div>

  <div class="relative max-w-4xl mx-auto px-4 py-14 sm:py-20 flex flex-col justify-end" style="min-height: 360px;">
    {{-- Breadcrumbs --}}
    <nav aria-label="Breadcrumb" class="mb-5">
      <ol class="flex items-center gap-2 text-xs text-white/60 flex-wrap" itemscope itemtype="https://schema.org/BreadcrumbList">
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
          <a href="{{ url('/') }}" itemprop="item" class="hover:text-white transition-colors">
            <span itemprop="name">หน้าแรก</span>
          </a>
          <meta itemprop="position" content="1">
        </li>
        <li class="text-white/40"><i class="bi bi-chevron-right text-[10px]"></i></li>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
          <a href="{{ route('blog.index') }}" itemprop="item" class="hover:text-white transition-colors">
            <span itemprop="name">บทความ</span>
          </a>
          <meta itemprop="position" content="2">
        </li>
        @if($post->category)
        <li class="text-white/40"><i class="bi bi-chevron-right text-[10px]"></i></li>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
          <a href="{{ route('blog.category', $post->category->slug) }}" itemprop="item" class="hover:text-white transition-colors">
            <span itemprop="name">{{ $post->category->name }}</span>
          </a>
          <meta itemprop="position" content="3">
        </li>
        @endif
      </ol>
    </nav>

    {{-- Category Badge --}}
    @if($post->category)
      <span class="inline-flex items-center self-start px-3.5 py-1.5 rounded-full text-xs font-semibold text-white bg-gradient-to-br from-indigo-500 to-violet-600 backdrop-blur-sm mb-4 shadow-lg shadow-indigo-500/30">
        @if($post->category->icon)<i class="{{ $post->category->icon }} mr-1"></i>@endif
        {{ $post->category->name }}
      </span>
    @endif

    {{-- Title --}}
    <h1 class="text-white font-extrabold text-2xl sm:text-3xl md:text-4xl lg:text-5xl leading-tight mb-5" style="line-height:1.2;">{{ $post->title }}</h1>

    {{-- Meta Line --}}
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-white/70 text-sm">
      @if($post->author)
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center text-white text-xs font-bold shadow-md">
          {{ mb_substr($post->author->first_name ?? 'A', 0, 1) }}
        </div>
        <span class="font-medium">{{ $post->author->full_name ?? $post->author->first_name ?? 'Admin' }}</span>
      </div>
      @endif
      @if($post->published_at)
        <span class="flex items-center gap-1.5"><i class="bi bi-calendar3"></i> {{ $post->published_at->locale('th')->translatedFormat('j F Y') }}</span>
      @endif
      @if($post->reading_time)
        <span class="flex items-center gap-1.5"><i class="bi bi-clock"></i> {{ $post->reading_time }} นาที</span>
      @endif
      @if($post->view_count > 0)
        <span class="flex items-center gap-1.5"><i class="bi bi-eye"></i> {{ number_format($post->view_count) }} ครั้ง</span>
      @endif
      @if($post->share_count > 0)
        <span class="flex items-center gap-1.5"><i class="bi bi-share"></i> {{ number_format($post->share_count) }}</span>
      @endif
    </div>
  </div>
  <div class="absolute bottom-0 left-0 right-0 h-px" style="background:linear-gradient(90deg,transparent,rgba(99,102,241,0.3),transparent);"></div>
</div>
@endsection

@section('content')

<div class="py-8 flex flex-col lg:flex-row gap-8">

  {{-- ============ Main Content Column ============ --}}
  <article class="flex-1 min-w-0" itemscope itemtype="https://schema.org/BlogPosting">
    <meta itemprop="headline" content="{{ $post->title }}">
    <meta itemprop="description" content="{{ $post->meta_description ?? $post->excerpt }}">
    @if($post->featured_image)<meta itemprop="image" content="{{ asset('storage/' . $post->featured_image) }}">@endif
    @if($post->published_at)<meta itemprop="datePublished" content="{{ $post->published_at->toIso8601String() }}">@endif
    @if($post->last_modified_at)<meta itemprop="dateModified" content="{{ $post->last_modified_at->toIso8601String() }}">@endif
    <div itemprop="author" itemscope itemtype="https://schema.org/Person">
      <meta itemprop="name" content="{{ $post->author->full_name ?? $post->author->first_name ?? 'Admin' }}">
    </div>
    <div itemprop="publisher" itemscope itemtype="https://schema.org/Organization">
      <meta itemprop="name" content="{{ config('app.name') }}">
    </div>
    <meta itemprop="mainEntityOfPage" content="{{ route('blog.show', $post->slug) }}">

    {{-- Table of Contents --}}
    @if($post->table_of_contents && count($post->table_of_contents) > 2)
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl mb-10 shadow-sm" x-data="{ open: true }">
      <button @click="open = !open" class="w-full flex items-center justify-between p-5 sm:p-6 text-left">
        <h2 class="font-bold text-base text-slate-800 dark:text-gray-100 flex items-center gap-2">
          <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-md shadow-indigo-500/25">
            <i class="bi bi-list-nested text-white text-sm"></i>
          </span>
          สารบัญ
        </h2>
        <i class="bi text-gray-400 dark:text-gray-500 transition-transform duration-200" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
      </button>
      <div x-show="open" x-collapse>
        <nav class="px-5 sm:px-6 pb-5 sm:pb-6 border-t border-gray-100 dark:border-white/10 pt-3">
          <ol class="space-y-1 text-sm">
            @foreach($post->table_of_contents as $i => $tocItem)
            <li style="padding-left: {{ ($tocItem['level'] - 2) * 1.25 }}rem;">
              <a href="#{{ $tocItem['id'] }}"
                 class="flex items-start gap-2 py-2 px-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"
                 onclick="document.getElementById('{{ $tocItem['id'] }}')?.scrollIntoView({behavior:'smooth', block:'start'})">
                <span class="text-indigo-500 dark:text-indigo-300 font-mono text-xs mt-0.5 shrink-0">{{ $i + 1 }}.</span>
                <span>{{ $tocItem['text'] }}</span>
              </a>
            </li>
            @endforeach
          </ol>
        </nav>
      </div>
    </div>
    @endif

    {{-- Article Content --}}
    <div class="blog-content prose prose-lg prose-gray dark:prose-invert max-w-none
                prose-headings:font-bold prose-headings:text-slate-800 dark:prose-headings:text-gray-100
                prose-h2:text-2xl prose-h2:mt-12 prose-h2:mb-5 prose-h2:pb-3 prose-h2:border-b prose-h2:border-gray-100 dark:prose-h2:border-white/10
                prose-h3:text-xl prose-h3:mt-10 prose-h3:mb-4
                prose-p:leading-[1.8] prose-p:text-gray-700 dark:prose-p:text-gray-300 prose-p:my-5
                prose-a:text-indigo-600 dark:prose-a:text-indigo-300 prose-a:font-medium prose-a:no-underline hover:prose-a:underline
                prose-strong:text-slate-800 dark:prose-strong:text-gray-100
                prose-img:rounded-2xl prose-img:shadow-xl prose-img:mx-auto prose-img:my-8
                prose-blockquote:border-l-4 prose-blockquote:border-indigo-400 dark:prose-blockquote:border-indigo-300 prose-blockquote:bg-indigo-50/60 dark:prose-blockquote:bg-indigo-500/10 prose-blockquote:rounded-r-xl prose-blockquote:py-2 prose-blockquote:px-4 prose-blockquote:not-italic prose-blockquote:text-slate-700 dark:prose-blockquote:text-gray-300
                prose-code:text-indigo-600 dark:prose-code:text-indigo-300 prose-code:bg-indigo-50 dark:prose-code:bg-indigo-500/10 prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded-md prose-code:text-sm prose-code:before:content-none prose-code:after:content-none
                prose-pre:bg-gray-900 dark:prose-pre:bg-black/50 prose-pre:rounded-xl prose-pre:shadow-lg prose-pre:border prose-pre:border-white/5
                prose-li:text-gray-700 dark:prose-li:text-gray-300 prose-li:my-1.5
                prose-hr:border-gray-200 dark:prose-hr:border-white/10"
         style="line-height:1.8;"
         itemprop="articleBody">
      {!! \App\Support\HtmlSanitizer::clean($post->content) !!}
    </div>

    {{-- Inline Affiliate CTAs (rendered within content via controller or here) --}}
    @if($post->is_affiliate_post && isset($ctaButtons) && $ctaButtons->count())
    <section class="mt-12 space-y-6">
      <h2 class="text-xl font-bold text-slate-800 dark:text-gray-100 flex items-center gap-2">
        <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center shadow-md shadow-rose-500/25">
          <i class="bi bi-bag-heart-fill text-white text-sm"></i>
        </span>
        สินค้าแนะนำ
      </h2>

      @foreach($ctaButtons->where('position', 'after_content') as $cta)
        @include('public.blog._affiliate-cta', ['cta' => $cta])
      @endforeach

      {{-- Multi-CTA Section for Affiliate Posts --}}
      @if($ctaButtons->where('position', 'after_content')->count() === 0)
        @foreach($ctaButtons->take(3) as $cta)
          @include('public.blog._affiliate-cta', ['cta' => $cta])
        @endforeach
      @endif

      {{-- Urgency & Countdown --}}
      <div class="bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-950/40 dark:to-orange-950/40 border-2 border-red-200 dark:border-red-400/30 rounded-2xl p-6 md:p-8 text-center shadow-lg" x-data="ctaCountdown()">
        <p class="text-red-600 dark:text-red-300 font-bold text-lg mb-3"><i class="bi bi-alarm-fill"></i> โปรโมชั่นมีเวลาจำกัด!</p>
        <div class="flex items-center justify-center gap-3 mb-5">
          <div class="bg-gradient-to-br from-red-600 to-rose-600 text-white rounded-xl px-4 py-3 text-center min-w-[72px] shadow-lg shadow-red-500/30">
            <span class="text-2xl font-bold" x-text="hours">00</span>
            <p class="text-xs opacity-80">ชั่วโมง</p>
          </div>
          <span class="text-red-400 dark:text-red-300 text-2xl font-bold">:</span>
          <div class="bg-gradient-to-br from-red-600 to-rose-600 text-white rounded-xl px-4 py-3 text-center min-w-[72px] shadow-lg shadow-red-500/30">
            <span class="text-2xl font-bold" x-text="minutes">00</span>
            <p class="text-xs opacity-80">นาที</p>
          </div>
          <span class="text-red-400 dark:text-red-300 text-2xl font-bold">:</span>
          <div class="bg-gradient-to-br from-red-600 to-rose-600 text-white rounded-xl px-4 py-3 text-center min-w-[72px] shadow-lg shadow-red-500/30">
            <span class="text-2xl font-bold" x-text="seconds">00</span>
            <p class="text-xs opacity-80">วินาที</p>
          </div>
        </div>
        <div class="flex items-center justify-center gap-4 text-xs text-gray-600 dark:text-gray-300 flex-wrap">
          <span class="flex items-center gap-1"><i class="bi bi-shield-check text-emerald-500 dark:text-emerald-400"></i> รับประกันคืนเงิน</span>
          <span class="flex items-center gap-1"><i class="bi bi-truck text-blue-500 dark:text-blue-400"></i> จัดส่งฟรี</span>
          <span class="flex items-center gap-1"><i class="bi bi-patch-check text-indigo-500 dark:text-indigo-400"></i> ของแท้ 100%</span>
        </div>
      </div>
    </section>
    @endif

    {{-- Tags --}}
    @if($post->tags && $post->tags->count())
    <div class="mt-10 pt-8 border-t border-gray-100 dark:border-white/10">
      <h3 class="text-sm font-bold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
        <i class="bi bi-tags text-indigo-500 dark:text-indigo-300"></i> แท็ก
      </h3>
      <div class="flex flex-wrap gap-2">
        @foreach($post->tags as $tag)
        <a href="{{ route('blog.tag', $tag->slug) }}"
           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-50 dark:bg-white/5 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-white/10 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-600 dark:hover:text-indigo-300 hover:border-indigo-200 dark:hover:border-indigo-400/30 hover:scale-105 transition-all">
          <i class="bi bi-hash"></i>{{ $tag->name }}
        </a>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Action Buttons: Share / Print / Save / Download --}}
    <div class="mt-10 pt-8 border-t border-gray-100 dark:border-white/10" x-data="shareButtons()">
      <h3 class="text-sm font-bold text-slate-800 dark:text-gray-100 mb-4 flex items-center gap-2">
        <i class="bi bi-share text-indigo-500 dark:text-indigo-300"></i> แชร์และบันทึก
      </h3>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {{-- Share (Blue gradient) --}}
        <a :href="fbUrl" target="_blank" rel="noopener"
           class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-br from-blue-500 to-cyan-600 shadow-lg shadow-blue-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-share-fill"></i> แชร์
        </a>
        {{-- Print (Emerald) --}}
        <button onclick="window.print()" type="button"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-printer-fill"></i> พิมพ์
        </button>
        {{-- Save (Amber) --}}
        <button @click="copyLink()" type="button"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-br from-amber-500 to-orange-600 shadow-lg shadow-amber-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-bookmark-star-fill'"></i>
          <span x-text="copied ? 'คัดลอกแล้ว' : 'บันทึก'"></span>
        </button>
        {{-- Read Later (Violet) --}}
        <a :href="lineUrl" target="_blank" rel="noopener"
           class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-br from-violet-500 to-purple-600 shadow-lg shadow-violet-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-clock-history"></i> อ่านต่อทีหลัง
        </a>
      </div>

      {{-- Socials sub-row --}}
      <div class="mt-4 flex items-center gap-2 flex-wrap">
        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">แพลตฟอร์ม:</span>
        <a :href="fbUrl" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-white bg-[#1877F2] hover:bg-[#166FE5] transition-colors shadow-sm">
          <i class="bi bi-facebook"></i> Facebook
        </a>
        <a :href="twUrl" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-white bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 dark:hover:bg-gray-600 transition-colors shadow-sm">
          <i class="bi bi-twitter-x"></i> X
        </a>
        <a :href="lineUrl" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-white bg-[#06C755] hover:bg-[#05B04C] transition-colors shadow-sm">
          <i class="bi bi-line"></i> Line
        </a>
      </div>
    </div>

    {{-- Author Box --}}
    @if($post->author)
    <div class="mt-10 pt-8 border-t border-gray-100 dark:border-white/10">
      <div class="bg-gradient-to-br from-gray-50 to-indigo-50/40 dark:from-slate-800 dark:to-indigo-950/30 rounded-2xl p-6 sm:p-8 flex flex-col sm:flex-row items-center sm:items-start gap-5 border border-gray-100 dark:border-white/10 shadow-sm">
        <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white text-3xl font-bold shrink-0 shadow-xl shadow-indigo-500/30">
          {{ mb_substr($post->author->first_name ?? 'A', 0, 1) }}
        </div>
        <div class="text-center sm:text-left flex-1">
          <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider mb-1">เขียนโดย</p>
          <h4 class="font-bold text-lg text-slate-800 dark:text-gray-100">{{ $post->author->full_name ?? $post->author->first_name ?? 'Admin' }}</h4>
          @if(isset($post->author->bio))
            <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">{{ $post->author->bio }}</p>
          @endif
          <a href="{{ route('blog.index', ['author' => $post->author_id]) }}" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-300 text-sm font-semibold mt-3 hover:underline">
            ดูบทความทั้งหมดของผู้เขียน <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>
    @endif

    {{-- Related Posts --}}
    @if(isset($relatedPosts) && $relatedPosts->count())
    <section class="mt-12 pt-10 border-t border-gray-100 dark:border-white/10">
      <h2 class="text-xl font-bold text-slate-800 dark:text-gray-100 mb-6 flex items-center gap-2">
        <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-md shadow-indigo-500/25">
          <i class="bi bi-journal-richtext text-white text-sm"></i>
        </span>
        บทความที่เกี่ยวข้อง
      </h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-6">
        @foreach($relatedPosts->take(4) as $relPost)
          <article class="group">
            <a href="{{ route('blog.show', $relPost->slug) }}" class="flex gap-4 p-4 bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
              <div class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-xl overflow-hidden shrink-0">
                @if($relPost->featured_image)
                  <img src="{{ asset('storage/' . $relPost->featured_image) }}" alt="{{ $relPost->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                @else
                  <div class="w-full h-full bg-gradient-to-br from-indigo-200 to-violet-200 dark:from-indigo-900/50 dark:to-violet-900/50 flex items-center justify-center">
                    <i class="bi bi-newspaper text-2xl text-indigo-300 dark:text-indigo-500"></i>
                  </div>
                @endif
              </div>
              <div class="flex-1 min-w-0 flex flex-col justify-center">
                @if($relPost->category)
                  <span class="inline-flex items-center self-start px-2 py-0.5 rounded-full text-[10px] font-semibold text-indigo-600 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-500/10 mb-1.5">
                    {{ $relPost->category->name }}
                  </span>
                @endif
                <h3 class="text-sm sm:text-base font-bold leading-snug line-clamp-2 text-slate-800 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors">{{ $relPost->title }}</h3>
                <div class="flex items-center gap-3 text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                  @if($relPost->published_at)<span>{{ $relPost->published_at->locale('th')->translatedFormat('j M Y') }}</span>@endif
                  @if($relPost->reading_time)<span><i class="bi bi-clock"></i> {{ $relPost->reading_time }} นาที</span>@endif
                </div>
              </div>
            </a>
          </article>
        @endforeach
      </div>
    </section>
    @endif

    {{-- Previous / Next Navigation --}}
    @if(isset($previousPost) || isset($nextPost))
    <nav class="mt-12 pt-10 border-t border-gray-100 dark:border-white/10">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @if(isset($previousPost) && $previousPost)
        <a href="{{ route('blog.show', $previousPost->slug) }}" class="group flex items-start gap-3 bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-5 hover:border-indigo-300 dark:hover:border-indigo-400/40 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
          <div class="w-11 h-11 rounded-xl bg-gray-50 dark:bg-white/5 group-hover:bg-gradient-to-br group-hover:from-indigo-500 group-hover:to-violet-600 flex items-center justify-center shrink-0 transition-all">
            <i class="bi bi-arrow-left text-gray-400 dark:text-gray-500 group-hover:text-white transition-colors"></i>
          </div>
          <div class="min-w-0">
            <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold mb-1">บทความก่อนหน้า</p>
            <h4 class="text-sm font-bold text-slate-800 dark:text-gray-100 line-clamp-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors">{{ $previousPost->title }}</h4>
          </div>
        </a>
        @else
        <div></div>
        @endif

        @if(isset($nextPost) && $nextPost)
        <a href="{{ route('blog.show', $nextPost->slug) }}" class="group flex items-start gap-3 bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-5 hover:border-indigo-300 dark:hover:border-indigo-400/40 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 text-right sm:flex-row-reverse">
          <div class="w-11 h-11 rounded-xl bg-gray-50 dark:bg-white/5 group-hover:bg-gradient-to-br group-hover:from-indigo-500 group-hover:to-violet-600 flex items-center justify-center shrink-0 transition-all">
            <i class="bi bi-arrow-right text-gray-400 dark:text-gray-500 group-hover:text-white transition-colors"></i>
          </div>
          <div class="min-w-0">
            <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold mb-1">บทความถัดไป</p>
            <h4 class="text-sm font-bold text-slate-800 dark:text-gray-100 line-clamp-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors">{{ $nextPost->title }}</h4>
          </div>
        </a>
        @endif
      </div>
    </nav>
    @endif
  </article>

  {{-- ============ Sidebar ============ --}}
  <div class="hidden lg:block w-80 xl:w-96 shrink-0">
    <div class="sticky top-20">
      @include('public.blog._sidebar')
    </div>
  </div>
</div>

{{-- ============ Sticky Bottom CTA (mobile, affiliate posts) ============ --}}
@if($post->is_affiliate_post && isset($ctaButtons) && $ctaButtons->count())
<div x-data="stickyBottomCta()" x-show="show && !dismissed" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
     class="fixed bottom-0 left-0 right-0 z-50 lg:hidden">
  <div class="bg-gradient-to-r from-indigo-700 via-violet-700 to-purple-700 shadow-2xl border-t border-white/10 px-4 py-3 safe-area-inset-bottom">
    <div class="flex items-center gap-3">
      <div class="flex-1 min-w-0">
        <p class="text-white font-bold text-sm truncate">{{ $ctaButtons->first()->label ?? 'ข้อเสนอพิเศษ' }}</p>
        @if($ctaButtons->first()->sub_label)
          <p class="text-white/60 text-xs truncate">{{ $ctaButtons->first()->sub_label }}</p>
        @endif
      </div>
      <a href="{{ $ctaButtons->first()->url ?? ($ctaButtons->first()->affiliateLink ? $ctaButtons->first()->affiliateLink->getCloakedUrl() : '#') }}"
         rel="nofollow noopener sponsored"
         target="_blank"
         class="shrink-0 px-5 py-2.5 bg-white text-indigo-700 font-bold text-sm rounded-xl shadow-lg hover:bg-gray-50 transition-colors"
         data-cta-id="{{ $ctaButtons->first()->id }}"
         onclick="trackCtaClick({{ $ctaButtons->first()->id }})">
        คลิกเลย <i class="bi bi-arrow-right"></i>
      </a>
      <button @click="dismissed = true" class="text-white/50 hover:text-white transition-colors shrink-0 p-1">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
  </div>
</div>
@endif

@endsection

@push('styles')
<style>
/* Blog content typography */
.blog-content h2 { scroll-margin-top: 80px; }
.blog-content h3 { scroll-margin-top: 80px; }
.blog-content h4 { scroll-margin-top: 80px; }

/* Blog card hover */
.blog-card { will-change: transform; transition: all 0.3s ease; }
.blog-card:hover { transform: translateY(-4px); box-shadow: 0 16px 32px -8px rgba(0,0,0,0.1); }

/* CTA animations */
@keyframes ctaShake {
  0%, 100% { transform: translateX(0); }
  10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); }
  20%, 40%, 60%, 80% { transform: translateX(2px); }
}
.cta-shake { animation: ctaShake 3s ease-in-out infinite; }

/* Safe area for mobile bottom bar */
.safe-area-inset-bottom { padding-bottom: max(0.75rem, env(safe-area-inset-bottom)); }
</style>
@endpush

@push('scripts')
<script>
function shareButtons() {
  const url = encodeURIComponent(window.location.href);
  const title = encodeURIComponent(@json($post->title));
  return {
    copied: false,
    fbUrl: `https://www.facebook.com/sharer/sharer.php?u=${url}`,
    twUrl: `https://twitter.com/intent/tweet?url=${url}&text=${title}`,
    lineUrl: `https://social-plugins.line.me/lineit/share?url=${url}`,
    copyLink() {
      navigator.clipboard.writeText(window.location.href).then(() => {
        this.copied = true;
        setTimeout(() => this.copied = false, 2000);
      });
    }
  };
}

function stickyBottomCta() {
  return {
    show: false,
    dismissed: false,
    init() {
      window.addEventListener('scroll', () => {
        const scrollPercent = window.scrollY / (document.documentElement.scrollHeight - window.innerHeight);
        this.show = scrollPercent > 0.3;
      });
    }
  };
}

function ctaCountdown() {
  return {
    hours: '00',
    minutes: '00',
    seconds: '00',
    init() {
      // Countdown to end of day (creates daily urgency)
      const update = () => {
        const now = new Date();
        const endOfDay = new Date(now);
        endOfDay.setHours(23, 59, 59, 999);
        const diff = endOfDay - now;
        const h = Math.floor(diff / (1000 * 60 * 60));
        const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((diff % (1000 * 60)) / 1000);
        this.hours = String(h).padStart(2, '0');
        this.minutes = String(m).padStart(2, '0');
        this.seconds = String(s).padStart(2, '0');
      };
      update();
      setInterval(update, 1000);
    }
  };
}

// CTA Tracking
function trackCtaImpression(ctaId) {
  fetch(`/api/blog/cta/${ctaId}/impression`, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' } }).catch(() => {});
}
function trackCtaClick(ctaId) {
  fetch(`/api/blog/cta/${ctaId}/click`, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' } }).catch(() => {});
}
</script>

{{-- JSON-LD Structured Data (built in PHP to avoid Blade directive conflicts with @type/@context/@id) --}}
@php
  $_ldSchema = [
    '@context'    => 'https://schema.org',
    '@type'       => $post->schema_type ?? 'BlogPosting',
    'headline'    => $post->title,
    'description' => $post->meta_description ?? $post->excerpt ?? \Str::limit(strip_tags($post->content), 160),
    'author'      => [
      '@type' => 'Person',
      'name'  => $post->author->full_name ?? $post->author->first_name ?? 'Admin',
    ],
    'publisher'   => [
      '@type' => 'Organization',
      'name'  => config('app.name'),
      'logo'  => [
        '@type' => 'ImageObject',
        'url'   => asset('images/logo.png'),
      ],
    ],
    'datePublished' => $post->published_at?->toIso8601String(),
    'dateModified'  => ($post->last_modified_at ?? $post->updated_at)?->toIso8601String(),
    'mainEntityOfPage' => [
      '@type' => 'WebPage',
      '@id'   => route('blog.show', $post->slug),
    ],
    'wordCount'       => $post->word_count ?? 0,
    'articleSection'  => $post->category->name ?? 'บทความ',
  ];
  if ($post->featured_image) {
    $_ldSchema['image'] = asset('storage/' . $post->featured_image);
  }
  if ($post->focus_keyword) {
    $_ldSchema['keywords'] = $post->focus_keyword;
  }
@endphp
<script type="application/ld+json">{!! json_encode($_ldSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
@endpush
