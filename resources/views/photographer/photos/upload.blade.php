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
{{-- ────────────────────────────────────────────────────────────────
    Header: breadcrumb + title + quick actions
    ──────────────────────────────────────────────────────────────── --}}
<div class="mb-5">
  <nav class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-2">
    <a href="{{ route('photographer.events.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">อีเวนต์</a>
    <i class="bi bi-chevron-right text-[10px]"></i>
    <a href="{{ route('photographer.events.show', $event) }}" class="hover:text-blue-600 dark:hover:text-blue-400 truncate max-w-[220px]">{{ $event->name }}</a>
    <i class="bi bi-chevron-right text-[10px]"></i>
    <a href="{{ route('photographer.events.photos.index', $event) }}" class="hover:text-blue-600 dark:hover:text-blue-400">จัดการรูป</a>
    <i class="bi bi-chevron-right text-[10px]"></i>
    <span class="text-gray-700 dark:text-gray-200">อัพโหลด</span>
  </nav>

  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-blue-600/10 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400">
        <i class="bi bi-cloud-upload text-xl"></i>
      </div>
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 leading-tight">อัพโหลดรูปภาพ</h1>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">เพิ่มรูปใหม่เข้าสู่อีเวนต์ {{ $event->name }}</p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <a href="{{ route('photographer.events.photos.index', $event) }}"
         class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-blue-600 bg-blue-600/10 hover:bg-blue-600/15 rounded-lg transition-colors dark:text-blue-400 dark:bg-blue-500/10 dark:hover:bg-blue-500/20">
        <i class="bi bi-images"></i> จัดการรูปภาพ
      </a>
      <a href="{{ route('photographer.events.show', $event) }}"
         class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-gray-600 bg-gray-500/10 hover:bg-gray-500/15 rounded-lg transition-colors dark:text-gray-300 dark:bg-white/5 dark:hover:bg-white/10">
        <i class="bi bi-arrow-left"></i> กลับ
      </a>
    </div>
  </div>
</div>

{{-- ────────────────────────────────────────────────────────────────
    Event info strip — compact single-row card
    ──────────────────────────────────────────────────────────────── --}}
<div class="mb-5 bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/5 shadow-sm">
  <div class="flex flex-wrap items-center gap-4 p-4">
    <x-event-cover :src="$event->cover_image_url"
            :name="$event->name"
            :event-id="$event->id"
            size="thumb" />
    <div class="min-w-0 flex-1">
      <h2 class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $event->name }}</h2>
      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400 mt-1">
        <span class="inline-flex items-center gap-1">
          <i class="bi bi-images"></i>
          <span class="font-medium text-gray-700 dark:text-gray-200">{{ number_format($photoCount) }}</span> รูปภาพ
        </span>
        @if($event->shoot_date)
          <span class="inline-flex items-center gap-1">
            <i class="bi bi-calendar-event"></i>
            {{ \Carbon\Carbon::parse($event->shoot_date)->format('d/m/Y') }}
          </span>
        @endif
        @if($event->status)
          <span class="inline-flex items-center gap-1">
            <i class="bi bi-circle-fill text-[6px]"></i>
            {{ $event->status }}
          </span>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ────────────────────────────────────────────────────────────────
    NSFW status banner (when client prefilter is enabled)
    ──────────────────────────────────────────────────────────────── --}}
@if($nsfwEnabled)
<div id="nsfwBanner"
     class="flex items-center gap-2 mb-5 px-4 py-2.5 rounded-xl text-xs bg-indigo-500/10 text-indigo-600 border border-indigo-500/20 dark:text-indigo-300 dark:border-indigo-400/20">
  <i class="bi bi-shield-check text-base"></i>
  <span id="nsfwStatus">กำลังโหลดระบบตรวจสอบภาพอัตโนมัติ (AI)…</span>
</div>
@endif

{{-- ────────────────────────────────────────────────────────────────
    Drop zone — primary upload surface
    ──────────────────────────────────────────────────────────────── --}}
