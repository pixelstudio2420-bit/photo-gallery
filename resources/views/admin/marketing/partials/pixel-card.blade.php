{{-- Reusable pixel-config card — modern brand-themed design --}}
@php
  // Determine if any field has a value — used for "configured" status indicator
  $_anyConfigured = false;
  foreach ($fields as $_f) {
    if (!empty($_f['value'])) { $_anyConfigured = true; break; }
  }
@endphp
<div x-data="{ enabled: {{ $toggleValue ? 'true' : 'false' }}, expanded: {{ $toggleValue ? 'true' : 'false' }} }"
     class="pixel-card platform-{{ $platform ?? 'meta' }} rounded-2xl border border-slate-200/70 dark:border-white/[0.06]
            bg-white dark:bg-slate-800 overflow-hidden shadow-sm"
     :class="enabled ? 'is-on' : ''">

  {{-- Header row --}}
  <div class="flex items-center gap-3 p-4 cursor-pointer"
       @click="if($event.target.closest('label,input,a,button')) return; expanded = !expanded">
    <div class="pixel-icon-bg w-11 h-11 rounded-xl flex items-center justify-center text-lg shrink-0
                bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 transition-all">
      <i class="bi {{ $icon }}"></i>
    </div>

    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2 flex-wrap">
        <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 truncate">{{ $title }}</h3>

        {{-- Optional badge (Recommended, ไทย, etc.) --}}
        @if(!empty($badge))
          <span class="inline-flex items-center text-[10px] font-bold px-2 py-0.5 rounded-full
                       bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700
                       dark:bg-{{ $badge['color'] }}-500/15 dark:text-{{ $badge['color'] }}-300">
            <i class="bi bi-star-fill mr-0.5 text-[8px]"></i>{{ $badge['label'] }}
          </span>
        @endif

        {{-- Status indicator: configured / not configured / active --}}
        <template x-if="enabled">
          <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            ทำงาน
          </span>
        </template>
        <template x-if="!enabled">
          @if($_anyConfigured)
            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400">
              <i class="bi bi-pause-fill"></i> ปิด
            </span>
          @else
            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
              <i class="bi bi-dash-circle"></i> ยังไม่ตั้งค่า
            </span>
          @endif
        </template>
      </div>

      @if(!empty($help))
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed line-clamp-1">{{ $help }}</p>
      @endif
    </div>

    {{-- Expand chevron --}}
    <button type="button"
            @click.stop="expanded = !expanded"
            class="hidden md:inline-flex w-7 h-7 items-center justify-center rounded-lg
                   text-slate-400 hover:text-slate-600 dark:hover:text-slate-200
                   hover:bg-slate-100 dark:hover:bg-slate-700/50 transition shrink-0">
      <i class="bi" :class="expanded ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
    </button>

    {{-- Toggle switch --}}
    <label class="inline-flex items-center cursor-pointer shrink-0">
      <input type="hidden" name="{{ $toggleName }}" value="0">
      <input type="checkbox" name="{{ $toggleName }}" value="1"
             x-model="enabled"
             @change="$el.closest('[x-data*=pixelsForm]')?._x_dataStack?.[0] && ($el.closest('[x-data*=pixelsForm]')._x_dataStack[0].form['{{ $toggleName }}'] = enabled, $el.closest('[x-data*=pixelsForm]')._x_dataStack[0].hasChanges = true); if(enabled) expanded = true;"
             class="sr-only" {{ $toggleValue ? 'checked' : '' }}>
      <span class="toggle-switch transition-all"
            :class="enabled ? 'is-on' : 'bg-slate-200 dark:bg-slate-700'"
            :style="enabled ? 'background: linear-gradient(135deg, var(--brand), var(--brand-2))' : ''"></span>
    </label>
  </div>

  {{-- Expandable fields section --}}
  <div x-show="expanded" x-collapse
       class="border-t border-slate-100 dark:border-white/[0.04] bg-slate-50/40 dark:bg-slate-900/20">
    <div class="p-4 space-y-3">
      {{-- Full help text in expanded view --}}
      @if(!empty($help))
      <p class="text-[12px] text-slate-600 dark:text-slate-400 leading-relaxed flex items-start gap-1.5">
        <i class="bi bi-info-circle text-slate-400 mt-0.5"></i>
        <span>{{ $help }}</span>
      </p>
      @endif

      @foreach($fields as $field)
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">
            {{ $field['label'] }}
          </label>
          <div class="relative">
            <input type="{{ $field['type'] ?? 'text' }}"
                   name="{{ $field['name'] }}"
                   value="{{ $field['value'] }}"
                   placeholder="{{ $field['placeholder'] ?? '' }}"
                   autocomplete="off"
                   spellcheck="false"
                   class="w-full px-3.5 py-2 rounded-lg bg-white dark:bg-slate-800
                          border border-slate-200 dark:border-white/[0.08]
                          text-slate-800 dark:text-slate-100 text-sm font-mono
                          focus:outline-none focus:ring-2 transition"
                   style="--tw-ring-color: var(--brand);">
            @if(($field['type'] ?? 'text') !== 'password' && !empty($field['value']))
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-emerald-500 text-xs">
                <i class="bi bi-check-circle-fill"></i>
              </span>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>
