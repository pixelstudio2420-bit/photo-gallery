{{-- ============================================================
     Loadroop pagination view (v2)
     ────────────────────────────────────────────────────────────
     Modern pill pagination with blue→violet gradient on the
     active page (matches the photographer / admin theme), subtle
     hover lift, dark-mode aware. Drop in via:

       {{ $items->withQueryString()->links('vendor.pagination.loadroop') }}

     v2 changes (2026-05-19)
     ───────────────────────
     • Active page now uses from-blue-500 → to-violet-600 (was
       blue → cyan). Lines up with the photographer/photos header
       and the "อัปโหลดรูป" CTA on the same page.
     • Mobile shows page numbers too — was collapsing to an
       unclickable "X / Y" pill, which broke jump-to-page on
       narrow screens (the bug the photographer reported).
       Compact mobile rule: always show first + last + current,
       and 1 neighbour on each side of current. Three-dot
       separators bridge the gaps.
     • First-page / Last-page quick-jump buttons (« and ») on
       wider screens — saves the photographer 8 clicks when they
       want to bounce to page 1 from page 9.
     • Total-count caption ("แสดง 61–120 จาก 247 รายการ") stays
       below the pager.

     Behaviour:
     • Disabled prev/next still render so the row width doesn't
       shift between first & last pages.
     • One single ::hover transform (no compound animations) — keeps
       the click target stable on touch devices.
  ============================================================ --}}

@if ($paginator->hasPages())
@php
  // Build a compact list of page numbers for MOBILE.
  // Rule: always show 1, last, current, current±1 — three-dot fill
  // anywhere they don't connect. Caps the rendered button count at
  // ≤7 even for 100-page sets so the row never wraps on a 320px screen.
  $current = $paginator->currentPage();
  $last    = $paginator->lastPage();
  $compact = collect([1, $current - 1, $current, $current + 1, $last])
                ->filter(fn ($n) => $n >= 1 && $n <= $last)
                ->unique()
                ->sort()
                ->values()
                ->all();
@endphp

