@extends('layouts.app')

@section('title', 'อีเวนต์')

@section('hero')
{{-- Hero Search Bar --}}
<div class="relative overflow-hidden bg-gradient-to-br from-pink-50 via-indigo-50 to-violet-50 dark:from-slate-900 dark:via-indigo-950 dark:to-purple-950">
  <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(circle at 20% 50%,rgba(99,102,241,0.10) 0%,transparent 50%),radial-gradient(circle at 80% 20%,rgba(139,92,246,0.10) 0%,transparent 50%);"></div>
  <div class="absolute inset-0 pointer-events-none opacity-60 dark:opacity-100" style="background-image:url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 40L40 0M-10 10L10-10M30 50L50 30&quot; stroke=&quot;rgba(100,116,139,0.06)&quot; stroke-width=&quot;1&quot;/%3E%3C/svg%3E');"></div>

  {{-- Decorative blobs --}}
  <div class="absolute w-96 h-96 rounded-full bg-pink-400/15 dark:bg-indigo-500/20 blur-3xl top-[-100px] right-[-100px] pointer-events-none"></div>
  <div class="absolute w-80 h-80 rounded-full bg-violet-400/15 dark:bg-rose-500/15 blur-3xl bottom-[-80px] left-[-80px] pointer-events-none"></div>

  <div class="relative max-w-6xl mx-auto px-4 py-12 md:py-16">
    {{-- Breadcrumb nav --}}
    <nav aria-label="Breadcrumb" class="mb-5 max-w-2xl mx-auto">
      <ol class="flex items-center justify-center gap-2 text-xs text-slate-600 dark:text-slate-400 flex-wrap">
        <li><a href="{{ url('/') }}" class="hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"><i class="bi bi-house-door mr-1"></i>หน้าแรก</a></li>
        <li class="text-slate-400 dark:text-slate-500"><i class="bi bi-chevron-right text-[10px]"></i></li>
        <li class="font-semibold text-slate-800 dark:text-gray-100">อีเวนต์</li>
      </ol>
    </nav>

    {{-- Title --}}
    <div class="text-center mb-8">
      <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold backdrop-blur-md border bg-white/70 dark:bg-white/10 border-indigo-200/60 dark:border-white/10 text-indigo-700 dark:text-indigo-200 shadow-sm mb-4">
        <i class="bi bi-person-bounding-box"></i> ค้นหาด้วยใบหน้า · จ่ายแล้วดาวน์โหลดทันที
      </span>
      <h1 class="font-extrabold text-3xl sm:text-4xl md:text-5xl lg:text-6xl tracking-tight leading-[1.2] mb-3">
        <span class="bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 dark:from-indigo-300 dark:via-violet-300 dark:to-fuchsia-300 bg-clip-text text-transparent">ค้นหาภาพของคุณจากงาน</span>
      </h1>
      <p class="text-slate-600 dark:text-slate-300/80 text-sm sm:text-base max-w-2xl mx-auto">
        เลือกงานที่คุณเข้าร่วม → อัปโหลดเซลฟี่ → ระบบ AI หารูปคุณให้ทุกภาพ
        <span class="block mt-1 text-xs text-slate-500 dark:text-slate-400">
          จ่ายด้วย PromptPay / บัตรเครดิต · ได้ลิงก์ดาวน์โหลดทันทีในอีเมล · ความละเอียดสูงไม่มีลายน้ำ
        </span>
      </p>
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
                 placeholder="พิมพ์ชื่องาน, สถานที่, หรือคำค้นหา..."
                 value="{{ request('q') }}"
                 autocomplete="off"
                 @input.debounce.350ms="$dispatch('hero-search', { q: $el.value })">
          <button type="button" id="hero-clear-btn" class="hidden pr-4 text-gray-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-white transition" @click="$el.previousElementSibling.value=''; $dispatch('hero-search', {q:''})">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>
    </div>

    {{-- Stats pills --}}
    <div class="flex items-center justify-center gap-3 mt-6 flex-wrap">
      <div class="inline-flex items-center gap-2 bg-white/70 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full px-4 py-1.5 backdrop-blur-sm">
        <span class="w-2 h-2 rounded-full bg-indigo-500 dark:bg-indigo-400 animate-pulse"></span>
        <span class="text-slate-600 dark:text-slate-300/70 text-xs font-medium">ทั้งหมด <strong class="text-slate-800 dark:text-white" id="stat-total">{{ $stats['total'] ?? 0 }}</strong> อีเวนต์</span>
      </div>
      @if(($stats['free'] ?? 0) > 0)
      <div class="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 dark:border-emerald-400/30 rounded-full px-4 py-1.5 backdrop-blur-sm">
        <i class="bi bi-gift-fill text-emerald-500 dark:text-emerald-300 text-xs"></i>
        <span class="text-emerald-700 dark:text-emerald-200 text-xs font-medium"><strong>{{ $stats['free'] }}</strong> ฟรี</span>
      </div>
      @endif
      @if(($stats['this_month'] ?? 0) > 0)
      <div class="inline-flex items-center gap-2 bg-amber-500/10 border border-amber-500/20 dark:border-amber-400/30 rounded-full px-4 py-1.5 backdrop-blur-sm">
        <i class="bi bi-calendar-star text-amber-500 dark:text-amber-300 text-xs"></i>
        <span class="text-amber-700 dark:text-amber-200 text-xs font-medium"><strong>{{ $stats['this_month'] }}</strong> เดือนนี้</span>
      </div>
      @endif
    </div>
  </div>
  <div class="absolute bottom-0 left-0 right-0 h-px" style="background:linear-gradient(90deg,transparent,rgba(99,102,241,0.3),transparent);"></div>
