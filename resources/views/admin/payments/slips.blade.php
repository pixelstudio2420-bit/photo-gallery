@extends('layouts.admin')

@section('title', 'สลิปการโอนเงิน')

@php
  // Compute auto-refresh need ONCE per request — used by both the head
  // meta tag below and the visible "อัปเดตอัตโนมัติ" pill in the page
  // header. Only true when SlipOK is enabled AND at least one slip is in
  // the active retry window (uploaded within last 30min, no transRef yet).
  // After 30min the async retry job has exhausted its 3 attempts so a
  // refresh won't change anything — admin can refresh manually if they
  // want to see late callbacks.
  $needsRefresh = false;
  if (!empty($settings['slipok_enabled'])) {
      $needsRefresh = \App\Models\PaymentSlip::where('verify_status', 'pending')
          ->whereNull('slipok_trans_ref')
          ->where('created_at', '>=', now()->subMinutes(30))
          ->exists();
  }
@endphp

@push('styles')
  {{-- Meta tag pushed through the styles stack because admin layout
       only stacks `styles` and `scripts` in its head. http-equiv meta
       is valid head content regardless of the stack name. --}}
  @if($needsRefresh)
    <meta http-equiv="refresh" content="15">
  @endif
@endpush

@section('content')
<div class="flex justify-between items-center mb-6">
  <h4 class="font-bold text-xl tracking-tight">
    <i class="bi bi-credit-card-2-front mr-2 text-indigo-500"></i>สลิปการโอนเงิน
  </h4>
  {{-- Live-refresh chip — only visible while the auto-refresh meta is
       emitted, so admin knows the page is updating itself and doesn't
       hammer F5. --}}
  @if($needsRefresh ?? false)
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-200">
      <i class="bi bi-arrow-repeat animate-spin"></i>
      <span>อัปเดตอัตโนมัติ (15s)</span>
    </span>
  @endif
</div>

