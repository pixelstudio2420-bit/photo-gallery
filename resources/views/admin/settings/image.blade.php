@extends('layouts.admin')

@section('title', 'Image Processing Settings')

@push('styles')
@include('admin.settings._shared-styles')
<style>
  /* Style the range slider tracks */
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
        <i class="bi bi-image text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Image Processing</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Configure automatic image optimisation per context.
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

  {{-- ═══ GD Library Status ═══ --}}
  <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-5">
    <div class="p-5 flex items-center gap-4">
      @if($gdAvailable)
        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 bg-emerald-100 dark:bg-emerald-500/15">
          <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-2xl"></i>
        </div>
        <div class="grow">
          <div class="font-semibold text-emerald-600 dark:text-emerald-400">GD Library Available</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            @php
              $formats = [];
              if (!empty($gdInfo['JPEG Support'])) $formats[] = 'JPEG';
              if (!empty($gdInfo['PNG Support'])) $formats[] = 'PNG';
              if (!empty($gdInfo['WebP Support'])) $formats[] = 'WebP';
              if (!empty($gdInfo['GIF Read Support'])) $formats[] = 'GIF';
            @endphp
            Supported formats: <strong class="text-slate-700 dark:text-slate-200">{{ implode(', ', $formats) ?: 'Unknown' }}</strong>
            &nbsp;·&nbsp;
            Version: <strong class="text-slate-700 dark:text-slate-200">{{ $gdInfo['GD Version'] ?? 'N/A' }}</strong>
          </div>
        </div>
      @else
        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 bg-rose-100 dark:bg-rose-500/15">
          <i class="bi bi-x-circle-fill text-rose-600 dark:text-rose-400 text-2xl"></i>
        </div>
        <div class="grow">
          <div class="font-semibold text-rose-600 dark:text-rose-400">GD Library Not Available</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Image processing is unavailable. Enable the GD extension in your PHP configuration.
          </div>
        </div>
      @endif
    </div>
  </div>

  <form method="POST" action="{{ route('admin.settings.image.update') }}" id="imageForm">
    @csrf

    {{-- ═══ Master Toggle ═══ --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-5">
      <div class="p-5 flex items-center justify-between gap-4">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-indigo-100 dark:bg-indigo-500/15">
            <i class="bi bi-magic text-indigo-600 dark:text-indigo-400 text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">Enable Image Processing</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Automatically optimise images on upload.
            </p>
          </div>
        </div>
        <label class="tw-switch">
          <input type="checkbox" id="img_processing_enabled" name="img_processing_enabled" value="1"
                 {{ ($settings['img_processing_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
          <span class="tw-switch-track"></span>
          <span class="tw-switch-knob"></span>
        </label>
      </div>
    </div>

    {{-- ═══ Per-context settings ═══ --}}
    @php
      $contexts = [
        'cover'  => ['label' => 'Cover Images',       'icon' => 'bi-card-image',     'showFormat' => true,  'defaultW' => 1920, 'defaultH' => 1080, 'defaultQ' => 85, 'defaultF' => 'webp'],
        'avatar' => ['label' => 'Avatar / Profile',   'icon' => 'bi-person-circle',  'showFormat' => true,  'defaultW' => 400,  'defaultH' => 400,  'defaultQ' => 80, 'defaultF' => 'webp'],
        'slip'   => ['label' => 'Payment Slips',      'icon' => 'bi-receipt',        'showFormat' => false, 'defaultW' => 1200, 'defaultH' => 1600, 'defaultQ' => 90, 'defaultF' => 'jpeg'],
        'seo'    => ['label' => 'SEO / OG Images',    'icon' => 'bi-share',          'showFormat' => true,  'defaultW' => 1200, 'defaultH' => 630,  'defaultQ' => 85, 'defaultF' => 'jpeg'],
      ];
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      @foreach($contexts as $ctx => $cfg)
      <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between gap-3">
          <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
            <i class="bi {{ $cfg['icon'] }} text-indigo-600 dark:text-indigo-400"></i>
            {{ $cfg['label'] }}
          </h3>
          <label class="tw-switch">
            <input type="checkbox" name="img_{{ $ctx }}_enabled" value="1"
                   {{ ($settings["img_{$ctx}_enabled"] ?? '0') == '1' ? 'checked' : '' }}>
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
        </div>
        <div class="p-5 space-y-4">
          {{-- Format --}}
          @if($cfg['showFormat'])
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Output Format</label>
            <select name="img_{{ $ctx }}_format"
                    class="w-full px-3 py-2 rounded-lg text-sm
                           bg-white dark:bg-slate-800
                           border border-slate-300 dark:border-white/10
                           text-slate-900 dark:text-slate-100
                           focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
              @foreach(['webp' => 'WebP (recommended)', 'jpeg' => 'JPEG', 'png' => 'PNG', 'original' => 'Keep Original'] as $fval => $flabel)
                <option value="{{ $fval }}" {{ ($settings["img_{$ctx}_format"] ?? $cfg['defaultF']) === $fval ? 'selected' : '' }}>{{ $flabel }}</option>
              @endforeach
            </select>
          </div>
          @endif

          {{-- Quality --}}
          <div>
            <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
              <span>Quality</span>
              <span class="text-indigo-600 dark:text-indigo-400 font-bold" id="q_{{ $ctx }}_val">{{ $settings["img_{$ctx}_quality"] ?: $cfg['defaultQ'] }}%</span>
            </label>
            <input type="range" name="img_{{ $ctx }}_quality" class="tw-range"
                   min="30" max="100" step="5"
                   value="{{ $settings["img_{$ctx}_quality"] ?: $cfg['defaultQ'] }}"
                   oninput="document.getElementById('q_{{ $ctx }}_val').textContent = this.value + '%'">
            <div class="flex justify-between text-[10px] text-slate-400 dark:text-slate-500 mt-1">
              <span>30%</span><span>100%</span>
            </div>
          </div>

          {{-- Dimensions --}}
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Max Width (px)</label>
              <input type="number" name="img_{{ $ctx }}_max_width"
                     min="100" max="8000"
                     placeholder="{{ $cfg['defaultW'] }}"
                     value="{{ $settings["img_{$ctx}_max_width"] ?: $cfg['defaultW'] }}"
                     class="w-full px-3 py-2 rounded-lg text-sm
                            bg-white dark:bg-slate-800
                            border border-slate-300 dark:border-white/10
                            text-slate-900 dark:text-slate-100
                            focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Max Height (px)</label>
              <input type="number" name="img_{{ $ctx }}_max_height"
                     min="100" max="8000"
                     placeholder="{{ $cfg['defaultH'] }}"
                     value="{{ $settings["img_{$ctx}_max_height"] ?: $cfg['defaultH'] }}"
                     class="w-full px-3 py-2 rounded-lg text-sm
                            bg-white dark:bg-slate-800
                            border border-slate-300 dark:border-white/10
                            text-slate-900 dark:text-slate-100
                            focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>
          </div>
        </div>
      </div>
      @endforeach
    </div>

    {{-- ═══ Action Buttons ═══ --}}
    <div class="flex flex-wrap justify-end gap-2 mt-5">
      <a href="{{ route('admin.settings.index') }}"
         class="px-4 py-2 rounded-lg text-sm font-medium
                bg-white dark:bg-slate-800
                border border-slate-200 dark:border-white/10
                text-slate-700 dark:text-slate-200
                hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</a>
      <button type="button" onclick="resetDefaults()"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium
                     bg-amber-50 dark:bg-amber-500/10
                     text-amber-700 dark:text-amber-300
                     border border-amber-200 dark:border-amber-500/30
                     hover:bg-amber-100 dark:hover:bg-amber-500/20 transition">
        <i class="bi bi-arrow-counterclockwise"></i> Reset Defaults
      </button>
      <button type="submit"
              class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg text-sm font-semibold text-white
                     bg-gradient-to-r from-indigo-600 to-violet-600
                     hover:from-indigo-500 hover:to-violet-500
                     shadow-md shadow-indigo-500/30 transition">
        <i class="bi bi-save"></i> Save Image Settings
      </button>
    </div>
  </form>

</div>
@endsection

@push('scripts')
<script>
function resetDefaults() {
  if (!confirm('Reset all image processing settings to their defaults? This cannot be undone.')) return;

  const defaults = {
    img_cover_format:  'webp', img_cover_quality: 85, img_cover_max_width: 1920, img_cover_max_height: 1080,
    img_avatar_format: 'webp', img_avatar_quality: 80, img_avatar_max_width: 400,  img_avatar_max_height: 400,
                                img_slip_quality:   90, img_slip_max_width:   1200, img_slip_max_height:  1600,
    img_seo_format:    'jpeg', img_seo_quality:    85, img_seo_max_width:    1200, img_seo_max_height:   630,
  };

  const form = document.getElementById('imageForm');
  for (const [name, value] of Object.entries(defaults)) {
    const el = form.querySelector('[name="' + name + '"]');
    if (!el) continue;
    if (el.tagName === 'SELECT') {
      el.value = value;
    } else if (el.type === 'range' || el.type === 'number') {
      el.value = value;
      const ctx = name.match(/img_(\w+)_quality/);
      if (ctx) {
        const label = document.getElementById('q_' + ctx[1] + '_val');
        if (label) label.textContent = value + '%';
      }
    }
  }
}
</script>
@endpush
