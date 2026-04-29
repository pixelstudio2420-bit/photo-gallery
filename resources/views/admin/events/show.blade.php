@extends('layouts.admin')

@section('title', $event->name)

@section('content')
{{-- Back Button --}}
<div class="mb-4">
  <a href="{{ route('admin.events.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-indigo-600 transition">
    <i class="bi bi-arrow-left mr-1"></i> กลับไปรายการอีเวนต์
  </a>
</div>

{{-- ═══ Hero Card ═══ --}}
@php
  $statusMap = [
    'active'    => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-600', 'dot' => 'bg-emerald-500'],
    'published' => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-600', 'dot' => 'bg-emerald-500'],
    'draft'     => ['bg' => 'bg-amber-500/10',   'text' => 'text-amber-600',   'dot' => 'bg-amber-500'],
    'archived'  => ['bg' => 'bg-gray-500/10',    'text' => 'text-gray-500',    'dot' => 'bg-gray-500'],
    'hidden'    => ['bg' => 'bg-gray-500/10',    'text' => 'text-gray-500',    'dot' => 'bg-gray-500'],
  ];
  $st = $statusMap[$event->status ?? 'draft'] ?? $statusMap['draft'];
@endphp

<div class="rounded-2xl overflow-hidden shadow-sm mb-6 bg-gradient-to-r from-indigo-600 to-violet-600">
  <div class="px-6 py-6">
    <div class="flex flex-col md:flex-row md:items-center gap-5">

      {{-- Cover Image / Placeholder --}}
      <div class="shrink-0">
        @if($event->cover_image_url)
          <img src="{{ $event->cover_image_url }}" alt="{{ $event->name }}"
               class="w-[120px] h-[120px] rounded-xl object-cover border-2 border-white/20 shadow-lg">
        @else
          <div class="w-[120px] h-[120px] rounded-xl bg-white/10 border-2 border-white/20 flex items-center justify-center">
            <i class="bi bi-camera text-4xl text-white/50"></i>
          </div>
        @endif
      </div>

      {{-- Event Info --}}
      <div class="flex-1 min-w-0">
        <h1 class="text-xl font-bold text-white tracking-tight">{{ $event->name }}</h1>
        <div class="flex items-center gap-2 mt-2 flex-wrap">
          @if($event->category)
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/20 text-white">
              {{ $event->category->name }}
            </span>
          @endif
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/20 text-white">
            <span class="w-1.5 h-1.5 rounded-full {{ $st['dot'] }} mr-1.5"></span>
            {{ ucfirst($event->status ?? 'draft') }}
          </span>
        </div>
        <div class="flex items-center gap-4 mt-3 text-sm text-white/70 flex-wrap">
          @if($event->shoot_date)
            <span><i class="bi bi-calendar3 mr-1"></i>{{ $event->shoot_date->format('d/m/Y') }}</span>
          @endif
          @if($event->full_location && $event->full_location !== '-')
            <span><i class="bi bi-geo-alt mr-1"></i>{{ $event->full_location }}</span>
          @endif
        </div>
      </div>

      {{-- Action Buttons --}}
      <div class="flex items-center gap-2 shrink-0 flex-wrap">
        <a href="{{ route('admin.events.edit', $event->id) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-white/15 text-white hover:bg-white/25 transition backdrop-blur-sm">
          <i class="bi bi-pencil mr-1.5"></i> แก้ไข
        </a>
        <form method="POST" action="{{ route('admin.events.toggle-status', $event->id) }}" class="inline">
          @csrf
          <button type="submit"
                  class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-white/15 text-white hover:bg-white/25 transition backdrop-blur-sm">
            <i class="bi bi-toggle-on mr-1.5"></i> สลับสถานะ
          </button>
        </form>
        <a href="{{ route('admin.events.qrcode', $event->id) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-white/15 text-white hover:bg-white/25 transition backdrop-blur-sm">
          <i class="bi bi-qr-code mr-1.5"></i> QR Code
        </a>
        <a href="{{ route('events.show', $event->slug ?: $event->id) }}" target="_blank"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-white/15 text-white hover:bg-white/25 transition backdrop-blur-sm">
          <i class="bi bi-box-arrow-up-right mr-1.5"></i> ดูหน้าสาธารณะ
        </a>
      </div>

    </div>
  </div>
</div>

