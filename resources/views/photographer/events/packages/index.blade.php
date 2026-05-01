@extends('layouts.photographer')

@section('title', 'แพ็กเกจขายภาพ — ' . $event->name)

@section('content')
<div class="max-w-7xl mx-auto">

  {{-- ── Header ─────────────────────────────────── --}}
  <div class="flex items-start justify-between gap-3 mb-5 flex-wrap">
    <div>
      <div class="text-xs text-gray-400 mb-1">
        <a href="{{ route('photographer.events.show', $event) }}" class="hover:text-indigo-500">
          <i class="bi bi-arrow-left mr-0.5"></i> {{ $event->name }}
        </a>
      </div>
      <h1 class="text-2xl font-bold tracking-tight">
        <i class="bi bi-box-seam mr-2 text-indigo-500"></i> แพ็กเกจขายภาพ
      </h1>
      <p class="text-sm text-gray-500 mt-1">
        ตั้งราคาขายแบบเหมาเป็นแพ็ก — เพิ่มยอดขายเฉลี่ยต่อออเดอร์ (AOV) 2-4 เท่า
      </p>
    </div>

    <div class="text-right">
      <div class="text-xs text-gray-400">ราคาภาพเดี่ยว</div>
      <div class="font-bold text-indigo-600">฿{{ number_format($perPhoto, 0) }}/รูป</div>
    </div>
  </div>

  @if(session('success'))
    <div class="mb-4 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-sm">
      <i class="bi bi-check-circle mr-1"></i>{{ session('success') }}
    </div>
  @endif

  @if(session('error'))
    <div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300 text-sm">
      <i class="bi bi-exclamation-circle mr-1"></i>{{ session('error') }}
    </div>
  @endif

  @if($errors->any())
    <div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300 text-sm">
      <i class="bi bi-exclamation-circle mr-1"></i>{{ $errors->first() }}
    </div>
  @endif

  {{-- ── Price-drift warning ──────────────────────────────────────
       Surfaces bundles whose price doesn't match what (price_per_photo
       × count × discount%) currently yields. Common cause: photographer
       changed the per-photo price after bundles were seeded, leaving
       the bundle prices stale (and possibly far cheaper than intended).
       Single-click "Recalculate" rebuilds them from the current price. --}}
  @if(!empty($priceDrift))
    <div class="mb-5 p-4 rounded-xl bg-amber-50 dark:bg-amber-500/10 border-2 border-amber-300 dark:border-amber-500/30">
      <div class="flex items-start gap-3 mb-3">
        <i class="bi bi-exclamation-triangle-fill text-amber-500 text-2xl shrink-0 mt-0.5"></i>
        <div class="flex-1 min-w-0">
          <div class="font-bold text-amber-900 dark:text-amber-200 mb-1">
            ราคาแพ็กเกจไม่ตรงกับราคา/รูปปัจจุบัน ({{ count($priceDrift) }} แพ็กเกจ)
          </div>
          <p class="text-xs text-amber-800 dark:text-amber-300/80 leading-relaxed mb-2">
            ราคา/รูปปัจจุบันคือ <strong>฿{{ number_format($perPhoto, 0) }}</strong> แต่ราคาแพ็กเกจบางใบยังคำนวณจากราคาเดิม —
            ลูกค้าอาจได้ส่วนลดมาก/น้อยกว่าที่ตั้งใจ
          </p>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-lg p-3 mb-3 text-xs space-y-1">
        @foreach($priceDrift as $d)
          <div class="flex items-center justify-between gap-2 py-1 border-b border-amber-100 dark:border-amber-500/10 last:border-0">
            <div class="flex items-center gap-2">
              <span class="font-medium">{{ $d['bundle']->name }}</span>
              <span class="text-gray-400">·</span>
              <span class="text-gray-500">ส่วนลด {{ (int) $d['bundle']->discount_pct }}%</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <span class="text-red-500 line-through">฿{{ number_format($d['actual'], 0) }}</span>
              <i class="bi bi-arrow-right text-amber-500"></i>
              <span class="text-emerald-600 font-bold">฿{{ number_format($d['expected'], 0) }}</span>
              <span class="text-[10px] text-gray-400">({{ $d['drift_pct'] }}% off)</span>
            </div>
          </div>
        @endforeach
      </div>

      <form method="POST" action="{{ route('photographer.events.packages.recalculate', $event) }}"
            onsubmit="return confirm('ปรับราคาแพ็กเกจ {{ count($priceDrift) }} ใบให้ตรงกับราคา/รูปปัจจุบัน (฿{{ number_format($perPhoto, 0) }}) — ส่วนลด% เดิมจะถูกเก็บไว้ ยืนยันหรือไม่?');">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold transition shadow-md">
          <i class="bi bi-arrow-clockwise"></i>
          ปรับราคาทั้งหมดให้ตรงกับ ฿{{ number_format($perPhoto, 0) }}/รูป
        </button>
      </form>
    </div>
  @endif

  {{-- ── Stats Cards ───────────────────────────── --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="rounded-xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/[0.06] p-4">
      <div class="text-xs text-gray-400 mb-1">แพ็กเกจที่มี</div>
      <div class="text-2xl font-bold">{{ $packages->count() }}</div>
    </div>
    <div class="rounded-xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/[0.06] p-4">
      <div class="text-xs text-gray-400 mb-1">ยอดขายรวม</div>
      <div class="text-2xl font-bold">{{ $stats['total_purchases'] }} ครั้ง</div>
    </div>
    <div class="rounded-xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/[0.06] p-4">
      <div class="text-xs text-gray-400 mb-1">รายได้จากแพ็กเกจ</div>
      <div class="text-2xl font-bold text-emerald-600">฿{{ number_format($stats['total_revenue'], 0) }}</div>
    </div>
    <div class="rounded-xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/[0.06] p-4">
      <div class="text-xs text-gray-400 mb-1">ขายดีที่สุด</div>
      <div class="text-sm font-semibold truncate">{{ $stats['best_seller']?->name ?? '—' }}</div>
      <div class="text-xs text-gray-400">{{ $stats['best_seller']?->purchase_count ?? 0 }} ครั้ง</div>
    </div>
  </div>

  {{-- ── Apply Template Section ─────────────────── --}}
  <div class="mb-6 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 p-5 text-white" x-data="{ open: false, selected: '{{ $suggestedTemplate }}' }">
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <div class="font-semibold mb-1">
          <i class="bi bi-lightning-charge mr-1"></i> ใช้เทมเพลตด่วน
        </div>
        <p class="text-white/80 text-xs">
          เลือกเทมเพลตที่ตรงกับประเภทอีเวนต์ — ระบบจะสร้างแพ็กเกจให้ทันทีพร้อมราคาที่ปรับให้ขายดีที่สุด
          @if($suggestedTemplate)
            <span class="inline-block px-2 py-0.5 bg-white/20 rounded ml-1 text-[10px]">
              แนะนำสำหรับอีเวนต์ของคุณ: {{ $templates[$suggestedTemplate]['label'] ?? $suggestedTemplate }}
            </span>
          @endif
        </p>
      </div>
      <button @click="open = !open" class="px-3 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-xs font-medium transition shrink-0">
        <i class="bi bi-grid-3x3-gap mr-1"></i><span x-text="open ? 'ซ่อน' : 'เลือกเทมเพลต'"></span>
      </button>
    </div>

    <div x-show="open" x-transition class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
      @foreach($templates as $key => $tpl)
        <form method="POST" action="{{ route('photographer.events.packages.template', $event) }}"
              onsubmit="return confirm('การใช้เทมเพลตจะ ลบแพ็กเกจเดิมทั้งหมด แล้วสร้างใหม่ — ยืนยันหรือไม่?');"
              class="bg-white/15 hover:bg-white/25 backdrop-blur-sm rounded-lg p-3 transition cursor-pointer group">
          @csrf
          <input type="hidden" name="template" value="{{ $key }}">
          <button type="submit" class="w-full text-left">
            <div class="flex items-center gap-2 mb-1">
              <i class="bi {{ $tpl['icon'] }} text-lg"></i>
              <span class="font-semibold text-sm">{{ $tpl['label'] }}</span>
              @if($key === $suggestedTemplate)
                <span class="ml-auto text-[9px] px-1.5 py-0.5 bg-yellow-300 text-yellow-900 rounded">แนะนำ</span>
              @endif
            </div>
            <p class="text-[11px] text-white/80 leading-snug">{{ $tpl['desc'] }}</p>
            <div class="mt-2 text-[10px] text-white/70">
              จะสร้าง {{ count($tpl['bundles']) }} แพ็กเกจ
              <i class="bi bi-arrow-right ml-1 group-hover:ml-2 transition-all"></i>
            </div>
          </button>
        </form>
      @endforeach
    </div>
  </div>

  {{-- ── Bundle List ─────────────────────────── --}}
  <div class="rounded-xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/[0.06] overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
      <h6 class="font-semibold text-sm">
        <i class="bi bi-list-ul mr-1 text-indigo-500"></i> แพ็กเกจปัจจุบัน
      </h6>
      <button x-data @click="document.getElementById('add-bundle-modal').showModal()" class="text-xs px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 dark:hover:bg-indigo-500/20 rounded-lg font-medium transition">
        <i class="bi bi-plus-lg mr-0.5"></i> เพิ่มแพ็กเกจเอง
      </button>
    </div>

    @if($packages->isEmpty())
      <div class="p-12 text-center">
        <i class="bi bi-box text-5xl text-gray-200 dark:text-gray-700 mb-3"></i>
        <h3 class="font-semibold mb-1">ยังไม่มีแพ็กเกจ</h3>
        <p class="text-sm text-gray-500 mb-4">เลือกเทมเพลตด้านบน หรือเพิ่มแพ็กเกจเอง</p>
      </div>
    @else
      <div class="divide-y divide-gray-100 dark:divide-white/[0.04]">
        @foreach($packages as $pkg)
          <div class="px-5 py-4 hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition flex items-center gap-4">
            {{-- Type icon --}}
            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0
              @if($pkg->bundle_type === 'face_match') bg-pink-500/10 text-pink-600
              @elseif($pkg->bundle_type === 'event_all') bg-purple-500/10 text-purple-600
              @else bg-indigo-500/10 text-indigo-600
              @endif">
              <i class="bi
                @if($pkg->bundle_type === 'face_match') bi-person-bounding-box
                @elseif($pkg->bundle_type === 'event_all') bi-collection
                @else bi-stack
                @endif"></i>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold">{{ $pkg->name }}</span>
                @if($pkg->is_featured)
                  <span class="px-1.5 py-0.5 bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-400 rounded text-[10px] font-bold">
                    <i class="bi bi-star-fill"></i> ขายดีที่สุด
                  </span>
                @endif
                @if($pkg->badge)
                  <span class="px-1.5 py-0.5 bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300 rounded text-[10px]">{{ $pkg->badge }}</span>
                @endif
                @if(!$pkg->is_active)
                  <span class="px-1.5 py-0.5 bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400 rounded text-[10px]">ปิดใช้งาน</span>
                @endif
              </div>
              <div class="text-xs text-gray-400 mt-0.5">
                @if($pkg->bundle_type === 'count' && $pkg->photo_count)
                  เลือก {{ $pkg->photo_count }} รูป — ฿{{ number_format($pkg->per_photo_price, 0) }}/รูป
                @elseif($pkg->bundle_type === 'face_match')
                  ใช้ Face Search — ลด {{ (int) $pkg->discount_pct }}% (สูงสุด ฿{{ number_format($pkg->max_price, 0) }})
                @elseif($pkg->bundle_type === 'event_all')
                  เหมาทั้งอีเวนต์ — {{ $pkg->photo_count ?? 'all' }} รูป
                @endif
                · ขายแล้ว {{ $pkg->purchase_count }} ครั้ง
              </div>
            </div>

            {{-- Price --}}
            <div class="text-right shrink-0">
              @if($pkg->bundle_type === 'face_match')
                <div class="text-sm text-gray-500">ราคาผันแปร</div>
                <div class="text-xs text-gray-400">ลด {{ (int) $pkg->discount_pct }}%</div>
              @else
                @if($pkg->original_price && $pkg->original_price > $pkg->price)
                  <div class="text-xs text-gray-400 line-through">฿{{ number_format($pkg->original_price, 0) }}</div>
                @endif
                <div class="text-lg font-bold text-indigo-600">฿{{ number_format($pkg->price, 0) }}</div>
                @if($pkg->savings_pct > 0)
                  <div class="text-[10px] text-emerald-600">-{{ $pkg->savings_pct }}%</div>
                @endif
              @endif
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-1 shrink-0">
              <form method="POST" action="{{ route('photographer.events.packages.feature', [$event, $pkg]) }}">
                @csrf
                <button class="w-8 h-8 rounded-lg hover:bg-gray-100 dark:hover:bg-white/[0.04] flex items-center justify-center transition" title="ปักหมุดเป็น 'ขายดีที่สุด'">
                  <i class="bi {{ $pkg->is_featured ? 'bi-star-fill text-yellow-500' : 'bi-star text-gray-400' }}"></i>
                </button>
              </form>

              <button x-data
                      @click="$dispatch('edit-bundle', {{ json_encode([
                        'id' => $pkg->id,
                        'name' => $pkg->name,
                        'photo_count' => $pkg->photo_count,
                        'price' => $pkg->price,
                        'original_price' => $pkg->original_price,
                        'description' => $pkg->description,
                        'bundle_subtitle' => $pkg->bundle_subtitle,
                        'badge' => $pkg->badge,
                        'is_active' => $pkg->is_active,
                        'discount_pct' => $pkg->discount_pct,
                        'max_price' => $pkg->max_price,
                        'bundle_type' => $pkg->bundle_type,
                      ]) }})"
                      class="w-8 h-8 rounded-lg hover:bg-gray-100 dark:hover:bg-white/[0.04] flex items-center justify-center transition" title="แก้ไข">
                <i class="bi bi-pencil text-gray-400 hover:text-indigo-500"></i>
              </button>

              <form method="POST" action="{{ route('photographer.events.packages.destroy', [$event, $pkg]) }}"
                    onsubmit="return confirm('ลบแพ็กเกจ &quot;{{ $pkg->name }}&quot; แน่นอน?');">
                @csrf @method('DELETE')
                <button class="w-8 h-8 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 flex items-center justify-center transition" title="ลบ">
                  <i class="bi bi-trash text-gray-400 hover:text-red-500"></i>
                </button>
              </form>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  {{-- ── Add Bundle Modal ──────────────────────── --}}
  <dialog id="add-bundle-modal" class="rounded-2xl backdrop:bg-black/50 p-0 max-w-md w-full mx-auto bg-white dark:bg-slate-800">
    <form method="POST" action="{{ route('photographer.events.packages.store', $event) }}" class="p-6"
          x-data="{
            type: 'count',
            photoCount: 5,
            updatePrice() {
              if (this.type === 'count' && this.photoCount > 0) {
                const original = this.photoCount * {{ $perPhoto }};
                document.querySelector('[name=original_price]').value = original;
              }
            }
          }">
      @csrf
      <h3 class="font-bold text-lg mb-4">เพิ่มแพ็กเกจ</h3>

      <div class="space-y-3">
        <div>
          <label class="block text-xs font-semibold mb-1">ประเภท</label>
          <select name="bundle_type" x-model="type" @change="updatePrice()" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:bg-slate-700 dark:border-white/10 text-sm">
            <option value="count">จำนวนภาพ (เลือก N รูป)</option>
            <option value="face_match">เหมารูปตัวเอง (Face Match)</option>
            <option value="event_all">เหมาทั้งอีเวนต์</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold mb-1">ชื่อแพ็กเกจ</label>
          <input type="text" name="name" required class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:bg-slate-700 dark:border-white/10 text-sm" placeholder="เช่น 5 รูปคุ้ม">
        </div>

        <div x-show="type !== 'face_match'">
          <label class="block text-xs font-semibold mb-1">จำนวนรูป</label>
          <input type="number" name="photo_count" x-model="photoCount" @input="updatePrice()" min="1" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:bg-slate-700 dark:border-white/10 text-sm">
        </div>

        <div class="grid grid-cols-2 gap-3" x-show="type !== 'face_match'">
          <div>
            <label class="block text-xs font-semibold mb-1">ราคาขาย (฿)</label>
            <input type="number" name="price" required step="0.01" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:bg-slate-700 dark:border-white/10 text-sm">
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1">ราคาปกติ (ขีดทับ)</label>
            <input type="number" name="original_price" step="0.01" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:bg-slate-700 dark:border-white/10 text-sm">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3" x-show="type === 'face_match'">
          <div>
            <label class="block text-xs font-semibold mb-1">ส่วนลด (%)</label>
            <input type="number" name="discount_pct" value="50" min="0" max="100" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:bg-slate-700 dark:border-white/10 text-sm">
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1">ราคาสูงสุด (cap, ฿)</label>
            <input type="number" name="max_price" value="1500" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:bg-slate-700 dark:border-white/10 text-sm">
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold mb-1">Badge (ขายดี / คุ้มสุด)</label>
          <input type="text" name="badge" maxlength="50" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:bg-slate-700 dark:border-white/10 text-sm">
        </div>

        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="is_featured" value="1" class="rounded">
          <span>ปักหมุดเป็น "ขายดีที่สุด" (ยกระดับ UI)</span>
        </label>
      </div>

      <div class="flex gap-2 mt-5 justify-end">
        <button type="button" onclick="document.getElementById('add-bundle-modal').close()" class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-white/[0.06] text-sm">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white text-sm font-medium">บันทึก</button>
      </div>
    </form>
  </dialog>

  {{-- ── Tips ──────────────────────────────────── --}}
  <div class="rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 p-5 text-sm text-blue-900 dark:text-blue-200 leading-relaxed">
    <div class="font-semibold mb-2"><i class="bi bi-lightbulb mr-1"></i> เคล็ดลับการตั้งราคา</div>
    <ul class="list-disc list-inside space-y-1 text-xs">
      <li><strong>Decoy Effect</strong> — มี 3 แพ็กเกจขั้นต่ำ (เล็ก/กลาง/ใหญ่) ลูกค้าจะเลือกตัวกลางเป็นส่วนใหญ่</li>
      <li><strong>"ขายดีที่สุด"</strong> — ปักหมุดบนแพ็กเกจ 6 รูปหรือ 10 รูป จะเพิ่ม conversion 20-40%</li>
      <li><strong>Face Bundle</strong> — เพิ่มแพ็ก "เหมารูปตัวเอง" ดึงลูกค้าที่อยากได้รูปตัวเองทั้งหมด AOV เพิ่ม 3-5 เท่า</li>
      <li><strong>ราคาขีดทับ</strong> — ตั้งราคาปกติให้สูงกว่าราคาขายเสมอ ลูกค้าจะรู้สึก "ประหยัด"</li>
    </ul>
  </div>
</div>
@endsection
