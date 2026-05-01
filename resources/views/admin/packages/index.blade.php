@extends('layouts.admin')

@section('title', 'จัดการแพ็คเกจ')

@section('content')
<div class="flex justify-between items-center mb-4">
  <div>
    <h4 class="font-bold text-xl tracking-tight">
      <i class="bi bi-box-seam mr-2 text-indigo-500"></i>จัดการแพ็คเกจ
    </h4>
    <p class="text-xs text-gray-500 mt-1">
      ภาพรวมทุกแพ็กเกจในระบบ — ช่างภาพแก้ไขเฉพาะอีเวนต์ตัวเองได้ที่หน้า
      <a href="#" class="text-indigo-500 hover:underline">photographer/events/&lt;event&gt;/packages</a>
    </p>
  </div>
  <div class="flex items-center gap-2">
    <a href="{{ route('admin.packages.audit') }}"
       class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-lg font-medium text-sm px-4 py-2 transition inline-flex items-center gap-1">
      <i class="bi bi-shield-check text-indigo-500"></i> Audit Log
    </a>
    <button type="button" onclick="openAddModal()"
      class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium text-sm px-5 py-2 transition hover:from-indigo-600 hover:to-indigo-700">
      <i class="bi bi-plus-lg mr-1"></i> เพิ่มแพ็คเกจ
    </button>
  </div>
</div>

{{-- Stats banner — explains that "5 bundles repeating" = 5 templates × N events --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
  <div class="rounded-xl bg-white border border-gray-100 p-4">
    <div class="text-xs text-gray-500 mb-1"><i class="bi bi-collection mr-1"></i>แพ็กเกจทั้งหมด</div>
    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['total'] ?? 0) }}</div>
  </div>
  <div class="rounded-xl bg-white border border-gray-100 p-4">
    <div class="text-xs text-gray-500 mb-1"><i class="bi bi-calendar-event mr-1 text-indigo-500"></i>กระจายอยู่ในอีเวนต์</div>
    <div class="text-2xl font-bold text-indigo-600">{{ number_format($stats['events'] ?? 0) }} อีเวนต์</div>
  </div>
  <div class="rounded-xl bg-white border border-gray-100 p-4">
    <div class="text-xs text-gray-500 mb-1"><i class="bi bi-globe mr-1 text-emerald-500"></i>แพ็กเกจกลาง (ทุกอีเวนต์)</div>
    <div class="text-2xl font-bold text-emerald-600">{{ number_format($stats['global'] ?? 0) }}</div>
  </div>
  <div class="rounded-xl bg-white border border-gray-100 p-4">
    <div class="text-xs text-gray-500 mb-1"><i class="bi bi-star-fill mr-1 text-amber-500"></i>ปักหมุด "ขายดีที่สุด"</div>
    <div class="text-2xl font-bold text-amber-600">{{ number_format($stats['featured'] ?? 0) }}</div>
  </div>
</div>

