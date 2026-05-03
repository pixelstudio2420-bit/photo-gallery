@extends('layouts.photographer')

@section('title', 'อัพโหลดรูปภาพ — ' . $event->name)

@php
  // Client-side NSFW prefilter config (NSFWJS / TensorFlow.js)
  // Only enabled if moderation master switch AND client prefilter are both ON.
  $nsfwEnabled = \App\Models\AppSetting::get('moderation_enabled', '1') === '1'
               && \App\Models\AppSetting::get('moderation_client_prefilter', '0') === '1';

  // Hard-block threshold: if Porn/Hentai confidence >= this (0-1), block upload client-side
  $nsfwHardBlock = (float) \App\Models\AppSetting::get('moderation_client_hard_block', '0.85');
  // Warning threshold: 0-1, shows caution but allows upload (server will still moderate)
  $nsfwWarn = (float) \App\Models\AppSetting::get('moderation_client_warn', '0.60');

  // Photo-performance settings — surfaced to the JS compressor below.
  // Admin toggles these at /admin/settings/photo-performance.
  $clientCompress      = \App\Models\AppSetting::get('photo_client_compress_enabled', '1') === '1';
  $clientMaxDimension  = (int) \App\Models\AppSetting::get('photo_client_max_dimension', 3840);
  $clientQuality       = (int) \App\Models\AppSetting::get('photo_client_quality', 85);
@endphp

@section('content')
<div class="max-w-7xl mx-auto">

{{-- ════════════════════════════════════════════════════════════════
     HEADER — gradient avatar + breadcrumb + actions
     ════════════════════════════════════════════════════════════════ --}}
<div class="mb-6">
  <nav class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-3">
    <a href="{{ route('photographer.events.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">อีเวนต์</a>
    <i class="bi bi-chevron-right text-[10px] text-gray-300"></i>
    <a href="{{ route('photographer.events.show', $event) }}" class="hover:text-blue-600 dark:hover:text-blue-400 truncate max-w-[200px] transition">{{ $event->name }}</a>
    <i class="bi bi-chevron-right text-[10px] text-gray-300"></i>
    <a href="{{ route('photographer.events.photos.index', $event) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">จัดการรูป</a>
    <i class="bi bi-chevron-right text-[10px] text-gray-300"></i>
    <span class="text-gray-700 dark:text-gray-200 font-medium">อัพโหลด</span>
  </nav>

  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-500 to-violet-600 text-white flex items-center justify-center shadow-md shadow-blue-500/30">
        <i class="bi bi-cloud-arrow-up-fill text-xl"></i>
      </div>
      <div>
        <h1 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-gray-100 leading-tight tracking-tight">อัพโหลดรูปภาพ</h1>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">เพิ่มรูปเข้าสู่อีเวนต์ — ลาก/วาง/วางจากคลิปบอร์ด</p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <a href="{{ route('photographer.events.photos.index', $event) }}"
         class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 px-3.5 py-2 text-sm font-medium transition">
        <i class="bi bi-images"></i> จัดการรูป
      </a>
      <a href="{{ route('photographer.events.show', $event) }}"
         class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/10 px-3.5 py-2 text-sm font-medium transition">
        <i class="bi bi-arrow-left"></i> กลับ
      </a>
    </div>
  </div>
</div>

