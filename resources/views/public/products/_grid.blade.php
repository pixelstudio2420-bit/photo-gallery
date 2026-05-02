{{-- =======================================================================
     PRODUCT CARD — REDESIGN
     -------------------------------------------------------------------
     • Flat, modern e-commerce card with explicit dark/light surfaces.
     • Clear hierarchy: badges → image → meta → price → CTA.
     • Hover lifts the whole card + zooms image + highlights CTA.
     ====================================================================== --}}
@foreach($products as $product)
@php
  $typeMeta = $typeLabels[$product->product_type] ?? [
    'label' => 'สินค้า',
    'icon'  => 'bi-box-seam',
    'color' => 'from-slate-500 to-slate-600',
  ];
  $hasSale  = !empty($product->sale_price);
  $discount = $hasSale
    ? round((($product->price - $product->sale_price) / max($product->price, 0.01)) * 100)
    : 0;
  // Lead-magnet detection: price=0 items are gated behind LINE friend
  // collection (per the "ฟรี — รับผ่าน LINE" marketing strategy). The
  // card shows "FREE" instead of "0 ฿" + a LINE accent so users
  // immediately understand the value-vs-cost trade-off.
  $isFree = (float) $product->price === 0.0 && empty($product->sale_price);
@endphp

<div class="product-card-wrap h-full">
  <a href="{{ route('products.show', $product->slug) }}"
     style="animation-delay: {{ ($loop->index % 12) * 40 }}ms"
     class="group relative flex flex-col h-full overflow-hidden rounded-2xl
            bg-white dark:bg-slate-900
            border border-slate-200 dark:border-slate-800
            shadow-sm shadow-slate-900/5 dark:shadow-black/20
            hover:shadow-2xl hover:shadow-indigo-500/15 dark:hover:shadow-indigo-500/20
            hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-500/40
            transition-all duration-300">

    {{-- ── IMAGE ─────────────────────────────────────────────────── --}}
    <div class="relative overflow-hidden aspect-[4/3]
                bg-slate-100 dark:bg-slate-800">
      @if($product->cover_image_url)
        <img src="{{ $product->cover_image_url }}"
             alt="{{ $product->name }}"
             loading="lazy"
             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 ease-out">
      @else
        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br {{ $typeMeta['color'] }}">
          <i class="bi {{ $typeMeta['icon'] }} text-white text-6xl opacity-40"></i>
        </div>
      @endif

      {{-- Bottom gradient (always subtle, stronger on hover) --}}
      <div class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/60 via-black/10 to-transparent
                  opacity-40 group-hover:opacity-80 transition-opacity duration-300"></div>

      {{-- Type badge (top-left) --}}
      <div class="absolute top-3 left-3">
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold
                     bg-white/95 dark:bg-slate-900/90 backdrop-blur
                     text-slate-700 dark:text-slate-200
                     border border-white/60 dark:border-white/10
                     shadow-sm">
          <i class="bi {{ $typeMeta['icon'] }} text-indigo-600 dark:text-indigo-400"></i>
          {{ $typeMeta['label'] }}
        </span>
      </div>

      {{-- Badges (top-right) --}}
      <div class="absolute top-3 right-3 flex flex-col gap-1.5 items-end">
        {{-- FREE badge — green LINE-themed, replaces sale% for price=0 items.
             Eye-catching but doesn't scream "discount" because there's no
             original price to discount from. --}}
        @if($isFree)
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold
                       bg-gradient-to-r from-emerald-500 to-green-600 text-white
                       shadow-md shadow-emerald-500/30">
            <i class="bi bi-gift-fill mr-1 text-[10px]"></i>FREE
          </span>
        @elseif($hasSale)
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold
                       bg-gradient-to-r from-rose-500 to-pink-500 text-white
                       shadow-md shadow-rose-500/30">
            <i class="bi bi-tag-fill mr-1 text-[10px]"></i>-{{ $discount }}%
          </span>
        @endif
        @if($product->is_featured)
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold
                       bg-gradient-to-r from-amber-400 to-orange-500 text-white
                       shadow-md shadow-amber-500/30">
            <i class="bi bi-star-fill mr-0.5"></i>FEATURED
          </span>
        @endif
      </div>

      {{-- Quick-view CTA (reveals on hover) --}}
      <div class="absolute bottom-3 left-3 right-3
                  translate-y-2 opacity-0
                  group-hover:translate-y-0 group-hover:opacity-100
                  transition-all duration-300">
        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full
                    bg-white/95 backdrop-blur text-slate-900 text-xs font-semibold shadow-lg">
          <i class="bi bi-eye-fill text-indigo-600"></i> ดูรายละเอียด
          <i class="bi bi-arrow-right text-indigo-600"></i>
        </div>
      </div>
    </div>

    {{-- ── CONTENT ───────────────────────────────────────────────── --}}
    <div class="p-4 md:p-5 flex-1 flex flex-col">
      {{-- Name --}}
      <h3 class="font-bold text-[15px] leading-snug line-clamp-2 mb-1.5
                 text-slate-900 dark:text-slate-50
                 group-hover:text-indigo-600 dark:group-hover:text-indigo-300
                 transition-colors">
        {{ $product->name }}
      </h3>

      {{-- Short description --}}
      @if($product->short_description)
        <p class="text-xs leading-relaxed line-clamp-2 mb-3
                  text-slate-600 dark:text-slate-400">
          {{ $product->short_description }}
        </p>
      @endif

      {{-- Meta row (file format, sales count) --}}
      <div class="flex items-center gap-3 text-[11px] font-medium mb-3 flex-wrap">
        @if($product->file_format)
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md
                       bg-slate-100 dark:bg-slate-800
                       text-slate-600 dark:text-slate-300">
            <i class="bi bi-file-earmark-fill text-indigo-500 dark:text-indigo-400"></i>
            {{ $product->file_format }}
          </span>
        @endif
        @if($product->total_sales > 0)
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md
                       bg-emerald-50 dark:bg-emerald-500/10
                       text-emerald-700 dark:text-emerald-300
                       border border-emerald-200/60 dark:border-emerald-500/30">
            <i class="bi bi-download"></i> {{ number_format($product->total_sales) }}
          </span>
        @endif
        @if($product->version)
          <span class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-500">
            <i class="bi bi-tag"></i> v{{ $product->version }}
          </span>
        @endif
      </div>

      {{-- Price + CTA --}}
      <div class="mt-auto pt-3 border-t border-slate-100 dark:border-slate-800
                  flex items-end justify-between gap-2">
        <div>
          @if($isFree)
            {{-- FREE products: emerald + LINE accent. Replaces "0 ฿"
                 with explicit "FREE / รับผ่าน LINE" so the value
                 proposition reads as a deliberate trade (LINE friend
                 → access) not "lol free zero baht." --}}
            <div class="text-[10px] font-semibold uppercase tracking-wider leading-none mb-0.5
                        text-emerald-600 dark:text-emerald-400">รับฟรี</div>
            <div class="text-xl font-extrabold leading-tight
                        text-emerald-600 dark:text-emerald-400
                        flex items-center gap-1.5">
              FREE
              <i class="bi bi-line text-[15px]" title="รับผ่าน LINE @loadroop"></i>
            </div>
          @elseif($hasSale)
            <div class="text-xs leading-none mb-0.5
                        text-slate-400 dark:text-slate-500 line-through">
              {{ number_format($product->price, 0) }} ฿
            </div>
            <div class="text-xl font-extrabold leading-tight
                        text-rose-600 dark:text-rose-400
                        flex items-baseline gap-1">
              {{ number_format($product->sale_price, 0) }}
              <span class="text-xs font-semibold">฿</span>
            </div>
          @else
            <div class="text-[10px] font-semibold uppercase tracking-wider leading-none mb-0.5
                        text-slate-400 dark:text-slate-500">ราคา</div>
            <div class="text-xl font-extrabold leading-tight
                        text-indigo-600 dark:text-indigo-400
                        flex items-baseline gap-1">
              {{ number_format($product->price, 0) }}
              <span class="text-xs font-semibold">฿</span>
            </div>
          @endif
        </div>
        {{-- CTA arrow — green for FREE (LINE), indigo for paid. Same
             hover lift pattern across both so the visual rhythm of
             the grid stays consistent. --}}
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl shrink-0 text-white shadow-md transition-all duration-200
                     {{ $isFree
                          ? 'bg-gradient-to-br from-emerald-500 to-green-600 shadow-emerald-500/25 group-hover:shadow-emerald-500/40'
                          : 'bg-gradient-to-br from-indigo-600 to-violet-600 shadow-indigo-500/25 group-hover:shadow-indigo-500/40' }}
                     group-hover:scale-110 group-hover:shadow-lg"
              aria-label="ดูรายละเอียด">
          <i class="bi {{ $isFree ? 'bi-gift-fill' : 'bi-arrow-right' }}"></i>
        </span>
      </div>
    </div>
  </a>
</div>
@endforeach
