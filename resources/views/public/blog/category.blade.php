@extends('layouts.app')

@section('title', ($category->meta_title ?? $category->name) . ' - บทความ')

@section('hero')
{{-- Category Hero --}}
<div class="relative overflow-hidden bg-gradient-to-br from-pink-50 via-indigo-50 to-violet-50 dark:from-slate-900 dark:via-indigo-950 dark:to-purple-950">
  <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(circle at 20% 50%,rgba(99,102,241,0.10) 0%,transparent 50%),radial-gradient(circle at 80% 20%,rgba(139,92,246,0.10) 0%,transparent 50%);"></div>
  <div class="absolute inset-0 pointer-events-none opacity-60 dark:opacity-100" style="background-image:url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 40L40 0M-10 10L10-10M30 50L50 30&quot; stroke=&quot;rgba(100,116,139,0.06)&quot; stroke-width=&quot;1&quot;/%3E%3C/svg%3E');"></div>

  {{-- Decorative blobs --}}
  <div class="absolute w-96 h-96 rounded-full bg-pink-400/15 dark:bg-indigo-500/20 blur-3xl top-[-100px] right-[-100px] pointer-events-none"></div>
  <div class="absolute w-80 h-80 rounded-full bg-violet-400/15 dark:bg-rose-500/15 blur-3xl bottom-[-80px] left-[-80px] pointer-events-none"></div>

  <div class="relative max-w-6xl mx-auto px-4 py-12 md:py-16">
    {{-- Breadcrumbs --}}
    <nav aria-label="Breadcrumb" class="mb-5">
      <ol class="flex items-center justify-center gap-2 text-xs text-slate-600 dark:text-slate-400 flex-wrap" itemscope itemtype="https://schema.org/BreadcrumbList">
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
          <a href="{{ url('/') }}" itemprop="item" class="hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"><span itemprop="name"><i class="bi bi-house-door mr-1"></i>หน้าแรก</span></a>
          <meta itemprop="position" content="1">
        </li>
        <li class="text-slate-400 dark:text-slate-500"><i class="bi bi-chevron-right text-[10px]"></i></li>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
          <a href="{{ route('blog.index') }}" itemprop="item" class="hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"><span itemprop="name">บทความ</span></a>
          <meta itemprop="position" content="2">
        </li>
        <li class="text-slate-400 dark:text-slate-500"><i class="bi bi-chevron-right text-[10px]"></i></li>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
          <span itemprop="name" class="font-semibold text-slate-800 dark:text-gray-100">{{ $category->name }}</span>
          <meta itemprop="position" content="3">
        </li>
      </ol>
    </nav>

    {{-- Title --}}
    <div class="text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-xl shadow-indigo-500/30 mb-5">
        <i class="{{ $category->icon ?? 'bi bi-folder-fill' }} text-3xl text-white"></i>
      </div>
      <h1 class="font-extrabold text-3xl sm:text-4xl md:text-5xl lg:text-6xl tracking-tight leading-[1.2] mb-3">
        <span class="bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 dark:from-indigo-300 dark:via-violet-300 dark:to-fuchsia-300 bg-clip-text text-transparent">{{ $category->name }}</span>
      </h1>
      @if($category->description)
        <p class="text-slate-600 dark:text-slate-300/80 text-sm sm:text-base max-w-2xl mx-auto">{{ $category->description }}</p>
      @endif
    </div>

    {{-- Stats --}}
    <div class="flex items-center justify-center gap-3 mt-6">
      <div class="inline-flex items-center gap-2 bg-white/70 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full px-4 py-1.5 backdrop-blur-sm">
        <span class="w-2 h-2 rounded-full bg-indigo-500 dark:bg-indigo-400 animate-pulse"></span>
        <span class="text-slate-600 dark:text-slate-300/70 text-xs font-medium"><strong class="text-slate-800 dark:text-white">{{ $posts->total() }}</strong> บทความ</span>
      </div>
    </div>

    {{-- Search --}}
    <div class="max-w-xl mx-auto mt-6" x-data>
      <div class="relative group">
        <div class="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 to-violet-500 rounded-2xl blur-lg opacity-20 group-hover:opacity-40 transition-opacity"></div>
        <div class="relative flex items-center bg-white dark:bg-slate-900/80 backdrop-blur-xl border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-lg shadow-indigo-500/10 dark:shadow-black/30 transition-all focus-within:shadow-xl focus-within:border-indigo-400/60">
          <span class="pl-5 pr-2 text-gray-400 dark:text-slate-500"><i class="bi bi-search text-lg"></i></span>
          <input type="text"
                 id="hero-search-input"
                 class="flex-1 bg-transparent border-0 text-slate-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-slate-500 py-3.5 px-2 text-sm focus:outline-none focus:ring-0"
                 placeholder="ค้นหาในหมวด {{ $category->name }}..."
                 value="{{ request('q') }}"
                 autocomplete="off"
                 @input.debounce.350ms="$dispatch('hero-search', { q: $el.value })">
        </div>
      </div>
    </div>
  </div>
  <div class="absolute bottom-0 left-0 right-0 h-px" style="background:linear-gradient(90deg,transparent,rgba(99,102,241,0.3),transparent);"></div>
