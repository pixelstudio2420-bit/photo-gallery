@extends('layouts.photographer')

@section('title', 'AI Tools')

@php
  // All known AI features. Order = visual order on the index page.
  // Each entry is rendered only when the global feature flag is ON;
  // deprecated features (color_enhance / smart_captions / video_thumbnails)
  // are filtered out below so the tile grid stays focused for MVP.
  $featureMetaAll = [
    'duplicate_detection' => ['icon' => 'bi-files',           'label' => 'ตรวจจับรูปซ้ำ',  'route' => 'duplicates',     'color' => 'sky'],
    'quality_filter'      => ['icon' => 'bi-funnel',          'label' => 'คัดรูปเบลอ',     'route' => 'quality',        'color' => 'amber'],
    'best_shot'           => ['icon' => 'bi-trophy',          'label' => 'เลือกช็อตเด็ด',   'route' => 'best-shot',      'color' => 'yellow'],
    'color_enhance'       => ['icon' => 'bi-palette2',        'label' => 'ปรับสีอัตโนมัติ',  'route' => 'color-enhance',  'color' => 'pink'],
    'auto_tagging'        => ['icon' => 'bi-tags',            'label' => 'ติดแท็กอัตโนมัติ', 'route' => 'auto-tagging',   'color' => 'indigo'],
    'face_search'         => ['icon' => 'bi-person-bounding-box', 'label' => 'index ใบหน้า', 'route' => 'face-index',     'color' => 'fuchsia'],
    'smart_captions'      => ['icon' => 'bi-chat-quote',      'label' => 'Smart Captions', 'route' => 'smart-captions', 'color' => 'violet'],
    'video_thumbnails'    => ['icon' => 'bi-play-btn',        'label' => 'Video Thumbnail','route' => 'video-thumbnails','color' => 'rose'],
  ];
  $subs = app(\App\Services\SubscriptionService::class);
  $featureMeta = collect($featureMetaAll)
    ->filter(fn($_, $code) => $subs->featureGloballyEnabled($code))
    ->all();
  $apct = $creditsCap > 0 ? min(100, round(($creditsUsed / $creditsCap) * 100, 1)) : 0;
@endphp

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-stars',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'AI Tools',
  'subtitle' => 'เครื่องมือ AI สำหรับจัดการรูปในอีเวนต์ — กดเลือกฟีเจอร์แล้วเลือกอีเวนต์',
  'actions'  => '<a href="'.route('photographer.subscription.plans').'" class="pg-btn-ghost"><i class="bi bi-box"></i> ดูแผนทั้งหมด</a>',
])

@if(session('success'))
  <div class="pg-alert pg-alert--success mb-4">
    <i class="bi bi-check-circle-fill"></i>
    <div>{{ session('success') }}</div>
  </div>
@endif
@if(session('error'))
  <div class="pg-alert pg-alert--danger mb-4">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>{{ session('error') }}</div>
  </div>
@endif

{{-- AI Credits gauge --}}
<div class="pg-card pg-card-padded mb-6 pg-anim d1">
  <div class="flex items-center justify-between mb-2">
    <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">
      <i class="bi bi-cpu mr-1"></i>เครดิต AI ในรอบนี้
    </p>
    <span class="text-sm font-bold text-gray-900 dark:text-gray-100">
      <span class="is-mono">{{ number_format($creditsUsed) }}</span>
      <span class="text-gray-400">/ {{ number_format($creditsCap) }}</span>
    </span>
  </div>
  <div class="w-full bg-gray-100 dark:bg-white/[0.06] rounded-full h-2.5 overflow-hidden">
    @php
      // Gradient bar matching the auth-flow theme — picks shade by usage
      $barGradient = $apct >= 100
        ? 'linear-gradient(90deg, #f43f5e, #e11d48)'
        : ($apct >= 80
            ? 'linear-gradient(90deg, #fbbf24, #f59e0b)'
            : 'linear-gradient(90deg, #6366f1, #7c3aed, #ec4899)');
    @endphp
    <div class="h-2.5 rounded-full transition-all" style="width: {{ min(100, $apct) }}%; background: {{ $barGradient }};"></div>
  </div>
  <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
    เหลือ <strong class="text-indigo-600 dark:text-indigo-300">{{ number_format($creditsRemaining) }}</strong> เครดิต
    — แต่ละฟีเจอร์ใช้ 1 เครดิต/รูป
  </p>
