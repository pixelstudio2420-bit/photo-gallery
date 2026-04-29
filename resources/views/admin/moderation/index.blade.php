@extends('layouts.admin')

@section('title', 'ตรวจสอบภาพ')

@section('content')
<div class="space-y-5">

  {{-- Header --}}
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-shield-check text-indigo-500 mr-2"></i>ตรวจสอบเนื้อหาภาพ
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        ตรวจสอบภาพที่ระบบ AI ติดธงไว้ — อนุมัติ/ปฏิเสธได้ตามดุลยพินิจแอดมิน
      </p>
    </div>
    @if($stats['flagged'] > 0)
    <span class="px-4 py-2 bg-amber-500 text-white rounded-xl text-sm font-medium flex items-center gap-2">
      <i class="bi bi-flag-fill"></i> มีภาพรอตรวจ {{ $stats['flagged'] }} รายการ
    </span>
    @endif
  </div>

  {{-- Stats Cards --}}
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
    @php
      $statCards = [
        ['key' => 'total',    'label' => 'ทั้งหมด',    'color' => 'slate',   'icon' => 'bi-images'],
        ['key' => 'pending',  'label' => 'รอสแกน',     'color' => 'blue',    'icon' => 'bi-hourglass-split'],
        ['key' => 'flagged',  'label' => 'ติดธง',      'color' => 'amber',   'icon' => 'bi-flag-fill'],
        ['key' => 'rejected', 'label' => 'ถูกปฏิเสธ',   'color' => 'red',     'icon' => 'bi-x-octagon-fill'],
        ['key' => 'approved', 'label' => 'อนุมัติ',    'color' => 'emerald', 'icon' => 'bi-check-circle-fill'],
        ['key' => 'skipped',  'label' => 'ยกเว้น',     'color' => 'gray',    'icon' => 'bi-slash-circle'],
      ];
    @endphp
    @foreach($statCards as $c)
    <a href="{{ route('admin.moderation.index', ['status' => $c['key']]) }}"
       class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 hover:shadow-md transition
              {{ $status === $c['key'] ? 'ring-2 ring-indigo-400' : '' }}">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400">{{ $c['label'] }}</div>
          <div class="text-2xl font-bold text-{{ $c['color'] }}-600 dark:text-{{ $c['color'] }}-400">
            {{ number_format($stats[$c['key']]) }}
          </div>
        </div>
        <i class="bi {{ $c['icon'] }} text-2xl text-{{ $c['color'] }}-300 dark:text-{{ $c['color'] }}-500/50"></i>
      </div>
    </a>
    @endforeach
  </div>

  {{-- Filter Bar --}}
  <form method="GET" action="{{ route('admin.moderation.index') }}"
        class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[180px]">
      <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">สถานะ</label>
      <select name="status" class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 rounded-lg text-sm">
        <option value="all"      @selected($status === 'all')>ทั้งหมด</option>
        <option value="flagged"  @selected($status === 'flagged')>ติดธง</option>
        <option value="pending"  @selected($status === 'pending')>รอสแกน</option>
        <option value="rejected" @selected($status === 'rejected')>ถูกปฏิเสธ</option>
        <option value="approved" @selected($status === 'approved')>อนุมัติ</option>
        <option value="skipped"  @selected($status === 'skipped')>ยกเว้น</option>
      </select>
    </div>
    <div class="flex-1 min-w-[180px]">
      <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">อีเวนต์</label>
      <select name="event_id" class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 rounded-lg text-sm">
        <option value="">-- ทุกอีเวนต์ --</option>
        @foreach($events as $event)
        <option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>
          {{ $event->name }}
        </option>
        @endforeach
      </select>
    </div>
    <div class="min-w-[140px]">
      <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">คะแนน ≥</label>
      <input type="number" step="1" min="0" max="100" name="min_score" value="{{ request('min_score') }}"
             class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 rounded-lg text-sm">
    </div>
    <button type="submit" class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm font-medium">
      <i class="bi bi-search mr-1"></i>กรอง
    </button>
    @if(request()->hasAny(['event_id', 'min_score']))
    <a href="{{ route('admin.moderation.index', ['status' => $status]) }}"
       class="px-4 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5">
      ล้าง
    </a>
    @endif
  </form>

  {{-- Bulk Action Bar (hidden until checked) --}}
  <form method="POST" action="{{ route('admin.moderation.bulk') }}" id="bulkForm"
        class="bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 rounded-xl p-3
               flex flex-wrap items-center gap-3 hidden" data-bulk-bar>
    @csrf
    <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">
      เลือกแล้ว <span data-bulk-count>0</span> รายการ
    </span>
    <select name="action" class="px-3 py-1.5 border border-indigo-200 dark:border-indigo-500/30 rounded-lg text-sm bg-white dark:bg-slate-900">
      <option value="approve">อนุมัติทั้งหมด</option>
      <option value="reject">ปฏิเสธทั้งหมด</option>
      <option value="skip">ยกเว้นทั้งหมด</option>
      <option value="rescan">สแกนใหม่</option>
    </select>
    <input type="text" name="reason" placeholder="เหตุผล (ถ้าปฏิเสธ)"
           class="flex-1 min-w-[200px] px-3 py-1.5 border border-indigo-200 dark:border-indigo-500/30 rounded-lg text-sm bg-white dark:bg-slate-900">
    <button type="submit" class="px-4 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm font-medium">
      ดำเนินการ
    </button>
  </form>

  {{-- Results Grid --}}
  @if($photos->count() > 0)
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
    @foreach($photos as $photo)
    @php
      $statusColor = match($photo->moderation_status) {
        'flagged'  => 'amber',
        'rejected' => 'red',
        'approved' => 'emerald',
        'skipped'  => 'gray',
        default    => 'blue',
      };
      $cats = collect($photo->moderation_labels ?? [])
        ->whereNotNull('Name')
        ->map(fn($l) => ($l['ParentName'] ?? '') ?: ($l['Name'] ?? ''))
        ->unique()
        ->filter()
        ->take(3)
        ->values();
    @endphp
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl overflow-hidden
                hover:shadow-lg transition relative group">
      {{-- Selection checkbox --}}
      <label class="absolute top-2 left-2 z-10 w-6 h-6 rounded-md bg-white/80 dark:bg-slate-900/80 backdrop-blur
                    flex items-center justify-center cursor-pointer shadow-sm">
        <input type="checkbox" name="ids[]" value="{{ $photo->id }}" form="bulkForm"
               class="w-4 h-4 accent-indigo-500" data-bulk-check>
      </label>

      {{-- Preview (click to detail) --}}
      <a href="{{ route('admin.moderation.show', $photo->id) }}" class="block relative aspect-square overflow-hidden bg-gray-100 dark:bg-slate-900">
        @if($photo->thumbnail_url)
        <img src="{{ $photo->thumbnail_url }}" alt="Photo #{{ $photo->id }}"
             class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
             loading="lazy"
             {{-- Blur sensitive content by default — admin can click through --}}
             @if(in_array($photo->moderation_status, ['flagged', 'rejected'])) style="filter: blur(24px);" @endif>
        @if(in_array($photo->moderation_status, ['flagged', 'rejected']))
        <div class="absolute inset-0 flex items-center justify-center bg-black/20">
          <span class="px-3 py-1.5 rounded-lg bg-black/60 text-white text-xs font-medium">
            <i class="bi bi-eye-slash mr-1"></i>คลิกเพื่อดูภาพ
          </span>
        </div>
        @endif
        @else
        <div class="w-full h-full flex items-center justify-center text-gray-300 dark:text-gray-600">
          <i class="bi bi-image text-4xl"></i>
        </div>
        @endif
      </a>

      {{-- Info --}}
      <div class="p-3 space-y-2">
        <div class="flex items-center gap-2">
          <span class="px-2 py-0.5 rounded-md text-[0.7rem] font-semibold
                       bg-{{ $statusColor }}-100 dark:bg-{{ $statusColor }}-500/20
                       text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-300">
            {{ match($photo->moderation_status) {
                'flagged' => 'ติดธง',
                'rejected' => 'ปฏิเสธ',
                'approved' => 'อนุมัติ',
                'skipped' => 'ยกเว้น',
                'pending' => 'รอสแกน',
                default => $photo->moderation_status,
            } }}
          </span>
          @if($photo->moderation_score)
          <span class="text-xs font-mono font-semibold text-{{ $statusColor }}-600 dark:text-{{ $statusColor }}-400">
            {{ number_format($photo->moderation_score, 1) }}%
          </span>
          @endif
        </div>

        @if($cats->count() > 0)
        <div class="flex flex-wrap gap-1">
          @foreach($cats as $cat)
          <span class="px-1.5 py-0.5 rounded text-[0.65rem] bg-gray-100 dark:bg-white/5 text-gray-600 dark:text-gray-400">
            {{ $cat }}
          </span>
          @endforeach
        </div>
        @endif

        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
          <i class="bi bi-calendar-event text-[0.7rem]"></i>
          {{ optional($photo->event)->name ?? 'Event #' . $photo->event_id }}
        </div>

        @if($photo->uploader)
        <div class="text-xs text-gray-400 dark:text-gray-500 truncate">
          <i class="bi bi-person text-[0.7rem]"></i>
          {{ trim(($photo->uploader->first_name ?? '') . ' ' . ($photo->uploader->last_name ?? '')) ?: $photo->uploader->email }}
        </div>
        @endif

        {{-- Quick-action buttons for flagged --}}
        @if($photo->moderation_status === 'flagged')
        <div class="flex gap-1.5 pt-1">
          <form method="POST" action="{{ route('admin.moderation.approve', $photo->id) }}" class="flex-1">
            @csrf
            <button type="submit" class="w-full px-2 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-md text-xs font-medium">
              <i class="bi bi-check-lg"></i> อนุมัติ
            </button>
          </form>
          <form method="POST" action="{{ route('admin.moderation.reject', $photo->id) }}" class="flex-1">
            @csrf
            <button type="submit" class="w-full px-2 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-md text-xs font-medium"
                    onclick="return confirm('ปฏิเสธภาพนี้?')">
              <i class="bi bi-x-lg"></i> ปฏิเสธ
            </button>
          </form>
        </div>
        @endif
      </div>
    </div>
    @endforeach
  </div>

  {{-- Pagination --}}
  <div class="mt-4">
    {{ $photos->links() }}
  </div>

  @else
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-12 text-center">
    <i class="bi bi-shield-check text-6xl text-emerald-400"></i>
    <p class="mt-4 text-lg font-semibold text-slate-700 dark:text-gray-200">ไม่มีภาพในสถานะนี้</p>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
      @if($status === 'flagged')
        ยินดีด้วย! ไม่มีภาพที่ต้องตรวจสอบในขณะนี้
      @else
        ลองเปลี่ยนตัวกรองดู
      @endif
    </p>
  </div>
  @endif

</div>

<script>
// Bulk-selection helper — shows the bar when anything is checked.
(function () {
  const checks = document.querySelectorAll('[data-bulk-check]');
  const bar    = document.querySelector('[data-bulk-bar]');
  const count  = document.querySelector('[data-bulk-count]');
  if (!bar || !count) return;

  function update() {
    const selected = Array.from(checks).filter(c => c.checked).length;
    count.textContent = selected;
    bar.classList.toggle('hidden', selected === 0);
  }

  checks.forEach(c => c.addEventListener('change', update));

  // Also intercept submit to ensure FormData carries the checked IDs (they
  // are already attached via form="bulkForm", but Safari sometimes drops them).
  const form = document.getElementById('bulkForm');
  if (form) {
    form.addEventListener('submit', (e) => {
      const ids = Array.from(checks).filter(c => c.checked).map(c => c.value);
      if (ids.length === 0) {
        e.preventDefault();
        alert('กรุณาเลือกภาพอย่างน้อย 1 รายการ');
      }
    });
  }
})();
</script>
@endsection
