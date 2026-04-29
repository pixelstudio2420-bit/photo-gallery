@extends('layouts.admin')

@section('title', 'Cloudflare R2 Storage')

@section('content')
{{-- ════════════════════════════════════════════════════════════════════
     /admin/settings/r2 — Dedicated form for Cloudflare R2 object storage.

     Why a separate page from /admin/settings/cloudflare?
     The Cloudflare page handles CDN/Zone API token + cache purge — those
     settings are conceptually different from "where do my photos live".
     Mixing them in one page made admins enter R2 credentials in fields
     that looked like Cloudflare API token fields and vice versa. Splitting
     the UI fixes that confusion. The underlying AppSetting keys are still
     shared (r2_enabled, r2_access_key_id, etc.) so this page is purely a
     UI re-organisation.

     R2 is S3-compatible — Laravel's `r2` disk in config/filesystems.php
     uses the s3 driver pointed at R2's endpoint. So the values entered
     here flow into config('filesystems.disks.r2') at runtime.
═══════════════════════════════════════════════════════════════════════ --}}

{{-- Page Header --}}
<div class="flex items-center justify-between mb-4">
  <div>
    <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="#f6821f" class="inline-block align-text-bottom mr-2">
        <path d="M16.5 16.5c.28-1.02.17-1.96-.32-2.65-.45-.64-1.18-1.02-2.03-1.07l-.38-.01-.17-.35c-.5-1.02-1.52-1.67-2.66-1.67-1.6 0-2.94 1.27-3.03 2.87l-.03.45-.45.04c-.96.09-1.71.88-1.71 1.85 0 1.03.84 1.87 1.87 1.87l8.06-.01c.82 0 1.5-.61 1.59-1.41l.01-.1-.75.01z"/>
      </svg>
      Cloudflare R2 Storage
    </h4>
    <p class="text-gray-500 text-sm mb-0 mt-1">
      Object storage แบบ S3-compatible — <strong class="text-emerald-600">egress ฟรี</strong> เหมาะกับเว็บแกลลอรี่ที่มี traffic เยอะ
    </p>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('admin.settings.aws') }}"
       class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
      <i class="bi bi-cloud"></i> AWS
    </a>
    <a href="{{ route('admin.settings.storage') }}"
       class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
      <i class="bi bi-hdd-stack"></i> Storage Routing
    </a>
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-lg"
       style="background:rgba(99,102,241,0.08);color:#6366f1;">
      <i class="bi bi-arrow-left"></i> Back to Settings
    </a>
  </div>
</div>

{{-- Flash Messages --}}
@if(session('success'))
  <div class="border-0 px-4 py-3 mb-4 flex items-center justify-between rounded-xl"
       style="background:rgba(16,185,129,0.08);color:#059669;">
    <span><i class="bi bi-check-circle mr-1"></i>{{ session('success') }}</span>
    <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer text-xs" onclick="this.parentElement.remove()">×</button>
  </div>
@endif
@if(session('error'))
  <div class="border-0 px-4 py-3 mb-4 flex items-center justify-between rounded-xl"
       style="background:rgba(239,68,68,0.08);color:#dc2626;">
    <span><i class="bi bi-exclamation-circle mr-1"></i>{{ session('error') }}</span>
    <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer text-xs" onclick="this.parentElement.remove()">×</button>
  </div>
@endif

{{-- ════════════════════════════════════════════════════════
     STATUS BANNER (only when not yet configured)
═══════════════════════════════════════════════════════ --}}
@unless($isConfigured)
<div class="mb-4 p-4 rounded-2xl flex items-start gap-3"
     style="background:linear-gradient(135deg,rgba(246,130,31,0.06),rgba(246,130,31,0.02));border:1px solid rgba(246,130,31,0.20);">
  <div class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(246,130,31,0.12);">
    <i class="bi bi-info-circle-fill text-lg" style="color:#f6821f;"></i>
  </div>
  <div class="flex-1 min-w-0">
    <div class="font-bold text-sm text-gray-800 mb-1">ยังไม่ได้ตั้งค่า — เริ่มตั้งค่าใน 4 ขั้น</div>
    <ol class="text-xs text-gray-600 space-y-0.5 ml-4 list-decimal">
      <li>เข้า <a href="https://dash.cloudflare.com/?to=/:account/r2" target="_blank" rel="noopener" class="font-semibold underline" style="color:#f6821f;">Cloudflare R2 Dashboard</a> → สร้าง Bucket</li>
      <li>ที่หน้า bucket → เมนู <strong>Settings → R2.dev subdomain</strong> → กด <strong>Allow Access</strong> (จะได้ public URL)</li>
      <li>คลิกปุ่ม <strong>Manage R2 API Tokens</strong> (มุมบนขวา) → Create API Token → permissions: <strong>Object Read & Write</strong> → ก๊อป 4 ค่า</li>
      <li>กรอก 4 ช่องด้านล่าง → Save → กด Test Connection</li>
    </ol>
  </div>
