@extends('layouts.admin')

@section('title', 'Social Login & Registration')

{{-- =======================================================================
     SOCIAL LOGIN & REGISTRATION — ADMIN SETTINGS
     -------------------------------------------------------------------
     Single source of truth for:
       • Provider on/off toggles (LINE / Google / Facebook / Apple)
       • Email registration
       • LINE-connect enforcement
       • Default-provider per role
       • OAuth / OpenID Connect client credentials per provider

     Note on shared keys:
       - google_client_id / google_client_secret are also editable at
         /admin/settings/google-drive (drives both OAuth login & Drive API).
       - line_channel_id / line_channel_secret are also editable at
         /admin/settings/line (drives both LINE Login & Messaging API).
     Facebook credentials moved here from .env; Apple is new.
     ====================================================================== --}}

@push('styles')
<style>
  /* ── Tailwind-only toggle switch (matches /admin/settings/line) ─── */
  .tw-switch { position: relative; display: inline-block; width: 2.75rem; height: 1.5rem; flex-shrink: 0; }
  .tw-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
  .tw-switch .slider {
    position: absolute; inset: 0;
    background: rgb(226 232 240);
    border-radius: 9999px;
    cursor: pointer;
    transition: background-color .2s;
  }
  .dark .tw-switch .slider { background: rgb(51 65 85); }
  .tw-switch .slider::before {
    content: ''; position: absolute;
    height: 1.125rem; width: 1.125rem;
    left: 3px; top: 3px;
    background: #fff;
    border-radius: 9999px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.25);
    transition: transform .2s;
  }
  .tw-switch input:checked + .slider { background: linear-gradient(135deg, #6366f1, #818cf8); }
  .tw-switch input:checked + .slider::before { transform: translateX(1.25rem); }
  .tw-switch input:focus-visible + .slider { box-shadow: 0 0 0 3px rgba(99,102,241,0.35); }
  .tw-switch input:disabled + .slider { opacity: 0.5; cursor: not-allowed; }

  /* ── Status dot pill ──────────────────────────────────────────── */
  .status-dot {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-size: 0.72rem; font-weight: 600;
    padding: 0.22rem 0.65rem; border-radius: 9999px;
    white-space: nowrap;
  }
  .status-dot::before {
    content: ''; width: 7px; height: 7px; border-radius: 50%;
    background: currentColor; flex-shrink: 0;
  }
  .status-dot.configured    { background: rgb(209 250 229); color: rgb(4 120 87); }
  .status-dot.not-configured{ background: rgb(254 226 226); color: rgb(185 28 28); }
  .status-dot.disabled      { background: rgb(226 232 240); color: rgb(71 85 105); }
  .dark .status-dot.configured     { background: rgba(16,185,129,0.15); color: rgb(110 231 183); }
  .dark .status-dot.not-configured { background: rgba(239,68,68,0.15);  color: rgb(252 165 165); }
  .dark .status-dot.disabled       { background: rgba(148,163,184,0.15); color: rgb(203 213 225); }

  /* ── Copy button "copied" state ──────────────────────────────── */
  .copy-btn.copied {
    border-color: rgb(16 185 129) !important;
    color: rgb(5 150 105) !important;
    background: rgb(209 250 229) !important;
  }
  .dark .copy-btn.copied {
    border-color: rgb(16 185 129) !important;
    color: rgb(110 231 183) !important;
    background: rgba(16,185,129,0.15) !important;
  }

  /* ── Provider toggle row ─────────────────────────────────────── */
  .provider-row{display:flex;align-items:center;gap:.85rem;padding:.85rem 1rem;border:1.5px solid #e5e7eb;border-radius:12px;margin-bottom:.6rem;background:#fff;transition:border-color .15s;}
  .provider-row:hover{border-color:#c7d2fe;}
  .provider-row.off{background:#fafafa;}
  .provider-logo{width:40px;height:40px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:1.15rem;flex-shrink:0;}
  html.dark .provider-row{background:#1e293b;border-color:#334155;}
  html.dark .provider-row.off{background:#0f172a;}

  /* ── Credential card header ──────────────────────────────────── */
  .cred-card details > summary{list-style:none;cursor:pointer;}
  .cred-card details > summary::-webkit-details-marker{display:none;}
  .cred-card details[open] .chevron{transform:rotate(180deg);}
  .chevron{transition:transform .2s;}
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ────────── PAGE HEADER ────────── --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h4 class="text-2xl font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-3 tracking-tight">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl shadow-md shadow-indigo-500/30 bg-gradient-to-br from-indigo-500 to-purple-600 text-white">
          <i class="bi bi-shield-lock-fill"></i>
        </span>
        Social Login &amp; Registration
      </h4>
      <p class="text-sm text-slate-500 dark:text-slate-400 ml-14">
        ควบคุมช่องทางสมัครสมาชิก / เข้าสู่ระบบ พร้อมตั้งค่า OAuth / OpenID Connect สำหรับแต่ละ provider
      </p>
    </div>
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 text-sm px-4 py-2 rounded-lg
              border border-slate-200 dark:border-white/10
              text-slate-700 dark:text-slate-300
              bg-white dark:bg-slate-900
              hover:bg-slate-50 dark:hover:bg-slate-800 transition">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
  </div>

  {{-- ────────── FLASH MESSAGE ────────── --}}
  @if(session('success'))
  <div class="mb-5 px-4 py-3 rounded-xl flex items-start gap-2
              bg-emerald-50 dark:bg-emerald-500/10
              border border-emerald-200 dark:border-emerald-500/30
              text-emerald-800 dark:text-emerald-300">
    <i class="bi bi-check-circle-fill mt-0.5"></i>
    <div class="flex-1 text-sm">{{ session('success') }}</div>
    <button type="button" onclick="this.parentElement.remove()"
            class="text-emerald-500 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300">
      <i class="bi bi-x-lg text-xs"></i>
    </button>
  </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.social-auth.update') }}" id="socialAuthForm" autocomplete="off">
    @csrf

    {{-- ══════════════════════════════════════════════════════════════
         PROVIDER TOGGLES
         ══════════════════════════════════════════════════════════════ --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900
                border border-slate-200 dark:border-white/10
                shadow-sm shadow-slate-900/5 dark:shadow-black/20 mb-5">
      <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-start gap-3">
        <span class="w-10 h-10 flex items-center justify-center rounded-xl flex-shrink-0"
              style="background:rgba(99,102,241,0.12);color:#6366f1;">
          <i class="bi bi-box-arrow-in-right"></i>
        </span>
        <div>
          <h6 class="font-bold text-[15px] text-slate-900 dark:text-white leading-tight mb-0.5">Social Login Providers</h6>
          <p class="text-xs text-slate-500 dark:text-slate-400">เปิด / ปิดแต่ละช่องทาง ผู้ใช้จะเห็นเฉพาะที่เปิด และต้องตั้งค่า Credentials ด้านล่างให้ครบก่อนใช้งาน</p>
        </div>
      </div>
      <div class="p-5">
        @foreach($providers as $key => $meta)
          @php
            $enabled  = ($settings['auth_social_'.$key.'_enabled'] ?? '0') === '1';
            $hasCreds = $providerStatus[$key] ?? false;
          @endphp
          <div class="provider-row {{ $enabled ? '' : 'off' }}">
            <span class="provider-logo" style="background:{{ $meta['color'] }};">
              <i class="bi {{ $meta['icon'] }}"></i>
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h6 class="font-bold text-slate-900 dark:text-white mb-0">{{ $meta['label'] }}</h6>
                @if($enabled)
                  <span class="status-dot {{ $hasCreds ? 'configured' : 'not-configured' }}">
                    {{ $hasCreds ? 'Configured' : 'Missing credentials' }}
                  </span>
                @else
                  <span class="status-dot disabled">Disabled</span>
                @endif
                <a href="#cred-{{ $key }}"
                   onclick="openCredCard('{{ $key }}')"
                   class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                  <i class="bi bi-gear-fill mr-0.5"></i>ตั้งค่า Credentials
                </a>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 mb-0">
                @switch($key)
                  @case('line')     สมัคร / เข้าสู่ระบบผ่าน LINE Login — เหมาะกับลูกค้าคนไทย @break
                  @case('google')   Google OAuth 2.0 / OIDC — เหมาะกับช่างภาพและผู้ใช้ Gmail @break
                  @case('facebook') Facebook Login — ครอบคลุมผู้ใช้ทั่วไป @break
                  @case('apple')    Sign in with Apple — ต้องมี Apple Developer Account @break
                @endswitch
              </p>
            </div>
            <label class="tw-switch" title="เปิด/ปิด {{ $meta['label'] }}">
              <input type="checkbox" name="auth_social_{{ $key }}_enabled" value="1" {{ $enabled ? 'checked' : '' }}>
              <span class="slider"></span>
            </label>
          </div>
        @endforeach
      </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         OAUTH CREDENTIALS (per provider — collapsible cards)
         ══════════════════════════════════════════════════════════════ --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900
                border border-slate-200 dark:border-white/10
                shadow-sm shadow-slate-900/5 dark:shadow-black/20 mb-5">
      <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-start gap-3">
        <span class="w-10 h-10 flex items-center justify-center rounded-xl flex-shrink-0"
              style="background:rgba(244,63,94,0.12);color:#e11d48;">
          <i class="bi bi-key-fill"></i>
        </span>
        <div class="flex-1">
          <h6 class="font-bold text-[15px] text-slate-900 dark:text-white leading-tight mb-0.5">OAuth / OpenID Connect Credentials</h6>
          <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
            Client ID + Client Secret สำหรับแต่ละ provider
            — ตั้งค่าแล้วระบบจะใช้ค่าเหล่านี้โดยอัตโนมัติ (ไม่ต้องแก้ <code class="text-[11px] bg-slate-100 dark:bg-slate-800 px-1 rounded">.env</code>)
          </p>
        </div>
      </div>

      <div class="p-5 space-y-3">

        {{-- ─────────── GOOGLE ─────────── --}}
        <div class="cred-card rounded-xl border border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-slate-800/50 overflow-hidden" id="cred-google">
          <details {{ empty($settings['google_client_id']) ? 'open' : '' }}>
            <summary class="flex items-center gap-3 px-4 py-3 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
              <span class="w-9 h-9 rounded-lg flex items-center justify-center text-white text-[15px]" style="background:#4285F4;">
                <i class="bi bi-google"></i>
              </span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-sm text-slate-800 dark:text-slate-100">Google</span>
                  <span class="status-dot {{ $providerStatus['google'] ? 'configured' : 'not-configured' }}">
                    {{ $providerStatus['google'] ? 'Configured' : 'Not configured' }}
                  </span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400">Client ID + Client Secret สำหรับ Google OAuth 2.0 / OpenID Connect</div>
              </div>
              <i class="bi bi-chevron-down chevron text-slate-400"></i>
            </summary>
            <div class="px-4 pb-4 pt-1 space-y-3">
              {{-- Redirect URI --}}
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Authorized redirect URI</label>
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-white/10">
                  <i class="bi bi-link-45deg text-slate-400 shrink-0"></i>
                  <code id="redirectGoogle" class="flex-1 min-w-0 text-[12px] md:text-[13px] font-mono break-all text-slate-700 dark:text-slate-200">{{ $redirectUris['google'] }}</code>
                  <button type="button" data-copy="redirectGoogle"
                          class="copy-btn shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold transition
                                 bg-white dark:bg-slate-700 border border-slate-300 dark:border-white/10
                                 text-slate-600 dark:text-slate-200
                                 hover:border-indigo-400 dark:hover:border-indigo-500/50">
                    <i class="bi bi-clipboard"></i> คัดลอก
                  </button>
                </div>
              </div>

              {{-- Client ID --}}
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="google_client_id">Client ID</label>
                <input type="text" id="google_client_id" name="google_client_id"
                       value="{{ $settings['google_client_id'] ?? '' }}"
                       placeholder="xxxxxxxxxxxx.apps.googleusercontent.com"
                       class="w-full px-3.5 py-2.5 rounded-lg text-sm font-mono
                              bg-white dark:bg-slate-900
                              border border-slate-200 dark:border-white/10
                              text-slate-900 dark:text-slate-100
                              placeholder-slate-400 dark:placeholder-slate-500
                              focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition">
              </div>

              {{-- Client Secret --}}
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="google_client_secret">Client Secret</label>
                <div class="flex">
                  <input type="password" id="google_client_secret" name="google_client_secret"
                         value=""
                         placeholder="{{ !empty($settings['google_client_secret']) ? '••••••• Secret saved (leave blank to keep)' : 'GOCSPX-...' }}"
                         class="flex-1 min-w-0 px-3.5 py-2.5 rounded-l-lg text-sm font-mono
                                bg-white dark:bg-slate-900
                                border border-slate-200 dark:border-white/10
                                text-slate-900 dark:text-slate-100
                                placeholder-slate-400 dark:placeholder-slate-500
                                focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition">
                  <button type="button" onclick="togglePassword('google_client_secret', 'eyeGoogle')"
                          class="px-3 rounded-r-lg text-sm bg-slate-50 dark:bg-slate-800 border border-l-0 border-slate-200 dark:border-white/10 text-slate-500 hover:text-indigo-600">
                    <i class="bi bi-eye" id="eyeGoogle"></i>
                  </button>
                </div>
              </div>

              {{-- Help --}}
              <div class="rounded-lg p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/5 text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                <i class="bi bi-info-circle mr-0.5 text-indigo-500"></i>
                สร้างใน
                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener"
                   class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">Google Cloud Console → APIs &amp; Services → Credentials</a>
                (ชนิด OAuth 2.0 Client IDs → Web application) แล้ววาง Authorized redirect URI ด้านบน
                • เป็นคีย์เดียวกันกับหน้า
                <a href="{{ route('admin.settings.google-drive') }}" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">Google Drive Settings</a>
              </div>
            </div>
          </details>
        </div>

        {{-- ─────────── LINE ─────────── --}}
        <div class="cred-card rounded-xl border border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-slate-800/50 overflow-hidden" id="cred-line">
          <details {{ empty($settings['line_channel_id']) ? 'open' : '' }}>
            <summary class="flex items-center gap-3 px-4 py-3 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
              <span class="w-9 h-9 rounded-lg flex items-center justify-center text-white text-[15px]" style="background:#06C755;">
                <i class="bi bi-line"></i>
              </span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-sm text-slate-800 dark:text-slate-100">LINE</span>
                  <span class="status-dot {{ $providerStatus['line'] ? 'configured' : 'not-configured' }}">
                    {{ $providerStatus['line'] ? 'Configured' : 'Not configured' }}
                  </span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400">Channel ID + Channel Secret จาก LINE Developers Console</div>
              </div>
              <i class="bi bi-chevron-down chevron text-slate-400"></i>
            </summary>
            <div class="px-4 pb-4 pt-1 space-y-3">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Callback URL</label>
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-white/10">
                  <i class="bi bi-link-45deg text-slate-400 shrink-0"></i>
                  <code id="redirectLine" class="flex-1 min-w-0 text-[12px] md:text-[13px] font-mono break-all text-slate-700 dark:text-slate-200">{{ $redirectUris['line'] }}</code>
                  <button type="button" data-copy="redirectLine"
                          class="copy-btn shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold transition
                                 bg-white dark:bg-slate-700 border border-slate-300 dark:border-white/10
                                 text-slate-600 dark:text-slate-200 hover:border-indigo-400">
                    <i class="bi bi-clipboard"></i> คัดลอก
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="line_channel_id">Channel ID</label>
                <input type="text" id="line_channel_id" name="line_channel_id"
                       value="{{ $settings['line_channel_id'] ?? '' }}"
                       placeholder="เช่น 1234567890"
                       class="w-full px-3.5 py-2.5 rounded-lg text-sm font-mono
                              bg-white dark:bg-slate-900
                              border border-slate-200 dark:border-white/10
                              text-slate-900 dark:text-slate-100
                              placeholder-slate-400 dark:placeholder-slate-500
                              focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition">
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="line_channel_secret">Channel Secret</label>
                <div class="flex">
                  <input type="password" id="line_channel_secret" name="line_channel_secret"
                         value=""
                         placeholder="{{ !empty($settings['line_channel_secret']) ? '••••••• Secret saved (leave blank to keep)' : 'Channel Secret' }}"
                         class="flex-1 min-w-0 px-3.5 py-2.5 rounded-l-lg text-sm font-mono
                                bg-white dark:bg-slate-900
                                border border-slate-200 dark:border-white/10
                                text-slate-900 dark:text-slate-100
                                placeholder-slate-400 dark:placeholder-slate-500
                                focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition">
                  <button type="button" onclick="togglePassword('line_channel_secret', 'eyeLine')"
                          class="px-3 rounded-r-lg text-sm bg-slate-50 dark:bg-slate-800 border border-l-0 border-slate-200 dark:border-white/10 text-slate-500 hover:text-indigo-600">
                    <i class="bi bi-eye" id="eyeLine"></i>
                  </button>
                </div>
              </div>

              <div class="rounded-lg p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/5 text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                <i class="bi bi-info-circle mr-0.5 text-emerald-500"></i>
                สร้างใน
                <a href="https://developers.line.biz/console/" target="_blank" rel="noopener"
                   class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">LINE Developers Console</a>
                → Channel (LINE Login) → ตั้ง Callback URL ให้ตรงกับด้านบน
                • เป็นคีย์เดียวกันกับหน้า
                <a href="{{ route('admin.settings.line') }}" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">LINE Settings</a>
                (ใช้ทั้ง Login และ Messaging API)
              </div>
            </div>
          </details>
        </div>

        {{-- ─────────── FACEBOOK ─────────── --}}
        <div class="cred-card rounded-xl border border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-slate-800/50 overflow-hidden" id="cred-facebook">
          <details {{ empty($settings['facebook_client_id']) ? 'open' : '' }}>
            <summary class="flex items-center gap-3 px-4 py-3 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
              <span class="w-9 h-9 rounded-lg flex items-center justify-center text-white text-[15px]" style="background:#1877F2;">
                <i class="bi bi-facebook"></i>
              </span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-sm text-slate-800 dark:text-slate-100">Facebook</span>
                  <span class="status-dot {{ $providerStatus['facebook'] ? 'configured' : 'not-configured' }}">
                    {{ $providerStatus['facebook'] ? 'Configured' : 'Not configured' }}
                  </span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400">App ID + App Secret จาก Meta for Developers</div>
              </div>
              <i class="bi bi-chevron-down chevron text-slate-400"></i>
            </summary>
            <div class="px-4 pb-4 pt-1 space-y-3">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Valid OAuth Redirect URI</label>
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-white/10">
                  <i class="bi bi-link-45deg text-slate-400 shrink-0"></i>
                  <code id="redirectFacebook" class="flex-1 min-w-0 text-[12px] md:text-[13px] font-mono break-all text-slate-700 dark:text-slate-200">{{ $redirectUris['facebook'] }}</code>
                  <button type="button" data-copy="redirectFacebook"
                          class="copy-btn shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold transition
                                 bg-white dark:bg-slate-700 border border-slate-300 dark:border-white/10
                                 text-slate-600 dark:text-slate-200 hover:border-indigo-400">
                    <i class="bi bi-clipboard"></i> คัดลอก
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="facebook_client_id">App ID (Client ID)</label>
                <input type="text" id="facebook_client_id" name="facebook_client_id"
                       value="{{ $settings['facebook_client_id'] ?? '' }}"
                       placeholder="เช่น 1234567890123456"
                       class="w-full px-3.5 py-2.5 rounded-lg text-sm font-mono
                              bg-white dark:bg-slate-900
                              border border-slate-200 dark:border-white/10
                              text-slate-900 dark:text-slate-100
                              placeholder-slate-400 dark:placeholder-slate-500
                              focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition">
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="facebook_client_secret">App Secret (Client Secret)</label>
                <div class="flex">
                  <input type="password" id="facebook_client_secret" name="facebook_client_secret"
                         value=""
                         placeholder="{{ !empty($settings['facebook_client_secret']) ? '••••••• Secret saved (leave blank to keep)' : '32-hex chars' }}"
                         class="flex-1 min-w-0 px-3.5 py-2.5 rounded-l-lg text-sm font-mono
                                bg-white dark:bg-slate-900
                                border border-slate-200 dark:border-white/10
                                text-slate-900 dark:text-slate-100
                                placeholder-slate-400 dark:placeholder-slate-500
                                focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition">
                  <button type="button" onclick="togglePassword('facebook_client_secret', 'eyeFb')"
                          class="px-3 rounded-r-lg text-sm bg-slate-50 dark:bg-slate-800 border border-l-0 border-slate-200 dark:border-white/10 text-slate-500 hover:text-indigo-600">
                    <i class="bi bi-eye" id="eyeFb"></i>
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="facebook_redirect_uri">
                  Custom Redirect URI
                  <span class="font-normal text-slate-400 dark:text-slate-500">— ไม่บังคับ, เว้นว่างเพื่อใช้ค่าด้านบน</span>
                </label>
                <input type="text" id="facebook_redirect_uri" name="facebook_redirect_uri"
                       value="{{ $settings['facebook_redirect_uri'] ?? '' }}"
                       placeholder="{{ $redirectUris['facebook'] }}"
                       class="w-full px-3.5 py-2.5 rounded-lg text-sm font-mono
                              bg-white dark:bg-slate-900
                              border border-slate-200 dark:border-white/10
                              text-slate-900 dark:text-slate-100
                              placeholder-slate-400 dark:placeholder-slate-500
                              focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition">
              </div>

              <div class="rounded-lg p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/5 text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                <i class="bi bi-info-circle mr-0.5 text-blue-500"></i>
                สร้างใน
                <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener"
                   class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">Meta for Developers → My Apps</a>
                → Facebook Login → Settings → Valid OAuth Redirect URIs
                • ค่าที่บันทึกที่นี่จะแทนที่ <code class="text-[10px] bg-slate-100 dark:bg-slate-800 px-1 rounded">FB_APP_ID</code> / <code class="text-[10px] bg-slate-100 dark:bg-slate-800 px-1 rounded">FB_APP_SECRET</code> ใน .env โดยอัตโนมัติ
              </div>
            </div>
          </details>
        </div>

        {{-- ─────────── APPLE ─────────── --}}
        <div class="cred-card rounded-xl border border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-slate-800/50 overflow-hidden" id="cred-apple">
          <details {{ empty($settings['apple_client_id']) ? 'open' : '' }}>
            <summary class="flex items-center gap-3 px-4 py-3 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
              <span class="w-9 h-9 rounded-lg flex items-center justify-center text-white text-[15px]" style="background:#000;">
                <i class="bi bi-apple"></i>
              </span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-sm text-slate-800 dark:text-slate-100">Apple</span>
                  <span class="status-dot {{ $providerStatus['apple'] ? 'configured' : 'not-configured' }}">
                    {{ $providerStatus['apple'] ? 'Configured' : 'Not configured' }}
                  </span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400">Service ID + Team ID + Key ID + Private Key (.p8) สำหรับ Sign in with Apple</div>
              </div>
              <i class="bi bi-chevron-down chevron text-slate-400"></i>
            </summary>
            <div class="px-4 pb-4 pt-1 space-y-3">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Return URL</label>
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-white/10">
                  <i class="bi bi-link-45deg text-slate-400 shrink-0"></i>
                  <code id="redirectApple" class="flex-1 min-w-0 text-[12px] md:text-[13px] font-mono break-all text-slate-700 dark:text-slate-200">{{ $redirectUris['apple'] }}</code>
                  <button type="button" data-copy="redirectApple"
                          class="copy-btn shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold transition
                                 bg-white dark:bg-slate-700 border border-slate-300 dark:border-white/10
                                 text-slate-600 dark:text-slate-200 hover:border-indigo-400">
                    <i class="bi bi-clipboard"></i> คัดลอก
                  </button>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="apple_client_id">Service ID (Client ID)</label>
                  <input type="text" id="apple_client_id" name="apple_client_id"
                         value="{{ $settings['apple_client_id'] ?? '' }}"
                         placeholder="com.example.web"
                         class="w-full px-3.5 py-2.5 rounded-lg text-sm font-mono bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="apple_team_id">Team ID</label>
                  <input type="text" id="apple_team_id" name="apple_team_id"
                         value="{{ $settings['apple_team_id'] ?? '' }}"
                         placeholder="10-char team ID"
                         class="w-full px-3.5 py-2.5 rounded-lg text-sm font-mono bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition">
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="apple_key_id">Key ID</label>
                <input type="text" id="apple_key_id" name="apple_key_id"
                       value="{{ $settings['apple_key_id'] ?? '' }}"
                       placeholder="10-char key ID"
                       class="w-full px-3.5 py-2.5 rounded-lg text-sm font-mono bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition">
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="apple_private_key">Private Key (.p8 content)</label>
                <div class="relative">
                  <textarea id="apple_private_key" name="apple_private_key" rows="5"
                            placeholder="{{ !empty($settings['apple_private_key']) ? '••••••• Private key saved (leave blank to keep)' : "-----BEGIN PRIVATE KEY-----\nMIGTAgEA...\n-----END PRIVATE KEY-----" }}"
                            class="w-full px-3.5 py-2.5 rounded-lg text-[12px] font-mono bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition resize-y"></textarea>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                  วางเนื้อหาของไฟล์ <code class="text-[10px] bg-slate-100 dark:bg-slate-800 px-1 rounded">AuthKey_XXXXXXXXXX.p8</code> ทั้งไฟล์ (รวมบรรทัด BEGIN/END)
                </p>
              </div>

              <div class="rounded-lg p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/5 text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                <i class="bi bi-info-circle mr-0.5 text-slate-500"></i>
                สร้างใน
                <a href="https://developer.apple.com/account/resources/identifiers/list" target="_blank" rel="noopener"
                   class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">Apple Developer → Certificates, Identifiers &amp; Profiles</a>
                (Services IDs + Keys) • ต้อง verify domain และเพิ่ม Return URL ด้านบนใน Service ID
              </div>
            </div>
          </details>
        </div>

      </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         EMAIL REGISTRATION + LINE CONNECT ENFORCEMENT (side by side)
         ══════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
      {{-- Email registration --}}
      <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-start gap-3">
          <span class="w-10 h-10 flex items-center justify-center rounded-xl flex-shrink-0" style="background:rgba(16,185,129,0.12);color:#059669;">
            <i class="bi bi-envelope-at"></i>
          </span>
          <div>
            <h6 class="font-bold text-[15px] text-slate-900 dark:text-white leading-tight mb-0.5">การสมัครด้วยอีเมล</h6>
            <p class="text-xs text-slate-500 dark:text-slate-400">อนุญาตให้สมัครด้วยอีเมล + รหัสผ่านแบบคลาสสิก</p>
          </div>
        </div>
        <div class="p-5">
          @php $emailEnabled = ($settings['auth_email_registration_enabled'] ?? '1') === '1'; @endphp
          <div class="flex items-center justify-between gap-3 py-2">
            <div class="min-w-0">
              <div class="text-sm font-medium text-slate-800 dark:text-slate-100">เปิดการสมัครด้วยอีเมล</div>
              <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">ถ้าปิด ผู้ใช้ต้องใช้ Social Login เท่านั้น</div>
            </div>
            <label class="tw-switch">
              <input type="checkbox" name="auth_email_registration_enabled" value="1" {{ $emailEnabled ? 'checked' : '' }}>
              <span class="slider"></span>
            </label>
          </div>
        </div>
      </div>

      {{-- LINE Connect enforcement --}}
      <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-start gap-3">
          <span class="w-10 h-10 flex items-center justify-center rounded-xl flex-shrink-0" style="background:rgba(6,199,85,0.12);color:#06C755;">
            <i class="bi bi-line"></i>
          </span>
          <div>
            <h6 class="font-bold text-[15px] text-slate-900 dark:text-white leading-tight mb-0.5">การเชื่อมต่อ LINE</h6>
            <p class="text-xs text-slate-500 dark:text-slate-400">ควบคุมให้ทุกบัญชีต้องผูก LINE เพื่อรับแจ้งเตือน</p>
          </div>
        </div>
        <div class="p-5 divide-y divide-slate-200 dark:divide-white/5">
          @php
            $requireLine = ($settings['auth_require_line_connect'] ?? '1') === '1';
            $allowSkip   = ($settings['auth_allow_line_connect_skip'] ?? '1') === '1';
          @endphp
          <div class="flex items-center justify-between gap-3 py-3">
            <div class="min-w-0">
              <div class="text-sm font-medium text-slate-800 dark:text-slate-100">บังคับเชื่อมต่อ LINE หลังสมัคร</div>
              <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">สำหรับผู้ที่สมัครผ่าน Google / Facebook / อีเมล</div>
            </div>
            <label class="tw-switch">
              <input type="checkbox" name="auth_require_line_connect" value="1" {{ $requireLine ? 'checked' : '' }}>
              <span class="slider"></span>
            </label>
          </div>
          <div class="flex items-center justify-between gap-3 py-3">
            <div class="min-w-0">
              <div class="text-sm font-medium text-slate-800 dark:text-slate-100">อนุญาตให้ข้าม (Skip)</div>
              <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">ถ้าปิด ผู้ใช้จะถูกล็อกไว้ที่หน้าผูก LINE จนกว่าจะเชื่อมต่อ</div>
            </div>
            <label class="tw-switch">
              <input type="checkbox" name="auth_allow_line_connect_skip" value="1" {{ $allowSkip ? 'checked' : '' }}>
              <span class="slider"></span>
            </label>
          </div>
        </div>
      </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         DEFAULT PROVIDER PER ROLE
         ══════════════════════════════════════════════════════════════ --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-6">
      <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-start gap-3">
        <span class="w-10 h-10 flex items-center justify-center rounded-xl flex-shrink-0" style="background:rgba(244,63,94,0.12);color:#e11d48;">
          <i class="bi bi-people-fill"></i>
        </span>
        <div>
          <h6 class="font-bold text-[15px] text-slate-900 dark:text-white leading-tight mb-0.5">Provider แนะนำต่อบทบาท</h6>
          <p class="text-xs text-slate-500 dark:text-slate-400">ปุ่ม &ldquo;แนะนำ&rdquo; ในหน้าสมัคร จะใช้ค่านี้</p>
        </div>
      </div>
      <div class="p-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">สำหรับลูกค้า (Customer)</label>
            <select name="auth_default_customer_provider"
                    class="w-full px-4 py-2.5 border border-slate-200 dark:border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white dark:bg-slate-900 dark:text-slate-100">
              @foreach($providers as $key => $meta)
                <option value="{{ $key }}" {{ ($settings['auth_default_customer_provider'] ?? 'line') === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
              @endforeach
            </select>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">ลูกค้าคนไทยส่วนมากใช้ LINE</div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">สำหรับช่างภาพ (Photographer)</label>
            <select name="auth_default_photographer_provider"
                    class="w-full px-4 py-2.5 border border-slate-200 dark:border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white dark:bg-slate-900 dark:text-slate-100">
              @foreach($providers as $key => $meta)
                <option value="{{ $key }}" {{ ($settings['auth_default_photographer_provider'] ?? 'google') === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
              @endforeach
            </select>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">ช่างภาพใช้ Google Drive อยู่แล้ว แนะนำ Google</div>
          </div>
        </div>
      </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         FOOTER ACTIONS
         ══════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center justify-end gap-3 flex-wrap">
      <button type="submit"
              class="inline-flex items-center gap-1.5 px-6 py-2.5 rounded-lg text-sm font-semibold text-white transition
                     bg-gradient-to-br from-indigo-600 to-violet-600
                     shadow-md shadow-indigo-500/30
                     hover:shadow-lg hover:shadow-indigo-500/40 hover:-translate-y-0.5
                     active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed">
        <i class="bi bi-floppy-fill"></i> บันทึกการตั้งค่า
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
// ─── Toggle password field visibility ───────────────────────────
function togglePassword(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  if (!input || !icon) return;
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}

// ─── Copy-to-clipboard for every .copy-btn[data-copy] ───────────
document.querySelectorAll('.copy-btn[data-copy]').forEach(btn => {
  btn.addEventListener('click', async () => {
    const targetId = btn.dataset.copy;
    const el = document.getElementById(targetId);
    if (!el) return;
    const text = el.textContent.trim();
    const original = btn.innerHTML;
    const markCopied = () => {
      btn.classList.add('copied');
      btn.innerHTML = '<i class="bi bi-check2"></i> คัดลอกแล้ว';
      setTimeout(() => {
        btn.classList.remove('copied');
        btn.innerHTML = original;
      }, 1800);
    };
    try {
      await navigator.clipboard.writeText(text);
      markCopied();
    } catch {
      // fallback for older browsers
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      markCopied();
    }
  });
});

// ─── Open a credential card from the "ตั้งค่า Credentials" link ──
function openCredCard(provider) {
  const card = document.getElementById('cred-' + provider);
  if (!card) return;
  const details = card.querySelector('details');
  if (details && !details.open) details.open = true;
  setTimeout(() => {
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, 50);
}

// ─── Form submit: inline loading spinner on save button ─────────
document.getElementById('socialAuthForm').addEventListener('submit', function() {
  const btn = this.querySelector('button[type=submit]');
  if (!btn) return;
  const original = btn.innerHTML;
  btn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2 align-[-2px]"></span>กำลังบันทึก...';
  btn.disabled = true;
  setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 8000);
});
</script>
@endpush
