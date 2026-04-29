@extends('layouts.app')

@section('title', 'สินค้าดิจิทัล · Digital Products')

{{-- =======================================================================
     PRODUCTS INDEX — REDESIGN
     -------------------------------------------------------------------
     Design goals
       • Professional, e-commerce-grade visual hierarchy.
       • Crystal-clear Light / Dark mode with distinct surfaces and
         explicit text colours — never rely on default body text colour.
       • All interactive state (hover / active / focus) is visible in BOTH
         modes.
     Colour tokens used on this page
       Surface 0 (page bg):    bg-slate-50          dark:bg-slate-950
       Surface 1 (card):       bg-white             dark:bg-slate-900
       Surface 2 (hero/chip):  bg-white             dark:bg-slate-900/80
       Border:                 border-slate-200     dark:border-slate-800
       Text — primary:         text-slate-900       dark:text-slate-50
       Text — secondary:       text-slate-600       dark:text-slate-300
       Text — tertiary:        text-slate-500       dark:text-slate-400
       Accent primary:         indigo-600  / indigo-400
       Accent sale:            rose-600    / rose-400
       Accent featured:        amber-500   / amber-400
     ====================================================================== --}}

@section('hero')
{{-- ---------------------------------------------------------------------
     HERO
     --------------------------------------------------------------------- --}}