</div>
@endsection

@section('content')

{{-- Face Search instructional banner — shown when buyer arrived from
     the home "ค้นหาด้วยใบหน้า" CTA (which can't link straight to a
     face-search page because the route needs an event_id). The banner
     tells them what to do next: pick an event, then use Face Search
     inside it. Only renders for ?action=face-search to avoid noise on
     the regular events listing flow. --}}
@if(request('action') === 'face-search')
<div class="mt-6">
  <div class="relative overflow-hidden rounded-2xl border border-amber-300/40 dark:border-amber-400/30
              bg-gradient-to-r from-amber-50 via-orange-50 to-rose-50
              dark:from-amber-950/30 dark:via-orange-950/30 dark:to-rose-950/30
              p-5 sm:p-6 shadow-sm">
    <div class="absolute -right-8 -top-8 w-40 h-40 rounded-full bg-amber-300/20 blur-3xl pointer-events-none"></div>
    <div class="absolute -left-8 -bottom-8 w-40 h-40 rounded-full bg-rose-300/20 blur-3xl pointer-events-none"></div>
    <div class="relative flex flex-col sm:flex-row items-start sm:items-center gap-4">
      <div class="shrink-0 w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-lg shadow-amber-500/30">
        <i class="bi bi-person-bounding-box text-2xl text-white"></i>
      </div>
      <div class="flex-1 min-w-0">
        <h3 class="font-bold text-base sm:text-lg text-amber-900 dark:text-amber-100 leading-tight">
          ค้นหารูปด้วยใบหน้า — เลือกอีเวนต์ของคุณก่อน
        </h3>
        <p class="mt-1 text-sm text-amber-800/90 dark:text-amber-200/80 leading-relaxed">
          กดเข้าไปในอีเวนต์ที่คุณเข้าร่วม → ภายในหน้าอีเวนต์จะมีปุ่ม
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-200/60 dark:bg-amber-500/20 text-amber-900 dark:text-amber-200 font-semibold text-xs">
            <i class="bi bi-search"></i> ค้นหารูปของฉัน
          </span>
          อัปโหลดเซลฟี่ของคุณ ระบบ AI จะหารูปคุณจากทุกภาพในอีเวนต์ให้
        </p>
      </div>
    </div>
  </div>
</div>
@endif

{{-- Sponsored search-page ad — only renders when an active ad_creative
     with placement='search_inline' exists. Sits between the hero and
     the filter chips so it's above-the-fold but doesn't push results down. --}}
<div class="pt-6">
  <x-ad-slot placement="search_inline" />
</div>

