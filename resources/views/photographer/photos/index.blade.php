@extends('layouts.photographer')

@section('title', 'จัดการรูปภาพ — ' . $event->name)

@php
  // Resolve the current event-cover key once so we can flag it on the grid.
  // cover_image may store either a raw path OR a full URL — we match on
  // both so legacy rows and newly-uploaded rows both show the "ปก" badge.
  $currentCoverKey = (string) ($event->cover_image ?? '');
  $isCoverForPhoto = function ($photo) use ($currentCoverKey) {
    if ($currentCoverKey === '') return false;
    $candidates = array_filter([
      $photo->thumbnail_path, $photo->original_path,
      $photo->thumbnail_url,  $photo->original_url,
    ]);
    foreach ($candidates as $c) {
      if ($c !== '' && $c === $currentCoverKey) return true;
    }
    return !empty($photo->thumbnail_path) && str_ends_with($currentCoverKey, $photo->thumbnail_path);
  };

  // Human-readable size for the stats card.
  $humanSize = function (int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0; $v = (float) $bytes;
    while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
    return ($i === 0 ? (int) $v : number_format($v, 1)) . ' ' . $units[$i];
  };

  // Processing flag — drives the auto-refresh hint banner so photographers
  // know the thumbnail/watermark pipeline is still working in the background.
  $hasProcessing = ($stats['processing'] ?? 0) > 0;
@endphp