<nav role="navigation" aria-label="Pagination Navigation"
     class="flex items-center justify-center mt-8 select-none">
  <ul class="inline-flex items-center gap-1 sm:gap-1.5 p-1.5 sm:p-2 rounded-2xl
             bg-white dark:bg-slate-800/70 backdrop-blur
             border border-slate-200 dark:border-white/10
             shadow-sm">

    {{-- ── First page (« — desktop only) ── --}}
    @if ($paginator->onFirstPage())
      <li class="hidden sm:flex">
        <span aria-disabled="true" aria-label="First page"
              class="inline-flex items-center justify-center
                     w-10 h-10 rounded-xl text-base font-semibold
                     bg-slate-50 dark:bg-white/[0.03]
                     text-slate-300 dark:text-slate-600 cursor-not-allowed">
          <i class="bi bi-chevron-double-left"></i>
        </span>
      </li>
    @else
      <li class="hidden sm:flex">
        <a href="{{ $paginator->url(1) }}"
           aria-label="First page"
           class="inline-flex items-center justify-center
                  w-10 h-10 rounded-xl text-base font-semibold
                  text-slate-600 dark:text-slate-300
                  hover:bg-blue-50 dark:hover:bg-blue-500/15
                  hover:text-blue-700 dark:hover:text-blue-300
                  transition">
          <i class="bi bi-chevron-double-left"></i>
        </a>
      </li>
    @endif

    {{-- ── Previous Page ── --}}
    @if ($paginator->onFirstPage())
      <li>
        <span aria-disabled="true" aria-label="@lang('pagination.previous')"
              class="inline-flex items-center justify-center gap-1
                     min-w-[40px] h-10 px-2.5 sm:px-3 rounded-xl text-sm font-semibold
                     bg-slate-50 dark:bg-white/[0.03]
                     text-slate-300 dark:text-slate-600 cursor-not-allowed">
          <i class="bi bi-chevron-left text-base"></i>
          <span class="hidden sm:inline">ก่อนหน้า</span>
        </span>
      </li>
    @else
      <li>
        <a href="{{ $paginator->previousPageUrl() }}"
           rel="prev" aria-label="@lang('pagination.previous')"
           class="inline-flex items-center justify-center gap-1
                  min-w-[40px] h-10 px-2.5 sm:px-3 rounded-xl text-sm font-semibold
                  text-slate-700 dark:text-slate-200
                  hover:bg-blue-50 dark:hover:bg-blue-500/15
                  hover:text-blue-700 dark:hover:text-blue-300
                  hover:-translate-y-0.5 active:translate-y-0
                  transition will-change-transform">
          <i class="bi bi-chevron-left text-base"></i>
          <span class="hidden sm:inline">ก่อนหน้า</span>
        </a>
      </li>
    @endif

    {{-- ── DESKTOP: full page-number list with three-dot separators ── --}}
    <li class="hidden sm:flex contents">
      @foreach ($elements as $element)
        @if (is_string($element))
          {{-- "..." gap separator --}}
          <span class="inline-flex items-center justify-center
                       min-w-[40px] h-10 text-slate-400 dark:text-slate-600 font-bold">…</span>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <span aria-current="page"
                    class="inline-flex items-center justify-center
                           min-w-[40px] h-10 px-3 rounded-xl text-sm font-bold
                           bg-gradient-to-br from-blue-500 to-violet-600
                           text-white shadow-md shadow-violet-500/30
                           ring-2 ring-violet-500/20 dark:ring-violet-400/20">
                {{ $page }}
              </span>
            @else
              <a href="{{ $url }}"
                 aria-label="@lang('pagination.go_to_page', ['page' => $page])"
                 class="inline-flex items-center justify-center
                        min-w-[40px] h-10 px-3 rounded-xl text-sm font-semibold
                        text-slate-700 dark:text-slate-200
                        hover:bg-blue-50 dark:hover:bg-blue-500/15
                        hover:text-blue-700 dark:hover:text-blue-300
                        hover:-translate-y-0.5 active:translate-y-0
                        transition will-change-transform">
                {{ $page }}
              </a>
            @endif
          @endforeach
        @endif
      @endforeach
    </li>

    {{-- ── MOBILE: compact 1 / current / last with three-dot fill ── --}}
    @php $prev = 0; @endphp
    @foreach ($compact as $num)
      @if ($num - $prev > 1)
        <li class="flex sm:hidden">
          <span class="inline-flex items-center justify-center
                       min-w-[28px] h-10 text-slate-400 dark:text-slate-600 font-bold">…</span>
        </li>
      @endif
      <li class="flex sm:hidden">
        @if ($num === $current)
          <span aria-current="page"
                class="inline-flex items-center justify-center
                       min-w-[40px] h-10 px-2.5 rounded-xl text-sm font-bold
                       bg-gradient-to-br from-blue-500 to-violet-600
                       text-white shadow-md shadow-violet-500/30
                       ring-2 ring-violet-500/20">
            {{ $num }}
          </span>
        @else
          <a href="{{ $paginator->url($num) }}"
             class="inline-flex items-center justify-center
                    min-w-[40px] h-10 px-2.5 rounded-xl text-sm font-semibold
                    text-slate-700 dark:text-slate-200
                    hover:bg-blue-50 dark:hover:bg-blue-500/15
                    hover:text-blue-700 dark:hover:text-blue-300
                    transition">
            {{ $num }}
          </a>
        @endif
      </li>
      @php $prev = $num; @endphp
    @endforeach

    {{-- ── Next Page ── --}}
    @if ($paginator->hasMorePages())
      <li>
        <a href="{{ $paginator->nextPageUrl() }}"
           rel="next" aria-label="@lang('pagination.next')"
           class="inline-flex items-center justify-center gap-1
                  min-w-[40px] h-10 px-2.5 sm:px-3 rounded-xl text-sm font-semibold
                  text-slate-700 dark:text-slate-200
                  hover:bg-blue-50 dark:hover:bg-blue-500/15
                  hover:text-blue-700 dark:hover:text-blue-300
                  hover:-translate-y-0.5 active:translate-y-0
                  transition will-change-transform">
          <span class="hidden sm:inline">ถัดไป</span>
          <i class="bi bi-chevron-right text-base"></i>
        </a>
      </li>
    @else
      <li>
        <span aria-disabled="true" aria-label="@lang('pagination.next')"
              class="inline-flex items-center justify-center gap-1
                     min-w-[40px] h-10 px-2.5 sm:px-3 rounded-xl text-sm font-semibold
                     bg-slate-50 dark:bg-white/[0.03]
                     text-slate-300 dark:text-slate-600 cursor-not-allowed">
          <span class="hidden sm:inline">ถัดไป</span>
          <i class="bi bi-chevron-right text-base"></i>
        </span>
      </li>
    @endif

    {{-- ── Last page (» — desktop only) ── --}}
    @if ($paginator->hasMorePages())
      <li class="hidden sm:flex">
        <a href="{{ $paginator->url($paginator->lastPage()) }}"
           aria-label="Last page"
           class="inline-flex items-center justify-center
                  w-10 h-10 rounded-xl text-base font-semibold
                  text-slate-600 dark:text-slate-300
                  hover:bg-blue-50 dark:hover:bg-blue-500/15
                  hover:text-blue-700 dark:hover:text-blue-300
                  transition">
          <i class="bi bi-chevron-double-right"></i>
        </a>
      </li>
    @else
      <li class="hidden sm:flex">
        <span aria-disabled="true" aria-label="Last page"
              class="inline-flex items-center justify-center
                     w-10 h-10 rounded-xl text-base font-semibold
                     bg-slate-50 dark:bg-white/[0.03]
                     text-slate-300 dark:text-slate-600 cursor-not-allowed">
          <i class="bi bi-chevron-double-right"></i>
        </span>
      </li>
    @endif
  </ul>
</nav>

{{-- "Showing X–Y of Z รายการ" caption — gives the photographer
     a sense of where they are in a long list without having to
     do the math themselves. --}}
<div class="text-center text-xs text-slate-400 dark:text-slate-500 mt-2.5">
  แสดง
  <span class="font-semibold text-slate-600 dark:text-slate-400 tabular-nums">{{ number_format($paginator->firstItem()) }}</span>–<span class="font-semibold text-slate-600 dark:text-slate-400 tabular-nums">{{ number_format($paginator->lastItem()) }}</span>
  จาก
  <span class="font-semibold text-slate-600 dark:text-slate-400 tabular-nums">{{ number_format($paginator->total()) }}</span>
  รายการ
</div>
@endif
