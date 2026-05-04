@extends('layouts.app')

@section('title', 'หน้าแรก')

@section('hero')
{{-- Hero Section --}}
<section class="relative overflow-hidden min-h-[60vh] bg-gradient-to-br from-pink-50 via-indigo-50 to-violet-50 dark:from-slate-900 dark:via-indigo-950 dark:to-purple-950"
         x-data="{ mx: 50, my: 50 }"
         @mousemove="mx = ($event.clientX / window.innerWidth) * 100; my = ($event.clientY / window.innerHeight) * 100;">
  {{-- Mouse-follow glow (optional, subtle) --}}
  <div class="absolute inset-0 pointer-events-none transition-[background] duration-500 ease-out"
       :style="`background: radial-gradient(600px circle at ${mx}% ${my}%, rgba(139,92,246,0.10), transparent 60%);`"></div>

  {{-- Pattern overlay --}}
  <div class="absolute inset-0 pointer-events-none opacity-60 dark:opacity-100" style="background-image:url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0z&quot; fill=&quot;none&quot;/%3E%3Cpath d=&quot;M0 40L40 0M-10 10L10-10M30 50L50 30&quot; stroke=&quot;rgba(100,116,139,0.08)&quot; stroke-width=&quot;1&quot;/%3E%3C/svg%3E');"></div>

  {{-- Decorative blobs: light mode --}}
  <div class="absolute dark:hidden" style="width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(236,72,153,0.25) 0%,transparent 70%);top:-200px;right:-100px;"></div>
  <div class="absolute dark:hidden" style="width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(139,92,246,0.22) 0%,transparent 70%);bottom:-100px;left:-100px;"></div>

  {{-- Decorative blobs: dark mode --}}
  <div class="absolute hidden dark:block" style="width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(99,102,241,0.22) 0%,transparent 70%);top:-200px;right:-100px;"></div>
  <div class="absolute hidden dark:block" style="width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(244,63,94,0.15) 0%,transparent 70%);bottom:-100px;left:-100px;"></div>

  <div class="max-w-7xl mx-auto px-4 relative py-12 md:py-16">
    <div class="grid grid-cols-1 lg:grid-cols-12 items-center gap-8 py-4">
      <div class="lg:col-span-7 relative z-[1]">
        {{-- Badge --}}
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold backdrop-blur-md border bg-white/70 dark:bg-white/10 border-indigo-200/60 dark:border-white/10 text-indigo-700 dark:text-indigo-200 shadow-sm mb-5">
          <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
          แพลตฟอร์มภาพอีเวนต์ อันดับ 1 ในไทย
        </span>

        {{-- Headline --}}
        <h1 class="font-extrabold mb-5 text-4xl sm:text-5xl lg:text-6xl xl:text-7xl leading-[1.15] tracking-tight">
          <span class="block bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 dark:from-indigo-300 dark:via-violet-300 dark:to-fuchsia-300 bg-clip-text text-transparent">
            ค้นหาและซื้อรูปภาพ
          </span>
          <span class="block bg-gradient-to-br from-pink-500 via-rose-500 to-orange-400 dark:from-pink-400 dark:via-rose-300 dark:to-amber-300 bg-clip-text text-transparent">
            จากงานอีเวนต์ของคุณ
          </span>
          <span class="block mt-2 text-slate-800 dark:text-white text-2xl sm:text-3xl lg:text-4xl font-bold">
            คุณภาพระดับมืออาชีพ
            <i class="bi bi-stars text-amber-500 dark:text-amber-300"></i>
          </span>
        </h1>

        <p class="mb-6 text-base sm:text-lg text-slate-600 dark:text-slate-300/80 max-w-xl" style="line-height:1.7;">
          เรียกดูและซื้อภาพถ่ายจากงานอีเวนต์ต่างๆ ได้ง่ายดาย รวดเร็ว พร้อมดาวน์โหลดทันทีที่ชำระเงิน
        </p>

        {{-- Search --}}
        <form action="{{ route('events.index') }}" method="GET" class="hero-search mb-6 max-w-xl">
          <div class="relative group">
            <div class="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 to-violet-500 rounded-2xl blur opacity-25 group-hover:opacity-50 transition-opacity"></div>
            <div class="relative flex items-center bg-white dark:bg-slate-900/80 backdrop-blur-xl border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-lg shadow-indigo-500/10 dark:shadow-black/30">
              <span class="pl-5 pr-2 text-gray-400 dark:text-slate-500"><i class="bi bi-search text-lg"></i></span>
              <input type="text" name="q" class="flex-1 bg-transparent border-0 text-slate-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-slate-500 py-3.5 px-2 text-base focus:outline-none focus:ring-0" placeholder="ค้นหาอีเวนต์...">
              <button type="submit" class="m-1.5 px-5 py-2.5 rounded-xl font-semibold text-white bg-gradient-to-br from-indigo-500 to-violet-600 hover:shadow-xl hover:shadow-indigo-500/30 transition-all duration-200">ค้นหา</button>
            </div>
          </div>
        </form>

        {{-- CTA Buttons --}}
        <div class="flex flex-wrap items-center gap-3 mb-8">
          <a href="{{ route('events.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all duration-200">
            <i class="bi bi-grid-3x3-gap-fill"></i> ดูอีเวนต์ทั้งหมด
          </a>
          <a href="{{ route('events.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold border-2 border-indigo-500/80 dark:border-indigo-300/50 text-indigo-600 dark:text-indigo-200 bg-white/70 dark:bg-white/5 backdrop-blur-sm hover:bg-indigo-50 dark:hover:bg-white/10 hover:-translate-y-0.5 hover:shadow-lg transition-all duration-200">
            <i class="bi bi-person-bounding-box"></i> ค้นหาด้วยใบหน้า
          </a>
        </div>

        {{-- Stats – glass-morphism cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 max-w-xl">
          {{-- Events --}}
          <div class="group relative rounded-2xl p-4 backdrop-blur-xl border bg-white/70 dark:bg-white/10 border-white/60 dark:border-white/10 shadow-lg shadow-indigo-500/5 dark:shadow-black/20 hover:-translate-y-1 transition-all duration-300">
            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md mb-2">
              <i class="bi bi-calendar-event text-sm"></i>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white leading-none hero-stat-number">{{ $categories->sum('events_count') ?? 0 }}+</div>
            <div class="text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-300/70 mt-1 uppercase tracking-wider">อีเวนต์</div>
          </div>
          {{-- Photographers --}}
          <div class="group relative rounded-2xl p-4 backdrop-blur-xl border bg-white/70 dark:bg-white/10 border-white/60 dark:border-white/10 shadow-lg shadow-indigo-500/5 dark:shadow-black/20 hover:-translate-y-1 transition-all duration-300" style="animation-delay:.15s">
            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-pink-500 to-rose-600 text-white shadow-md mb-2">
              <i class="bi bi-camera-fill text-sm"></i>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white leading-none hero-stat-number">{{ isset($photographers) ? $photographers->count() : 0 }}</div>
            <div class="text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-300/70 mt-1 uppercase tracking-wider">ช่างภาพ</div>
          </div>
          {{-- Quality --}}
          <div class="group relative rounded-2xl p-4 backdrop-blur-xl border bg-white/70 dark:bg-white/10 border-white/60 dark:border-white/10 shadow-lg shadow-indigo-500/5 dark:shadow-black/20 hover:-translate-y-1 transition-all duration-300" style="animation-delay:.3s">
            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md mb-2">
              <i class="bi bi-image-fill text-sm"></i>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white leading-none hero-stat-number">HD</div>
            <div class="text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-300/70 mt-1 uppercase tracking-wider">คุณภาพสูง</div>
          </div>
          {{-- Fast delivery --}}
          <div class="group relative rounded-2xl p-4 backdrop-blur-xl border bg-white/70 dark:bg-white/10 border-white/60 dark:border-white/10 shadow-lg shadow-indigo-500/5 dark:shadow-black/20 hover:-translate-y-1 transition-all duration-300" style="animation-delay:.45s">
            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-md mb-2">
              <i class="bi bi-lightning-charge-fill text-sm"></i>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white leading-none hero-stat-number">24/7</div>
            <div class="text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-300/70 mt-1 uppercase tracking-wider">พร้อมใช้งาน</div>
          </div>
        </div>
      </div>

      {{-- Optional decorative visual on large screens --}}
      <div class="hidden lg:block lg:col-span-5 relative z-[1]">
        <div class="relative">
          <div class="absolute -inset-6 bg-gradient-to-tr from-indigo-400/20 via-fuchsia-400/20 to-amber-400/20 dark:from-indigo-500/20 dark:via-fuchsia-500/20 dark:to-amber-500/10 rounded-3xl blur-3xl"></div>
          <div class="relative rounded-3xl overflow-hidden border border-white/60 dark:border-white/10 backdrop-blur-xl bg-white/50 dark:bg-white/5 p-6 shadow-2xl shadow-indigo-500/10 dark:shadow-black/40">
            <div class="grid grid-cols-2 gap-3">
              <div class="aspect-square rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white"><i class="bi bi-camera-reels text-4xl"></i></div>
              <div class="aspect-square rounded-2xl bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center text-white"><i class="bi bi-heart-fill text-4xl"></i></div>
              <div class="aspect-square rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white"><i class="bi bi-image-fill text-4xl"></i></div>
              <div class="aspect-square rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white"><i class="bi bi-stars text-4xl"></i></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@section('content')

{{-- ════════════════════════════════════════════════════════════════════
     Promo Banner — links to /promo with the 3 USPs
     Subtle, dismissable, scoped to home page only.
     ════════════════════════════════════════════════════════════════════ --}}
<section class="mb-10 md:mb-12 fade-up" x-data="{ show: localStorage.getItem('promo_banner_dismissed') !== '1' }" x-show="show" x-transition>
  <div class="relative rounded-3xl overflow-hidden p-5 md:p-7
              bg-gradient-to-r from-emerald-500 via-indigo-500 to-pink-500
              shadow-xl shadow-indigo-500/30 border border-white/20">
    <div class="absolute inset-0 pointer-events-none opacity-20"
         style="background-image: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.4), transparent 50%), radial-gradient(circle at 80% 50%, rgba(255,255,255,0.3), transparent 50%);"></div>
    <button type="button"
            @click="show = false; localStorage.setItem('promo_banner_dismissed', '1');"
            class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center backdrop-blur-md transition z-10"
            aria-label="ปิด">
      <i class="bi bi-x-lg text-sm"></i>
    </button>
    <div class="relative flex items-center gap-4 md:gap-6 flex-wrap">
      <div class="hidden sm:flex w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-white/25 backdrop-blur-md items-center justify-center text-2xl md:text-3xl text-white shadow-lg shrink-0">
        <i class="bi bi-stars"></i>
      </div>
      <div class="flex-1 min-w-0 text-white">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-[10px] uppercase tracking-widest font-bold bg-white/25 backdrop-blur-md px-2 py-0.5 rounded-full">ใหม่</span>
          <span class="text-xs font-semibold opacity-90">เร็ว · แม่นยำ · เข้า LINE ทันที</span>
        </div>
        <h3 class="text-base md:text-xl font-extrabold leading-tight">
          จ่ายผ่าน PromptPay → รับรูปเข้า LINE · มี AI หาใบหน้าตัวเองในงาน
        </h3>
      </div>
      {{-- Unified primary CTA pattern. Class list intentionally repeated
           verbatim across home/promo so brand looks consistent and any
           future tweak only needs sed-replace. The `ring-inset` keeps the
           border crisp against any hero background tone (gradient, dark,
           light, etc.) — solves the "button blends into bg" failure mode
           on light-mode systems. --}}
      {{-- IMPORTANT: bg uses `rgb(255,255,255)` instead of `#ffffff` to dodge
           darkmode.css's `[data-bs-theme="dark"] [style*="background:#fff..."]`
           attribute-selector !important rule that re-tints any inline-styled
           white element to slate-800 on dark mode. The `bg-white` Tailwind
           class is similarly hijacked by `[data-bs-theme="dark"] .bg-white`,
           so we avoid both. Color stays #4338ca (no `color:#fff/black` matcher
           exists in darkmode.css for indigo-700). --}}
      <a href="{{ route('promo') }}"
         class="group inline-flex items-center gap-2 px-4 md:px-5 py-2.5 rounded-xl text-sm font-bold
                ring-1 ring-inset ring-indigo-200/50
                shadow-lg shadow-indigo-900/20
                hover:-translate-y-0.5 hover:shadow-xl hover:shadow-indigo-900/30 hover:ring-indigo-300
                transition shrink-0"
         style="background:rgb(255,255,255);color:#4338ca;">
        ดูจุดเด่นของเรา
        <i class="bi bi-arrow-right transition-transform group-hover:translate-x-0.5"></i>
      </a>
    </div>
  </div>
</section>

{{-- Sponsored homepage banner — auto-fills only when an active
     ad_creative with placement='homepage_banner' exists. Renders nothing
     when no ad pool is available, so it never leaves a hole on the page. --}}
<x-ad-slot placement="homepage_banner" />

{{-- ════════════════════════════════════════════════════════════════════
     TRUST STRIP — adaptive counters (founding/growing/mature)
     Pulled from App\Support\PlatformStats so the copy stays honest:
     when DB counts are tiny we show momentum/process framing instead
     of "ช่างภาพ 7 คน" which would feel like an empty marketplace.
     ════════════════════════════════════════════════════════════════════ --}}
<section class="mb-10 md:mb-12 fade-up">
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
    @foreach(['photographers', 'photos', 'events', 'orders'] as $key)
      @php $s = $stats['adaptive'][$key]; @endphp
      <div class="group relative p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 hover:-translate-y-1 hover:shadow-xl transition-all">
        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br
                    {{ $s['tier'] === 'founding' ? 'from-amber-500 to-orange-600 shadow-amber-500/30' : 'from-indigo-500 to-violet-600 shadow-indigo-500/30' }}
                    text-white shadow-md mb-3">
          <i class="bi {{ $s['icon'] }} text-base"></i>
        </div>
        <div class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white leading-none">
          {{ $s['label'] }}
        </div>
        <div class="text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-300/70 mt-1.5">
          {{ $s['sub'] }}
        </div>
      </div>
    @endforeach
  </div>
</section>

{{-- ════════════════════════════════════════════════════════════════════
     OUR COMMITMENT — risk-reversal badges (refund / privacy / verified)
     Placed early so trust signals hit before the user scrolls past
     the marketplace listing. 4 promises in flat icon-grid.
     ════════════════════════════════════════════════════════════════════ --}}
<section class="mb-12 md:mb-14 fade-up">
  <div class="rounded-3xl bg-gradient-to-br from-emerald-50 via-teal-50 to-cyan-50 dark:from-emerald-500/10 dark:via-teal-500/10 dark:to-cyan-500/10 border border-emerald-200/60 dark:border-emerald-400/20 p-5 sm:p-7 md:p-8">
    <div class="flex items-center gap-3 mb-5 justify-center">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md shadow-emerald-500/30">
        <i class="bi bi-shield-check text-lg"></i>
      </span>
      <h2 class="text-lg sm:text-xl font-bold text-slate-800 dark:text-white">สัญญาความปลอดภัยของเรา</h2>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/70 dark:border-white/10 flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 flex items-center justify-center shrink-0">
          <i class="bi bi-cash-coin"></i>
        </div>
        <div class="min-w-0">
          <p class="font-bold text-slate-800 dark:text-white text-sm">คืนเงิน 100%</p>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">จ่ายแล้วช่างไม่มา → คืนเข้าบัญชีใน 24 ชม.</p>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/70 dark:border-white/10 flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-500/15 text-blue-600 dark:text-blue-300 flex items-center justify-center shrink-0">
          <i class="bi bi-patch-check-fill"></i>
        </div>
        <div class="min-w-0">
          <p class="font-bold text-slate-800 dark:text-white text-sm">ตรวจสอบช่างภาพทุกคน</p>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">บัตรประชาชน + ผลงานจริงก่อนรับเข้าระบบ</p>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/70 dark:border-white/10 flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-violet-100 dark:bg-violet-500/15 text-violet-600 dark:text-violet-300 flex items-center justify-center shrink-0">
          <i class="bi bi-lock-fill"></i>
        </div>
        <div class="min-w-0">
          <p class="font-bold text-slate-800 dark:text-white text-sm">รูปไม่หลุด</p>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Watermark + ป้องกันการ screenshot อัตโนมัติ</p>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/70 dark:border-white/10 flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-300 flex items-center justify-center shrink-0">
          <i class="bi bi-receipt"></i>
        </div>
        <div class="min-w-0">
          <p class="font-bold text-slate-800 dark:text-white text-sm">ตรวจสลิปอัตโนมัติ</p>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">AI ตรวจสลิปอัจฉริยะ ปลอมไม่ได้</p>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- Categories --}}
@if($categories->count() > 0)
<section class="mb-12 md:mb-16 fade-up">
  <div class="flex justify-between items-center mb-6">
    <h4 class="font-bold text-xl sm:text-2xl text-slate-800 dark:text-gray-100 flex items-center gap-2" style="letter-spacing:-0.02em;">
      <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-md shadow-indigo-500/25">
        <i class="bi bi-grid text-white text-base"></i>
      </span>
      หมวดหมู่
    </h4>
    <a href="{{ route('events.index') }}" class="hidden sm:inline-flex items-center gap-1 text-sm font-medium px-4 py-1.5 rounded-full bg-indigo-50 dark:bg-white/5 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-white/10 transition">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-4">
    @foreach($categories as $cat)
    <a href="{{ route('events.index', ['category' => $cat->id]) }}" class="no-underline">
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/10 h-full category-card transition-all duration-300 cursor-pointer hover:-translate-y-1.5 hover:shadow-xl hover:border-indigo-300 dark:hover:border-indigo-400/40">
        <div class="text-center py-6 px-4">
          <div class="flex items-center justify-center mx-auto mb-3 w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-500/20 dark:to-violet-500/10">
            <i class="bi {{ $cat->icon ?? 'bi-folder-fill' }} text-2xl text-indigo-600 dark:text-indigo-300"></i>
          </div>
          <h6 class="font-semibold mb-1 text-base text-slate-800 dark:text-gray-100">{{ $cat->name }}</h6>
          <small class="text-gray-500 dark:text-gray-400">{{ $cat->events_count ?? 0 }} อีเวนต์</small>
        </div>
      </div>
    </a>
    @endforeach
  </div>
</section>
@endif

{{-- Featured Events --}}
@if($featuredEvents->count() > 0)
<section class="mb-12 md:mb-16 fade-up">
  <div class="flex justify-between items-center mb-6">
    <h4 class="font-bold text-xl sm:text-2xl text-slate-800 dark:text-gray-100 flex items-center gap-2" style="letter-spacing:-0.02em;">
      <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-md shadow-amber-500/25">
        <i class="bi bi-star-fill text-white text-base"></i>
      </span>
      อีเวนต์แนะนำ
    </h4>
    <a href="{{ route('events.index') }}" class="inline-flex items-center gap-1 text-sm font-medium px-4 py-1.5 rounded-full bg-indigo-50 dark:bg-white/5 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-white/10 transition">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    @foreach($featuredEvents as $event)
    <div>
      {{-- Whole card is a clickable link --}}
      <a href="{{ route('events.show', $event->id) }}"
         class="block h-full no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 rounded-2xl"
         aria-label="ดูรายละเอียดอีเวนต์ {{ $event->name }}">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/10 h-full overflow-hidden event-card transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl dark:hover:shadow-black/40 group cursor-pointer">
          <div class="relative overflow-hidden">
            <x-event-cover :src="$event->cover_image_url"
                    :name="$event->name"
                    :event-id="$event->id"
                    size="card" />
            <div class="absolute bottom-0 left-0 w-full" style="height:60%;background:linear-gradient(to top,rgba(0,0,0,0.6),transparent);z-index:3;"></div>
            @if($event->category)
            <span class="absolute top-0 left-0 m-2 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium text-white bg-gradient-to-br from-indigo-500 to-violet-600 shadow-md" style="z-index:4;">{{ $event->category->name }}</span>
            @endif
            {{-- Hover hint overlay --}}
            <div class="absolute inset-0 z-[5] flex items-center justify-center bg-indigo-900/0 group-hover:bg-indigo-900/40 transition-all duration-300 opacity-0 group-hover:opacity-100 pointer-events-none">
              <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-white/95 text-indigo-700 text-xs font-bold shadow-xl scale-90 group-hover:scale-100 transition-transform duration-300">
                <i class="bi bi-images"></i> ดูภาพถ่าย
              </span>
            </div>
          </div>
          <div class="p-5">
            <h6 class="font-semibold mb-2 text-base text-slate-800 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors" style="line-height:1.4;">{{ $event->name }}</h6>
            <div class="flex items-center gap-3 text-gray-500 dark:text-gray-400 text-sm">
              <span><i class="bi bi-calendar mr-1"></i>{{ $event->shoot_date?->format('d M Y') }}</span>
              @if($event->location)
              <span><i class="bi bi-geo-alt mr-1"></i>{{ Str::limit($event->location, 15) }}</span>
              @endif
            </div>
          </div>
          <div class="px-5 pb-5">
            <span class="block w-full text-center text-sm font-semibold text-white py-2.5 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 group-hover:shadow-lg group-hover:shadow-indigo-500/30 transition-all duration-200">
              <i class="bi bi-images mr-1"></i> ดูภาพถ่าย <i class="bi bi-arrow-right ml-1 transition-transform duration-200 group-hover:translate-x-1"></i>
            </span>
          </div>
        </div>
      </a>
    </div>
    @endforeach
  </div>
</section>
@endif

{{-- Latest Events --}}
@if($latestEvents->count() > 0)
<section class="mb-12 md:mb-16 fade-up">
  <div class="flex justify-between items-center mb-6">
    <h4 class="font-bold text-xl sm:text-2xl text-slate-800 dark:text-gray-100 flex items-center gap-2" style="letter-spacing:-0.02em;">
      <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-md shadow-indigo-500/25">
        <i class="bi bi-clock-history text-white text-base"></i>
      </span>
      อีเวนต์ล่าสุด
    </h4>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    @foreach($latestEvents as $event)
    <div>
      {{-- Whole card is a clickable link --}}
      <a href="{{ route('events.show', $event->id) }}"
         class="block h-full no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 rounded-2xl"
         aria-label="ดูรายละเอียดอีเวนต์ {{ $event->name }}">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/10 h-full overflow-hidden event-card transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl dark:hover:shadow-black/40 group cursor-pointer">
          <div class="relative overflow-hidden">
            <x-event-cover :src="$event->cover_image_url"
                    :name="$event->name"
                    :event-id="$event->id"
                    size="small" />
            <div class="absolute bottom-0 left-0 w-full" style="height:50%;background:linear-gradient(to top,rgba(0,0,0,0.5),transparent);z-index:3;"></div>
            {{-- Hover hint overlay --}}
            <div class="absolute inset-0 z-[5] flex items-center justify-center bg-indigo-900/0 group-hover:bg-indigo-900/40 transition-all duration-300 opacity-0 group-hover:opacity-100 pointer-events-none">
              <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-white/95 text-indigo-700 text-xs font-bold shadow-xl scale-90 group-hover:scale-100 transition-transform duration-300">
                <i class="bi bi-eye"></i> ดูรายละเอียด
              </span>
            </div>
          </div>
          <div class="p-5">
            <h6 class="font-semibold mb-1 text-base text-slate-800 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors">{{ $event->name }}</h6>
            <small class="text-gray-500 dark:text-gray-400"><i class="bi bi-calendar mr-1"></i>{{ $event->shoot_date?->format('d M Y') }}</small>
          </div>
          <div class="px-5 pb-5">
            <span class="block w-full text-center text-sm font-semibold border-2 border-indigo-500 dark:border-indigo-400/60 text-indigo-600 dark:text-indigo-300 group-hover:bg-gradient-to-br group-hover:from-indigo-500 group-hover:to-violet-600 group-hover:border-transparent group-hover:text-white py-2.5 rounded-xl transition-all duration-200">
              ดูรายละเอียด <i class="bi bi-arrow-right ml-1 transition-transform duration-200 group-hover:translate-x-1"></i>
            </span>
          </div>
        </div>
      </a>
    </div>
    @endforeach
  </div>
</section>
@endif

{{-- Photographers Section --}}
@if(isset($photographers) && $photographers->count() > 0)
<section class="mb-12 md:mb-16 fade-up">
  <div class="flex justify-between items-center mb-6">
    <h4 class="font-bold text-xl sm:text-2xl text-slate-800 dark:text-gray-100 flex items-center gap-2" style="letter-spacing:-0.02em;">
      <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center shadow-md shadow-pink-500/25">
        <i class="bi bi-people text-white text-base"></i>
      </span>
      ช่างภาพ
    </h4>
  </div>
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-6 gap-4 stagger-children">
    @foreach($photographers as $pg)
    {{-- Card wrapped in <a> so the whole tile links to the photographer profile.
         $pg->user_id comes from photographer_profiles.user_id (see HomeController
         GROUP BY join); routes/web.php :: photographers.show takes that id. --}}
    <div class="fade-up">
      <a href="{{ route('photographers.show', $pg->user_id) }}"
         class="group block bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/10 h-full text-center photographer-card transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:border-indigo-200 dark:hover:border-indigo-400/40 relative overflow-hidden"
         aria-label="ดูโปรไฟล์ของ {{ $pg->display_name ?? $pg->first_name }}">
        {{-- Tier badge (top-right corner) --}}
        @if(($pg->tier ?? null) === \App\Models\PhotographerProfile::TIER_PRO)
          <span class="absolute top-2 right-2 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[0.6rem] font-bold text-white shadow-sm z-10"
                style="background:linear-gradient(135deg,#f59e0b,#d97706);"
                title="Pro Verified">
            <i class="bi bi-patch-check-fill"></i>Pro
          </span>
        @elseif(($pg->tier ?? null) === \App\Models\PhotographerProfile::TIER_SELLER)
          <span class="absolute top-2 right-2 inline-flex items-center gap-0.5 w-5 h-5 rounded-full justify-center text-[0.7rem] text-white shadow-sm z-10"
                style="background:#10b981;"
                title="Verified Seller">
            <i class="bi bi-check-lg"></i>
          </span>
        @endif

        <div class="py-6 px-4">
          <div class="mx-auto mb-3 transition-transform duration-300 group-hover:scale-105">
            <x-avatar :src="$pg->avatar"
                 :name="$pg->display_name ?? $pg->first_name ?? 'P'"
                 :user-id="$pg->user_id ?? $pg->id"
                 size="xl"
                 :ring="true" />
          </div>
          <h6 class="font-semibold mb-1 text-sm text-slate-800 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate px-2">
            {{ $pg->display_name ?? $pg->first_name }}
          </h6>
          <small class="text-gray-500 dark:text-gray-400">
            <i class="bi bi-camera mr-1"></i>{{ $pg->events_count }} อีเวนต์
          </small>

          {{-- Subtle "View profile" hint that appears on hover --}}
          <div class="mt-3 text-[0.7rem] font-medium text-indigo-500 dark:text-indigo-400 opacity-0 group-hover:opacity-100 transition-opacity">
            ดูโปรไฟล์ <i class="bi bi-arrow-right ml-0.5"></i>
          </div>
        </div>
      </a>
    </div>
    @endforeach
  </div>
</section>
@endif

{{-- ════════════════════════════════════════════════════════════════════
     HOW IT WORKS — 3 simple steps for buyers + 3 for photographers
     A switchable preview that shows the journey in plain language.
     ════════════════════════════════════════════════════════════════════ --}}
<section class="mb-12 md:mb-16 fade-up" x-data="{ role: 'buyer' }">
  <div class="text-center mb-8">
    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white mb-2">วิธีใช้งาน</h2>
    <p class="text-slate-500 dark:text-slate-400 mb-5">ไม่ซับซ้อน ทำได้ใน 3 ขั้น</p>
    <div class="inline-flex items-center p-1 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
      <button type="button" @click="role = 'buyer'"
              :class="role === 'buyer' ? 'bg-gradient-to-br from-pink-500 to-rose-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-300'"
              class="px-4 py-2 rounded-xl text-sm font-semibold transition-all">
        <i class="bi bi-person-heart mr-1"></i>สำหรับลูกค้า
      </button>
      <button type="button" @click="role = 'photographer'"
              :class="role === 'photographer' ? 'bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-300'"
              class="px-4 py-2 rounded-xl text-sm font-semibold transition-all">
        <i class="bi bi-camera-fill mr-1"></i>สำหรับช่างภาพ
      </button>
    </div>
  </div>

  {{-- Buyer flow --}}
  <div x-show="role === 'buyer'" x-transition class="grid grid-cols-1 md:grid-cols-3 gap-5">
    @php
      $buyerSteps = [
        ['n' => '1', 'icon' => 'search', 'title' => 'ค้นหารูปงานคุณ', 'desc' => 'พิมพ์ชื่ออีเวนต์ หรือใช้ AI Face Search อัพโหลดรูปตัวเอง — ระบบหาให้ใน 3 วินาที'],
        ['n' => '2', 'icon' => 'qr-code', 'title' => 'จ่ายผ่าน PromptPay', 'desc' => 'สแกน QR + แนบสลิป — ระบบ AI ตรวจสลิปอัตโนมัติใน 3 วินาที'],
        ['n' => '3', 'icon' => 'line', 'title' => 'รับรูปเข้า LINE', 'desc' => 'ภายใน 30 วินาทีหลังจ่าย ภาพต้นฉบับเข้า LINE คุณทันที — ดาวน์โหลดได้ตลอด'],
      ];
    @endphp
    @foreach($buyerSteps as $step)
      <div class="relative rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-6 hover:shadow-xl hover:-translate-y-1 transition-all">
        <div class="absolute -top-3 -left-3 w-9 h-9 rounded-xl bg-gradient-to-br from-pink-500 to-rose-600 text-white flex items-center justify-center font-extrabold shadow-lg shadow-pink-500/30">{{ $step['n'] }}</div>
        <div class="w-12 h-12 rounded-2xl bg-pink-100 dark:bg-pink-500/15 text-pink-600 dark:text-pink-300 flex items-center justify-center mb-4">
          <i class="bi bi-{{ $step['icon'] }} text-xl"></i>
        </div>
        <h3 class="font-bold text-slate-800 dark:text-white mb-1.5">{{ $step['title'] }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{{ $step['desc'] }}</p>
      </div>
    @endforeach
  </div>

  {{-- Photographer flow --}}
  <div x-show="role === 'photographer'" x-cloak x-transition class="grid grid-cols-1 md:grid-cols-3 gap-5">
    @php
      $phSteps = [
        ['n' => '1', 'icon' => 'person-plus', 'title' => 'สมัคร + ยืนยัน', 'desc' => 'อัพโหลดผลงาน 5 ภาพ + บัตรประชาชน — ทีมตรวจสอบใน 1-2 วันทำการ'],
        ['n' => '2', 'icon' => 'cloud-upload', 'title' => 'อัพโหลดรูปงาน', 'desc' => 'สร้างอีเวนต์ → drag-drop รูปทั้งหมด — Face index อัตโนมัติ ลูกค้าหาเจอทันที'],
        ['n' => '3', 'icon' => 'cash-stack', 'title' => 'รับเงินเข้าบัญชี', 'desc' => 'ลูกค้าจ่าย → ระบบหัก commission → โอนเข้า PromptPay ของคุณทุกศุกร์'],
      ];
    @endphp
    @foreach($phSteps as $step)
      <div class="relative rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-6 hover:shadow-xl hover:-translate-y-1 transition-all">
        <div class="absolute -top-3 -left-3 w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center font-extrabold shadow-lg shadow-indigo-500/30">{{ $step['n'] }}</div>
        <div class="w-12 h-12 rounded-2xl bg-indigo-100 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 flex items-center justify-center mb-4">
          <i class="bi bi-{{ $step['icon'] }} text-xl"></i>
        </div>
        <h3 class="font-bold text-slate-800 dark:text-white mb-1.5">{{ $step['title'] }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{{ $step['desc'] }}</p>
      </div>
    @endforeach
  </div>

  {{-- Persona-specific deeper CTA --}}
  <div class="text-center mt-8">
    <div x-show="role === 'buyer'">
      <a href="{{ route('events.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold text-white bg-gradient-to-br from-pink-500 to-rose-600 shadow-lg shadow-pink-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all">
        <i class="bi bi-search"></i>ค้นหารูปฉันเลย
      </a>
    </div>
    <div x-show="role === 'photographer'" x-cloak>
      <a href="{{ route('photographer-onboarding.quick') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold text-white bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all">
        <i class="bi bi-rocket-takeoff"></i>ลงทะเบียนช่างภาพ — ฟรี 60 วัน
      </a>
    </div>
  </div>
</section>

{{-- ════════════════════════════════════════════════════════════════════
     FOUNDING-COHORT SOCIAL PROOF
     Honest framing — we don't fabricate testimonials. Instead we show
     why early users joined and what they get for being early. This
     reads as authentic for an early-stage marketplace where false
     reviews would damage trust faster than no reviews.
     ════════════════════════════════════════════════════════════════════ --}}
<section class="mb-12 md:mb-16 fade-up">
  <div class="text-center mb-8">
    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300 mb-3">
      <i class="bi bi-rocket-takeoff"></i>Founding Cohort
    </span>
    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white mb-2">เปิดรับช่างภาพรุ่นแรก</h2>
    <p class="text-slate-500 dark:text-slate-400">ทำไมช่างภาพไทยเลือก loadroop.com เป็นช่องทางใหม่</p>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-6 hover:shadow-xl transition-all">
      <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-md shadow-emerald-500/25 mb-4">
        <i class="bi bi-percent text-lg"></i>
      </div>
      <h3 class="font-bold text-slate-800 dark:text-white mb-2">Commission 0% ใน Pro</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mb-3">
        Pro plan ฿790/เดือน — commission <strong class="text-emerald-600 dark:text-emerald-400">0%</strong> ได้เต็มทุกบาท เทียบกับ 20-40% ของช่องทางอื่น Free plan ก็เริ่มได้เลย commission แค่ <strong>20%</strong>
      </p>
      <div class="text-xs text-emerald-700 dark:text-emerald-400 font-semibold flex items-center gap-1">
        <i class="bi bi-arrow-right"></i>ดูราคาทั้งหมด <a href="{{ route('pricing') }}" class="underline">/pricing</a>
      </div>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-6 hover:shadow-xl transition-all">
      <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center shadow-md shadow-indigo-500/25 mb-4">
        <i class="bi bi-stars text-lg"></i>
      </div>
      <h3 class="font-bold text-slate-800 dark:text-white mb-2">AI Face Search ในตัว</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mb-3">
        ลูกค้าหาตัวเองในงานได้ใน 3 วินาที — ไม่ต้อง scroll หา 1,000 ภาพอีกต่อไป conversion เพิ่ม <strong class="text-indigo-600 dark:text-indigo-400">3-5 เท่า</strong>
      </p>
      <div class="text-xs text-indigo-700 dark:text-indigo-400 font-semibold flex items-center gap-1">
        <i class="bi bi-arrow-right"></i>ใช้ได้ทุก plan
      </div>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-6 hover:shadow-xl transition-all">
      <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-pink-500 to-rose-600 text-white flex items-center justify-center shadow-md shadow-pink-500/25 mb-4">
        <i class="bi bi-line text-lg"></i>
      </div>
      <h3 class="font-bold text-slate-800 dark:text-white mb-2">ส่งรูปเข้า LINE อัตโนมัติ</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mb-3">
        จ่ายแล้ว → รูปต้นฉบับเข้า LINE ลูกค้าใน 30 วิ — ไม่ต้องเตรียม Drive link, ไม่ต้องตอบ chat ตี 2 อีกต่อไป
      </p>
      <div class="text-xs text-pink-700 dark:text-pink-400 font-semibold flex items-center gap-1">
        <i class="bi bi-arrow-right"></i>หมดปัญหา customer support
      </div>
    </div>
  </div>
</section>

{{-- CTA Section — Role-Aware --}}
<section class="mb-4">
  <div class="relative overflow-hidden text-center py-14 md:py-16 px-6 rounded-3xl bg-gradient-to-br from-indigo-50 via-white to-pink-50 dark:from-slate-800 dark:via-slate-800 dark:to-indigo-950/60 border border-white/60 dark:border-white/10 shadow-xl shadow-indigo-500/5 dark:shadow-black/30">
    <div class="absolute -top-24 -right-24 w-64 h-64 rounded-full bg-indigo-400/10 dark:bg-indigo-500/10 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-24 -left-24 w-64 h-64 rounded-full bg-pink-400/10 dark:bg-pink-500/10 blur-3xl pointer-events-none"></div>
    <div class="relative">
    @auth
      @php
        $_hasProfile = Auth::user()->photographerProfile !== null;
        $_approved  = $_hasProfile && Auth::user()->photographerProfile->status === 'approved';
        $_pending  = $_hasProfile && Auth::user()->photographerProfile->status === 'pending';
      @endphp

      @if($_approved)
        {{-- Approved Photographer → Go to Dashboard --}}
        <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 items-center justify-center shadow-lg shadow-blue-500/30 mb-4">
          <i class="bi bi-camera-reels text-white text-2xl"></i>
        </div>
        <h4 class="font-bold mb-2 text-xl sm:text-2xl text-slate-800 dark:text-gray-100">จัดการงานของคุณ</h4>
        <p class="text-gray-600 dark:text-gray-400 mb-5 max-w-md mx-auto">เข้าสู่แดชบอร์ดช่างภาพเพื่อจัดการอีเวนต์และรูปภาพ</p>
        <a href="{{ route('photographer.dashboard') }}" class="inline-flex items-center gap-2 font-semibold text-white px-6 py-3 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-speedometer2"></i> แดชบอร์ดช่างภาพ
        </a>
      @elseif($_pending)
        {{-- Pending Photographer → Status Message --}}
        <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 items-center justify-center shadow-lg shadow-amber-500/30 mb-4">
          <i class="bi bi-hourglass-split text-white text-2xl"></i>
        </div>
        <h4 class="font-bold mb-2 text-xl sm:text-2xl text-slate-800 dark:text-gray-100">กำลังรอการอนุมัติ</h4>
        <p class="text-gray-600 dark:text-gray-400 mb-5 max-w-md mx-auto">คำขอสมัครช่างภาพของคุณอยู่ระหว่างการตรวจสอบ เราจะแจ้งให้ทราบเมื่อได้รับการอนุมัติ</p>
        <a href="{{ route('profile') }}" class="inline-flex items-center gap-2 font-semibold text-white px-6 py-3 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 shadow-lg shadow-amber-500/30 hover:shadow-xl hover:shadow-amber-500/40 hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-person"></i> ดูโปรไฟล์ของฉัน
        </a>
      @else
        {{-- Regular Customer → Upgrade to Photographer --}}
        <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 items-center justify-center shadow-lg shadow-indigo-500/30 mb-4">
          <i class="bi bi-camera2 text-white text-2xl"></i>
        </div>
        <h4 class="font-bold mb-2 text-xl sm:text-2xl text-slate-800 dark:text-gray-100">อยากขายรูปภาพอีเวนต์?</h4>
        <p class="text-gray-600 dark:text-gray-400 mb-5 max-w-md mx-auto">อัพเกรดบัญชีเป็นช่างภาพเพื่อเริ่มอัปโหลดและขายรูปภาพของคุณ</p>
        <a href="{{ route('photographer.register') }}" class="inline-flex items-center gap-2 font-semibold text-white px-6 py-3 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-arrow-up-circle"></i> อัพเกรดเป็นช่างภาพ
        </a>
      @endif
    @else
      {{-- Guest → Register as Photographer --}}
      <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 items-center justify-center shadow-lg shadow-indigo-500/30 mb-4">
        <i class="bi bi-camera2 text-white text-2xl"></i>
      </div>
      <h4 class="font-bold mb-2 text-xl sm:text-2xl text-slate-800 dark:text-gray-100">คุณเป็นช่างภาพ?</h4>
      <p class="text-gray-600 dark:text-gray-400 mb-5 max-w-md mx-auto">เข้าร่วมกับเราเพื่อขายภาพถ่ายอีเวนต์ของคุณ</p>
      <a href="{{ route('photographer.register') }}" class="inline-flex items-center gap-2 font-semibold text-white px-6 py-3 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all duration-200">
        <i class="bi bi-person-plus"></i> สมัครเป็นช่างภาพ
      </a>
    @endauth
    </div>
  </div>
</section>

<style>
.event-card:hover img { transform:scale(1.08); }

/* Float animation on hero stat numbers */
@keyframes heroStatFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}
.hero-stat-number {
  animation: heroStatFloat 4s ease-in-out infinite;
  display: inline-block;
}
</style>
@endsection