{{-- Navigation Tabs --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-3">
  <div class="py-2 px-3">
    <div class="flex gap-1 flex-wrap">
      <a href="{{ route('admin.payments.index') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-receipt mr-1"></i> ธุรกรรม
      </a>
      <a href="{{ route('admin.payments.methods') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-wallet2 mr-1"></i> วิธีการชำระ
      </a>
      <a href="{{ route('admin.payments.slips') }}" class="text-sm px-4 py-1.5 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-medium">
        <i class="bi bi-image mr-1"></i> สลิปโอน
      </a>
      <a href="{{ route('admin.payments.banks') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-bank mr-1"></i> บัญชีธนาคาร
      </a>
      <a href="{{ route('admin.payments.payouts') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-cash-stack mr-1"></i> การจ่ายช่างภาพ
      </a>
    </div>
  </div>
</div>

@if(session('success'))
<div class="flex items-center gap-2 mb-3 px-4 py-3 rounded-xl bg-emerald-500/10 text-emerald-800">
  <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="flex items-center gap-2 mb-3 px-4 py-3 rounded-xl bg-red-500/10 text-red-800">
  <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
</div>
@endif

{{-- Slip Verification Settings --}}
<div x-data="{
  showSettings: false,
  mode: '{{ $settings['slip_verify_mode'] }}',
  threshold: {{ $settings['slip_auto_approve_threshold'] }},
  tolerance: {{ $settings['slip_amount_tolerance_percent'] }},
  requireSlipok: {{ $settings['slip_require_slipok_for_auto'] ? 'true' : 'false' }},
  requireReceiver: {{ $settings['slip_require_receiver_match'] ? 'true' : 'false' }},
  slipokEnabled: {{ $settings['slipok_enabled'] ? 'true' : 'false' }}
}" class="bg-white rounded-xl shadow-sm border border-gray-100 mb-3">
  <button type="button" @click="showSettings = !showSettings"
    class="w-full flex items-center justify-between py-3 px-4 text-left hover:bg-gray-50/50 transition rounded-xl">
    <div class="flex items-center gap-2">
      <i class="bi bi-gear text-indigo-500"></i>
      <span class="font-semibold text-sm">ตั้งค่าการตรวจสลิป</span>
      <span class="text-xs px-2 py-0.5 rounded-full font-medium"
        :class="mode === 'auto' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-gray-500/10 text-gray-500'"
        x-text="mode === 'auto' ? 'อัตโนมัติ' : 'ตรวจเอง'"></span>
    </div>
    <i class="bi text-gray-400 transition-transform duration-200"
      :class="showSettings ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
  </button>

  <div x-show="showSettings" x-collapse x-cloak>
    <form method="POST" action="{{ route('admin.payments.slips.settings') }}" class="px-4 pb-4 border-t border-gray-100 pt-4">
      @csrf
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Left Column: Verify Mode --}}
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">โหมดตรวจสลิป</label>
            <div class="flex gap-3">
              <label class="flex-1 cursor-pointer">
                <input type="radio" name="slip_verify_mode" value="manual" x-model="mode" class="peer sr-only">
                <div class="border-2 rounded-xl p-3 text-center transition peer-checked:border-indigo-500 peer-checked:bg-indigo-500/[0.04]"
                  :class="mode === 'manual' ? 'border-indigo-500 bg-indigo-500/[0.04]' : 'border-gray-200 hover:border-gray-300'">
                  <i class="bi bi-person-check text-xl block mb-1"
                    :class="mode === 'manual' ? 'text-indigo-500' : 'text-gray-400'"></i>
                  <div class="font-semibold text-sm" :class="mode === 'manual' ? 'text-indigo-600' : 'text-gray-600'">ตรวจเอง</div>
                  <div class="text-xs text-gray-500 mt-0.5">แอดมินตรวจทุกสลิป</div>
                </div>
              </label>
              <label class="flex-1 cursor-pointer">
                <input type="radio" name="slip_verify_mode" value="auto" x-model="mode" class="peer sr-only">
                <div class="border-2 rounded-xl p-3 text-center transition peer-checked:border-emerald-500 peer-checked:bg-emerald-500/[0.04]"
                  :class="mode === 'auto' ? 'border-emerald-500 bg-emerald-500/[0.04]' : 'border-gray-200 hover:border-gray-300'">
                  <i class="bi bi-robot text-xl block mb-1"
                    :class="mode === 'auto' ? 'text-emerald-500' : 'text-gray-400'"></i>
                  <div class="font-semibold text-sm" :class="mode === 'auto' ? 'text-emerald-600' : 'text-gray-600'">อัตโนมัติ</div>
                  <div class="text-xs text-gray-500 mt-0.5">อนุมัติอัตโนมัติตามคะแนน</div>
                </div>
              </label>
            </div>
          </div>

          {{-- Threshold slider (visible when auto mode) --}}
          <div x-show="mode === 'auto'" x-transition class="bg-gray-50 rounded-xl p-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              คะแนนขั้นต่ำสำหรับอนุมัติอัตโนมัติ: <span class="text-indigo-600 font-bold" x-text="threshold + '%'"></span>
            </label>
            <input type="range" name="slip_auto_approve_threshold" min="50" max="100" step="5"
              x-model="threshold"
              class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-indigo-500">
            <div class="flex justify-between text-xs text-gray-400 mt-1">
              <span>50%</span>
              <span>75%</span>
              <span>100%</span>
            </div>
            <p class="text-xs text-gray-500 mt-2">
              <i class="bi bi-info-circle mr-1"></i>สลิปที่คะแนนตรวจสอบ >= <span x-text="threshold"></span>% จะถูกอนุมัติอัตโนมัติ
            </p>
          </div>
          {{-- Hidden input for threshold when in manual mode --}}
          <input x-show="mode !== 'auto'" type="hidden" name="slip_auto_approve_threshold" :value="threshold">

          {{-- Amount tolerance — applies to both manual + auto (affects scoring) --}}
          <div class="bg-gray-50 rounded-xl p-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              ช่วงคลาดเคลื่อนของยอดเงิน: <span class="text-indigo-600 font-bold" x-text="tolerance + '%'"></span>
            </label>
            <input type="range" name="slip_amount_tolerance_percent" min="0.1" max="5" step="0.1"
              x-model.number="tolerance"
              class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-indigo-500">
            <div class="flex justify-between text-xs text-gray-400 mt-1">
              <span>0.1%</span>
              <span>1%</span>
              <span>5%</span>
            </div>
            <p class="text-xs text-gray-500 mt-2">
              <i class="bi bi-info-circle mr-1"></i>ยอดเงินในสลิปต้องไม่ต่างจากออเดอร์เกิน <span x-text="tolerance"></span>% (แนะนำ 1% สำหรับร้านค้าทั่วไป)
            </p>
          </div>

          {{-- Strict mode toggles (auto mode only, and only useful when SlipOK is on) --}}
          <div x-show="mode === 'auto' && slipokEnabled" x-transition class="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-3">
            <div class="flex items-center gap-2 text-sm font-semibold text-amber-700">
              <i class="bi bi-shield-check"></i> โหมดเข้มงวด (แนะนำ)
            </div>
            <label class="flex items-start gap-2 cursor-pointer">
              <input type="hidden" name="slip_require_slipok_for_auto" value="0">
              <input type="checkbox" name="slip_require_slipok_for_auto" value="1" x-model="requireSlipok"
                class="mt-0.5 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
              <div class="text-xs">
                <div class="font-medium text-gray-700">ต้องผ่าน SlipOK API ก่อนอนุมัติอัตโนมัติ</div>
                <div class="text-gray-500">ลดความเสี่ยงสลิปปลอมที่ผ่านเกณฑ์คะแนนด้านอื่น</div>
              </div>
            </label>
            <label class="flex items-start gap-2 cursor-pointer">
              <input type="hidden" name="slip_require_receiver_match" value="0">
              <input type="checkbox" name="slip_require_receiver_match" value="1" x-model="requireReceiver"
                class="mt-0.5 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
              <div class="text-xs">
                <div class="font-medium text-gray-700">ต้องโอนเข้าบัญชีที่ตั้งไว้</div>
                <div class="text-gray-500">ป้องกันผู้ใช้อัปสลิปของร้านอื่น (ต้องตั้งบัญชีธนาคารไว้)</div>
              </div>
            </label>
          </div>
        </div>

        {{-- Right Column: SlipOK API --}}
        <div class="space-y-4">
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-sm font-medium text-gray-700">SlipOK API</label>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="slipok_enabled" value="0">
                <input type="checkbox" name="slipok_enabled" value="1" x-model="slipokEnabled"
                  class="sr-only peer">
                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-500"></div>
                <span class="ml-2 text-xs font-medium" :class="slipokEnabled ? 'text-indigo-600' : 'text-gray-400'" x-text="slipokEnabled ? 'เปิด' : 'ปิด'"></span>
              </label>
            </div>
            <p class="text-xs text-gray-500 mb-3">
              <i class="bi bi-info-circle mr-1"></i>เชื่อมต่อ SlipOK สำหรับตรวจสอบสลิปอัตโนมัติผ่าน API
            </p>
          </div>

          <div x-show="slipokEnabled" x-transition class="space-y-3">
            {{--
              SlipOK dashboard hands users TWO things only:
                1. API URL — the full endpoint to POST slips at, branch
                   information is already embedded in this URL by SlipOK.
                2. API Key — sent as `x-authorization` header.
              Earlier UIs asked for a separate "Branch ID" but SlipOK
              never exposes that as a standalone field — admin only sees
              the full URL with the branch baked in. Field 1 below
              accepts the URL exactly as SlipOK gives it.
            --}}
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                API URL <span class="text-red-500">*</span>
                <span class="text-[11px] font-normal text-gray-500">— วาง URL จาก SlipOK dashboard ตรงนี้</span>
              </label>
              <input type="url" name="slipok_api_url"
                value="{{ $settings['slipok_api_url'] ?? '' }}"
                placeholder="https://api.slipok.com/api/line/apikey/XXXXX"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              @if(!empty($settings['slipok_api_url']))
                <p class="text-xs text-emerald-600 mt-1"><i class="bi bi-check-circle mr-1"></i>ตั้งค่า API URL แล้ว</p>
              @elseif(!empty($settings['slipok_branch_id']))
                <p class="text-xs text-amber-600 mt-1">
                  <i class="bi bi-info-circle mr-1"></i>
                  พบค่าเก่า Branch ID = <code>{{ $settings['slipok_branch_id'] }}</code> —
                  ระบบจะใช้ค่าเก่าจนกว่าจะกรอก API URL ใหม่
                </p>
              @endif
              <p class="text-[11px] text-gray-500 mt-1.5 leading-relaxed">
                <i class="bi bi-lightbulb"></i>
                คัดลอก URL จากหน้า "API" หรือ "Settings" ใน SlipOK dashboard ตรงๆ — มี branch ID อยู่ในนี้แล้ว
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                API Key <span class="text-red-500">*</span>
              </label>
              <input type="password" name="slipok_api_key"
                value="{{ $settings['slipok_api_key'] }}"
                placeholder="ใส่ API Key ใหม่เพื่อเปลี่ยน..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                autocomplete="off">
              @if($settings['slipok_api_key'])
              <p class="text-xs text-emerald-600 mt-1"><i class="bi bi-check-circle mr-1"></i>ตั้งค่า API Key แล้ว</p>
              @endif
              <p class="text-[11px] text-gray-500 mt-1.5">
                <i class="bi bi-shield-lock"></i>
                ส่งใน header <code>x-authorization</code> — เก็บลับเสมอ
              </p>
            </div>

            {{--
              Test Connection — calls our test-slipok endpoint which posts a
              dummy 1x1 image to SlipOK to verify auth + network reachability
              without touching a real customer slip. JS handles the response
              inline (no page reload) so admin sees the result immediately.
            --}}
            <div x-data="{ testing: false, lastResult: null }" class="border-t border-amber-200 pt-3 mt-3">
              <button type="button"
                      :disabled="testing || !{{ $settings['slipok_api_key'] ? 'true' : 'false' }}"
                      @click="
                        testing = true; lastResult = null;
                        fetch('{{ route('admin.payments.slips.test-slipok') }}', {
                          method: 'POST',
                          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                        })
                        .then(r => r.json().then(j => ({ status: r.status, body: j })))
                        .then(({ status, body }) => { lastResult = { ...body, http_status: status }; })
                        .catch(e => { lastResult = { ok: false, message: 'เชื่อมต่อล้มเหลว: ' + e.message, category: 'network' }; })
                        .finally(() => { testing = false; });
                      "
                      class="w-full inline-flex items-center justify-center gap-2 py-2 rounded-lg bg-white border-2 border-indigo-300 text-indigo-700 text-sm font-semibold hover:bg-indigo-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="bi bi-plug" :class="testing ? 'animate-pulse' : ''"></i>
                <span x-show="!testing">ทดสอบการเชื่อมต่อ SlipOK</span>
                <span x-show="testing" x-cloak>กำลังทดสอบ...</span>
              </button>

              <div x-show="lastResult" x-cloak x-transition class="mt-2 rounded-lg p-3 text-xs"
                   :class="lastResult?.ok ? 'bg-emerald-50 border border-emerald-200 text-emerald-900' : 'bg-rose-50 border border-rose-200 text-rose-900'">
                <div class="flex items-start gap-2">
                  <i class="bi" :class="lastResult?.ok ? 'bi-check-circle-fill text-emerald-600' : 'bi-x-circle-fill text-rose-600'"></i>
                  <div class="flex-1 min-w-0">
                    <p class="font-semibold leading-tight" x-text="lastResult?.message"></p>
                    <p x-show="lastResult?.response_time_ms" class="opacity-70 mt-0.5">
                      ตอบกลับใน <span x-text="lastResult?.response_time_ms"></span>ms
                    </p>
                    <p x-show="lastResult?.note" class="opacity-70 mt-0.5" x-text="lastResult?.note"></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{--
        ── SlipOK Webhook Setup ──────────────────────────────────────
        Shown only when SlipOK is enabled. Admin needs to copy the
        webhook URL into SlipOK's dashboard so the third-party can
        push verification results back to us. The HMAC secret is the
        shared key SlipOK uses to sign callbacks — without it, anyone
        could spoof a "slip approved" callback. We let admin generate
        a strong random secret with one click.
      --}}
      <div x-show="slipokEnabled" x-transition class="mt-5 pt-5 border-t border-gray-100">
        <div class="flex items-center gap-2 mb-3">
          <i class="bi bi-link-45deg text-violet-500"></i>
          <h6 class="font-semibold text-sm">Webhook Setup</h6>
          <span class="text-[10px] font-medium uppercase tracking-wider px-2 py-0.5 rounded-full bg-violet-100 text-violet-700">callback URL</span>
        </div>

        <div class="bg-gradient-to-br from-violet-50 to-indigo-50 border border-violet-200 rounded-xl p-4 space-y-3">

          {{-- Step 1: webhook URL (read-only, copy button) --}}
          <div x-data="{ copied: false }">
            <label class="block text-xs font-semibold text-gray-700 mb-1">
              <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-violet-500 text-white text-[10px] font-bold mr-1">1</span>
              Webhook URL
              <span class="font-normal text-gray-500">— วางในช่อง Webhook URL ของ SlipOK dashboard</span>
            </label>
            <div class="flex gap-2">
              <input type="text" readonly
                     value="{{ url('/api/webhooks/slipok') }}"
                     class="flex-1 px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs font-mono text-slate-800 focus:ring-2 focus:ring-violet-500"
                     onfocus="this.select()">
              <button type="button"
                      @click="navigator.clipboard.writeText('{{ url('/api/webhooks/slipok') }}'); copied = true; setTimeout(() => copied = false, 1500)"
                      class="shrink-0 inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-white border border-gray-200 text-xs font-semibold text-slate-700 hover:bg-violet-50 transition">
                <i class="bi" :class="copied ? 'bi-check-lg text-emerald-600' : 'bi-clipboard'"></i>
                <span x-text="copied ? 'คัดลอกแล้ว' : 'คัดลอก'"></span>
              </button>
            </div>
          </div>

          {{-- Step 2: webhook secret (generate + copy) --}}
          <div x-data="{
                  copied: false,
                  generating: false,
                  secret: @js($settings['slipok_webhook_secret'] ?? ''),
                  generate() {
                    if (!confirm('สร้าง webhook secret ใหม่จะแทนที่ของเก่า\nต้องอัปเดตใน SlipOK dashboard ด้วย — ดำเนินการต่อ?')) return;
                    this.generating = true;
                    fetch('{{ route('admin.payments.slips.generate-slipok-secret') }}', {
                      method: 'POST',
                      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(j => { if (j.ok) this.secret = j.secret; })
                    .finally(() => { this.generating = false; });
                  }
               }">
            <label class="block text-xs font-semibold text-gray-700 mb-1">
              <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-violet-500 text-white text-[10px] font-bold mr-1">2</span>
              Webhook Secret
              <span class="font-normal text-gray-500">— HMAC key สำหรับยืนยัน callback ว่ามาจาก SlipOK จริง</span>
            </label>
            <div class="flex gap-2">
              <input type="text" readonly
                     :value="secret || 'ยังไม่ได้สร้าง — กดปุ่ม Generate'"
                     :class="secret ? 'font-mono text-slate-800' : 'italic text-gray-400'"
                     class="flex-1 px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-violet-500"
                     onfocus="this.select()">
              <button type="button"
                      @click="navigator.clipboard.writeText(secret); copied = true; setTimeout(() => copied = false, 1500)"
                      :disabled="!secret"
                      class="shrink-0 inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-white border border-gray-200 text-xs font-semibold text-slate-700 hover:bg-violet-50 transition disabled:opacity-40 disabled:cursor-not-allowed">
                <i class="bi" :class="copied ? 'bi-check-lg text-emerald-600' : 'bi-clipboard'"></i>
                <span x-text="copied ? 'คัดลอก' : 'คัดลอก'"></span>
              </button>
              <button type="button"
                      @click="generate()"
                      :disabled="generating"
                      class="shrink-0 inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-violet-500 text-white text-xs font-semibold hover:bg-violet-600 transition disabled:opacity-50">
                <i class="bi" :class="generating ? 'bi-arrow-repeat animate-spin' : 'bi-shuffle'"></i>
                <span x-text="secret ? 'Regenerate' : 'Generate'"></span>
              </button>
            </div>
            <p class="text-[10px] text-violet-700 mt-1.5 leading-relaxed">
              <i class="bi bi-shield-lock"></i>
              ระบบจะใช้ secret นี้ตรวจ HMAC ของทุก callback — ถ้า signature ไม่ match จะปฏิเสธทันที (กัน fake "slip approved" จาก attacker)
            </p>
          </div>

          {{-- Step 3: Status pills.
               URL pill is green when EITHER slipok_api_url is set OR the
               legacy slipok_branch_id is — the service auto-falls back to
               the latter via resolveApiUrl(). --}}
          @php
            $hasUrl = !empty($settings['slipok_api_url']) || !empty($settings['slipok_branch_id']);
          @endphp
          <div class="flex items-center gap-2 flex-wrap pt-2 border-t border-violet-200/50">
            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">สถานะ:</span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $settings['slipok_api_key'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">
              <i class="bi {{ $settings['slipok_api_key'] ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
              API Key
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $hasUrl ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">
              <i class="bi {{ $hasUrl ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
              API URL
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $settings['slipok_webhook_secret'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
              <i class="bi {{ $settings['slipok_webhook_secret'] ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }}"></i>
              Webhook Secret {{ $settings['slipok_webhook_secret'] ? '' : '(แนะนำให้ตั้ง)' }}
            </span>
          </div>
        </div>
      </div>

      {{-- Save Button --}}
      <div class="flex justify-end mt-5 pt-4 border-t border-gray-100">
        <button type="submit"
          class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium text-sm px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700 flex items-center gap-2">
          <i class="bi bi-check-lg"></i> บันทึกการตั้งค่า
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Stats Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="flex items-center gap-3 py-3 px-4">
      <div class="w-11 h-11 rounded-xl bg-indigo-500/10 flex items-center justify-center flex-shrink-0">
        <i class="bi bi-image text-lg text-indigo-500"></i>
      </div>
      <div>
        <div class="font-bold text-xl leading-none">{{ number_format($stats->total ?? 0) }}</div>
        <div class="text-gray-500 text-sm mt-1">สลิปทั้งหมด</div>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="flex items-center gap-3 py-3 px-4">
      <div class="w-11 h-11 rounded-xl bg-amber-500/10 flex items-center justify-center flex-shrink-0">
        <i class="bi bi-hourglass-split text-lg text-amber-500"></i>
      </div>
      <div>
        <div class="font-bold text-xl leading-none text-amber-500">{{ number_format($stats->pending_count ?? 0) }}</div>
        <div class="text-gray-500 text-sm mt-1">รอตรวจสอบ</div>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="flex items-center gap-3 py-3 px-4">
      <div class="w-11 h-11 rounded-xl bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
        <i class="bi bi-check-circle text-lg text-emerald-500"></i>
      </div>
      <div>
        <div class="font-bold text-xl leading-none text-emerald-500">{{ number_format($stats->approved_count ?? 0) }}</div>
        <div class="text-gray-500 text-sm mt-1">อนุมัติแล้ว</div>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="flex items-center gap-3 py-3 px-4">
      <div class="w-11 h-11 rounded-xl bg-red-500/10 flex items-center justify-center flex-shrink-0">
        <i class="bi bi-x-circle text-lg text-red-500"></i>
      </div>
      <div>
        <div class="font-bold text-xl leading-none text-red-500">{{ number_format($stats->rejected_count ?? 0) }}</div>
        <div class="text-gray-500 text-sm mt-1">ปฏิเสธ</div>
      </div>
    </div>
  </div>
</div>

{{-- Filter Bar --}}
<div class="af-bar mb-3" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.payments.slips') }}">
    <div class="af-grid">

      {{-- Search field (span 2 cols) --}}
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="search" class="af-input" placeholder="เลขออเดอร์, อีเมล, รหัสอ้างอิง..." value="{{ request('search') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>

      {{-- Status filter --}}
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="pending"  {{ request('status') === 'pending'  ? 'selected' : '' }}>รอตรวจสอบ</option>
          <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>อนุมัติแล้ว</option>
          <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>ปฏิเสธ</option>
        </select>
      </div>

      {{-- Actions --}}
      <div class="af-actions">
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>

    </div>
  </form>
</div>

<div id="admin-table-area">
{{-- Bulk Action Form --}}
<form id="bulkForm" method="POST">
  @csrf
  <input type="hidden" name="reason" id="bulkReasonInput" value="">

{{-- Bulk Action Bar --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-3 hidden" id="bulkBar">
  <div class="py-2 px-4 flex items-center gap-3 flex-wrap">
    <span class="text-gray-500 text-sm font-medium" id="selectedCount">0 รายการที่เลือก</span>
    <button type="button" class="text-sm px-4 py-1.5 rounded-lg bg-emerald-500/10 text-emerald-500 font-medium transition hover:bg-emerald-500/[0.15]" onclick="submitBulkApprove()">
      <i class="bi bi-check-all mr-1"></i> อนุมัติที่เลือก
    </button>
    <button type="button" class="text-sm px-4 py-1.5 rounded-lg bg-red-500/10 text-red-500 font-medium transition hover:bg-red-500/[0.15]" onclick="openBulkRejectModal()">
      <i class="bi bi-x-lg mr-1"></i> ปฏิเสธที่เลือก
    </button>
  </div>
</div>

{{-- Slips Table --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-indigo-500/[0.03]">
        <tr>
          <th class="px-3 py-3 text-left w-10">
            <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
          </th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ออเดอร์ #</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ผู้ซื้อ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ยอดเงิน</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">วันที่โอน</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">รหัสอ้างอิง</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สลิป</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">คะแนน</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($slips as $slip)
        @php
          $isPending = ($slip->verify_status ?? 'pending') === 'pending';
          $statusMap = [
            'pending' => ['bg' => 'rgba(245,158,11,0.1)', 'color' => '#f59e0b', 'label' => 'รอตรวจสอบ'],
            'approved' => ['bg' => 'rgba(16,185,129,0.1)', 'color' => '#10b981', 'label' => 'อนุมัติแล้ว'],
            'rejected' => ['bg' => 'rgba(239,68,68,0.1)', 'color' => '#ef4444', 'label' => 'ปฏิเสธ'],
          ];
          $sc = $statusMap[$slip->verify_status ?? 'pending'] ?? ['bg' => 'rgba(107,114,128,0.1)', 'color' => '#6b7280', 'label' => $slip->verify_status ?? 'pending'];
          $score = $slip->verify_score ?? null;
          $scoreColor = $score === null ? '#94a3b8' : ($score >= 80 ? '#10b981' : ($score >= 50 ? '#f59e0b' : '#ef4444'));
          $scoreBg    = $score === null ? 'bg-gray-200' : ($score >= 80 ? 'bg-emerald-500' : ($score >= 50 ? 'bg-amber-500' : 'bg-red-500'));
          $scoreBadgeBg = $score === null ? 'bg-gray-100 text-gray-400' : ($score >= 80 ? 'bg-emerald-500/10 text-emerald-600' : ($score >= 50 ? 'bg-amber-500/10 text-amber-600' : 'bg-red-500/10 text-red-600'));
          $fraudFlags = [];
          if (!empty($slip->fraud_flags)) {
              $decoded = is_string($slip->fraud_flags) ? json_decode($slip->fraud_flags, true) : (array) $slip->fraud_flags;
              $fraudFlags = is_array($decoded) ? $decoded : [];
          }
          $slipImage = $slip->slip_image ?? $slip->slip_path ?? null;
          // `$slip` is a stdClass from a raw DB join, not a PaymentSlip model —
          // so the slip_url accessor isn't available. Resolve via StorageManager
          // directly; it figures out whether the file lives on R2, S3, or the
          // legacy `public` disk without us having to know up-front.
          $slipUrl = $slipImage
              ? app(\App\Services\StorageManager::class)->resolveUrl($slipImage)
              : '';

          /* ── SlipOK pipeline state (derived per slip) ──────────────────
             Tells admin AT A GLANCE where this slip is in the verification
             pipeline. State machine:
                uploaded → slipok_checking → slipok_done → decision
                                ↓ (retry exhausted)
                              slipok_failed (manual review)

             Inputs (all already on the slip row):
               - $slipokEnabledGlobally — admin's master toggle
               - $slip->slipok_trans_ref — set when SlipOK successfully verified
               - $slip->verify_status   — pending/approved/rejected
               - $slip->verified_by     — 'slipok_async' / admin id / null
               - $slip->created_at      — when admin/customer uploaded
          ──────────────────────────────────────────────────────────────── */
          $slipokEnabledGlobally = !empty($settings['slipok_enabled']);
          $hasSlipokRef          = !empty($slip->slipok_trans_ref);
          $secondsSinceUpload    = $slip->created_at
              ? max(0, now()->diffInSeconds(\Carbon\Carbon::parse($slip->created_at), false) * -1)
              : null;
          // verified_by is an INTEGER column (admin_id) — NULL when SlipOK
          // or the system handled it. We don't compare to a string here.
          $verifiedByAdminId = $slip->verified_by;
          $hasAdminVerifier  = !empty($verifiedByAdminId);

          if (!$slipokEnabledGlobally) {
              $pipelineState = ['key' => 'slipok_off',     'label' => 'SlipOK ปิด',      'icon' => 'bi-slash-circle',     'color' => 'text-gray-500',  'bg' => 'bg-gray-100',     'spin' => false];
          } elseif ($hasSlipokRef) {
              $pipelineState = ['key' => 'slipok_done',    'label' => 'SlipOK ตรวจแล้ว','icon' => 'bi-check-circle-fill','color' => 'text-emerald-700','bg' => 'bg-emerald-100',  'spin' => false];
          } elseif ($isPending && $secondsSinceUpload !== null && $secondsSinceUpload < 60) {
              $pipelineState = ['key' => 'slipok_checking','label' => 'กำลังตรวจ',     'icon' => 'bi-arrow-repeat',     'color' => 'text-blue-700',  'bg' => 'bg-blue-100',     'spin' => true];
          } elseif ($isPending && $secondsSinceUpload !== null && $secondsSinceUpload < 1200) {
              // Within retry window (3 attempts: 60s/5min/15min = ~21min).
              // Show as "queued for retry" so admin knows to wait, not act.
              $pipelineState = ['key' => 'slipok_retry',   'label' => 'รอ retry',       'icon' => 'bi-hourglass-split',  'color' => 'text-amber-700', 'bg' => 'bg-amber-100',    'spin' => false];
          } elseif ($isPending) {
              // Past retry window without a transRef — admin needs to look.
              $pipelineState = ['key' => 'slipok_manual',  'label' => 'ต้องตรวจมือ',   'icon' => 'bi-person-raised-hand','color' => 'text-rose-700',  'bg' => 'bg-rose-100',     'spin' => false];
          } else {
              // Already decided (approved/rejected). If transRef missing here
              // it means admin decided manually before SlipOK finished.
              $pipelineState = ['key' => 'slipok_skipped', 'label' => 'ข้าม SlipOK',    'icon' => 'bi-skip-forward',     'color' => 'text-gray-600',  'bg' => 'bg-gray-100',     'spin' => false];
          }

          // "Decided by" — derived from the admin_id (NULL = system/auto).
          // Pairing with $hasSlipokRef tells us SlipOK-auto vs system-other:
          //   admin_id set        → admin manually approved
          //   null + slipok ref   → SlipOK auto-approved via the async job
          //   null + no slipok    → system (gateway webhook, etc.)
          $isTerminal = !$isPending;
          $decidedBy = null;
          if ($isTerminal) {
              if ($hasAdminVerifier) {
                  $decidedBy = ['label' => 'แอดมิน',     'icon' => 'bi-person-badge', 'color' => 'text-indigo-700', 'bg' => 'bg-indigo-100'];
              } elseif ($hasSlipokRef) {
                  $decidedBy = ['label' => 'อัตโนมัติ',  'icon' => 'bi-robot',         'color' => 'text-violet-700', 'bg' => 'bg-violet-100'];
              }
          }
        @endphp
        <tr class="hover:bg-gray-50/50 transition align-middle">
          <td class="px-3 py-3">
            @if($isPending)
            <input type="checkbox" name="slip_ids[]" value="{{ $slip->id }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer slip-check">
            @endif
          </td>
          <td class="px-4 py-3">
            <span class="font-semibold text-indigo-500">#{{ $slip->order_number ?? $slip->order_id }}</span>
          </td>
          <td class="px-4 py-3">
            <div class="font-medium text-sm">{{ trim(($slip->first_name ?? '') . ' ' . ($slip->last_name ?? '')) ?: '-' }}</div>
            <div class="text-gray-500 text-xs">{{ $slip->user_email ?? '-' }}</div>
          </td>
          <td class="px-4 py-3 font-semibold">
            ฿{{ number_format($slip->transfer_amount ?? $slip->amount ?? 0, 2) }}
          </td>
          <td class="px-4 py-3 text-gray-500 text-sm">
            {{ $slip->transfer_date ? \Carbon\Carbon::parse($slip->transfer_date)->format('d/m/Y H:i') : '-' }}
          </td>
          <td class="px-4 py-3">
            @if($slip->ref_code ?? $slip->reference_code)
            <code class="bg-indigo-500/[0.08] text-indigo-500 px-2 py-0.5 rounded text-xs">{{ $slip->ref_code ?? $slip->reference_code }}</code>
            @else
            <span class="text-gray-500">-</span>
            @endif
          </td>
          <td class="px-4 py-3">
            @if($slipImage)
            <img src="{{ $slipUrl }}"
               alt="สลิป"
               class="rounded-lg cursor-pointer slip-thumb w-12 h-12 object-cover border-2 border-gray-200 hover:border-indigo-300 transition"
               onclick="previewSlip(this.src)"
               loading="lazy">
            @else
            <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center">
              <i class="bi bi-image text-gray-500"></i>
            </div>
            @endif
          </td>
          <td class="px-4 py-3">
            @if($score !== null)
            <div class="flex items-center gap-2">
              <div class="w-16">
                <div class="flex items-center justify-between mb-0.5">
                  <span class="font-bold text-sm {{ $scoreBadgeBg }} px-1.5 py-0 rounded">{{ $score }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1.5">
                  <div class="{{ $scoreBg }} h-1.5 rounded-full transition-all" style="width: {{ min($score, 100) }}%"></div>
                </div>
              </div>
              @if($score >= 80)
              <i class="bi bi-shield-check text-emerald-500 text-sm" title="คะแนนสูง"></i>
              @elseif($score < 50)
              <i class="bi bi-exclamation-triangle text-red-500 text-sm" title="คะแนนต่ำ"></i>
              @endif
            </div>
            @if(count($fraudFlags) > 0)
            <div class="mt-1 flex flex-wrap gap-1">
              @foreach($fraudFlags as $flag)
              <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-500/10 text-red-600" title="{{ $flag }}">
                <i class="bi bi-flag-fill text-[8px]"></i>{{ Str::limit($flag, 20) }}
              </span>
              @endforeach
            </div>
            @endif
            @else
            <span class="text-gray-400 text-xs">ยังไม่ตรวจ</span>
            @endif
          </td>
          <td class="px-4 py-3">
            {{-- Top: terminal status (pending/approved/rejected) --}}
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};">
              {{ $sc['label'] }}
            </span>

            {{-- Middle: SlipOK pipeline pill — animated when actively
                 checking, static badge when done/queued/manual. Tells admin
                 at-a-glance whether to wait (auto-pipeline running) or
                 review manually (pipeline finished or skipped). --}}
            <div class="flex flex-wrap gap-1 mt-1.5">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $pipelineState['bg'] }} {{ $pipelineState['color'] }}"
                    title="{{ $pipelineState['key'] }}">
                <i class="bi {{ $pipelineState['icon'] }} {{ $pipelineState['spin'] ? 'animate-spin' : '' }}"></i>
                {{ $pipelineState['label'] }}
              </span>

              {{-- Decided-by pill — only when terminal status reached --}}
              @if($decidedBy)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $decidedBy['bg'] }} {{ $decidedBy['color'] }}"
                      title="{{ $hasAdminVerifier ? 'admin_id=' . $verifiedByAdminId : 'auto-verified by SlipOK' }}">
                  <i class="bi {{ $decidedBy['icon'] }}"></i>{{ $decidedBy['label'] }}
                </span>
              @endif
            </div>

            {{-- Age — when admin sees "กำลังตรวจ" they want to know
                 "for how long?" so they can spot stuck slips. --}}
            @if($slip->created_at)
              <p class="text-[10px] text-gray-400 mt-1">
                <i class="bi bi-clock"></i>
                อัปโหลด {{ \Carbon\Carbon::parse($slip->created_at)->diffForHumans() }}
              </p>
            @endif

            @if(($slip->verify_status ?? '') === 'rejected' && $slip->reject_reason)
            <div class="text-gray-500 mt-1 text-xs" title="{{ $slip->reject_reason }}">
              <i class="bi bi-info-circle mr-1"></i>{{ Str::limit($slip->reject_reason, 30) }}
            </div>
            @endif

            {{-- SlipOK trans ref — irrefutable proof of bank-side success --}}
            @if($hasSlipokRef)
              <p class="text-[9px] text-emerald-600 mt-1 font-mono" title="SlipOK transaction reference">
                <i class="bi bi-shield-fill-check"></i>
                {{ Str::limit($slip->slipok_trans_ref, 18) }}
              </p>
            @endif
          </td>
          <td class="px-4 py-3">
            @if($isPending)
            <div class="flex gap-1">
              <button type="button"
                class="w-8 h-8 rounded-lg bg-emerald-500/[0.08] text-emerald-500 flex items-center justify-center transition hover:bg-emerald-500/[0.15]"
                title="อนุมัติ"
                onclick="confirmApprove({{ $slip->id }}, '{{ $slip->order_number ?? $slip->order_id }}')">
                <i class="bi bi-check-lg text-sm"></i>
              </button>
              <button type="button"
                class="w-8 h-8 rounded-lg bg-red-500/[0.08] text-red-500 flex items-center justify-center transition hover:bg-red-500/[0.15]"
                title="ปฏิเสธ"
                onclick="openRejectModal({{ $slip->id }}, '{{ $slip->order_number ?? $slip->order_id }}')">
                <i class="bi bi-x-lg text-sm"></i>
              </button>
              {{-- Re-verify with SlipOK — only shown when SlipOK is enabled
                   AND we don't already have a transRef. Lets admin manually
                   trigger a SlipOK call + see the exact error code, useful
                   for "ทำไม slip ไม่เข้า SlipOK" debugging. --}}
              @if($slipokEnabledGlobally && !$hasSlipokRef)
                <button type="button"
                  class="w-8 h-8 rounded-lg bg-violet-500/[0.08] text-violet-600 flex items-center justify-center transition hover:bg-violet-500/[0.15]"
                  title="ส่งให้ SlipOK ตรวจอีกครั้ง"
                  onclick="reverifySlipOK({{ $slip->id }}, this)">
                  <i class="bi bi-arrow-repeat text-sm"></i>
                </button>
              @endif
            </div>
            @else
            <span class="text-gray-500 text-sm">-</span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="10" class="text-center py-12">
            <i class="bi bi-image text-4xl text-gray-300"></i>
            <p class="text-gray-500 mt-2 text-sm">ยังไม่มีสลิปการโอนเงิน</p>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
</form>

</div>{{-- /#admin-table-area --}}

@if($slips->hasPages())
<div id="admin-pagination-area" class="flex justify-center mt-6">{{ $slips->withQueryString()->links() }}</div>
@endif

{{-- Approve Confirmation Modal --}}
<div x-data="{ open: false }" x-on:open-approve-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
      <div class="text-center">
        <div class="w-16 h-16 rounded-full bg-emerald-500/10 flex items-center justify-center mx-auto mb-4">
          <i class="bi bi-check-circle-fill text-3xl text-emerald-500"></i>
        </div>
        <h5 class="font-bold text-lg mb-2">ยืนยันการอนุมัติ</h5>
        <p class="text-gray-500 mb-6" id="approveModalText">คุณต้องการอนุมัติสลิปนี้ใช่หรือไม่?</p>
        <div class="flex gap-2 justify-center">
          <button type="button" @click="open = false" class="rounded-lg bg-gray-500/10 text-gray-500 font-medium px-6 py-2.5 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
          <form id="approveForm" method="POST">
            @csrf
            <button type="submit" class="rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 text-white font-medium px-6 py-2.5 transition hover:from-emerald-600 hover:to-emerald-700">
              <i class="bi bi-check-lg mr-1"></i> อนุมัติ
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Reject Modal --}}
<div x-data="{ open: false }" x-on:open-reject-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
      <div class="text-center mb-4">
        <div class="w-16 h-16 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-4">
          <i class="bi bi-x-circle-fill text-3xl text-red-500"></i>
        </div>
        <h5 class="font-bold text-lg mb-1">ปฏิเสธสลิป</h5>
        <p class="text-gray-500 text-sm" id="rejectModalText">กรุณาระบุเหตุผล</p>
      </div>
      <form id="rejectForm" method="POST">
        @csrf
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">เหตุผลการปฏิเสธ <span class="text-red-500">*</span></label>
          <textarea name="reason" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" placeholder="ระบุเหตุผลที่ปฏิเสธสลิป..." required maxlength="500"></textarea>
        </div>
        <div class="flex gap-2 justify-end">
          <button type="button" @click="open = false" class="rounded-lg bg-gray-500/10 text-gray-500 font-medium px-5 py-2 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
          <button type="submit" class="rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white font-medium px-5 py-2 transition hover:from-red-600 hover:to-red-700">
            <i class="bi bi-x-lg mr-1"></i> ปฏิเสธ
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Bulk Reject Modal --}}
<div x-data="{ open: false }" x-on:open-bulk-reject-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
      <div class="text-center mb-4">
        <div class="w-16 h-16 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-4">
          <i class="bi bi-x-circle-fill text-3xl text-red-500"></i>
        </div>
        <h5 class="font-bold text-lg mb-1">ปฏิเสธสลิปที่เลือก</h5>
        <p class="text-gray-500 text-sm" id="bulkRejectText">กรุณาระบุเหตุผล</p>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">เหตุผลการปฏิเสธ <span class="text-red-500">*</span></label>
        <textarea id="bulkRejectReason" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" placeholder="ระบุเหตุผล..." maxlength="500"></textarea>
      </div>
      <div class="flex gap-2 justify-end">
        <button type="button" @click="open = false" class="rounded-lg bg-gray-500/10 text-gray-500 font-medium px-5 py-2 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
        <button type="button" onclick="submitBulkReject()" class="rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white font-medium px-5 py-2 transition hover:from-red-600 hover:to-red-700">
          <i class="bi bi-x-lg mr-1"></i> ปฏิเสธทั้งหมด
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Slip Image Preview Modal --}}
<div x-data="{ open: false, src: '' }" x-on:open-slip-preview.window="src = $event.detail; open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/60" @click="open = false"></div>
    <div x-show="open" x-transition class="relative max-w-3xl w-full text-center">
      <button type="button" @click="open = false" class="absolute -top-3 -right-3 w-9 h-9 bg-white rounded-full shadow-lg flex items-center justify-center z-10 hover:bg-gray-100 transition">
        <i class="bi bi-x-lg text-gray-600"></i>
      </button>
      <img :src="src" alt="สลิป" class="max-h-[85vh] rounded-xl object-contain mx-auto">
    </div>
  </div>
