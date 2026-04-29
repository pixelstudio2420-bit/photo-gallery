@extends('layouts.admin')

@section('title', 'Source Protection')

@push('styles')
@include('admin.settings._shared-styles')
<style>
  /* Protection level radio cards */
  .sp-level-card {
    position: relative;
    border-radius: 0.75rem;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: all .2s ease;
    background: rgb(248 250 252);
    border: 2px solid rgb(226 232 240);
  }
  .dark .sp-level-card { background: rgba(30,41,59,.5); border-color: rgba(255,255,255,.08); }
  .sp-level-card:hover { border-color: rgb(99 102 241); }
  .sp-level-radio:checked ~ .sp-level-card {
    border-color: rgb(99 102 241);
    background: rgba(99,102,241,.08);
    box-shadow: 0 0 0 3px rgba(99,102,241,.15);
  }
  .sp-level-card .sp-check {
    position: absolute;
    top: .5rem; right: .5rem;
    width: 20px; height: 20px;
    border-radius: 50%;
    display: none;
    align-items: center; justify-content: center;
    background: rgb(99 102 241);
    color: white;
    font-size: .7rem;
  }
  .sp-level-radio:checked ~ .sp-level-card .sp-check { display: flex; }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ═══ Page Header ═══ --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center shadow-lg shadow-indigo-500/30">
        <i class="bi bi-shield-lock text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Source Protection</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Protect your HTML source code and prevent unauthorised copying.
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

  <form method="POST" action="{{ route('admin.settings.source-protection.update') }}">
    @csrf

    {{-- ═══ Master Enable ═══ --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-5">
      <div class="p-5 flex items-center justify-between gap-4">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-indigo-100 dark:bg-indigo-500/15">
            <i class="bi bi-power text-indigo-600 dark:text-indigo-400 text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">Enable Source Protection</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Activate all source protection features on the front-end.
            </p>
          </div>
        </div>
        <label class="tw-switch">
          <input type="checkbox" name="source_protection_enabled" id="source_protection_enabled" value="1"
                 {{ ($settings['source_protection_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
          <span class="tw-switch-track"></span>
          <span class="tw-switch-knob"></span>
        </label>
      </div>
    </div>

    {{-- ═══ Protection Level ═══ --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-5">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
        <h3 class="font-semibold text-slate-900 dark:text-white">
          <i class="bi bi-sliders mr-2 text-indigo-600 dark:text-indigo-400"></i>Protection Level
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          Choose a preset level — individual toggles below can further customise behaviour.
        </p>
      </div>
      <div class="p-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          @php
            $levels = [
              ['value' => 'light',    'emoji' => '🟢', 'label' => 'Light',    'desc' => 'Disables right-click & DevTools detection only. Minimal impact on usability.'],
              ['value' => 'standard', 'emoji' => '🟡', 'label' => 'Standard', 'desc' => 'Light + disables View Source, Copy, and Drag. Recommended for most sites.'],
              ['value' => 'strict',   'emoji' => '🔴', 'label' => 'Strict',   'desc' => 'Standard + HTML obfuscation & console warning. Maximum protection.'],
            ];
            $currentLevel = $settings['source_protection_level'] ?? 'standard';
          @endphp
          @foreach($levels as $level)
          <label class="block cursor-pointer relative">
            <input type="radio" name="source_protection_level" value="{{ $level['value'] }}" class="sp-level-radio sr-only"
                   {{ $currentLevel === $level['value'] ? 'checked' : '' }}>
            <div class="sp-level-card">
              <span class="sp-check"><i class="bi bi-check-lg"></i></span>
              <div class="text-3xl mb-2">{{ $level['emoji'] }}</div>
              <h4 class="font-bold text-slate-900 dark:text-white mb-1">{{ $level['label'] }}</h4>
              <p class="text-xs text-slate-500 dark:text-slate-400">{{ $level['desc'] }}</p>
            </div>
          </label>
          @endforeach
        </div>
      </div>
    </div>

    {{-- ═══ Individual Toggles ═══ --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-5">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
        <h3 class="font-semibold text-slate-900 dark:text-white">
          <i class="bi bi-toggles mr-2 text-indigo-600 dark:text-indigo-400"></i>Individual Controls
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          Fine-tune which protections are active.
        </p>
      </div>
      @php
      $toggles = [
        ['key' => 'sp_disable_rightclick', 'icon' => 'bi-mouse2',              'label' => 'Disable Right-Click',   'desc' => 'Blocks the context menu on right-click'],
        ['key' => 'sp_disable_devtools',   'icon' => 'bi-tools',               'label' => 'Disable DevTools',      'desc' => 'Detects and reacts when browser DevTools are opened'],
        ['key' => 'sp_disable_viewsource', 'icon' => 'bi-code-slash',          'label' => 'Disable View Source',   'desc' => 'Blocks Ctrl+U / Cmd+U keyboard shortcut'],
        ['key' => 'sp_disable_drag',       'icon' => 'bi-hand-index',          'label' => 'Disable Drag',          'desc' => 'Prevents users from dragging images and text'],
        ['key' => 'sp_disable_copy',       'icon' => 'bi-clipboard-x',         'label' => 'Disable Copy',          'desc' => 'Blocks Ctrl+C / Cmd+C and text selection copy'],
        ['key' => 'sp_obfuscate_html',     'icon' => 'bi-file-earmark-x',      'label' => 'Obfuscate HTML',        'desc' => 'Renders page content in a way that obscures source HTML'],
        ['key' => 'sp_console_warning',    'icon' => 'bi-exclamation-triangle','label' => 'Console Warning',       'desc' => 'Shows a stern security warning in the browser console'],
      ];
      @endphp
      <div class="divide-y divide-slate-200 dark:divide-white/10">
        @foreach($toggles as $toggle)
        <div class="flex items-center justify-between gap-3 px-5 py-3.5">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0
                        bg-indigo-100 dark:bg-indigo-500/15
                        text-indigo-600 dark:text-indigo-400">
              <i class="bi {{ $toggle['icon'] }}"></i>
            </div>
            <div class="min-w-0">
              <div class="font-medium text-sm text-slate-900 dark:text-white">{{ $toggle['label'] }}</div>
              <div class="text-xs text-slate-500 dark:text-slate-400">{{ $toggle['desc'] }}</div>
            </div>
          </div>
          <label class="tw-switch">
            <input type="checkbox" name="{{ $toggle['key'] }}" id="{{ $toggle['key'] }}" value="1"
                   {{ ($settings[$toggle['key']] ?? '0') === '1' ? 'checked' : '' }}>
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
        </div>
        @endforeach
      </div>
    </div>

    {{-- ═══ Apply to Admin (warning card) ═══ --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-5 border-l-4 border-l-amber-500">
      <div class="p-5 flex items-center justify-between gap-4">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-amber-100 dark:bg-amber-500/15">
            <i class="bi bi-exclamation-triangle text-amber-600 dark:text-amber-400 text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-amber-700 dark:text-amber-300">Apply to Admin Pages</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
              Also apply source protection to admin panel pages.
              <strong class="text-amber-700 dark:text-amber-400">Warning:</strong> this may interfere with admin usability (copy-paste, DevTools).
            </p>
          </div>
        </div>
        <label class="tw-switch">
          <input type="checkbox" name="sp_apply_admin" id="sp_apply_admin" value="1"
                 {{ ($settings['sp_apply_admin'] ?? '0') === '1' ? 'checked' : '' }}>
          <span class="tw-switch-track"></span>
          <span class="tw-switch-knob"></span>
        </label>
      </div>
    </div>

    {{-- ═══ Save ═══ --}}
    <div class="flex justify-end gap-2">
      <a href="{{ route('admin.settings.index') }}"
         class="px-4 py-2 rounded-lg text-sm font-medium
                bg-white dark:bg-slate-800
                border border-slate-200 dark:border-white/10
                text-slate-700 dark:text-slate-200
                hover:bg-slate-50 dark:hover:bg-slate-700 transition">
        Cancel
      </a>
      <button type="submit"
              class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg text-sm font-semibold text-white
                     bg-gradient-to-r from-indigo-600 to-violet-600
                     hover:from-indigo-500 hover:to-violet-500
                     shadow-md shadow-indigo-500/30 transition">
        <i class="bi bi-save"></i> Save Protection Settings
      </button>
    </div>
  </form>

</div>
@endsection
