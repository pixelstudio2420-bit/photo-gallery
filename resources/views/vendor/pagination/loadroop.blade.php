{{-- ============================================================
     Loadroop pagination view
     ────────────────────────────────────────────────────────────
     Rounded pill buttons with a gradient on the active page,
     subtle hover lift, dark-mode aware. Designed to drop in
     anywhere via `$paginator->links('vendor.pagination.loadroop')`.

     Behaviours:
       • Prev / Next buttons get an icon + label (label collapses
         to icon-only on mobile to keep the row from wrapping).
       • Page numbers use ellipsis ("…") when the paginator window
         skips a range.
       • The current page is non-clickable and visually elevated.
       • Disabled (no prev / no next) buttons stay rendered so the
         row width doesn't shift between the first & last pages.

     Usage:
       {{ $items->withQueryString()->links('vendor.pagination.loadroop') }}
  ============================================================ --}}

@if ($paginator->hasPages())
<nav role="navigation" aria-label="Pagination Navigation"
     class="flex items-center justify-center mt-8 select-none">
  <ul class="inline-flex items-center gap-1.5 sm:gap-2 p-1.5 sm:p-2 rounded-2xl
             bg-white dark:bg-slate-800/70 backdrop-blur
             border border-slate-200 dark:border-white/10
             shadow-sm">

    {{-- ── Previous Page ── --}}
    @if ($paginator->onFirstPage())
      <li>
        <span aria-disabled="true" aria-label="@lang('pagination.previous')"
              class="inline-flex items-center justify-center gap-1
                     min-w-[40px] h-10 px-3 rounded-xl text-sm font-semibold
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
                  min-w-[40px] h-10 px-3 rounded-xl text-sm font-semibold
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

    {{-- ── Page numbers + "Three Dots" separators ── --}}
    @foreach ($elements as $element)
      {{-- Three-dots separator returns a string, not an array --}}
      @if (is_string($element))
        <li class="hidden sm:flex">
          <span class="inline-flex items-center justify-center
                       min-w-[40px] h-10 text-slate-400 dark:text-slate-600 font-bold">
            …
          </span>
        </li>
      @endif

      {{-- Array of links keyed by page number --}}
      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <li aria-current="page">
              <span class="inline-flex items-center justify-center
                           min-w-[40px] h-10 px-3 rounded-xl text-sm font-bold
                           bg-gradient-to-br from-blue-500 to-cyan-500
                           text-white shadow-md shadow-blue-500/30
                           ring-2 ring-blue-500/20 dark:ring-blue-400/20">
                {{ $page }}
              </span>
            </li>
          @else
            <li class="hidden sm:flex">
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
            </li>
          @endif
        @endforeach
      @endif
    @endforeach

    {{-- Mobile: compact "page X of Y" between prev/next instead of every number --}}
    <li class="flex sm:hidden">
      <span class="inline-flex items-center justify-center
                   min-w-[64px] h-10 px-3 rounded-xl text-sm font-semibold
                   bg-slate-50 dark:bg-white/[0.03]
                   text-slate-700 dark:text-slate-200">
        {{ $paginator->currentPage() }} <span class="text-slate-400 dark:text-slate-500 mx-1">/</span> {{ $paginator->lastPage() }}
      </span>
    </li>

    {{-- ── Next Page ── --}}
    @if ($paginator->hasMorePages())
      <li>
        <a href="{{ $paginator->nextPageUrl() }}"
           rel="next" aria-label="@lang('pagination.next')"
           class="inline-flex items-center justify-center gap-1
                  min-w-[40px] h-10 px-3 rounded-xl text-sm font-semibold
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
                     min-w-[40px] h-10 px-3 rounded-xl text-sm font-semibold
                     bg-slate-50 dark:bg-white/[0.03]
                     text-slate-300 dark:text-slate-600 cursor-not-allowed">
          <span class="hidden sm:inline">ถัดไป</span>
          <i class="bi bi-chevron-right text-base"></i>
        </span>
      </li>
    @endif
  </ul>
</nav>

{{-- Tiny range caption under the pager so the user sees "10–20 ของ 87" --}}
<div class="text-center text-xs text-slate-400 dark:text-slate-500 mt-2">
  แสดง <span class="font-semibold text-slate-600 dark:text-slate-400">{{ $paginator->firstItem() }}</span>–<span class="font-semibold text-slate-600 dark:text-slate-400">{{ $paginator->lastItem() }}</span>
  จาก <span class="font-semibold text-slate-600 dark:text-slate-400">{{ $paginator->total() }}</span> รายการ
</div>
@endif