<section class="relative overflow-hidden bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
  {{-- Decorative backdrop: radial accents + subtle grid --}}
  <div class="absolute inset-0 pointer-events-none"
       style="background:
         radial-gradient(circle at 15% 20%, rgba(99,102,241,0.10) 0%, transparent 45%),
         radial-gradient(circle at 85% 30%, rgba(168,85,247,0.10) 0%, transparent 45%);">
  </div>
  <div class="absolute inset-0 pointer-events-none opacity-[0.35] dark:opacity-[0.15]"
       style="background-image: linear-gradient(rgba(100,116,139,0.15) 1px, transparent 1px),
                                linear-gradient(90deg, rgba(100,116,139,0.15) 1px, transparent 1px);
              background-size: 40px 40px;">
  </div>
  <div class="absolute -top-28 -right-28 w-96 h-96 rounded-full bg-indigo-400/20 dark:bg-indigo-500/15 blur-3xl pointer-events-none"></div>
  <div class="absolute -bottom-24 -left-24 w-[26rem] h-[26rem] rounded-full bg-fuchsia-400/15 dark:bg-purple-500/15 blur-3xl pointer-events-none"></div>

  <div class="relative max-w-6xl mx-auto px-4 py-14 md:py-20">
    {{-- Breadcrumb --}}
    <nav aria-label="Breadcrumb" class="mb-6 max-w-2xl mx-auto">
      <ol class="flex items-center justify-center gap-2 text-xs flex-wrap">
        <li>
          <a href="{{ url('/') }}" class="text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors">
            <i class="bi bi-house-door mr-1"></i>หน้าแรก
          </a>
        </li>
        <li class="text-slate-400 dark:text-slate-600"><i class="bi bi-chevron-right text-[10px]"></i></li>
        <li class="font-semibold text-slate-900 dark:text-slate-50">สินค้าดิจิทัล</li>
      </ol>
    </nav>

    {{-- Eyebrow + Title --}}
    <div class="text-center mb-10">
      <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold
                   bg-white border border-indigo-200 text-indigo-700 shadow-sm
                   dark:bg-slate-900 dark:border-indigo-500/30 dark:text-indigo-300 mb-5">
        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 dark:bg-indigo-400 animate-pulse"></span>
        Digital Store · สินค้าคุณภาพสูง
      </span>
      <h1 class="font-extrabold text-3xl sm:text-4xl md:text-5xl lg:text-6xl tracking-tight leading-[1.15] mb-4">
        <span class="text-slate-900 dark:text-slate-50">คลังสินค้า</span>
        <span class="bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600
                     dark:from-indigo-400 dark:via-violet-400 dark:to-fuchsia-400
                     bg-clip-text text-transparent">ดิจิทัล</span>
      </h1>
      <p class="text-sm sm:text-base text-slate-600 dark:text-slate-300 max-w-2xl mx-auto leading-relaxed">
        พรีเซ็ต Lightroom, โอเวอร์เลย์, เทมเพลต และเครื่องมือดิจิทัลคุณภาพสูง
        — ใช้ได้ทันที ซื้อครั้งเดียว ใช้ได้ตลอดไป
      </p>
    </div>

    {{-- Search --}}
    <div class="max-w-2xl mx-auto" x-data>
      <div class="relative group">
        <div class="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 via-violet-500 to-fuchsia-500
                    rounded-2xl blur-md opacity-25 group-hover:opacity-45 transition-opacity duration-300"></div>
        <div class="relative flex items-center bg-white dark:bg-slate-900
                    border border-slate-200 dark:border-slate-800
                    rounded-2xl overflow-hidden shadow-sm shadow-slate-900/5 dark:shadow-black/30
                    transition-all focus-within:border-indigo-400 dark:focus-within:border-indigo-500
                    focus-within:shadow-md focus-within:shadow-indigo-500/10">
          <span class="pl-5 pr-2 text-slate-400 dark:text-slate-500">
            <i class="bi bi-search text-lg"></i>
          </span>
          <input type="text"
                 id="hero-search-input"
                 class="flex-1 bg-transparent border-0 py-4 px-2 text-base
                        text-slate-900 dark:text-slate-50
                        placeholder-slate-400 dark:placeholder-slate-500
                        focus:outline-none focus:ring-0"
                 placeholder="ค้นหาพรีเซ็ต, เทมเพลต, โอเวอร์เลย์..."
                 value="{{ $search ?? '' }}"
                 autocomplete="off"
                 @input.debounce.350ms="$dispatch('hero-search', { q: $el.value })">
          <button type="button" id="hero-clear-btn"
                  class="{{ !empty($search) ? '' : 'hidden' }} mr-2 p-2 rounded-lg
                         text-slate-400 dark:text-slate-500
                         hover:text-rose-600 dark:hover:text-rose-400
                         hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors"
                  @click="$el.previousElementSibling.value=''; $el.previousElementSibling.dispatchEvent(new Event('input')); $dispatch('hero-search', {q:''})">
            <i class="bi bi-x-lg text-sm"></i>
          </button>
        </div>
      </div>
    </div>

    {{-- Type chips --}}
    <div class="flex items-center justify-center gap-2 mt-7 overflow-x-auto pb-1 -mx-1 px-1"
         id="hero-type-chips" style="scrollbar-width:none;">
      <button class="hero-type-chip active-chip shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-full
                     text-xs font-semibold transition-all cursor-pointer active:scale-95
                     bg-gradient-to-br from-indigo-600 to-violet-600 text-white
                     border border-transparent shadow-md shadow-indigo-500/30"
              data-type=""
              onclick="window.dispatchEvent(new CustomEvent('hero-type', {detail:{id:''}}))">
        <i class="bi bi-grid-3x3-gap"></i> ทั้งหมด
        <span class="opacity-80">({{ $totalCount }})</span>
      </button>
      @foreach($typeLabels as $key => $meta)
        @php $count = $typeCounts[$key] ?? 0; @endphp
        @if($count > 0)
        <button class="hero-type-chip shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-full
                       text-xs font-semibold transition-all cursor-pointer active:scale-95
                       bg-white dark:bg-slate-900
                       text-slate-700 dark:text-slate-300
                       border border-slate-200 dark:border-slate-800
                       hover:bg-indigo-50 dark:hover:bg-slate-800
                       hover:text-indigo-700 dark:hover:text-indigo-300
                       hover:border-indigo-200 dark:hover:border-indigo-500/40"
                data-type="{{ $key }}"
                onclick="window.dispatchEvent(new CustomEvent('hero-type', {detail:{id:'{{ $key }}'}}))">
          <i class="bi {{ $meta['icon'] }}"></i> {{ $meta['label'] }}
          <span class="opacity-70">({{ $count }})</span>
        </button>
        @endif
      @endforeach
    </div>

    {{-- Stats pills --}}
    <div class="flex items-center justify-center gap-2.5 mt-6 flex-wrap">
      <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full
                  bg-white dark:bg-slate-900
                  border border-slate-200 dark:border-slate-800">
        <span class="w-2 h-2 rounded-full bg-indigo-500 dark:bg-indigo-400 animate-pulse"></span>
        <span class="text-xs font-medium text-slate-600 dark:text-slate-400">
          ทั้งหมด <strong class="text-slate-900 dark:text-slate-50" id="stat-total">{{ $totalCount }}</strong> รายการ
        </span>
      </div>
      @if($onSaleCount > 0)
      <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full
                  bg-rose-50 dark:bg-rose-500/10
                  border border-rose-200 dark:border-rose-500/30">
        <i class="bi bi-tag-fill text-rose-600 dark:text-rose-400 text-xs"></i>
        <span class="text-xs font-semibold text-rose-700 dark:text-rose-300">
          <strong>{{ $onSaleCount }}</strong> กำลังลดราคา
        </span>
      </div>
      @endif
      <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full
                  bg-emerald-50 dark:bg-emerald-500/10
                  border border-emerald-200 dark:border-emerald-500/30">
        <i class="bi bi-infinity text-emerald-600 dark:text-emerald-400 text-xs"></i>
        <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">ใช้ได้ตลอดชีพ</span>
      </div>
      <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full
                  bg-amber-50 dark:bg-amber-500/10
                  border border-amber-200 dark:border-amber-500/30">
        <i class="bi bi-shield-fill-check text-amber-600 dark:text-amber-400 text-xs"></i>
        <span class="text-xs font-semibold text-amber-700 dark:text-amber-300">ลิขสิทธิ์แท้ 100%</span>
      </div>
    </div>
  </div>