</div>

@push('scripts')
<script>
// Checkbox select all
const selectAll = document.getElementById('selectAll');
const bulkBar = document.getElementById('bulkBar');
const selectedCount = document.getElementById('selectedCount');

function updateBulkBar() {
  const checked = document.querySelectorAll('.slip-check:checked').length;
  selectedCount.textContent = checked + ' รายการที่เลือก';
  bulkBar.classList.toggle('hidden', checked === 0);
}

selectAll?.addEventListener('change', function () {
  document.querySelectorAll('.slip-check').forEach(cb => cb.checked = this.checked);
  updateBulkBar();
});

document.querySelectorAll('.slip-check').forEach(cb => {
  cb.addEventListener('change', function () {
    const all = document.querySelectorAll('.slip-check');
    const checked = document.querySelectorAll('.slip-check:checked');
    selectAll.checked = all.length > 0 && checked.length === all.length;
    selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
    updateBulkBar();
  });
});

// Approve single
function confirmApprove(slipId, orderNum) {
  document.getElementById('approveModalText').textContent =
    'คุณต้องการอนุมัติสลิปสำหรับออเดอร์ #' + orderNum + ' ใช่หรือไม่?';
  document.getElementById('approveForm').action = '/admin/payments/slips/' + slipId + '/approve';
  window.dispatchEvent(new CustomEvent('open-approve-modal'));
}