{{-- ════════════════════════════════════════════════════════════════
     2-COLUMN LAYOUT — drop zone left, sidebar right
     ════════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

  {{-- ── LEFT (col-span-2): drop zone + progress + recent ─────────── --}}
  <div class="lg:col-span-2 space-y-5">

    {{-- ⚡ NSFW status banner (compact) --}}
    @if($nsfwEnabled)
    <div id="nsfwBanner"
         class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs bg-indigo-500/10 text-indigo-700 border border-indigo-200 dark:text-indigo-300 dark:border-indigo-500/20">
      <i class="bi bi-shield-check text-base"></i>
      <span id="nsfwStatus">กำลังโหลดระบบตรวจสอบภาพอัตโนมัติ (AI)…</span>
    </div>
    @endif

    {{-- ⬆ DROP ZONE — bigger, friendlier, animated --}}
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-white/5 shadow-sm overflow-hidden">
      <div id="dropZone"
           class="relative cursor-pointer transition-all duration-300 px-6 py-14 sm:py-16 text-center
                  bg-gradient-to-br from-blue-50/40 via-violet-50/30 to-pink-50/40
                  dark:from-blue-500/[0.04] dark:via-violet-500/[0.04] dark:to-pink-500/[0.04]
                  border-2 border-dashed border-gray-200 dark:border-white/10
                  hover:border-blue-400 hover:bg-gradient-to-br hover:from-blue-50/80 hover:via-violet-50/50 hover:to-pink-50/60
                  dark:hover:border-blue-400/50
                  m-4 rounded-2xl group">

        {{-- Decorative floating gradient blobs (CSS-only, very subtle) --}}
        <div class="absolute inset-0 pointer-events-none overflow-hidden rounded-2xl">
          <div class="absolute -top-12 -left-12 w-48 h-48 rounded-full bg-blue-300/20 blur-3xl"></div>
          <div class="absolute -bottom-12 -right-12 w-48 h-48 rounded-full bg-violet-300/20 blur-3xl"></div>
        </div>

        <div class="relative">
          {{-- Animated icon stack --}}
          <div id="dropIcon" class="mb-5 transition-all duration-300 group-hover:-translate-y-1">
            <div class="relative inline-flex items-center justify-center">
              {{-- Soft glow ring --}}
              <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-blue-400 to-violet-500 blur-xl opacity-30 group-hover:opacity-50 transition-opacity"></div>
              {{-- Main icon container --}}
              <div class="relative inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-3xl bg-gradient-to-br from-blue-500 to-violet-600 text-white shadow-xl shadow-blue-500/30 group-hover:shadow-2xl group-hover:shadow-violet-500/40 transition-shadow">
                <i class="bi bi-cloud-arrow-up-fill text-3xl sm:text-4xl"></i>
              </div>
            </div>
          </div>

          <h3 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2 tracking-tight">
            ลากรูปมาวางที่นี่
          </h3>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-5">
            หรือคลิกเพื่อเลือกไฟล์
            <span class="hidden sm:inline">
              · กด
              <kbd class="inline-block px-1.5 py-0.5 rounded-md bg-white dark:bg-slate-900 text-[11px] font-mono font-semibold text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-white/10 shadow-sm">⌘ V</kbd>
              เพื่อวางจากคลิปบอร์ด
            </span>
          </p>

          {{-- Feature pills (centered, modern) --}}
          <div class="flex flex-wrap items-center justify-center gap-1.5">
            <span class="inline-flex items-center gap-1 rounded-full bg-white/80 backdrop-blur dark:bg-white/5 border border-gray-200 dark:border-white/10 px-2.5 py-1 text-[11px] font-medium text-gray-700 dark:text-gray-300">
              <i class="bi bi-image text-blue-500"></i> JPG · PNG · WebP · GIF
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-white/80 backdrop-blur dark:bg-white/5 border border-gray-200 dark:border-white/10 px-2.5 py-1 text-[11px] font-medium text-gray-700 dark:text-gray-300">
              <i class="bi bi-hdd text-amber-500"></i> ≤ 20MB
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-white/80 backdrop-blur dark:bg-white/5 border border-gray-200 dark:border-white/10 px-2.5 py-1 text-[11px] font-medium text-gray-700 dark:text-gray-300">
              <i class="bi bi-collection text-violet-500"></i> หลายไฟล์
            </span>
            @if($clientCompress)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/15 border border-emerald-200 dark:border-emerald-500/20 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
              <i class="bi bi-lightning-charge-fill"></i> บีบอัดเร็วในเบราว์เซอร์
            </span>
            @endif
          </div>
        </div>

        <input type="file" id="fileInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="hidden">
      </div>
    </div>

    {{-- ⏳ UPLOAD PROGRESS panel (hidden until first file added) --}}
    <div id="progressSection"
         class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-white/5 shadow-sm overflow-hidden"
         style="display:none;">

      {{-- Header with title + actions --}}
      <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5 bg-gradient-to-r from-blue-50/50 to-violet-50/50 dark:from-blue-500/[0.04] dark:to-violet-500/[0.04]">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center">
              <i class="bi bi-arrow-up-circle-fill"></i>
            </div>
            <div>
              <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">กำลังอัพโหลด</h3>
              <p id="progressCount" class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5"></p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button id="btnPause" type="button" onclick="togglePause()"
                    class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition bg-amber-500/10 text-amber-700 hover:bg-amber-500/20 dark:text-amber-300 dark:bg-amber-400/10 dark:hover:bg-amber-400/20">
              <i class="bi bi-pause-fill"></i>
              <span class="hidden sm:inline">หยุดชั่วคราว</span>
            </button>
            <button id="btnCancel" type="button" onclick="cancelAll()"
                    class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition bg-red-500/10 text-red-700 hover:bg-red-500/20 dark:text-red-300 dark:bg-red-400/10 dark:hover:bg-red-400/20">
              <i class="bi bi-x-lg"></i>
              <span class="hidden sm:inline">ยกเลิก</span>
            </button>
          </div>
        </div>
      </div>

      {{-- Aggregate stats strip (4 mini-KPI tiles) --}}
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-gray-100 dark:bg-white/5 border-b border-gray-100 dark:border-white/5">
        <div class="bg-white dark:bg-slate-800 p-3">
          <div class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-semibold tracking-wider">ทั้งหมด</div>
          <div id="totalCountTile" class="text-lg font-bold text-gray-900 dark:text-gray-100 tabular-nums mt-0.5">0</div>
        </div>
        <div class="bg-white dark:bg-slate-800 p-3">
          <div class="text-[10px] text-emerald-600 dark:text-emerald-400 uppercase font-semibold tracking-wider">สำเร็จ</div>
          <div id="successCount" class="text-lg font-bold text-emerald-600 dark:text-emerald-400 tabular-nums mt-0.5">0</div>
        </div>
        <div class="bg-white dark:bg-slate-800 p-3">
          <div class="text-[10px] text-red-600 dark:text-red-400 uppercase font-semibold tracking-wider">ล้มเหลว</div>
          <div id="failCount" class="text-lg font-bold text-red-600 dark:text-red-400 tabular-nums mt-0.5">0</div>
        </div>
        <div class="bg-white dark:bg-slate-800 p-3">
          <div class="text-[10px] text-blue-600 dark:text-blue-400 uppercase font-semibold tracking-wider">ความคืบหน้า</div>
          <div id="totalPercent" class="text-lg font-bold text-blue-600 dark:text-blue-400 tabular-nums mt-0.5">0%</div>
        </div>
      </div>

      {{-- Main progress bar (gradient-fill, animated) --}}
      <div class="relative h-2.5 bg-gray-100 dark:bg-white/5 overflow-hidden">
        <div id="totalProgress" class="h-full bg-gradient-to-r from-blue-500 via-violet-500 to-pink-500 transition-all duration-300 relative" style="width:0%;">
          {{-- Shimmer animation on top --}}
          <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/40 to-transparent animate-shimmer"></div>
        </div>
      </div>

      {{-- Item list (scrollable) --}}
      <div id="uploadList" class="max-h-[420px] overflow-y-auto"></div>

      {{-- Footer: ETA + retry + compression summary --}}
      <div class="px-5 py-3 border-t border-gray-100 dark:border-white/5 bg-gray-50/30 dark:bg-slate-900/30">
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <div class="flex items-center gap-3 text-xs text-gray-600 dark:text-gray-400">
            <span id="etaLabel" class="inline-flex items-center gap-1 tabular-nums" style="display:none;">
              <i class="bi bi-clock"></i>
              <span>เหลือ <span id="etaText">—</span></span>
            </span>
            @if($clientCompress)
            <span id="compressionSummary" class="inline-flex items-center gap-1.5" style="display:none;">
              <i class="bi bi-file-earmark-zip text-emerald-500"></i>
              <span><span id="compressedOrig" class="font-medium tabular-nums">0</span> → <span id="compressedNew" class="font-medium tabular-nums">0</span></span>
              <span class="text-gray-300 dark:text-gray-600">·</span>
              <span>ประหยัด <span id="compressedSaved" class="font-bold text-emerald-600 dark:text-emerald-400">0%</span></span>
            </span>
            @endif
          </div>
          <button id="btnRetry" type="button" onclick="retryFailed()"
                  class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-blue-600/10 text-blue-700 hover:bg-blue-600/20 dark:text-blue-300 dark:bg-blue-500/15 dark:hover:bg-blue-500/25 transition"
                  style="display:none;">
            <i class="bi bi-arrow-clockwise"></i> ลองใหม่
          </button>
        </div>
      </div>
    </div>

    {{-- 🎉 RECENT UPLOADS — bigger thumbs grid --}}
    <div id="recentSection"
         class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-white/5 shadow-sm overflow-hidden"
         style="display:none;">
      <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/5 bg-gradient-to-r from-emerald-50/50 to-teal-50/50 dark:from-emerald-500/[0.04] dark:to-teal-500/[0.04]">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
            <i class="bi bi-check2-circle"></i>
          </div>
          <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">อัพโหลดสำเร็จล่าสุด</h3>
        </div>
        <a href="{{ route('photographer.events.photos.index', $event) }}"
           class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-blue-600/10 text-blue-700 hover:bg-blue-600/20 dark:text-blue-300 dark:bg-blue-500/15 dark:hover:bg-blue-500/25 transition">
          <i class="bi bi-images"></i> ดูทั้งหมด
        </a>
      </div>
      <div class="p-4">
        <div id="recentGrid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-2.5"></div>
      </div>
    </div>
  </div>

  {{-- ── RIGHT (col-span-1): event card + tips + safety ───────────── --}}
  <aside class="lg:col-span-1 space-y-4">

    {{-- 🎫 EVENT CARD --}}
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-white/5 shadow-sm overflow-hidden">
      {{-- Cover image full-bleed --}}
      <div class="relative aspect-[16/9] bg-gradient-to-br from-blue-100 to-violet-100 dark:from-slate-700 dark:to-slate-900 overflow-hidden">
        @if($event->cover_image_url)
          <img src="{{ $event->cover_image_url }}" alt="{{ $event->name }}"
               class="w-full h-full object-cover">
          <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
        @else
          <div class="w-full h-full flex items-center justify-center text-blue-300">
            <i class="bi bi-camera text-5xl"></i>
          </div>
        @endif
        {{-- Photo count badge floating bottom-right --}}
        <div class="absolute bottom-3 right-3 inline-flex items-center gap-1.5 rounded-full bg-white/95 dark:bg-slate-900/95 backdrop-blur px-3 py-1 text-xs font-bold text-gray-900 dark:text-gray-100 shadow-md">
          <i class="bi bi-images text-blue-500"></i>
          <span id="photoCountBadge" class="tabular-nums">{{ number_format($photoCount) }}</span> รูป
        </div>
      </div>

      <div class="p-4">
        <h2 class="font-bold text-gray-900 dark:text-gray-100 text-base leading-tight line-clamp-2 mb-2">{{ $event->name }}</h2>
        <div class="space-y-1.5">
          @if($event->shoot_date)
          <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
            <i class="bi bi-calendar-event w-4 text-center text-blue-500"></i>
            <span>วันถ่าย: <strong class="text-gray-900 dark:text-gray-100">{{ \Carbon\Carbon::parse($event->shoot_date)->format('d M Y') }}</strong></span>
          </div>
          @endif
          @if($event->status)
          <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
            <i class="bi bi-circle-fill w-4 text-center text-emerald-500 text-[8px]"></i>
            <span>สถานะ: <strong class="text-gray-900 dark:text-gray-100">{{ $event->status }}</strong></span>
          </div>
          @endif
        </div>
      </div>
    </div>

    {{-- 💡 UPLOAD TIPS --}}
    <div class="bg-gradient-to-br from-blue-50 to-violet-50 dark:from-blue-500/[0.06] dark:to-violet-500/[0.06] rounded-3xl border border-blue-100 dark:border-blue-500/20 p-4">
      <div class="flex items-center gap-2 mb-3">
        <div class="w-8 h-8 rounded-lg bg-white/80 dark:bg-white/5 text-amber-500 flex items-center justify-center">
          <i class="bi bi-lightbulb-fill"></i>
        </div>
        <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">เคล็ดลับ</h3>
      </div>
      <ul class="space-y-2 text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
        <li class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 text-[12px] mt-0.5 shrink-0"></i>
          <span>อัพโหลดทีละ <strong>หลายไฟล์</strong> ได้เลย — ระบบจะคิวให้เอง</span>
        </li>
        <li class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 text-[12px] mt-0.5 shrink-0"></i>
          <span>กด <kbd class="px-1.5 py-0.5 rounded bg-white dark:bg-slate-900 border border-gray-200 dark:border-white/10 text-[10px] font-mono">⌘ V</kbd> เพื่อวาง screenshot</span>
        </li>
        @if($clientCompress)
        <li class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 text-[12px] mt-0.5 shrink-0"></i>
          <span>ระบบ <strong>บีบอัดให้อัตโนมัติ</strong> ก่อนอัพโหลด — ประหยัดเน็ต</span>
        </li>
        @endif
        <li class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 text-[12px] mt-0.5 shrink-0"></i>
          <span>ถ้าอัพโหลดล้มเหลว กดปุ่ม <i class="bi bi-arrow-clockwise"></i> เพื่อลองใหม่</span>
        </li>
      </ul>
    </div>

    {{-- 🛡 SAFETY/MODERATION CARD --}}
    @if($nsfwEnabled)
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-white/5 shadow-sm p-4">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-8 h-8 rounded-lg bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
          <i class="bi bi-shield-check-fill"></i>
        </div>
        <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">การคัดกรอง AI</h3>
      </div>
      <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
        ระบบตรวจสอบเนื้อหาภาพอัตโนมัติด้วย AI ก่อนอัพโหลด — ภาพไม่เหมาะสมจะถูกปฏิเสธทันที
      </p>
    </div>
    @endif

    {{-- 📊 LIMITS CARD --}}
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-white/5 shadow-sm p-4">
      <h3 class="text-[11px] uppercase tracking-wider font-bold text-gray-500 dark:text-gray-400 mb-2">ข้อกำหนดไฟล์</h3>
      <div class="grid grid-cols-2 gap-2 text-xs">
        <div class="rounded-xl bg-gray-50 dark:bg-white/5 p-2.5">
          <div class="text-[10px] text-gray-500 dark:text-gray-400 mb-0.5">ขนาดสูงสุด</div>
          <div class="font-bold text-gray-900 dark:text-gray-100">20 MB</div>
        </div>
        <div class="rounded-xl bg-gray-50 dark:bg-white/5 p-2.5">
          <div class="text-[10px] text-gray-500 dark:text-gray-400 mb-0.5">รูปแบบ</div>
          <div class="font-bold text-gray-900 dark:text-gray-100">JPG/PNG/WebP</div>
        </div>
      </div>
    </div>

  </aside>
</div>

</div>
@endsection

@push('styles')
<style>
/* ── Drop-zone drag-over state ──────────────────────────────── */
#dropZone.drag-over {
  border-color: rgb(59 130 246) !important;
  background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(139,92,246,0.06), rgba(236,72,153,0.08)) !important;
  transform: scale(1.005);
}
.dark #dropZone.drag-over {
  border-color: rgb(96 165 250) !important;
  background: linear-gradient(135deg, rgba(96,165,250,0.10), rgba(167,139,250,0.08), rgba(244,114,182,0.10)) !important;
}
#dropZone.drag-over #dropIcon {
  transform: scale(1.1) translateY(-4px);
}
#dropZone.drag-over #dropIcon > div > div:last-child {
  animation: bounce-light 0.6s ease-in-out infinite;
}

