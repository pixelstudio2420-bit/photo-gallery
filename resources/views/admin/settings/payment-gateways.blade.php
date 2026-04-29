@extends('layouts.admin')

@section('title', 'Payment Gateways')

@push('styles')
@include('admin.settings._shared-styles')
@endpush

@php
  // Helper: configuration status
  $cfg = fn($key) => !empty($settings[$key] ?? '');

  // Icon tile color classes (Tailwind safelist these since they're static strings)
  $accent = [
    'purple' => 'bg-violet-100 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400',
    'blue'   => 'bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400',
    'green'  => 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    'orange' => 'bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400',
    'pink'   => 'bg-pink-100 dark:bg-pink-500/15 text-pink-600 dark:text-pink-400',
    'cyan'   => 'bg-cyan-100 dark:bg-cyan-500/15 text-cyan-600 dark:text-cyan-400',
    'red'    => 'bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400',
  ];
@endphp

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ═══════════════════ Header ═══════════════════ --}}
  <div class="mb-8">
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition mb-4">
      <i class="bi bi-arrow-left"></i> Back to Settings
    </a>

    <div class="flex items-start gap-4">
      <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
        <i class="bi bi-credit-card-2-front text-white text-xl"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
          Payment Gateways
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Configure payment provider credentials and sandbox modes
        </p>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Flash Messages ═══════════════════ --}}
  @if(session('success'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
      <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-lg"></i>
      <div class="flex-1 text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ session('success') }}</div>
    </div>
  @endif
  @if(session('error'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30">
      <i class="bi bi-exclamation-triangle-fill text-rose-600 dark:text-rose-400 text-lg"></i>
      <div class="flex-1 text-sm font-medium text-rose-800 dark:text-rose-300">{{ session('error') }}</div>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.payment-gateways.update') }}" id="pgForm" class="space-y-6">
    @csrf

    {{-- ═══════════════════ Stripe ═══════════════════ --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl {{ $accent['purple'] }} flex items-center justify-center">
            <i class="bi bi-stripe text-lg"></i>
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Stripe</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Credit/debit card payments via Stripe</p>
          </div>
        </div>
        <span class="status-dot {{ $cfg('stripe_public_key') ? 'connected' : 'disconnected' }}">
          {{ $cfg('stripe_public_key') ? 'Configured' : 'Not configured' }}
        </span>
      </div>
      <div class="p-6">
        <div class="mb-5 flex items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
          <div>
            <div class="text-sm font-semibold text-slate-900 dark:text-white">Enable Stripe</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Accept credit/debit card payments</div>
          </div>
          <label class="tw-switch">
            <input type="checkbox" name="stripe_enabled" id="stripe_enabled" {{ ($settings['stripe_enabled'] ?? '') === '1' ? 'checked' : '' }}>
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Publishable Key</label>
            <input type="text" name="stripe_public_key"
                   value="{{ $settings['stripe_public_key'] ?? '' }}" placeholder="pk_test_..."
                   class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Stripe publishable (public) key.</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Secret Key</label>
            <div class="relative">
              <input type="password" name="stripe_secret_key" id="stripe_secret_key"
                     value="{{ $settings['stripe_secret_key'] ?? '' }}"
                     placeholder="{{ $cfg('stripe_secret_key') ? 'Secret saved' : 'sk_test_...' }}"
                     autocomplete="new-password"
                     class="w-full pr-10 px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <button type="button" class="toggle-pw absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-target="stripe_secret_key">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Leave blank to keep existing value.</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Webhook Secret</label>
            <div class="relative">
              <input type="password" name="stripe_webhook_secret" id="stripe_webhook_secret"
                     value="{{ $settings['stripe_webhook_secret'] ?? '' }}"
                     placeholder="{{ $cfg('stripe_webhook_secret') ? 'Secret saved' : 'whsec_...' }}"
                     autocomplete="new-password"
                     class="w-full pr-10 px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <button type="button" class="toggle-pw absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-target="stripe_webhook_secret">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Webhook signing secret for verifying events.</p>
          </div>
          <div class="md:col-span-2 lg:col-span-3">
            <div class="flex items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
              <div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white">Sandbox Mode</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Use Stripe test environment</div>
              </div>
              <label class="tw-switch">
                <input type="checkbox" name="stripe_sandbox" {{ ($settings['stripe_sandbox'] ?? '1') === '1' ? 'checked' : '' }}>
                <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══════════════════ Omise ═══════════════════ --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl {{ $accent['blue'] }} flex items-center justify-center">
            <i class="bi bi-shield-lock text-lg"></i>
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Omise</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Thai payment gateway (credit card, internet banking)</p>
          </div>
        </div>
        <span class="status-dot {{ $cfg('omise_public_key') ? 'connected' : 'disconnected' }}">
          {{ $cfg('omise_public_key') ? 'Configured' : 'Not configured' }}
        </span>
      </div>
      <div class="p-6">
        <div class="mb-5 flex items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
          <div>
            <div class="text-sm font-semibold text-slate-900 dark:text-white">Enable Omise</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Accept payments via Omise (Thailand)</div>
          </div>
          <label class="tw-switch">
            <input type="checkbox" name="omise_enabled" {{ ($settings['omise_enabled'] ?? '') === '1' ? 'checked' : '' }}>
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Public Key</label>
            <input type="text" name="omise_public_key"
                   value="{{ $settings['omise_public_key'] ?? '' }}" placeholder="pkey_test_..."
                   class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Secret Key</label>
            <div class="relative">
              <input type="password" name="omise_secret_key" id="omise_secret_key"
                     value="{{ $settings['omise_secret_key'] ?? '' }}"
                     placeholder="{{ $cfg('omise_secret_key') ? 'Secret saved' : 'skey_test_...' }}"
                     autocomplete="new-password"
                     class="w-full pr-10 px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <button type="button" class="toggle-pw absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-target="omise_secret_key">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Leave blank to keep existing value.</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Webhook Secret</label>
            <div class="relative">
              <input type="password" name="omise_webhook_secret" id="omise_webhook_secret"
                     value="{{ $settings['omise_webhook_secret'] ?? '' }}"
                     placeholder="{{ $cfg('omise_webhook_secret') ? 'Secret saved' : 'Set in Omise dashboard' }}"
                     autocomplete="new-password"
                     class="w-full pr-10 px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <button type="button" class="toggle-pw absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-target="omise_webhook_secret">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">
              HMAC-SHA256 secret สำหรับตรวจลายเซ็น webhook. หากเว้นว่าง — webhook จะยังทำงานแต่จะไม่ verify signature (ไม่แนะนำใน production).
            </p>
          </div>
          <div>
            <div class="flex items-center justify-between gap-4 h-full p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
              <div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white">Sandbox Mode</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Test environment</div>
              </div>
              <label class="tw-switch">
                <input type="checkbox" name="omise_sandbox" {{ ($settings['omise_sandbox'] ?? '1') === '1' ? 'checked' : '' }}>
                <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
              </label>
            </div>
          </div>
        </div>

        {{-- ── Webhook URL info box — tells admin what to paste into the Omise dashboard ── --}}
        <div class="mt-5 rounded-xl border border-sky-200 dark:border-sky-500/30 bg-sky-50 dark:bg-sky-500/10 p-4">
          <div class="flex items-start gap-3">
            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-sky-100 dark:bg-sky-500/20 text-sky-600 dark:text-sky-300 flex items-center justify-center">
              <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-semibold text-sky-900 dark:text-sky-200 mb-1">
                Webhook Endpoints
              </div>
              <p class="text-xs text-sky-800/90 dark:text-sky-300/80 mb-3 leading-relaxed">
                ไปที่ Omise Dashboard → Webhooks → Add endpoint แล้วใส่ URL ด้านล่างนี้ ระบบจะรับเหตุการณ์ <code class="px-1 py-0.5 rounded bg-white dark:bg-slate-800 text-[11px] font-mono">charge.complete</code>, <code class="px-1 py-0.5 rounded bg-white dark:bg-slate-800 text-[11px] font-mono">refund.create</code>, <code class="px-1 py-0.5 rounded bg-white dark:bg-slate-800 text-[11px] font-mono">transfer.*</code> เพื่อสร้างลิงก์ดาวน์โหลดให้ลูกค้าอัตโนมัติหลังชำระเงินสำเร็จ
              </p>

              <div class="space-y-2">
                @php
                  $omiseChargeUrl   = url('/api/webhooks/omise');
                  $omiseTransferUrl = url('/api/webhooks/omise-transfer');
                @endphp

                <div>
                  <div class="text-[11px] font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-400 mb-1">Charge webhook (บัตรเครดิต / Internet banking)</div>
                  <div class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-sky-200 dark:border-sky-500/30 rounded-lg px-3 py-2">
                    <i class="bi bi-link-45deg text-sky-500 shrink-0"></i>
                    <code class="flex-1 text-xs font-mono text-slate-700 dark:text-slate-200 break-all select-all">{{ $omiseChargeUrl }}</code>
                    <button type="button" data-copy="{{ $omiseChargeUrl }}"
                            class="copy-url shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md bg-sky-100 dark:bg-sky-500/20 text-sky-700 dark:text-sky-300 hover:bg-sky-200 dark:hover:bg-sky-500/30 text-xs font-medium transition">
                      <i class="bi bi-clipboard"></i> <span class="copy-label">Copy</span>
                    </button>
                  </div>
                </div>

                <div>
                  <div class="text-[11px] font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-400 mb-1">Transfer webhook (สำหรับ payout PromptPay — optional)</div>
                  <div class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-sky-200 dark:border-sky-500/30 rounded-lg px-3 py-2">
                    <i class="bi bi-link-45deg text-sky-500 shrink-0"></i>
                    <code class="flex-1 text-xs font-mono text-slate-700 dark:text-slate-200 break-all select-all">{{ $omiseTransferUrl }}</code>
                    <button type="button" data-copy="{{ $omiseTransferUrl }}"
                            class="copy-url shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md bg-sky-100 dark:bg-sky-500/20 text-sky-700 dark:text-sky-300 hover:bg-sky-200 dark:hover:bg-sky-500/30 text-xs font-medium transition">
                      <i class="bi bi-clipboard"></i> <span class="copy-label">Copy</span>
                    </button>
                  </div>
                </div>
              </div>

              <p class="text-[11px] text-sky-800/70 dark:text-sky-300/70 mt-3">
                <i class="bi bi-info-circle"></i>
                หลังตั้งค่าแล้ว Omise จะ generate "Endpoint secret" ให้ — copy ไปใส่ช่อง <strong>Webhook Secret</strong> ด้านบนเพื่อเปิด signature verification.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══════════════════ PayPal ═══════════════════ --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl {{ $accent['blue'] }} flex items-center justify-center">
            <i class="bi bi-paypal text-lg"></i>
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">PayPal</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">International payments via PayPal REST API</p>
          </div>
        </div>
        <span class="status-dot {{ $cfg('paypal_client_id') ? 'connected' : 'disconnected' }}">
          {{ $cfg('paypal_client_id') ? 'Configured' : 'Not configured' }}
        </span>
      </div>
      <div class="p-6">
        <div class="mb-5 flex items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
          <div>
            <div class="text-sm font-semibold text-slate-900 dark:text-white">Enable PayPal</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Accept PayPal and international card payments</div>
          </div>
          <label class="tw-switch">
            <input type="checkbox" name="paypal_enabled" {{ ($settings['paypal_enabled'] ?? '') === '1' ? 'checked' : '' }}>
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Client ID</label>
            <input type="text" name="paypal_client_id"
                   value="{{ $settings['paypal_client_id'] ?? '' }}" placeholder="AX..."
                   class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Secret</label>
            <div class="relative">
              <input type="password" name="paypal_secret" id="paypal_secret"
                     value="{{ $settings['paypal_secret'] ?? '' }}"
                     placeholder="{{ $cfg('paypal_secret') ? 'Secret saved' : 'EL...' }}"
                     autocomplete="new-password"
                     class="w-full pr-10 px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <button type="button" class="toggle-pw absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-target="paypal_secret">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Leave blank to keep existing value.</p>
          </div>
          <div>
            <div class="flex items-center justify-between gap-4 h-full p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
              <div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white">Sandbox Mode</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">PayPal sandbox</div>
              </div>
              <label class="tw-switch">
                <input type="checkbox" name="paypal_sandbox" {{ ($settings['paypal_sandbox'] ?? '1') === '1' ? 'checked' : '' }}>
                <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══════════════════ LINE Pay ═══════════════════ --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl {{ $accent['green'] }} flex items-center justify-center">
            <i class="bi bi-chat-dots text-lg"></i>
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">LINE Pay</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Mobile payments via LINE Pay API v3</p>
          </div>
        </div>
        <span class="status-dot {{ $cfg('line_pay_channel_id') ? 'connected' : 'disconnected' }}">
          {{ $cfg('line_pay_channel_id') ? 'Configured' : 'Not configured' }}
        </span>
      </div>
      <div class="p-6">
        <div class="mb-5 flex items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
          <div>
            <div class="text-sm font-semibold text-slate-900 dark:text-white">Enable LINE Pay</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Accept LINE Pay mobile payments</div>
          </div>
          <label class="tw-switch">
            <input type="checkbox" name="line_pay_enabled" {{ ($settings['line_pay_enabled'] ?? '') === '1' ? 'checked' : '' }}>
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Channel ID</label>
            <input type="text" name="line_pay_channel_id"
                   value="{{ $settings['line_pay_channel_id'] ?? '' }}" placeholder="Channel ID"
                   class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Channel Secret</label>
            <div class="relative">
              <input type="password" name="line_pay_channel_secret" id="line_pay_channel_secret"
                     value="{{ $settings['line_pay_channel_secret'] ?? '' }}"
                     placeholder="{{ $cfg('line_pay_channel_secret') ? 'Secret saved' : 'Channel Secret' }}"
                     autocomplete="new-password"
                     class="w-full pr-10 px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <button type="button" class="toggle-pw absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-target="line_pay_channel_secret">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Leave blank to keep existing value.</p>
          </div>
          <div>
            <div class="flex items-center justify-between gap-4 h-full p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
              <div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white">Sandbox Mode</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">LINE Pay sandbox</div>
              </div>
              <label class="tw-switch">
                <input type="checkbox" name="line_pay_sandbox" {{ ($settings['line_pay_sandbox'] ?? '1') === '1' ? 'checked' : '' }}>
                <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══════════════════ PromptPay ═══════════════════ --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl {{ $accent['cyan'] }} flex items-center justify-center">
            <i class="bi bi-qr-code text-lg"></i>
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">PromptPay</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Thai QR code payment via PromptPay</p>
          </div>
        </div>
        <span class="status-dot {{ $cfg('promptpay_number') ? 'connected' : 'disconnected' }}">
          {{ $cfg('promptpay_number') ? 'Configured' : 'Not configured' }}
        </span>
      </div>
      <div class="p-6">
        <div class="mb-5 flex items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
          <div>
            <div class="text-sm font-semibold text-slate-900 dark:text-white">Enable PromptPay</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Accept Thai QR code payments</div>
          </div>
          <label class="tw-switch">
            <input type="checkbox" name="promptpay_enabled" {{ ($settings['promptpay_enabled'] ?? '') === '1' ? 'checked' : '' }}>
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">PromptPay Number</label>
            <input type="text" name="promptpay_number"
                   value="{{ $settings['promptpay_number'] ?? '' }}" placeholder="Phone or National ID"
                   class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Phone number or National ID for receiving PromptPay transfers.</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Account Name</label>
            <input type="text" name="promptpay_name"
                   value="{{ $settings['promptpay_name'] ?? '' }}" placeholder="Account holder name"
                   class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Display name shown to customers on the payment page.</p>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══════════════════ TrueMoney Wallet ═══════════════════ --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl {{ $accent['orange'] }} flex items-center justify-center">
            <i class="bi bi-wallet2 text-lg"></i>
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">TrueMoney Wallet</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Thai e-wallet payments via TrueMoney</p>
          </div>
        </div>
        <span class="status-dot {{ $cfg('truemoney_merchant_id') ? 'connected' : 'disconnected' }}">
          {{ $cfg('truemoney_merchant_id') ? 'Configured' : 'Not configured' }}
        </span>
      </div>
      <div class="p-6">
        <div class="mb-5 flex items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
          <div>
            <div class="text-sm font-semibold text-slate-900 dark:text-white">Enable TrueMoney</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Accept TrueMoney Wallet payments</div>
          </div>
          <label class="tw-switch">
            <input type="checkbox" name="truemoney_enabled" {{ ($settings['truemoney_enabled'] ?? '') === '1' ? 'checked' : '' }}>
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Merchant ID</label>
            <input type="text" name="truemoney_merchant_id"
                   value="{{ $settings['truemoney_merchant_id'] ?? '' }}" placeholder="Merchant ID"
                   class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Secret Key</label>
            <div class="relative">
              <input type="password" name="truemoney_secret" id="truemoney_secret"
                     value="{{ $settings['truemoney_secret'] ?? '' }}"
                     placeholder="{{ $cfg('truemoney_secret') ? 'Secret saved' : 'Secret Key' }}"
                     autocomplete="new-password"
                     class="w-full pr-10 px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <button type="button" class="toggle-pw absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-target="truemoney_secret">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Leave blank to keep existing value.</p>
          </div>
          <div>
            <div class="flex items-center justify-between gap-4 h-full p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
              <div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white">Sandbox Mode</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Test environment</div>
              </div>
              <label class="tw-switch">
                <input type="checkbox" name="truemoney_sandbox" {{ ($settings['truemoney_sandbox'] ?? '1') === '1' ? 'checked' : '' }}>
                <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══════════════════ 2C2P ═══════════════════ --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl {{ $accent['red'] }} flex items-center justify-center">
            <i class="bi bi-globe text-lg"></i>
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">2C2P</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Southeast Asia payment gateway (cards, e-wallets, banking)</p>
          </div>
        </div>
        <span class="status-dot {{ $cfg('2c2p_merchant_id') ? 'connected' : 'disconnected' }}">
          {{ $cfg('2c2p_merchant_id') ? 'Configured' : 'Not configured' }}
        </span>
      </div>
      <div class="p-6">
        <div class="mb-5 flex items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
          <div>
            <div class="text-sm font-semibold text-slate-900 dark:text-white">Enable 2C2P</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Accept payments via 2C2P payment gateway</div>
          </div>
          <label class="tw-switch">
            <input type="checkbox" name="2c2p_enabled" {{ ($settings['2c2p_enabled'] ?? '') === '1' ? 'checked' : '' }}>
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Merchant ID</label>
            <input type="text" name="2c2p_merchant_id"
                   value="{{ $settings['2c2p_merchant_id'] ?? '' }}" placeholder="Merchant ID"
                   class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Secret Key</label>
            <div class="relative">
              <input type="password" name="2c2p_secret_key" id="2c2p_secret_key"
                     value="{{ $settings['2c2p_secret_key'] ?? '' }}"
                     placeholder="{{ $cfg('2c2p_secret_key') ? 'Secret saved' : 'Secret Key' }}"
                     autocomplete="new-password"
                     class="w-full pr-10 px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <button type="button" class="toggle-pw absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-target="2c2p_secret_key">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Leave blank to keep existing value.</p>
          </div>
          <div>
            <div class="flex items-center justify-between gap-4 h-full p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
              <div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white">Sandbox Mode</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Test environment</div>
              </div>
              <label class="tw-switch">
                <input type="checkbox" name="2c2p_sandbox" {{ ($settings['2c2p_sandbox'] ?? '1') === '1' ? 'checked' : '' }}>
                <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══════════════════ Save Button ═══════════════════ --}}
    <div class="flex justify-end">
      <button type="submit"
              class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 shadow-lg shadow-indigo-500/20 transition">
        <i class="bi bi-check2-circle"></i>
        <span>Save All Settings</span>
      </button>
    </div>

  </form>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.toggle-pw').forEach(btn => {
  btn.addEventListener('click', function() {
    const input = document.getElementById(this.dataset.target);
    if (!input) return;
    const icon = this.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
  });
});

// Copy-to-clipboard for webhook URL chips. Falls back to a select+execCommand
// path on insecure origins (e.g. local http) where navigator.clipboard is
// gated by the Permissions API.
document.querySelectorAll('.copy-url').forEach(btn => {
  btn.addEventListener('click', async function() {
    const text = this.dataset.copy || '';
    const label = this.querySelector('.copy-label');
    const icon  = this.querySelector('i');
    if (!text) return;

    const showCopied = () => {
      if (label) label.textContent = 'Copied!';
      if (icon)  icon.classList.replace('bi-clipboard', 'bi-check2');
      setTimeout(() => {
        if (label) label.textContent = 'Copy';
        if (icon)  icon.classList.replace('bi-check2', 'bi-clipboard');
      }, 1500);
    };

    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
      } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
      }
      showCopied();
    } catch (e) {
      console.error('Copy failed', e);
    }
  });
});
</script>
@endpush
