@extends('layouts.app')

@section('title', 'บทความ & ข่าวสาร')

@section('hero')
{{-- Hero Section --}}
<div class="relative overflow-hidden bg-gradient-to-br from-pink-50 via-indigo-50 to-violet-50 dark:from-slate-900 dark:via-indigo-950 dark:to-purple-950">
  <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(circle at 20% 50%,rgba(99,102,241,0.10) 0%,transparent 50%),radial-gradient(circle at 80% 20%,rgba(139,92,246,0.10) 0%,transparent 50%);"></div>
  <div class="absolute inset-0 pointer-events-none opacity-60 dark:opacity-100" style="background-image:url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 40L40 0M-10 10L10-10M30 50L50 30&quot; stroke=&quot;rgba(100,116,139,0.06)&quot; stroke-width=&quot;1&quot;/%3E%3C/svg%3E');"></div>

  {{-- Decorative blobs --}}
  <div class="absolute w-96 h-96 rounded-full bg-pink-400/15 dark:bg-indigo-500/20 blur-3xl top-[-100px] right-[-100px] pointer-events-none"></div>
  <div class="absolute w-80 h-80 rounded-full bg-violet-400/15 dark:bg-rose-500/15 blur-3xl bottom-[-80px] left-[-80px] pointer-events-none"></div>

  <div class="relative max-w-6xl mx-auto px-4 py-12 md:py-16">
    {{-- Title --}}
    <div class="text-center mb-8">
      <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold backdrop-blur-md border bg-white/70 dark:bg-white/10 border-indigo-200/60 dark:border-white/10 text-indigo-700 dark:text-indigo-200 shadow-sm mb-4">
        <i class="bi bi-journal-richtext"></i> Blog & News
      </span>
      <h1 class="font-extrabold text-3xl sm:text-4xl md:text-5xl lg:text-6xl tracking-tight leading-[1.2] mb-3">
        <span class="bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 dark:from-indigo-300 dark:via-violet-300 dark:to-fuchsia-300 bg-clip-text text-transparent">บทความ & ข่าวสาร</span>
      </h1>
      <p class="text-slate-600 dark:text-slate-300/80 text-sm sm:text-base max-w-2xl mx-auto">เรื่องราว เทคนิค และข่าวสารเกี่ยวกับการถ่ายภาพ พร้อมแรงบันดาลใจสำหรับช่างภาพทุกระดับ</p>
    </div>

    {{-- Search Input --}}
    <div class="max-w-2xl mx-auto" x-data>
      <div class="relative group">
        <div class="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 to-violet-500 rounded-2xl blur-lg opacity-30 group-hover:opacity-50 transition-opacity"></div>
        <div class="relative flex items-center bg-white dark:bg-slate-900/80 backdrop-blur-xl border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-lg shadow-indigo-500/10 dark:shadow-black/30 transition-all focus-within:shadow-xl focus-within:shadow-indigo-500/20 focus-within:border-indigo-400/60">
          <span class="pl-5 pr-2 text-gray-400 dark:text-slate-500"><i class="bi bi-search text-lg"></i></span>
          <input type="text"
                 id="hero-search-input"
                 class="flex-1 bg-transparent border-0 text-slate-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-slate-500 py-4 px-2 text-base focus:outline-none focus:ring-0"
                 placeholder="ค้นหาบทความ..."
                 value="{{ request('q') }}"
                 autocomplete="off"
                 @input.debounce.350ms="$dispatch('hero-search', { q: $el.value })">
          <button type="button" id="hero-clear-btn" class="{{ request('q') ? '' : 'hidden' }} pr-4 text-gray-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-white transition" @click="$el.previousElementSibling.value=''; $dispatch('hero-search', {q:''})">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>
    </div>

    {{-- Category Chips --}}
    @if(isset($categories) && $categories->count())
    <div class="flex items-center justify-center gap-2 mt-6 overflow-x-auto pb-1 -mx-1 px-1" id="hero-category-chips" style="scrollbar-width:none;">
      <button class="hero-cat-chip shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-semibold border transition-all cursor-pointer active:scale-95 hover:scale-105 bg-gradient-to-br from-indigo-500 to-violet-600 text-white border-transparent shadow-md shadow-indigo-500/30" data-cat="" onclick="window.dispatchEvent(new CustomEvent('hero-category', {detail:{id:''}}))">
        <i class="bi bi-grid-3x3-gap"></i> ทั้งหมด
      </button>
      @foreach($categories as $cat)
      <button class="hero-cat-chip shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-semibold border transition-all cursor-pointer active:scale-95 hover:scale-105 bg-white/80 dark:bg-white/5 text-slate-700 dark:text-slate-300 border-gray-200 dark:border-white/10 hover:bg-indigo-50 dark:hover:bg-white/10 hover:text-indigo-600 dark:hover:text-indigo-300 hover:border-indigo-300 dark:hover:border-indigo-400/40" data-cat="{{ $cat->id }}" onclick="window.dispatchEvent(new CustomEvent('hero-category', {detail:{id:'{{ $cat->id }}'}}))">
        @if($cat->icon)<i class="{{ $cat->icon }}"></i>@endif
        {{ $cat->name }}
        @if($cat->post_count > 0)<span class="opacity-60">({{ $cat->post_count }})</span>@endif
      </button>
      @endforeach
    </div>
    @endif

    {{-- Stats --}}
    <div class="flex items-center justify-center gap-3 mt-6 flex-wrap">
      <div class="inline-flex items-center gap-2 bg-white/70 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full px-4 py-1.5 backdrop-blur-sm">
        <span class="w-2 h-2 rounded-full bg-indigo-500 dark:bg-indigo-400 animate-pulse"></span>
        <span class="text-slate-600 dark:text-slate-300/70 text-xs font-medium">ทั้งหมด <strong class="text-slate-800 dark:text-white" id="stat-total">{{ $totalPosts ?? 0 }}</strong> บทความ</span>
      </div>
    </div>
  </div>
  <div class="absolute bottom-0 left-0 right-0 h-px" style="background:linear-gradient(90deg,transparent,rgba(99,102,241,0.3),transparent);"></div>