@keyframes bounce-light {
  0%, 100% { transform: translateY(0); }
  50%      { transform: translateY(-4px); }
}

/* Shimmer animation on the main progress bar */
@keyframes shimmer {
  0%   { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}
.animate-shimmer {
  animation: shimmer 1.8s linear infinite;
}

/* ── Upload-item rows ─────────────────────────────────────────
   Redesigned with bigger thumb (52px), cleaner spacing,
   smoother color transitions per status. JS template strings
   reference these classes by name — keep the names stable. */
.upload-item {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.875rem 1.25rem;
  border-bottom: 1px solid rgb(243 244 246);
  transition: background 0.15s;
}
.dark .upload-item {
  border-bottom-color: rgba(255,255,255,0.04);
}
.upload-item:hover {
  background: rgb(249 250 251);
}
.dark .upload-item:hover {
  background: rgba(255,255,255,0.02);
}
.upload-item:last-child {
  border-bottom: none;
}

.upload-item-thumb {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  object-fit: cover;
  background: linear-gradient(135deg, rgb(241 245 249), rgb(226 232 240));
  flex-shrink: 0;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.dark .upload-item-thumb {
  background: linear-gradient(135deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
}

.upload-item-progress {
  height: 5px;
  border-radius: 6px;
  background: rgb(229 231 235);
  flex-grow: 1;
  overflow: hidden;
  position: relative;
}
.dark .upload-item-progress {
  background: rgba(255,255,255,0.08);
}

.upload-item-progress .bar {
  height: 100%;
  border-radius: 6px;
  background: linear-gradient(90deg, #3b82f6, #8b5cf6);
  transition: width 0.2s ease, background 0.3s ease;
  position: relative;
}

/* ── Status chip at end of row ────────────────────────────────
   Bigger (32px), more distinct colors per status. Styles applied
   inline by JS via .style.background/.color assignments so we
   keep the visual contract here as just "round chip". */
.upload-item .status-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 0.95rem;
  transition: all 0.2s ease;
  box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}

/* Status pill — visual badge text per upload state, sits between thumb + progress */
.upload-item .status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}

/* ── Recent-uploads grid thumbs ───────────────────────────────
   Bigger, with gentle hover lift + checkmark overlay. */
.recent-thumb-wrap {
  position: relative;
  aspect-ratio: 1;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.recent-thumb-wrap:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 8px 16px rgba(0,0,0,0.12);
}
.recent-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.recent-thumb-wrap::after {
  content: '';
  position: absolute;
  top: 6px;
  right: 6px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #10b981 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='white' d='M13.485 1.929a1 1 0 011.41 1.41L6 12.243 1.105 7.348a1 1 0 011.41-1.41L6 9.422l7.485-7.493z'/%3E%3C/svg%3E") center/10px no-repeat;
  box-shadow: 0 2px 4px rgba(16,185,129,0.4);
}
</style>
@endpush

@push('scripts')
@if($nsfwEnabled)
{{-- TensorFlow.js core + NSFWJS model loader (lazy, loaded on demand) --}}
<script>
window.__nsfwConfig = {
  enabled: true,
  hardBlock: {{ $nsfwHardBlock }},
  warn: {{ $nsfwWarn }},
  model: null,
  loading: null,
  loadError: null,
  async loadModel() {
    if (this.model) return this.model;
    if (this.loading) return this.loading;

    this.loading = (async () => {
      try {
        // Load TensorFlow.js
        if (!window.tf) {
          await new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js';
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
          });
        }
        // Load NSFWJS
        if (!window.nsfwjs) {
          await new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/nsfwjs@3.0.0/dist/nsfwjs.min.js';
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
          });
        }
        // Load MobileNetV2 model from NSFWJS CDN (~4MB, cached)
        this.model = await window.nsfwjs.load('https://nsfw-model.s3.amazonaws.com/models/mobilenet_v2_140_224/', { size: 224 });
        const el = document.getElementById('nsfwStatus');
        if (el) el.textContent = '✓ ระบบตรวจสอบภาพพร้อมใช้งาน';
        return this.model;
      } catch (e) {
        this.loadError = e;
        const el = document.getElementById('nsfwStatus');
        if (el) el.textContent = '⚠️ ไม่สามารถโหลดระบบตรวจสอบ — จะใช้การตรวจสอบที่เซิร์ฟเวอร์แทน';
        console.warn('NSFWJS load failed:', e);
        return null;
      }
    })();
    return this.loading;
  },
  async classify(file) {
    const model = await this.loadModel();
    if (!model) return { ok: true, skipped: true };
    try {
      const img = new Image();
      const url = URL.createObjectURL(file);
      await new Promise((resolve, reject) => {
        img.onload = resolve;
        img.onerror = reject;
        img.src = url;
      });
      const predictions = await model.classify(img);
      URL.revokeObjectURL(url);

      const result = { Porn: 0, Hentai: 0, Sexy: 0, Neutral: 0, Drawing: 0 };
      for (const p of predictions) {
        result[p.className] = p.probability;
      }
      const nsfwScore = Math.max(result.Porn || 0, result.Hentai || 0);
      return {
        ok: nsfwScore < this.hardBlock,
        skipped: false,
        score: nsfwScore,
        predictions: result,
        verdict: nsfwScore >= this.hardBlock ? 'block' : (nsfwScore >= this.warn ? 'warn' : 'pass'),
      };
    } catch (e) {
      console.warn('NSFWJS classify failed:', e);
      return { ok: true, skipped: true, error: e.message };
    }
  },
};
// Preload model in background after page is idle
if ('requestIdleCallback' in window) {
  requestIdleCallback(() => window.__nsfwConfig.loadModel(), { timeout: 5000 });
} else {
  setTimeout(() => window.__nsfwConfig.loadModel(), 2000);
}
</script>
@endif

