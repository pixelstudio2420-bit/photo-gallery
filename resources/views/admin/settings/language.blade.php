@extends('layouts.admin')

@section('title', 'ตั้งค่าภาษา (Language Settings)')

@section('content')
@php
  $allLanguages = [
    'th' => ['label' => 'Thai',    'flag' => '🇹🇭', 'native' => 'ไทย',    'desc' => 'ภาษาหลักของระบบ', 'coverage' => '100%'],
    'en' => ['label' => 'English', 'flag' => '🇺🇸', 'native' => 'English', 'desc' => 'ภาษาสากล',        'coverage' => '95%'],
    'zh' => ['label' => 'Chinese', 'flag' => '🇨🇳', 'native' => '中文',    'desc' => 'ภาษาจีนกลาง',     'coverage' => '85%'],
  ];
  $defaultLang    = $settings['default_language']  ?? 'th';
  $multilangOn    = ($settings['multilang_enabled'] ?? '1') === '1';
  $selectedLangs  = $enabledLangs ?? array_keys($allLanguages);
@endphp

<div x-data="{
      enabled: {{ $multilangOn ? 'true' : 'false' }},
      selectedLangs: @js($selectedLangs),
      defaultLang: @js($defaultLang),
      toggle(lang) {
        if (lang === this.defaultLang) return; // cannot remove default
        if (this.selectedLangs.includes(lang)) {
          this.selectedLangs = this.selectedLangs.filter(l => l !== lang);
        } else {
          this.selectedLangs.push(lang);
        }
      },
      makeDefault(lang) {
        this.defaultLang = lang;
        if (!this.selectedLangs.includes(lang)) this.selectedLangs.push(lang);
      }
    }" class="max-w-6xl mx-auto pb-16">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6">
    <div>
      <h4 class="text-2xl font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
          <i class="bi bi-translate"></i>
        </span>
        ตั้งค่าระบบภาษา
      </h4>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">เปิด/ปิดระบบหลายภาษา เลือกภาษาที่ใช้งาน และกำหนดภาษาเริ่มต้น</p>
    </div>
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 text-sm px-4 py-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 transition">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
  </div>

  @if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-800 dark:text-emerald-300 flex items-start gap-2">
      <i class="bi bi-check-circle-fill mt-0.5"></i>
      <div class="flex-1 text-sm">{{ session('success') }}</div>
      <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">
        <i class="bi bi-x-lg text-xs"></i>
      </button>
    </div>
  @endif

  @if(session('error'))
    <div class="mb-4 px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 flex items-start gap-2">
      <i class="bi bi-exclamation-circle-fill mt-0.5"></i>
      <div class="flex-1 text-sm">{{ session('error') }}</div>
      <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700">
        <i class="bi bi-x-lg text-xs"></i>
      </button>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.language.update') }}" id="langForm">
    @csrf

    {{-- ═══════════════════════════════════════════════════════════════
         MASTER TOGGLE — big, obvious, gradient changes with state
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="rounded-2xl p-6 mb-6 shadow-lg transition-all duration-300 border"
         :class="enabled
            ? 'bg-gradient-to-br from-emerald-500 via-teal-500 to-green-500 border-emerald-400 text-white'
            : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300'">
      <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-start gap-4">
          <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl transition-all"
               :class="enabled ? 'bg-white/20 shadow-inner' : 'bg-slate-200 dark:bg-white/5'">
            <i class="bi" :class="enabled ? 'bi-translate' : 'bi-translate opacity-50'"></i>
          </div>
          <div>
            <h5 class="text-xl font-bold mb-1" x-text="enabled ? 'ระบบหลายภาษา: เปิดใช้งาน' : 'ระบบหลายภาษา: ปิดใช้งาน'"></h5>
            <p class="text-sm opacity-90" x-text="enabled
                ? 'ผู้เข้าชมจะเห็นปุ่มเปลี่ยนภาษาและสามารถเลือกภาษาได้'
                : 'ระบบจะใช้ภาษาเริ่มต้นเท่านั้น ซ่อนปุ่มเปลี่ยนภาษา'"></p>
            <p class="text-xs opacity-75 mt-1">
              <i class="bi bi-info-circle mr-1"></i>
              <span x-show="enabled">เลือกภาษาที่จะแสดงให้ผู้เข้าชมได้ที่ด้านล่าง</span>
              <span x-show="!enabled">เปิดเพื่อให้ผู้เข้าชมเลือกภาษาได้</span>
            </p>
          </div>
        </div>
        {{-- Big switch --}}
        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
          <input type="checkbox" name="multilang_enabled" value="1" class="sr-only peer" x-model="enabled">
          <div class="w-16 h-9 rounded-full transition-all duration-300 shadow-inner"
               :class="enabled ? 'bg-white/30' : 'bg-slate-300 dark:bg-slate-700'">
            <div class="absolute top-1 left-1 w-7 h-7 rounded-full shadow-lg transition-all duration-300 flex items-center justify-center"
                 :class="enabled ? 'bg-white translate-x-7 text-emerald-600' : 'bg-white translate-x-0 text-slate-400'">
              <i class="bi text-sm" :class="enabled ? 'bi-check-lg' : 'bi-x-lg'"></i>
            </div>
          </div>
        </label>
      </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         PER-LANGUAGE CARDS (disabled when master is off)
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/5 shadow-sm mb-6 transition-opacity"
         :class="enabled ? 'opacity-100' : 'opacity-60 pointer-events-none'">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-white/5 flex items-center justify-between flex-wrap gap-2">
        <div>
          <h6 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
            <i class="bi bi-globe2 text-indigo-500"></i> ภาษาที่พร้อมให้เลือก
          </h6>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">เลือกภาษาที่ต้องการให้ผู้เข้าชมใช้งานได้ และกำหนดภาษาเริ่มต้น</p>
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400">
          <span x-text="selectedLangs.length"></span> จาก {{ count($allLanguages) }} ภาษา
        </div>
      </div>

      <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          @foreach($allLanguages as $code => $lang)
          <div class="relative rounded-xl border-2 transition-all duration-200 p-4"
               :class="selectedLangs.includes('{{ $code }}')
                  ? (defaultLang === '{{ $code }}'
                      ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-500/10 shadow-md'
                      : 'border-emerald-400 bg-emerald-50/30 dark:bg-emerald-500/5 shadow-sm')
                  : 'border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-slate-900/30'">

            {{-- Default badge --}}
            <div x-show="defaultLang === '{{ $code }}'"
                 class="absolute -top-2 -right-2 px-2 py-0.5 rounded-full bg-indigo-500 text-white text-[10px] font-bold shadow">
              <i class="bi bi-star-fill mr-0.5"></i> ภาษาหลัก
            </div>

            {{-- Flag + names --}}
            <div class="flex items-start justify-between mb-3">
              <div class="flex items-center gap-3">
                <div class="text-4xl">{{ $lang['flag'] }}</div>
                <div>
                  <div class="font-semibold text-slate-900 dark:text-white">{{ $lang['native'] }}</div>
                  <div class="text-xs text-slate-500 dark:text-slate-400">{{ $lang['label'] }}</div>
                </div>
              </div>
              {{-- Per-language toggle (cannot remove the default language) --}}
              <label class="relative inline-flex items-center cursor-pointer flex-shrink-0"
                     :class="defaultLang === '{{ $code }}' ? 'cursor-not-allowed' : ''"
                     :title="defaultLang === '{{ $code }}' ? 'ภาษาหลักจะเปิดใช้งานเสมอ' : ''">
                {{-- Visible clickable area; state tracked in Alpine, real submission via hidden input --}}
                <input type="checkbox"
                       class="sr-only"
                       :checked="selectedLangs.includes('{{ $code }}')"
                       @click.prevent="toggle('{{ $code }}')">
                <div class="w-11 h-6 rounded-full transition-all"
                     :class="selectedLangs.includes('{{ $code }}') ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700'">
                  <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                       :class="selectedLangs.includes('{{ $code }}') ? 'translate-x-5' : 'translate-x-0'"></div>
                </div>
              </label>
              {{-- Real submit value, always current because bound to Alpine state --}}
              <template x-if="selectedLangs.includes('{{ $code }}')">
                <input type="hidden" name="enabled_languages[]" value="{{ $code }}">
              </template>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">{{ $lang['desc'] }}</p>

            {{-- Coverage bar --}}
            <div class="mb-3">
              <div class="flex items-center justify-between text-xs mb-1">
                <span class="text-slate-500 dark:text-slate-400">ความครอบคลุม</span>
                <span class="font-medium text-slate-700 dark:text-slate-300">{{ $lang['coverage'] }}</span>
              </div>
              <div class="w-full h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full" style="width: {{ $lang['coverage'] }};"></div>
              </div>
            </div>

            {{-- "Make default" button --}}
            <button type="button"
                    @click="makeDefault('{{ $code }}')"
                    :disabled="defaultLang === '{{ $code }}'"
                    class="w-full text-xs py-2 rounded-lg font-medium transition-all"
                    :class="defaultLang === '{{ $code }}'
                        ? 'bg-indigo-500/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 cursor-default'
                        : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-indigo-500 hover:text-white dark:hover:bg-indigo-500'">
              <span x-show="defaultLang === '{{ $code }}'"><i class="bi bi-check-circle-fill mr-1"></i> เป็นภาษาหลัก</span>
              <span x-show="defaultLang !== '{{ $code }}'"><i class="bi bi-star mr-1"></i> ตั้งเป็นภาษาหลัก</span>
            </button>
          </div>
          @endforeach
        </div>

        {{-- Hidden input carrying the default language --}}
        <input type="hidden" name="default_language" :value="defaultLang">
      </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         INFO CARDS
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <div class="rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 p-4">
        <div class="flex items-start gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-500 text-white flex items-center justify-center flex-shrink-0">
            <i class="bi bi-info-circle"></i>
          </div>
          <div class="flex-1">
            <h6 class="font-semibold text-blue-900 dark:text-blue-200 mb-1 text-sm">วิธีการทำงาน</h6>
            <ul class="text-xs text-blue-800 dark:text-blue-300/90 space-y-1 list-disc ml-4">
              <li>เมื่อเปิด "ระบบหลายภาษา" ผู้เข้าชมจะเห็นปุ่มเปลี่ยนภาษาในแถบเมนู</li>
              <li>เมื่อปิด ระบบจะใช้ภาษาหลักเพียงภาษาเดียวและซ่อนปุ่มเปลี่ยนภาษา</li>
              <li>ภาษาหลักคือภาษาที่แสดงเป็นค่าเริ่มต้นเมื่อยังไม่ได้เลือก</li>
              <li>การตั้งค่าจะผูกกับ session และ cookie ของผู้ใช้ (1 ปี)</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 p-4">
        <div class="flex items-start gap-3">
          <div class="w-8 h-8 rounded-lg bg-amber-500 text-white flex items-center justify-center flex-shrink-0">
            <i class="bi bi-exclamation-triangle"></i>
          </div>
          <div class="flex-1">
            <h6 class="font-semibold text-amber-900 dark:text-amber-200 mb-1 text-sm">คำเตือน</h6>
            <ul class="text-xs text-amber-800 dark:text-amber-300/90 space-y-1 list-disc ml-4">
              <li>การปิดระบบจะทำให้ผู้เข้าชมที่กำลังใช้ภาษาอื่นถูกเปลี่ยนเป็นภาษาหลัก</li>
              <li>ต้องมีภาษาหลักอย่างน้อย 1 ภาษาเสมอ ไม่สามารถปิดภาษาหลักได้</li>
              <li>หากบางภาษามีความครอบคลุม < 100% อาจเห็นข้อความภาษาอังกฤษปะปน</li>
              <li>หลังบันทึก ล้าง cache ของเบราว์เซอร์เพื่อให้เห็นผลทันที</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         STICKY SUBMIT BAR
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="sticky bottom-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl shadow-lg p-4 flex items-center justify-between gap-3 flex-wrap z-10">
      <div class="text-sm text-slate-600 dark:text-slate-300">
        <span x-show="enabled" class="flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          พร้อมบันทึก · เปิดระบบหลายภาษา (<span x-text="selectedLangs.length"></span> ภาษา)
        </span>
        <span x-show="!enabled" class="flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-slate-400"></span>
          พร้อมบันทึก · ปิดระบบหลายภาษา
        </span>
      </div>
      <div class="flex gap-2">
        <a href="{{ route('admin.settings.index') }}"
           class="px-4 py-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 text-sm transition">
          ยกเลิก
        </a>
        <button type="submit"
                class="px-5 py-2 rounded-lg bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-medium shadow-md transition-all text-sm">
          <i class="bi bi-save mr-1"></i> บันทึกการตั้งค่า
        </button>
      </div>
    </div>
  </form>
</div>
@endsection