// Reject single
function openRejectModal(slipId, orderNum) {
  document.getElementById('rejectModalText').textContent =
    'ออเดอร์ #' + orderNum + ' — กรุณาระบุเหตุผล';
  document.getElementById('rejectForm').action = '/admin/payments/slips/' + slipId + '/reject';
  document.querySelector('#rejectForm textarea[name="reason"]').value = '';
  window.dispatchEvent(new CustomEvent('open-reject-modal'));
}

/* Re-verify with SlipOK — admin clicks the violet refresh icon on a
   pending slip. Triggers a fresh call to the SlipOK API + writes the
   result into the slip row. Visual feedback inline (button spins),
   alert on completion with the actual error code so admin sees WHY
   it failed in plain text instead of guessing. */
function reverifySlipOK(slipId, btn) {
  const icon = btn.querySelector('i');
  const orig = icon.className;
  icon.className = 'bi bi-arrow-repeat animate-spin text-sm';
  btn.disabled = true;

  fetch('/admin/payments/slips/' + slipId + '/reverify-slipok', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json'
    }
  })
    .then(r => r.json().then(j => ({ status: r.status, body: j })))
    .then(({ status, body }) => {
      if (body.ok) {
        alert('✓ SlipOK ยืนยันสำเร็จ\n\ntransRef: ' + (body.trans_ref || '-') + '\n\nหน้าจะรีเฟรชเพื่อแสดงผล');
        window.location.reload();
      } else {
        alert('✗ SlipOK ปฏิเสธ\n\nError code: ' + (body.error_code || '-') + '\nDetail: ' + (body.error_msg || body.message || '-'));
      }
    })
    .catch(err => {
      alert('เชื่อมต่อล้มเหลว: ' + err.message);
    })
    .finally(() => {
      icon.className = orig;
      btn.disabled = false;
    });
}

// Bulk approve
function submitBulkApprove() {
  const form = document.getElementById('bulkForm');
  form.action = '{{ route('admin.payments.slips.bulk-approve') }}';
  document.getElementById('bulkReasonInput').name = '';
  form.submit();
}

// Bulk reject modal
function openBulkRejectModal() {
  document.getElementById('bulkRejectText').textContent =
    document.querySelectorAll('.slip-check:checked').length + ' รายการที่เลือก';
  document.getElementById('bulkRejectReason').value = '';
  window.dispatchEvent(new CustomEvent('open-bulk-reject-modal'));
}

function submitBulkReject() {
  const reason = document.getElementById('bulkRejectReason').value.trim();
  if (!reason) {
    document.getElementById('bulkRejectReason').classList.add('border-red-500');
    return;
  }
  const form = document.getElementById('bulkForm');
  form.action = '{{ route('admin.payments.slips.bulk-reject') }}';
  document.getElementById('bulkReasonInput').name = 'reason';
  document.getElementById('bulkReasonInput').value = reason;
  form.submit();
}

// Slip image preview
function previewSlip(src) {
  window.dispatchEvent(new CustomEvent('open-slip-preview', { detail: src }));
}
</script>
@endpush

@endsection