<script>
// ─── Photo-performance client config (from admin settings) ──────────
window.__photoPerf = {
  clientCompress:     @json($clientCompress),
  clientMaxDimension: {{ max(800, min(8000, $clientMaxDimension)) }},
  // Canvas.toBlob expects quality 0..1
  clientQuality:      {{ max(50, min(100, $clientQuality)) / 100 }},
};

document.addEventListener('DOMContentLoaded', function() {
  const dropZone    = document.getElementById('dropZone');
  const fileInput   = document.getElementById('fileInput');
  const progressSec = document.getElementById('progressSection');
  const recentSec   = document.getElementById('recentSection');
  const uploadList  = document.getElementById('uploadList');
  const recentGrid  = document.getElementById('recentGrid');
  const csrfToken   = document.querySelector('meta[name="csrf-token"]').content;
  const uploadUrl   = @json(route('photographer.events.photos.store', $event));
  const nsfwConfig  = window.__nsfwConfig || { enabled: false };
  const perf        = window.__photoPerf;

  let queue       = [];
  let paused      = false;
  let cancelled   = false;
  let concurrent  = 3;          // simultaneous uploads
  let activeCount = 0;
  let stats       = { total: 0, success: 0, fail: 0, done: 0, bytesDone: 0, bytesTotal: 0, compressedOrig: 0, compressedNew: 0 };
  let etaTimer    = null;
  let startTime   = 0;

  // ─── Drag & Drop ──────────────────────────────────────────────
  dropZone.addEventListener('click', () => fileInput.click());
  dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
  dropZone.addEventListener('dragleave', e => { e.preventDefault(); dropZone.classList.remove('drag-over'); });
  dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    handleFiles(e.dataTransfer.files);
  });
  fileInput.addEventListener('change', () => {
    handleFiles(fileInput.files);
    fileInput.value = '';
  });

  // ─── Paste from clipboard (Ctrl/Cmd+V) ────────────────────────
  document.addEventListener('paste', e => {
    const items = e.clipboardData && e.clipboardData.items;
    if (!items) return;
    const files = [];
    for (const item of items) {
      if (item.kind === 'file' && item.type.startsWith('image/')) {
        const f = item.getAsFile();
        if (f) {
          // Give clipboard screenshots a sane filename with timestamp
          const ts = new Date().toISOString().replace(/[:.]/g, '-').slice(0,19);
          const ext = (f.type.split('/')[1] || 'png').replace('jpeg','jpg');
          const renamed = new File([f], `pasted-${ts}.${ext}`, { type: f.type, lastModified: Date.now() });
          files.push(renamed);
        }
      }
    }
    if (files.length) {
      e.preventDefault();
      showToast(`วาง ${files.length} รูปจากคลิปบอร์ด`, 'info');
      handleFiles(files);
    }
  });

  // ═════════════════════════════════════════════════════════════
  //  Client-side compression (Canvas API)
  // ═════════════════════════════════════════════════════════════
  // Runs before upload when admin enabled photo_client_compress_enabled.
  // GIF is skipped (would lose animation). Output stays JPEG so the
  // server can always re-encode consistently on the other end.
  // Returns { file, originalSize, newSize, compressed }
  async function compressClient(file) {
    if (!perf.clientCompress) {
      return { file, originalSize: file.size, newSize: file.size, compressed: false };
    }
    if (file.type === 'image/gif') {
      return { file, originalSize: file.size, newSize: file.size, compressed: false };
    }

    try {
      const img = await loadImage(file);
      const maxDim = perf.clientMaxDimension;
      const w = img.naturalWidth;
      const h = img.naturalHeight;
      const longest = Math.max(w, h);

      // Only resize when larger than the admin-configured threshold;
      // otherwise a single re-encode still helps strip bloat.
      const scale = longest > maxDim ? maxDim / longest : 1;
      const targetW = Math.max(1, Math.round(w * scale));
      const targetH = Math.max(1, Math.round(h * scale));

      const canvas = document.createElement('canvas');
      canvas.width = targetW;
      canvas.height = targetH;
      const ctx = canvas.getContext('2d');
      // Tell the browser to use best-quality downscaling
      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(img, 0, 0, targetW, targetH);

      const blob = await new Promise(resolve => {
        canvas.toBlob(resolve, 'image/jpeg', perf.clientQuality);
      });
      URL.revokeObjectURL(img.src);

      if (!blob || blob.size >= file.size) {
        // Compression didn't help (e.g. already tiny JPEG) — keep original
        return { file, originalSize: file.size, newSize: file.size, compressed: false };
      }

      const baseName = file.name.replace(/\.[^/.]+$/, '');
      const compressedFile = new File([blob], baseName + '.jpg', {
        type: 'image/jpeg',
        lastModified: file.lastModified || Date.now(),
      });

      return { file: compressedFile, originalSize: file.size, newSize: blob.size, compressed: true };
    } catch (e) {
      console.warn('client compress failed:', e);
      return { file, originalSize: file.size, newSize: file.size, compressed: false };
    }
  }

  function loadImage(file) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload  = () => resolve(img);
      img.onerror = reject;
      img.src = URL.createObjectURL(file);
    });
  }

  // ═════════════════════════════════════════════════════════════
  //  File intake → NSFW check → compress → enqueue
  // ═════════════════════════════════════════════════════════════
  async function handleFiles(files) {
    const validFiles = Array.from(files).filter(f => {
      if (!f.type.startsWith('image/')) {
        showToast('ไม่รองรับไฟล์ ' + f.name, 'error');
        return false;
      }
      if (f.size > 20 * 1024 * 1024) {
        showToast(f.name + ' มีขนาดเกิน 20MB', 'error');
        return false;
      }
      return true;
    });
    if (!validFiles.length) return;

    // ── NSFW pre-check (client-side) ──
    let safeFiles = validFiles;
    if (nsfwConfig.enabled) {
      safeFiles = [];
      for (const file of validFiles) {
        if (file.type === 'image/gif') { safeFiles.push(file); continue; }
        try {
          const check = await nsfwConfig.classify(file);
          if (!check.ok && check.verdict === 'block') {
            const pct = Math.round((check.score || 0) * 100);
            showToast(`🚫 ${file.name} มีเนื้อหาไม่เหมาะสม (${pct}%) — ไม่สามารถอัปโหลดได้`, 'error');
            continue;
          }
          if (check.verdict === 'warn') {
            const pct = Math.round((check.score || 0) * 100);
            showToast(`⚠️ ${file.name} อาจมีเนื้อหาที่ต้องตรวจสอบ (${pct}%) — แอดมินจะตรวจสอบก่อนเผยแพร่`, 'warning');
          }
          safeFiles.push(file);
        } catch (e) {
          safeFiles.push(file);  // fail-open
        }
      }
      if (safeFiles.length === 0) {
        showToast('ไม่มีไฟล์ที่ผ่านการตรวจสอบเนื้อหา', 'error');
        return;
      }
    }

    // Show progress panel up-front so the user sees items queue even during compression
    progressSec.style.display = 'block';
    cancelled = false;
    if (!startTime) startTime = Date.now();
    if (etaTimer === null) etaTimer = setInterval(updateEta, 1000);

    // Enqueue items as "compressing" and render immediately
    const newItems = safeFiles.map(file => ({
      id: 'u_' + Date.now() + '_' + Math.random().toString(36).slice(2,8),
      originalFile: file,
      file,                       // mutated after compression
      originalSize: file.size,
      progress: 0,
      status: 'compressing',
      xhr: null,
      attempts: 0,
      // Idempotency key — one UUID per file, REUSED on every retry of
      // this same file. The server's uniq_event_photos_idempotency
      // partial index turns a retry from "re-upload + race-cleanup" into
      // an instant 200 lookup, saving R2 bandwidth + the user's quota
      // when their network blips mid-batch. Modern browsers (Chrome 92+,
      // Firefox 95+, Safari 15.4+) all support crypto.randomUUID; the
      // fallback covers older devices without throwing.
      idempotencyKey: (typeof crypto !== 'undefined' && crypto.randomUUID)
        ? crypto.randomUUID()
        : ('xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
          })),
    }));
    newItems.forEach(item => {
      queue.push(item);
      stats.total++;
      renderUploadItem(item);
    });
    updateStats();

    // Compress sequentially so we don't freeze the tab on 30+ images,
    // but kick off the upload queue as soon as each one is ready.
    for (const item of newItems) {
      if (cancelled) break;
      const { file, originalSize, newSize, compressed } = await compressClient(item.originalFile);
      item.file = file;
      item.originalSize = originalSize;
      item.newSize = newSize;
      if (compressed) {
        stats.compressedOrig += originalSize;
        stats.compressedNew  += newSize;
        updateCompressionSummary();
      }
      stats.bytesTotal += file.size;
      item.status = 'queued';
      updateItemUI(item);
      processQueue();
    }
    processQueue();
  }

  // ═════════════════════════════════════════════════════════════
  //  Rendering
  // ═════════════════════════════════════════════════════════════
  function renderUploadItem(item) {
    const thumbUrl = URL.createObjectURL(item.originalFile);
    const html = `
      <div class="upload-item" id="${item.id}">
        <img src="${thumbUrl}" class="upload-item-thumb" alt="">
        <div class="grow min-w-0">
          <div class="flex justify-between items-center gap-2 mb-1.5">
            <span class="text-sm font-medium truncate text-gray-800 dark:text-gray-100" style="max-width:280px;">${escHtml(item.originalFile.name)}</span>
            <span class="text-[11px] text-gray-500 dark:text-gray-400 item-size tabular-nums whitespace-nowrap">${formatSize(item.originalFile.size)}</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="upload-item-progress">
              <div class="bar" style="width:0%"></div>
            </div>
            <span class="status-pill item-pill" style="background:rgba(99,102,241,0.10);color:#6366f1;">
              <i class="bi bi-file-earmark-zip"></i> เตรียม
            </span>
          </div>
        </div>
        <div class="status-icon" style="background:rgba(99,102,241,0.10);color:#6366f1;">
          <i class="bi bi-file-earmark-zip"></i>
        </div>
        <button type="button" class="btn-retry" style="display:none;background:rgba(37,99,235,0.10);color:#2563eb;border:none;padding:6px 10px;border-radius:8px;font-size:12px;cursor:pointer;transition:background 0.15s;" data-id="${item.id}" title="ลองใหม่"
                onmouseover="this.style.background='rgba(37,99,235,0.18)'" onmouseout="this.style.background='rgba(37,99,235,0.10)'">
          <i class="bi bi-arrow-clockwise"></i>
        </button>
      </div>`;
    uploadList.insertAdjacentHTML('beforeend', html);
    // Wire retry-per-row
    const btn = uploadList.querySelector(`.btn-retry[data-id="${item.id}"]`);
    if (btn) btn.addEventListener('click', () => retryOne(item.id));
  }

  function updateItemUI(item) {
    const el = document.getElementById(item.id);
    if (!el) return;
    const bar    = el.querySelector('.bar');
    const icon   = el.querySelector('.status-icon');
    const size   = el.querySelector('.item-size');
    const retry  = el.querySelector('.btn-retry');
    const pill   = el.querySelector('.item-pill');

    bar.style.width = item.progress + '%';
    if (size && item.newSize && item.newSize !== item.originalSize) {
      size.innerHTML = `<span style="text-decoration:line-through;opacity:0.5;">${formatSize(item.originalSize)}</span> <span style="color:#10b981;font-weight:600;">${formatSize(item.newSize)}</span>`;
    } else if (size) {
      size.textContent = formatSize(item.file.size);
    }

    if (retry) retry.style.display = (item.status === 'error') ? 'inline-block' : 'none';

    // Status visual mapping. Keeps the inline-style pattern from the
    // original — JS template strings rely on direct DOM manipulation
    // for tight FPS during rapid status changes.
    const states = {
      compressing: {
        barBg: 'linear-gradient(90deg, #6366f1, #818cf8)',
        iconBg: 'rgba(99,102,241,0.12)',
        iconColor: '#6366f1',
        icon: 'bi-file-earmark-zip',
        pillText: 'กำลังบีบอัด',
      },
      queued: {
        barBg: 'linear-gradient(90deg, #94a3b8, #cbd5e1)',
        iconBg: 'rgba(37,99,235,0.10)',
        iconColor: '#2563eb',
        icon: 'bi-hourglass-split',
        pillText: 'รอคิว',
      },
      uploading: {
        barBg: 'linear-gradient(90deg, #3b82f6, #8b5cf6)',
        iconBg: 'rgba(59,130,246,0.12)',
        iconColor: '#3b82f6',
        icon: 'bi-arrow-up',
        pillText: 'กำลังอัพโหลด',
      },
      done: {
        barBg: 'linear-gradient(90deg, #10b981, #34d399)',
        iconBg: 'rgba(16,185,129,0.12)',
        iconColor: '#10b981',
        icon: 'bi-check-lg',
        pillText: 'สำเร็จ',
      },
      error: {
        barBg: 'linear-gradient(90deg, #ef4444, #f87171)',
        iconBg: 'rgba(239,68,68,0.12)',
        iconColor: '#ef4444',
        icon: 'bi-exclamation-triangle-fill',
        pillText: 'ล้มเหลว',
      },
      cancelled: {
        barBg: 'linear-gradient(90deg, #6b7280, #9ca3af)',
        iconBg: 'rgba(107,114,128,0.12)',
        iconColor: '#6b7280',
        icon: 'bi-dash-lg',
        pillText: 'ยกเลิก',
      },
    };

    const s = states[item.status];
    if (!s) return;

    bar.style.background = s.barBg;
    icon.style.background = s.iconBg;
    icon.style.color = s.iconColor;
    icon.innerHTML = `<i class="bi ${s.icon}"></i>`;

    if (pill) {
      pill.style.background = s.iconBg;
      pill.style.color = s.iconColor;
      pill.innerHTML = `<i class="bi ${s.icon}"></i> ${s.pillText}`;
    }
  }

  // ═════════════════════════════════════════════════════════════
  //  Upload pipeline
  // ═════════════════════════════════════════════════════════════
  function processQueue() {
    if (cancelled || paused) return;
    while (activeCount < concurrent) {
      const next = queue.find(i => i.status === 'queued');
      if (!next) break;
      next.status = 'uploading';
      activeCount++;
      uploadFile(next);
    }
  }

  function uploadFile(item) {
    const formData = new FormData();
    formData.append('photo', item.file, item.file.name);
    formData.append('_token', csrfToken);

    const xhr = new XMLHttpRequest();
    item.xhr = xhr;
    item.attempts += 1;
    let lastLoaded = 0;

    xhr.upload.addEventListener('progress', e => {
      if (e.lengthComputable) {
        item.progress = Math.round(e.loaded / e.total * 100);
        // Track cumulative bytes for ETA
        stats.bytesDone += (e.loaded - lastLoaded);
        lastLoaded = e.loaded;
        updateItemUI(item);
      }
    });

    xhr.addEventListener('load', () => {
      activeCount--;
      stats.done++;
      if (xhr.status === 200) {
        try {
          const resp = JSON.parse(xhr.responseText);
          if (resp.success) {
            item.status = 'done';
            item.progress = 100;
            stats.success++;
            addToRecent(resp.photo);
          } else {
            item.status = 'error';
            item.errorMsg = resp.message || 'อัพโหลดไม่สำเร็จ';
            stats.fail++;
          }
        } catch (e) {
          item.status = 'error';
          item.errorMsg = 'Invalid server response';
          stats.fail++;
        }
      } else {
        item.status = 'error';
        item.errorMsg = `HTTP ${xhr.status}`;
        stats.fail++;
      }
      updateItemUI(item);
      updateStats();
      processQueue();
    });

    xhr.addEventListener('error', () => {
      activeCount--;
      stats.done++;
      item.status = 'error';
      item.errorMsg = 'Network error';
      stats.fail++;
      updateItemUI(item);
      updateStats();
      processQueue();
    });

    xhr.addEventListener('abort', () => {
      activeCount--;
      stats.done++;
      item.status = 'cancelled';
      updateItemUI(item);
      updateStats();
    });

    xhr.open('POST', uploadUrl);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    // Idempotency-Key — RFC 7231-style header. Server's PhotoController
    // checks this BEFORE doing the SHA-256 hash + R2 upload, so a retry
    // resolves with an instant DB lookup (200 + replayed:true) instead
    // of re-uploading the bytes only to be deduped post-hoc.
    if (item.idempotencyKey) {
      xhr.setRequestHeader('Idempotency-Key', item.idempotencyKey);
    }
    xhr.send(formData);
  }

  // ═════════════════════════════════════════════════════════════
  //  ETA / stats / compression summary
  // ═════════════════════════════════════════════════════════════
  function updateStats() {
    document.getElementById('progressCount').textContent = `${stats.done} / ${stats.total} ไฟล์`;
    document.getElementById('successCount').textContent = stats.success;
    document.getElementById('failCount').textContent = stats.fail;
    const totalTile = document.getElementById('totalCountTile');
    if (totalTile) totalTile.textContent = stats.total;

    const pct = stats.total > 0 ? Math.round(stats.done / stats.total * 100) : 0;
    document.getElementById('totalProgress').style.width = pct + '%';
    document.getElementById('totalPercent').textContent = pct + '%';

    // Show/hide retry-all button
    const retryBtn = document.getElementById('btnRetry');
    if (retryBtn) retryBtn.style.display = stats.fail > 0 ? 'inline-flex' : 'none';

    // Stop ETA timer when all done
    if (stats.done >= stats.total && etaTimer) {
      clearInterval(etaTimer);
      etaTimer = null;
      const etaEl = document.getElementById('etaLabel');
      if (etaEl) etaEl.style.display = 'none';
    }
  }

  function updateEta() {
    const label = document.getElementById('etaLabel');
    if (!label) return;
    const pending = queue.filter(i => i.status === 'queued' || i.status === 'uploading');
    if (!pending.length || stats.bytesDone < 1024) { label.style.display = 'none'; return; }

    const elapsed = (Date.now() - startTime) / 1000;           // seconds
    if (elapsed < 1.5) return;                                  // give enough samples
    const bytesPerSec = stats.bytesDone / elapsed;
    if (bytesPerSec <= 0) return;
    const remainingBytes = pending.reduce((s, i) => {
      const done = (i.progress / 100) * i.file.size;
      return s + Math.max(0, i.file.size - done);
    }, 0);
    const etaSec = remainingBytes / bytesPerSec;

    label.style.display = 'inline-flex';
    document.getElementById('etaText').textContent = formatEta(etaSec);
  }

  function updateCompressionSummary() {
    const el = document.getElementById('compressionSummary');
    if (!el) return;
    if (stats.compressedOrig === 0) return;
    el.style.display = 'inline-flex';
    document.getElementById('compressedOrig').textContent = formatSize(stats.compressedOrig);
    document.getElementById('compressedNew').textContent  = formatSize(stats.compressedNew);
    const saved = Math.max(0, Math.round((1 - stats.compressedNew / stats.compressedOrig) * 100));
    document.getElementById('compressedSaved').textContent = saved + '%';
  }

  function addToRecent(photo) {
    recentSec.style.display = 'block';
    if (photo.thumbnail_url) {
      const html = `
        <div class="recent-thumb-wrap">
          <img src="${photo.thumbnail_url}" class="recent-thumb" alt="${escHtml(photo.filename || '')}" title="${escHtml(photo.filename || '')} (${photo.file_size_human || ''})" loading="lazy" decoding="async">
        </div>`;
      recentGrid.insertAdjacentHTML('beforeend', html);
    }
    // Bump the photo count badge in the sidebar event card
    const badge = document.getElementById('photoCountBadge');
    if (badge) {
      const current = parseInt(badge.textContent.replace(/,/g, ''), 10) || 0;
      badge.textContent = (current + 1).toLocaleString();
    }
  }

  // ═════════════════════════════════════════════════════════════
  //  Controls: pause / cancel / retry
  // ═════════════════════════════════════════════════════════════
  window.togglePause = function() {
    paused = !paused;
    const btn = document.getElementById('btnPause');
    if (paused) {
      btn.innerHTML = '<i class="bi bi-play-fill"></i> ดำเนินการต่อ';
      btn.classList.remove('bg-amber-500/10','text-amber-600','hover:bg-amber-500/15','dark:text-amber-400','dark:bg-amber-400/10','dark:hover:bg-amber-400/20');
      btn.classList.add('bg-emerald-500/10','text-emerald-600','hover:bg-emerald-500/15','dark:text-emerald-400','dark:bg-emerald-400/10','dark:hover:bg-emerald-400/20');
    } else {
      btn.innerHTML = '<i class="bi bi-pause-fill"></i> หยุดชั่วคราว';
      btn.classList.remove('bg-emerald-500/10','text-emerald-600','hover:bg-emerald-500/15','dark:text-emerald-400','dark:bg-emerald-400/10','dark:hover:bg-emerald-400/20');
      btn.classList.add('bg-amber-500/10','text-amber-600','hover:bg-amber-500/15','dark:text-amber-400','dark:bg-amber-400/10','dark:hover:bg-amber-400/20');
      processQueue();
    }
  };

  window.cancelAll = function() {
    cancelled = true;
    queue.forEach(item => {
      if (item.xhr && item.status === 'uploading') { item.xhr.abort(); }
      if (item.status === 'queued' || item.status === 'compressing') {
        item.status = 'cancelled';
        stats.done++;
        updateItemUI(item);
      }
    });
    updateStats();
  };

  window.retryFailed = function() {
    const failed = queue.filter(i => i.status === 'error');
    if (!failed.length) return;
    failed.forEach(item => {
      item.status = 'queued';
      item.progress = 0;
      stats.fail--;
      stats.done--;
      updateItemUI(item);
    });
    updateStats();
    cancelled = false;
    processQueue();
  };

  function retryOne(id) {
    const item = queue.find(i => i.id === id);
    if (!item || item.status !== 'error') return;
    item.status = 'queued';
    item.progress = 0;
    stats.fail--;
    stats.done--;
    updateItemUI(item);
    updateStats();
    cancelled = false;
    processQueue();
  }

  // ─── helpers ───────────────────────────────────────────────
  function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }
  function formatEta(sec) {
    if (!isFinite(sec) || sec < 0) return '—';
    if (sec < 60) return Math.ceil(sec) + 's';
    if (sec < 3600) return Math.floor(sec/60) + 'm ' + Math.ceil(sec%60) + 's';
    return Math.floor(sec/3600) + 'h ' + Math.floor((sec%3600)/60) + 'm';
  }
  function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = String(s ?? '');
    return d.innerHTML;
  }
  function showToast(msg, type) {
    const iconMap = { error: 'error', warning: 'warning', success: 'success', info: 'info' };
    const icon = iconMap[type] || 'info';
    if (typeof Swal !== 'undefined') {
      Swal.fire({ toast: true, position: 'top-end', icon, title: msg, showConfirmButton: false, timer: type === 'warning' ? 5000 : 3000 });
    } else {
      console.log(`[${type}]`, msg);
    }
  }
});
</script>
@endpush
