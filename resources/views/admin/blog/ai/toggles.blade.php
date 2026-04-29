@extends('layouts.admin')

@section('title', 'AI Tools Settings')

@push('styles')
<style>
  .toggle-card {
    transition: transform .2s cubic-bezier(.34,1.56,.64,1), box-shadow .2s, border-color .2s, background .2s;
  }
  .toggle-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px -8px rgb(99 102 241 / .25);
  }
  .toggle-switch {
    position: relative; width: 44px; height: 24px;
    border-radius: 9999px;
    transition: background .25s ease;
    cursor: pointer;
  }
  .toggle-switch::after {
    content: ''; position: absolute;
    top: 3px; left: 3px;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: white;
    box-shadow: 0 2px 6px rgba(0,0,0,.2);
    transition: transform .25s cubic-bezier(.34,1.56,.64,1);
  }
  .toggle-switch.is-on::after { transform: translateX(20px); }

  .toggle-switch-lg {
    width: 64px; height: 32px;
  }
  .toggle-switch-lg::after {
    top: 4px; left: 4px;
    width: 24px; height: 24px;
  }
  .toggle-switch-lg.is-on::after { transform: translateX(32px); }

  @keyframes pulse-soft {
    0%, 100% { box-shadow: 0 0 0 0 var(--pulse-c, rgba(16,185,129,.5)); }
    50%      { box-shadow: 0 0 0 8px transparent; }
  }
  .pulse-on { animation: pulse-soft 2.5s ease-in-out infinite; }

  .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(99,102,241,.15) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(139,92,246,.15) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(236,72,153,.08) 0px, transparent 50%);
  }
  .dark .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(99,102,241,.18) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(139,92,246,.18) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(236,72,153,.12) 0px, transparent 50%);
  }

  /* Provider radio card */
  .provider-radio {
    cursor: pointer;
    transition: all .2s;
  }
  .provider-radio input[type="radio"] { display: none; }
  .provider-radio:has(input:checked) {
    border-color: rgb(99 102 241);
    background: linear-gradient(135deg, rgba(99,102,241,.08), rgba(139,92,246,.05));
    box-shadow: 0 4px 16px -4px rgba(99,102,241,.3);
  }
  .dark .provider-radio:has(input:checked) {
    border-color: rgb(129 140 248);
    background: linear-gradient(135deg, rgba(99,102,241,.15), rgba(139,92,246,.1));
  }
  .provider-radio:has(input:checked) .check-mark { opacity: 1; transform: scale(1); }
  .provider-radio .check-mark { opacity: 0; transform: scale(.5); transition: opacity .2s, transform .2s; }
  .provider-radio:has(input:disabled) { opacity: .4; cursor: not-allowed; }

  /* Pending changes indicator */
  @keyframes pending-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,.4); }
    50%      { box-shadow: 0 0 0 6px transparent; }
  }
  .has-changes { animation: pending-glow 1.8s ease-in-out infinite; }
</style>
@endpush