</div>
@endsection

@section('content')

<div class="py-8" x-data="blogSearch()" x-init="init()" @hero-search.window="query = $event.detail.q; fetchPosts()" @hero-category.window="category = $event.detail.id; updateCategoryChips(); fetchPosts()">

  {{-- ============ Featured Posts ============ --}}
  @if(isset($featuredPosts) && $featuredPosts->count())
  <section class="mb-12">
    <h2 class="flex items-center gap-2 text-xl font-bold text-slate-800 dark:text-gray-100 mb-6">
      <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-md shadow-amber-500/25">
        <i class="bi bi-star-fill text-white text-sm"></i>
      </span>
      บทความแนะนำ
    </h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      {{-- Main Featured Post --}}
      @php $mainFeatured = $featuredPosts->first(); @endphp
      <article class="lg:row-span-2 group">
        <a href="{{ route('blog.show', $mainFeatured->slug) }}" class="block bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl overflow-hidden h-full blog-card transition-all duration-300">
          <div class="relative overflow-hidden aspect-[16/9] lg:aspect-auto lg:h-full lg:min-h-[340px]">
            @if($mainFeatured->featured_image)
              <img src="{{ asset('storage/' . $mainFeatured->featured_image) }}" alt="{{ $mainFeatured->title }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            @else
              <div class="w-full h-full bg-gradient-to-br from-indigo-200 to-violet-200 dark:from-indigo-900 dark:to-violet-900 flex items-center justify-center">
                <i class="bi bi-newspaper text-6xl text-indigo-300 dark:text-indigo-500"></i>
              </div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>

            <div class="absolute bottom-0 left-0 right-0 p-5 sm:p-6">
              @if($mainFeatured->category)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold text-white bg-indigo-600/85 backdrop-blur-sm mb-3 shadow-md">
                  {{ $mainFeatured->category->name }}
                </span>
              @endif
              <h3 class="text-white font-bold text-lg sm:text-xl lg:text-2xl leading-snug line-clamp-2 mb-2">{{ $mainFeatured->title }}</h3>
              @if($mainFeatured->excerpt)
                <p class="text-white/70 text-sm line-clamp-2 hidden sm:block">{{ $mainFeatured->excerpt }}</p>
              @endif
              <div class="flex items-center gap-3 text-white/60 text-xs mt-3">
                @if($mainFeatured->author)
                  <span>{{ $mainFeatured->author->full_name ?? $mainFeatured->author->first_name }}</span>
                @endif
                @if($mainFeatured->published_at)
                  <span><i class="bi bi-calendar3 mr-1"></i>{{ $mainFeatured->published_at->locale('th')->translatedFormat('j M Y') }}</span>
                @endif
                @if($mainFeatured->reading_time)
                  <span><i class="bi bi-clock mr-1"></i>{{ $mainFeatured->reading_time }} นาที</span>
                @endif
              </div>
            </div>
          </div>
        </a>
      </article>

      {{-- Secondary Featured Posts --}}
      @foreach($featuredPosts->skip(1)->take(2) as $featured)
      <article class="group">
        <a href="{{ route('blog.show', $featured->slug) }}" class="flex flex-col sm:flex-row bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl overflow-hidden blog-card transition-all duration-300 h-full">
          <div class="relative overflow-hidden sm:w-56 aspect-[16/10] sm:aspect-auto shrink-0">
            @if($featured->featured_image)
              <img src="{{ asset('storage/' . $featured->featured_image) }}" alt="{{ $featured->title }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            @else
              <div class="w-full h-full bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-900/50 dark:to-violet-900/50 flex items-center justify-center">
                <i class="bi bi-newspaper text-3xl text-indigo-300 dark:text-indigo-500"></i>
              </div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent sm:bg-gradient-to-r"></div>
          </div>
          <div class="p-5 flex flex-col justify-center flex-1">
            @if($featured->category)
              <span class="inline-flex items-center self-start px-2.5 py-0.5 rounded-full text-xs font-medium text-indigo-600 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-500/10 mb-2">
                {{ $featured->category->name }}
              </span>
            @endif
            <h3 class="font-semibold text-sm sm:text-base leading-relaxed line-clamp-2 mb-2 text-slate-800 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors">{{ $featured->title }}</h3>
            <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
              @if($featured->published_at)
                <span>{{ $featured->published_at->locale('th')->translatedFormat('j M Y') }}</span>
              @endif
              @if($featured->reading_time)
                <span><i class="bi bi-clock mr-1"></i>{{ $featured->reading_time }} นาที</span>
              @endif
            </div>
          </div>
        </a>
      </article>
      @endforeach
    </div>
  </section>
  @endif

  {{-- ============ Main Content + Sidebar ============ --}}
  <div class="flex flex-col lg:flex-row gap-8">

    {{-- Main Content --}}
    <div class="flex-1 min-w-0">

      {{-- Sort Row --}}
      <div class="flex items-center justify-between mb-6 gap-3">
        <h2 class="text-xl font-bold text-slate-800 dark:text-gray-100 flex items-center gap-2">
          <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-md shadow-indigo-500/25">
            <i class="bi bi-newspaper text-white text-sm"></i>
          </span>
          บทความล่าสุด
        </h2>
        <select x-model="sort" @change="fetchPosts()"
                class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-slate-800 text-xs font-medium text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer transition-colors hover:border-indigo-300 dark:hover:border-indigo-400/40">
          <option value="latest">ล่าสุด</option>
          <option value="popular">ยอดนิยม</option>
          <option value="title">ชื่อ ก-ฮ</option>
        </select>
      </div>

      {{-- Active Filters --}}
      <template x-if="hasActiveFilters">
        <div class="flex items-center gap-3 mb-5 flex-wrap">
          <span class="text-xs text-gray-500 dark:text-gray-400">ตัวกรอง:</span>
          <template x-if="query">
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-400/30">
              <i class="bi bi-search"></i> "<span x-text="query"></span>"
              <button @click="query=''; clearHeroSearch(); fetchPosts()" class="ml-1 hover:text-indigo-800 dark:hover:text-indigo-200"><i class="bi bi-x"></i></button>
            </span>
          </template>
          <button @click="clearAll()" class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold text-rose-600 dark:text-rose-300 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-400/30 hover:bg-rose-100 dark:hover:bg-rose-500/20 transition cursor-pointer active:scale-95">
            <i class="bi bi-x-circle"></i> ล้างทั้งหมด
          </button>
        </div>
      </template>

      {{-- Loading --}}
      <div x-show="loading" x-transition.opacity class="flex items-center justify-center py-16">
        <div class="flex flex-col items-center gap-3">
          <div class="w-10 h-10 border-[3px] border-indigo-100 dark:border-white/10 border-t-indigo-500 dark:border-t-indigo-400 rounded-full animate-spin"></div>
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">กำลังโหลดบทความ...</span>
        </div>
      </div>

      {{-- Posts Grid --}}
      <div x-show="!loading" x-transition.opacity>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="blog-posts-grid">
          @foreach($posts as $post)
            @include('public.blog._post-card', ['post' => $post])
          @endforeach

          @if($posts->isEmpty())
          <div class="col-span-full">
            <div class="text-center py-20 px-6 rounded-3xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10">
              <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-gray-100 to-gray-50 dark:from-slate-700 dark:to-slate-800 mb-5 shadow-inner">
                <i class="bi bi-journal-x text-5xl text-gray-300 dark:text-slate-500"></i>
              </div>
              <p class="text-slate-700 dark:text-gray-100 font-bold mb-1 text-lg">ไม่พบบทความ</p>
              <p class="text-gray-500 dark:text-gray-400 text-sm">ลองเปลี่ยนคำค้นหาหรือตัวกรอง</p>
            </div>
          </div>
          @endif
        </div>

        {{-- Pagination --}}
        <div id="blog-pagination" class="my-12">
          @include('public.blog._pagination', ['posts' => $posts])
        </div>
      </div>
    </div>

    {{-- Sidebar (desktop only) --}}
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
/* Blog card hover */
.blog-card { will-change: transform; }
.blog-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -12px rgba(0,0,0,0.12); }

