@extends('layouts.photographer')

@section('title', $mode === 'create' ? 'สร้าง Preset' : 'แก้ไข: '.$preset->name)

@php
  $merged = $preset->merged_settings ?? \App\Models\PhotographerPreset::DEFAULTS;

  $tabs = [
    'light' => [
      'label' => 'Light',
      'icon'  => 'bi-sun',
      'sliders' => [
        ['exposure',   'Exposure',   'bi-brightness-high',     -2,    2,    0.05, 0],
        ['contrast',   'Contrast',   'bi-circle-half',         -100,  100,  1,    0],
        ['highlights', 'Highlights', 'bi-arrow-up-square',     -100,  100,  1,    0],
        ['shadows',    'Shadows',    'bi-arrow-down-square',   -100,  100,  1,    0],
        ['whites',     'Whites',     'bi-square',              -100,  100,  1,    0],
        ['blacks',     'Blacks',     'bi-square-fill',         -100,  100,  1,    0],
      ],
    ],
    'color' => [
      'label' => 'Color',
      'icon'  => 'bi-droplet-half',
      'sliders' => [
        ['temperature', 'Temperature', 'bi-thermometer-sun',  -100, 100, 1, 0],
        ['tint',        'Tint',        'bi-eyedropper',       -100, 100, 1, 0],
        ['vibrance',    'Vibrance',    'bi-stars',            -100, 100, 1, 0],
        ['saturation',  'Saturation',  'bi-palette',          -100, 100, 1, 0],
      ],
    ],
    'effects' => [
      'label' => 'Effects',
      'icon'  => 'bi-magic',
      'sliders' => [
        ['clarity',   'Clarity',   'bi-aperture',         -100, 100, 1, 0],
        ['sharpness', 'Sharpness', 'bi-pin-angle',         0,   100, 1, 0],
        ['vignette',  'Vignette',  'bi-circle',           -100, 100, 1, 0],
      ],
    ],
  ];

  $defaultSource = (count($recentPhotos ?? []) > 0)
    ? 'photo:'.$recentPhotos[0]['id']
    : 'synthetic';
@endphp