{{-- ═══ Stats Cards ═══ --}}
@php
  $statCards = [
    ['icon' => 'bi-image',             'color' => 'blue',    'value' => number_format($stats['photos_count']),               'label' => 'ภาพถ่าย'],
    ['icon' => 'bi-bag',               'color' => 'emerald', 'value' => number_format($stats['orders_count']),               'label' => 'ออเดอร์'],
    ['icon' => 'bi-currency-exchange',  'color' => 'violet',  'value' => number_format($stats['total_revenue'], 0) . ' ฿',  'label' => 'รายได้'],
    ['icon' => 'bi-chat-dots',          'color' => 'amber',   'value' => number_format($stats['reviews_count']),             'label' => 'รีวิว'],
    ['icon' => 'bi-star-fill',          'color' => 'yellow',  'value' => number_format($stats['avg_rating'], 1),             'label' => 'คะแนน'],
    ['icon' => 'bi-eye',               'color' => 'gray',    'value' => number_format($stats['view_count']),                'label' => 'เข้าชม'],
  ];
@endphp
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
  @foreach($statCards as $sc)
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-3 text-center">
      <i class="bi {{ $sc['icon'] }} text-{{ $sc['color'] }}-500 text-lg mb-1"></i>
      <div class="font-bold text-lg leading-tight">{{ $sc['value'] }}</div>
      <small class="text-gray-400 text-xs">{{ $sc['label'] }}</small>
    </div>
  </div>
  @endforeach
</div>

{{-- ═══ Face-Search Coverage Widget ═══ --}}
<div id="faceCoverageWidget" class="mb-6" x-data="faceCoverage({{ $event->id }})" x-init="load()">
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06] p-4">
    <div class="flex items-start justify-between gap-3 mb-3">
      <div class="flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-500/10">
          <i class="bi bi-person-bounding-box"></i>
        </span>
        <div>
          <div class="font-semibold text-sm">Face-Search Index Coverage</div>
          <div class="text-xs text-gray-400" x-text="statusText"></div>
        </div>
      </div>
      <button type="button" @click="load()" :disabled="loading"
              class="text-xs text-indigo-600 hover:text-indigo-700 disabled:opacity-40">
        <i class="bi bi-arrow-clockwise" :class="loading && 'animate-spin'"></i> Refresh
      </button>
    </div>

    {{-- Progress bar --}}
    <div class="h-2 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
      <div class="h-full transition-all duration-500 rounded-full"
           :class="{
             'bg-emerald-500': pct >= 90,
             'bg-lime-500':    pct >= 60 && pct < 90,
             'bg-amber-500':   pct >= 30 && pct < 60,
             'bg-rose-500':    pct < 30
           }"
           :style="`width: ${pct}%`"></div>
    </div>

    <div class="flex items-center justify-between mt-2 text-xs text-gray-500">
      <div>
        <span x-text="indexed"></span> / <span x-text="active"></span> indexed
        <span class="mx-1 text-gray-300">•</span>
        <span x-text="pending"></span> pending
      </div>
      <div class="font-semibold text-gray-700 dark:text-gray-300">
        <span x-text="pct + '%'"></span>
      </div>
    </div>

    {{-- Warnings + actions --}}
    <template x-if="!rekognitionReady">
      <div class="mt-3 text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-md p-2 dark:bg-amber-500/10 dark:border-amber-500/20">
        <i class="bi bi-exclamation-triangle"></i>
        AWS Rekognition ยังไม่ตั้งค่า —
        <a href="{{ route('admin.settings.index') }}" class="underline font-semibold">ตั้งค่าที่นี่</a>
      </div>
    </template>

    <template x-if="rekognitionReady && pending > 0">
      <div class="mt-3 text-xs bg-indigo-50 border border-indigo-200 text-indigo-800 rounded-md p-2 dark:bg-indigo-500/10 dark:border-indigo-500/20">
        <i class="bi bi-info-circle"></i>
        มีรูป <span class="font-semibold" x-text="pending"></span> ใบยังไม่ index —
        รันคำสั่งนี้จาก server:
        <code class="block mt-1 bg-white/60 dark:bg-slate-900 px-2 py-1 rounded" x-text="reindexCmd"></code>
      </div>
    </template>
  </div>
</div>

