@extends('layouts.photographer')

@section('title', 'อีเวนต์ของฉัน')

@push('styles')
<style>
  /* ══════════════════════════════════════════════════════════════
     Photographer Events index — card-based responsive layout
     ══════════════════════════════════════════════════════════════ */

  /* Event card — image-led, with status pill in top-right corner */
  .ev-card {
    background: white;
    border: 1px solid rgb(229 231 235);
    border-radius: 16px;
    overflow: hidden;
    transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
    display: flex;
    flex-direction: column;
  }
  .dark .ev-card { background: rgb(15 23 42); border-color: rgba(255,255,255,0.08); }

  @media (hover: hover) {
    .ev-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 18px 40px -12px rgba(0,0,0,0.12);
      border-color: rgb(199 210 254);
    }
    .dark .ev-card:hover { border-color: rgba(99,102,241,0.4); }
  }

  /* Cover area: 16:9 ratio, gradient placeholder when no image */
  .ev-cover {
    aspect-ratio: 16 / 9;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
    position: relative;
    overflow: hidden;
  }
  .ev-cover img { width: 100%; height: 100%; object-fit: cover; }
  .ev-cover-placeholder {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,0.4);
    font-size: 3rem;
  }

  /* Status pill on cover */
  .ev-status {
    position: absolute; top: .65rem; right: .65rem;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(8px);
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  }

  /* Action button row inside the card */
  .ev-action {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background .15s, transform .1s;
    cursor: pointer;
    border: 0;
    flex-shrink: 0;
  }
  .ev-action:hover { transform: scale(1.05); }
  .ev-action:active { transform: scale(0.95); }
  .ev-action.is-edit    { background: rgba(245,158,11,.10); color: #d97706; }
  .ev-action.is-photos  { background: rgba(16,185,129,.10); color: #059669; }
  .ev-action.is-upload  { background: rgba(99,102,241,.10); color: #4f46e5; }
  .ev-action.is-qr      { background: rgba(124,58,237,.10); color: #7c3aed; }
  .ev-action.is-delete  { background: rgba(239,68,68,.10); color: #dc2626; }
  .ev-action.is-view    { background: rgba(14,165,233,.10); color: #0284c7; }

  /* Filter chip strip */
  .ev-filter {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .4rem .85rem;
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 600;
    background: rgb(243 244 246);
    color: rgb(75 85 99);
    transition: all .15s;
    text-decoration: none;
    white-space: nowrap;
  }
  .ev-filter:hover { background: rgb(229 231 235); }
  .ev-filter.is-active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 12px -2px rgba(99,102,241,0.45);
  }
  .dark .ev-filter { background: rgba(255,255,255,0.05); color: rgb(203 213 225); }
  .dark .ev-filter:hover { background: rgba(255,255,255,0.10); }

  /* Empty state — bigger illustration on desktop */
  .ev-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: rgb(107 114 128);
  }
  .ev-empty-icon {
    width: 96px; height: 96px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff, #fce7f3);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem;
    color: #6366f1;
  }
  .dark .ev-empty-icon { background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(236,72,153,0.10)); }
</style>
@endpush

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-calendar-event',
  'eyebrow'  => 'การทำงาน',
  'title'    => 'อีเวนต์ของฉัน',
  'subtitle' => 'จัดการอีเวนต์ทั้งหมด · สร้างใหม่ · เผยแพร่ · ปิดงาน',
  'actions'  => '<a href="'.route('photographer.events.create').'" class="pg-btn-primary"><i class="bi bi-plus-lg"></i> สร้างอีเวนต์</a>',
])

{{-- ═══════════════════════════════════════════════════════
     Quick stats — 4 status counters
     ═══════════════════════════════════════════════════════ --}}
@php
  // Compute stats from the paginated collection's underlying query.
  // We use a separate count query so pagination doesn't skew the
  // numbers — the user wants "totals across all my events", not
  // "what's on this page".
  $statsByStatus = [];
  try {
      $statsByStatus = \App\Models\Event::where('photographer_id', \Illuminate\Support\Facades\Auth::id())
          ->selectRaw("status, COUNT(*) as c")
          ->groupBy('status')
          ->pluck('c', 'status')
          ->toArray();
  } catch (\Throwable) { $statsByStatus = []; }

  $totalEvents = array_sum($statsByStatus);
  $currentFilter = request('status', 'all');
  $currentSearch = trim((string) request('q', ''));
@endphp

{{--
  Stats / filter chips — static class strings only.
  Tailwind v4 scans blade files at build time, so any class name
  built via string concatenation (`'border-'.$color.'-500'`) won't be
  visible to the scanner and will silently fall back to the browser
  default. Each entry below carries its full ACTIVE / IDLE class
  bundle inline so every utility is statically discoverable.
--}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-4 pg-anim d1">
  @php
    $statFilters = [
      ['key'=>'all','label'=>'ทั้งหมด','icon'=>'bi-collection','count'=>$totalEvents,
       'icon_color'=>'text-indigo-600 dark:text-indigo-400',
       'active'=>'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10',
       'idle'  =>'border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:border-indigo-300'],
      ['key'=>'draft','label'=>'ร่าง','icon'=>'bi-pencil-square','count'=>$statsByStatus['draft'] ?? 0,
       'icon_color'=>'text-amber-600 dark:text-amber-400',
       'active'=>'border-amber-500 bg-amber-50 dark:bg-amber-500/10',
       'idle'  =>'border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:border-amber-300'],
      ['key'=>'active','label'=>'เปิดขาย','icon'=>'bi-broadcast','count'=>($statsByStatus['active'] ?? 0) + ($statsByStatus['published'] ?? 0),
       'icon_color'=>'text-emerald-600 dark:text-emerald-400',
       'active'=>'border-emerald-500 bg-emerald-50 dark:bg-emerald-500/10',
       'idle'  =>'border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:border-emerald-300'],
      ['key'=>'closed','label'=>'ปิดงาน','icon'=>'bi-archive','count'=>$statsByStatus['closed'] ?? 0,
       'icon_color'=>'text-slate-600 dark:text-slate-400',
       'active'=>'border-slate-500 bg-slate-50 dark:bg-slate-500/10',
       'idle'  =>'border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:border-slate-300'],
    ];
  @endphp
  @foreach($statFilters as $stat)
    <a href="{{ url()->current() }}?status={{ $stat['key'] }}{{ $currentSearch ? '&q='.urlencode($currentSearch) : '' }}"
       class="block p-3 rounded-xl border transition no-underline
              {{ $currentFilter === $stat['key'] ? $stat['active'] : $stat['idle'] }}">
      <div class="flex items-center gap-2 mb-1">
        <i class="bi {{ $stat['icon'] }} {{ $stat['icon_color'] }} text-base"></i>
        <span class="text-[11px] sm:text-xs uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</span>
      </div>
      <div class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white leading-none">
        {{ $stat['count'] }}
      </div>
    </a>
  @endforeach
</div>

{{-- ═══════════════════════════════════════════════════════
     Search + filter bar
     ═══════════════════════════════════════════════════════ --}}
<div class="flex flex-col sm:flex-row gap-2 mb-4 pg-anim d2">
  <form method="GET" action="{{ url()->current() }}" class="flex-1 relative">
    @if($currentFilter !== 'all')
      <input type="hidden" name="status" value="{{ $currentFilter }}">
    @endif
    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
    <input type="text" name="q"
           value="{{ $currentSearch }}"
           placeholder="ค้นหาชื่ออีเวนต์ · สถานที่..."
           class="w-full pl-9 pr-4 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
    @if($currentSearch)
      <a href="{{ url()->current() }}{{ $currentFilter !== 'all' ? '?status='.$currentFilter : '' }}"
         class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500 text-sm">
        <i class="bi bi-x-circle-fill"></i>
      </a>
    @endif
  </form>
  <a href="{{ route('photographer.events.create') }}"
     class="sm:hidden inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold no-underline transition">
    <i class="bi bi-plus-lg"></i> สร้างอีเวนต์
  </a>
</div>

{{-- ═══════════════════════════════════════════════════════
     Events grid — 1 col mobile, 2 col tablet, 3 col desktop
     ═══════════════════════════════════════════════════════ --}}
@if($events->count() > 0)
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 pg-anim d3">
    @foreach($events as $event)
      @php
        $statusMap = [
          'draft'     => ['label'=>'ร่าง',     'color'=>'#d97706', 'bg'=>'#fef3c7', 'icon'=>'bi-pencil-square'],
          'active'    => ['label'=>'เปิดขาย', 'color'=>'#059669', 'bg'=>'#d1fae5', 'icon'=>'bi-broadcast'],
          'published' => ['label'=>'เผยแพร่',  'color'=>'#1d4ed8', 'bg'=>'#dbeafe', 'icon'=>'bi-megaphone'],
          'closed'    => ['label'=>'ปิดงาน',   'color'=>'#475569', 'bg'=>'#e2e8f0', 'icon'=>'bi-archive'],
          'archived'  => ['label'=>'เก็บถาวร', 'color'=>'#475569', 'bg'=>'#e2e8f0', 'icon'=>'bi-archive'],
        ];
        $st = $statusMap[$event->status] ?? $statusMap['draft'];

        // Photo count for the card meta line. Cached via Laravel's
        // `withCount('photos')` if the controller adds it; otherwise
        // we lazy-load (acceptable for a paginated list of <=20 items).
        $photoCount = $event->photos_count ?? null;
      @endphp

      <div class="ev-card">
        {{-- Cover --}}
        <a href="{{ route('photographer.events.show', $event) }}"
           class="block relative no-underline">
          <div class="ev-cover">
            {{-- Use the model's cover_image_url accessor — it routes the
                 stored key through StorageManager so R2 / S3 / local
                 disks all resolve to the right public URL. The plain
                 asset('storage/'.$key) shortcut only works when the
                 file actually lives on the local public disk, which
                 isn't true once the project switched to R2. --}}
            @php $coverUrl = $event->cover_image_url; @endphp
            @if($coverUrl)
              <img src="{{ $coverUrl }}"
                   alt="{{ $event->name }}"
                   loading="lazy"
                   onerror="this.style.display='none'; this.parentElement.querySelector('.ev-cover-placeholder')?.classList.remove('hidden');">
              <div class="ev-cover-placeholder hidden">
                <i class="bi bi-image"></i>
              </div>
            @else
              <div class="ev-cover-placeholder">
                <i class="bi bi-camera"></i>
              </div>
            @endif

            {{-- Status pill --}}
            <span class="ev-status"
                  style="color:{{ $st['color'] }}; background:rgba(255,255,255,0.95);">
              <i class="bi {{ $st['icon'] }}"></i>
              {{ $st['label'] }}
            </span>
          </div>
        </a>

        {{-- Body --}}
        <div class="p-4 flex-1 flex flex-col">
          <a href="{{ route('photographer.events.show', $event) }}"
             class="font-bold text-slate-900 dark:text-white text-base mb-1.5 line-clamp-2 hover:text-indigo-600 dark:hover:text-indigo-400 transition no-underline leading-snug">
            {{ $event->name }}
          </a>

          {{-- Meta — date + location + photo count --}}
          <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400 mb-3">
            @if($event->shoot_date)
              <span class="inline-flex items-center gap-1">
                <i class="bi bi-calendar3"></i>
                {{ \Carbon\Carbon::parse($event->shoot_date)->format('d/m/Y') }}
              </span>
            @endif
            @if($event->location)
              <span class="inline-flex items-center gap-1 truncate max-w-[180px]">
                <i class="bi bi-geo-alt"></i>
                {{ \Illuminate\Support\Str::limit($event->location, 25) }}
              </span>
            @endif
            @if(!is_null($photoCount))
              <span class="inline-flex items-center gap-1">
                <i class="bi bi-images"></i>
                {{ number_format($photoCount) }} รูป
              </span>
            @endif
          </div>

          {{-- Price + ID line --}}
          <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100 dark:border-white/5 mt-auto">
            <div>
              @if($event->is_free)
                <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                  <i class="bi bi-gift-fill"></i> ฟรี
                </span>
              @else
                <span class="text-sm font-bold text-slate-900 dark:text-white">
                  ฿{{ number_format($event->price_per_photo) }}
                </span>
                <span class="text-[11px] text-slate-500">/รูป</span>
              @endif
            </div>
            <span class="text-[10px] text-slate-400 font-mono">#{{ $event->id }}</span>
          </div>

          {{-- Actions — split into "primary" full-width + "secondary" icons --}}
          <div class="grid grid-cols-2 gap-2 mb-2">
            <a href="{{ route('photographer.events.edit', $event) }}"
               class="ev-action is-edit !w-auto !h-auto py-2 px-3 text-xs font-semibold gap-1.5 no-underline">
              <i class="bi bi-pencil"></i> แก้ไข
            </a>
            <a href="{{ route('photographer.events.photos.upload', $event) }}"
               class="ev-action is-upload !w-auto !h-auto py-2 px-3 text-xs font-semibold gap-1.5 no-underline">
              <i class="bi bi-cloud-upload"></i> อัปโหลด
            </a>
          </div>
          <div class="flex items-center gap-1.5">
            <a href="{{ route('photographer.events.show', $event) }}" class="ev-action is-view" title="ดู">
              <i class="bi bi-eye"></i>
            </a>
            <a href="{{ route('photographer.events.photos.index', $event) }}" class="ev-action is-photos" title="จัดการรูป">
              <i class="bi bi-images"></i>
            </a>
            <a href="{{ route('photographer.events.qrcode', $event) }}" class="ev-action is-qr" title="QR Code">
              <i class="bi bi-qr-code"></i>
            </a>
            <form action="{{ route('photographer.events.destroy', $event) }}"
                  method="POST"
                  class="ml-auto"
                  onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบอีเวนต์ ‘{{ $event->name }}’ ?\n\nการลบจะเอารูปทั้งหมดที่อัปโหลดไว้ออกจากระบบ — ไม่สามารถกู้คืนได้');">
              @csrf
              @method('DELETE')
              <button type="submit" class="ev-action is-delete" title="ลบ">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  @if($events->hasPages())
    <div class="flex justify-center mt-6">
      {{ $events->withQueryString()->links() }}
    </div>
  @endif

@else
  {{-- Empty state — different copy depending on whether the user is
       filtering or genuinely has no events. --}}
  <div class="pg-card pg-anim d3">
    <div class="ev-empty">
      <div class="ev-empty-icon">
        <i class="bi {{ $currentSearch || $currentFilter !== 'all' ? 'bi-search' : 'bi-calendar-x' }}"></i>
      </div>
      @if($currentSearch || $currentFilter !== 'all')
        <p class="font-bold text-base text-slate-700 dark:text-slate-200">ไม่พบอีเวนต์ที่ตรงกับเงื่อนไข</p>
        <p class="text-sm mt-1">ลองเปลี่ยนคำค้นหา หรือเลือกสถานะอื่น</p>
        <a href="{{ url()->current() }}" class="inline-flex items-center gap-1.5 mt-4 text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
          <i class="bi bi-arrow-counterclockwise"></i> ล้างตัวกรอง
        </a>
      @else
        <p class="font-bold text-base text-slate-700 dark:text-slate-200">ยังไม่มีอีเวนต์</p>
        <p class="text-sm mt-1">เริ่มสร้างอีเวนต์แรกของคุณ — อัปโหลดภาพแล้วขายได้เลย</p>
        <a href="{{ route('photographer.events.create') }}"
           class="inline-flex items-center gap-1.5 mt-4 px-5 py-2.5 rounded-lg bg-gradient-to-br from-indigo-600 to-purple-600 text-white font-bold text-sm no-underline shadow hover:shadow-lg transition">
          <i class="bi bi-plus-lg"></i> สร้างอีเวนต์แรก
        </a>
      @endif
    </div>
  </div>
@endif
@endsection