{{-- Notice: explain the per-event nature so admin doesn't think
     duplicate names = bug. Each event gets its own template-driven
     bundle set, so the same names ("3 รูป", "6 รูป", ...) recur per
     event — but they're distinct rows with different prices possible. --}}
<div class="mb-5 p-3 rounded-xl bg-blue-50 border border-blue-200 text-xs text-blue-900 leading-relaxed">
  <i class="bi bi-info-circle mr-1"></i>
  <strong>ทำไมเห็น "3 รูป", "6 รูป", ฯลฯ ซ้ำกัน?</strong> —
  ระบบสร้างแพ็กเกจ {{ $stats['total'] > 0 && $stats['events'] > 0 ? round($stats['total'] / max($stats['events'], 1)) : 5 }} ใบให้แต่ละอีเวนต์โดยอัตโนมัติเมื่อสร้างอีเวนต์ใหม่
  (Standard template = 3/6/10/20 รูป + เหมารูปตัวเอง) ดังนั้นชื่อจะซ้ำกัน
  แต่ <strong>row นั้นแตกต่างกันโดย <code>event_id</code></strong> และราคาตั้งต่างกันได้ตามอีเวนต์ —
  ใช้ filter ด้านล่างเพื่อดูแพ็กเกจของอีเวนต์ใดอีเวนต์หนึ่ง
</div>

{{-- Filters --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.packages.index') }}">
    <div class="af-grid">

      {{-- Event dropdown --}}
      <div>
        <label class="af-label">อีเวนต์</label>
        <select name="event_id" class="af-input">
          <option value="">ทั้งหมด</option>
          @foreach($events as $event)
            <option value="{{ $event->id }}" {{ request('event_id') == $event->id ? 'selected' : '' }}>{{ $event->name }}</option>
          @endforeach
        </select>
      </div>

      {{-- Status dropdown --}}
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>ใช้งาน</option>
          <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>ปิดใช้งาน</option>
        </select>
      </div>

      {{-- Actions --}}
      <div class="af-actions">
        <div class="af-spinner" x-show="loading" x-cloak></div>
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>

    </div>
  </form>
</div>

{{--
  Bulk Smart-Pricing recalc bar — same form posts whether a single
  event is filtered (uses ?event_id) or "all events" (no filter).
  Dispatches the existing #recalc-confirm-modal Alpine state below;
  Alpine submits the form on confirm to avoid an accidental "missed
  the price hike" run.
--}}
<div class="flex items-start justify-between gap-3 mb-3 p-4 rounded-xl bg-gradient-to-br from-indigo-500/[0.06] to-purple-500/[0.06] border border-indigo-500/15">
  <div class="flex items-start gap-3">
    <div class="w-10 h-10 rounded-lg bg-indigo-500/15 text-indigo-600 flex items-center justify-center shrink-0">
      <i class="bi bi-magic text-lg"></i>
    </div>
    <div class="min-w-0">
      <div class="font-semibold text-slate-800 mb-0.5">คำนวณราคาแพ็กเกจอัตโนมัติ</div>
      <div class="text-xs text-slate-600 leading-relaxed">
        ระบบจะคำนวณราคาทุกแพ็กเกจ (ยกเว้น <code class="bg-slate-100 px-1 rounded text-[10px]">face_match</code>)
        จาก <strong>ราคา/ภาพของแต่ละอีเวนต์</strong> + ขนาด bundle + curve ส่วนลดตาม tier (low / mid / premium)
        พร้อม charm pricing
        @if(request('event_id'))
          — ตอนนี้กำลังกรองเฉพาะอีเวนต์ ID <strong>{{ request('event_id') }}</strong>
        @else
          — รันทุกอีเวนต์ที่ตั้ง <code class="bg-slate-100 px-1 rounded text-[10px]">price_per_photo</code> ไว้แล้ว
        @endif
        · <span class="text-slate-500">หลังคำนวณยังแก้ไขแยกรายแพ็กเกจได้ตามต้องการ</span>
      </div>
    </div>
  </div>
  <button type="button"
          x-data
          @click="$dispatch('open-recalc-modal')"
          class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 text-white text-sm font-semibold shadow-md shadow-indigo-500/25 transition">
    <i class="bi bi-magic"></i>
    <span>คำนวณราคาทั้งหมด</span>
  </button>
</div>

{{-- Recalculate confirmation modal — second-step gate so a misclick
     can't suddenly rewrite every package price on the platform. --}}
<div x-data="{ open: false }" x-on:open-recalc-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
      <div class="text-center">
        <div class="w-14 h-14 rounded-full bg-indigo-500/10 flex items-center justify-center mx-auto mb-4">
          <i class="bi bi-magic text-2xl text-indigo-500"></i>
        </div>
        <h6 class="font-bold text-lg mb-2">คำนวณราคาแพ็กเกจใหม่?</h6>
        <p class="text-gray-600 text-sm mb-2 leading-relaxed">
          ระบบจะเขียนทับราคาเดิมของทุกแพ็กเกจ
          @if(request('event_id'))
            ใน <strong>อีเวนต์ ID {{ request('event_id') }}</strong>
          @else
            ใน <strong>ทุกอีเวนต์</strong>
          @endif
          ด้วยราคาที่คำนวณจาก curve ของ Smart Pricing
        </p>
        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2.5 mb-5 leading-relaxed">
          <i class="bi bi-exclamation-triangle-fill"></i>
          แพ็กเกจ <code>face_match</code> และแพ็กเกจไม่ผูกอีเวนต์จะถูกข้าม
          (ไม่มี <code>price_per_photo</code> ให้คำนวณ)
        </p>
        <div class="flex gap-2 justify-center">
          <button type="button" @click="open = false" class="text-sm rounded-lg bg-gray-500/10 text-gray-600 font-medium px-5 py-2 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
          <form method="POST" action="{{ route('admin.packages.recalculate-all') }}">
            @csrf
            @if(request('event_id'))
              <input type="hidden" name="event_id" value="{{ request('event_id') }}">
            @endif
            <button type="submit" class="text-sm rounded-lg bg-gradient-to-br from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 text-white font-semibold px-5 py-2 transition">
              <i class="bi bi-magic mr-1"></i>คำนวณเลย
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

@if(session('success'))
<div class="flex items-center gap-2 mb-3 px-4 py-3 rounded-xl bg-emerald-500/10 text-emerald-800">
  <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="flex items-center gap-2 mb-3 px-4 py-3 rounded-xl bg-red-500/10 text-red-800">
  <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
</div>
@endif

<div id="admin-table-area">
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-indigo-500/[0.03]">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider pl-5">อีเวนต์</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">แพ็กเกจ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ประเภท</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">จำนวน</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ราคา</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @php
          // Track when the event_id changes between rows so we can render
          // a visible separator → admin sees clear "section breaks" between
          // each event's bundle group instead of one undifferentiated list.
          $previousEventId = null;
        @endphp
        @forelse($packages as $pkg)
        @php
          // Bundle-type styling map. Drives both the "ประเภท" column chip
          // colour and the leading icon in the package name cell.
          $typeStyle = match($pkg->bundle_type ?? 'count') {
              'face_match' => ['bg' => 'bg-pink-500/10',    'text' => 'text-pink-600',    'icon' => 'bi-person-bounding-box', 'label' => 'Face Match'],
              'event_all'  => ['bg' => 'bg-purple-500/10',  'text' => 'text-purple-600',  'icon' => 'bi-collection',          'label' => 'เหมาทั้งงาน'],
              default      => ['bg' => 'bg-indigo-500/10',  'text' => 'text-indigo-600',  'icon' => 'bi-stack',               'label' => 'จำนวนรูป'],
          };
          $eventChanged = $previousEventId !== ($pkg->event_id ?? null);
          $previousEventId = $pkg->event_id ?? null;
        @endphp
        @if($eventChanged && !$loop->first)
          {{-- Visual separator between event groups so the admin's eye
               registers "new event starting here" before scanning rows. --}}
          <tr class="border-t-4 border-gray-200"></tr>
        @endif
        <tr class="hover:bg-gray-50/50 transition align-middle">
          {{-- Event column — most prominent now. Colored chip with the
               event name (or "ทั่วไป" badge for global / event_id=null). --}}
          <td class="pl-5 px-4 py-3">
            @if($pkg->event_id && $pkg->event)
              <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-semibold border border-indigo-200">
                <i class="bi bi-calendar-event"></i>
                <span class="max-w-[160px] truncate">{{ $pkg->event->name }}</span>
              </div>
              <div class="text-[10px] text-gray-400 mt-0.5">ID: {{ $pkg->event_id }}</div>
            @else
              <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                <i class="bi bi-globe"></i> ทั่วไป (ทุกอีเวนต์)
              </div>
            @endif
          </td>

          {{-- Package name + featured indicator --}}
          <td class="px-4 py-3">
            <div class="flex items-center gap-2">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 {{ $typeStyle['bg'] }}">
                <i class="bi {{ $typeStyle['icon'] }} {{ $typeStyle['text'] }}"></i>
              </div>
              <div class="min-w-0">
                <div class="font-medium flex items-center gap-1.5">
                  {{ $pkg->name }}
                  @if($pkg->is_featured ?? false)
                    <i class="bi bi-star-fill text-amber-500" title="ขายดีที่สุด"></i>
                  @endif
                </div>
                @if($pkg->badge ?? false)
                  <div class="text-[10px] text-gray-500 mt-0.5">{{ $pkg->badge }}</div>
                @endif
                @if($pkg->bundle_subtitle ?? false)
                  <div class="text-xs text-gray-400 truncate max-w-[200px]">{{ $pkg->bundle_subtitle }}</div>
                @endif
              </div>
            </div>
          </td>

          {{-- Bundle type chip --}}
          <td class="px-4 py-3">
            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium {{ $typeStyle['bg'] }} {{ $typeStyle['text'] }}">
              {{ $typeStyle['label'] }}
            </span>
          </td>

          {{-- Photo count (face_match has no fixed count) --}}
          <td class="px-4 py-3">
            @if($pkg->bundle_type === 'face_match')
              <code class="bg-pink-500/[0.08] text-pink-600 px-2 py-1 rounded-md text-xs">ผันแปร</code>
            @else
              <code class="bg-indigo-500/[0.08] text-indigo-500 px-2 py-1 rounded-md text-xs">{{ number_format($pkg->photo_count ?? 0) }} รูป</code>
            @endif
          </td>

          {{-- Price (with original_price strikethrough if discounted) --}}
          <td class="px-4 py-3 font-medium">
            @if($pkg->bundle_type === 'face_match')
              {{-- face_match has no fixed price — it's computed live
                   from per_photo × matched-count × discount, capped
                   at max_price. Showing the stored 0.00 placeholder
                   would mislead admins into thinking it's free. --}}
              <div class="text-pink-600 font-semibold text-sm">ราคาผันแปร</div>
              <div class="text-[10px] text-gray-500 mt-0.5">
                ลด {{ (int) ($pkg->discount_pct ?? 50) }}%
                @if($pkg->max_price)
                  · cap ฿{{ number_format($pkg->max_price, 0) }}
                @endif
              </div>
            @else
              @if($pkg->original_price && $pkg->original_price > $pkg->price)
                <div class="text-xs text-gray-400 line-through">{{ number_format($pkg->original_price, 0) }} ฿</div>
              @endif
              <div>{{ number_format($pkg->price, 2) }} ฿</div>
            @endif
          </td>

          <td class="px-4 py-3">
            @if($pkg->is_active)
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-500">ใช้งาน</span>
            @else
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-500/10 text-gray-500">ปิดใช้งาน</span>
            @endif
          </td>
          <td class="px-4 py-3">
            <div class="flex gap-1">
              <button type="button"
                class="w-8 h-8 rounded-lg bg-indigo-500/[0.08] text-indigo-500 flex items-center justify-center transition hover:bg-indigo-500/[0.15]"
                title="แก้ไข"
                onclick="openEditModal({{ json_encode($pkg) }})">
                <i class="bi bi-pencil text-xs"></i>
              </button>
              <button type="button"
                class="w-8 h-8 rounded-lg bg-red-500/[0.08] text-red-500 flex items-center justify-center transition hover:bg-red-500/[0.15]"
                title="ลบ"
                onclick="confirmDelete({{ $pkg->id }}, '{{ addslashes($pkg->name) }}')">
                <i class="bi bi-trash3 text-xs"></i>
              </button>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="text-center py-12">
            <i class="bi bi-box-seam text-4xl text-gray-300"></i>
            <p class="text-gray-500 mt-2 text-sm">ยังไม่มีแพ็คเกจ</p>
            <button type="button" class="mt-3 text-sm px-4 py-1.5 rounded-lg bg-indigo-500/10 text-indigo-500 transition hover:bg-indigo-500/[0.15]" onclick="openAddModal()">
              <i class="bi bi-plus-lg mr-1"></i> เพิ่มแพ็คเกจ
            </button>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
</div>{{-- end #admin-table-area --}}

{{-- Pagination --}}
@if($packages->hasPages())
<div id="admin-pagination-area" class="mt-4">
  {{ $packages->withQueryString()->links() }}
</div>
@endif

{{-- Add/Edit Package Modal --}}
<div x-data="{ open: false }" x-on:open-pkg-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl max-w-md w-full">
      <div class="flex items-center justify-between px-6 pt-5 pb-0">
        <h5 class="font-bold text-lg" id="pkgModalTitle">เพิ่มแพ็คเกจ</h5>
        <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 transition">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="px-6 pb-6 pt-4">
        <form id="pkgForm" method="POST">
          @csrf
          <input type="hidden" name="_method" id="pkgMethod" value="POST">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อแพ็คเกจ <span class="text-red-500">*</span></label>
              <input type="text" name="name" id="pkgName" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required placeholder="เช่น Basic, Standard, Premium" maxlength="100">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">จำนวนรูป <span class="text-red-500">*</span></label>
              <input type="number" name="photo_count" id="pkgPhotoCount" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required min="1" placeholder="10">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5 flex items-center justify-between gap-2">
                <span>ราคา (บาท) <span class="text-red-500">*</span></span>
                {{--
                  Smart-Pricing auto-fill button. Hits
                  /admin/packages/calculate-price with current event_id
                  + photo_count and writes the suggested price into the
                  field below. The field stays editable — admin can
                  tweak after auto-fill if a manual override is wanted.
                --}}
                <button type="button" id="pkgAutoCalc"
                        class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-1 rounded-md bg-indigo-500/10 text-indigo-600 hover:bg-indigo-500/15 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        title="คำนวณอัตโนมัติจากราคา/ภาพของอีเวนต์">
                  <i class="bi bi-magic"></i>
                  <span>คำนวณอัตโนมัติ</span>
                </button>
              </label>
              <input type="number" name="price" id="pkgPrice" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required min="0" step="0.01" placeholder="199.00">
              {{-- Inline hint that appears AFTER auto-calc fills the
                   field. Stays hidden by default and after a manual
                   edit so the admin doesn't think the value is locked. --}}
              <p id="pkgAutoCalcHint" class="hidden text-[11px] text-emerald-600 mt-1.5 leading-snug"></p>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">รายละเอียด</label>
              <textarea name="description" id="pkgDescription" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="รายละเอียดแพ็คเกจ..." maxlength="500"></textarea>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">อีเวนต์</label>
              <select name="event_id" id="pkgEventId" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">-- ทั่วไป (ไม่ระบุอีเวนต์) --</option>
                @foreach($events as $event)
                  <option value="{{ $event->id }}">{{ $event->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" id="pkgIsActive" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm font-medium text-gray-700">เปิดใช้งาน</span>
              </label>
            </div>
          </div>
          <div class="flex gap-2 justify-end mt-5">
            <button type="button" @click="open = false" class="rounded-lg bg-gray-500/10 text-gray-500 font-medium px-6 py-2.5 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
            <button type="submit" class="rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-medium px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700">
              <i class="bi bi-check-lg mr-1"></i> <span id="pkgSubmitText">เพิ่มแพ็คเกจ</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Delete Confirm Modal --}}
<div x-data="{ open: false }" x-on:open-delete-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
      <div class="text-center">
        <div class="w-14 h-14 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-4">
          <i class="bi bi-trash3-fill text-2xl text-red-500"></i>
        </div>
        <h6 class="font-bold text-lg mb-2">ลบแพ็คเกจ?</h6>
        <p class="text-gray-500 text-sm mb-6" id="deleteModalText">คุณต้องการลบแพ็คเกจนี้ใช่หรือไม่?</p>
        <div class="flex gap-2 justify-center">
          <button type="button" @click="open = false" class="text-sm rounded-lg bg-gray-500/10 text-gray-500 font-medium px-5 py-2 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
          <form id="deleteForm" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm rounded-lg bg-red-500 text-white font-medium px-5 py-2 transition hover:bg-red-600">ลบ</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
function openAddModal() {
  document.getElementById('pkgModalTitle').textContent = 'เพิ่มแพ็คเกจ';
  document.getElementById('pkgSubmitText').textContent = 'เพิ่มแพ็คเกจ';
  document.getElementById('pkgForm').action = '{{ route("admin.packages.store") }}';
  document.getElementById('pkgMethod').value = 'POST';
  document.getElementById('pkgName').value = '';
  document.getElementById('pkgPhotoCount').value = '';
  document.getElementById('pkgPrice').value = '';
  document.getElementById('pkgDescription').value = '';
  document.getElementById('pkgEventId').value = '';
  document.getElementById('pkgIsActive').checked = true;
  window.dispatchEvent(new CustomEvent('open-pkg-modal'));
}

function openEditModal(pkg) {
  document.getElementById('pkgModalTitle').textContent = 'แก้ไขแพ็คเกจ';
  document.getElementById('pkgSubmitText').textContent = 'บันทึกการแก้ไข';
  document.getElementById('pkgForm').action = '/admin/packages/' + pkg.id;
  document.getElementById('pkgMethod').value = 'PUT';
  document.getElementById('pkgName').value = pkg.name || '';
  document.getElementById('pkgPhotoCount').value = pkg.photo_count || '';
  document.getElementById('pkgPrice').value = pkg.price || '';
  document.getElementById('pkgDescription').value = pkg.description || '';
  document.getElementById('pkgEventId').value = pkg.event_id || '';
  document.getElementById('pkgIsActive').checked = !!pkg.is_active;
  window.dispatchEvent(new CustomEvent('open-pkg-modal'));
}

function confirmDelete(id, name) {
  document.getElementById('deleteModalText').textContent = 'คุณต้องการลบแพ็คเกจ "' + name + '" ใช่หรือไม่?';
  document.getElementById('deleteForm').action = '/admin/packages/' + id;
  window.dispatchEvent(new CustomEvent('open-delete-modal'));
}

/* ──────────────────────────────────────────────────────────────────
   Auto-calculate price (Smart Pricing)
   ──────────────────────────────────────────────────────────────────
   Reads the modal's current event_id + photo_count, calls the
   /admin/packages/calculate-price endpoint, and writes the result
   into the Price field. Edits to the field clear the hint so the
   admin doesn't think their override is being lied about. */
(function () {
  const btn         = document.getElementById('pkgAutoCalc');
  const eventSel    = document.getElementById('pkgEventId');
  const photoInput  = document.getElementById('pkgPhotoCount');
  const priceInput  = document.getElementById('pkgPrice');
  const hint        = document.getElementById('pkgAutoCalcHint');
  if (!btn) return;

  // The route() helper isn't available in JS, so we hardcode the
  // path. Server-side route name is admin.packages.calculate-price.
  const ENDPOINT = '{{ route('admin.packages.calculate-price') }}';

  btn.addEventListener('click', async () => {
    const photoCount = parseInt(photoInput.value, 10);
    const eventId    = eventSel.value;

    if (!photoCount || photoCount < 1) {
      hint.textContent = 'กรุณาใส่จำนวนรูปก่อน แล้วลองใหม่';
      hint.className = 'text-[11px] text-amber-600 mt-1.5 leading-snug';
      hint.classList.remove('hidden');
      photoInput.focus();
      return;
    }

    const params = new URLSearchParams({ photo_count: String(photoCount) });
    if (eventId) params.set('event_id', eventId);

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i><span>กำลังคำนวณ...</span>';

    try {
      const res = await fetch(`${ENDPOINT}?${params}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();

      priceInput.value = data.price;

      const tierTH = ({ low: 'ราคาประหยัด', mid: 'ราคากลาง', premium: 'ราคาพรีเมียม' })[data.tier] || data.tier;
      const sourceTH = data.source === 'event' ? 'จากอีเวนต์ที่เลือก' : 'จากค่าเฉลี่ยทั้งระบบ';
      hint.innerHTML =
        '<i class="bi bi-check-circle-fill"></i> ' +
        `คำนวณ ${sourceTH}: ราคา/ภาพ ฿${data.per_photo.toLocaleString()} (${tierTH}) · ` +
        `ลด ${data.discount_pct}% · ประหยัด ฿${data.savings.toLocaleString()} ` +
        `<span class="opacity-60">(แก้ไขได้ตามต้องการ)</span>`;
      hint.className = 'text-[11px] text-emerald-600 mt-1.5 leading-snug';
      hint.classList.remove('hidden');
    } catch (e) {
      hint.textContent = 'คำนวณไม่สำเร็จ — ลองใหม่อีกครั้ง';
      hint.className = 'text-[11px] text-red-600 mt-1.5 leading-snug';
      hint.classList.remove('hidden');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-magic"></i><span>คำนวณอัตโนมัติ</span>';
    }
  });

  // Hide the hint once the admin starts overriding the value — keeps
  // the UI honest about whether the visible price is auto vs manual.
  priceInput.addEventListener('input', () => {
    hint.classList.add('hidden');
  });
})();
</script>
@endpush

@endsection