@section('content')
<div x-data="photoManager({
  eventId: {{ $event->id }},
  processing: {{ $hasProcessing ? 'true' : 'false' }},
})" x-init="init()" class="space-y-5">

  {{-- ════════════════════════════════════════════════════════
       HEADER — event info + primary actions
       ════════════════════════════════════════════════════════ --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
    <div class="p-5 flex items-center justify-between flex-wrap gap-4">
      <div class="flex items-center gap-4 min-w-0">
        <div class="shrink-0">
          <x-event-cover :src="$event->cover_image_url"
                         :name="$event->name"
                         :event-id="$event->id"
                         size="thumb-sm" />
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
            <a href="{{ route('photographer.events.index') }}"
               class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">
              <i class="bi bi-calendar-event"></i> อีเวนต์
            </a>
            <i class="bi bi-chevron-right text-[10px]"></i>
            <a href="{{ route('photographer.events.show', $event) }}"
               class="hover:text-indigo-600 dark:hover:text-indigo-400 transition truncate max-w-[200px]">
              {{ $event->name }}
            </a>
          </div>
          <h1 class="text-xl md:text-2xl font-bold text-slate-900 dark:text-white tracking-tight truncate">
            จัดการรูปภาพ
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            <i class="bi bi-images mr-1"></i>
            {{ number_format($stats['total']) }} รูป · รวม {{ $humanSize($stats['size_bytes']) }}
          </p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <a href="{{ route('photographer.events.show', $event) }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-medium
                  text-slate-700 dark:text-slate-300
                  bg-slate-100 hover:bg-slate-200 dark:bg-white/5 dark:hover:bg-white/10
                  transition">
          <i class="bi bi-arrow-left"></i> กลับ
        </a>
        <a href="{{ route('photographer.events.photos.upload', $event) }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-white
                  bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700
                  shadow-md shadow-blue-500/30 transition">
          <i class="bi bi-cloud-upload"></i> อัปโหลดเพิ่ม
        </a>
      </div>
    </div>

    {{-- Stats strip — compact KPI row so photographers can scan their
         library at a glance without a separate dashboard trip. --}}
    <div class="border-t border-slate-100 dark:border-white/5 grid grid-cols-2 md:grid-cols-4 divide-x divide-slate-100 dark:divide-white/5">
      <div class="p-4">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">ทั้งหมด</div>
        <div class="mt-0.5 flex items-baseline gap-2">
          <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['total']) }}</span>
          <span class="text-xs text-slate-400 dark:text-slate-500">รูป</span>
        </div>
      </div>
      <div class="p-4">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">พร้อมขาย</div>
        <div class="mt-0.5 flex items-baseline gap-2">
          <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['active']) }}</span>
          <span class="text-xs text-slate-400 dark:text-slate-500">รูป</span>
        </div>
      </div>
      <div class="p-4">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">กำลังประมวลผล</div>
        <div class="mt-0.5 flex items-baseline gap-2">
          <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['processing']) }}</span>
          @if($hasProcessing)
            <i class="bi bi-arrow-repeat animate-spin text-amber-500 text-sm"></i>
          @endif
        </div>
      </div>
      <div class="p-4">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">พื้นที่ใช้</div>
        <div class="mt-0.5 flex items-baseline gap-2">
          <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $humanSize($stats['size_bytes']) }}</span>
        </div>
      </div>
    </div>
  </div>

  {{-- Processing banner — surfaces the background queue so nobody
       thinks new uploads silently vanished while thumbnails bake. --}}
  @if($hasProcessing)
  <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-3 flex items-center gap-3 text-sm">
    <i class="bi bi-gear-wide-connected text-amber-500 animate-spin"></i>
    <div class="flex-1 text-amber-800 dark:text-amber-200">
      ระบบกำลังประมวลผล <strong>{{ number_format($stats['processing']) }}</strong> รูป (สร้าง thumbnail / ลายน้ำ) — หน้านี้จะ refresh อัตโนมัติ
    </div>
    <button type="button" @click="window.location.reload()"
            class="text-xs font-semibold text-amber-700 dark:text-amber-300 hover:text-amber-900 dark:hover:text-amber-100 inline-flex items-center gap-1">
      <i class="bi bi-arrow-clockwise"></i> รีเฟรชทันที
    </button>
  </div>
  @endif

  {{-- ════════════════════════════════════════════════════════
       TOOLBAR — search / filter / sort / density / select mode
       ════════════════════════════════════════════════════════ --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
    <form method="GET" class="p-4 flex flex-wrap items-center gap-2">
      {{-- Search --}}
      <div class="relative flex-1 min-w-[220px]">
        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
        <input type="text" name="q" value="{{ $q }}"
               placeholder="ค้นหาชื่อไฟล์..."
               class="w-full pl-9 pr-3 py-2 rounded-lg text-sm
                      bg-slate-50 dark:bg-slate-900
                      border border-slate-200 dark:border-white/10
                      text-slate-900 dark:text-white
                      placeholder:text-slate-400 dark:placeholder:text-slate-500
                      focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">
      </div>

      {{-- Status filter --}}
      <select name="status"
              onchange="this.form.submit()"
              class="px-3 py-2 rounded-lg text-sm
                     bg-slate-50 dark:bg-slate-900
                     border border-slate-200 dark:border-white/10
                     text-slate-900 dark:text-white
                     focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">
        <option value="all"        {{ $status === 'all'        ? 'selected' : '' }}>ทุกสถานะ</option>
        <option value="active"     {{ $status === 'active'     ? 'selected' : '' }}>พร้อมขาย</option>
        <option value="processing" {{ $status === 'processing' ? 'selected' : '' }}>กำลังประมวลผล</option>
        <option value="failed"     {{ $status === 'failed'     ? 'selected' : '' }}>ล้มเหลว</option>
      </select>

      {{-- Sort --}}
      <select name="sort"
              onchange="this.form.submit()"
              class="px-3 py-2 rounded-lg text-sm
                     bg-slate-50 dark:bg-slate-900
                     border border-slate-200 dark:border-white/10
                     text-slate-900 dark:text-white
                     focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">
        <option value="order"  {{ $sort === 'order'  ? 'selected' : '' }}>เรียงตามลำดับ</option>
        <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>ใหม่สุด</option>
        <option value="oldest" {{ $sort === 'oldest' ? 'selected' : '' }}>เก่าสุด</option>
        <option value="name"   {{ $sort === 'name'   ? 'selected' : '' }}>ชื่อไฟล์ A-Z</option>
        <option value="size"   {{ $sort === 'size'   ? 'selected' : '' }}>ขนาดไฟล์ใหญ่สุด</option>
      </select>

      <button type="submit"
              class="px-3 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition">
        <i class="bi bi-funnel"></i>
      </button>

      <div class="h-6 w-px bg-slate-200 dark:bg-white/10 mx-1"></div>

      {{-- Density toggle — icon group. Click triggers Alpine to swap the
           grid column class. Selected option highlighted via `density`. --}}
      <div class="inline-flex rounded-lg border border-slate-200 dark:border-white/10 overflow-hidden">
        <button type="button" @click="setDensity('compact')" :class="density === 'compact' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300'"
                class="px-2.5 py-2 text-xs transition" title="หนาแน่น">
          <i class="bi bi-grid-3x3-gap-fill"></i>
        </button>
        <button type="button" @click="setDensity('comfort')" :class="density === 'comfort' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300'"
                class="px-2.5 py-2 text-xs border-x border-slate-200 dark:border-white/10 transition" title="ปกติ">
          <i class="bi bi-grid-fill"></i>
        </button>
        <button type="button" @click="setDensity('roomy')" :class="density === 'roomy' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300'"
                class="px-2.5 py-2 text-xs transition" title="ห่าง">
          <i class="bi bi-grid"></i>
        </button>
      </div>

      <button type="button" @click="toggleSelectMode()"
              :class="selectMode ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-white/10'"
              class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border text-sm font-medium transition">
        <i class="bi bi-check2-square"></i>
        <span x-text="selectMode ? 'กำลังเลือก' : 'เลือกหลายรูป'"></span>
      </button>
    </form>

    {{-- Active-filter chips — one-click dismissal keeps the URL clean. --}}
    @if($q !== '' || $status !== 'all' || $sort !== 'order')
    <div class="px-4 pb-3 flex flex-wrap items-center gap-2 text-xs">
      <span class="text-slate-500 dark:text-slate-400">ตัวกรองที่ใช้:</span>
      @if($q !== '')
        <a href="{{ request()->fullUrlWithQuery(['q' => null]) }}"
           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/25 transition">
          คำค้น: <strong>{{ $q }}</strong> <i class="bi bi-x"></i>
        </a>
      @endif
      @if($status !== 'all')
        <a href="{{ request()->fullUrlWithQuery(['status' => 'all']) }}"
           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/25 transition">
          สถานะ: <strong>{{ $status }}</strong> <i class="bi bi-x"></i>
        </a>
      @endif
      @if($sort !== 'order')
        <a href="{{ request()->fullUrlWithQuery(['sort' => 'order']) }}"
           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/25 transition">
          เรียง: <strong>{{ $sort }}</strong> <i class="bi bi-x"></i>
        </a>
      @endif
    </div>
    @endif
  </div>

  {{-- ════════════════════════════════════════════════════════
       BULK ACTION BAR — animated in/out via Alpine; only visible
       while select-mode is active. Sticks under the toolbar so
       actions stay in reach even when the grid scrolls long.
       ════════════════════════════════════════════════════════ --}}
  <div x-show="selectMode" x-cloak
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0 -translate-y-2"
       x-transition:enter-end="opacity-100 translate-y-0"
       class="sticky top-[60px] z-20 rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/30 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <label class="inline-flex items-center gap-2 cursor-pointer">
        <input type="checkbox"
               :checked="allSelected"
               @change="toggleSelectAll($event.target.checked)"
               class="w-4 h-4 rounded border-white/40 bg-white/10 text-indigo-600 focus:ring-2 focus:ring-white/50">
        <span class="text-sm font-medium">เลือกทั้งหมดในหน้า</span>
      </label>
      <span class="text-sm text-indigo-100">
        เลือกแล้ว <strong x-text="selected.length"></strong> รูป
      </span>
    </div>
    <div class="flex items-center gap-2">
      <button type="button" @click="clearSelection()"
              :disabled="selected.length === 0"
              :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white/15'"
              class="px-3 py-1.5 rounded-lg text-sm bg-white/10 transition">
        ล้างการเลือก
      </button>
      <button type="button" @click="bulkDelete()"
              :disabled="selected.length === 0"
              :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-rose-600'"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold bg-rose-500 transition">
        <i class="bi bi-trash"></i> ลบที่เลือก
      </button>
      <button type="button" @click="toggleSelectMode()"
              class="px-3 py-1.5 rounded-lg text-sm bg-white/10 hover:bg-white/15 transition">
        ปิด
      </button>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
       PHOTO GRID or EMPTY STATE
       ════════════════════════════════════════════════════════ --}}
  @if($photos->count() > 0)
  <div id="photoGrid"
       :class="gridClass()"
       class="grid gap-3">
    @foreach($photos as $photo)
    @php $cover = $isCoverForPhoto($photo); @endphp

    <div x-data="{ hover: false }"
         @mouseenter="hover = true"
         @mouseleave="hover = false"
         @click="selectMode ? toggleOne({{ $photo->id }}) : null"
         :class="selected.includes({{ $photo->id }}) ? 'ring-4 ring-indigo-500 ring-offset-2 ring-offset-white dark:ring-offset-slate-900' : ''"
         class="photo-card group relative rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800 shadow-sm hover:shadow-xl transition-all cursor-pointer"
         data-id="{{ $photo->id }}">

      {{-- Thumb — aspect-square for visual consistency; still clickable
           to open preview when NOT in select mode. --}}
      @if($photo->status === 'active' && !empty($photo->thumbnail_url))
        <img src="{{ $photo->thumbnail_url }}"
             alt="{{ $photo->original_filename }}"
             loading="lazy"
             class="w-full aspect-square object-cover transition-transform duration-300 group-hover:scale-105"
             @click.stop="selectMode ? toggleOne({{ $photo->id }}) : openPreview({{ json_encode([
               'id'       => $photo->id,
               'url'      => $photo->watermarked_url ?: $photo->original_url,
               'thumb'    => $photo->thumbnail_url,
               'filename' => $photo->original_filename,
               'size'     => $photo->file_size_human,
               'w'        => $photo->width,
               'h'        => $photo->height,
             ]) }})">
      @else
        {{-- Processing / failed placeholder — the thumbnail hasn't been
             generated yet (or the job failed). Show a status tile so the
             photo is still selectable/deletable from this page. --}}
        <div class="w-full aspect-square flex flex-col items-center justify-center text-center p-4
                    bg-gradient-to-br from-slate-100 to-slate-200
                    dark:from-slate-800 dark:to-slate-900">
          @if($photo->status === 'processing')
            <i class="bi bi-arrow-repeat text-3xl text-amber-500 animate-spin"></i>
            <div class="text-xs font-semibold text-amber-600 dark:text-amber-400 mt-2">กำลังประมวลผล</div>
          @elseif($photo->status === 'failed')
            <i class="bi bi-exclamation-triangle-fill text-3xl text-rose-500"></i>
            <div class="text-xs font-semibold text-rose-600 dark:text-rose-400 mt-2">ประมวลผลล้มเหลว</div>
          @else
            <i class="bi bi-image text-3xl text-slate-400"></i>
            <div class="text-xs font-semibold text-slate-500 mt-2">ไม่มี thumbnail</div>
          @endif
          <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 truncate max-w-full">
            {{ $photo->original_filename }}
          </div>
        </div>
      @endif

      {{-- Top-left badges: cover + selection checkbox --}}
      <div class="absolute top-2 left-2 flex flex-col gap-1.5 z-10">
        @if($cover)
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold
                       bg-amber-500 text-white shadow-md">
            <i class="bi bi-star-fill"></i> ปก
          </span>
        @endif
        <template x-if="selectMode">
          <label @click.stop class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-white/90 backdrop-blur-sm shadow cursor-pointer">
            <input type="checkbox" value="{{ $photo->id }}"
                   :checked="selected.includes({{ $photo->id }})"
                   @change="toggleOne({{ $photo->id }})"
                   class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-2 focus:ring-indigo-500/30">
          </label>
        </template>
      </div>

      {{-- Top-right: source tag (e.g. Google Drive) + status pill --}}
      <div class="absolute top-2 right-2 flex flex-col items-end gap-1 z-10">
        @if($photo->source === 'drive')
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold
                       bg-white/90 text-blue-600 backdrop-blur-sm shadow">
            <i class="bi bi-google"></i> Drive
          </span>
        @endif
        @if($photo->status === 'processing')
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold
                       bg-amber-500 text-white shadow">
            <i class="bi bi-arrow-repeat animate-spin"></i> กำลังทำ
          </span>
        @elseif($photo->status === 'failed')
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold
                       bg-rose-500 text-white shadow">
            <i class="bi bi-x-circle"></i> ล้มเหลว
          </span>
        @endif
      </div>

      {{-- Filename + size strip at the bottom — always visible so
           photographers can scan names without hovering. --}}
      <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-2 pt-8 pointer-events-none">
        <div class="text-[11px] text-white/90 truncate" title="{{ $photo->original_filename }}">
          {{ $photo->original_filename }}
        </div>
        <div class="text-[10px] text-white/60 flex items-center gap-2 mt-0.5">
          <span>{{ $photo->width }}×{{ $photo->height }}</span>
          <span>·</span>
          <span>{{ $photo->file_size_human }}</span>
        </div>
      </div>

      {{-- Quick-action cluster — revealed on hover, hidden in select
           mode to avoid accidental clicks. --}}
      <div x-show="hover && !selectMode" x-cloak
           class="absolute top-2 right-2 mt-7 flex flex-col gap-1.5 z-20">
        <button type="button"
                @click.stop="setCover({{ $photo->id }})"
                title="ตั้งเป็นรูปปก"
                class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-white/95 text-amber-600 hover:bg-amber-500 hover:text-white shadow-md backdrop-blur-sm transition">
          <i class="bi bi-star-fill text-xs"></i>
        </button>
        <button type="button"
                @click.stop="deletePhoto({{ $photo->id }})"
                title="ลบรูปภาพ"
                class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-white/95 text-rose-600 hover:bg-rose-500 hover:text-white shadow-md backdrop-blur-sm transition">
          <i class="bi bi-trash text-xs"></i>
        </button>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Pagination --}}
  <div class="flex justify-center pt-2">
    {{ $photos->links() }}
  </div>

  @else
  {{-- ════════════════════════════════════════════════════════
       EMPTY STATE
       ════════════════════════════════════════════════════════ --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-dashed border-slate-200 dark:border-white/10 p-12 text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30 mb-4">
      <i class="bi bi-images text-2xl"></i>
    </div>
    @if($q !== '' || $status !== 'all')
      <h3 class="text-lg font-bold text-slate-900 dark:text-white">ไม่พบรูปภาพตามเงื่อนไขที่เลือก</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 mb-5">
        ลองเปลี่ยนตัวกรองหรือล้างเงื่อนไขทั้งหมด
      </p>
      <a href="{{ route('photographer.events.photos.index', $event) }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition">
        <i class="bi bi-arrow-counterclockwise"></i> ล้างตัวกรอง
      </a>
    @else
      <h3 class="text-lg font-bold text-slate-900 dark:text-white">ยังไม่มีรูปภาพ</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 mb-5">
        เริ่มอัปโหลดรูปภาพเพื่อให้ลูกค้าเข้ามาเลือกซื้อได้
      </p>
      <a href="{{ route('photographer.events.photos.upload', $event) }}"
         class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-lg text-sm font-semibold text-white
                bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700
                shadow-md shadow-blue-500/30 transition">
        <i class="bi bi-cloud-upload"></i> อัปโหลดรูปแรก
      </a>
    @endif
  </div>
  @endif

  {{-- ════════════════════════════════════════════════════════
       PREVIEW MODAL — Alpine-driven, keyboard-navigable
       (← / → for prev/next, Esc to close).
       ════════════════════════════════════════════════════════ --}}
  <div x-show="preview.open" x-cloak
       @keydown.escape.window="closePreview()"
       @keydown.arrow-left.window="navPreview(-1)"
       @keydown.arrow-right.window="navPreview(1)"
       class="fixed inset-0 z-50 flex items-center justify-center p-4"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100">
    <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm" @click="closePreview()"></div>

    <div class="relative w-full max-w-5xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">

      {{-- Header --}}
      <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-slate-200 dark:border-white/10">
        <div class="min-w-0">
          <h3 class="font-semibold text-slate-900 dark:text-white truncate" x-text="preview.filename"></h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            <span x-text="preview.meta"></span>
          </p>
        </div>
        <button type="button" @click="closePreview()"
                class="shrink-0 w-9 h-9 rounded-lg inline-flex items-center justify-center text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-white/10 transition">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      {{-- Image + prev/next overlay buttons --}}
      <div class="relative bg-slate-100 dark:bg-slate-950 flex items-center justify-center" style="min-height:50vh;">
        <img :src="preview.url" alt=""
             class="max-w-full max-h-[70vh] object-contain select-none"
             draggable="false">

        <button type="button" @click="navPreview(-1)"
                class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full inline-flex items-center justify-center bg-white/80 dark:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700 shadow-lg backdrop-blur-sm transition">
          <i class="bi bi-chevron-left"></i>
        </button>
        <button type="button" @click="navPreview(1)"
                class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full inline-flex items-center justify-center bg-white/80 dark:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700 shadow-lg backdrop-blur-sm transition">
          <i class="bi bi-chevron-right"></i>
        </button>
      </div>

      {{-- Footer actions --}}
      <div class="flex items-center justify-between gap-2 px-5 py-3 border-t border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-800/50">
        <div class="text-xs text-slate-500 dark:text-slate-400 hidden md:block">
          <kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-[10px] font-mono">←</kbd>
          <kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-[10px] font-mono">→</kbd>
          เลื่อนดู ·
          <kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-[10px] font-mono">Esc</kbd>
          ปิด
        </div>
        <div class="flex items-center gap-2 ml-auto">
          <button type="button" @click="setCover(preview.id)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold
                         bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300
                         hover:bg-amber-100 dark:hover:bg-amber-500/25 transition">
            <i class="bi bi-star-fill"></i> ตั้งเป็นรูปปก
          </button>
          <button type="button" @click="deletePhoto(preview.id, true)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold
                         bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300
                         hover:bg-rose-100 dark:hover:bg-rose-500/25 transition">
            <i class="bi bi-trash"></i> ลบรูปภาพ
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  /* Selected photo card offset ring fix for dark mode */
  .photo-card.selected { outline: 3px solid #4f46e5; outline-offset: -3px; }
  /* Thin scrollbar inside the preview modal if image is tall */
  [x-cloak] { display: none !important; }
</style>
@endpush

@push('scripts')
<script>
/**
 * Photo-management screen state. Everything is driven by Alpine so the
 * Blade markup stays declarative — no more `document.querySelectorAll`
 * style spaghetti, and the select / preview / delete / cover flows all
 * share one reactive source of truth.
 */
function photoManager(initial) {
  return {
    // ── state ──
    eventId:   initial.eventId,
    selectMode: false,
    selected:  [],           // array of photo IDs
    density:   localStorage.getItem('pg-photo-density') || 'comfort',
    preview:   { open: false, id: null, url: '', thumb: '', filename: '', meta: '' },
    processing: initial.processing,
    _autoRefreshTimer: null,
    csrf:      document.querySelector('meta[name="csrf-token"]')?.content || '',

    init() {
      // Auto-refresh the page every 15s while any photo is still
      // processing so the photographer sees thumbnails appear without
      // manually pressing reload. Cleared once nothing is in queue.
      if (this.processing) {
        this._autoRefreshTimer = setTimeout(() => window.location.reload(), 15000);
      }
    },

    // ── grid density ──
    gridClass() {
      switch (this.density) {
        case 'compact': return 'grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8';
        case 'roomy':   return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3';
        case 'comfort':
        default:        return 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5';
      }
    },
    setDensity(d) {
      this.density = d;
      localStorage.setItem('pg-photo-density', d);
    },

    // ── select mode ──
    get allSelected() {
      const ids = Array.from(document.querySelectorAll('.photo-card')).map(el => parseInt(el.dataset.id));
      return ids.length > 0 && ids.every(id => this.selected.includes(id));
    },
    toggleSelectMode() {
      this.selectMode = !this.selectMode;
      if (!this.selectMode) this.selected = [];
    },
    toggleOne(id) {
      const i = this.selected.indexOf(id);
      if (i === -1) this.selected.push(id);
      else          this.selected.splice(i, 1);
    },
    toggleSelectAll(checked) {
      const ids = Array.from(document.querySelectorAll('.photo-card')).map(el => parseInt(el.dataset.id));
      this.selected = checked ? ids : [];
    },
    clearSelection() { this.selected = []; },

    // ── preview modal ──
    openPreview(data) {
      this.preview = {
        open: true,
        id: data.id,
        url: data.url,
        thumb: data.thumb,
        filename: data.filename,
        meta: `${data.w} × ${data.h} px · ${data.size}`,
      };
    },
    closePreview() { this.preview.open = false; },
    navPreview(dir) {
      if (!this.preview.open) return;
      const cards = Array.from(document.querySelectorAll('.photo-card'));
      const idx = cards.findIndex(el => parseInt(el.dataset.id) === this.preview.id);
      if (idx === -1) return;
      const next = cards[(idx + dir + cards.length) % cards.length];
      if (!next) return;
      // Re-use the click handler on the thumbnail image so we get the
      // exact same payload shape openPreview() already handles.
      const img = next.querySelector('img');
      if (img) img.click();
      else     next.click();
    },

    // ── actions ──
    setCover(id) {
      if (!id) return;
      fetch(`/photographer/events/${this.eventId}/photos/${id}/set-cover`, {
        method:  'POST',
        headers: { 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      })
      .then(async r => {
        const data = await r.json().catch(() => ({ success: false, message: 'Invalid server response' }));
        if (!r.ok) throw new Error(data.message || `HTTP ${r.status}`);
        return data;
      })
      .then(data => {
        if (!data.success) throw new Error(data.message || 'ตั้งรูปปกไม่สำเร็จ');
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1500 });

        // Live-swap the cover preview in the event info header
        if (data.cover_url) {
          const coverRoot = document.querySelector(`.ec-cover[data-event-id="${this.eventId}"]`);
          if (coverRoot) {
            let img = coverRoot.querySelector('img.ec-cover-img');
            const bust = data.cover_url + (data.cover_url.includes('?') ? '&' : '?') + '_t=' + Date.now();
            if (img) {
              img.src = bust;
              img.style.display = '';
              coverRoot.classList.remove('ec-fallback');
            } else {
              img = document.createElement('img');
              img.className = 'ec-cover-img';
              img.src = bust;
              img.loading = 'lazy';
              img.onerror = function () { coverRoot.classList.add('ec-fallback'); this.style.display = 'none'; };
              coverRoot.prepend(img);
              coverRoot.classList.remove('ec-fallback');
            }
          }
        }

        // Re-draw the "ปก" badge on just the picked card.
        document.querySelectorAll('.photo-card .cover-badge-live').forEach(el => el.remove());
        const picked = document.querySelector(`.photo-card[data-id="${id}"]`);
        if (picked) {
          const badgeWrap = picked.querySelector('.absolute.top-2.left-2');
          if (badgeWrap) {
            const badge = document.createElement('span');
            badge.className = 'cover-badge-live inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500 text-white shadow-md';
            badge.innerHTML = '<i class="bi bi-star-fill"></i> ปก';
            badgeWrap.prepend(badge);
          }
        }
      })
      .catch(err => Swal.fire({ icon: 'error', title: 'ตั้งรูปปกไม่สำเร็จ', text: err.message || 'กรุณาลองอีกครั้ง' }));
    },

    deletePhoto(id, fromPreview = false) {
      if (!id) return;
      Swal.fire({
        title: 'ลบรูปภาพ?',
        text:  'การลบจะเอารูปออกจากอีเวนต์นี้ทันที',
        icon:  'warning',
        showCancelButton:  true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ลบ',
        cancelButtonText:  'ยกเลิก',
      }).then(result => {
        if (!result.isConfirmed) return;
        fetch(`/photographer/events/${this.eventId}/photos/${id}`, {
          method:  'DELETE',
          headers: { 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(r => r.json())
        .then(data => {
          if (!data.success) throw new Error(data.message || 'ลบไม่สำเร็จ');
          const card = document.querySelector(`.photo-card[data-id="${id}"]`);
          if (card) card.remove();
          this.selected = this.selected.filter(x => x !== id);
          if (fromPreview) this.closePreview();
          Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'ลบรูปภาพสำเร็จ', showConfirmButton: false, timer: 2000 });
        })
        .catch(err => Swal.fire({ icon: 'error', title: 'ลบไม่สำเร็จ', text: err.message }));
      });
    },

    bulkDelete() {
      if (!this.selected.length) return;
      const ids = [...this.selected];
      Swal.fire({
        title: `ลบ ${ids.length} รูป?`,
        text:  'การลบถาวร ไม่สามารถกู้คืนได้',
        icon:  'warning',
        showCancelButton:  true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ลบทั้งหมด',
        cancelButtonText:  'ยกเลิก',
      }).then(result => {
        if (!result.isConfirmed) return;
        fetch(`/photographer/events/${this.eventId}/photos/bulk-delete`, {
          method:  'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({ photo_ids: ids }),
        })
        .then(r => r.json())
        .then(data => {
          if (!data.success && !data.deleted) throw new Error(data.message || 'ลบไม่สำเร็จ');
          ids.forEach(id => {
            const el = document.querySelector(`.photo-card[data-id="${id}"]`);
            if (el) el.remove();
          });
          this.selected = [];
          this.toggleSelectMode();
          Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2200 });
        })
        .catch(err => Swal.fire({ icon: 'error', title: 'ลบไม่สำเร็จ', text: err.message }));
      });
    },
  };
}
</script>
@endpush
