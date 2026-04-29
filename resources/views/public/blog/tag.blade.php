@extends('layouts.app')

@section('title', 'แท็ก: ' . $tag->name . ' - บทความ')

@section('hero')
{{-- Tag Hero --}}
<div class="relative overflow-hidden" style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#312e81 100%);">
  <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(circle at 20% 50%,rgba(99,102,241,0.15) 0%,transparent 50%),radial-gradient(circle at 80% 20%,rgba(139,92,246,0.12) 0%,transparent 50%);"></div>
  <div class="absolute inset-0 pointer-events-none" style="background-image:url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 40L40 0M-10 10L10-10M30 50L50 30&quot; stroke=&quot;rgba(255,255,255,0.02)&quot; stroke-width=&quot;1&quot;/%3E%3C/svg%3E');"></div>

  <div class="relative max-w-5xl mx-auto px-4 py-10 sm:py-14">
    {{-- Breadcrumbs --}}
    <nav aria-label="Breadcrumb" class="mb-4">
      <ol class="flex items-center gap-2 text-xs text-white/50" itemscope itemtype="https://schema.org/BreadcrumbList">
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
          <a href="{{ url('/') }}" itemprop="item" class="hover:text-white/80 transition-colors"><span itemprop="name">หน้าแรก</span></a>
          <meta itemprop="position" content="1">
        </li>
        <li class="text-white/30"><i class="bi bi-chevron-right text-[10px]"></i></li>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
          <a href="{{ route('blog.index') }}" itemprop="item" class="hover:text-white/80 transition-colors"><span itemprop="name">บทความ</span></a>
          <meta itemprop="position" content="2">
        </li>
        <li class="text-white/30"><i class="bi bi-chevron-right text-[10px]"></i></li>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
          <span itemprop="name" class="text-white/80">{{ $tag->name }}</span>
          <meta itemprop="position" content="3">
        </li>
      </ol>
    </nav>

    {{-- Title --}}
    <div class="text-center">
      <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-cyan-500/30 to-blue-600/30 border border-white/10 backdrop-blur-sm mb-4">
        <i class="bi bi-hash text-2xl text-cyan-300"></i>
      </div>
      <h1 class="text-white font-extrabold text-2xl sm:text-3xl tracking-tight mb-2">
        แท็ก: <span class="text-indigo-300">{{ $tag->name }}</span>
      </h1>
      <p class="text-white/50 text-sm sm:text-base">บทความทั้งหมดที่เกี่ยวข้องกับ "{{ $tag->name }}"</p>
    </div>

    {{-- Stats --}}
    <div class="flex items-center justify-center gap-4 mt-5">
      <div class="inline-flex items-center gap-2 bg-white/[0.06] border border-white/[0.08] rounded-full px-4 py-1.5">
        <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
        <span class="text-white/60 text-xs font-medium"><strong class="text-white">{{ $posts->total() }}</strong> บทความ</span>
      </div>
    </div>

    {{-- Search --}}
    <div class="max-w-xl mx-auto mt-6" x-data>
      <div class="relative group">
        <div class="absolute inset-0 bg-gradient-to-r from-cyan-500 to-blue-500 rounded-2xl blur-lg opacity-20 group-hover:opacity-40 transition-opacity"></div>
        <div class="relative flex items-center bg-white/[0.08] backdrop-blur-xl border border-white/[0.12] rounded-2xl overflow-hidden transition-all focus-within:bg-white/[0.12] focus-within:border-cyan-400/50">
          <span class="pl-5 pr-2 text-white/40"><i class="bi bi-search text-lg"></i></span>
          <input type="text"
                 id="hero-search-input"
                 class="flex-1 bg-transparent border-0 text-white placeholder-white/40 py-3.5 px-2 text-sm focus:outline-none focus:ring-0"
                 placeholder="ค้นหาในแท็ก {{ $tag->name }}..."
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

<div x-data="tagSearch()" x-init="init()" @hero-search.window="query = $event.detail.q; fetchPosts()">

  <div class="flex flex-col lg:flex-row gap-8">

    {{-- Main Content --}}
    <div class="flex-1 min-w-0">

      {{-- Sort Row --}}
      <div class="flex items-center justify-between mb-5">
        <div class="text-sm text-gray-500" x-show="!loading">
          <span x-text="resultText"></span>
        </div>
        <select x-model="sort" @change="fetchPosts()"
                class="px-3 py-2 border border-gray-200 rounded-xl bg-white text-xs font-medium text-gray-600 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer transition-colors hover:border-indigo-300">
          <option value="latest">ล่าสุด</option>
          <option value="popular">ยอดนิยม</option>
          <option value="title">ชื่อ ก-ฮ</option>
        </select>
      </div>

      {{-- Loading --}}
      <div x-show="loading" x-transition.opacity class="flex items-center justify-center py-16">
        <div class="flex flex-col items-center gap-3">
          <div class="w-10 h-10 border-[3px] border-indigo-100 border-t-indigo-500 rounded-full animate-spin"></div>
          <span class="text-gray-400 text-sm font-medium">กำลังโหลด...</span>
        </div>
      </div>

      {{-- Posts Grid --}}
      <div x-show="!loading" x-transition.opacity>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5" id="blog-posts-grid">
          @forelse($posts as $post)
            @include('public.blog._post-card', ['post' => $post])
          @empty
          <div class="col-span-full">
            <div class="text-center py-16">
              <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-50 mb-4">
                <i class="bi bi-hash text-4xl text-gray-300"></i>
              </div>
              <p class="text-gray-400 font-medium mb-1">ไม่พบบทความสำหรับแท็กนี้</p>
              <a href="{{ route('blog.index') }}" class="text-indigo-500 text-sm hover:underline mt-2 inline-block">กลับไปดูบทความทั้งหมด</a>
            </div>
          </div>
          @endforelse
        </div>

        <div id="blog-pagination">
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
function tagSearch() {
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
        const res = await fetch(`{{ route("blog.tag", $tag->slug) }}?${params.toString()}`, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        document.getElementById('blog-posts-grid').innerHTML = data.html;
        document.getElementById('blog-pagination').innerHTML = data.pagination;
        this.total = data.total;
        this.showing = data.showing;
        this.bindPagination();
      } catch (e) {
        console.error('Tag search failed:', e);
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