{{-- ============ Filter + Results (Alpine.js realtime) ============ --}}
<div class="py-8" x-data="eventSearch()" x-init="init()" @hero-search.window="query = $event.detail.q; fetchEvents()">

  {{-- Category Chips --}}
  <div class="flex items-center gap-2 overflow-x-auto pb-2 mb-6 -mx-1 px-1" style="scrollbar-width:none;">
    <button @click="category=''; fetchEvents()"
            :class="category === '' ? 'bg-gradient-to-br from-indigo-500 to-violet-600 text-white border-transparent shadow-lg shadow-indigo-500/30 scale-105' : 'bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-white/10 hover:border-indigo-300 dark:hover:border-indigo-400/40 hover:text-indigo-600 dark:hover:text-indigo-300 hover:scale-105'"
            class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-semibold border transition-all duration-200 cursor-pointer active:scale-95">
      <i class="bi bi-grid-3x3-gap"></i> ทั้งหมด
    </button>
    @foreach($categories as $cat)
    <button @click="category = (category === '{{ $cat->id }}') ? '' : '{{ $cat->id }}'; fetchEvents()"
            :class="category === '{{ $cat->id }}' ? 'bg-gradient-to-br from-indigo-500 to-violet-600 text-white border-transparent shadow-lg shadow-indigo-500/30 scale-105' : 'bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-white/10 hover:border-indigo-300 dark:hover:border-indigo-400/40 hover:text-indigo-600 dark:hover:text-indigo-300 hover:scale-105'"
            class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-semibold border transition-all duration-200 cursor-pointer active:scale-95">
      @if($cat->icon)<i class="{{ $cat->icon }}"></i>@endif
      {{ $cat->name }}
      @if($cat->events_count > 0)
      <span class="opacity-70">({{ $cat->events_count }})</span>
      @endif
    </button>
    @endforeach
  </div>

  {{-- Filter Row --}}
  <div class="flex flex-wrap items-center gap-3 mb-8">
    {{-- Sort --}}
    <select x-model="sort" @change="fetchEvents()"
            class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-slate-800 text-xs font-medium text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer transition-colors hover:border-indigo-300 dark:hover:border-indigo-400/40">
      <option value="latest">ล่าสุด</option>
      <option value="popular">ยอดนิยม</option>
      <option value="name">ชื่อ ก-ฮ</option>
      <option value="price_low">ราคาต่ำ-สูง</option>
      <option value="price_high">ราคาสูง-ต่ำ</option>
    </select>

    {{-- Price filter --}}
    <select x-model="price" @change="fetchEvents()"
            class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-slate-800 text-xs font-medium text-gray-600 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer transition-colors hover:border-indigo-300 dark:hover:border-indigo-400/50">
      <option value="">ทุกราคา</option>
      <option value="free">ฟรี</option>
      <option value="paid">มีค่าใช้จ่าย</option>
    </select>

    {{-- Province --}}
    @if($provinces->count() > 0)
    <select x-model="province" @change="fetchEvents()"
            class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-slate-800 text-xs font-medium text-gray-600 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer transition-colors hover:border-indigo-300 dark:hover:border-indigo-400/50">
      <option value="">ทุกจังหวัด</option>
      @foreach($provinces as $prov)
      <option value="{{ $prov->id }}">{{ $prov->name_th }}</option>
      @endforeach
    </select>
    @endif

    {{-- Active filters clear --}}
    <template x-if="hasActiveFilters">
      <button @click="clearAll()" class="inline-flex items-center gap-1 px-3 py-2 rounded-xl text-xs font-semibold text-rose-600 dark:text-rose-300 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 hover:bg-rose-100 dark:hover:bg-rose-500/20 transition cursor-pointer active:scale-95">
        <i class="bi bi-x-circle"></i> ล้างตัวกรอง
      </button>
    </template>

    {{-- Results count --}}
    <div class="ml-auto text-xs text-gray-400 dark:text-gray-500 font-medium" x-show="!loading">
      <span x-text="resultText"></span>
    </div>
  </div>

  {{-- Loading indicator --}}
  <div x-show="loading" x-transition.opacity class="flex items-center justify-center py-16">
    <div class="flex flex-col items-center gap-3">
      <div class="w-10 h-10 border-[3px] border-indigo-100 dark:border-indigo-500/20 border-t-indigo-500 dark:border-t-indigo-400 rounded-full animate-spin"></div>
      <span class="text-gray-400 dark:text-gray-500 text-sm font-medium">กำลังค้นหา...</span>
    </div>
  </div>

  {{-- Events Grid --}}
  <div x-show="!loading" x-transition.opacity>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="events-grid">
      @include('public.events._grid', ['events' => $events])
    </div>

    {{-- Empty state when no results --}}
    @if($events->count() === 0)
    <div class="col-span-full">
      <div class="text-center py-20 px-6 rounded-3xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 mt-4">
        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-gray-100 to-gray-50 dark:from-slate-700 dark:to-slate-800 mb-5 shadow-inner">
          <i class="bi bi-search text-5xl text-gray-300 dark:text-slate-500"></i>
        </div>
        <p class="text-slate-700 dark:text-gray-100 font-bold mb-1 text-lg">ไม่พบอีเวนต์</p>
        <p class="text-gray-500 dark:text-gray-400 text-sm mb-5">ลองเปลี่ยนคำค้นหา หรือเลือกหมวดหมู่อื่น</p>
        <button type="button" @click="clearAll()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-arrow-clockwise"></i> ล้างตัวกรอง
        </button>
      </div>
    </div>
    @endif

    {{-- Pagination --}}
    <div id="events-pagination" class="my-12">
      @include('public.events._pagination', ['events' => $events])
    </div>
  </div>