@section('content')
<div x-data="aiToggles({{ json_encode([
        'master' => (bool) $matrix['master'],
        'tools' => collect($matrix['tools'])->mapWithKeys(fn($t) => [$t['key'] => (bool) $t['enabled']])->all(),
        'providers' => collect($matrix['providers'])->mapWithKeys(fn($p) => [$p['key'] => (bool) $p['enabled']])->all(),
        'default_provider' => \App\Models\AppSetting::get('blog_ai_default_provider', 'openai'),
     ]) }})"
     class="space-y-5 pb-24">

  {{-- ── HERO HEADER ────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-indigo-100 dark:border-indigo-500/20 gradient-mesh">
    <div class="relative p-6 md:p-7 flex items-start justify-between flex-wrap gap-4">
      <div class="flex items-start gap-4 min-w-0 flex-1">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 via-violet-500 to-pink-500 text-white flex items-center justify-center shadow-lg shrink-0">
          <i class="bi bi-toggles text-2xl"></i>
        </div>
        <div class="min-w-0 flex-1">
          <h1 class="text-2xl md:text-[1.65rem] font-bold text-slate-800 dark:text-gray-100 tracking-tight leading-tight">
            เปิด-ปิด AI Tools
          </h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            จัดการ AI tools และ providers ทีละรายการ — เปลี่ยนแล้วกด "บันทึก" ที่ด้านล่าง
          </p>

          {{-- Live stats pills --}}
          <div class="flex items-center gap-2 mt-3 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                  :class="master ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400'">
              <span class="w-1.5 h-1.5 rounded-full" :class="master ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
              <span x-text="master ? 'Master ON' : 'Master OFF'"></span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
              <i class="bi bi-tools"></i>
              <span x-text="enabledToolsCount + ' / ' + totalToolsCount + ' tools'"></span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
              <i class="bi bi-robot"></i>
              <span x-text="enabledProvidersCount + ' / ' + totalProvidersCount + ' providers'"></span>
            </span>
          </div>
        </div>
      </div>

      <a href="{{ route('admin.blog.ai.index') }}"
         class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-medium
                bg-white/80 dark:bg-slate-800/80 backdrop-blur
                text-slate-700 dark:text-slate-200
                border border-slate-200/60 dark:border-white/10
                hover:bg-white dark:hover:bg-slate-700 transition shrink-0">
        <i class="bi bi-chevron-left"></i> Dashboard
      </a>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.blog.ai.toggles.save') }}"
        @submit="hasChanges = false"
        class="space-y-5">
    @csrf

    {{-- ── MASTER SWITCH ──────────────────────────────────────────── --}}
    <div class="relative overflow-hidden rounded-2xl shadow-lg transition-all duration-300"
         :class="master ? 'bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-600' : 'bg-gradient-to-br from-slate-500 via-slate-600 to-slate-700'">
      {{-- Decorative corner blob --}}
      <div class="absolute -top-12 -right-12 w-40 h-40 rounded-full opacity-20"
           style="background: radial-gradient(circle, white 0%, transparent 70%);"></div>

      <div class="relative p-6 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4 min-w-0 flex-1">
          <div class="w-14 h-14 rounded-xl bg-white/15 backdrop-blur flex items-center justify-center shrink-0"
               :class="master ? 'pulse-on' : ''" style="--pulse-c: rgba(255,255,255,.4);">
            <i class="bi bi-power text-white text-2xl"></i>
          </div>
          <div class="min-w-0 text-white">
            <h2 class="text-xl font-bold flex items-center gap-2">
              Master Switch
              <span class="text-xs px-2 py-0.5 rounded-full bg-white/20 backdrop-blur"
                    x-text="master ? 'ON' : 'OFF'"></span>
            </h2>
            <p class="text-sm opacity-90 mt-0.5" x-show="master">
              ระบบ AI กำลังทำงาน — tools และ providers ที่เปิดไว้จะใช้งานได้
            </p>
            <p class="text-sm opacity-90 mt-0.5" x-show="!master" x-cloak>
              ระบบ AI ถูกปิด — ไม่มี tool/provider ทำงานได้จนกว่าจะเปิดสวิตช์นี้
            </p>
          </div>
        </div>

        <label class="inline-flex items-center cursor-pointer shrink-0">
          <input type="checkbox" name="master" value="1" x-model="master" @change="markChanged()" class="sr-only">
          <span class="toggle-switch toggle-switch-lg"
                :class="master ? 'is-on bg-white' : 'bg-white/30'"></span>
        </label>
      </div>
    </div>

    {{-- ── AI TOOLS ───────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl overflow-hidden shadow-sm">
      <div class="px-5 md:px-6 py-4 border-b border-slate-100 dark:border-white/[0.06] bg-gradient-to-r from-indigo-50/50 to-transparent dark:from-indigo-500/5">
        <div class="flex items-center justify-between flex-wrap gap-2">
          <div>
            <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
              <i class="bi bi-tools text-indigo-500"></i>AI Tools
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300"
                    x-text="enabledToolsCount + '/' + totalToolsCount"></span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">เลือกเครื่องมือที่ต้องการให้ผู้ใช้เปิดใช้งานได้</p>
          </div>
          <div class="flex items-center gap-2 text-xs">
            <button type="button" @click="setAllTools(true); markChanged()"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition font-medium">
              <i class="bi bi-check-all"></i> เปิดทั้งหมด
            </button>
            <button type="button" @click="setAllTools(false); markChanged()"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-500/20 transition font-medium">
              <i class="bi bi-x-lg"></i> ปิดทั้งหมด
            </button>
          </div>
        </div>
      </div>

      <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
        @foreach($matrix['tools'] as $tool)
        <div class="toggle-card flex items-center justify-between gap-4 p-4 md:p-5"
             :class="(tools['{{ $tool['key'] }}'] && master) ? 'bg-white dark:bg-slate-800' : 'bg-slate-50/40 dark:bg-slate-900/20'">
          <div class="flex items-center gap-4 flex-1 min-w-0">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                 :class="(tools['{{ $tool['key'] }}'] && master)
                   ? 'bg-gradient-to-br from-indigo-500 to-violet-500 text-white shadow-md shadow-indigo-500/30'
                   : 'bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500'">
              <i class="bi bi-{{ $tool['meta']['icon'] }} text-lg"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-sm text-slate-800 dark:text-slate-100">{{ $tool['meta']['label'] }}</span>
                <code class="text-[10px] text-slate-400 dark:text-slate-500 font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700/50">{{ $tool['key'] }}</code>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">{{ $tool['meta']['desc'] }}</p>
            </div>
          </div>

          <label class="inline-flex items-center cursor-pointer shrink-0">
            <input type="checkbox" name="tools[{{ $tool['key'] }}]" value="1"
                   x-model="tools['{{ $tool['key'] }}']"
                   @change="markChanged()"
                   :disabled="!master"
                   class="sr-only">
            <span class="toggle-switch"
                  :class="tools['{{ $tool['key'] }}'] ? 'is-on bg-emerald-500' : 'bg-slate-200 dark:bg-slate-700'"
                  :style="!master ? 'opacity:0.4;cursor:not-allowed' : ''"></span>
          </label>
        </div>
        @endforeach
      </div>
    </div>

    {{-- ── AI PROVIDERS ───────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl overflow-hidden shadow-sm">
      <div class="px-5 md:px-6 py-4 border-b border-slate-100 dark:border-white/[0.06] bg-gradient-to-r from-violet-50/50 to-transparent dark:from-violet-500/5">
        <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <i class="bi bi-robot text-violet-500"></i>AI Providers
          <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
                x-text="enabledProvidersCount + '/' + totalProvidersCount"></span>
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">เปิด/ปิด AI provider — ต้องตั้ง API key ก่อนใช้งาน</p>
      </div>

      <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
        @foreach($matrix['providers'] as $p)
        <div class="toggle-card flex items-center justify-between gap-4 p-4 md:p-5"
             :class="(providers['{{ $p['key'] }}'] && master && {{ $p['has_api_key'] ? 'true' : 'false' }}) ? 'bg-white dark:bg-slate-800' : 'bg-slate-50/40 dark:bg-slate-900/20'">
          <div class="flex items-center gap-4 flex-1 min-w-0">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 text-2xl
                        bg-gradient-to-br from-indigo-500 via-violet-500 to-pink-500 text-white shadow-md shadow-violet-500/20">
              {{ $p['meta']['icon'] }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-sm text-slate-800 dark:text-slate-100">{{ $p['meta']['label'] }}</span>

                @if($p['has_api_key'])
                  <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                    <i class="bi bi-key-fill"></i> API Key
                  </span>
                @else
                  <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                    <i class="bi bi-exclamation-triangle-fill"></i> ไม่มี API Key
                  </span>
                @endif

                <span x-show="providers['{{ $p['key'] }}'] && master && {{ $p['has_api_key'] ? 'true' : 'false' }}"
                      class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500 text-white">
                  <i class="bi bi-check-circle-fill"></i> ใช้งานได้
                </span>
                <span x-show="!(providers['{{ $p['key'] }}'] && master && {{ $p['has_api_key'] ? 'true' : 'false' }})" x-cloak
                      class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400">
                  ปิด
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                @php
                  $_models = $p['meta']['models'];
                  $_modelsDisplay = implode(', ', array_slice($_models, 0, 3));
                  $_extraCount = max(0, count($_models) - 3);
                @endphp
                Models: <span class="font-mono text-[11px]">{{ $_modelsDisplay }}{{ $_extraCount > 0 ? ' +' . $_extraCount : '' }}</span>
              </p>
              <code class="text-[10px] text-slate-400 dark:text-slate-500 font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700/50 mt-1 inline-block">{{ $p['key'] }}</code>
            </div>
          </div>

          <label class="inline-flex items-center cursor-pointer shrink-0">
            <input type="checkbox" name="providers[{{ $p['key'] }}]" value="1"
                   x-model="providers['{{ $p['key'] }}']"
                   @change="markChanged()"
                   :disabled="!master"
                   class="sr-only">
            <span class="toggle-switch"
                  :class="providers['{{ $p['key'] }}'] ? 'is-on bg-violet-500' : 'bg-slate-200 dark:bg-slate-700'"
                  :style="!master ? 'opacity:0.4;cursor:not-allowed' : ''"></span>
          </label>
        </div>
        @endforeach
      </div>

      <div class="px-5 md:px-6 py-3 bg-amber-50/60 dark:bg-amber-500/[0.06] border-t border-amber-200/60 dark:border-amber-500/15 text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
        <i class="bi bi-info-circle-fill"></i>
        <strong>ตั้งค่า API Keys:</strong>
        แก้ไฟล์ <code class="px-1 py-0.5 rounded bg-amber-100/60 dark:bg-amber-500/10 font-mono text-[11px]">.env</code> หรือไปที่
        <a href="{{ route('admin.settings.index') }}" class="underline font-semibold hover:text-amber-900 dark:hover:text-amber-200">Admin Settings</a> —
        keys: <code class="font-mono text-[11px]">OPENAI_API_KEY</code>,
        <code class="font-mono text-[11px]">ANTHROPIC_API_KEY</code>,
        <code class="font-mono text-[11px]">GEMINI_API_KEY</code>
      </div>
    </div>

    {{-- ── DEFAULT PROVIDER (radio cards instead of dropdown) ─────── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-5 md:p-6 shadow-sm">
      <div class="mb-3">
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <i class="bi bi-star-fill text-amber-500"></i>Default Provider
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Provider ที่ระบบใช้เมื่อไม่ระบุเจาะจง</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($matrix['providers'] as $p)
        <label class="provider-radio relative block p-4 rounded-xl border-2 border-slate-200 dark:border-white/[0.06] hover:border-indigo-300 dark:hover:border-indigo-500/40 bg-white dark:bg-slate-800/50">
          <input type="radio" name="default_provider" value="{{ $p['key'] }}"
                 {{ \App\Models\AppSetting::get('blog_ai_default_provider', 'openai') === $p['key'] ? 'checked' : '' }}
                 {{ !$p['usable'] ? 'disabled' : '' }}
                 @change="markChanged()">

          {{-- Check mark (top right) --}}
          <span class="check-mark absolute top-2 right-2 w-5 h-5 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[10px]">
            <i class="bi bi-check-lg"></i>
          </span>

          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 text-xl
                        bg-gradient-to-br from-indigo-500 via-violet-500 to-pink-500 text-white shadow">
              {{ $p['meta']['icon'] }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-sm text-slate-800 dark:text-slate-100">{{ $p['meta']['label'] }}</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                @if(!$p['usable'])
                  <span class="text-rose-500"><i class="bi bi-x-circle-fill"></i> ปิดใช้งาน</span>
                @else
                  <span class="text-emerald-600 dark:text-emerald-400"><i class="bi bi-check-circle-fill"></i> พร้อมใช้</span>
                @endif
              </div>
            </div>
          </div>
        </label>
        @endforeach
      </div>
    </div>

    {{-- ── STICKY SUBMIT BAR ──────────────────────────────────────── --}}
    <div class="fixed bottom-0 left-0 right-0 lg:left-[260px] lg:[.lg\:ml-\[72px\]_&]:left-[72px] z-30 transition-all"
         :class="hasChanges ? 'translate-y-0' : 'translate-y-full lg:translate-y-0'">
      <div class="bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl border-t border-slate-200/60 dark:border-white/[0.06] shadow-[0_-8px_24px_-12px_rgba(0,0,0,0.15)]">
        <div class="max-w-full px-4 lg:px-6 py-3 flex items-center justify-between gap-3 flex-wrap">

          <div class="text-xs text-slate-600 dark:text-slate-400 flex items-center gap-2">
            <span x-show="hasChanges" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 font-semibold has-changes">
              <i class="bi bi-exclamation-circle-fill"></i> มีการเปลี่ยนแปลง
            </span>
            <span x-show="!hasChanges" x-cloak class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">
              <i class="bi bi-check-circle"></i> ไม่มีการเปลี่ยนแปลง
            </span>
          </div>

          <div class="flex items-center gap-2">
            <a href="{{ route('admin.blog.ai.index') }}"
               class="px-4 py-2 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300
                      hover:bg-slate-100 dark:hover:bg-slate-700/50 transition">
              ยกเลิก
            </a>
            <button type="submit"
                    :disabled="!hasChanges"
                    :class="hasChanges
                      ? 'bg-gradient-to-br from-indigo-500 via-violet-500 to-pink-500 text-white shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:-translate-y-0.5'
                      : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-500 cursor-not-allowed'"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold transition-all duration-200">
              <i class="bi bi-check2"></i>
              <span x-text="hasChanges ? 'บันทึกการเปลี่ยนแปลง' : 'บันทึกแล้ว'"></span>
            </button>
          </div>
        </div>
      </div>
    </div>

  </form>
</div>

@push('scripts')
<script>
function aiToggles(initial) {
  return {
    master: initial.master,
    tools: initial.tools,
    providers: initial.providers,
    defaultProvider: initial.default_provider,
    hasChanges: false,

    get totalToolsCount() { return Object.keys(this.tools).length; },
    get enabledToolsCount() {
      return Object.values(this.tools).filter(v => v).length;
    },
    get totalProvidersCount() { return Object.keys(this.providers).length; },
    get enabledProvidersCount() {
      return Object.values(this.providers).filter(v => v).length;
    },

    markChanged() { this.hasChanges = true; },

    setAllTools(enable) {
      Object.keys(this.tools).forEach(k => this.tools[k] = enable);
    },
  };
}
</script>
@endpush
@endsection