@push('scripts')
<script>
function faceCoverage(eventId) {
  return {
    loading: false,
    pct: 0, active: 0, indexed: 0, pending: 0, total: 0,
    rekognitionReady: false,
    reindexCmd: '',
    statusText: 'กำลังโหลด…',
    async load() {
      this.loading = true;
      try {
        const resp = await fetch(`/admin/diagnostics/events/${eventId}/face-coverage`, {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        });
        const j = await resp.json();
        this.pct     = j.coverage_pct ?? 0;
        this.active  = j.active_photos ?? 0;
        this.indexed = j.indexed_photos ?? 0;
        this.pending = j.pending_photos ?? 0;
        this.total   = j.total_photos ?? 0;
        this.rekognitionReady = !!j.rekognition_ready;
        this.reindexCmd = j.reindex_cmd || '';
        this.statusText = this.rekognitionReady
          ? `คลังรูปพร้อมใช้ face search — collection: ${j.collection_id}`
          : 'AWS Rekognition ปิดอยู่ — face search ยังใช้ไม่ได้';
      } catch (e) {
        this.statusText = 'ดึงข้อมูลไม่สำเร็จ';
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>
@endpush

{{-- ═══ Tabs Section ═══ --}}
<div x-data="{ tab: 'overview' }" class="mb-6">

  {{-- Tab Navigation --}}
  <div class="bg-white rounded-t-xl border border-b-0 border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <nav class="flex gap-0 px-4 overflow-x-auto">
      @php
        $tabs = [
          'overview' => ['icon' => 'bi-info-circle',    'label' => 'ภาพรวม'],
          'photos'   => ['icon' => 'bi-images',         'label' => 'ภาพถ่าย'],
          'orders'   => ['icon' => 'bi-bag-check',      'label' => 'ออเดอร์'],
          'reviews'  => ['icon' => 'bi-star',           'label' => 'รีวิว'],
        ];
      @endphp
      @foreach($tabs as $key => $t)
      <button @click="tab = '{{ $key }}'"
              :class="tab === '{{ $key }}' ? 'border-indigo-500 text-indigo-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700'"
              class="flex items-center gap-1.5 px-4 py-3 text-sm border-b-2 transition whitespace-nowrap">
        <i class="bi {{ $t['icon'] }}"></i> {{ $t['label'] }}
      </button>
      @endforeach
    </nav>
  </div>

  {{-- Tab Content --}}
  <div class="bg-white rounded-b-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">

    {{-- ── Overview Tab ── --}}
    <div x-show="tab === 'overview'" x-transition class="p-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left Column: Event Details (2/3) --}}
        <div class="lg:col-span-2">
          <h6 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mb-4">
            <i class="bi bi-calendar-event mr-1 text-indigo-500"></i>ข้อมูลอีเวนต์
          </h6>
          <div class="space-y-4">
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ชื่ออีเวนต์</label>
              <p class="mt-0.5 font-medium text-gray-800 dark:text-gray-100">{{ $event->name }}</p>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">รายละเอียด</label>
              <p class="mt-0.5 text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $event->description ?: '-' }}</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">หมวดหมู่</label>
                <p class="mt-0.5">
                  @if($event->category)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-600">{{ $event->category->name }}</span>
                  @else
                    <span class="text-gray-400">-</span>
                  @endif
                </p>
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">วันที่ถ่าย</label>
                <p class="mt-0.5 font-medium">{{ $event->shoot_date?->format('d/m/Y') ?? '-' }}</p>
              </div>
            </div>

            {{-- Location Structured --}}
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">สถานที่</label>
              <div class="mt-1 space-y-2 text-sm">
                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-white/[0.06]">
                  <span class="text-gray-500">จังหวัด</span>
                  <span class="font-medium">{{ $event->province->name_th ?? '-' }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-white/[0.06]">
                  <span class="text-gray-500">อำเภอ/เขต</span>
                  <span class="font-medium">{{ $event->district->name_th ?? '-' }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-white/[0.06]">
                  <span class="text-gray-500">ตำบล/แขวง</span>
                  <span class="font-medium">{{ $event->subdistrict->name_th ?? '-' }}</span>
                </div>
                @if($event->location_detail)
                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-white/[0.06]">
                  <span class="text-gray-500">รายละเอียด</span>
                  <span class="font-medium">{{ $event->location_detail }}</span>
                </div>
                @endif
              </div>
            </div>

            {{-- Photographer --}}
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ช่างภาพ</label>
              <div class="mt-1">
                @if($event->photographer)
                  <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 text-white font-bold text-sm">
                      {{ mb_strtoupper(mb_substr($event->photographer->first_name ?? $event->photographer->name ?? 'P', 0, 1, 'UTF-8'), 'UTF-8') }}
                    </div>
                    <div>
                      <div class="font-medium text-gray-800 dark:text-gray-100">{{ $event->photographer->first_name ?? '' }} {{ $event->photographer->last_name ?? '' }}</div>
                      <div class="text-xs text-gray-400">{{ $event->photographer->email ?? '' }}</div>
                    </div>
                  </div>
                @else
                  <span class="text-gray-400">-</span>
                @endif
              </div>
            </div>

            {{-- Visibility --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">การมองเห็น</label>
                <p class="mt-0.5">
                  @if($event->visibility === 'public')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-600">
                      <i class="bi bi-globe mr-1"></i>สาธารณะ
                    </span>
                  @elseif($event->visibility === 'password')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-600">
                      <i class="bi bi-lock mr-1"></i>ใส่รหัสผ่าน
                    </span>
                  @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                      <i class="bi bi-eye-slash mr-1"></i>{{ ucfirst($event->visibility ?? 'public') }}
                    </span>
                  @endif
                </p>
              </div>
              @if($event->visibility === 'password' && $event->event_password)
              <div>
                <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">รหัสผ่าน</label>
                <p class="mt-0.5">
                  <code class="bg-gray-100 text-gray-700 px-2.5 py-1 rounded-lg text-sm dark:bg-white/10 dark:text-gray-300">{{ $event->event_password }}</code>
                </p>
              </div>
              @endif
            </div>
          </div>
        </div>

        {{-- Right Column: Pricing & Meta (1/3) --}}
        <div class="lg:col-span-1">
          {{-- Pricing Card --}}
          <h6 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mb-4">
            <i class="bi bi-tag mr-1 text-violet-500"></i>ราคาและข้อมูล
          </h6>
          <div class="p-4 rounded-xl border border-gray-100 dark:border-white/[0.06] space-y-4">
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ราคาต่อรูป</label>
              <div class="mt-1 flex items-center gap-2">
                @if($event->is_free)
                  <span class="text-xl font-bold text-emerald-500">ฟรี</span>
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-600">FREE</span>
                @else
                  <span class="text-xl font-bold text-indigo-600">฿{{ number_format($event->price_per_photo, 2) }}</span>
                @endif
              </div>
            </div>

            @if($event->drive_folder_id || $event->drive_folder_link)
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Google Drive</label>
              <div class="mt-1">
                @if($event->drive_folder_link)
                  <a href="{{ $event->drive_folder_link }}" target="_blank"
                     class="inline-flex items-center text-sm text-indigo-500 hover:text-indigo-700 transition">
                    <i class="bi bi-folder2-open mr-1"></i>เปิดโฟลเดอร์
                  </a>
                @endif
                @if($event->drive_folder_id)
                  <div class="mt-1">
                    <code class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded dark:bg-white/10 dark:text-gray-400">{{ $event->drive_folder_id }}</code>
                  </div>
                @endif
              </div>
            </div>
            @endif

            <div class="pt-3 border-t border-gray-100 dark:border-white/[0.06] space-y-3 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">สร้างเมื่อ</span>
                <span>{{ $event->created_at?->format('d/m/Y H:i') }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">อัพเดทล่าสุด</span>
                <span>{{ $event->updated_at?->format('d/m/Y H:i') }}</span>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    {{-- ── Photos Tab ── --}}
    <div x-show="tab === 'photos'" x-transition x-cloak class="p-6">
      <h6 class="font-semibold mb-4">
        <i class="bi bi-images mr-1 text-blue-500"></i>ภาพถ่ายทั้งหมด ({{ $event->photos->count() }})
      </h6>
      @if($event->photos->count())
      <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
        @foreach($event->photos as $photo)
        <div class="group relative aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-white/5">
          <img src="{{ $photo->thumbnail_url }}" alt="{{ $photo->filename ?? '' }}"
               class="w-full h-full object-cover transition group-hover:scale-105" loading="lazy">
          <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition flex items-center justify-center opacity-0 group-hover:opacity-100">
            <span class="text-white text-xs font-medium">
              <i class="bi bi-zoom-in"></i>
            </span>
          </div>
        </div>
        @endforeach
      </div>
      @else
      <div class="text-center py-12">
        <i class="bi bi-images text-4xl text-gray-300 dark:text-gray-600"></i>
        <p class="text-gray-400 mt-2 text-sm">ยังไม่มีภาพถ่ายในอีเวนต์นี้</p>
      </div>
      @endif
    </div>

    {{-- ── Orders Tab ── --}}
    <div x-show="tab === 'orders'" x-transition x-cloak class="p-6">
      <h6 class="font-semibold mb-4">
        <i class="bi bi-bag-check mr-1 text-emerald-500"></i>ออเดอร์ล่าสุด ({{ $stats['orders_count'] }})
      </h6>
      @if($recentOrders->count())
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-white/[0.02]">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ลูกค้า</th>
              <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">จำนวนเงิน</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">วันที่</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            @foreach($recentOrders as $order)
            @php
              $orderSt = match($order->status) {
                'completed', 'paid' => 'bg-emerald-500/10 text-emerald-600',
                'pending_payment', 'pending' => 'bg-amber-500/10 text-amber-600',
                'cancelled', 'failed' => 'bg-red-500/10 text-red-500',
                default => 'bg-gray-200/60 text-gray-500',
              };
            @endphp
            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
              <td class="px-4 py-3 font-mono text-gray-500">#{{ $order->id }}</td>
              <td class="px-4 py-3">
                @if($order->user)
                  <span class="font-medium text-gray-800 dark:text-gray-100">{{ $order->user->first_name ?? '' }} {{ $order->user->last_name ?? '' }}</span>
                @else
                  <span class="text-gray-400">-</span>
                @endif
              </td>
              <td class="px-4 py-3 text-right font-semibold">฿{{ number_format($order->total ?? 0, 2) }}</td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $orderSt }}">{{ ucfirst($order->status) }}</span>
              </td>
              <td class="px-4 py-3 text-gray-500">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @else
      <div class="text-center py-12">
        <i class="bi bi-bag-x text-4xl text-gray-300 dark:text-gray-600"></i>
        <p class="text-gray-400 mt-2 text-sm">ยังไม่มีออเดอร์สำหรับอีเวนต์นี้</p>
      </div>
      @endif
    </div>

    {{-- ── Reviews Tab ── --}}
    <div x-show="tab === 'reviews'" x-transition x-cloak class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h6 class="font-semibold">
          <i class="bi bi-star mr-1 text-amber-500"></i>รีวิวล่าสุด ({{ $stats['reviews_count'] }})
        </h6>
        @if($stats['avg_rating'] > 0)
        <div class="flex items-center gap-1.5">
          @for($i = 1; $i <= 5; $i++)
            <i class="bi bi-star{{ $i <= round($stats['avg_rating']) ? '-fill' : '' }} text-amber-400"></i>
          @endfor
          <span class="font-semibold ml-1">{{ number_format($stats['avg_rating'], 1) }}</span>
        </div>
        @endif
      </div>
      @if($recentReviews->count())
      <div class="space-y-4">
        @foreach($recentReviews as $review)
        <div class="p-4 rounded-xl border border-gray-100 dark:border-white/[0.06] hover:shadow-sm transition">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium text-gray-800 dark:text-gray-100">{{ $review->user->first_name ?? 'ผู้ใช้' }} {{ $review->user->last_name ?? '' }}</span>
                <div class="flex gap-0.5">
                  @for($i = 1; $i <= 5; $i++)
                    <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }} text-amber-400 text-xs"></i>
                  @endfor
                </div>
              </div>
              <p class="text-gray-600 dark:text-gray-300 text-sm">{{ $review->comment ?: '-' }}</p>
            </div>
            <span class="text-xs text-gray-400 whitespace-nowrap">{{ $review->created_at?->format('d/m/Y') }}</span>
          </div>
        </div>
        @endforeach
      </div>
      @else
      <div class="text-center py-12">
        <i class="bi bi-star text-4xl text-gray-300 dark:text-gray-600"></i>
        <p class="text-gray-400 mt-2 text-sm">ยังไม่มีรีวิวสำหรับอีเวนต์นี้</p>
      </div>
      @endif
    </div>

  </div>
</div>
@endsection
