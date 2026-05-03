@extends('layouts.photographer')

@section('title', 'รายละเอียดอีเวนต์ — ' . $event->name)

@php
  $statusMap = [
    'active'    => ['label' => 'ใช้งาน',     'gradient' => 'from-emerald-500 to-teal-500',  'pill' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'],
    'published' => ['label' => 'เผยแพร่',     'gradient' => 'from-blue-500 to-indigo-500',   'pill' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300'],
    'draft'     => ['label' => 'ร่าง',         'gradient' => 'from-slate-500 to-slate-600',   'pill' => 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-300'],
    'archived'  => ['label' => 'เก็บแล้ว',    'gradient' => 'from-amber-500 to-orange-500',  'pill' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'],
  ];
  $st = $statusMap[$event->status] ?? $statusMap['draft'];

  $humanSize = function (int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0; $v = (float) $bytes;
    while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
    return ($i === 0 ? (int) $v : number_format($v, 1)) . ' ' . $units[$i];
  };

  $hasProcessing = ($stats['photos_processing'] ?? 0) > 0;
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-5">

  {{-- ════════════════════════════════════════════════════════
       HERO — cover image full-bleed + status overlay + actions
       ════════════════════════════════════════════════════════ --}}
  <div class="relative rounded-3xl overflow-hidden bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
    {{-- Breadcrumb (above hero) --}}
    <nav class="absolute top-3 left-4 z-20 flex items-center gap-1.5 text-xs text-white/90 backdrop-blur bg-black/20 px-3 py-1.5 rounded-full">
      <a href="{{ route('photographer.events.index') }}" class="hover:text-white transition">อีเวนต์</a>
      <i class="bi bi-chevron-right text-[9px] opacity-70"></i>
      <span class="font-medium truncate max-w-[200px]">{{ $event->name }}</span>
    </nav>

    {{-- Cover image hero --}}
    <div class="relative aspect-[16/6] sm:aspect-[16/5] bg-gradient-to-br from-blue-100 via-violet-100 to-pink-100 dark:from-slate-700 dark:via-slate-800 dark:to-slate-900 overflow-hidden">
      @if($event->cover_image_url)
        <img src="{{ $event->cover_image_url }}" alt="{{ $event->name }}"
             class="w-full h-full object-cover">
      @else
        <div class="w-full h-full flex items-center justify-center text-blue-300 dark:text-slate-600">
          <i class="bi bi-camera text-6xl"></i>
        </div>
      @endif
      {{-- Gradient overlay for text legibility --}}
      <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>

      {{-- Title overlay bottom-left --}}
      <div class="absolute bottom-0 inset-x-0 p-5 sm:p-6 z-10">
        <div class="flex items-center gap-2 flex-wrap mb-2">
          <span class="inline-flex items-center gap-1 rounded-full bg-gradient-to-r {{ $st['gradient'] }} text-white px-2.5 py-1 text-[11px] font-bold tracking-wide shadow-lg">
            <i class="bi bi-circle-fill text-[6px]"></i>
            {{ $st['label'] }}
          </span>
          @if($event->event_type)
            <span class="inline-flex items-center gap-1 rounded-full bg-white/20 backdrop-blur text-white px-2.5 py-1 text-[11px] font-semibold">
              <i class="bi bi-tag-fill"></i> {{ $event->event_type }}
            </span>
          @endif
          @if((int) $event->is_free === 1 || $event->is_free === true)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500 text-white px-2.5 py-1 text-[11px] font-bold shadow-lg">
              <i class="bi bi-gift-fill"></i> FREE
            </span>
          @endif
          @if($event->visibility === 'private' || !empty($event->event_password))
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-500 text-white px-2.5 py-1 text-[11px] font-bold shadow-lg">
              <i class="bi bi-lock-fill"></i> ส่วนตัว
            </span>
          @endif
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight leading-tight drop-shadow-lg line-clamp-2">
          {{ $event->name }}
        </h1>
        <div class="flex items-center gap-3 flex-wrap text-xs text-white/85 mt-2">
          @if($event->shoot_date)
            <span class="inline-flex items-center gap-1">
              <i class="bi bi-calendar-event"></i>
              {{ \Carbon\Carbon::parse($event->shoot_date)->format('d M Y') }}
              @if($event->start_time)
                · {{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }}
                @if($event->end_time) – {{ \Carbon\Carbon::parse($event->end_time)->format('H:i') }} @endif
              @endif
            </span>
          @endif
          @if($locationFull)
            <span class="text-white/40">·</span>
            <span class="inline-flex items-center gap-1 truncate max-w-[300px]">
              <i class="bi bi-geo-alt-fill"></i>
              {{ $locationFull }}
            </span>
          @endif
          @if($event->venue_name)
            <span class="text-white/40">·</span>
            <span class="inline-flex items-center gap-1 truncate max-w-[200px]">
              <i class="bi bi-pin-map-fill"></i>
              {{ $event->venue_name }}
            </span>
          @endif
        </div>
      </div>
    </div>

    {{-- Action toolbar — sits below the cover --}}
    <div class="px-5 py-3 flex items-center justify-between gap-3 flex-wrap border-t border-slate-100 dark:border-white/5">
      <div class="flex items-center gap-2 flex-wrap">
        <a href="{{ route('photographer.events.photos.upload', $event) }}"
           class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 hover:from-blue-600 hover:to-violet-700 text-white px-3.5 py-2 text-sm font-semibold shadow-md shadow-blue-500/30 transition">
          <i class="bi bi-cloud-upload-fill"></i> อัปโหลด
        </a>
        <a href="{{ route('photographer.events.photos.index', $event) }}"
           class="inline-flex items-center gap-1.5 rounded-xl bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-500/25 px-3.5 py-2 text-sm font-semibold transition">
          <i class="bi bi-images"></i> จัดการรูป
        </a>
        @if(\Illuminate\Support\Facades\Route::has('photographer.events.packages.index'))
        <a href="{{ route('photographer.events.packages.index', $event) }}"
           class="inline-flex items-center gap-1.5 rounded-xl bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 hover:bg-purple-200 dark:hover:bg-purple-500/25 px-3.5 py-2 text-sm font-semibold transition">
          <i class="bi bi-box-seam"></i> แพ็กเกจ
        </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('photographer.events.qrcode'))
        <a href="{{ route('photographer.events.qrcode', $event) }}"
           class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-500/25 px-3.5 py-2 text-sm font-semibold transition">
          <i class="bi bi-qr-code"></i> QR
        </a>
        @endif
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('photographer.events.edit', $event) }}"
           class="inline-flex items-center gap-1.5 rounded-xl bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 hover:bg-amber-200 dark:hover:bg-amber-500/25 px-3.5 py-2 text-sm font-semibold transition">
          <i class="bi bi-pencil-fill"></i> แก้ไข
        </a>
        <a href="{{ route('photographer.events.index') }}"
           class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/10 px-3.5 py-2 text-sm font-medium transition">
          <i class="bi bi-arrow-left"></i> กลับ
        </a>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
       KPI TILES — 5 stat cards (responsive grid)
       ════════════════════════════════════════════════════════ --}}
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
    {{-- Photos --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/15 to-violet-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-images text-xl"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-slate-900 dark:text-white tabular-nums">{{ number_format($stats['photos']) }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">รูปทั้งหมด</div>
        </div>
      </div>
    </div>
    {{-- Active --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-check-circle-fill text-xl"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-emerald-600 dark:text-emerald-400 tabular-nums">{{ number_format($stats['photos_active']) }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">พร้อมขาย</div>
        </div>
      </div>
    </div>
    {{-- Processing --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm relative overflow-hidden
                {{ $hasProcessing ? 'ring-2 ring-amber-200 dark:ring-amber-500/30' : '' }}">
      @if($hasProcessing)
        <span class="absolute top-2 right-2 flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
        </span>
      @endif
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center flex-shrink-0">
          <i class="bi {{ $hasProcessing ? 'bi-arrow-repeat animate-spin' : 'bi-hourglass-split' }} text-xl"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-amber-600 dark:text-amber-400 tabular-nums">{{ number_format($stats['photos_processing']) }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">กำลังประมวลผล</div>
        </div>
      </div>
    </div>
    {{-- Views --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-eye-fill text-xl"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-slate-900 dark:text-white tabular-nums">{{ number_format($stats['view_count']) }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">ยอดเข้าชม</div>
        </div>
      </div>
    </div>
    {{-- Revenue --}}
    <div class="bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-500/[0.08] dark:to-teal-500/[0.08] rounded-2xl border border-emerald-200 dark:border-emerald-500/20 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-emerald-500 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-emerald-500/30">
          <i class="bi bi-currency-exchange text-xl"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-emerald-700 dark:text-emerald-300 tabular-nums">{{ number_format($stats['revenue'], 0) }}</div>
          <div class="text-[11px] text-emerald-700/80 dark:text-emerald-400/80 mt-1">฿ จาก {{ $stats['orders'] }} ออเดอร์</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Processing banner (if any photos are processing) --}}
  @if($hasProcessing)
  <div class="rounded-2xl border border-amber-200 dark:border-amber-500/30 bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-500/10 dark:to-orange-500/10 px-4 py-3 flex items-center gap-3 text-sm shadow-sm">
    <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
      <i class="bi bi-gear-fill animate-spin"></i>
    </div>
    <div class="flex-1 min-w-0">
      <div class="font-semibold text-amber-900 dark:text-amber-200">
        กำลังประมวลผล <span class="tabular-nums">{{ number_format($stats['photos_processing']) }}</span> รูป
      </div>
      <div class="text-[11px] text-amber-700 dark:text-amber-400/80 mt-0.5">
        ระบบสร้าง thumbnail + ลายน้ำในเบื้องหลัง — จะพร้อมขายใน 1-3 นาที
      </div>
    </div>
  </div>
  @endif

  {{-- ════════════════════════════════════════════════════════
       2-COL LAYOUT — gallery preview left, details sidebar right
       ════════════════════════════════════════════════════════ --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- ── LEFT (col-span-2): Photo gallery preview + description ─ --}}
    <div class="lg:col-span-2 space-y-5">

      {{-- 📸 Photo gallery preview --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center">
              <i class="bi bi-images"></i>
            </div>
            <div>
              <h3 class="font-semibold text-slate-900 dark:text-white text-sm">รูปภาพในอีเวนต์</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ number_format($stats['photos_active']) }} พร้อมขาย</p>
            </div>
          </div>
          @if($recentPhotos->count() > 0)
          <a href="{{ route('photographer.events.photos.index', $event) }}"
             class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-500/25 transition">
            ดูทั้งหมด <i class="bi bi-arrow-right"></i>
          </a>
          @endif
        </div>

        @if($recentPhotos->count() > 0)
        <div class="p-4">
          <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
            @foreach($recentPhotos as $photo)
              <a href="{{ route('photographer.events.photos.index', $event) }}"
                 class="block aspect-square rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-900 group relative">
                @if($photo->thumbnail_url)
                  <img src="{{ $photo->thumbnail_url }}" alt="" loading="lazy"
                       class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110">
                @else
                  <div class="w-full h-full flex items-center justify-center text-slate-400">
                    <i class="bi bi-image text-2xl"></i>
                  </div>
                @endif
              </a>
            @endforeach
          </div>
          @if($stats['photos_active'] > $recentPhotos->count())
          <div class="mt-4 text-center">
            <a href="{{ route('photographer.events.photos.index', $event) }}"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition">
              <i class="bi bi-grid-3x3"></i>
              ดูรูปทั้งหมด {{ number_format($stats['photos_active']) }} รูป
              <i class="bi bi-arrow-right"></i>
            </a>
          </div>
          @endif
        </div>
        @else
        {{-- Empty state — gradient invitation --}}
        <div class="p-10 text-center">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-3xl bg-gradient-to-br from-blue-500 to-violet-600 text-white shadow-xl shadow-blue-500/30 mb-4">
            <i class="bi bi-camera-fill text-2xl"></i>
          </div>
          <h4 class="font-bold text-slate-900 dark:text-white mb-1">ยังไม่มีรูปภาพในอีเวนต์</h4>
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">เริ่มอัปโหลดรูปเพื่อให้ลูกค้าเลือกซื้อได้</p>
          <a href="{{ route('photographer.events.photos.upload', $event) }}"
             class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 hover:from-blue-600 hover:to-violet-700 text-white px-5 py-2.5 text-sm font-bold shadow-lg shadow-blue-500/30 transition">
            <i class="bi bi-cloud-upload-fill"></i> อัปโหลดรูปแรก
          </a>
        </div>
        @endif
      </div>

      {{-- 📝 Description --}}
      @if($event->description)
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center">
            <i class="bi bi-file-text"></i>
          </div>
          <h3 class="font-semibold text-slate-900 dark:text-white text-sm">รายละเอียด</h3>
        </div>
        <div class="px-5 py-4">
          <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">{{ $event->description }}</p>
        </div>
      </div>
      @endif

      {{-- 🎯 Highlights tags --}}
      @php
        $highlights = is_array($event->highlights) ? $event->highlights : (json_decode($event->highlights ?? '[]', true) ?: []);
        $tags       = is_array($event->tags)       ? $event->tags       : (json_decode($event->tags ?? '[]', true) ?: []);
      @endphp
      @if(!empty($highlights) || !empty($tags))
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-pink-500/15 text-pink-600 dark:text-pink-400 flex items-center justify-center">
            <i class="bi bi-stars"></i>
          </div>
          <h3 class="font-semibold text-slate-900 dark:text-white text-sm">ไฮไลต์ + แท็ก</h3>
        </div>
        <div class="px-5 py-4 space-y-3">
          @if(!empty($highlights))
          <div>
            <p class="text-[11px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400 mb-2">ไฮไลต์</p>
            <ul class="space-y-1.5">
              @foreach($highlights as $hl)
                <li class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                  <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i>
                  <span>{{ $hl }}</span>
                </li>
              @endforeach
            </ul>
          </div>
          @endif
          @if(!empty($tags))
          <div>
            <p class="text-[11px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400 mb-2">แท็ก</p>
            <div class="flex flex-wrap gap-1.5">
              @foreach($tags as $tag)
                <span class="inline-flex items-center gap-1 rounded-full bg-pink-50 dark:bg-pink-500/10 text-pink-700 dark:text-pink-300 border border-pink-200 dark:border-pink-500/20 px-2.5 py-1 text-xs font-medium">
                  <i class="bi bi-hash text-[10px]"></i>{{ $tag }}
                </span>
              @endforeach
            </div>
          </div>
          @endif
        </div>
      </div>
      @endif
    </div>

    {{-- ── RIGHT (col-span-1): Details sidebar ─────────────────── --}}
    <aside class="lg:col-span-1 space-y-4">

      {{-- 💰 Pricing card --}}
      <div class="rounded-2xl bg-gradient-to-br from-blue-500 to-violet-600 text-white shadow-xl shadow-blue-500/30 p-5 overflow-hidden relative">
        <div class="absolute -top-12 -right-12 w-40 h-40 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative">
          <div class="flex items-center gap-2 text-xs uppercase tracking-wider opacity-90 mb-2">
            <i class="bi bi-cash-stack"></i>
            ราคาต่อภาพ
          </div>
          @if((int) $event->is_free === 1 || $event->is_free === true)
            <div class="text-3xl font-bold">FREE</div>
            <p class="text-xs opacity-80 mt-1">ลูกค้าโหลดได้ฟรี</p>
          @elseif($event->price_per_photo)
            <div class="text-3xl font-bold">฿{{ number_format($event->price_per_photo, 0) }}</div>
            <p class="text-xs opacity-80 mt-1">ต่อ 1 รูปภาพ</p>
          @else
            <div class="text-2xl font-bold opacity-70">— ยังไม่ได้ตั้ง —</div>
          @endif
        </div>
      </div>

      {{-- 📋 Event details --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 dark:border-white/5 flex items-center gap-2">
          <i class="bi bi-info-circle text-slate-500"></i>
          <h3 class="font-semibold text-slate-900 dark:text-white text-sm">ข้อมูลอีเวนต์</h3>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-white/5">
          @if($event->shoot_date)
          <div class="px-5 py-3 flex items-start gap-3">
            <i class="bi bi-calendar-event text-blue-500 shrink-0 w-4 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">วันถ่าย</div>
              <div class="text-sm font-medium text-slate-900 dark:text-white">
                {{ \Carbon\Carbon::parse($event->shoot_date)->format('d M Y') }}
                @if($event->start_time)
                  <span class="text-slate-500 ml-1">·</span>
                  {{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }}
                  @if($event->end_time) – {{ \Carbon\Carbon::parse($event->end_time)->format('H:i') }} @endif
                @endif
              </div>
            </div>
          </div>
          @endif

          @if($locationFull || $event->venue_name)
          <div class="px-5 py-3 flex items-start gap-3">
            <i class="bi bi-geo-alt text-emerald-500 shrink-0 w-4 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">สถานที่</div>
              @if($event->venue_name)
                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $event->venue_name }}</div>
              @endif
              @if($locationFull)
                <div class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">{{ $locationFull }}</div>
              @endif
              @if($event->location_detail)
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $event->location_detail }}</div>
              @endif
            </div>
          </div>
          @endif

          @if($event->organizer)
          <div class="px-5 py-3 flex items-start gap-3">
            <i class="bi bi-building text-violet-500 shrink-0 w-4 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">ผู้จัด</div>
              <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $event->organizer }}</div>
            </div>
          </div>
          @endif

          @if($event->expected_attendees)
          <div class="px-5 py-3 flex items-start gap-3">
            <i class="bi bi-people-fill text-cyan-500 shrink-0 w-4 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">ผู้เข้าร่วม</div>
              <div class="text-sm font-medium text-slate-900 dark:text-white">{{ number_format($event->expected_attendees) }} คน</div>
            </div>
          </div>
          @endif

          @if($event->dress_code)
          <div class="px-5 py-3 flex items-start gap-3">
            <i class="bi bi-suit-club text-amber-500 shrink-0 w-4 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">การแต่งกาย</div>
              <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $event->dress_code }}</div>
            </div>
          </div>
          @endif

          @if($event->parking_info)
          <div class="px-5 py-3 flex items-start gap-3">
            <i class="bi bi-p-square text-indigo-500 shrink-0 w-4 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">ที่จอดรถ</div>
              <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $event->parking_info }}</div>
            </div>
          </div>
          @endif

          <div class="px-5 py-3 flex items-start gap-3">
            <i class="bi bi-hdd text-slate-500 shrink-0 w-4 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">พื้นที่ใช้</div>
              <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $humanSize($stats['storage_bytes']) }}</div>
            </div>
          </div>
        </div>
      </div>

      {{-- 📞 Contact card --}}
      @if($event->contact_phone || $event->contact_email || $event->website_url || $event->facebook_url)
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 dark:border-white/5 flex items-center gap-2">
          <i class="bi bi-telephone text-slate-500"></i>
          <h3 class="font-semibold text-slate-900 dark:text-white text-sm">ติดต่อ / ลิงก์</h3>
        </div>
        <div class="px-5 py-3 space-y-2">
          @if($event->contact_phone)
            <a href="tel:{{ $event->contact_phone }}" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition">
              <i class="bi bi-telephone-fill text-blue-500 w-4"></i>
              <span class="font-medium">{{ $event->contact_phone }}</span>
            </a>
          @endif
          @if($event->contact_email)
            <a href="mailto:{{ $event->contact_email }}" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition truncate">
              <i class="bi bi-envelope-fill text-blue-500 w-4"></i>
              <span class="font-medium truncate">{{ $event->contact_email }}</span>
            </a>
          @endif
          @if($event->website_url)
            <a href="{{ $event->website_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition truncate">
              <i class="bi bi-globe text-blue-500 w-4"></i>
              <span class="font-medium truncate">เว็บไซต์</span>
              <i class="bi bi-arrow-up-right text-[10px] opacity-50"></i>
            </a>
          @endif
          @if($event->facebook_url)
            <a href="{{ $event->facebook_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition truncate">
              <i class="bi bi-facebook text-blue-500 w-4"></i>
              <span class="font-medium truncate">Facebook</span>
              <i class="bi bi-arrow-up-right text-[10px] opacity-50"></i>
            </a>
          @endif
        </div>
      </div>
      @endif

      {{-- 🔗 Drive folder --}}
      @if($event->drive_folder_id || $event->drive_folder_link)
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 dark:border-white/5 flex items-center gap-2">
          <i class="bi bi-google text-blue-500"></i>
          <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Google Drive</h3>
        </div>
        <div class="px-5 py-3 space-y-2">
          @if($event->drive_folder_link)
            <a href="{{ $event->drive_folder_link }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 rounded-lg bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-500/25 px-3 py-1.5 text-xs font-semibold transition">
              <i class="bi bi-box-arrow-up-right"></i> เปิดโฟลเดอร์
            </a>
          @endif
          @if($event->drive_folder_id)
            <p class="text-[11px] font-mono text-slate-500 dark:text-slate-400 break-all">{{ $event->drive_folder_id }}</p>
          @endif
        </div>
      </div>
      @endif
    </aside>
  </div>

</div>
@endsection