</div>

@push('scripts')
<script>
function eventSearch() {
  return {
    query: '{{ request("q") }}',
    category: '{{ request("category") }}',
    sort: '{{ request("sort", "latest") }}',
    price: '{{ request("price") }}',
    province: '{{ request("province") }}',
    loading: false,
    total: {{ $events->total() }},
    showing: {{ $events->count() }},
    debounceTimer: null,

    get hasActiveFilters() {
      return this.query !== '' || this.category !== '' || this.price !== '' || this.province !== '' || this.sort !== 'latest';
    },

    get resultText() {
      if (this.total === 0) return '';
      if (this.showing === this.total) return `${this.total} อีเวนต์`;
      return `${this.showing} จาก ${this.total} อีเวนต์`;
    },

    init() {
      // Sync hero search input
      const heroInput = document.getElementById('hero-search-input');
      const clearBtn = document.getElementById('hero-clear-btn');
      if (heroInput) {
        heroInput.value = this.query;
        if (clearBtn) clearBtn.classList.toggle('hidden', !this.query);
        heroInput.addEventListener('input', () => {
          if (clearBtn) clearBtn.classList.toggle('hidden', !heroInput.value);
        });
      }
    },

    clearAll() {
      this.query = '';
      this.category = '';
      this.sort = 'latest';
      this.price = '';
      this.province = '';
      const heroInput = document.getElementById('hero-search-input');
      if (heroInput) heroInput.value = '';
      const clearBtn = document.getElementById('hero-clear-btn');
      if (clearBtn) clearBtn.classList.add('hidden');
      this.fetchEvents();
    },

    async fetchEvents(page) {
      this.loading = true;

      const params = new URLSearchParams();
      if (this.query) params.set('q', this.query);
      if (this.category) params.set('category', this.category);
      if (this.sort && this.sort !== 'latest') params.set('sort', this.sort);
      if (this.price) params.set('price', this.price);
      if (this.province) params.set('province', this.province);
      if (page) params.set('page', page);

      // Update URL without reload
      const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
      history.replaceState(null, '', newUrl);

      try {
        const res = await fetch(`{{ route("events.index") }}?${params.toString()}`, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        document.getElementById('events-grid').innerHTML = data.html;
        document.getElementById('events-pagination').innerHTML = data.pagination;
        this.total = data.total;
        this.showing = data.showing;

        // Update stat in hero
        const statEl = document.getElementById('stat-total');
        if (statEl) statEl.textContent = data.total;

        // Rebind pagination clicks
        this.bindPagination();
      } catch (e) {
        console.error('Search failed:', e);
      } finally {
        this.loading = false;
      }
    },

    bindPagination() {
      document.querySelectorAll('#events-pagination a[href]').forEach(link => {
        link.addEventListener('click', (e) => {
          e.preventDefault();
          const url = new URL(link.href);
          const page = url.searchParams.get('page');
          if (page) {
            this.fetchEvents(page);
            window.scrollTo({ top: 0, behavior: 'smooth' });
          }
        });
      });
    }
  };
}

// Initial pagination binding
document.addEventListener('DOMContentLoaded', () => {
  // Handled by Alpine init
});
</script>
@endpush

<style>
.event-card { will-change: transform; }
.event-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -12px rgba(0,0,0,0.12); }
:root.dark .event-card:hover { box-shadow: 0 20px 40px -12px rgba(0,0,0,0.45); }
.event-card:hover img { transform: scale(1.06); }
.event-card img { transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1); }

/* Hide scrollbar for category chips (scoped to category scrollers only, not global) */

/* Fade-in animation for cards */
.event-card-wrap { animation: cardFadeIn 0.4s ease-out both; }
.event-card-wrap:nth-child(1) { animation-delay: 0.02s; }
.event-card-wrap:nth-child(2) { animation-delay: 0.06s; }
.event-card-wrap:nth-child(3) { animation-delay: 0.10s; }
.event-card-wrap:nth-child(4) { animation-delay: 0.14s; }
.event-card-wrap:nth-child(5) { animation-delay: 0.18s; }
.event-card-wrap:nth-child(6) { animation-delay: 0.22s; }
.event-card-wrap:nth-child(7) { animation-delay: 0.26s; }
.event-card-wrap:nth-child(8) { animation-delay: 0.30s; }
@keyframes cardFadeIn {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
@endsection