<div class="mb-5 bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/5 shadow-sm overflow-hidden">
  <div id="dropZone"
       class="relative cursor-pointer transition-all duration-200 px-6 py-12 sm:py-14 text-center
              bg-gradient-to-b from-gray-50/50 to-white dark:from-slate-900/40 dark:to-slate-800
              border-2 border-dashed border-gray-200 dark:border-white/10
              hover:border-blue-500/60 hover:bg-blue-50/30 dark:hover:bg-blue-500/5
              m-4 rounded-xl">
    <div id="dropIcon" class="mb-4 transition-transform">
      <div class="inline-flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-blue-500/10 text-blue-500 dark:text-blue-400">
        <i class="bi bi-cloud-arrow-up text-3xl sm:text-4xl"></i>
      </div>
    </div>
    <h3 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 mb-1">ลากรูปภาพมาวางที่นี่</h3>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
      หรือคลิกเพื่อเลือกไฟล์
      <span class="inline-flex items-center gap-1 ml-1">
        <span class="text-gray-300 dark:text-gray-600">·</span>
        กด
        <kbd class="inline-block px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-white/10 text-[11px] font-mono text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-white/10">Ctrl</kbd>
        <span>+</span>
        <kbd class="inline-block px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-white/10 text-[11px] font-mono text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-white/10">V</kbd>
        เพื่อวางจากคลิปบอร์ด
      </span>
    </p>

    {{-- Feature pills --}}
    <div class="flex flex-wrap items-center justify-center gap-2 text-xs">
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-white/5 text-gray-600 dark:text-gray-300">
        <i class="bi bi-file-earmark-image"></i> JPG, PNG, WebP, GIF
      </span>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-white/5 text-gray-600 dark:text-gray-300">
        <i class="bi bi-hdd"></i> ≤ 20MB / ไฟล์
      </span>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-white/5 text-gray-600 dark:text-gray-300">
        <i class="bi bi-collection"></i> หลายไฟล์พร้อมกัน
      </span>
      @if($clientCompress)
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
        <i class="bi bi-lightning-charge-fill"></i> บีบอัดในเบราว์เซอร์
      </span>
      @endif
    </div>

    <input type="file" id="fileInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="hidden">
  </div>
</div>

{{-- ────────────────────────────────────────────────────────────────
    Upload progress panel (shown while uploading)
    ──────────────────────────────────────────────────────────────── --}}
