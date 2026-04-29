@extends('layouts.admin')

@section('title', 'Watermark Settings')

@push('styles')
@include('admin.settings._shared-styles')
<style>
  /* Type radio card */
  .wm-type-card {
    display: block;
    padding: 1rem;
    border-radius: 0.75rem;
    cursor: pointer;
    background: rgb(248 250 252);
    border: 2px solid rgb(226 232 240);
    transition: all .2s ease;
  }
  .dark .wm-type-card { background: rgba(30,41,59,.5); border-color: rgba(255,255,255,.08); }
  .wm-type-card:hover { border-color: rgb(99 102 241); }
  .wm-type-radio:checked ~ .wm-type-card {
    border-color: rgb(99 102 241);
    background: rgba(99,102,241,.08);
    box-shadow: 0 0 0 3px rgba(99,102,241,.15);
  }

  /* Range slider */
  .tw-range {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 6px;
    background: rgb(226 232 240);
    border-radius: 9999px;
    outline: none;
  }
  .dark .tw-range { background: rgb(51 65 85); }
  .tw-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
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
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ═══ Page Header ═══ --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center shadow-lg shadow-indigo-500/30">
        <i class="bi bi-badge-wc text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Watermark Settings</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Configure watermark applied to photos for protection.
        </p>
      </div>
    </div>
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg
              bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10
              text-slate-700 dark:text-slate-200
              hover:bg-slate-50 dark:hover:bg-slate-700 transition">
      <i class="bi bi-arrow-left"></i> Back to Settings
    </a>
  </div>

  {{-- ═══ Flash Messages ═══ --}}
  @if(session('success'))
  <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl
              bg-emerald-50 dark:bg-emerald-500/10
              text-emerald-700 dark:text-emerald-300
              border border-emerald-200 dark:border-emerald-500/30 text-sm">
    <i class="bi bi-check-circle-fill"></i>
    <span>{{ session('success') }}</span>
  </div>
  @endif
  @if(session('error'))
  <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl
              bg-rose-50 dark:bg-rose-500/10
              text-rose-700 dark:text-rose-300
              border border-rose-200 dark:border-rose-500/30 text-sm">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span>{{ session('error') }}</span>
  </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.watermark.update') }}" enctype="multipart/form-data">
    @csrf

    {{-- ═══ Enable / Disable ═══ --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-5">
      <div class="p-5 flex items-center justify-between gap-4">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-indigo-100 dark:bg-indigo-500/15">
            <i class="bi bi-power text-indigo-600 dark:text-indigo-400 text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">Enable Watermark</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Apply watermark to all photos delivered to customers.
            </p>
          </div>
        </div>
        <label class="tw-switch">
          <input type="checkbox" id="watermark_enabled" name="watermark_enabled" value="1"
                 {{ ($settings['watermark_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
          <span class="tw-switch-track"></span>
          <span class="tw-switch-knob"></span>
        </label>
      </div>
    </div>

    {{-- ═══ Watermark Type ═══ --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-5">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
        <h3 class="font-semibold text-slate-900 dark:text-white">
          <i class="bi bi-type mr-2 text-indigo-600 dark:text-indigo-400"></i>Watermark Type
        </h3>
      </div>
      <div class="p-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
          <label class="block relative">
            <input type="radio" name="watermark_type" id="type_text" value="text" class="wm-type-radio sr-only"
                   {{ ($settings['watermark_type'] ?? 'text') === 'text' ? 'checked' : '' }}
                   onchange="toggleWatermarkType()">
            <div class="wm-type-card flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center
                          bg-indigo-100 dark:bg-indigo-500/15
                          text-indigo-600 dark:text-indigo-400">
                <i class="bi bi-fonts text-lg"></i>
              </div>
              <div>
                <div class="font-semibold text-slate-900 dark:text-white">Text Watermark</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Overlay custom text on photos</div>
              </div>
            </div>
          </label>
          <label class="block relative">
            <input type="radio" name="watermark_type" id="type_image" value="image" class="wm-type-radio sr-only"
                   {{ ($settings['watermark_type'] ?? '') === 'image' ? 'checked' : '' }}
                   onchange="toggleWatermarkType()">
            <div class="wm-type-card flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center
                          bg-indigo-100 dark:bg-indigo-500/15
                          text-indigo-600 dark:text-indigo-400">
                <i class="bi bi-image text-lg"></i>
              </div>
              <div>
                <div class="font-semibold text-slate-900 dark:text-white">Image Watermark</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Overlay a logo/image on photos</div>
              </div>
            </div>
          </label>
        </div>

        {{-- Text Options --}}
        <div id="section-text" class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-slate-200 dark:border-white/10">
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Watermark Text</label>
            <input type="text" name="watermark_text"
                   placeholder="© Your Studio Name"
                   value="{{ $settings['watermark_text'] ?? '' }}"
                   class="w-full px-3 py-2 rounded-lg text-sm
                          bg-white dark:bg-slate-800
                          border border-slate-300 dark:border-white/10
                          text-slate-900 dark:text-slate-100
                          focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Text Color</label>
            <div class="flex items-center gap-2">
              <input type="color" name="watermark_color"
                     value="{{ $settings['watermark_color'] ?: '#ffffff' }}"
                     class="h-10 w-14 rounded-lg border border-slate-300 dark:border-white/10 cursor-pointer bg-white dark:bg-slate-800">
              <input type="text" id="colorHex"
                     placeholder="#ffffff"
                     value="{{ $settings['watermark_color'] ?: '#ffffff' }}"
                     readonly
                     class="flex-1 px-3 py-2 rounded-lg text-sm font-mono
                            bg-slate-50 dark:bg-slate-800
                            border border-slate-300 dark:border-white/10
                            text-slate-700 dark:text-slate-200">
            </div>
          </div>
        </div>

        {{-- Image Upload Options --}}
        <div id="section-image" class="pt-4 border-t border-slate-200 dark:border-white/10" style="display:none;">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Watermark Image</label>
          @if(!empty($settings['watermark_image_path']))
            <div class="mb-3">
              <img src="{{ Storage::url($settings['watermark_image_path']) }}"
                   alt="Current watermark" id="watermarkPreview"
                   class="max-h-28 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 p-1">
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Current watermark image</p>
            </div>
          @else
            <div class="mb-3">
              <img src="" alt="" id="watermarkPreview" style="display:none;"
                   class="max-h-28 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 p-1">
            </div>
          @endif
          <input type="file" name="watermark_image_path"
                 accept="image/png,image/svg+xml,image/webp"
                 onchange="previewWatermarkImage(this)"
                 class="w-full text-sm text-slate-700 dark:text-slate-300
                        file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0
                        file:text-sm file:font-medium
                        file:bg-indigo-50 file:text-indigo-700
                        dark:file:bg-indigo-500/20 dark:file:text-indigo-300
                        hover:file:bg-indigo-100 dark:hover:file:bg-indigo-500/30">
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Recommended: PNG with transparency. Max 2MB.</div>
        </div>
      </div>
    </div>

    {{-- ═══ Appearance & Position ═══ --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-5">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
        <h3 class="font-semibold text-slate-900 dark:text-white">
          <i class="bi bi-layout-wtf mr-2 text-indigo-600 dark:text-indigo-400"></i>Appearance &amp; Position
        </h3>
      </div>
      <div class="p-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Position</label>
            <select name="watermark_position"
                    class="w-full px-3 py-2 rounded-lg text-sm
                           bg-white dark:bg-slate-800
                           border border-slate-300 dark:border-white/10
                           text-slate-900 dark:text-slate-100
                           focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
              @foreach(['diagonal' => 'Diagonal (Across image)', 'tiled' => 'Tiled (Repeat)', 'center' => 'Center', 'bottom-right' => 'Bottom Right', 'bottom-left' => 'Bottom Left', 'top-right' => 'Top Right'] as $val => $label)
                <option value="{{ $val }}" {{ ($settings['watermark_position'] ?? 'diagonal') === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
              <span>Opacity</span>
              <span class="text-indigo-600 dark:text-indigo-400 font-bold" id="opacityVal">{{ $settings['watermark_opacity'] ?: 50 }}%</span>
            </label>
            <input type="range" name="watermark_opacity" class="tw-range" min="5" max="100" step="5"
                   value="{{ $settings['watermark_opacity'] ?: 50 }}"
                   oninput="document.getElementById('opacityVal').textContent = this.value + '%'">
            <div class="flex justify-between text-[10px] text-slate-400 dark:text-slate-500 mt-1">
              <span>5%</span><span>100%</span>
            </div>
          </div>

          <div>
            <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
              <span>Size</span>
              <span class="text-indigo-600 dark:text-indigo-400 font-bold" id="sizeVal">{{ $settings['watermark_size_percent'] ?: 30 }}%</span>
            </label>
            <input type="range" name="watermark_size_percent" class="tw-range" min="10" max="80" step="5"
                   value="{{ $settings['watermark_size_percent'] ?: 30 }}"
                   oninput="document.getElementById('sizeVal').textContent = this.value + '%'">
            <div class="flex justify-between text-[10px] text-slate-400 dark:text-slate-500 mt-1">
              <span>10%</span><span>80%</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══ Actions ═══ --}}
    <div class="flex justify-end gap-2">
      <a href="{{ route('admin.settings.index') }}"
         class="px-4 py-2 rounded-lg text-sm font-medium
                bg-white dark:bg-slate-800
                border border-slate-200 dark:border-white/10
                text-slate-700 dark:text-slate-200
                hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</a>
      <button type="submit"
              class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg text-sm font-semibold text-white
                     bg-gradient-to-r from-indigo-600 to-violet-600
                     hover:from-indigo-500 hover:to-violet-500
                     shadow-md shadow-indigo-500/30 transition">
        <i class="bi bi-save"></i> Save Watermark Settings
      </button>
    </div>
  </form>

</div>
@endsection

@push('scripts')
<script>
function toggleWatermarkType() {
  const isText = document.getElementById('type_text').checked;
  document.getElementById('section-text').style.display  = isText ? '' : 'none';
  document.getElementById('section-image').style.display = isText ? 'none' : '';
}

function previewWatermarkImage(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.getElementById('watermarkPreview');
      img.src = e.target.result;
      img.style.display = '';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// Color picker sync
const colorPicker = document.querySelector('input[type="color"][name="watermark_color"]');
if (colorPicker) {
  colorPicker.addEventListener('input', function() {
    document.getElementById('colorHex').value = this.value;
  });
}

// Init on load
document.addEventListener('DOMContentLoaded', function() {
  toggleWatermarkType();
});
</script>
@endpush