@push('styles')
<style>
  body {
    background: radial-gradient(ellipse at top, #1a1625 0%, #0a0a0f 50%, #050507 100%);
    background-attachment: fixed;
    min-height: 100vh;
  }

  /* Glass card effect */
  .glass {
    background: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.02) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.06);
    box-shadow: 0 8px 32px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.04);
  }

  .glass-elevated {
    background: linear-gradient(180deg, rgba(30,30,45,0.7) 0%, rgba(15,15,25,0.7) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.08);
    box-shadow: 0 12px 48px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.06);
  }

  /* Premium slider */
  .slider {
    -webkit-appearance: none; appearance: none;
    width: 100%; height: 6px; outline: none;
    background: linear-gradient(to right, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.04) 100%);
    border-radius: 999px;
    cursor: pointer;
  }
  .slider.bipolar {
    background:
      radial-gradient(circle at 50% 50%, rgba(255,255,255,0.6) 0px, rgba(255,255,255,0.6) 1px, transparent 1px),
      linear-gradient(to right, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.04) 100%);
    background-size: 100% 100%, 100% 100%;
  }
  .slider::-webkit-slider-thumb {
    -webkit-appearance: none; appearance: none;
    width: 18px; height: 18px;
    background: linear-gradient(135deg, #fff 0%, #f1f5f9 100%);
    border-radius: 999px;
    border: 0;
    box-shadow:
      0 0 0 1.5px rgba(244, 63, 94, 0.9),
      0 4px 12px rgba(0,0,0,0.5),
      0 0 0 0 rgba(244, 63, 94, 0);
    cursor: grab;
    transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.18s;
  }
  .slider::-webkit-slider-thumb:hover {
    transform: scale(1.18);
    box-shadow:
      0 0 0 1.5px rgba(244, 63, 94, 1),
      0 4px 16px rgba(244, 63, 94, 0.4),
      0 0 0 6px rgba(244, 63, 94, 0.15);
  }
  .slider::-webkit-slider-thumb:active {
    cursor: grabbing;
    transform: scale(1.28);
  }
  .slider::-moz-range-thumb {
    width: 18px; height: 18px;
    background: #fff;
    border: 0;
    border-radius: 999px;
    box-shadow: 0 0 0 1.5px #f43f5e, 0 4px 12px rgba(0,0,0,0.5);
    cursor: grab;
  }

  /* Tab pills */
  .tab-pill {
    position: relative;
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(161, 161, 170);
    letter-spacing: 0.025em;
    transition: color 0.15s;
    cursor: pointer;
    background: transparent;
    border: 0;
    z-index: 1;
  }
  .tab-pill:hover { color: rgb(244, 244, 245); }
  .tab-pill.active { color: white; }
  .tab-indicator {
    position: absolute;
    background: linear-gradient(135deg, #f43f5e, #ec4899);
    border-radius: 999px;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    z-index: 0;
    box-shadow: 0 4px 14px rgba(244, 63, 94, 0.4);
  }

  /* Sample thumb */
  .sample-thumb {
    position: relative;
    width: 64px; height: 64px;
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.02);
    transition: all 0.2s;
    flex-shrink: 0;
    cursor: pointer;
  }
  .sample-thumb:hover {
    border-color: rgba(244, 63, 94, 0.6);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(244, 63, 94, 0.2);
  }
  .sample-thumb.active {
    border-color: #f43f5e;
    box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.25), 0 8px 20px rgba(244, 63, 94, 0.3);
  }
  .sample-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .sample-thumb.upload {
    border-style: dashed;
    color: rgb(113, 113, 122);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
  }
  .sample-thumb.upload:hover { color: rgb(244, 244, 245); border-color: rgba(244, 63, 94, 0.6); border-style: dashed; }

  /* Custom scrollbar */
  .custom-scroll::-webkit-scrollbar { height: 6px; }
  .custom-scroll::-webkit-scrollbar-track { background: transparent; }
  .custom-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
  .custom-scroll::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

  /* Render bar */
  .render-bar {
    transform: scaleX(0); transform-origin: left;
    transition: transform 0.3s ease-out;
  }
  .render-bar.active {
    transform: scaleX(1);
    transition: transform 1.5s ease-in-out;
  }

  /* Subtle row hover */
  .slider-row { transition: background 0.15s; }
  .slider-row:hover { background: rgba(255,255,255,0.025); }

  /* Numeric input */
  .num-input {
    background: rgba(0,0,0,0.4);
    border: 1px solid rgba(255,255,255,0.08);
    color: rgb(244, 244, 245);
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 11px;
    font-family: 'SF Mono', Consolas, monospace;
    text-align: right;
    transition: all 0.15s;
    width: 56px;
    font-weight: 500;
  }
  .num-input:focus {
    outline: none;
    border-color: #f43f5e;
    background: rgba(244, 63, 94, 0.05);
    box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.1);
  }
  .num-input.dirty {
    color: #fbbf24;
    border-color: rgba(251, 191, 36, 0.3);
    background: rgba(251, 191, 36, 0.04);
  }

  /* Action buttons */
  .btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.875rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(212, 212, 216);
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 0.625rem;
    transition: all 0.15s;
    cursor: pointer;
  }
  .btn-ghost:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.12);
    color: white;
  }

  .btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: white;
    background: linear-gradient(135deg, #f43f5e 0%, #ec4899 100%);
    border: 0;
    border-radius: 0.625rem;
    transition: all 0.15s;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(244, 63, 94, 0.35), inset 0 1px 0 rgba(255,255,255,0.2);
  }
  .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(244, 63, 94, 0.5), inset 0 1px 0 rgba(255,255,255,0.2);
  }

  /* Title — solid white for max contrast (gradient text was unreliable) */
  .title-gradient {
    color: #fafafa;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
  }

  /* B&W toggle */
  .bw-toggle {
    position: relative;
    overflow: hidden;
    transition: all 0.2s;
  }
  .bw-toggle.on {
    background: linear-gradient(135deg, #fff 0%, #e2e8f0 100%);
    color: #18181b;
    border-color: white;
    box-shadow: 0 4px 14px rgba(255,255,255,0.15);
  }
</style>
@endpush

@section('content')
<div class="text-zinc-200 -mx-3 sm:-mx-4 px-3 sm:px-4 lg:-mx-6 lg:px-6">

  {{-- ─── Premium Header ──────────────────────────────────────── --}}
  <div class="flex items-start justify-between gap-4 mb-6 pt-1">
    <div class="flex items-center gap-3 min-w-0 flex-1">
      <a href="{{ route('photographer.presets.index') }}"
         class="shrink-0 w-10 h-10 inline-flex items-center justify-center rounded-xl bg-white/5 hover:bg-white/10 border border-white/[0.06] text-zinc-400 hover:text-white transition group">
        <i class="bi bi-arrow-left text-lg group-hover:-translate-x-0.5 transition-transform"></i>
      </a>
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 mb-0.5">
          <span class="text-[10px] font-bold uppercase tracking-[0.15em] text-rose-400/80">
            {{ $mode === 'create' ? '+ NEW PRESET' : 'EDITING' }}
          </span>
          @if($mode === 'edit')
            <span class="text-zinc-700">·</span>
            <span class="text-[10px] font-mono text-zinc-500">#{{ $preset->id }}</span>
          @endif
        </div>
        <h1 class="title-gradient font-bold text-xl lg:text-2xl tracking-tight m-0 truncate">
          {{ $mode === 'create' ? 'สร้าง Preset ใหม่' : $preset->name }}
        </h1>
      </div>
    </div>

    <div class="flex items-center gap-2 shrink-0">
      <button type="button" id="resetAllBtn" class="btn-ghost">
        <i class="bi bi-arrow-counterclockwise"></i>
        <span class="hidden sm:inline">รีเซ็ต</span>
      </button>
      <button type="submit" form="presetForm" class="btn-primary">
        <i class="bi bi-cloud-check"></i>
        บันทึก Preset
      </button>
    </div>
  </div>

  @if($errors->any())
    <div class="mb-4 rounded-xl border border-rose-500/20 bg-rose-500/5 backdrop-blur text-rose-200 text-sm px-4 py-3">
      @foreach($errors->all() as $e)
        <p class="m-0 flex items-center gap-2"><i class="bi bi-exclamation-circle"></i>{{ $e }}</p>
      @endforeach
    </div>
  @endif

  {{-- ─── Main grid ─────────────────────────────────────────────── --}}
  <form method="POST" id="presetForm"
        action="{{ $mode === 'create' ? route('photographer.presets.store') : route('photographer.presets.update', $preset->id) }}"
        class="grid grid-cols-1 xl:grid-cols-12 gap-4 lg:gap-5 pb-8">
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    {{-- ═══ LEFT: PREVIEW + SAMPLES + META ═══════════════════════ --}}
    <div class="xl:col-span-8 space-y-4 min-w-0">

      {{-- HERO PREVIEW --}}
      <div class="glass-elevated rounded-2xl overflow-hidden">
        <div id="previewPane" class="relative aspect-[16/10] bg-black overflow-hidden">

          {{-- Render progress bar --}}
          <div id="renderBar" class="render-bar absolute top-0 inset-x-0 h-[3px] bg-gradient-to-r from-rose-500 via-pink-500 to-rose-500 z-30 shadow-[0_0_20px_rgba(244,63,94,0.6)]"></div>

          {{-- Drop overlay --}}
          <div id="dropOverlay" class="hidden absolute inset-3 z-40 rounded-xl border-2 border-dashed border-rose-400 bg-rose-500/[0.08] backdrop-blur-md flex flex-col items-center justify-center gap-3 pointer-events-none">
            <div class="w-16 h-16 rounded-full bg-rose-500/20 flex items-center justify-center">
              <i class="bi bi-cloud-upload text-rose-300 text-3xl"></i>
            </div>
            <div class="text-center">
              <p class="text-white font-bold text-base m-0">วางไฟล์รูปที่นี่</p>
              <p class="text-zinc-400 text-xs m-0">JPG / PNG / WebP — สูงสุด 10 MB</p>
            </div>
          </div>

          {{-- Single mode --}}
          <img id="livePreview" alt="Preview"
               class="w-full h-full object-contain cursor-zoom-in select-none"
               draggable="false">

          {{-- Compare mode --}}
          <div id="compareMode" class="hidden absolute inset-0 grid grid-cols-2 bg-black">
            <div class="relative overflow-hidden border-r border-white/[0.04]">
              <div class="absolute top-3 left-3 z-10 px-2.5 py-1 text-[9px] font-black tracking-[0.2em] rounded-md bg-black/60 backdrop-blur text-white border border-white/10">BEFORE</div>
              <img id="beforeImg" alt="Before" class="w-full h-full object-contain">
            </div>
            <div class="relative overflow-hidden">
              <div class="absolute top-3 right-3 z-10 px-2.5 py-1 text-[9px] font-black tracking-[0.2em] rounded-md bg-gradient-to-br from-rose-500 to-pink-500 text-white shadow-lg shadow-rose-500/30">AFTER</div>
              <img id="afterImg" alt="After" class="w-full h-full object-contain">
            </div>
          </div>
        </div>

        {{-- Toolbar --}}
        <div class="px-4 py-3 flex items-center justify-between border-t border-white/[0.04] bg-black/30">
          <div class="text-[11px] text-zinc-500 font-mono truncate flex items-center gap-2" id="previewMeta">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
            กำลังโหลด preview…
          </div>
          <div class="flex items-center gap-1.5 shrink-0">
            <button type="button" id="compareToggle"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-semibold text-zinc-300 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg transition border border-white/[0.06]"
                    title="เปรียบเทียบ Before/After">
              <i class="bi bi-layout-split"></i>
              <span class="hidden sm:inline">Compare</span>
            </button>
            <button type="button" id="zoomBtn"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-semibold text-zinc-300 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg transition border border-white/[0.06]"
                    title="ดูเต็มจอ">
              <i class="bi bi-arrows-fullscreen"></i>
            </button>
          </div>
        </div>
      </div>

      {{-- SAMPLE PICKER --}}
      <div class="glass rounded-2xl p-5">
        <div class="flex items-center justify-between mb-3">
          <div>
            <p class="text-[10px] font-black uppercase tracking-[0.15em] text-zinc-500 m-0">
              <i class="bi bi-images mr-1"></i> ทดสอบกับรูป
            </p>
            <p class="text-xs text-zinc-400 mt-0.5 m-0">เลือกรูป, อัปโหลด หรือลากวางบน preview</p>
          </div>
        </div>
        <div class="flex gap-3 overflow-x-auto pb-2 custom-scroll" id="sampleStrip">
          {{-- Synthetic --}}
          <button type="button" data-source="synthetic" class="sample-thumb active" title="Synthetic gradient">
            <div class="w-full h-full bg-gradient-to-br from-indigo-500 via-fuchsia-500 to-pink-500 flex items-center justify-center text-white text-[10px] font-black tracking-wider">
              SYNTH
            </div>
          </button>
          @foreach($recentPhotos ?? [] as $rp)
            <button type="button" data-source="photo:{{ $rp['id'] }}" class="sample-thumb" title="{{ $rp['label'] }}">
              @if($rp['thumb'])
                <img src="{{ $rp['thumb'] }}" loading="lazy" alt="{{ $rp['label'] }}">
              @else
                <div class="w-full h-full bg-zinc-800 flex items-center justify-center text-[9px] text-zinc-500">{{ $rp['label'] }}</div>
              @endif
            </button>
          @endforeach
          {{-- Upload --}}
          <label class="sample-thumb upload" title="อัปโหลดรูปของคุณ">
            <input type="file" id="customUpload" accept="image/jpeg,image/png,image/webp" class="hidden">
            <i class="bi bi-plus-lg text-xl"></i>
            <span class="text-[8px] font-black tracking-widest">UPLOAD</span>
          </label>
        </div>
      </div>

      {{-- META --}}
      <div class="glass rounded-2xl p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-1">
          <label class="text-[10px] font-black uppercase tracking-[0.15em] text-zinc-500 block mb-2">ชื่อ Preset</label>
          <input type="text" name="name" value="{{ old('name', $preset->name) }}" required maxlength="100"
                 class="w-full bg-black/30 border border-white/10 rounded-lg text-sm text-white px-3 py-2 placeholder-zinc-600 focus:border-rose-500 focus:bg-rose-500/[0.04] focus:ring-2 focus:ring-rose-500/20 outline-none transition"
                 placeholder="Golden Hour Wedding">
        </div>
        <div class="md:col-span-2">
          <label class="text-[10px] font-black uppercase tracking-[0.15em] text-zinc-500 block mb-2">คำอธิบาย</label>
          <input type="text" name="description" value="{{ old('description', $preset->description) }}" maxlength="250"
                 class="w-full bg-black/30 border border-white/10 rounded-lg text-sm text-white px-3 py-2 placeholder-zinc-600 focus:border-rose-500 focus:bg-rose-500/[0.04] focus:ring-2 focus:ring-rose-500/20 outline-none transition"
                 placeholder="โทนอุ่น สดใส เหมาะกับงานแต่งงาน...">
        </div>
      </div>
    </div>

    {{-- ═══ RIGHT: TABBED SLIDERS ═══════════════════════════════════ --}}
    <div class="xl:col-span-4">
      <div class="glass-elevated rounded-2xl xl:sticky xl:top-4 overflow-hidden">

        {{-- Premium pill tabs --}}
        <div class="relative p-2 border-b border-white/[0.06]">
          <div class="relative flex items-center bg-black/40 rounded-xl p-1">
            <div id="tabIndicator" class="tab-indicator absolute h-[calc(100%-8px)] top-1"></div>
            @foreach($tabs as $key => $tab)
              <button type="button" data-tab="{{ $key }}"
                      class="tab-pill flex-1 rounded-lg flex items-center justify-center gap-1.5 {{ $loop->first ? 'active' : '' }}">
                <i class="bi {{ $tab['icon'] }}"></i>
                <span>{{ $tab['label'] }}</span>
              </button>
            @endforeach
          </div>
        </div>

        {{-- Tab content --}}
        <div class="p-5">
          @foreach($tabs as $key => $tab)
            <div class="tab-panel space-y-1.5 {{ $loop->first ? '' : 'hidden' }}" data-panel="{{ $key }}">
              @foreach($tab['sliders'] as [$name, $label, $icon, $min, $max, $step, $default])
                @php $val = (float) old($name, $merged[$name] ?? $default); @endphp
                <div class="slider-row grid grid-cols-[100px_1fr_56px] gap-3 items-center py-2.5 px-2 -mx-2 rounded-lg" data-slider="{{ $name }}">
                  <label class="flex items-center gap-2 text-xs font-medium text-zinc-300 cursor-default select-none">
                    <i class="bi {{ $icon }} text-zinc-500 text-[13px]"></i>
                    <span>{{ $label }}</span>
                  </label>
                  <input type="range" name="{{ $name }}"
                         class="slider preset-slider {{ $min < 0 ? 'bipolar' : '' }}"
                         min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
                         value="{{ $val }}"
                         data-default="{{ $default }}"
                         title="Double-click to reset">
                  <input type="number"
                         class="num-input preset-numeric"
                         data-for="{{ $name }}"
                         min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
                         value="{{ $val }}">
                </div>
              @endforeach

              @if($key === 'color')
                <div class="pt-4 mt-3 border-t border-white/[0.06]">
                  <p class="text-[10px] font-black uppercase tracking-[0.15em] text-zinc-500 mb-3">Treatment</p>
                  <button type="button" id="bwToggle"
                          class="bw-toggle w-full flex items-center justify-between px-4 py-2.5 text-sm font-semibold rounded-xl border border-white/10 bg-white/[0.04] text-zinc-200 hover:bg-white/[0.08] {{ ($merged['grayscale'] ?? false) ? 'on' : '' }}">
                    <span class="flex items-center gap-2">
                      <i class="bi bi-circle-half text-base"></i>
                      Black &amp; White
                    </span>
                    <span class="text-[10px] font-mono opacity-60 px-2 py-0.5 rounded bg-black/20">{{ ($merged['grayscale'] ?? false) ? 'ON' : 'OFF' }}</span>
                  </button>
                  <input type="hidden" name="grayscale" id="grayscaleInput" value="{{ ($merged['grayscale'] ?? false) ? '1' : '0' }}">
                </div>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </form>
</div>

{{-- Zoom overlay --}}
<div id="zoomOverlay" class="hidden fixed inset-0 z-[9999] bg-black/95 backdrop-blur-md items-center justify-center cursor-zoom-out p-8">
  <img id="zoomImg" alt="zoom" class="max-w-[95vw] max-h-[95vh] object-contain shadow-2xl">
</div>

{{-- Plan-required modal (shared partial). Triggered by the live-preview
     fetch handler when the backend returns 402 with code=plan_required. --}}
@include('photographer.presets.partials.upgrade-modal')

@push('scripts')
<script>
(() => {
  const csrf = '{{ csrf_token() }}';
  const previewUrl = '{{ route('photographer.presets.preview') }}';
  const uploadUrl  = '{{ route('photographer.presets.upload-sample') }}';

  let currentSource = '{{ $defaultSource }}';
  let originalBlobUrl = null;
  let renderInFlight = false;
  let renderQueued = false;
  let compareMode = false;
  // When the backend reports the photographer's plan no longer covers
  // presets (e.g. they downgraded mid-session), we flip this and stop
  // auto-firing /preview so the slider drag doesn't spam 402s.
  let presetsLocked = false;

  const $ = (id) => document.getElementById(id);
  const previewImg   = $('livePreview');
  const beforeImg    = $('beforeImg');
  const afterImg     = $('afterImg');
  const compareBox   = $('compareMode');
  const compareToggle = $('compareToggle');
  const zoomBtn      = $('zoomBtn');
  const zoomOverlay  = $('zoomOverlay');
  const zoomImg      = $('zoomImg');
  const dropOverlay  = $('dropOverlay');
  const previewPane  = $('previewPane');
  const renderBar    = $('renderBar');
  const previewMeta  = $('previewMeta');
  const customUpload = $('customUpload');
  const sampleStrip  = $('sampleStrip');
  const resetAllBtn  = $('resetAllBtn');
  const bwToggle     = $('bwToggle');
  const bwInput      = $('grayscaleInput');
  const tabIndicator = $('tabIndicator');

  // ─── Tab switching with sliding indicator ──────────────────────────
  function moveIndicator(targetTab) {
    if (!tabIndicator || !targetTab) return;
    const parent = targetTab.parentElement;
    const rect = targetTab.getBoundingClientRect();
    const pRect = parent.getBoundingClientRect();
    tabIndicator.style.width = rect.width + 'px';
    tabIndicator.style.left = (rect.left - pRect.left) + 'px';
  }
  document.querySelectorAll('.tab-pill').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.tab-pill').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      moveIndicator(tab);
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
      document.querySelectorAll('[data-panel="' + tab.dataset.tab + '"]').forEach(p => p.classList.remove('hidden'));
    });
  });
  // Initial position
  setTimeout(() => moveIndicator(document.querySelector('.tab-pill.active')), 50);
  window.addEventListener('resize', () => moveIndicator(document.querySelector('.tab-pill.active')));

  // ─── Slider sync ──────────────────────────────────────────────────
  document.querySelectorAll('.preset-slider').forEach(s => {
    s.addEventListener('input', () => {
      const numeric = document.querySelector('[data-for="' + s.name + '"]');
      if (numeric) { numeric.value = s.value; markDirty(numeric, s); }
      scheduleRefresh();
    });
    s.addEventListener('dblclick', () => {
      s.value = s.dataset.default || 0;
      const numeric = document.querySelector('[data-for="' + s.name + '"]');
      if (numeric) { numeric.value = s.value; markDirty(numeric, s); }
      scheduleRefresh();
    });
  });
  document.querySelectorAll('.preset-numeric').forEach(n => {
    n.addEventListener('input', () => {
      const slider = document.querySelector('input.preset-slider[name="' + n.dataset.for + '"]');
      if (slider) { slider.value = n.value; markDirty(n, slider); }
      scheduleRefresh();
    });
  });

  function markDirty(numeric, slider) {
    const def = parseFloat(slider.dataset.default || 0);
    const cur = parseFloat(slider.value || 0);
    numeric.classList.toggle('dirty', Math.abs(cur - def) > 0.001);
  }

  // ─── B&W toggle ────────────────────────────────────────────────────
  if (bwToggle) {
    bwToggle.addEventListener('click', () => {
      const isOn = bwToggle.classList.toggle('on');
      bwToggle.querySelector('span:last-child').textContent = isOn ? 'ON' : 'OFF';
      bwInput.value = isOn ? '1' : '0';
      scheduleRefresh();
    });
  }

  // ─── Reset all ─────────────────────────────────────────────────────
  resetAllBtn.addEventListener('click', () => {
    if (!confirm('รีเซ็ตทุก slider กลับเป็นศูนย์?')) return;
    document.querySelectorAll('.preset-slider').forEach(s => {
      s.value = s.dataset.default || 0;
      const numeric = document.querySelector('[data-for="' + s.name + '"]');
      if (numeric) { numeric.value = s.value; numeric.classList.remove('dirty'); }
    });
    if (bwToggle && bwInput.value === '1') bwToggle.click();
    scheduleRefresh();
  });

  // ─── Sample picker ─────────────────────────────────────────────────
  sampleStrip.addEventListener('click', (e) => {
    const btn = e.target.closest('.sample-thumb[data-source]');
    if (!btn) return;
    document.querySelectorAll('.sample-thumb').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentSource = btn.dataset.source;
    refresh(true);
  });

  // ─── Upload ────────────────────────────────────────────────────────
  customUpload.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) handleUpload(file);
  });

  async function handleUpload(file) {
    if (!file.type.startsWith('image/')) { alert('กรุณาเลือกไฟล์รูปภาพ'); return; }
    if (file.size > 10 * 1024 * 1024) { alert('ไฟล์ใหญ่เกิน 10 MB'); return; }

    previewMeta.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> กำลังอัปโหลด...';
    renderBar.classList.add('active');

    const fd = new FormData();
    fd.append('_token', csrf);
    fd.append('image', file);

    try {
      const resp = await fetch(uploadUrl, {
        method: 'POST', body: fd,
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
      });
      if (resp.status === 402) {
        const errData = await resp.json().catch(() => null);
        if (errData && errData.code === 'plan_required') {
          presetsLocked = true;
          if (typeof window.openPresetUpgradeModal === 'function') window.openPresetUpgradeModal();
          previewMeta.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500"></span> ต้องอัปเกรดแผน';
          return;
        }
      }
      const data = await resp.json();
      if (data.success && data.source) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'sample-thumb';
        btn.dataset.source = data.source;
        btn.title = 'Custom (' + Math.round(data.size_bytes / 1024) + ' KB)';
        const img = document.createElement('img');
        const reader = new FileReader();
        reader.onload = (ev) => { img.src = ev.target.result; };
        reader.readAsDataURL(file);
        btn.appendChild(img);
        sampleStrip.insertBefore(btn, customUpload.parentElement);

        document.querySelectorAll('.sample-thumb').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentSource = data.source;
        refresh(true);
      } else {
        alert(data.message || 'Upload failed');
      }
    } catch (err) {
      alert('Upload error: ' + err.message);
    } finally {
      renderBar.classList.remove('active');
      customUpload.value = '';
    }
  }

  // ─── Drag-drop ─────────────────────────────────────────────────────
  ['dragenter', 'dragover'].forEach(evt =>
    previewPane.addEventListener(evt, (e) => {
      e.preventDefault();
      dropOverlay.classList.remove('hidden');
      dropOverlay.classList.add('flex');
    })
  );
  previewPane.addEventListener('dragleave', (e) => {
    if (e.target === previewPane || e.target === dropOverlay) {
      dropOverlay.classList.add('hidden');
      dropOverlay.classList.remove('flex');
    }
  });
  previewPane.addEventListener('drop', (e) => {
    e.preventDefault();
    dropOverlay.classList.add('hidden');
    dropOverlay.classList.remove('flex');
    const file = e.dataTransfer.files[0];
    if (file) handleUpload(file);
  });

  // ─── Compare ───────────────────────────────────────────────────────
  compareToggle.addEventListener('click', () => {
    compareMode = !compareMode;
    if (compareMode) {
      previewImg.classList.add('hidden');
      compareBox.classList.remove('hidden');
      compareToggle.classList.add('!bg-rose-500/15', '!text-rose-300', '!border-rose-500/30');
      if (originalBlobUrl) beforeImg.src = originalBlobUrl;
      if (previewImg.src) afterImg.src = previewImg.src;
    } else {
      previewImg.classList.remove('hidden');
      compareBox.classList.add('hidden');
      compareToggle.classList.remove('!bg-rose-500/15', '!text-rose-300', '!border-rose-500/30');
    }
  });

  // ─── Zoom ──────────────────────────────────────────────────────────
  function openZoom() {
    if (!previewImg.src) return;
    zoomImg.src = previewImg.src;
    zoomOverlay.classList.replace('hidden', 'flex');
  }
  zoomBtn.addEventListener('click', openZoom);
  previewImg.addEventListener('click', openZoom);
  zoomOverlay.addEventListener('click', () => zoomOverlay.classList.replace('flex', 'hidden'));

  // ─── Render ────────────────────────────────────────────────────────
  function readSettings() {
    const s = {};
    document.querySelectorAll('.preset-slider').forEach(el => { s[el.name] = parseFloat(el.value) || 0; });
    s['grayscale'] = bwInput.value === '1' ? 1 : 0;
    return s;
  }

  // Inspect a /preview response for the plan-required JSON envelope.
  // When detected, lock further auto-renders, flip the meta strip into
  // a "ต้องอัปเกรด" hint, and pop the upgrade modal once. Returns true
  // if the response was a 402 plan-required (caller should bail out).
  async function handlePlanRequired(resp) {
    if (resp.status !== 402) return false;
    let data = null;
    try { data = await resp.clone().json(); } catch (e) {}
    if (!data || data.code !== 'plan_required') return false;

    presetsLocked = true;
    if (previewMeta) {
      previewMeta.innerHTML =
        '<span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500"></span> ' +
        '<button type="button" onclick="openPresetUpgradeModal()" class="text-amber-300 hover:text-amber-200 underline">' +
        'ต้องอัปเกรดแผนเพื่อดู preview</button>';
    }
    if (typeof window.openPresetUpgradeModal === 'function') {
      window.openPresetUpgradeModal();
    }
    return true;
  }

  async function fetchOriginal() {
    if (presetsLocked) return;
    const fd = new FormData();
    fd.append('_token', csrf);
    fd.append('source', currentSource);
    fd.append('size', 800);
    Object.keys(readSettings()).forEach(k => fd.append('settings[' + k + ']', 0));
    const resp = await fetch(previewUrl, {
      method: 'POST', body: fd,
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
    });
    if (await handlePlanRequired(resp)) return;
    if (resp.ok) {
      const blob = await resp.blob();
      if (originalBlobUrl) URL.revokeObjectURL(originalBlobUrl);
      originalBlobUrl = URL.createObjectURL(blob);
      beforeImg.src = originalBlobUrl;
    }
  }

  async function refresh(rebuildBefore = false) {
    if (presetsLocked) return;
    if (renderInFlight) { renderQueued = true; return; }
    renderInFlight = true;
    renderBar.classList.add('active');

    if (rebuildBefore) { try { await fetchOriginal(); } catch (e) {} }
    if (presetsLocked) {
      renderBar.classList.remove('active');
      renderInFlight = false;
      return;
    }

    const settings = readSettings();
    const fd = new FormData();
    fd.append('_token', csrf);
    fd.append('source', currentSource);
    fd.append('size', 800);
    Object.entries(settings).forEach(([k, v]) => fd.append('settings[' + k + ']', v));

    const t0 = performance.now();
    try {
      const resp = await fetch(previewUrl, {
        method: 'POST', body: fd,
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
      });
      if (await handlePlanRequired(resp)) {
        renderBar.classList.remove('active');
        renderInFlight = false;
        return;
      }
      if (resp.ok) {
        const blob = await resp.blob();
        const url = URL.createObjectURL(blob);
        previewImg.src = url;
        afterImg.src = url;
        const ms = Math.round(performance.now() - t0);
        previewMeta.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span> ' + ms + 'ms · ' + Math.round(blob.size / 1024) + ' KB';
      } else {
        previewMeta.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-500"></span> HTTP ' + resp.status;
      }
    } catch (e) {
      previewMeta.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-500"></span> ' + e.message;
    }
    renderBar.classList.remove('active');
    renderInFlight = false;
    if (renderQueued && !presetsLocked) { renderQueued = false; refresh(); }
  }

  let timer;
  function scheduleRefresh() {
    if (presetsLocked) return;
    clearTimeout(timer);
    timer = setTimeout(() => refresh(false), 200);
  }

  // ─── Init ──────────────────────────────────────────────────────────
  document.querySelectorAll('.preset-slider').forEach(s => {
    const numeric = document.querySelector('[data-for="' + s.name + '"]');
    if (numeric) markDirty(numeric, s);
  });
  refresh(true);
})();
</script>
@endpush
@endsection
