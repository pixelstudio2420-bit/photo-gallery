@extends('layouts.admin')

@section('title', 'อัปโหลด & แสดงภาพ')

@push('styles')
@include('admin.settings._shared-styles')
<style>
  .tw-range {
    -webkit-appearance: none; appearance: none;
    width: 100%; height: 6px;
    background: rgb(226 232 240);
    border-radius: 9999px;
    outline: none;
  }
  .dark .tw-range { background: rgb(51 65 85); }
  .tw-range::-webkit-slider-thumb {
    -webkit-appearance: none; appearance: none;
    width: 16px; height: 16px;
    border-radius: 50%;
    background: rgb(99 102 241);
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(0,0,0,.2);
  }
  .tw-range::-moz-range-thumb {
    width: 16px; height: 16px;
    border-radius: 50%;
    background: rgb(99 102 241);
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(0,0,0,.2);
  }
  .section-card {
    border-radius: 1rem;
    background: white;
    border: 1px solid rgb(226 232 240);
    box-shadow: 0 1px 2px rgba(0,0,0,.03);
    overflow: hidden;
  }
  .dark .section-card {
    background: rgb(15 23 42);
    border-color: rgba(255,255,255,.08);
  }
  .section-header {
    padding: 1.25rem 1.25rem 0.75rem;
    border-bottom: 1px solid rgb(241 245 249);
  }
  .dark .section-header { border-bottom-color: rgba(255,255,255,.06); }
  .section-body { padding: 1.25rem; }
  .input-xs {
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    background: white;
    border: 1px solid rgb(203 213 225);
    color: rgb(15 23 42);
    outline: none;
  }
  .input-xs:focus {
    box-shadow: 0 0 0 2px rgba(99,102,241,.3);
    border-color: rgb(99 102 241);
  }
  .dark .input-xs {
    background: rgb(30 41 59);
    border-color: rgba(255,255,255,.10);
    color: rgb(241 245 249);
  }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- Page Header --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shadow-lg shadow-amber-500/30">
        <i class="bi bi-lightning-charge-fill text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">อัปโหลด &amp; แสดงภาพ</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          ควบคุมการบีบอัดรูป ขนาด thumbnail/preview และความเร็วแสดงผลแกลเลอรี่ของอีเวนต์
        </p>
      </div>
    </div>
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg
              bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10
              text-slate-700 dark:text-slate-200
              hover:bg-slate-50 dark:hover:bg-slate-700 transition">
      <i class="bi bi-arrow-left"></i> กลับไปตั้งค่า
    </a>
  </div>

  {{-- Flash --}}
  @if(session('success'))
    <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl
                bg-emerald-50 dark:bg-emerald-500/10
                text-emerald-700 dark:text-emerald-300
                border border-emerald-200 dark:border-emerald-500/30 text-sm">
      <i class="bi bi-check-circle-fill"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif
  @if($errors->any())
    <div class="mb-4 flex items-start gap-2 px-4 py-3 rounded-xl
                bg-rose-50 dark:bg-rose-500/10
                text-rose-700 dark:text-rose-300
                border border-rose-200 dark:border-rose-500/30 text-sm">
      <i class="bi bi-exclamation-triangle-fill mt-0.5"></i>
      <ul class="list-disc pl-4 space-y-1">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- GD status --}}
  @if(!$gdAvailable)
    <div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-xl
                bg-rose-50 dark:bg-rose-500/10
                text-rose-700 dark:text-rose-300
                border border-rose-200 dark:border-rose-500/30 text-sm">
      <i class="bi bi-x-octagon-fill text-lg"></i>
      <div>
        <div class="font-semibold">PHP GD Extension ไม่พร้อมใช้งาน</div>
        <div class="text-xs opacity-80 mt-0.5">การบีบอัดฝั่งเซิร์ฟเวอร์จะไม่ทำงาน — ติดต่อผู้ดูแลเซิร์ฟเวอร์ให้เปิด extension=gd</div>
      </div>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.photo-performance.update') }}" id="perfForm" class="space-y-5">
    @csrf

    {{-- ═══ 1. Server-side original compression ═══ --}}
    <div class="section-card">
      <div class="section-header flex items-center justify-between gap-3">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-amber-100 dark:bg-amber-500/15">
            <i class="bi bi-file-zip-fill text-amber-600 dark:text-amber-400 text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">บีบอัดไฟล์ต้นฉบับ (เซิร์ฟเวอร์)</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              เมื่อเปิด — ระบบจะ re-encode ต้นฉบับให้มีขนาดไฟล์เล็กลงก่อนเก็บถาวร (ลดค่าพื้นที่และเร่งการส่ง)
            </p>
          </div>
        </div>
        <label class="tw-switch">
          <input type="checkbox" name="photo_compress_enabled" value="1"
                 {{ ($settings['photo_compress_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
          <span class="tw-switch-track"></span>
          <span class="tw-switch-knob"></span>
        </label>
      </div>
      <div class="section-body grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Format</label>
          <select name="photo_compress_format" class="input-xs">
            @foreach(['jpeg' => 'JPEG (เล็ก, แนะนำ)', 'webp' => 'WebP (เล็กกว่า แต่รองรับน้อยกว่า)', 'original' => 'คงรูปแบบเดิม'] as $fval => $flabel)
              <option value="{{ $fval }}" {{ ($settings['photo_compress_format'] ?? 'jpeg') === $fval ? 'selected' : '' }}>{{ $flabel }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
            <span>คุณภาพ</span>
            <span class="text-indigo-600 dark:text-indigo-400 font-bold" id="q_compress_val">{{ $settings['photo_compress_quality'] ?? 85 }}%</span>
          </label>
          <input type="range" name="photo_compress_quality" class="tw-range"
                 min="50" max="100" step="1"
                 value="{{ $settings['photo_compress_quality'] ?? 85 }}"
                 oninput="document.getElementById('q_compress_val').textContent = this.value + '%'">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ความกว้างสูงสุด (px)</label>
          <input type="number" name="photo_compress_max_width" class="input-xs"
                 min="800" max="8000" value="{{ $settings['photo_compress_max_width'] ?? 2560 }}">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ความสูงสูงสุด (px)</label>
          <input type="number" name="photo_compress_max_height" class="input-xs"
                 min="800" max="8000" value="{{ $settings['photo_compress_max_height'] ?? 2560 }}">
        </div>
        <div class="md:col-span-2 flex items-center gap-3 pt-1">
          <label class="tw-switch">
            <input type="checkbox" name="photo_compress_strip_exif" value="1"
                   {{ ($settings['photo_compress_strip_exif'] ?? '1') == '1' ? 'checked' : '' }}>
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
          <div>
            <div class="text-sm font-medium text-slate-800 dark:text-slate-200">ลบ EXIF metadata</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">ป้องกันการเผยแพร่พิกัด GPS และข้อมูลกล้องของช่างภาพ</div>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══ 2. Thumbnail + Preview ═══ --}}
    <div class="section-card">
      <div class="section-header flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-violet-100 dark:bg-violet-500/15">
          <i class="bi bi-aspect-ratio-fill text-violet-600 dark:text-violet-400 text-lg"></i>
        </div>
        <div>
          <h3 class="font-semibold text-slate-900 dark:text-white">Thumbnail &amp; Preview</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            กำหนดขนาดและคุณภาพของรูปย่อ (ใช้ในแกลเลอรี่) และภาพ preview ที่ติดลายน้ำ
          </p>
        </div>
      </div>
      <div class="section-body grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Thumbnail size (px, สี่เหลี่ยมจัตุรัส)</label>
          <input type="number" name="photo_thumbnail_size" class="input-xs"
                 min="100" max="800" value="{{ $settings['photo_thumbnail_size'] ?? 400 }}">
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">แนะนำ 300–400 สำหรับ HD screens</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
            <span>Thumbnail quality</span>
            <span class="text-violet-600 dark:text-violet-400 font-bold" id="q_thumb_val">{{ $settings['photo_thumbnail_quality'] ?? 75 }}%</span>
          </label>
          <input type="range" name="photo_thumbnail_quality" class="tw-range"
                 min="50" max="100" step="1"
                 value="{{ $settings['photo_thumbnail_quality'] ?? 75 }}"
                 oninput="document.getElementById('q_thumb_val').textContent = this.value + '%'">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Preview max side (px)</label>
          <input type="number" name="photo_preview_max" class="input-xs"
                 min="600" max="4000" value="{{ $settings['photo_preview_max'] ?? 1600 }}">
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">ภาพที่ลูกค้าเห็นก่อนซื้อ (มีลายน้ำ)</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
            <span>Preview quality</span>
            <span class="text-violet-600 dark:text-violet-400 font-bold" id="q_preview_val">{{ $settings['photo_preview_quality'] ?? 82 }}%</span>
          </label>
          <input type="range" name="photo_preview_quality" class="tw-range"
                 min="50" max="100" step="1"
                 value="{{ $settings['photo_preview_quality'] ?? 82 }}"
                 oninput="document.getElementById('q_preview_val').textContent = this.value + '%'">
        </div>
      </div>
    </div>

    {{-- ═══ 3. Gallery delivery ═══ --}}
    <div class="section-card">
      <div class="section-header flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-emerald-100 dark:bg-emerald-500/15">
          <i class="bi bi-rocket-takeoff-fill text-emerald-600 dark:text-emerald-400 text-lg"></i>
        </div>
        <div>
          <h3 class="font-semibold text-slate-900 dark:text-white">ความเร็วแกลเลอรี่ (ฝั่งลูกค้า)</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            ควบคุมว่าหน้าแกลเลอรี่โหลดรูปจำนวนเท่าไหร่ทันที และที่เหลือจะค่อย ๆ โหลดเมื่อเลื่อนดู
          </p>
        </div>
      </div>
      <div class="section-body grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Eager-load count</label>
          <input type="number" name="photo_gallery_eager_count" class="input-xs"
                 min="0" max="60" value="{{ $settings['photo_gallery_eager_count'] ?? 12 }}">
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">จำนวนรูปที่โหลดทันทีตอนเปิดหน้า (ที่เหลือ lazy)</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Gallery thumb size (px)</label>
          <input type="number" name="photo_gallery_thumb_size" class="input-xs"
                 min="100" max="600" value="{{ $settings['photo_gallery_thumb_size'] ?? 200 }}">
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">ขนาดรูปย่อที่แสดง (ระบบส่ง 2x อัตโนมัติ)</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Cache-Control (วินาที)</label>
          <input type="number" name="photo_gallery_cache_seconds" class="input-xs"
                 min="0" max="3600" value="{{ $settings['photo_gallery_cache_seconds'] ?? 60 }}">
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">browser cache max-age ของ /api/drive</p>
        </div>
      </div>
    </div>

    {{-- ═══ 4. Client-side pre-upload compression ═══ --}}
    <div class="section-card">
      <div class="section-header flex items-center justify-between gap-3">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-sky-100 dark:bg-sky-500/15">
            <i class="bi bi-laptop-fill text-sky-600 dark:text-sky-400 text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">บีบอัดในเบราว์เซอร์ก่อนอัปโหลด</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              ย่อขนาดรูปในเครื่องช่างภาพก่อนส่งขึ้นเซิร์ฟเวอร์ — ประหยัด bandwidth และอัปโหลดเร็วขึ้นมาก
            </p>
          </div>
        </div>
        <label class="tw-switch">
          <input type="checkbox" name="photo_client_compress_enabled" value="1"
                 {{ ($settings['photo_client_compress_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
          <span class="tw-switch-track"></span>
          <span class="tw-switch-knob"></span>
        </label>
      </div>
      <div class="section-body grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Max dimension (px)</label>
          <input type="number" name="photo_client_max_dimension" class="input-xs"
                 min="800" max="8000" value="{{ $settings['photo_client_max_dimension'] ?? 3840 }}">
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">รูปที่ด้านยาวกว่านี้จะถูกย่อในเบราว์เซอร์</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
            <span>JPEG quality (ฝั่งเบราว์เซอร์)</span>
            <span class="text-sky-600 dark:text-sky-400 font-bold" id="q_client_val">{{ $settings['photo_client_quality'] ?? 85 }}%</span>
          </label>
          <input type="range" name="photo_client_quality" class="tw-range"
                 min="50" max="100" step="1"
                 value="{{ $settings['photo_client_quality'] ?? 85 }}"
                 oninput="document.getElementById('q_client_val').textContent = this.value + '%'">
        </div>
      </div>
    </div>

    {{-- Tip box --}}
    <div class="flex items-start gap-3 p-4 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/30">
      <i class="bi bi-lightbulb-fill text-indigo-600 dark:text-indigo-400 text-lg shrink-0 mt-0.5"></i>
      <div class="text-xs text-indigo-800 dark:text-indigo-200 space-y-1">
        <div class="font-semibold">เคล็ดลับ</div>
        <ul class="list-disc pl-4 space-y-0.5 opacity-90">
          <li>เปิดบีบอัดฝั่งเบราว์เซอร์ — อัปโหลดเร็วขึ้น 3–8 เท่า</li>
          <li>Quality 85 เป็นจุดที่บาลานซ์ระหว่างขนาดและคุณภาพที่ดี</li>
          <li>Eager-load 12 รูป เหมาะกับ viewport ส่วนใหญ่ — มากกว่านี้จะชะลอ first paint</li>
          <li>Gallery cache 60 วินาที ช่วยให้ลูกค้ารี-โหลดหน้าเดิมโดยไม่ต้องยิงซ้ำ</li>
        </ul>
      </div>
    </div>

    {{-- Actions --}}
    <div class="flex flex-wrap justify-end gap-2">
      <button type="button" onclick="resetDefaults()"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium
                     bg-amber-50 dark:bg-amber-500/10
                     text-amber-700 dark:text-amber-300
                     border border-amber-200 dark:border-amber-500/30
                     hover:bg-amber-100 dark:hover:bg-amber-500/20 transition">
        <i class="bi bi-arrow-counterclockwise"></i> คืนค่าเริ่มต้น
      </button>
      <button type="submit"
              class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg text-sm font-semibold text-white
                     bg-gradient-to-r from-amber-500 to-orange-600
                     hover:from-amber-400 hover:to-orange-500
                     shadow-md shadow-amber-500/30 transition">
        <i class="bi bi-save"></i> บันทึกการตั้งค่า
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
function resetDefaults() {
  if (!confirm('คืนค่าเริ่มต้นทั้งหมด?')) return;
  const defaults = {
    photo_compress_enabled:        '1',
    photo_compress_max_width:      2560,
    photo_compress_max_height:     2560,
    photo_compress_quality:        85,
    photo_compress_format:         'jpeg',
    photo_compress_strip_exif:     '1',
    photo_thumbnail_size:          400,
    photo_thumbnail_quality:       75,
    photo_preview_max:             1600,
    photo_preview_quality:         82,
    photo_gallery_eager_count:     12,
    photo_gallery_thumb_size:      200,
    photo_gallery_cache_seconds:   60,
    photo_client_compress_enabled: '1',
    photo_client_max_dimension:    3840,
    photo_client_quality:          85,
  };
  const form = document.getElementById('perfForm');
  for (const [name, val] of Object.entries(defaults)) {
    const el = form.querySelector('[name="' + name + '"]');
    if (!el) continue;
    if (el.type === 'checkbox') {
      el.checked = String(val) === '1';
    } else {
      el.value = val;
      if (el.type === 'range') {
        el.dispatchEvent(new Event('input'));
      }
    }
  }
}
</script>
@endpush