/* Fade-in animation for cards */
.blog-card-wrap { animation: blogCardFadeIn 0.4s ease-out both; }
.blog-card-wrap:nth-child(1) { animation-delay: 0.02s; }
.blog-card-wrap:nth-child(2) { animation-delay: 0.06s; }
.blog-card-wrap:nth-child(3) { animation-delay: 0.10s; }
.blog-card-wrap:nth-child(4) { animation-delay: 0.14s; }
.blog-card-wrap:nth-child(5) { animation-delay: 0.18s; }
.blog-card-wrap:nth-child(6) { animation-delay: 0.22s; }
.blog-card-wrap:nth-child(7) { animation-delay: 0.26s; }
.blog-card-wrap:nth-child(8) { animation-delay: 0.30s; }
.blog-card-wrap:nth-child(9) { animation-delay: 0.34s; }
@keyframes blogCardFadeIn {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Hide scrollbar for category chips */
#hero-category-chips::-webkit-scrollbar { width: 0; height: 0; }
#hero-category-chips { scrollbar-width: none; }
</style>
@endpush

@push('scripts')
<script>
function blogSearch() {
  return {
    query: '{{ request("q") }}',
    category: '{{ request("category") }}',
    sort: '{{ request("sort", "latest") }}',
    loading: false,
    total: {{ $posts->total() }},
    showing: {{ $posts->count() }},

    get hasActiveFilters() {
      return this.query !== '' || this.category !== '' || this.sort !== 'latest';
    },

    init() {
      const heroInput = document.getElementById('hero-search-input');
      const clearBtn = document.getElementById('hero-clear-btn');
      if (heroInput) {
        heroInput.value = this.query;
        if (clearBtn) clearBtn.classList.toggle('hidden', !this.query);
        heroInput.addEventListener('input', () => {
          if (clearBtn) clearBtn.classList.toggle('hidden', !heroInput.value);
        });
      }
      this.updateCategoryChips();
      this.bindPagination();
    },

    updateCategoryChips() {
      const activeClasses = ['bg-gradient-to-br','from-indigo-500','to-violet-600','text-white','border-transparent','shadow-md','shadow-indigo-500/30'];
      const inactiveClasses = ['bg-white/80','dark:bg-white/5','text-slate-700','dark:text-slate-300','border-gray-200','dark:border-white/10','hover:bg-indigo-50','dark:hover:bg-white/10','hover:text-indigo-600','dark:hover:text-indigo-300','hover:border-indigo-300','dark:hover:border-indigo-400/40'];
      document.querySelectorAll('.hero-cat-chip').forEach(chip => {
        const catId = chip.dataset.cat;
        if (catId === (this.category || '')) {
          inactiveClasses.forEach(c => chip.classList.remove(c));
          activeClasses.forEach(c => chip.classList.add(c));
        } else {
          activeClasses.forEach(c => chip.classList.remove(c));
          inactiveClasses.forEach(c => chip.classList.add(c));
        }
      });
    },

    clearHeroSearch() {
      const heroInput = document.getElementById('hero-search-input');
      if (heroInput) heroInput.value = '';
      const clearBtn = document.getElementById('hero-clear-btn');
      if (clearBtn) clearBtn.classList.add('hidden');
    },

    clearAll() {
      this.query = '';
      this.category = '';
      this.sort = 'latest';
      this.clearHeroSearch();
      this.updateCategoryChips();
      this.fetchPosts();
    },

    async fetchPosts(page) {
      this.loading = true;
      const params = new URLSearchParams();
      if (this.query) params.set('q', this.query);
      if (this.category) params.set('category', this.category);
      if (this.sort && this.sort !== 'latest') params.set('sort', this.sort);
      if (page) params.set('page', page);

      const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
      history.replaceState(null, '', newUrl);

      try {
        const res = await fetch(`{{ route("blog.index") }}?${params.toString()}`, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        document.getElementById('blog-posts-grid').innerHTML = data.html;
        document.getElementById('blog-pagination').innerHTML = data.pagination;
        this.total = data.total;
        this.showing = data.showing;

        const statEl = document.getElementById('stat-total');
        if (statEl) statEl.textContent = data.total;

        this.bindPagination();
      } catch (e) {
        console.error('Blog search failed:', e);
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