</section>
@endsection

@section('content')
{{-- Page surface: slate-50 in light, slate-950 in dark --}}
<div class="-mx-4 md:-mx-6 lg:-mx-8 bg-slate-50 dark:bg-slate-950">
  <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 py-8 md:py-10">

    {{-- ═══════════════════════════════════════════════════════════════
         FEATURED STRIP
         ═══════════════════════════════════════════════════════════════ --}}
    @if($featured->count() > 0 && empty($search) && (empty($type) || $type === 'all'))
    <section class="mb-10">
      <div class="rounded-3xl bg-white dark:bg-slate-900
                  border border-slate-200 dark:border-slate-800
                  shadow-sm shadow-slate-900/5 dark:shadow-black/20 p-5 md:p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-base md:text-lg font-bold text-slate-900 dark:text-slate-50 flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl
                         bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-md shadow-amber-500/30">
              <i class="bi bi-star-fill text-sm"></i>
            </span>
            แนะนำ · Featured
          </h2>
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full
                       bg-amber-50 dark:bg-amber-500/10
                       border border-amber-200 dark:border-amber-500/30
                       text-xs font-semibold text-amber-700 dark:text-amber-300">
            <i class="bi bi-lightning-charge-fill"></i> {{ $featured->count() }} รายการเด็ด
          </span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 md:gap-4">
          @foreach($featured as $f)
          <a href="{{ route('products.show', $f->slug) }}"
             class="group relative rounded-2xl overflow-hidden
                    bg-slate-100 dark:bg-slate-800
                    border border-slate-200 dark:border-slate-700
                    aspect-square block transition-all duration-300
                    hover:shadow-xl hover:shadow-indigo-500/20 hover:-translate-y-1 hover:border-indigo-300 dark:hover:border-indigo-500/50">
            @if($f->cover_image_url)
              <img src="{{ $f->cover_image_url }}" alt="{{ $f->name }}"
                   class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
            @else
              <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-400 to-purple-500">
                <i class="bi bi-box-seam text-white text-3xl opacity-60"></i>
              </div>
            @endif
            {{-- Gradient overlay (readable caption) --}}
            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent"></div>
            <div class="absolute bottom-0 left-0 right-0 p-3">
              <div class="text-white text-xs font-semibold line-clamp-1 mb-0.5 drop-shadow">{{ $f->name }}</div>
              <div class="text-white text-[13px] font-bold drop-shadow flex items-center gap-1">
                @if($f->sale_price)
                  <span class="text-rose-300">{{ number_format($f->sale_price, 0) }} ฿</span>
                  <span class="text-white/60 text-[10px] line-through font-medium">{{ number_format($f->price, 0) }}</span>
                @else
                  {{ number_format($f->price, 0) }} ฿
                @endif
              </div>
            </div>
            @if($f->sale_price)
              <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full
                           bg-rose-500 text-white text-[10px] font-bold shadow-lg shadow-rose-500/40">SALE</span>
            @endif
          </a>
          @endforeach
        </div>
      </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         MAIN GRID (Alpine.js realtime filter)
         ═══════════════════════════════════════════════════════════════ --}}
    <div x-data="productSearch()" x-init="init()"
         @hero-search.window="query = $event.detail.q; fetchProducts()"
         @hero-type.window="type = $event.detail.id; updateTypeChips(); fetchProducts()">

      {{-- Section header --}}
      <div class="flex items-end justify-between mb-4 gap-4 flex-wrap">
        <div>
          <h2 class="text-xl md:text-2xl font-bold text-slate-900 dark:text-slate-50 flex items-center gap-2">
            <i class="bi bi-collection text-indigo-600 dark:text-indigo-400"></i>
            สินค้าทั้งหมด
          </h2>
          <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-0.5" x-show="!loading">
            <span x-text="resultText"></span>
          </p>
        </div>
      </div>

      {{-- Toolbar: sticky filter/sort row --}}
      <div class="sticky top-0 z-30 -mx-4 md:mx-0 mb-6">
        <div class="mx-4 md:mx-0 rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur
                    border border-slate-200 dark:border-slate-800
                    shadow-sm shadow-slate-900/5 dark:shadow-black/20
                    p-3 md:p-4">
          <div class="flex flex-wrap items-center gap-2 md:gap-3">
            {{-- Sort --}}
            <div class="relative">
              <select x-model="sort" @change="fetchProducts()"
                      class="appearance-none pl-9 pr-8 py-2.5 rounded-xl text-xs font-semibold cursor-pointer
                             bg-white dark:bg-slate-800
                             border border-slate-200 dark:border-slate-700
                             text-slate-700 dark:text-slate-200
                             hover:border-indigo-300 dark:hover:border-indigo-500/40
                             focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                <option value="latest">ล่าสุด</option>
                <option value="featured">แนะนำก่อน</option>
                <option value="best_seller">ขายดี</option>
                <option value="price_low">ราคาต่ำ → สูง</option>
                <option value="price_high">ราคาสูง → ต่ำ</option>
              </select>
              <i class="bi bi-sort-down absolute left-3 top-1/2 -translate-y-1/2 text-indigo-500 dark:text-indigo-400 pointer-events-none"></i>
              <i class="bi bi-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 text-xs pointer-events-none"></i>
            </div>

            {{-- On sale toggle --}}
            <label class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-semibold cursor-pointer
                          bg-white dark:bg-slate-800
                          border border-slate-200 dark:border-slate-700
                          text-slate-700 dark:text-slate-200
                          hover:border-rose-300 dark:hover:border-rose-500/40 transition-colors">
              <input type="checkbox" x-model="onSale" @change="fetchProducts()"
                     class="w-4 h-4 rounded border-slate-300 dark:border-slate-600
                            text-rose-500 focus:ring-rose-400 bg-white dark:bg-slate-900">
              <i class="bi bi-tag-fill text-rose-500 dark:text-rose-400"></i> เฉพาะลดราคา
            </label>

            {{-- Clear filters --}}
            <template x-if="hasActiveFilters">
              <button @click="clearAll()"
                      class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition active:scale-95
                             bg-rose-50 dark:bg-rose-500/10
                             border border-rose-200 dark:border-rose-500/30
                             text-rose-700 dark:text-rose-300
                             hover:bg-rose-100 dark:hover:bg-rose-500/20">
                <i class="bi bi-x-circle"></i> ล้างตัวกรอง
              </button>
            </template>

            {{-- Active query chip --}}
            <template x-if="query">
              <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium
                           bg-indigo-50 dark:bg-indigo-500/10
                           border border-indigo-200 dark:border-indigo-500/30
                           text-indigo-700 dark:text-indigo-300">
                <i class="bi bi-search"></i>
                "<span x-text="query"></span>"
                <button @click="query=''; clearHeroSearch(); fetchProducts()"
                        class="ml-1 w-4 h-4 inline-flex items-center justify-center rounded-full
                               hover:bg-indigo-200 dark:hover:bg-indigo-500/30 transition">
                  <i class="bi bi-x text-xs"></i>
                </button>
              </span>
            </template>

            {{-- Result summary (desktop only) --}}
            <div class="ml-auto hidden md:inline-flex items-center gap-2 text-xs font-medium
                        text-slate-500 dark:text-slate-400" x-show="!loading">
              <i class="bi bi-grid-3x3 text-indigo-500 dark:text-indigo-400"></i>
              <span x-text="resultText"></span>
            </div>
          </div>
        </div>
      </div>

      {{-- Loading state (skeleton grid) --}}
      <div x-show="loading" x-transition.opacity
           class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        @for($i = 0; $i < 8; $i++)
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden">
          <div class="aspect-[4/3] bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
          <div class="p-4 space-y-3">
            <div class="h-4 w-3/4 rounded bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
            <div class="h-3 w-full rounded bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
            <div class="h-3 w-2/3 rounded bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-end justify-between">
              <div class="h-5 w-16 rounded bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
              <div class="h-9 w-9 rounded-xl bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
            </div>
          </div>
        </div>
        @endfor
      </div>

      {{-- Products grid --}}
      <div x-show="!loading" x-transition.opacity>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5" id="products-grid">
          @include('public.products._grid', ['products' => $products, 'typeLabels' => $typeLabels])
        </div>

        {{-- Empty state --}}
        @if($products->count() === 0)
        <div class="text-center py-20 px-6 rounded-3xl mt-4
                    bg-white dark:bg-slate-900
                    border border-slate-200 dark:border-slate-800
                    shadow-sm shadow-slate-900/5 dark:shadow-black/20">
          <div class="relative inline-flex items-center justify-center w-28 h-28 mb-6">
            <div class="absolute inset-0 rounded-full bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-500/20 dark:to-violet-500/20 blur-lg"></div>
            <div class="relative inline-flex items-center justify-center w-24 h-24 rounded-full
                        bg-gradient-to-br from-slate-100 to-slate-50
                        dark:from-slate-800 dark:to-slate-900
                        border border-slate-200 dark:border-slate-700 shadow-inner">
              <i class="bi bi-search text-5xl text-slate-300 dark:text-slate-600"></i>
            </div>
          </div>
          <p class="text-slate-900 dark:text-slate-50 font-bold mb-1.5 text-xl">ไม่พบสินค้าที่คุณค้นหา</p>
          <p class="text-slate-500 dark:text-slate-400 text-sm mb-6 max-w-md mx-auto">
            ลองใช้คำค้นหาอื่น ลบตัวกรองบางอย่าง หรือเลือกหมวดหมู่อื่นเพื่อดูสินค้าทั้งหมด
          </p>
          <button type="button" @click="clearAll()"
                  class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-sm text-white
                         bg-gradient-to-br from-indigo-600 to-violet-600
                         shadow-lg shadow-indigo-500/30
                         hover:shadow-xl hover:shadow-indigo-500/40 hover:-translate-y-0.5
                         transition-all duration-200 active:scale-95">
            <i class="bi bi-arrow-clockwise"></i> ล้างตัวกรองทั้งหมด
          </button>
        </div>
        @endif

        {{-- Pagination --}}
        <div id="products-pagination" class="mt-12">
          @include('public.products._pagination', ['products' => $products])
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       BENEFITS BAR
       ═══════════════════════════════════════════════════════════════ --}}
  <section class="bg-white dark:bg-slate-900 border-y border-slate-200 dark:border-slate-800 py-12">
    <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8">
      <div class="text-center mb-8">
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full
                     bg-indigo-50 dark:bg-indigo-500/10
                     border border-indigo-200 dark:border-indigo-500/30
                     text-xs font-semibold text-indigo-700 dark:text-indigo-300">
          <i class="bi bi-patch-check-fill"></i> ทำไมต้องเลือกเรา
        </span>
        <h3 class="mt-3 text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-slate-50">
          ประสบการณ์ช้อปปิ้งดิจิทัลที่ <span class="text-indigo-600 dark:text-indigo-400">ครบ ถ้วน ปลอดภัย</span>
        </h3>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 md:gap-5">
        @foreach([
          ['icon' => 'bi-lightning-charge-fill', 'grad' => 'from-emerald-500 to-teal-500',  'title' => 'ดาวน์โหลดทันที',   'sub' => 'ได้ไฟล์ทันทีหลังชำระเงิน ไม่มีรอ'],
          ['icon' => 'bi-shield-fill-check',     'grad' => 'from-blue-500 to-indigo-500',   'title' => 'ลิขสิทธิ์แท้ 100%', 'sub' => 'ซื้อจากผู้สร้างโดยตรง ใช้งานเชิงพาณิชย์ได้'],
          ['icon' => 'bi-infinity',              'grad' => 'from-violet-500 to-fuchsia-500','title' => 'ใช้ได้ตลอดชีพ',      'sub' => 'ซื้อครั้งเดียว ไม่มีค่ารายเดือน'],
          ['icon' => 'bi-headset',               'grad' => 'from-amber-500 to-orange-500',  'title' => 'ซัพพอร์ต 24/7',       'sub' => 'ทีมงานพร้อมตอบทุกคำถาม'],
        ] as $b)
        <div class="group rounded-2xl p-5
                    bg-slate-50 dark:bg-slate-950
                    border border-slate-200 dark:border-slate-800
                    hover:border-indigo-200 dark:hover:border-indigo-500/40
                    hover:shadow-lg hover:shadow-indigo-500/10
                    transition-all duration-200">
          <div class="flex items-start gap-3">
            <div class="w-12 h-12 flex-shrink-0 rounded-xl bg-gradient-to-br {{ $b['grad'] }} text-white
                        flex items-center justify-center shadow-md group-hover:scale-110 transition-transform duration-300">
              <i class="bi {{ $b['icon'] }} text-xl"></i>
            </div>
            <div>
              <h4 class="font-bold text-sm text-slate-900 dark:text-slate-50 mb-1">{{ $b['title'] }}</h4>
              <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">{{ $b['sub'] }}</p>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </section>