<div id="progressSection"
     class="mb-5 bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/5 shadow-sm overflow-hidden"
     style="display:none;">
  {{-- Header --}}
  <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 border-b border-gray-100 dark:border-white/5 bg-gray-50/50 dark:bg-slate-900/40">
    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
      <i class="bi bi-upload text-blue-600 dark:text-blue-400"></i>
      กำลังอัพโหลด
      <span id="progressCount" class="text-gray-500 dark:text-gray-400 font-normal"></span>
    </h3>
    <div class="flex items-center gap-2">
      <button id="btnPause" type="button" onclick="togglePause()"
              class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors bg-amber-500/10 text-amber-600 hover:bg-amber-500/15 dark:text-amber-400 dark:bg-amber-400/10 dark:hover:bg-amber-400/20">
        <i class="bi bi-pause-fill"></i> หยุดชั่วคราว
      </button>
      <button id="btnCancel" type="button" onclick="cancelAll()"
              class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors bg-red-500/10 text-red-600 hover:bg-red-500/15 dark:text-red-400 dark:bg-red-400/10 dark:hover:bg-red-400/20">
        <i class="bi bi-x-lg"></i> ยกเลิก
      </button>
    </div>
  </div>

  {{-- Item list --}}
  <div id="uploadList" class="max-h-[420px] overflow-y-auto divide-y divide-gray-100 dark:divide-white/5"></div>

  {{-- Footer: aggregate stats + progress bar --}}
  <div class="px-5 py-3 border-t border-gray-100 dark:border-white/5 bg-gray-50/30 dark:bg-slate-900/30">
    <div class="flex flex-wrap items-center gap-3">
      <div class="text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
        <span id="successCount" class="font-semibold text-emerald-600 dark:text-emerald-400">0</span> สำเร็จ
        <span class="text-gray-300 dark:text-gray-600 mx-1">·</span>
        <span id="failCount" class="font-semibold text-red-600 dark:text-red-400">0</span> ล้มเหลว
      </div>
      <div class="flex-1 min-w-[160px] h-1.5 rounded-full bg-gray-200 dark:bg-white/10 overflow-hidden">
        <div id="totalProgress" class="h-full bg-blue-600 dark:bg-blue-500 transition-all duration-300" style="width:0%;"></div>
      </div>
      <span id="totalPercent" class="text-xs text-gray-600 dark:text-gray-400 font-medium tabular-nums min-w-[36px] text-right">0%</span>
      <span id="etaLabel" class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 tabular-nums" style="display:none;">
        <i class="bi bi-clock"></i> <span id="etaText">—</span>
      </span>
      <button id="btnRetry" type="button" onclick="retryFailed()"
              class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-blue-600/10 text-blue-600 hover:bg-blue-600/15 dark:text-blue-400 dark:bg-blue-500/15 dark:hover:bg-blue-500/25"
              style="display:none;">
        <i class="bi bi-arrow-clockwise"></i> ลองใหม่
      </button>
    </div>
    @if($clientCompress)
    <div id="compressionSummary" class="mt-2.5 text-xs text-gray-500 dark:text-gray-400 inline-flex items-center gap-1.5" style="display:none;">
      <i class="bi bi-file-earmark-zip text-emerald-600 dark:text-emerald-400"></i>
      <span>บีบอัดแล้ว: <span id="compressedOrig" class="font-medium tabular-nums">0</span> → <span id="compressedNew" class="font-medium tabular-nums">0</span></span>
      <span class="text-gray-300 dark:text-gray-600">·</span>
      <span>ประหยัด <span id="compressedSaved" class="font-semibold text-emerald-600 dark:text-emerald-400">0%</span></span>
    </div>
    @endif
  </div>
</div>

{{-- ────────────────────────────────────────────────────────────────
    Recently uploaded thumbs — lives below the progress panel
    ──────────────────────────────────────────────────────────────── --}}
<div id="recentSection"
     class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/5 shadow-sm overflow-hidden"
     style="display:none;">
  <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 border-b border-gray-100 dark:border-white/5 bg-gray-50/50 dark:bg-slate-900/40">
    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
      <i class="bi bi-check2-circle text-emerald-600 dark:text-emerald-400"></i>
      อัพโหลดสำเร็จ
    </h3>
    <a href="{{ route('photographer.events.photos.index', $event) }}"
       class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-blue-600/10 text-blue-600 hover:bg-blue-600/15 dark:text-blue-400 dark:bg-blue-500/15 dark:hover:bg-blue-500/25">
      <i class="bi bi-images"></i> ดูรูปทั้งหมด
    </a>
  </div>
  <div class="p-4">
    <div id="recentGrid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2"></div>
  </div>
</div>

@endsection

@push('styles')
<style>
/* Drop-zone hover/drag states (Tailwind can't target drag-over from JS easily) */
#dropZone.drag-over {
  border-color: rgb(37 99 235) !important;                 /* blue-600 */
  background: rgba(37, 99, 235, 0.05) !important;
}
.dark #dropZone.drag-over {
  background: rgba(59, 130, 246, 0.08) !important;         /* blue-500 / darker */
}
#dropZone.drag-over #dropIcon {
  transform: scale(1.05);
}

/* Upload-row primitives — kept as plain classes because the JS
   template strings reference them by name. We style them with Tailwind
   utilities applied via @apply so dark mode + utilities still work. */
.upload-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.25rem;
}
.upload-item-thumb {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  object-fit: cover;
  background: #f1f5f9;
  flex-shrink: 0;
}
.dark .upload-item-thumb { background: rgba(255,255,255,0.05); }