</div>

{{-- Feature cards
     Each AI feature is a unified pg-card with:
       • Coloured-gradient icon badge (per feature accent)
       • Lock overlay when the plan doesn't include the feature
       • Hover lift via pg-card-hover
     The base .pg-card already handles dark-mode bg/border, so we don't
     need per-feature dark variants. --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
  @foreach($featureMeta as $code => $meta)
    @php
      $enabled = in_array($code, $features, true);
      // Map color shorthand → concrete hex pair for the icon badge.
      // Done as inline gradient so we don't depend on Tailwind JIT
      // having generated `bg-pink-100`/`text-pink-600` etc. (which it
      // sometimes drops if no other class needs them).
      $accentMap = [
        'sky'     => ['#0ea5e9', '#38bdf8'],
        'amber'   => ['#f59e0b', '#fbbf24'],
        'yellow'  => ['#eab308', '#facc15'],
        'pink'    => ['#ec4899', '#f472b6'],
        'indigo'  => ['#4f46e5', '#6366f1'],
        'fuchsia' => ['#c026d3', '#e879f9'],
        'violet'  => ['#7c3aed', '#a855f7'],
        'rose'    => ['#e11d48', '#f43f5e'],
      ];
      [$c1, $c2] = $accentMap[$meta['color']] ?? ['#6366f1', '#7c3aed'];
    @endphp
    <button type="button"
            @if($enabled)
              onclick="document.getElementById('event-picker').setAttribute('data-route', '{{ route('photographer.ai.'.$meta['route'], 0) }}'); document.getElementById('event-picker').setAttribute('data-label', '{{ $meta['label'] }}'); document.getElementById('event-picker-modal').classList.remove('hidden');"
            @else
              disabled
            @endif
            class="pg-card pg-card-padded text-left transition relative overflow-hidden
                   {{ $enabled ? 'pg-card-hover cursor-pointer' : 'opacity-60 cursor-not-allowed' }}">
      {{-- Corner gleam in the feature accent colour --}}
      <span class="absolute top-0 right-0 w-24 h-24 pointer-events-none rounded-tr-2xl"
            style="background: radial-gradient(circle at top right, {{ $c1 }}26 0%, transparent 70%);"></span>
      <div class="relative flex items-center gap-3 mb-2">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white shadow-sm shrink-0"
             style="background:linear-gradient(135deg, {{ $c1 }}, {{ $c2 }});">
          <i class="bi {{ $meta['icon'] }} text-lg"></i>
        </div>
        @if(!$enabled)
          <span class="ml-auto inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
            <i class="bi bi-lock-fill"></i> Locked
          </span>
        @endif
      </div>
      <p class="relative font-bold text-sm text-gray-900 dark:text-gray-100">{{ $meta['label'] }}</p>
      <p class="relative text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
        @if($enabled)
          คลิกเพื่อเลือกอีเวนต์
        @else
          ต้องอัปเกรดแผน
        @endif
      </p>
    </button>
  @endforeach
</div>

{{-- Event picker modal — unified pg-card style, dark-mode aware --}}
<div id="event-picker-modal" class="hidden fixed inset-0 z-[1060] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="pg-card max-w-md w-full overflow-hidden shadow-2xl">
    <div class="pg-card-header">
      <h5 class="pg-section-title m-0"><i class="bi bi-collection"></i> เลือกอีเวนต์เพื่อรัน <span id="event-picker-feature" class="text-indigo-600 dark:text-indigo-300"></span></h5>
      <button type="button" onclick="document.getElementById('event-picker-modal').classList.add('hidden');"
              class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
        <i class="bi bi-x-lg text-lg"></i>
      </button>
    </div>
    <div class="pg-card-body">
      @if($events->isEmpty())
        <div class="pg-empty">
          <div class="pg-empty-icon"><i class="bi bi-calendar-x"></i></div>
          <p class="font-medium">ยังไม่มีอีเวนต์</p>
          <p class="text-xs mt-1">สร้างอีเวนต์ก่อนเพื่อรัน AI</p>
        </div>
      @else
        <form id="event-picker" method="POST" data-route="" data-label="">
          @csrf
          <select name="_event_id" required
                  class="w-full rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-100 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 mb-3 px-3 py-2">
            @foreach($events as $ev)
              <option value="{{ $ev->id }}">{{ $ev->name }}</option>
            @endforeach
          </select>
          <button type="submit" class="pg-btn-primary w-full justify-center">
            <i class="bi bi-play-fill"></i> รัน
          </button>
        </form>
      @endif
    </div>
  </div>
</div>

<script>
document.getElementById('event-picker')?.addEventListener('submit', function(e) {
  e.preventDefault();
  const eventId = this.querySelector('[name=_event_id]').value;
  const tpl = this.getAttribute('data-route');     // .../{0}
  const url = tpl.replace(/\/0$/, '/' + eventId);
  this.setAttribute('action', url);
  this.removeEventListener('submit', arguments.callee);
  this.submit();
});

// Sync feature label into modal title
document.querySelectorAll('[onclick*="event-picker-modal"]').forEach(btn => {
  btn.addEventListener('click', () => {
    const label = document.getElementById('event-picker').getAttribute('data-label');
    document.getElementById('event-picker-feature').textContent = label || '';
  });
});
</script>

{{-- Recent task feed --}}
<div class="pg-card overflow-hidden pg-anim d3">
  <div class="pg-card-header">
    <h5 class="pg-section-title m-0"><i class="bi bi-clock-history"></i> งาน AI ล่าสุด</h5>
  </div>
  @if($recentTasks->isEmpty())
    <div class="pg-empty">
      <div class="pg-empty-icon"><i class="bi bi-cpu"></i></div>
      <p class="font-medium">ยังไม่มีงาน AI</p>
      <p class="text-xs mt-1">เริ่มใช้ AI ฟีเจอร์ด้านบนเพื่อสร้างงานแรก</p>
    </div>
  @else
    <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
      <table class="pg-table">
        <thead>
          <tr>
            <th>ฟีเจอร์</th>
            <th>อีเวนต์</th>
            <th>สถานะ</th>
            <th class="text-end">ใช้เครดิต</th>
            <th class="text-end">เมื่อไหร่</th>
          </tr>
        </thead>
        <tbody>
          @foreach($recentTasks as $task)
            <tr>
              <td class="font-medium">{{ $featureMeta[$task->kind]['label'] ?? $task->kind }}</td>
              <td class="text-gray-600">{{ $task->event?->name ?? '—' }}</td>
              <td>
                @php
                  $badge = match($task->status) {
                    'done'    => ['pg-pill--green', 'เสร็จสิ้น'],
                    'failed'  => ['pg-pill--rose',  'ล้มเหลว'],
                    'running' => ['pg-pill--amber', 'กำลังทำ'],
                    default   => ['pg-pill--gray',  'รอ'],
                  };
                @endphp
                <span class="pg-pill {{ $badge[0] }}">{{ $badge[1] }}</span>
              </td>
              <td class="text-end is-mono">{{ number_format($task->credits_used) }}</td>
              <td class="text-end text-xs text-gray-500">{{ $task->created_at?->diffForHumans() }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