</div>

@push('styles')
<style>
  /* Hide scrollbar on type chip row */
  #hero-type-chips::-webkit-scrollbar { width: 0; height: 0; }
  #hero-type-chips { scrollbar-width: none; }

  /* Card fade-in cascade */
  .product-card-wrap {
    animation: productCardFadeIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
  }
  .product-card-wrap:nth-child(1)  { animation-delay: 0.02s; }
  .product-card-wrap:nth-child(2)  { animation-delay: 0.06s; }
  .product-card-wrap:nth-child(3)  { animation-delay: 0.10s; }
  .product-card-wrap:nth-child(4)  { animation-delay: 0.14s; }
  .product-card-wrap:nth-child(5)  { animation-delay: 0.18s; }
  .product-card-wrap:nth-child(6)  { animation-delay: 0.22s; }
  .product-card-wrap:nth-child(7)  { animation-delay: 0.26s; }
  .product-card-wrap:nth-child(8)  { animation-delay: 0.30s; }
  .product-card-wrap:nth-child(9)  { animation-delay: 0.34s; }
  .product-card-wrap:nth-child(10) { animation-delay: 0.38s; }
  .product-card-wrap:nth-child(11) { animation-delay: 0.42s; }
  .product-card-wrap:nth-child(12) { animation-delay: 0.46s; }
  @keyframes productCardFadeIn {
    from { opacity: 0; transform: translateY(14px) scale(0.98); }
    to   { opacity: 1; transform: translateY(0)    scale(1); }
  }

  /* ── Pagination — Tailwind-like, dark/light aware ─────────────── */
  .pagination-tw .pagination {
    display: inline-flex; align-items: center; gap: 6px;
    list-style: none; padding: 0; margin: 0;
  }
  .pagination-tw .page-item .page-link {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 40px; height: 40px; padding: 0 14px;
    border-radius: 12px;
    border: 1px solid rgb(226 232 240); /* slate-200 */
    background: #fff;
    color: rgb(71 85 105);               /* slate-600 */
    font-size: 14px; font-weight: 600; text-decoration: none;
    transition: all 0.15s;
  }
  .pagination-tw .page-item .page-link:hover {
    background: rgb(238 242 255);        /* indigo-50 */
    color: rgb(79 70 229);               /* indigo-600 */
    border-color: rgb(199 210 254);      /* indigo-200 */
  }
  .pagination-tw .page-item.active .page-link {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #fff; border-color: transparent;
    box-shadow: 0 6px 16px rgba(99,102,241,0.35);
  }
  .pagination-tw .page-item.disabled .page-link {
    opacity: 0.45; cursor: not-allowed;
  }
  .dark .pagination-tw .page-item .page-link {
    background: rgb(15 23 42);           /* slate-900 */
    border-color: rgb(30 41 59);         /* slate-800 */
    color: rgb(203 213 225);             /* slate-300 */
  }
  .dark .pagination-tw .page-item .page-link:hover {
    background: rgb(30 41 59);
    border-color: rgb(99 102 241 / 0.4);
    color: rgb(165 180 252);             /* indigo-300 */
  }
  .dark .pagination-tw .page-item.active .page-link {
    background: linear-gradient(135deg, #6366f1, #a855f7);
    color: #fff; border-color: transparent;
    box-shadow: 0 6px 20px rgba(99,102,241,0.45);
  }
</style>
@endpush

@push('scripts')
<script>
function productSearch() {
  return {
    query:   {!! json_encode($search ?? '') !!},
    type:    {!! json_encode($type ?: '') !!},
    sort:    {!! json_encode($sort ?: 'latest') !!},
    onSale:  {{ request()->boolean('on_sale') ? 'true' : 'false' }},
    loading: false,
    total:   {{ $products->total() }},
    showing: {{ $products->count() }},

    get hasActiveFilters() {
      return this.query !== '' || (this.type !== '' && this.type !== 'all') || this.onSale || this.sort !== 'latest';
    },

    get resultText() {
      if (this.total === 0) return 'ไม่พบสินค้า';
      if (this.showing === this.total) return `พบ ${this.total.toLocaleString()} รายการ`;
      return `แสดง ${this.showing.toLocaleString()} จาก ${this.total.toLocaleString()} รายการ`;
    },

    init() {
      const heroInput = document.getElementById('hero-search-input');
      const clearBtn  = document.getElementById('hero-clear-btn');
      if (heroInput) {
        heroInput.value = this.query;
        if (clearBtn) clearBtn.classList.toggle('hidden', !this.query);
        heroInput.addEventListener('input', () => {
          if (clearBtn) clearBtn.classList.toggle('hidden', !heroInput.value);
        });
      }
      this.updateTypeChips();
      this.bindPagination();
    },

    /**
     * Keep the "active" style class set in sync with current type.
     * We toggle a single `data-active` attribute and let CSS (utility
     * classes written inline on the chip) reflect state via a helper
     * class — simpler than large class swaps and avoids FOUC.
     */
    updateTypeChips() {
      const activeCls   = ['bg-gradient-to-br','from-indigo-600','to-violet-600','text-white','border-transparent','shadow-md','shadow-indigo-500/30'];
      const inactiveCls = ['bg-white','dark:bg-slate-900','text-slate-700','dark:text-slate-300','border-slate-200','dark:border-slate-800','hover:bg-indigo-50','dark:hover:bg-slate-800','hover:text-indigo-700','dark:hover:text-indigo-300','hover:border-indigo-200','dark:hover:border-indigo-500/40'];
      document.querySelectorAll('.hero-type-chip').forEach(chip => {
        const typeId = chip.dataset.type;
        const isActive = typeId === (this.type || '');
        if (isActive) {
          inactiveCls.forEach(c => chip.classList.remove(c));
          activeCls.forEach(c => chip.classList.add(c));
        } else {
          activeCls.forEach(c => chip.classList.remove(c));
          inactiveCls.forEach(c => chip.classList.add(c));
        }
      });
    },

    clearHeroSearch() {
      const heroInput = document.getElementById('hero-search-input');
      if (heroInput) heroInput.value = '';
      const clearBtn  = document.getElementById('hero-clear-btn');
      if (clearBtn) clearBtn.classList.add('hidden');
    },

    clearAll() {
      this.query  = '';
      this.type   = '';
      this.sort   = 'latest';
      this.onSale = false;
      this.clearHeroSearch();
      this.updateTypeChips();
      this.fetchProducts();
    },

    async fetchProducts(page) {
      this.loading = true;
      const params = new URLSearchParams();
      if (this.query)                          params.set('q', this.query);
      if (this.type && this.type !== 'all')    params.set('type', this.type);
      if (this.sort && this.sort !== 'latest') params.set('sort', this.sort);
      if (this.onSale)                         params.set('on_sale', '1');
      if (page)                                params.set('page', page);

      const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
      history.replaceState(null, '', newUrl);

      try {
        const res  = await fetch(`{{ route('products.index') }}?${params.toString()}`, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        document.getElementById('products-grid').innerHTML        = data.html;
        document.getElementById('products-pagination').innerHTML  = data.pagination;
        this.total   = data.total;
        this.showing = data.showing;

        const statEl = document.getElementById('stat-total');
        if (statEl) statEl.textContent = data.total.toLocaleString();

        this.bindPagination();
      } catch (e) {
        console.error('Product search failed:', e);
      } finally {
        this.loading = false;
      }
    },

    bindPagination() {
      this.$nextTick(() => {
        document.querySelectorAll('#products-pagination a[href]').forEach(link => {
          link.addEventListener('click', (e) => {
            e.preventDefault();
            const url  = new URL(link.href);
            const page = url.searchParams.get('page');
            if (page) {
              this.fetchProducts(page);
              window.scrollTo({ top: 0, behavior: 'smooth' });
            }
          });
        });
      });
    }
  };
}
</script>
@endpush
@endsection
