@extends('layouts.admin')

@section('title', 'Google APIs Integration')

@section('content')
<div class="max-w-5xl mx-auto" x-data="googleApiSettings()">

  {{-- Header --}}
  <div class="mb-6">
    <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 hover:text-indigo-600 transition mb-2">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
    <div class="flex items-center gap-3">
      <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center shadow-md shadow-blue-500/30">
        <i class="bi bi-google text-xl"></i>
      </div>
      <div>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Google APIs Integration</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400">Service Account สำหรับ GA4 + Search Console (ใช้ JSON เดียวสำหรับทุก service)</p>
      </div>
    </div>
  </div>

  {{-- Flash --}}
  @if(session('success'))
  <div class="mb-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm whitespace-pre-line">
    <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
  </div>
  @endif
  @if(session('error'))
  <div class="mb-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 px-4 py-3 text-sm">
    <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
  </div>
  @endif

  {{-- ─────────────── Step 1: Service Account ─────────────── --}}
  <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden mb-5">
    <div class="h-1.5 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
    <div class="p-5">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
            <i class="bi bi-key text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">1. Service Account JSON</h3>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">ใช้สำหรับ auth ทุก Google API (ฟรี ไม่หมดอายุ)</p>
          </div>
        </div>
        @if($has_json)
          <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 px-2.5 py-1 text-[11px] font-bold">
            <i class="bi bi-check-circle-fill"></i> อัปโหลดแล้ว
          </span>
        @else
          <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 px-2.5 py-1 text-[11px] font-medium">
            <i class="bi bi-circle"></i> ยังไม่ได้ตั้งค่า
          </span>
        @endif
      </div>

      {{-- Setup guide --}}
      <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 px-4 py-3 text-xs text-slate-700 dark:text-slate-300 leading-relaxed mb-4">
        <p class="font-semibold text-amber-700 dark:text-amber-300 mb-2">
          <i class="bi bi-info-circle-fill"></i> สร้าง Service Account ครั้งเดียว ใช้ทุก service
        </p>
        <ol class="space-y-1.5 list-decimal pl-5">
          <li>
            <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Cloud Console → Credentials</a>
            → <strong>Create credentials → Service account</strong>
          </li>
          <li>ตั้งชื่อ (เช่น "loadroop-analytics") → กด Done</li>
          <li>คลิกที่ service account ที่สร้าง → Keys tab → Add Key → Create new key → JSON → Create</li>
          <li>ไฟล์ JSON จะ download อัตโนมัติ — อัปโหลดที่นี่</li>
          <li>
            <strong>เปิด API ที่ Library</strong>:
            <a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Google Analytics Data API</a>
            +
            <a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Search Console API</a>
          </li>
        </ol>
      </div>

      {{-- Service account email display --}}
      @if($service_account_email)
        <div class="rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 px-4 py-3 mb-4">
          <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-1">
            <i class="bi bi-envelope-at"></i> Service Account Email (คัดลอกไปเพิ่มสิทธิ์ใน GA4 + Search Console):
          </p>
          <div class="flex items-center gap-2">
            <code class="flex-1 px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-blue-200 dark:border-blue-500/30 text-xs font-mono break-all">{{ $service_account_email }}</code>
            <button type="button" @click="navigator.clipboard.writeText('{{ $service_account_email }}'); $event.target.textContent = '✓ คัดลอกแล้ว'; setTimeout(() => $event.target.textContent = 'คัดลอก', 1500)"
                    class="shrink-0 inline-flex items-center justify-center rounded-lg bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 text-xs font-medium transition">
              คัดลอก
            </button>
          </div>
        </div>
      @endif

      {{-- Upload form --}}
      <form method="POST" action="{{ route('admin.settings.google-apis.save-service-account') }}" enctype="multipart/form-data" class="space-y-3">
        @csrf
        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
            อัปโหลดไฟล์ JSON
          </label>
          <input type="file" name="json_file" accept=".json,application/json"
                 class="block w-full text-sm text-slate-900 dark:text-slate-100
                        file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0
                        file:text-sm file:font-medium file:bg-blue-100 file:text-blue-700
                        dark:file:bg-blue-500/20 dark:file:text-blue-300
                        hover:file:bg-blue-200 transition">
        </div>
        <div class="flex items-center gap-2">
          <button type="submit"
                  class="inline-flex items-center gap-1.5 rounded-xl bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 text-sm font-medium transition">
            <i class="bi bi-upload"></i> อัปโหลด
          </button>
          @if($has_json)
            <button type="submit" formaction="{{ route('admin.settings.google-apis.clear-service-account') }}"
                    onclick="return confirm('ลบ Service Account JSON?')"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-rose-200 dark:border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-50 px-3 py-2 text-xs font-medium transition">
              <i class="bi bi-trash"></i> ลบ
            </button>
          @endif
        </div>
      </form>
    </div>
  </div>

  {{-- ─────────────── Step 2: GA4 Property ID ─────────────── --}}
  <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden mb-5">
    <div class="h-1.5 bg-gradient-to-r from-purple-500 to-pink-500"></div>
    <div class="p-5">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center">
            <i class="bi bi-bar-chart-line text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">2. Google Analytics 4 (GA4)</h3>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Property ID — ตัวเลข ไม่ใช่ Measurement ID</p>
          </div>
        </div>
        @if($ga_configured)
          <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 px-2.5 py-1 text-[11px] font-bold">
            <i class="bi bi-check-circle-fill"></i> ตั้งค่าครบ
          </span>
        @else
          <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 px-2.5 py-1 text-[11px] font-medium">
            <i class="bi bi-circle"></i> ไม่ครบ
          </span>
        @endif
      </div>

      <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 px-4 py-3 text-xs text-slate-700 dark:text-slate-300 mb-4">
        <p class="font-semibold text-amber-700 dark:text-amber-300 mb-1.5">
          <i class="bi bi-info-circle"></i> สิ่งที่ต้องตั้ง 2 จุด:
        </p>
        <ol class="space-y-1 list-decimal pl-5">
          <li>หา Property ID: <strong>GA4 → Admin → Property Settings</strong> → คัดลอก Property ID (ตัวเลข ไม่มี G-)</li>
          <li>เพิ่มสิทธิ์: <strong>GA4 → Admin → Property access management</strong> → "+" → ใส่ service account email → Viewer</li>
        </ol>
      </div>

      <form method="POST" action="{{ route('admin.settings.google-apis.save-ga') }}" class="flex items-center gap-2 mb-3">
        @csrf
        <input type="text" name="google_analytics_property_id" value="{{ $ga_property_id }}"
               placeholder="เช่น 123456789" pattern="[0-9]*" maxlength="30"
               class="flex-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-500 transition">
        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-purple-500 hover:bg-purple-600 text-white px-4 py-2.5 text-sm font-medium transition">
          <i class="bi bi-save"></i> บันทึก
        </button>
      </form>

      @if($ga_configured)
      <button type="button" @click="testGa()" :disabled="testingGa"
              class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border-2 border-purple-300 dark:border-purple-500/40 text-purple-700 dark:text-purple-300 hover:bg-purple-50 px-4 py-2 text-sm font-semibold disabled:opacity-50 transition">
        <i class="bi" :class="testingGa ? 'bi-arrow-repeat animate-spin' : 'bi-plug'"></i>
        <span x-show="!testingGa">ทดสอบเชื่อมต่อ GA4</span>
        <span x-show="testingGa" x-cloak>กำลังทดสอบ...</span>
      </button>
      <div x-show="gaResult" x-cloak x-transition class="mt-3 rounded-xl p-4 text-xs"
           :class="gaResult?.ok ? 'bg-emerald-50 border border-emerald-200 text-emerald-900' : 'bg-rose-50 border border-rose-200 text-rose-900'">
        <p class="font-semibold" x-text="gaResult?.message"></p>
        <pre x-show="gaResult?.fix" x-cloak class="font-sans whitespace-pre-wrap mt-2 pt-2 border-t border-rose-200/70 text-[11px]" x-text="gaResult?.fix"></pre>
      </div>
      @endif
    </div>
  </div>

  {{-- ─────────────── Step 3: Search Console site URL ─────────────── --}}
  <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden mb-5">
    <div class="h-1.5 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
    <div class="p-5">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
            <i class="bi bi-search text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">3. Google Search Console</h3>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Site URL — verified property URL</p>
          </div>
        </div>
        @if($sc_configured)
          <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 px-2.5 py-1 text-[11px] font-bold">
            <i class="bi bi-check-circle-fill"></i> ตั้งค่าครบ
          </span>
        @else
          <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 px-2.5 py-1 text-[11px] font-medium">
            <i class="bi bi-circle"></i> ไม่ครบ
          </span>
        @endif
      </div>

      <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 px-4 py-3 text-xs text-slate-700 dark:text-slate-300 mb-4">
        <p class="font-semibold text-amber-700 dark:text-amber-300 mb-1.5">
          <i class="bi bi-info-circle"></i> Site URL ที่ระบบรับ:
        </p>
        <ul class="space-y-1 list-disc pl-5">
          <li>URL property: <code class="px-1.5 rounded bg-white dark:bg-white/5">https://loadroop.com/</code> (ต้องมี / ปิดท้าย)</li>
          <li>Domain property: <code class="px-1.5 rounded bg-white dark:bg-white/5">sc-domain:loadroop.com</code></li>
          <li>เพิ่มสิทธิ์: Search Console → Settings → Users and permissions → Add user → service account email → Restricted</li>
        </ul>
      </div>

      <form method="POST" action="{{ route('admin.settings.google-apis.save-sc') }}" class="flex items-center gap-2 mb-3">
        @csrf
        <input type="text" name="google_search_console_site_url" value="{{ $sc_site_url }}"
               placeholder="https://loadroop.com/ หรือ sc-domain:loadroop.com" maxlength="200"
               class="flex-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2.5 text-sm font-medium transition">
          <i class="bi bi-save"></i> บันทึก
        </button>
      </form>

      @if($sc_configured)
      <button type="button" @click="testSc()" :disabled="testingSc"
              class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border-2 border-emerald-300 dark:border-emerald-500/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 px-4 py-2 text-sm font-semibold disabled:opacity-50 transition">
        <i class="bi" :class="testingSc ? 'bi-arrow-repeat animate-spin' : 'bi-plug'"></i>
        <span x-show="!testingSc">ทดสอบเชื่อมต่อ Search Console</span>
        <span x-show="testingSc" x-cloak>กำลังทดสอบ...</span>
      </button>
      <div x-show="scResult" x-cloak x-transition class="mt-3 rounded-xl p-4 text-xs"
           :class="scResult?.ok ? 'bg-emerald-50 border border-emerald-200 text-emerald-900' : 'bg-rose-50 border border-rose-200 text-rose-900'">
        <p class="font-semibold" x-text="scResult?.message"></p>
        <pre x-show="scResult?.fix" x-cloak class="font-sans whitespace-pre-wrap mt-2 pt-2 border-t border-rose-200/70 text-[11px]" x-text="scResult?.fix"></pre>
      </div>
      @endif
    </div>
  </div>

  {{-- ─────────────── Step 4: OAuth User flow (Search Console workaround) ─────────────── --}}
  <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden mb-5">
    <div class="h-1.5 bg-gradient-to-r from-amber-500 to-orange-500"></div>
    <div class="p-5">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
            <i class="bi bi-shield-lock text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">
              4. OAuth User Flow
              <span class="ml-1 text-[10px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Search Console workaround</span>
            </h3>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">ใช้บัญชี Google ของคุณเอง (bypass UI bug ของ Search Console)</p>
          </div>
        </div>
        @if($oauth_is_connected)
          <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 px-2.5 py-1 text-[11px] font-bold">
            <i class="bi bi-check-circle-fill"></i> Connected
          </span>
        @else
          <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 px-2.5 py-1 text-[11px] font-medium">
            <i class="bi bi-circle"></i> Not connected
          </span>
        @endif
      </div>

      {{-- Why this section exists --}}
      <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 px-4 py-3 text-xs text-slate-700 dark:text-slate-300 leading-relaxed mb-4">
        <p class="font-semibold text-amber-700 dark:text-amber-300 mb-1.5">
          <i class="bi bi-exclamation-triangle-fill"></i> ทำไมต้องใช้ตัวนี้
        </p>
        <p>
          Search Console UI ของ Google มี bug — block service account email ทั่วไป (เห็น "ไม่พบอีเมล" ตอน Add user) ทางแก้คือใช้ <strong>OAuth User flow</strong> แทน:
          ใช้บัญชี Google ของคุณเอง (ที่เป็น owner ของ Search Console property อยู่แล้ว) → ไม่ต้องเพิ่ม service account
        </p>
        <p class="mt-2 text-[11px] text-amber-700">
          <strong>หมายเหตุ</strong>: GA4 ยังคงใช้ service account เหมือนเดิม (ทำงานได้ปกติ) — ตัวนี้ใช้แค่กับ Search Console
        </p>
      </div>

      @if($oauth_is_connected)
        {{-- Connected state --}}
        <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-4 py-3 mb-4">
          <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 mb-1">
            <i class="bi bi-check-circle-fill"></i> Connected as:
          </p>
          <code class="block px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-emerald-200 dark:border-emerald-500/30 text-xs font-mono break-all">
            {{ $oauth_connected_email ?? '(unknown)' }}
          </code>
          <p class="text-[11px] text-emerald-700 dark:text-emerald-300 mt-2">
            Search Console queries ตอนนี้ใช้บัญชีนี้ (ไม่ใช่ service account) — บัญชีนี้ต้องเป็น owner หรือ user ของ Search Console property
          </p>
        </div>
        <form method="POST" action="{{ route('admin.settings.google-apis.oauth-disconnect') }}"
              onsubmit="return confirm('Disconnect Google account นี้? — Search Console จะหยุดทำงานจนกว่าจะ connect ใหม่')">
          @csrf
          <button type="submit"
                  class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-rose-200 dark:border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-50 px-3 py-2 text-xs font-medium transition">
            <i class="bi bi-box-arrow-left"></i> Disconnect
          </button>
        </form>
      @else
        {{-- Setup guide --}}
        <div class="rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 px-4 py-3 text-xs text-slate-700 dark:text-slate-300 mb-4">
          <p class="font-semibold text-blue-700 dark:text-blue-300 mb-1.5">
            <i class="bi bi-info-circle"></i> ขั้นตอนตั้งค่า:
          </p>
          <ol class="space-y-1.5 list-decimal pl-5">
            <li>
              ไปที่
              <a href="https://console.cloud.google.com/apis/credentials?project=loadroop" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Cloud Console → Credentials</a>
              → คลิก OAuth 2.0 Client ID ที่มีอยู่ (เช่น "Web Loadroop") หรือสร้างใหม่
            </li>
            <li>
              ใต้ "Authorized redirect URIs" — กด <strong>Add URI</strong> และวาง:
              <div class="mt-1 flex items-center gap-2">
                <code class="flex-1 px-2 py-1 rounded bg-white dark:bg-slate-900 border border-blue-200 text-[11px] font-mono break-all">{{ $oauth_callback_url }}</code>
                <button type="button"
                        onclick="navigator.clipboard.writeText('{{ $oauth_callback_url }}'); this.textContent='✓'; setTimeout(() => this.textContent='Copy', 1500)"
                        class="shrink-0 rounded bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 text-[11px] font-medium transition">Copy</button>
              </div>
            </li>
            <li>กด Save ใน Cloud Console</li>
            <li>Copy <strong>Client ID</strong> + <strong>Client Secret</strong> → กรอกในฟอร์มด้านล่าง</li>
            <li>กดบันทึก → กด <strong>"Connect with Google"</strong> → Login ด้วยบัญชีที่เป็น owner ของ Search Console property</li>
          </ol>
        </div>

        <form method="POST" action="{{ route('admin.settings.google-apis.save-oauth') }}" class="space-y-3">
          @csrf
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">OAuth Client ID</label>
            <input type="text" name="google_oauth_client_id" value="{{ $oauth_client_id }}"
                   placeholder="123456789-abc...apps.googleusercontent.com" maxlength="200"
                   class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-amber-500 transition">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">OAuth Client Secret</label>
            <input type="password" name="google_oauth_client_secret" value="{{ $oauth_client_secret }}"
                   placeholder="GOCSPX-..." maxlength="200" autocomplete="off"
                   class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-amber-500 transition">
          </div>
          <div class="flex items-center gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 text-sm font-medium transition">
              <i class="bi bi-save"></i> บันทึก credentials
            </button>
            @if($oauth_has_credentials)
            <a href="{{ route('admin.settings.google-apis.oauth-connect') }}"
               class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white px-4 py-2 text-sm font-semibold shadow-md transition">
              <i class="bi bi-google"></i> Connect with Google
            </a>
            @endif
          </div>
        </form>
      @endif
    </div>
  </div>

  <div class="rounded-2xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-4 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
    <p class="font-medium text-slate-700 dark:text-slate-300 mb-1.5"><i class="bi bi-info-circle text-blue-500"></i> ฟีเจอร์ที่จะใช้งานได้หลังตั้งค่า:</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mt-2">
      <div class="rounded-lg bg-white dark:bg-white/5 px-3 py-2 border border-slate-200 dark:border-white/10">
        <strong class="text-purple-600 dark:text-purple-400">GA4</strong>: Real-time visitors badge, Traffic sources per photographer, Page bounce/exit, Geographic heatmap, Multi-touch attribution, Device breakdown
      </div>
      <div class="rounded-lg bg-white dark:bg-white/5 px-3 py-2 border border-slate-200 dark:border-white/10">
        <strong class="text-emerald-600 dark:text-emerald-400">Search Console</strong>: Top keywords ที่นำคนมา, Impressions/CTR per page, SEO summary
      </div>
      <div class="rounded-lg bg-white dark:bg-white/5 px-3 py-2 border border-slate-200 dark:border-white/10">
        <strong class="text-blue-600 dark:text-blue-400">Calendar</strong>: <a href="{{ route('admin.festivals.index') }}" class="underline hover:text-blue-700">ตั้งค่าแยกที่ Festivals</a> (ใช้ API key, simpler)
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
window.googleApiSettings = function () {
  return {
    testingGa: false, gaResult: null,
    testingSc: false, scResult: null,
    async testGa() {
      this.testingGa = true; this.gaResult = null;
      try {
        const r = await fetch('{{ route('admin.settings.google-apis.test-ga') }}', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        });
        this.gaResult = await r.json();
      } catch (e) { this.gaResult = { ok: false, message: 'Network error: ' + e.message }; }
      finally { this.testingGa = false; }
    },
    async testSc() {
      this.testingSc = true; this.scResult = null;
      try {
        const r = await fetch('{{ route('admin.settings.google-apis.test-sc') }}', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        });
        this.scResult = await r.json();
      } catch (e) { this.scResult = { ok: false, message: 'Network error: ' + e.message }; }
      finally { this.testingSc = false; }
    }
  };
};
</script>
@endpush
@endsection