.upload-item-progress {
  height: 4px;
  border-radius: 2px;
  background: #e2e8f0;
  flex-grow: 1;
  overflow: hidden;
}
.dark .upload-item-progress { background: rgba(255,255,255,0.08); }

.upload-item-progress .bar {
  height: 100%;
  border-radius: 2px;
  background: #2563eb;
  transition: width 0.15s;
}

/* Status chip sits at the end of each row */
.upload-item .status-icon {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 0.85rem;
}

/* Recent-upload thumb — square, clickable, gentle zoom on hover */
.recent-thumb {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 10px;
  cursor: pointer;
  transition: transform 0.2s;
}
.recent-thumb:hover { transform: scale(1.03); }
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
          <div class="flex justify-between items-center gap-2 mb-1">
            <span class="text-xs font-medium truncate text-gray-700 dark:text-gray-200" style="max-width:220px;">${escHtml(item.originalFile.name)}</span>
            <span class="text-[11px] text-gray-500 dark:text-gray-400 item-size tabular-nums whitespace-nowrap">${formatSize(item.originalFile.size)}</span>
          </div>
          <div class="upload-item-progress">
            <div class="bar" style="width:0%"></div>
          </div>
        </div>
        <div class="status-icon" style="background:rgba(99,102,241,0.10);color:#6366f1;">
          <i class="bi bi-file-earmark-zip"></i>
        </div>
        <button type="button" class="btn-retry" style="display:none;background:rgba(37,99,235,0.08);color:#2563eb;border:none;padding:4px 10px;border-radius:8px;font-size:12px;cursor:pointer;" data-id="${item.id}" title="ลองใหม่">
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

    bar.style.width = item.progress + '%';
    if (size && item.newSize && item.newSize !== item.originalSize) {
      size.innerHTML = `<span style="text-decoration:line-through;opacity:0.5;">${formatSize(item.originalSize)}</span> → ${formatSize(item.newSize)}`;
    } else if (size) {
      size.textContent = formatSize(item.file.size);
    }

    if (retry) retry.style.display = (item.status === 'error') ? 'inline-block' : 'none';

    if (item.status === 'compressing') {
      bar.style.background = '#6366f1';
      icon.style.background = 'rgba(99,102,241,0.10)';
      icon.style.color = '#6366f1';
      icon.innerHTML = '<i class="bi bi-file-earmark-zip"></i>';
    } else if (item.status === 'queued') {
      icon.style.background = 'rgba(37,99,235,0.08)';
      icon.style.color = '#2563eb';
      icon.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    } else if (item.status === 'uploading') {
      bar.style.background = '#2563eb';
      icon.innerHTML = '<i class="bi bi-arrow-up"></i>';
    } else if (item.status === 'done') {
      bar.style.background = '#10b981';
      icon.style.background = 'rgba(16,185,129,0.10)';
      icon.style.color = '#10b981';
      icon.innerHTML = '<i class="bi bi-check-lg"></i>';
    } else if (item.status === 'error') {
      bar.style.background = '#ef4444';
      icon.style.background = 'rgba(239,68,68,0.10)';
      icon.style.color = '#ef4444';
      icon.innerHTML = '<i class="bi bi-exclamation-triangle"></i>';
    } else if (item.status === 'cancelled') {
      bar.style.background = '#6b7280';
      icon.style.background = 'rgba(107,114,128,0.10)';
      icon.style.color = '#6b7280';
      icon.innerHTML = '<i class="bi bi-dash-lg"></i>';
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
    document.getElementById('progressCount').textContent = `(${stats.done}/${stats.total})`;
    document.getElementById('successCount').textContent = stats.success;
    document.getElementById('failCount').textContent = stats.fail;

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
        <div>
          <img src="${photo.thumbnail_url}" class="recent-thumb" alt="${escHtml(photo.filename || '')}" title="${escHtml(photo.filename || '')} (${photo.file_size_human || ''})" loading="lazy" decoding="async">
        </div>`;
      recentGrid.insertAdjacentHTML('beforeend', html);
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