</div>
@endunless

<form method="POST" action="{{ route('admin.settings.r2.update') }}" id="r2SettingsForm">
  @csrf

  {{-- ════════════════════════════════════════════════════════
     Section 1: API Credentials
  ════════════════════════════════════════════════════════ --}}
  <div class="bg-white rounded-2xl shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:rgba(246,130,31,0.12);">
          <i class="bi bi-key" style="color:#f6821f;"></i>
        </div>
        <div>
          <h6 class="font-bold mb-0">API Credentials</h6>
          <p class="text-gray-500 text-xs mb-0 mt-0.5">R2 Access Key + Secret + Endpoint</p>
        </div>
      </div>
      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
            style="{{ $isConfigured ? 'background:rgba(16,185,129,0.1);color:#059669;' : 'background:rgba(239,68,68,0.08);color:#dc2626;' }}">
        <span class="w-1.5 h-1.5 rounded-full" style="background:currentColor;"></span>
        {{ $isConfigured ? 'Configured' : 'Not configured' }}
      </span>
    </div>

    <div class="p-5">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        {{-- Access Key ID --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5" for="r2_access_key_id">
            Access Key ID
            <span class="text-rose-500">*</span>
          </label>
          <div class="flex">
            <input type="text"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-l-lg text-sm focus:ring-2 focus:ring-orange-200 focus:border-orange-400 font-mono"
                   name="r2_access_key_id" id="r2_access_key_id"
                   value="{{ old('r2_access_key_id', $settings['r2_access_key_id']) }}"
                   placeholder="abc123def456...">
            <span class="px-3 py-2.5 bg-gray-50 border border-l-0 border-gray-300 text-gray-500 cursor-pointer rounded-r-lg toggle-pw" data-target="r2_access_key_id" title="Show/hide">
              <i class="bi bi-eye"></i>
            </span>
          </div>
          <div class="text-xs text-gray-500 mt-1.5">
            จาก Cloudflare → R2 → Manage API Tokens → ก๊อป "Access Key ID"
          </div>
        </div>

        {{-- Secret Access Key --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5" for="r2_secret_access_key">
            Secret Access Key
            <span class="text-rose-500">*</span>
          </label>
          <div class="flex">
            <input type="password"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-l-lg text-sm focus:ring-2 focus:ring-orange-200 focus:border-orange-400 font-mono"
                   name="r2_secret_access_key" id="r2_secret_access_key"
                   value=""
                   placeholder="{{ $settings['r2_secret_access_key'] ? $settings['r2_secret_masked'] . '  (เว้นไว้ไม่เปลี่ยน)' : 'a1b2c3...' }}"
                   autocomplete="new-password">
            <span class="px-3 py-2.5 bg-gray-50 border border-l-0 border-gray-300 text-gray-500 cursor-pointer rounded-r-lg toggle-pw" data-target="r2_secret_access_key" title="Show/hide">
              <i class="bi bi-eye"></i>
            </span>
          </div>
          <div class="text-xs text-gray-500 mt-1.5">
            ⚠️ Cloudflare แสดงค่านี้ <strong>ครั้งเดียวตอนสร้าง token</strong> เท่านั้น — เก็บไว้ดีๆ
          </div>
        </div>

        {{-- Bucket Name --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5" for="r2_bucket">
            Bucket Name
            <span class="text-rose-500">*</span>
          </label>
          <input type="text"
                 class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-200 focus:border-orange-400 font-mono"
                 name="r2_bucket" id="r2_bucket"
                 value="{{ old('r2_bucket', $settings['r2_bucket']) }}"
                 placeholder="jabphap-photos">
          <div class="text-xs text-gray-500 mt-1.5">
            ชื่อ bucket ที่สร้างใน Cloudflare R2 (ใช้ตัวเล็ก + เครื่องหมาย <code>-</code> เท่านั้น)
          </div>
        </div>

        {{-- Endpoint --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5" for="r2_endpoint">
            S3 API Endpoint
            <span class="text-rose-500">*</span>
          </label>
          <input type="url"
                 class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-200 focus:border-orange-400 font-mono"
                 name="r2_endpoint" id="r2_endpoint"
                 value="{{ old('r2_endpoint', $settings['r2_endpoint']) }}"
                 placeholder="https://xxxxxxxxxx.r2.cloudflarestorage.com">
          <div class="text-xs text-gray-500 mt-1.5">
            URL endpoint จาก Cloudflare → R2 → bucket detail page (ขึ้นต้นด้วย <code>https://</code>)
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
     Section 2: Public URL (R2.dev or custom domain)
  ════════════════════════════════════════════════════════ --}}
  <div class="bg-white rounded-2xl shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:rgba(99,102,241,0.10);">
        <i class="bi bi-globe2 text-indigo-500"></i>
      </div>
      <div>
        <h6 class="font-bold mb-0">Public URL</h6>
        <p class="text-gray-500 text-xs mb-0 mt-0.5">URL ที่ลูกค้า browser ใช้ดาวน์โหลดรูป (ใส่อันใดอันหนึ่ง — custom domain ดีกว่า)</p>
      </div>
    </div>

    <div class="p-5">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        {{-- R2.dev Public URL --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5" for="r2_public_url">
            R2.dev Subdomain
            <span class="text-xs font-normal text-gray-500">(สำหรับทดสอบ)</span>
          </label>
          <input type="url"
                 class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-200 focus:border-orange-400 font-mono"
                 name="r2_public_url" id="r2_public_url"
                 value="{{ old('r2_public_url', $settings['r2_public_url']) }}"
                 placeholder="https://pub-xxxxxxxxxxxxxxxxxxxxxxxxxxxx.r2.dev">
          <div class="text-xs text-gray-500 mt-1.5">
            จาก bucket → Settings → R2.dev subdomain → กด <strong>Allow Access</strong> → ก๊อป URL ที่ได้
          </div>
        </div>

        {{-- Custom Domain --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5" for="r2_custom_domain">
            Custom Domain
            <span class="text-xs font-normal" style="color:#10b981;">(แนะนำ production)</span>
          </label>
          <input type="text"
                 class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-200 focus:border-orange-400 font-mono"
                 name="r2_custom_domain" id="r2_custom_domain"
                 value="{{ old('r2_custom_domain', $settings['r2_custom_domain']) }}"
                 placeholder="photos.jabphap.com">
          <div class="text-xs text-gray-500 mt-1.5">
            (ทำใน Cloudflare → bucket → Custom Domains) — ใส่แค่ hostname ไม่ต้องมี <code>https://</code>
          </div>
        </div>
      </div>

      {{-- Tip: which URL gets used --}}
      <div class="mt-4 p-3 rounded-xl flex items-start gap-2.5" style="background:rgba(99,102,241,0.04);border:1px dashed rgba(99,102,241,0.20);">
        <i class="bi bi-lightbulb-fill text-indigo-400 text-sm shrink-0 mt-0.5"></i>
        <div class="text-xs text-gray-600 leading-relaxed">
          <strong class="text-gray-800">ลำดับการเลือก URL:</strong>
          ถ้ากรอกทั้ง custom domain + r2.dev → ระบบใช้ <strong class="text-emerald-600">custom domain</strong> ก่อน
          (เร็วกว่า + Cloudflare CDN cache อยู่หน้า + branding ดูดีกว่า)
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
     Section 3: Enable + Test
  ════════════════════════════════════════════════════════ --}}
  <div class="bg-white rounded-2xl shadow-sm mb-4">
    <div class="p-5">

      {{-- Enable toggle --}}
      <div class="flex items-center justify-between p-4 rounded-xl"
           style="background:linear-gradient(135deg,rgba(246,130,31,0.05),rgba(246,130,31,0.01));border:1px solid rgba(246,130,31,0.15);">
        <div>
          <div class="font-bold text-sm text-gray-800">เปิดใช้งาน Cloudflare R2</div>
          <div class="text-xs text-gray-500 mt-0.5">
            เมื่อเปิด — multi-driver storage จะ route รูปไป R2 ตาม Storage Routing settings
          </div>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" name="r2_enabled" id="r2_enabled" value="1"
                 {{ ($settings['r2_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                 class="sr-only peer">
          <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"></div>
        </label>
      </div>

      {{-- Action row --}}
      <div class="flex flex-wrap items-center justify-between gap-3 mt-5 pt-4 border-t border-gray-100">
        <div class="flex items-center gap-3 flex-wrap">
          <button type="button" id="btnTestR2"
                  class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition"
                  style="background:rgba(99,102,241,0.08);color:#6366f1;border:1.5px solid rgba(99,102,241,0.20);">
            <i class="bi bi-plug"></i>
            <span>Test Connection</span>
            <span class="hidden test-spinner inline-block w-3.5 h-3.5 rounded-full border-2 border-indigo-300 border-t-indigo-600 animate-spin"></span>
          </button>
          <span id="testResult" class="text-sm hidden"></span>
        </div>

        <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-bold text-white transition shadow-md"
                style="background:linear-gradient(135deg,#f6821f,#e76800);box-shadow:0 4px 12px -2px rgba(246,130,31,0.4);">
          <i class="bi bi-save"></i> Save R2 Settings
        </button>
      </div>
    </div>
  </div>
</form>

{{-- ════════════════════════════════════════════════════════
     Help Card — Step-by-step Setup Guide
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
  <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3" style="background:linear-gradient(135deg,rgba(246,130,31,0.04),transparent);">
    <i class="bi bi-book-half text-lg" style="color:#f6821f;"></i>
    <h6 class="font-bold mb-0">Setup Guide — สร้าง R2 Bucket ครั้งแรก</h6>
  </div>

  <div class="p-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

      {{-- Step 1 --}}
      <div class="p-4 rounded-xl bg-gray-50 border border-gray-100">
        <div class="flex items-center gap-2 mb-2">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-white font-bold text-sm" style="background:#f6821f;">1</div>
          <div class="font-bold text-sm">Create R2 Bucket</div>
        </div>
        <ol class="text-xs text-gray-600 space-y-1 ml-2 list-decimal list-inside">
          <li>เข้า <a href="https://dash.cloudflare.com/?to=/:account/r2" target="_blank" rel="noopener" style="color:#f6821f;font-weight:600;">Cloudflare R2 Dashboard <i class="bi bi-box-arrow-up-right" style="font-size:10px;"></i></a></li>
          <li>คลิก <strong>Create bucket</strong></li>
          <li>ตั้งชื่อ (เช่น <code>jabphap-photos</code>)</li>
          <li>Region: <strong>Automatic</strong></li>
        </ol>
      </div>

      {{-- Step 2 --}}
      <div class="p-4 rounded-xl bg-gray-50 border border-gray-100">
        <div class="flex items-center gap-2 mb-2">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-white font-bold text-sm" style="background:#f6821f;">2</div>
          <div class="font-bold text-sm">Create API Token</div>
        </div>
        <ol class="text-xs text-gray-600 space-y-1 ml-2 list-decimal list-inside">
          <li>หน้า R2 → ปุ่ม <strong>Manage R2 API Tokens</strong></li>
          <li>คลิก <strong>Create API token</strong></li>
          <li>Permission: <strong>Object Read & Write</strong></li>
          <li>เลือก bucket → Create</li>
          <li>ก๊อปทันที — Access Key ID + Secret + Endpoint (จะแสดงครั้งเดียว)</li>
        </ol>
      </div>

      {{-- Step 3 --}}
      <div class="p-4 rounded-xl bg-gray-50 border border-gray-100">
        <div class="flex items-center gap-2 mb-2">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-white font-bold text-sm" style="background:#f6821f;">3</div>
          <div class="font-bold text-sm">Enable Public Access</div>
        </div>
        <ol class="text-xs text-gray-600 space-y-1 ml-2 list-decimal list-inside">
          <li>กลับมาหน้า bucket → tab <strong>Settings</strong></li>
          <li>หา section <strong>R2.dev subdomain</strong></li>
          <li>คลิก <strong>Allow Access</strong> → ก๊อป URL</li>
          <li><span class="text-emerald-600">หรือ Custom Domain ถ้ามี</span></li>
        </ol>
      </div>

      {{-- Step 4 --}}
      <div class="p-4 rounded-xl border" style="background:linear-gradient(135deg,rgba(16,185,129,0.05),rgba(16,185,129,0.01));border-color:rgba(16,185,129,0.20);">
        <div class="flex items-center gap-2 mb-2">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-white font-bold text-sm" style="background:#10b981;">4</div>
          <div class="font-bold text-sm">Test & Activate</div>
        </div>
        <ol class="text-xs text-gray-600 space-y-1 ml-2 list-decimal list-inside">
          <li>ใส่ค่าทั้ง 4 ในฟอร์มด้านบน + URL ในฟอร์มถัดไป</li>
          <li>ติ๊ก <strong>เปิดใช้งาน R2</strong></li>
          <li>กด <strong>Save R2 Settings</strong></li>
          <li>กด <strong>Test Connection</strong> → ขึ้น ✅ = สำเร็จ</li>
          <li>ไป <a href="{{ route('admin.settings.storage') }}" class="font-semibold underline" style="color:#10b981;">Storage Routing</a> ตั้ง R2 เป็น primary</li>
        </ol>
      </div>
    </div>

    {{-- Cost callout --}}
    <div class="mt-5 p-4 rounded-xl flex items-start gap-3"
         style="background:linear-gradient(135deg,rgba(16,185,129,0.06),rgba(59,130,246,0.04));border:1px solid rgba(16,185,129,0.15);">
      <i class="bi bi-piggy-bank-fill text-emerald-500 text-lg shrink-0"></i>
      <div class="text-xs text-gray-700">
        <strong class="text-gray-900">ทำไมแนะนำ R2?</strong>
        <strong class="text-emerald-600">Egress ฟรี!</strong> AWS S3 คิด $0.09/GB ที่ download ออก —
        ถ้าเว็บมี traffic 5,000 คน/วัน × 50MB/คน = 7.5TB/เดือน
        <span class="block mt-1">
          AWS S3: <strong class="text-rose-600">~$700/เดือน</strong>
          · R2: <strong class="text-emerald-600">~$17/เดือน</strong>
          (ประหยัด <strong>40 เท่า!</strong>)
        </span>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
  'use strict';

  // ─── Toggle password visibility on the eye icons ───
  document.querySelectorAll('.toggle-pw').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var target = document.getElementById(this.dataset.target);
      var icon   = this.querySelector('i');
      if (!target) return;
      if (target.type === 'password') {
        target.type = 'text';
        icon.className = 'bi bi-eye-slash';
      } else {
        target.type = 'password';
        icon.className = 'bi bi-eye';
      }
    });
  });

  // ─── Test Connection (AJAX POST to update endpoint with action=test_r2) ───
  // We post the CURRENT form values so admins can test BEFORE hitting Save.
  // The server-side handler tries an actual write+delete against the bucket,
  // so a passing test means production uploads will work.
  var testBtn = document.getElementById('btnTestR2');
  testBtn?.addEventListener('click', function() {
    var spinner = testBtn.querySelector('.test-spinner');
    var result  = document.getElementById('testResult');
    var form    = document.getElementById('r2SettingsForm');

    spinner.classList.remove('hidden');
    result.classList.add('hidden');
    testBtn.disabled = true;

    var fd = new FormData(form);
    fd.append('action', 'test_r2');

    fetch(form.action, {
      method: 'POST',
      body: fd,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      result.classList.remove('hidden');
      if (data.success) {
        result.innerHTML = '<span style="color:#059669;font-weight:600;"><i class="bi bi-check-circle-fill"></i> ' +
                           (data.message || 'Connection OK') + '</span>';
      } else {
        result.innerHTML = '<span style="color:#dc2626;font-weight:600;"><i class="bi bi-x-circle-fill"></i> ' +
                           (data.message || 'Connection failed') + '</span>';
      }
    })
    .catch(function(err) {
      result.classList.remove('hidden');
      result.innerHTML = '<span style="color:#dc2626;"><i class="bi bi-exclamation-triangle-fill"></i> ' +
                         (err.message || 'Network error') + '</span>';
    })
    .finally(function() {
      spinner.classList.add('hidden');
      testBtn.disabled = false;
    });
  });
})();
</script>
@endpush
