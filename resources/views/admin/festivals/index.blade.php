@extends('layouts.admin')

@section('title', 'เทศกาล / Festival popups')

@section('content')
<div x-data="{ openId: null, themePreviewId: null, showCreate: false, confirmDeleteId: null }">

  {{-- ═══════════════════════════════════════════════════════════════
       HEADER — matches the products/digital-orders admin design
       (icon-on-left, title + subtitle stack, no breadcrumb because
       sidebar already shows location)
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
    <div class="flex items-center gap-3">
      <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-pink-500 to-orange-500 text-white flex items-center justify-center shadow-md shadow-pink-500/30">
        <i class="bi bi-calendar-heart text-xl"></i>
      </div>
      <div>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">เทศกาล / Festival popups</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400">Popup ตามเทศกาลพร้อมธีมสี — สงกรานต์, ลอยกระทง, ปีใหม่ และอื่นๆ</p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      {{-- Sync from canonical calendar — re-applies authoritative dates
           from the multi-year table (covers fixed-date + lunar festivals).
           Confirms first because it overwrites starts_at/ends_at on the
           9 canonical festivals. --}}
      <form method="POST" action="{{ route('admin.festivals.sync') }}" class="contents"
            onsubmit="return confirm('ซิงค์วันที่เทศกาลกับปฏิทิน 9 ตัวหลัก (สงกรานต์, ลอยกระทง, ปีใหม่, ฯลฯ)?\n\nระบบจะอัปเดต starts_at/ends_at ให้เป็นปีหน้าโดยอัตโนมัติ — admin edit ของเนื้อหา/ธีม/ปุ่ม จะไม่ถูกแตะ')">
        @csrf
        <button type="submit"
                title="ซิงค์ปฏิทิน — อัปเดตวันที่เทศกาลตามปฏิทินจริง (เทศกาลที่ admin สร้างเองจะไม่ถูกแตะ)"
                class="inline-flex items-center gap-2 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 px-3 py-2 text-sm font-medium transition">
          <i class="bi bi-calendar-check"></i>
          <span class="hidden sm:inline">ซิงค์ปฏิทิน</span>
        </button>
      </form>

      <button type="button" @click="showCreate = !showCreate; if(showCreate) $nextTick(() => document.getElementById('create-festival-form')?.scrollIntoView({behavior:'smooth', block:'start'}))"
              class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-pink-500 to-orange-500 hover:from-pink-600 hover:to-orange-600 text-white px-4 py-2 text-sm font-medium shadow-sm shadow-pink-500/25 transition">
        <i class="bi" :class="showCreate ? 'bi-x-lg' : 'bi-plus-lg'"></i>
        <span x-text="showCreate ? 'ยกเลิก' : 'เพิ่มเทศกาล'"></span>
      </button>
    </div>
  </div>

  {{-- Flash messages --}}
  @if(session('success'))
  <div class="mb-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm flex items-start justify-between gap-3">
    <div class="flex items-start gap-2"><i class="bi bi-check-circle-fill mt-0.5"></i><span>{{ session('success') }}</span></div>
    <button type="button" class="text-emerald-600/80 hover:text-emerald-700" onclick="this.parentElement.remove()"><i class="bi bi-x-lg"></i></button>
  </div>
  @endif
  @if(session('error'))
  <div class="mb-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 px-4 py-3 text-sm flex items-start justify-between gap-3">
    <div class="flex items-start gap-2"><i class="bi bi-exclamation-triangle-fill mt-0.5"></i><span>{{ session('error') }}</span></div>
    <button type="button" class="text-rose-600/80 hover:text-rose-700" onclick="this.parentElement.remove()"><i class="bi bi-x-lg"></i></button>
  </div>
  @endif

  {{-- ═══════════════════════════════════════════════════════════════
       STATS — 4 KPI cards, same shape as digital-orders/index
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    {{-- Total --}}
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-collection text-xl text-slate-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-slate-900 dark:text-slate-100">{{ $stats['total'] }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">เทศกาลทั้งหมด</div>
        </div>
      </div>
    </div>
    {{-- Enabled --}}
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-toggle-on text-xl text-emerald-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-emerald-600 dark:text-emerald-400">{{ $stats['enabled'] }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">เปิดใช้งาน</div>
        </div>
      </div>
    </div>
    {{-- Currently live --}}
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm relative overflow-hidden
                {{ $stats['currently_live'] > 0 ? 'ring-2 ring-rose-200 dark:ring-rose-500/30' : '' }}">
      @if($stats['currently_live'] > 0)
        <span class="absolute top-2 right-2 flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
        </span>
      @endif
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-rose-500/10 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-broadcast text-xl text-rose-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-rose-600 dark:text-rose-400">{{ $stats['currently_live'] }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">โชว์ตอนนี้</div>
        </div>
      </div>
    </div>
    {{-- Upcoming --}}
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-indigo-500/10 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-clock-history text-xl text-indigo-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-indigo-600 dark:text-indigo-400">{{ $stats['upcoming_30d'] }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ใน 30 วัน</div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       GOOGLE CALENDAR INTEGRATION — collapsible settings panel
       Lets admin paste API key + test connection. Toggle visible
       always, panel collapsed by default.
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="mb-6" x-data="{
        showPanel: {{ $googleCal['configured'] ? 'false' : 'false' }},
        testing: false,
        result: null,
        async testKey() {
            this.testing = true; this.result = null;
            try {
                const r = await fetch('{{ route('admin.festivals.google-test') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
                this.result = await r.json();
            } catch (e) { this.result = { ok: false, message: 'Network error: ' + e.message }; }
            finally { this.testing = false; }
        }
      }">
    <button type="button" @click="showPanel = !showPanel"
            class="w-full bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm p-4 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-white/5 transition">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 text-white flex items-center justify-center">
          <i class="bi bi-google text-lg"></i>
        </div>
        <div class="text-left">
          <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm flex items-center gap-2">
            Google Calendar Integration
            @if($googleCal['configured'])
              <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 text-[10px] font-bold">
                <i class="bi bi-check-circle-fill"></i> เชื่อมต่อแล้ว
              </span>
            @else
              <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 px-2 py-0.5 text-[10px] font-medium">
                <i class="bi bi-circle"></i> ยังไม่เชื่อมต่อ
              </span>
            @endif
          </h3>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">
            ดึงวันหยุดราชการ + วันสำคัญทางพุทธศาสนา จาก Google Calendar (ฟรี · ไม่ต้อง OAuth)
          </p>
        </div>
      </div>
      <i class="bi text-slate-400 transition-transform" :class="showPanel ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
    </button>

    <div x-show="showPanel" x-cloak x-collapse class="mt-3">
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-blue-200 dark:border-blue-500/30 shadow-sm overflow-hidden">
        <div class="h-1.5 bg-gradient-to-r from-blue-500 to-cyan-500"></div>
        <div class="p-5 space-y-5">

          {{-- About + cost --}}
          <div class="rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 px-4 py-3 text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
            <p class="font-semibold text-blue-700 dark:text-blue-300 mb-1.5">
              <i class="bi bi-info-circle"></i> เกี่ยวกับ Google Calendar Integration
            </p>
            <ul class="space-y-1 list-disc pl-5">
              <li><strong>ฟรี</strong> — Google Calendar API quota = 1,000,000 calls/day, เราใช้แค่ ~12 calls/year</li>
              <li><strong>ไม่ต้อง OAuth</strong> — ปฏิทิน "Thailand Holidays" เป็น public, ใช้แค่ API key</li>
              <li><strong>ครอบคลุม</strong>: สงกรานต์, วันแม่ (พระราชสมภพราชินี), วันพ่อ (พระราชสมภพในหลวง), วันรัฐธรรมนูญ, วันมาฆ-วิสาขะ-อาสาฬหบูชา (จันทรคติ)</li>
              <li><strong>ไม่ครอบคลุม</strong>: ลอยกระทง / ตรุษจีน / Pride / Halloween / Valentine / Christmas → ใช้ตารางใน code</li>
            </ul>
          </div>

          {{-- API Key form --}}
          <form method="POST" action="{{ route('admin.festivals.google-config') }}" class="space-y-3">
            @csrf
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                Google Calendar API Key
                <span class="text-slate-400 font-normal text-[10px]">(เว้นว่างเพื่อปิดใช้)</span>
              </label>
              <div class="flex gap-2">
                <input type="password" name="google_calendar_api_key"
                       value="{{ $googleCal['api_key'] }}"
                       placeholder="AIzaSy..." autocomplete="off" maxlength="200"
                       class="flex-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-blue-500 hover:bg-blue-600 text-white px-4 py-2.5 text-sm font-medium transition">
                  <i class="bi bi-save"></i> บันทึก
                </button>
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                <i class="bi bi-key"></i>
                สร้าง API key ฟรีที่
                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener"
                   class="text-blue-600 dark:text-blue-400 hover:underline">Google Cloud Console</a>
              </p>
            </div>
          </form>

          {{-- ⚠️ Setup guide — explicit step-by-step to avoid common 403 errors
               (especially the "HTTP referrers blocked" trap). --}}
          <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 px-4 py-3 text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
            <p class="font-semibold text-amber-700 dark:text-amber-300 mb-2">
              <i class="bi bi-exclamation-triangle-fill"></i> ขั้นตอนตั้งค่า API Key (อ่านก่อนสร้าง)
            </p>
            <ol class="space-y-2 list-decimal pl-5">
              <li>
                <strong>Cloud Console → APIs & Services → Library</strong>
                — ค้นหา <code class="px-1.5 rounded bg-white dark:bg-white/5">Google Calendar API</code> → กด <strong>Enable</strong>
              </li>
              <li>
                <strong>APIs & Services → Credentials → Create credentials → API key</strong>
              </li>
              <li>
                หลังสร้างแล้ว กดแก้ไข key:
                <ul class="mt-1.5 space-y-1 list-disc pl-5 text-[11px]">
                  <li>
                    <strong class="text-rose-600 dark:text-rose-400">Application restrictions</strong>:
                    เลือก <strong>None</strong> หรือ <strong>IP addresses</strong>
                    <span class="text-slate-500">(ไม่ใช่ "HTTP referrers" — อันนั้นใช้กับ JavaScript เท่านั้น ถ้าเลือกจะ 403 ทันที)</span>
                  </li>
                  <li>
                    <strong>API restrictions</strong>:
                    เลือก <strong>Restrict key</strong> → ติ๊ก <strong>Google Calendar API</strong>
                    <span class="text-slate-500">(จำกัดความเสียหายถ้า key หลุด)</span>
                  </li>
                </ul>
              </li>
              <li>Copy key → paste ในช่องด้านบน → กด บันทึก → กด ทดสอบเชื่อมต่อ</li>
            </ol>
          </div>

          {{-- Test connection --}}
          @if($googleCal['configured'])
          <div class="pt-3 border-t border-slate-200 dark:border-white/10">
            <button type="button" @click="testKey()" :disabled="testing"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border-2 border-blue-300 dark:border-blue-500/40 text-blue-700 dark:text-blue-300 hover:bg-blue-50 px-4 py-2 text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition">
              <i class="bi" :class="testing ? 'bi-arrow-repeat animate-spin' : 'bi-plug'"></i>
              <span x-show="!testing">ทดสอบเชื่อมต่อ Google API</span>
              <span x-show="testing" x-cloak>กำลังทดสอบ...</span>
            </button>

            <div x-show="result" x-cloak x-transition class="mt-3 rounded-xl p-4 text-xs"
                 :class="result?.ok ? 'bg-emerald-50 border border-emerald-200 text-emerald-900' : 'bg-rose-50 border border-rose-200 text-rose-900'">
              <div class="flex items-start gap-2">
                <i class="bi mt-0.5 text-base" :class="result?.ok ? 'bi-check-circle-fill text-emerald-600' : 'bi-x-circle-fill text-rose-600'"></i>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold leading-snug text-sm" x-text="result?.message"></p>

                  {{-- Actionable fix — preformatted so step lists from
                       the service render with newlines preserved. --}}
                  <div x-show="result?.fix" x-cloak class="mt-2 pt-2 border-t border-rose-200/70 leading-relaxed">
                    <p class="font-semibold text-rose-700 mb-1">
                      <i class="bi bi-tools"></i> วิธีแก้:
                    </p>
                    <pre class="font-sans whitespace-pre-wrap text-[11px] text-rose-800" x-text="result?.fix"></pre>
                  </div>
                </div>
              </div>
            </div>
          </div>
          @endif

          {{-- Coverage list --}}
          <div class="pt-3 border-t border-slate-200 dark:border-white/10">
            <p class="text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">
              <i class="bi bi-list-check"></i> เทศกาลที่ใช้ข้อมูลจาก Google เมื่อเปิดใช้:
            </p>
            <div class="flex flex-wrap gap-1.5">
              @foreach($googleCal['covered_slugs'] as $slug)
                <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 px-2 py-0.5 text-[10px] font-mono font-semibold">
                  <i class="bi bi-google"></i> {{ $slug }}
                </span>
              @endforeach
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
              เทศกาลอื่น ๆ (ลอยกระทง, ตรุษจีน, Pride, Halloween, ฯลฯ) ใช้ตารางจันทรคติ/วันคงที่ในระบบ
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       GOOGLE CALENDAR PREVIEW + IMPORT
       Visible only when API key is configured. Lets admin:
         • See ALL holidays Google returns for the next 12 months
         • Filter by status (matched / importable / already-imported)
         • Tick rows to import as new festivals
         • Override theme + emoji per row before importing
       ═══════════════════════════════════════════════════════════════ --}}
  @if($googleCal['configured'])
  <div class="mb-6"
       x-data="googleImport()"
       x-init="loadPreview()">
    <button type="button" @click="showPanel = !showPanel"
            class="w-full bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm p-4 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-white/5 transition">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center">
          <i class="bi bi-cloud-download text-lg"></i>
        </div>
        <div class="text-left">
          <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm flex items-center gap-2">
            ดูข้อมูลจาก Google Calendar
            <span x-show="!loading && holidays.length > 0" x-cloak
                  class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 text-[10px] font-bold">
              <span x-text="holidays.length"></span> รายการ
            </span>
          </h3>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">
            ดูทุกวันสำคัญที่ Google มี + import เป็นเทศกาลใหม่ในเว็บ
          </p>
        </div>
      </div>
      <i class="bi text-slate-400 transition-transform" :class="showPanel ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
    </button>

    <div x-show="showPanel" x-cloak x-collapse class="mt-3">
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-emerald-200 dark:border-emerald-500/30 shadow-sm overflow-hidden">
        <div class="h-1.5 bg-gradient-to-r from-emerald-500 to-teal-500"></div>

        {{-- Loading state --}}
        <div x-show="loading" class="p-10 text-center">
          <div class="inline-flex items-center gap-2 text-sm text-slate-500">
            <i class="bi bi-arrow-repeat animate-spin text-lg"></i>
            กำลังดึงข้อมูลจาก Google...
          </div>
        </div>

        {{-- Error state --}}
        <div x-show="!loading && error" x-cloak class="p-5">
          <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">
            <i class="bi bi-x-circle-fill"></i> <span x-text="error"></span>
          </div>
        </div>

        {{-- Data state --}}
        <div x-show="!loading && !error && holidays.length > 0" x-cloak>
          {{-- Filter + bulk actions row --}}
          <div class="px-5 py-3 border-b border-slate-200 dark:border-white/10 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2">
              <button type="button" @click="filterStatus = 'all'"
                      :class="filterStatus === 'all' ? 'bg-slate-200 text-slate-900' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                      class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium transition">
                ทั้งหมด <span class="text-[10px]" x-text="holidays.length"></span>
              </button>
              <button type="button" @click="filterStatus = 'importable'"
                      :class="filterStatus === 'importable' ? 'bg-emerald-200 text-emerald-900' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                      class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium transition">
                ✚ Import ได้ <span class="text-[10px]" x-text="countByStatus.importable"></span>
              </button>
              <button type="button" @click="filterStatus = 'matched'"
                      :class="filterStatus === 'matched' ? 'bg-blue-200 text-blue-900' : 'bg-blue-50 text-blue-700 hover:bg-blue-100'"
                      class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium transition">
                <i class="bi bi-link-45deg"></i> Match แล้ว <span class="text-[10px]" x-text="countByStatus.matched"></span>
              </button>
              <button type="button" @click="filterStatus = 'already-imported'"
                      :class="filterStatus === 'already-imported' ? 'bg-amber-200 text-amber-900' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'"
                      class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium transition">
                <i class="bi bi-check2"></i> Import แล้ว <span class="text-[10px]" x-text="countByStatus['already-imported']"></span>
              </button>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" @click="selectAllImportable()"
                      class="text-xs text-slate-500 hover:text-slate-700 transition">
                เลือกที่ import ได้ทั้งหมด
              </button>
              <button type="button" @click="loadPreview(true)"
                      title="Refresh from Google"
                      class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition">
                <i class="bi bi-arrow-clockwise"></i>
              </button>
            </div>
          </div>

          {{-- Holiday list --}}
          <div class="max-h-[600px] overflow-y-auto">
            <template x-for="(h, idx) in filteredHolidays" :key="h.start_date + h.name">
              <div class="px-5 py-3 border-b border-slate-100 dark:border-white/5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-white/5 transition">
                {{-- Checkbox (only for importable) --}}
                <div class="w-5 shrink-0">
                  <input type="checkbox" x-show="h.match_status === 'importable'"
                         :checked="selected.has(h.suggested_slug)"
                         @change="toggleSelect(h.suggested_slug)"
                         class="rounded border-slate-300 text-emerald-500 focus:ring-emerald-500">
                </div>

                {{-- Date pill --}}
                <div class="shrink-0 w-20 text-center">
                  <div class="text-[10px] text-slate-500 uppercase font-mono" x-text="formatDateMonth(h.start_date)"></div>
                  <div class="text-base font-bold text-slate-900 dark:text-slate-100" x-text="formatDateDay(h.start_date)"></div>
                  <div class="text-[10px] text-slate-400" x-show="h.start_date !== h.end_date"
                       x-text="'– ' + formatDateDay(h.end_date)"></div>
                </div>

                {{-- Name + meta --}}
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-sm text-slate-900 dark:text-slate-100 truncate" x-text="h.name"></div>
                  <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                    <template x-if="h.match_status === 'matched'">
                      <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 text-blue-700 px-2 py-0.5 font-semibold">
                        <i class="bi bi-link-45deg"></i> ใช้ดึงวันให้ <span x-text="h.matched_slug" class="font-mono"></span>
                      </span>
                    </template>
                    <template x-if="h.match_status === 'already-imported'">
                      <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 font-semibold">
                        <i class="bi bi-check2"></i> Import แล้ว
                      </span>
                    </template>
                    <template x-if="h.match_status === 'importable'">
                      <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 font-semibold">
                        <i class="bi bi-plus-lg"></i> ยังไม่มี — Import ได้
                      </span>
                    </template>
                  </div>
                </div>

                {{-- Theme + emoji selectors (only for importable) --}}
                <template x-if="h.match_status === 'importable'">
                  <div class="flex items-center gap-2 shrink-0">
                    <input type="text" x-model="h.suggested_emoji" maxlength="10"
                           class="w-12 px-1 py-1 rounded-lg border border-slate-200 bg-white text-center text-base focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <select x-model="h.suggested_theme"
                            class="px-2 py-1 rounded-lg border border-slate-200 bg-white text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      <template x-for="t in themes" :key="t.key">
                        <option :value="t.key" x-text="t.label"></option>
                      </template>
                    </select>
                  </div>
                </template>
              </div>
            </template>
          </div>

          {{-- Bottom action bar — fixed when items are selected --}}
          <div x-show="selected.size > 0" x-cloak x-transition
               class="px-5 py-4 bg-emerald-50 dark:bg-emerald-500/10 border-t-2 border-emerald-300 dark:border-emerald-500/30 flex items-center justify-between gap-3 flex-wrap sticky bottom-0">
            <div class="text-sm text-emerald-900 dark:text-emerald-200">
              <strong x-text="selected.size"></strong> รายการเลือกแล้ว
              <span class="text-xs opacity-80">— จะถูก import เป็นเทศกาลใหม่ (ปิดอยู่ — admin ต้องเปิดใช้ทีละตัว)</span>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" @click="selected = new Set()"
                      class="inline-flex items-center gap-1 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-3 py-1.5 text-xs font-medium transition">
                ล้างที่เลือก
              </button>
              <button type="button" @click="importSelected()" :disabled="importing"
                      class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white px-4 py-2 text-sm font-semibold shadow-md disabled:opacity-50 transition">
                <i class="bi" :class="importing ? 'bi-arrow-repeat animate-spin' : 'bi-cloud-download'"></i>
                <span x-show="!importing">Import ที่เลือก (<span x-text="selected.size"></span>)</span>
                <span x-show="importing" x-cloak>กำลัง import...</span>
              </button>
            </div>
          </div>
        </div>

        {{-- Empty data state --}}
        <div x-show="!loading && !error && holidays.length === 0" x-cloak class="p-10 text-center text-sm text-slate-500">
          <i class="bi bi-inbox text-2xl block mb-2 opacity-50"></i>
          ไม่มีข้อมูลจาก Google ในช่วง 12 เดือนถัดไป
        </div>
      </div>
    </div>
  </div>
  @endif

  {{-- ═══════════════════════════════════════════════════════════════
       CREATE NEW FESTIVAL — Alpine-collapsed panel toggled by header
       button. Same field layout as inline edit, just no row data.
       ═══════════════════════════════════════════════════════════════ --}}
  <div x-show="showCreate" x-cloak x-collapse id="create-festival-form" class="mb-6">
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-pink-200 dark:border-pink-500/30 shadow-sm overflow-hidden">
      <div class="h-1.5 bg-gradient-to-r from-pink-500 to-orange-500"></div>
      <div class="p-5">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-pink-500/10 text-pink-600 dark:text-pink-400 flex items-center justify-center">
            <i class="bi bi-plus-lg text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-slate-100">สร้างเทศกาลใหม่</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">สำหรับ promotion พิเศษหรือเทศกาลเฉพาะที่ — ระบบ canonical seeded แล้วแก้ไขในกรุปด้านล่าง</p>
          </div>
        </div>

        <form method="POST" action="{{ route('admin.festivals.store') }}" class="space-y-4">
          @csrf

          {{-- Name + slug --}}
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">หัวข้อเทศกาล <span class="text-rose-500">*</span></label>
              <input type="text" name="name" value="{{ old('name') }}" required maxlength="200" placeholder="เช่น สงกรานต์ Special 2027"
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
              @error('name')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ชื่อย่อ</label>
              <input type="text" name="short_name" value="{{ old('short_name') }}" maxlength="80" placeholder="เช่น สงกรานต์"
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
            </div>
          </div>

          {{-- Slug + emoji + theme + priority --}}
          <div class="grid grid-cols-2 md:grid-cols-12 gap-3">
            <div class="md:col-span-4">
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                Slug <span class="text-slate-400 font-normal text-[10px]">(เว้นว่างเพื่อ auto-generate)</span>
              </label>
              <input type="text" name="slug" value="{{ old('slug') }}" maxlength="80" pattern="[a-z0-9\-]+" placeholder="songkran-2027-special"
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition font-mono">
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Emoji</label>
              <input type="text" name="emoji" value="{{ old('emoji', '🎉') }}" maxlength="30"
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-center text-lg focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
            </div>
            <div class="md:col-span-4">
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ธีมสี <span class="text-rose-500">*</span></label>
              <select name="theme_variant" required
                      class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
                @foreach($themes as $key => $themeOpt)
                  <option value="{{ $key }}" @selected(old('theme_variant', 'water-blue') === $key)>{{ $themeOpt['label'] }}</option>
                @endforeach
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Priority</label>
              <input type="number" name="show_priority" value="{{ old('show_priority', 30) }}" min="0" max="255" required
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
            </div>
          </div>

          {{-- Dates --}}
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">เริ่มเทศกาล <span class="text-rose-500">*</span></label>
              <input type="date" name="starts_at" value="{{ old('starts_at') }}" required
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">จบเทศกาล <span class="text-rose-500">*</span></label>
              <input type="date" name="ends_at" value="{{ old('ends_at') }}" required
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">โชว์ popup ล่วงหน้า (วัน) <span class="text-rose-500">*</span></label>
              <input type="number" name="popup_lead_days" value="{{ old('popup_lead_days', 7) }}" min="0" max="90" required
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
            </div>
          </div>

          {{-- Headline --}}
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">หัวข้อโชว์ใน popup <span class="text-rose-500">*</span></label>
            <input type="text" name="headline" value="{{ old('headline') }}" required maxlength="250" placeholder="เช่น 🎉 ตรุษจีน 2027 — รับอั่งเปาภาพถ่ายฟรี!"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
          </div>

          {{-- Body --}}
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
              เนื้อหา <span class="text-slate-400 font-normal text-[10px]">(Markdown)</span>
            </label>
            <textarea name="body_md" rows="3" maxlength="5000" placeholder="**เทศกาล**...&#10;&#10;- รายละเอียด 1&#10;- รายละเอียด 2"
                      class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition font-mono">{{ old('body_md') }}</textarea>
          </div>

          {{-- CTA + targeting --}}
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ปุ่ม CTA</label>
              <input type="text" name="cta_label" value="{{ old('cta_label') }}" maxlength="80" placeholder="เช่น ดูรายละเอียด"
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ลิงก์ปุ่ม</label>
              <input type="text" name="cta_url" value="{{ old('cta_url') }}" maxlength="500" placeholder="/events?tag=..."
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
            </div>
          </div>

          {{-- Targeting + flags --}}
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                <i class="bi bi-geo-alt"></i> จำกัดเฉพาะจังหวัด <span class="text-slate-400 font-normal text-[10px]">(เว้นว่าง = ทั่วประเทศ)</span>
              </label>
              @include('partials.province-select', [
                  'name' => 'target_province_id', 'selected' => old('target_province_id'),
                  'placeholder' => '— ทั่วประเทศ —',
              ])
            </div>
            <div class="flex items-end gap-2">
              <label class="flex-1 flex items-center gap-2 cursor-pointer px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 transition">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" {{ old('enabled', '1') ? 'checked' : '' }}
                       class="rounded border-slate-300 text-emerald-500 focus:ring-emerald-500">
                <span class="text-xs font-medium text-slate-700 dark:text-slate-300">เปิดใช้</span>
              </label>
              <label class="flex-1 flex items-center gap-2 cursor-pointer px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 transition">
                <input type="hidden" name="is_recurring" value="0">
                <input type="checkbox" name="is_recurring" value="1" {{ old('is_recurring') ? 'checked' : '' }}
                       class="rounded border-slate-300 text-indigo-500 focus:ring-indigo-500">
                <span class="text-xs font-medium text-slate-700 dark:text-slate-300">รายปี</span>
              </label>
            </div>
          </div>

          {{-- Submit row --}}
          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-white/10">
            <button type="button" @click="showCreate = false"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 px-4 py-2 text-sm font-medium transition">
              ยกเลิก
            </button>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-pink-500 to-orange-500 hover:from-pink-600 hover:to-orange-600 text-white px-4 py-2 text-sm font-medium shadow-sm shadow-pink-500/25 transition">
              <i class="bi bi-plus-lg"></i> สร้างเทศกาล
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       FESTIVAL CARDS
       Grid of cards. Each card has:
         • Theme color strip (top edge) — instant visual sort by mood
         • Big emoji + name + status pill row
         • Headline preview
         • Date range + popup window meta
         • Action toolbar (toggle / bump-year / edit / duplicate / delete)
         • Inline edit form (Alpine collapse)
       ═══════════════════════════════════════════════════════════════ --}}

  @if($festivals->isEmpty())
    <div class="rounded-2xl bg-white dark:bg-slate-900 border-2 border-dashed border-slate-200 dark:border-white/10 p-10 text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-white/5 mb-3">
        <i class="bi bi-calendar-x text-3xl text-slate-400"></i>
      </div>
      <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-1">ยังไม่มีเทศกาลในระบบ</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400">
        ระบบจะ seed อัตโนมัติเมื่อ deploy หรือรัน
        <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/5 text-[11px]">php artisan db:seed --class=FestivalsSeeder</code>
      </p>
    </div>
  @else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      @foreach($festivals as $f)
        @php
          $theme       = \App\Services\FestivalThemeService::theme($f->theme_variant);
          $now         = now()->startOfDay();
          $popupStart  = $f->starts_at?->copy()->subDays($f->popup_lead_days);
          $isLiveNow   = $f->enabled && $popupStart && $now->between($popupStart, $f->ends_at);
          $isPast      = $f->ends_at->lt($now);
          $isUpcoming  = $popupStart && $popupStart->gt($now);
          $daysToShow  = $isUpcoming ? $now->diffInDays($popupStart) : null;
          $daysLeftEnd = $isLiveNow ? $now->diffInDays($f->ends_at) : null;
        @endphp

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden
                    {{ $isLiveNow ? 'ring-2 ring-offset-2 ring-rose-200 dark:ring-rose-500/30 dark:ring-offset-slate-950' : '' }}
                    {{ !$f->enabled ? 'opacity-70' : '' }}
                    transition-all">

          {{-- Theme color strip — instantly conveys the festival mood --}}
          <div class="h-1.5" style="background: {{ $theme['gradient_css'] }};"></div>

          {{-- Header row --}}
          <div class="p-5">
            <div class="flex items-start gap-3 mb-3">
              {{-- Emoji avatar with theme gradient bg --}}
              <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl shrink-0 shadow-sm"
                   style="background: {{ $theme['gradient_css'] }};">
                <span style="filter: drop-shadow(0 1px 2px rgba(0,0,0,0.2));">{{ $f->emoji ?: '✨' }}</span>
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                  <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-base leading-tight truncate">
                    {{ $f->name }}
                  </h3>
                  {{-- Status pill --}}
                  @if(!$f->enabled)
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 px-2 py-0.5 text-[10px] font-semibold">
                      <i class="bi bi-pause-fill"></i> ปิดอยู่
                    </span>
                  @elseif($isLiveNow)
                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 px-2 py-0.5 text-[10px] font-semibold">
                      <span class="relative flex h-1.5 w-1.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-rose-500"></span>
                      </span>
                      LIVE
                    </span>
                  @elseif($isPast)
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 px-2 py-0.5 text-[10px] font-semibold">
                      <i class="bi bi-hourglass-bottom"></i> ผ่านมาแล้ว
                    </span>
                  @elseif($isUpcoming)
                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 text-[10px] font-semibold">
                      <i class="bi bi-clock"></i>
                      {{ $daysToShow }} วันถึงโชว์
                    </span>
                  @endif

                  {{-- Theme chip --}}
                  <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 dark:bg-white/5 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-white/10 px-2 py-0.5 text-[10px] font-medium">
                    🎨 {{ $theme['label'] }}
                  </span>

                  {{-- Date source badge — tells admin where this festival's
                       dates came from (Google API / internal table /
                       admin manual edit). --}}
                  @php $src = $f->date_source ?? 'internal'; @endphp
                  @if($src === 'google')
                    <span title="วันที่นี้ดึงจาก Google Calendar API"
                          class="inline-flex items-center gap-1 rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 px-2 py-0.5 text-[10px] font-semibold">
                      <i class="bi bi-google"></i> Google
                    </span>
                  @elseif($src === 'manual')
                    <span title="แอดมินตั้งวันที่เอง — sync จะไม่แตะ"
                          class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 px-2 py-0.5 text-[10px] font-semibold">
                      <i class="bi bi-pencil-fill"></i> ตั้งเอง
                    </span>
                  @else
                    <span title="วันที่ใช้ตาราง/logic ในระบบ (lunar table หรือ fixed-date helper)"
                          class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 px-2 py-0.5 text-[10px] font-medium">
                      <i class="bi bi-cpu"></i> ตารางในระบบ
                    </span>
                  @endif
                </div>

                {{-- Headline preview --}}
                <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-snug">
                  {{ $f->headline }}
                </p>
              </div>
            </div>

            {{-- Meta row: dates + priority --}}
            <div class="grid grid-cols-2 gap-2 mb-4 text-[11px]">
              <div class="rounded-xl bg-slate-50 dark:bg-white/5 px-3 py-2">
                <div class="text-slate-400 dark:text-slate-500 mb-0.5"><i class="bi bi-calendar-event"></i> เทศกาล</div>
                <div class="font-semibold text-slate-900 dark:text-slate-100 text-xs">
                  {{ $f->starts_at->format('d M Y') }}
                  @if(!$f->starts_at->isSameDay($f->ends_at))
                    <span class="text-slate-400">→</span>
                    {{ $f->ends_at->format('d M Y') }}
                  @endif
                </div>
              </div>
              <div class="rounded-xl bg-slate-50 dark:bg-white/5 px-3 py-2">
                <div class="text-slate-400 dark:text-slate-500 mb-0.5"><i class="bi bi-megaphone"></i> Popup เริ่ม</div>
                <div class="font-semibold text-slate-900 dark:text-slate-100 text-xs">
                  {{ $popupStart->format('d M Y') }}
                  <span class="text-slate-400 font-normal">({{ $f->popup_lead_days }} วันก่อน)</span>
                </div>
              </div>
            </div>

            {{-- Live extra info: days remaining --}}
            @if($isLiveNow && $daysLeftEnd >= 0)
            <div class="mb-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 px-3 py-2 text-xs text-rose-800 dark:text-rose-200">
              <i class="bi bi-fire"></i>
              กำลังโชว์อยู่ —
              @if($daysLeftEnd === 0)
                <strong>วันสุดท้าย</strong>
              @else
                เหลืออีก <strong>{{ $daysLeftEnd }}</strong> วันก่อนปิด
              @endif
            </div>
            @endif

            {{-- Priority + targeting indicator --}}
            <div class="flex items-center gap-2 flex-wrap text-[11px] text-slate-500 dark:text-slate-400 mb-4">
              <span class="inline-flex items-center gap-1">
                <i class="bi bi-arrow-up-right-circle"></i>
                Priority <strong class="text-slate-700 dark:text-slate-200 font-mono">{{ $f->show_priority }}</strong>
              </span>
              @if($f->target_province_id)
                <span class="text-slate-300">·</span>
                <span class="inline-flex items-center gap-1">
                  <i class="bi bi-geo-alt-fill text-amber-500"></i>
                  เฉพาะ
                  <strong class="text-slate-700 dark:text-slate-200">
                    {{ optional(\DB::table('thai_provinces')->find($f->target_province_id))->name_th ?? '#' . $f->target_province_id }}
                  </strong>
                </span>
              @else
                <span class="text-slate-300">·</span>
                <span class="inline-flex items-center gap-1">
                  <i class="bi bi-globe2 text-emerald-500"></i>
                  ทั่วประเทศ
                </span>
              @endif
              @if($f->is_recurring)
                <span class="text-slate-300">·</span>
                <span class="inline-flex items-center gap-1">
                  <i class="bi bi-arrow-repeat"></i> รายปี
                </span>
              @endif
            </div>

            {{-- Action toolbar --}}
            <div class="flex items-center gap-2 flex-wrap">
              {{-- Toggle on/off --}}
              <form method="POST" action="{{ route('admin.festivals.toggle', $f->id) }}" class="contents">
                @csrf
                <button type="submit"
                        title="{{ $f->enabled ? 'กดเพื่อปิด popup' : 'กดเพื่อเปิด popup' }}"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition
                               {{ $f->enabled
                                   ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200'
                                   : 'bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 hover:bg-slate-200' }}">
                  <i class="bi {{ $f->enabled ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                  {{ $f->enabled ? 'เปิดอยู่' : 'ปิดอยู่' }}
                </button>
              </form>

              {{-- Bump year for past + recurring only --}}
              @if($isPast && $f->is_recurring)
              <form method="POST" action="{{ route('admin.festivals.bump-year', $f->id) }}" class="contents"
                    onsubmit="return confirm('ปรับวันที่ของ &quot;{{ $f->short_name ?: $f->name }}&quot; เป็นปีถัดไปเลยไหม?')">
                @csrf
                <button type="submit"
                        title="ปรับวันที่เป็นปีถัดไปทันที"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 px-3 py-1.5 text-xs font-medium transition">
                  <i class="bi bi-arrow-clockwise"></i> ปรับเป็นปีหน้า
                </button>
              </form>
              @endif

              {{-- Right cluster: Duplicate / Delete / Edit --}}
              <div class="ml-auto flex items-center gap-2">
                {{-- Duplicate — clones into a disabled draft --}}
                <form method="POST" action="{{ route('admin.festivals.duplicate', $f->id) }}" class="contents"
                      onsubmit="return confirm('ทำสำเนา &quot;{{ $f->short_name ?: $f->name }}&quot; เป็นเทศกาลใหม่ไหม? (จะถูกตั้งเป็นปิดอยู่)')">
                  @csrf
                  <button type="submit"
                          title="ทำสำเนาเทศกาล (เพื่อแก้แล้วใช้ใหม่)"
                          class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-white/10 px-2.5 py-1.5 text-xs font-medium transition">
                    <i class="bi bi-files"></i>
                  </button>
                </form>

                {{-- Delete — confirmation prompt then DELETE form --}}
                <button type="button"
                        @click="confirmDeleteId = {{ $f->id }}"
                        title="ลบเทศกาลนี้"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-rose-200 dark:border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 px-2.5 py-1.5 text-xs font-medium transition">
                  <i class="bi bi-trash"></i>
                </button>

                {{-- Edit toggle --}}
                <button type="button"
                        @click="openId = openId === {{ $f->id }} ? null : {{ $f->id }}"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/10 px-3 py-1.5 text-xs font-medium transition">
                  <i class="bi" :class="openId === {{ $f->id }} ? 'bi-x-lg' : 'bi-pencil'"></i>
                  <span x-text="openId === {{ $f->id }} ? 'ปิด' : 'แก้ไข'"></span>
                </button>
              </div>
            </div>

            {{-- Inline delete confirmation panel — opens within the
                 card to avoid a full-screen modal that blocks the rest
                 of the list. Shows the festival name + final guard. --}}
            <div x-show="confirmDeleteId === {{ $f->id }}" x-cloak x-collapse class="mt-3">
              <div class="rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
                <div class="text-xs text-rose-800 dark:text-rose-200 leading-snug">
                  <strong><i class="bi bi-exclamation-triangle-fill"></i> ยืนยันลบ "{{ $f->name }}"?</strong>
                  <div class="opacity-90 mt-0.5">การลบจะ soft-delete (ข้อมูลยังอยู่ใน DB เพื่อ audit แต่จะหายจาก popup ทันที)</div>
                </div>
                <div class="flex gap-2">
                  <button type="button" @click="confirmDeleteId = null"
                          class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-3 py-1.5 text-xs font-medium transition">
                    ยกเลิก
                  </button>
                  <form method="POST" action="{{ route('admin.festivals.destroy', $f->id) }}" class="contents">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 text-xs font-medium shadow-sm transition">
                      <i class="bi bi-trash"></i> ลบเลย
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>

          {{-- ─── Inline edit form ─── --}}
          <div x-show="openId === {{ $f->id }}" x-cloak x-collapse>
            <div class="border-t border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02] p-5">
              <form method="POST" action="{{ route('admin.festivals.update', $f->id) }}" class="space-y-4">
                @csrf @method('PUT')

                {{-- Name + short --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">หัวข้อเทศกาล</label>
                    <input type="text" name="name" value="{{ old('name', $f->name) }}" required maxlength="200"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ชื่อย่อ</label>
                    <input type="text" name="short_name" value="{{ old('short_name', $f->short_name) }}" maxlength="80"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                </div>

                {{-- Emoji + theme + priority + enabled --}}
                <div class="grid grid-cols-2 md:grid-cols-12 gap-3">
                  <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Emoji</label>
                    <input type="text" name="emoji" value="{{ old('emoji', $f->emoji) }}" maxlength="30"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-center text-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div class="md:col-span-6">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ธีมสี</label>
                    <select name="theme_variant"
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                      @foreach($themes as $key => $themeOpt)
                        <option value="{{ $key }}" @selected($f->theme_variant === $key)>{{ $themeOpt['label'] }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Priority</label>
                    <input type="number" name="show_priority" value="{{ old('show_priority', $f->show_priority) }}" min="0" max="255"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div class="md:col-span-2 flex items-end">
                    <label class="flex items-center gap-2 cursor-pointer w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-white/5 transition">
                      <input type="hidden" name="enabled" value="0">
                      <input type="checkbox" name="enabled" value="1" {{ $f->enabled ? 'checked' : '' }}
                             class="rounded border-slate-300 text-emerald-500 focus:ring-emerald-500">
                      <span class="text-xs font-medium text-slate-700 dark:text-slate-300">ใช้งาน</span>
                    </label>
                  </div>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">เริ่มเทศกาล</label>
                    <input type="date" name="starts_at" value="{{ old('starts_at', $f->starts_at->format('Y-m-d')) }}" required
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">จบเทศกาล</label>
                    <input type="date" name="ends_at" value="{{ old('ends_at', $f->ends_at->format('Y-m-d')) }}" required
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">โชว์ popup ล่วงหน้า (วัน)</label>
                    <input type="number" name="popup_lead_days" value="{{ old('popup_lead_days', $f->popup_lead_days) }}" min="0" max="90" required
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                </div>

                {{-- Headline (popup) --}}
                <div>
                  <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">หัวข้อโชว์ใน popup</label>
                  <input type="text" name="headline" value="{{ old('headline', $f->headline) }}" required maxlength="250"
                         class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>

                {{-- Body markdown --}}
                <div>
                  <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    เนื้อหา <span class="text-slate-400 text-[10px]">(รองรับ Markdown — **bold**, *italic*, list)</span>
                  </label>
                  <textarea name="body_md" rows="4" maxlength="5000"
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition font-mono">{{ old('body_md', $f->body_md) }}</textarea>
                </div>

                {{-- CTA --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ปุ่ม CTA</label>
                    <input type="text" name="cta_label" value="{{ old('cta_label', $f->cta_label) }}" maxlength="80" placeholder="เช่น ดูช่างภาพ"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ลิงก์ปุ่ม</label>
                    <input type="text" name="cta_url" value="{{ old('cta_url', $f->cta_url) }}" maxlength="500" placeholder="/events?tag=..."
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                </div>

                {{-- Live preview hint card --}}
                <div class="rounded-xl bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-500/10 dark:to-purple-500/10 border border-indigo-200 dark:border-indigo-500/20 px-4 py-3 text-xs text-slate-700 dark:text-slate-300">
                  <i class="bi bi-eye text-indigo-500"></i>
                  <strong>เคล็ดลับ</strong>: ลูกค้าจะเห็น popup ตั้งแต่
                  <strong>{{ $popupStart->format('d M Y') }}</strong>
                  จนถึง <strong>{{ $f->ends_at->format('d M Y') }}</strong>
                  — popup จะแสดง 1 ครั้งต่อ user (กดปิดแล้วไม่กลับมาอีก)
                </div>

                {{-- Form actions --}}
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-white/10">
                  <button type="button" @click="openId = null"
                          class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/10 px-4 py-2 text-sm font-medium transition">
                    ยกเลิก
                  </button>
                  <button type="submit"
                          class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white px-4 py-2 text-sm font-medium shadow-sm shadow-indigo-500/25 transition">
                    <i class="bi bi-check-lg"></i> บันทึกการเปลี่ยนแปลง
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- Footer help card --}}
  <div class="mt-6 rounded-2xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-4 text-xs text-slate-600 dark:text-slate-400">
    <div class="flex items-start gap-3">
      <i class="bi bi-lightbulb text-amber-500 text-base mt-0.5"></i>
      <div class="flex-1 leading-relaxed">
        <p class="font-medium text-slate-700 dark:text-slate-300 mb-1">เกี่ยวกับระบบ Festival Popup</p>
        <ul class="space-y-1 list-disc pl-4">
          <li>Popup จะ pop ขึ้นที่หน้าเว็บลูกค้าหลัง 8 วินาที (announcement popup ขึ้นก่อน 6 วิ)</li>
          <li>กด "ปิด · ไม่แสดงอีก" หรือคลิก CTA = บันทึกใน DB ไม่กลับมาแสดงอีก</li>
          <li>เทศกาลจะหายจาก popup queue เมื่อเลย <code class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-white/10 text-[11px]">ends_at</code></li>
          <li>ตั้ง <strong>Priority</strong> สูงเพื่อให้ขึ้นก่อนเทศกาลอื่น (ถ้ามีเทศกาล active หลายตัวพร้อมกัน)</li>
          <li><strong>ปฏิทินอัตโนมัติ</strong>: ระบบ <em>ซิงค์วันที่ทุกวันที่ 1 ของเดือน</em> — ปีใหม่/สงกรานต์/วาเลนไทน์ ฯลฯ จะเด้งไปปีถัดไปเองหลังจบเทศกาล + ลอยกระทง/ตรุษจีนใช้ตารางจันทรคติ 2024-2030</li>
          <li>เพิ่มเทศกาลใหม่ผ่านปุ่ม "+ เพิ่มเทศกาล" — slug ใหม่จะไม่ถูกแตะโดย sync (เฉพาะ 9 ตัว canonical เท่านั้น)</li>
        </ul>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
/* Google Calendar Preview + Import — Alpine factory.
   Holidays returned by /admin/festivals/google-preview have:
     name, start_date, end_date, match_status, matched_slug,
     suggested_slug, suggested_theme, suggested_emoji
   Filter + select + bulk-import flow. */
window.googleImport = function () {
  return {
    showPanel: false,
    loading: true,
    error: null,
    holidays: [],
    themes: [],
    filterStatus: 'all',
    selected: new Set(),
    importing: false,

    async loadPreview(force = false) {
      this.loading = true; this.error = null;
      try {
        const r = await fetch('{{ route('admin.festivals.google-preview') }}', {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        });
        const data = await r.json();
        if (!data.ok) {
          this.error = data.message || 'Unknown error';
          this.holidays = [];
        } else {
          this.holidays = data.holidays || [];
          this.themes   = data.themes   || [];
        }
      } catch (e) {
        this.error = 'Network error: ' + e.message;
        this.holidays = [];
      } finally {
        this.loading = false;
      }
    },

    get filteredHolidays() {
      if (this.filterStatus === 'all') return this.holidays;
      return this.holidays.filter(h => h.match_status === this.filterStatus);
    },

    get countByStatus() {
      const c = { matched: 0, importable: 0, 'already-imported': 0 };
      for (const h of this.holidays) c[h.match_status] = (c[h.match_status] || 0) + 1;
      return c;
    },

    toggleSelect(slug) {
      if (this.selected.has(slug)) this.selected.delete(slug);
      else                          this.selected.add(slug);
      // Trigger Alpine reactivity (Set isn't proxied)
      this.selected = new Set(this.selected);
    },

    selectAllImportable() {
      const newSet = new Set(this.selected);
      for (const h of this.holidays) {
        if (h.match_status === 'importable') newSet.add(h.suggested_slug);
      }
      this.selected = newSet;
    },

    formatDateMonth(iso) {
      const months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
      return months[new Date(iso).getMonth()];
    },
    formatDateDay(iso) {
      return new Date(iso).getDate();
    },

    async importSelected() {
      const items = this.holidays
        .filter(h => h.match_status === 'importable' && this.selected.has(h.suggested_slug))
        .map(h => ({
          name:           h.name,
          start_date:     h.start_date,
          end_date:       h.end_date,
          theme_variant:  h.suggested_theme,
          emoji:          h.suggested_emoji,
          suggested_slug: h.suggested_slug,
        }));

      if (items.length === 0) return;

      this.importing = true;
      try {
        const r = await fetch('{{ route('admin.festivals.google-import') }}', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ items }),
        });
        const data = await r.json();
        if (data.ok) {
          // Reload to show the newly imported festivals in the list
          window.location.reload();
        } else {
          alert('Import failed: ' + (data.message || 'Unknown error'));
        }
      } catch (e) {
        alert('Network error: ' + e.message);
      } finally {
        this.importing = false;
      }
    },
  };
};
</script>
@endpush
@endsection
