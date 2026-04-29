@if(isset($posts) && $posts instanceof \Illuminate\Pagination\LengthAwarePaginator && $posts->hasPages())
<nav aria-label="Blog pagination" class="flex justify-center">
  <ul class="inline-flex items-center gap-1">
    {{-- Previous --}}
    @if($posts->onFirstPage())
      <li>
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-gray-300 dark:text-gray-600 cursor-not-allowed">
          <i class="bi bi-chevron-left"></i>
        </span>
      </li>
    @else
      <li>
        <a href="{{ $posts->previousPageUrl() }}"
           class="blog-page-link inline-flex items-center justify-center w-10 h-10 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"
           aria-label="หน้าก่อนหน้า">
          <i class="bi bi-chevron-left"></i>
        </a>
      </li>
    @endif

    {{-- Page Numbers --}}
    @php
      $current = $posts->currentPage();
      $last = $posts->lastPage();
      $start = max(1, $current - 2);
      $end = min($last, $current + 2);
    @endphp

    @if($start > 1)
      <li>
        <a href="{{ $posts->url(1) }}" class="blog-page-link inline-flex items-center justify-center w-10 h-10 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors">1</a>
      </li>
      @if($start > 2)
        <li><span class="inline-flex items-center justify-center w-8 h-10 text-gray-400 dark:text-gray-500 text-sm">...</span></li>
      @endif
    @endif

    @for($i = $start; $i <= $end; $i++)
      <li>
        @if($i == $current)
          <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-sm font-bold bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-500/30">{{ $i }}</span>
        @else
          <a href="{{ $posts->url($i) }}" class="blog-page-link inline-flex items-center justify-center w-10 h-10 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors">{{ $i }}</a>
        @endif
      </li>
    @endfor

    @if($end < $last)
      @if($end < $last - 1)
        <li><span class="inline-flex items-center justify-center w-8 h-10 text-gray-400 dark:text-gray-500 text-sm">...</span></li>
      @endif
      <li>
        <a href="{{ $posts->url($last) }}" class="blog-page-link inline-flex items-center justify-center w-10 h-10 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors">{{ $last }}</a>
      </li>
    @endif

    {{-- Next --}}
    @if($posts->hasMorePages())
      <li>
        <a href="{{ $posts->nextPageUrl() }}"
           class="blog-page-link inline-flex items-center justify-center w-10 h-10 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"
           aria-label="หน้าถัดไป">
          <i class="bi bi-chevron-right"></i>
        </a>
      </li>
    @else
      <li>
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-gray-300 dark:text-gray-600 cursor-not-allowed">
          <i class="bi bi-chevron-right"></i>
        </span>
      </li>
    @endif
  </ul>
</nav>
@endif