</div>
@endsection

@section('content')

<div class="py-8" x-data="categorySearch()" x-init="init()" @hero-search.window="query = $event.detail.q; fetchPosts()">

  <div class="flex flex-col lg:flex-row gap-8">

    {{-- Main Content --}}
    <div class="flex-1 min-w-0">

      {{-- Sort Row --}}
      <div class="flex items-center justify-between mb-6">
        <div class="text-sm text-gray-600 dark:text-gray-400 font-medium" x-show="!loading">
          <span x-text="resultText"></span>
        </div>
        <select x-model="sort" @change="fetchPosts()"
                class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-slate-800 text-xs font-medium text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer transition-colors hover:border-indigo-300 dark:hover:border-indigo-400/40">
          <option value="latest">ล่าสุด</option>
          <option value="popular">ยอดนิยม</option>
          <option value="title">ชื่อ ก-ฮ</option>
        </select>
      </div>

      {{-- Loading --}}
      <div x-show="loading" x-transition.opacity class="flex items-center justify-center py-16">
        <div class="flex flex-col items-center gap-3">
          <div class="w-10 h-10 border-[3px] border-indigo-100 dark:border-white/10 border-t-indigo-500 dark:border-t-indigo-400 rounded-full animate-spin"></div>
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">กำลังโหลด...</span>
        </div>
      </div>

      {{-- Posts Grid --}}
      <div x-show="!loading" x-transition.opacity>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="blog-posts-grid">
          @forelse($posts as $post)
            @include('public.blog._post-card', ['post' => $post])
          @empty
          <div class="col-span-full">
            <div class="text-center py-20 px-6 rounded-3xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10">
              <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-gray-100 to-gray-50 dark:from-slate-700 dark:to-slate-800 mb-5 shadow-inner">
                <i class="bi bi-journal-x text-5xl text-gray-300 dark:text-slate-500"></i>
              </div>
              <p class="text-slate-700 dark:text-gray-100 font-bold mb-1 text-lg">ยังไม่มีบทความในหมวดนี้</p>
              <p class="text-gray-500 dark:text-gray-400 text-sm mb-5">เราจะมีบทความใหม่ในเร็วๆ นี้</p>
              <a href="{{ route('blog.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
                <i class="bi bi-arrow-left"></i> กลับไปดูบทความทั้งหมด
              </a>
            </div>
          </div>
          @endforelse
        </div>

        <div id="blog-pagination" class="my-12">
          @include('public.blog._pagination', ['posts' => $posts])
        </div>
      </div>
    </div>

    {{-- Sidebar --}}
    <div class="hidden lg:block w-80 xl:w-96 shrink-0">
      <div class="sticky top-20">
        @include('public.blog._sidebar')
      </div>
    </div>
  </div>
</div>

@endsection

@push('styles')
<style>
.blog-card { will-change: transform; }
.blog-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -12px rgba(0,0,0,0.12); }
.blog-card-wrap { animation: blogCardFadeIn 0.4s ease-out both; }
.blog-card-wrap:nth-child(1) { animation-delay: 0.02s; }
.blog-card-wrap:nth-child(2) { animation-delay: 0.06s; }
.blog-card-wrap:nth-child(3) { animation-delay: 0.10s; }
.blog-card-wrap:nth-child(4) { animation-delay: 0.14s; }
.blog-card-wrap:nth-child(5) { animation-delay: 0.18s; }
.blog-card-wrap:nth-child(6) { animation-delay: 0.22s; }
@keyframes blogCardFadeIn {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
@endpush

@push('scripts')
<script>
function categorySearch() {
  return {
    query: '{{ request("q") }}',
    sort: '{{ request("sort", "latest") }}',
    loading: false,
    total: {{ $posts->total() }},
    showing: {{ $posts->count() }},

    get resultText() {
      if (this.total === 0) return 'ไม่พบบทความ';
      if (this.showing === this.total) return `${this.total} บทความ`;
      return `${this.showing} จาก ${this.total} บทความ`;
    },

    init() {
      this.bindPagination();
    },

    async fetchPosts(page) {
      this.loading = true;
      const params = new URLSearchParams();
      if (this.query) params.set('q', this.query);
      if (this.sort && this.sort !== 'latest') params.set('sort', this.sort);
      if (page) params.set('page', page);

      const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
      history.replaceState(null, '', newUrl);

      try {
        const res = await fetch(`{{ route("blog.category", $category->slug) }}?${params.toString()}`, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        document.getElementById('blog-posts-grid').innerHTML = data.html;
        document.getElementById('blog-pagination').innerHTML = data.pagination;
        this.total = data.total;
        this.showing = data.showing;
        this.bindPagination();
      } catch (e) {
        console.error('Category search failed:', e);
      } finally {
        this.loading = false;
      }
    },

    bindPagination() {
      this.$nextTick(() => {
        document.querySelectorAll('#blog-pagination a.blog-page-link').forEach(link => {
          link.addEventListener('click', (e) => {
            e.preventDefault();
            const url = new URL(link.href);
            const page = url.searchParams.get('page');
            if (page) {
              this.fetchPosts(page);
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
