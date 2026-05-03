@extends('layouts.app')

@section('title', 'คำสั่งซื้อ ' . $order->order_number)

@section('content')
@php
  $typeLabels = [
    'preset'   => ['label' => 'พรีเซ็ต',     'icon' => 'bi-sliders',  'color' => 'from-rose-500 to-pink-500'],
    'overlay'  => ['label' => 'โอเวอร์เลย์', 'icon' => 'bi-layers',   'color' => 'from-amber-500 to-orange-500'],
    'template' => ['label' => 'เทมเพลต',     'icon' => 'bi-grid-3x3', 'color' => 'from-emerald-500 to-teal-500'],
    'other'    => ['label' => 'อื่นๆ',         'icon' => 'bi-box-seam', 'color' => 'from-indigo-500 to-purple-500'],
  ];
  $typeMeta = $typeLabels[$order->product->product_type ?? 'other'] ?? $typeLabels['other'];

  $statusMap = [
    'pending_payment' => ['step' => 2, 'label' => 'รอการชำระเงิน',  'desc' => 'กรุณาชำระเงินและแนบสลิปเพื่อดำเนินการต่อ',     'icon' => 'bi-clock',             'color' => 'from-amber-500 to-orange-500',  'ring' => 'ring-amber-200 dark:ring-amber-500/30',   'bg' => 'bg-amber-50 dark:bg-amber-500/10'],
    'pending_review'  => ['step' => 3, 'label' => 'กำลังตรวจสอบ',    'desc' => 'แอดมินกำลังตรวจสอบหลักฐาน โดยปกติภายใน 24 ชั่วโมง', 'icon' => 'bi-hourglass-split',   'color' => 'from-blue-500 to-indigo-500',    'ring' => 'ring-blue-200 dark:ring-blue-500/30',      'bg' => 'bg-blue-50 dark:bg-blue-500/10'],
    'paid'            => ['step' => 4, 'label' => 'ชำระเงินสำเร็จ',   'desc' => 'คุณสามารถดาวน์โหลดสินค้าได้แล้ว',             'icon' => 'bi-check-circle-fill', 'color' => 'from-emerald-500 to-teal-500',   'ring' => 'ring-emerald-200 dark:ring-emerald-500/30','bg' => 'bg-emerald-50 dark:bg-emerald-500/10'],
    'cancelled'       => ['step' => 0, 'label' => 'คำสั่งซื้อถูกยกเลิก', 'desc' => 'คำสั่งซื้อนี้ไม่สามารถดำเนินการต่อได้',          'icon' => 'bi-x-circle-fill',     'color' => 'from-rose-500 to-red-500',       'ring' => 'ring-rose-200 dark:ring-rose-500/30',     'bg' => 'bg-rose-50 dark:bg-rose-500/10'],
  ];
  $s = $statusMap[$order->status] ?? $statusMap['pending_payment'];
  $isPaid      = $order->status === 'paid';
  $isCancelled = $order->status === 'cancelled';
@endphp

<div class="max-w-4xl mx-auto px-4 md:px-6 py-6"
     x-data="orderStatusPoller({
        orderId: {{ $order->id }},
        currentStatus: @js($order->status),
        statusUrl: @js(route('products.order.status', $order->id))
     })"
     x-init="init()">

  {{-- Realtime toast --}}
  <div x-cloak x-show="toast.show"
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="opacity-0 translate-y-4"
       x-transition:enter-end="opacity-100 translate-y-0"
       class="fixed top-20 left-1/2 -translate-x-1/2 z-50 px-5 py-3 rounded-xl shadow-2xl text-white font-medium flex items-center gap-2 text-sm"
       :class="toast.type === 'success' ? 'bg-gradient-to-r from-emerald-500 to-teal-500' : (toast.type === 'error' ? 'bg-gradient-to-r from-rose-500 to-red-500' : 'bg-gradient-to-r from-indigo-500 to-purple-500')">
    <i class="bi" :class="toast.type === 'success' ? 'bi-check-circle-fill' : (toast.type === 'error' ? 'bi-x-circle-fill' : 'bi-info-circle-fill')"></i>
    <span x-text="toast.message"></span>
  </div>

  {{-- Live polling indicator (pending only) --}}
  @if(in_array($order->status, ['pending_payment','pending_review']))
    <div x-show="polling" class="mb-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 text-blue-700 dark:text-blue-300 text-xs font-medium">
      <span class="relative flex h-2 w-2">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
      </span>
      ติดตามสถานะแบบเรียลไทม์
    </div>
  @endif


  {{-- Breadcrumb --}}
  <nav class="mb-4 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 flex-wrap">
    <a href="{{ url('/') }}" class="hover:text-indigo-500 transition">หน้าแรก</a>
    <i class="bi bi-chevron-right text-[10px]"></i>
    <a href="{{ route('products.index') }}" class="hover:text-indigo-500 transition">สินค้าดิจิทัล</a>
    <i class="bi bi-chevron-right text-[10px]"></i>
    <span class="text-slate-700 dark:text-slate-300 font-mono">{{ $order->order_number }}</span>
  </nav>

  @if(session('success'))
    <div class="mb-4 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-800 dark:text-emerald-300 text-sm flex items-start gap-2">
      <i class="bi bi-check-circle-fill mt-0.5"></i> {{ session('success') }}
    </div>
  @endif
  @if(session('error'))
    <div class="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 text-sm flex items-start gap-2">
      <i class="bi bi-exclamation-triangle-fill mt-0.5"></i> {{ session('error') }}
    </div>
  @endif
  @if(session('info'))
    <div class="mb-4 p-4 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 text-blue-800 dark:text-blue-300 text-sm flex items-start gap-2">
      <i class="bi bi-info-circle-fill mt-0.5"></i> {{ session('info') }}
    </div>
  @endif

  {{-- ═══════════════════════════════════════════════════════════════
       STATUS HERO CARD
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-lg overflow-hidden mb-5">
    {{-- Colored strip --}}
    <div class="h-1.5 bg-gradient-to-r {{ $s['color'] }}"></div>

    <div class="p-6 md:p-8 text-center relative overflow-hidden">
      {{-- Decorative background --}}
      <div class="absolute inset-0 opacity-5 pointer-events-none">
        <div class="absolute -top-10 -right-10 w-64 h-64 rounded-full bg-gradient-to-br {{ $s['color'] }} blur-3xl"></div>
      </div>

      {{-- Big status icon --}}
      <div class="relative inline-flex items-center justify-center w-20 h-20 md:w-24 md:h-24 rounded-full bg-gradient-to-br {{ $s['color'] }} text-white shadow-xl ring-8 {{ $s['ring'] }} mb-4 {{ $order->status === 'pending_payment' ? 'animate-pulse' : '' }}">
        <i class="bi {{ $s['icon'] }} text-4xl md:text-5xl"></i>
      </div>

      <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white mb-2 tracking-tight">{{ $s['label'] }}</h1>
      <p class="text-slate-500 dark:text-slate-400 text-sm md:text-base max-w-md mx-auto">{{ $s['desc'] }}</p>

      @if($isCancelled && $order->note)
        <div class="mt-4 inline-block px-4 py-2 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 text-sm">
          <i class="bi bi-info-circle mr-1"></i> เหตุผล: {{ $order->note }}
        </div>
      @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         PROGRESS STEPPER
         ═══════════════════════════════════════════════════════════════ --}}
    @if(!$isCancelled)
    <div class="px-6 md:px-8 pb-6">
      <div class="grid grid-cols-4 gap-2 relative">
        @php
          $steps = [
            1 => ['label' => 'สั่งซื้อ',        'icon' => 'bi-cart-check'],
            2 => ['label' => 'ชำระเงิน',      'icon' => 'bi-credit-card'],
            3 => ['label' => 'ตรวจสอบ',        'icon' => 'bi-search'],
            4 => ['label' => 'พร้อมโหลด',    'icon' => 'bi-download'],
          ];
          $currentStep = $s['step'];
        @endphp
        @foreach($steps as $stepNum => $step)
          @php
            $isDone    = $stepNum < $currentStep;
            $isCurrent = $stepNum === $currentStep;
          @endphp
          <div class="relative">
            <div class="flex flex-col items-center text-center">
              <div class="w-9 h-9 md:w-10 md:h-10 rounded-full flex items-center justify-center z-10 transition
                  {{ $isDone ? 'bg-gradient-to-br from-emerald-500 to-teal-500 text-white shadow-md' : '' }}
                  {{ $isCurrent ? 'bg-gradient-to-br ' . $s['color'] . ' text-white shadow-md ring-4 ' . $s['ring'] . ' animate-pulse' : '' }}
                  {{ !$isDone && !$isCurrent ? 'bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-slate-500' : '' }}">
                <i class="bi {{ $isDone ? 'bi-check-lg' : $step['icon'] }} text-sm"></i>
              </div>
              <div class="mt-2 text-[11px] md:text-xs font-medium {{ $isDone || $isCurrent ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400' }}">
                {{ $step['label'] }}
              </div>
            </div>
            @if($stepNum < count($steps))
              <div class="absolute top-[18px] md:top-5 left-1/2 w-full h-0.5 -z-0
                  {{ $stepNum < $currentStep ? 'bg-gradient-to-r from-emerald-500 to-teal-500' : 'bg-slate-200 dark:bg-white/10' }}"></div>
            @endif
          </div>
        @endforeach
      </div>
    </div>
    @endif
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       ACTION BUTTONS (primary CTAs based on status)
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="mb-5 flex flex-col sm:flex-row gap-3">
    @if($isPaid && $order->download_token)
      <a href="{{ route('products.download', $order->download_token) }}"
         class="flex-1 py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-semibold shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2 text-sm md:text-base">
        <i class="bi bi-download"></i>
        ดาวน์โหลดสินค้า
        @if($order->downloads_remaining > 0)
          <span class="ml-1 px-2 py-0.5 rounded-full bg-white/20 text-xs font-medium">
            เหลือ {{ $order->downloads_remaining }} ครั้ง
          </span>
        @endif
      </a>
    @endif

    <a href="{{ route('products.my-orders') }}"
       class="flex-shrink-0 py-3.5 px-6 rounded-xl border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 text-sm md:text-base font-medium transition flex items-center justify-center gap-2">
      <i class="bi bi-list-ul"></i> คำสั่งซื้อทั้งหมด
    </a>
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       INLINE CHECKOUT — only when payment is still pending.
       Replaces the old "ชำระเงินเลย" button that bounced users to
       a separate /checkout page. Single-screen flow:
         1. See QR (PromptPay default) or one-tap to bank list
         2. Pay with phone banking app
         3. Drop slip into the dropzone right below
         4. Submit — page reloads to whichever status the system put
            the order in (paid via SlipOK auto, or pending_review)

       Defaults to PromptPay because it's the dominant Thai payment
       rail (one scan, no typing). Bank list is one tap away for
       customers whose bank app doesn't scan well or who prefer typing.
       ═══════════════════════════════════════════════════════════════ --}}
  @if($order->status === 'pending_payment')
    @php
      $promptpay = collect($paymentMethods ?? [])->firstWhere('method_type', 'promptpay');
      $hasPromptpay = $promptpay !== null;
      $hasBanks     = isset($bankAccounts) && $bankAccounts->count() > 0;
      // Default tab: PromptPay if available, else first bank
      $defaultTab = $hasPromptpay ? 'promptpay' : ($hasBanks ? 'bank' : 'promptpay');
    @endphp
    <div class="rounded-3xl bg-white dark:bg-slate-800 border-2 border-indigo-200 dark:border-indigo-500/30 shadow-xl overflow-hidden mb-5"
         x-data="inlineCheckout({ defaultTab: @js($defaultTab) })">

      {{-- Header with amount + tab switcher --}}
      <div class="bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 p-5 lg:p-6 text-white">
        <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
          <div>
            <div class="text-xs uppercase tracking-wider opacity-80 mb-1">ยอดที่ต้องชำระ</div>
            <div class="text-3xl lg:text-4xl font-extrabold leading-none">
              {{ number_format($order->amount, 2) }}
              <span class="text-lg font-medium opacity-80">฿</span>
            </div>
          </div>
          <button type="button"
                  data-copy="{{ number_format($order->amount, 2, '.', '') }}"
                  class="copy-btn shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/20 hover:bg-white/30 text-white text-xs font-semibold transition backdrop-blur">
            <i class="bi bi-clipboard"></i> คัดลอกยอด
          </button>
        </div>

        {{-- Tab switcher — only render if there are multiple options --}}
        @if($hasPromptpay && $hasBanks)
        <div class="flex items-center gap-2 p-1 bg-white/15 backdrop-blur rounded-xl">
          <button type="button" @click="tab = 'promptpay'"
                  :class="tab === 'promptpay' ? 'bg-white text-indigo-700 shadow-md' : 'text-white/80 hover:text-white hover:bg-white/10'"
                  class="flex-1 py-2.5 rounded-lg text-sm font-bold transition flex items-center justify-center gap-2">
            <i class="bi bi-qr-code-scan"></i> PromptPay (แนะนำ)
          </button>
          <button type="button" @click="tab = 'bank'"
                  :class="tab === 'bank' ? 'bg-white text-indigo-700 shadow-md' : 'text-white/80 hover:text-white hover:bg-white/10'"
                  class="flex-1 py-2.5 rounded-lg text-sm font-bold transition flex items-center justify-center gap-2">
            <i class="bi bi-bank2"></i> โอนผ่านธนาคาร
          </button>
        </div>
        @endif
      </div>

      {{-- Body — single screen with QR/banks + slip upload below --}}
      <div class="p-5 lg:p-6 space-y-5">

        {{-- ────── PromptPay panel ────── --}}
        @if($hasPromptpay)
        <div x-show="tab === 'promptpay'" x-transition>
          <div class="flex flex-col sm:flex-row items-center gap-5">
            {{-- QR --}}
            @if(!empty($promptpay->qr_image))
              <div class="relative flex-shrink-0">
                <div class="absolute -inset-1 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl blur opacity-25"></div>
                <div class="relative w-44 h-44 lg:w-52 lg:h-52 p-3 bg-white rounded-2xl border-2 border-emerald-200 shadow-lg">
                  <img src="{{ asset('storage/' . $promptpay->qr_image) }}" alt="PromptPay QR" class="w-full h-full object-contain">
                  <div class="absolute inset-3 overflow-hidden rounded-lg pointer-events-none">
                    <div class="absolute left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-emerald-500 to-transparent shadow-[0_0_10px_rgba(16,185,129,0.8)] animate-qr-scan"></div>
                  </div>
                </div>
              </div>
            @endif
            {{-- Steps + recipient --}}
            <div class="flex-1 min-w-0 space-y-3">
              <div class="text-sm font-semibold text-slate-900 dark:text-white">
                <i class="bi bi-1-circle-fill text-emerald-500 mr-1"></i>
                เปิดแอปธนาคาร → กดสแกน QR
              </div>
              <div class="text-sm font-semibold text-slate-900 dark:text-white">
                <i class="bi bi-2-circle-fill text-emerald-500 mr-1"></i>
                ตรวจยอด <strong class="text-emerald-600 dark:text-emerald-400">฿{{ number_format($order->amount, 2) }}</strong> → กดยืนยัน
              </div>
              <div class="text-sm font-semibold text-slate-900 dark:text-white">
                <i class="bi bi-3-circle-fill text-emerald-500 mr-1"></i>
                บันทึกสลิป → อัปโหลดด้านล่าง 👇
              </div>

              @if(!empty($promptpay->account_holder_name))
              <div class="mt-3 p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20">
                <div class="text-[10px] uppercase tracking-wider text-emerald-700 dark:text-emerald-400 font-bold mb-0.5">โอนถึง</div>
                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ $promptpay->account_holder_name }}</div>
              </div>
              @endif
            </div>
          </div>
        </div>
        @endif

        {{-- ────── Bank panel ────── --}}
        @if($hasBanks)
        <div x-show="tab === 'bank'" x-transition class="space-y-2">
          @foreach($bankAccounts as $bank)
          <div class="rounded-2xl border border-slate-200 dark:border-white/10 hover:border-blue-300 dark:hover:border-blue-500/40 hover:shadow-md transition overflow-hidden">
            <div class="px-4 py-3 flex items-center gap-3">
              <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/10 to-indigo-500/10 dark:from-blue-500/20 dark:to-indigo-500/20 border border-blue-200 dark:border-blue-500/20 flex items-center justify-center flex-shrink-0">
                <i class="bi bi-bank text-blue-600 dark:text-blue-400 text-xl"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-bold text-slate-900 dark:text-white text-sm truncate">{{ $bank->bank_name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $bank->account_holder_name }}</div>
                <div class="font-mono text-base font-bold text-slate-900 dark:text-white tracking-wider mt-0.5">{{ $bank->account_number }}</div>
              </div>
              <button type="button"
                      data-copy="{{ $bank->account_number }}"
                      title="คัดลอกเลขบัญชี"
                      class="copy-btn shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-xl bg-slate-100 dark:bg-white/5 hover:bg-blue-500 hover:text-white text-slate-600 dark:text-slate-300 transition">
                <i class="bi bi-clipboard"></i>
              </button>
            </div>
          </div>
          @endforeach
        </div>
        @endif

        {{-- ────── Slip Upload (always visible) ────── --}}
        <form method="POST" action="{{ route('products.upload-slip', $order->id) }}"
              enctype="multipart/form-data"
              class="pt-4 border-t border-slate-200 dark:border-white/10 space-y-3">
          @csrf
          {{-- Hidden payment_method — auto-driven by tab choice --}}
          <input type="hidden" name="payment_method" :value="tab === 'bank' ? 'bank_transfer' : 'promptpay'">

          <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-300">
            <i class="bi bi-cloud-upload-fill text-purple-500"></i>
            อัปโหลดสลิป
            <span class="text-rose-500">*</span>
          </label>

          <label class="relative block cursor-pointer rounded-2xl border-2 border-dashed transition overflow-hidden group"
                 :class="slipPreview ? 'border-emerald-400 bg-emerald-50/50 dark:bg-emerald-500/5' : (dragging ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10 scale-[1.01]' : 'border-slate-300 dark:border-white/10 hover:border-indigo-400 bg-slate-50 dark:bg-slate-900/50')"
                 @dragover.prevent="dragging = true"
                 @dragleave.prevent="dragging = false"
                 @drop.prevent="handleDrop($event)">
            <input type="file" name="slip_image" accept="image/*" required x-ref="slipInput"
                   @change="handleFile($event)"
                   class="absolute inset-0 opacity-0 cursor-pointer z-10">

            <div x-show="!slipPreview" class="py-8 px-6 text-center">
              <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center mb-3 shadow-lg shadow-indigo-500/40 group-hover:scale-110 transition-transform">
                <i class="bi bi-cloud-upload-fill text-2xl"></i>
              </div>
              <div class="font-bold text-sm text-slate-900 dark:text-white mb-1">แตะเพื่อเลือกสลิป</div>
              <div class="text-xs text-slate-500 dark:text-slate-400">หรือลากรูปมาวาง · JPG/PNG สูงสุด 5MB</div>
            </div>

            <div x-show="slipPreview" x-cloak class="relative p-3">
              <div class="relative rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-900">
                <img :src="slipPreview" alt="Slip preview" class="w-full max-h-72 object-contain">
                <div class="absolute top-2 right-2 inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-500 text-white text-xs font-bold shadow-lg">
                  <i class="bi bi-check-circle-fill"></i> พร้อมส่ง
                </div>
              </div>
              <div class="mt-2 flex items-center justify-between gap-2">
                <span class="text-xs text-slate-500 dark:text-slate-400 truncate" x-text="slipName"></span>
                <button type="button" @click.prevent.stop="clearSlip()"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-500/20 transition text-xs font-medium relative z-20">
                  <i class="bi bi-trash"></i> เปลี่ยน
                </button>
              </div>
            </div>
          </label>

          <button type="submit"
                  :disabled="!slipPreview || submitting"
                  @click="submitting = true"
                  class="relative w-full py-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-bold text-base shadow-lg shadow-emerald-500/40 hover:shadow-xl transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed overflow-hidden group">
            <span class="absolute inset-0 bg-white/20 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700 skew-x-12"></span>
            <i class="bi" :class="submitting ? 'bi-arrow-clockwise animate-spin' : 'bi-check-circle-fill'"></i>
            <span x-show="!submitting">ส่งสลิป — ตรวจสอบอัตโนมัติ</span>
            <span x-show="submitting" x-cloak>กำลังส่ง...</span>
          </button>

          <div class="flex items-center justify-center gap-3 text-[11px] text-slate-500 dark:text-slate-400 flex-wrap">
            <span class="inline-flex items-center gap-1"><i class="bi bi-shield-check text-emerald-500"></i> SSL</span>
            <span>·</span>
            <span class="inline-flex items-center gap-1"><i class="bi bi-lightning-charge-fill text-amber-500"></i> ตรวจอัตโนมัติ</span>
            <span>·</span>
            <span class="inline-flex items-center gap-1"><i class="bi bi-clock-history text-slate-400"></i> สำรอง: ภายใน 24 ชม.</span>
          </div>
        </form>
      </div>
    </div>
  @endif

  {{-- ═══════════════════════════════════════════════════════════════
       ORDER DETAILS
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
    {{-- Product card --}}
    <div class="md:col-span-2 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-5">
      <h3 class="text-xs uppercase tracking-wide font-semibold text-slate-500 dark:text-slate-400 mb-3">สินค้า</h3>
      <a href="{{ route('products.show', $order->product->slug ?? '#') }}" class="flex gap-4 group">
        @if($order->product && $order->product->cover_image)
          <img src="{{ $order->product->cover_image_url }}" class="w-20 h-20 md:w-24 md:h-24 rounded-xl object-cover flex-shrink-0 group-hover:scale-105 transition-transform">
        @else
          <div class="w-20 h-20 md:w-24 md:h-24 rounded-xl bg-gradient-to-br {{ $typeMeta['color'] }} flex items-center justify-center flex-shrink-0">
            <i class="bi {{ $typeMeta['icon'] }} text-white text-2xl opacity-60"></i>
          </div>
        @endif
        <div class="flex-1 min-w-0">
          <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 dark:bg-white/5 text-[10px] font-semibold text-slate-600 dark:text-slate-300 mb-1">
            <i class="bi {{ $typeMeta['icon'] }}"></i> {{ $typeMeta['label'] }}
          </div>
          <div class="font-semibold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 line-clamp-2 leading-snug transition">
            {{ $order->product->name ?? 'สินค้า' }}
          </div>
          @if($order->product && $order->product->short_description)
            <div class="text-xs text-slate-500 dark:text-slate-400 line-clamp-1 mt-1">{{ $order->product->short_description }}</div>
          @endif
        </div>
      </a>
    </div>

    {{-- Total card — label adapts to status so customers don't get
         "ชำระเรียบร้อย" while still seeing the pay-now form. --}}
    <div class="rounded-2xl bg-gradient-to-br
                {{ $isPaid ? 'from-emerald-500 to-teal-600' : ($isCancelled ? 'from-slate-500 to-slate-700' : 'from-amber-500 to-orange-600') }}
                text-white shadow-lg p-5 flex flex-col justify-between">
      <div>
        <div class="text-xs uppercase tracking-wide opacity-75 mb-1">
          {{ $isPaid ? 'ยอดชำระ' : ($isCancelled ? 'ยอดสั่งซื้อ' : 'ยอดที่ต้องชำระ') }}
        </div>
        <div class="text-3xl font-bold">
          @if((float) $order->amount <= 0)
            <span class="text-2xl">FREE</span>
          @else
            {{ number_format($order->amount, 2) }} <span class="text-base font-medium">฿</span>
          @endif
        </div>
      </div>
      <div class="text-xs opacity-75 mt-3 flex items-center gap-1.5">
        @if($isPaid)
          <i class="bi bi-shield-fill-check"></i> ชำระเรียบร้อย
        @elseif($isCancelled)
          <i class="bi bi-x-circle-fill"></i> ยกเลิกแล้ว
        @else
          <i class="bi bi-clock-history"></i> รอชำระ — กดด้านล่างเพื่อจ่าย
        @endif
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       METADATA & SLIP
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-5">
      <h3 class="text-xs uppercase tracking-wide font-semibold text-slate-500 dark:text-slate-400 mb-3">
        <i class="bi bi-info-circle mr-1"></i> ข้อมูลคำสั่งซื้อ
      </h3>
      <dl class="text-sm space-y-3">
        <div class="flex items-center justify-between">
          <dt class="text-slate-500 dark:text-slate-400">เลขที่คำสั่งซื้อ</dt>
          <dd class="font-mono font-semibold text-slate-900 dark:text-white">{{ $order->order_number }}</dd>
        </div>
        <div class="flex items-center justify-between">
          <dt class="text-slate-500 dark:text-slate-400">วันที่สั่งซื้อ</dt>
          <dd class="font-medium text-slate-900 dark:text-white">{{ $order->created_at->format('d/m/Y H:i') }}</dd>
        </div>
        @if($order->payment_method && $order->payment_method !== 'pending')
        <div class="flex items-center justify-between">
          <dt class="text-slate-500 dark:text-slate-400">วิธีชำระเงิน</dt>
          <dd class="font-medium text-slate-900 dark:text-white">{{ $order->payment_method }}</dd>
        </div>
        @endif
        @if($isPaid && $order->expires_at)
        <div class="flex items-center justify-between">
          <dt class="text-slate-500 dark:text-slate-400">ลิงก์หมดอายุ</dt>
          <dd class="font-medium text-rose-600 dark:text-rose-400">{{ \Carbon\Carbon::parse($order->expires_at)->format('d/m/Y') }}</dd>
        </div>
        @endif
        @if($isPaid && isset($order->downloads_remaining))
        <div class="flex items-center justify-between">
          <dt class="text-slate-500 dark:text-slate-400">ดาวน์โหลดได้อีก</dt>
          <dd class="font-medium text-emerald-600 dark:text-emerald-400">{{ $order->downloads_remaining }} ครั้ง</dd>
        </div>
        @endif
      </dl>
    </div>

    {{-- Slip --}}
    @if($order->slip_image)
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-5">
      <h3 class="text-xs uppercase tracking-wide font-semibold text-slate-500 dark:text-slate-400 mb-3">
        <i class="bi bi-receipt mr-1"></i> หลักฐานการชำระเงิน
      </h3>
      @php $slipUrl = $order->slip_image_url; @endphp
      <a href="{{ $slipUrl }}" target="_blank" rel="noopener" class="block group">
        <img src="{{ $slipUrl }}"
             alt="Payment slip"
             class="w-full rounded-xl border border-slate-200 dark:border-white/10 group-hover:scale-[1.02] transition-transform max-h-64 object-contain bg-slate-50 dark:bg-slate-900">
        <div class="mt-2 text-xs text-center text-slate-500 dark:text-slate-400 group-hover:text-indigo-500 transition">
          <i class="bi bi-zoom-in mr-1"></i> คลิกเพื่อดูขนาดเต็ม
        </div>
      </a>
    </div>
    @else
    <div class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-white/10 p-5 flex flex-col items-center justify-center text-center">
      <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center mb-2">
        <i class="bi bi-receipt text-xl text-slate-400"></i>
      </div>
      <p class="text-sm text-slate-500 dark:text-slate-400">ยังไม่มีหลักฐานการชำระเงิน</p>
    </div>
    @endif
  </div>
</div>

@push('styles')
<style>
  /* QR scan-line animation matches the standalone checkout flavour
     so customers feel the same "machine is working" cue inline. */
  @keyframes qr-scan {
    0%, 100% { top: 0; }
    50%      { top: 100%; }
  }
  .animate-qr-scan { animation: qr-scan 2.4s ease-in-out infinite; }
</style>
@endpush

@push('scripts')
<script>
/* Alpine component for the inline checkout (PromptPay/bank tabs +
   slip dropzone). Mirrors productCheckout() from the legacy /checkout
   page so behaviour stays identical — just consolidated onto one page. */
function inlineCheckout(config) {
  return {
    tab: config.defaultTab || 'promptpay',
    slipPreview: null,
    slipName: '',
    dragging: false,
    submitting: false,

    handleFile(e) {
      const file = e.target.files && e.target.files[0];
      this.loadFile(file);
    },
    handleDrop(e) {
      this.dragging = false;
      const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (!file) return;
      try {
        const dt = new DataTransfer();
        dt.items.add(file);
        if (this.$refs.slipInput) this.$refs.slipInput.files = dt.files;
      } catch (_) { /* older browsers */ }
      this.loadFile(file);
    },
    loadFile(file) {
      if (!file) { this.slipPreview = null; this.slipName = ''; return; }
      this.slipName = file.name;
      const reader = new FileReader();
      reader.onload = ev => { this.slipPreview = ev.target.result; };
      reader.readAsDataURL(file);
    },
    clearSlip() {
      this.slipPreview = null; this.slipName = '';
      if (this.$refs.slipInput) this.$refs.slipInput.value = '';
    },
  };
}

/* Copy-to-clipboard for any .copy-btn in the inline checkout block.
   Scoped delegation handler — works on dynamically-rendered buttons,
   non-Alpine fallback included. */
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.copy-btn');
  if (!btn) return;
  const text = btn.dataset.copy;
  if (!text) return;

  const doCopy = (t) => {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(t);
    }
    const ta = document.createElement('textarea');
    ta.value = t;
    ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (_) {}
    document.body.removeChild(ta);
    return Promise.resolve();
  };

  doCopy(text).then(() => {
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2-circle"></i> คัดลอกแล้ว';
    setTimeout(() => { btn.innerHTML = old; }, 1500);
  });
});

function orderStatusPoller(config) {
  return {
    orderId: config.orderId,
    currentStatus: config.currentStatus,
    statusUrl: config.statusUrl,
    polling: false,
    timer: null,
    toast: { show: false, message: '', type: 'info' },

    init() {
      // Only poll when waiting for admin action
      if (['pending_payment', 'pending_review'].includes(this.currentStatus)) {
        this.polling = true;
        this.start();
      }
    },

    start() {
      this.timer = setInterval(() => this.check(), 5000);
      // Also check when tab regains focus
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden && this.polling) this.check();
      });
    },

    stop() {
      this.polling = false;
      if (this.timer) clearInterval(this.timer);
    },

    async check() {
      try {
        const res = await fetch(this.statusUrl, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        if (data.status && data.status !== this.currentStatus) {
          this.onStatusChange(data);
        }
      } catch (e) { /* silent */ }
    },

    onStatusChange(data) {
      this.stop();
      if (data.status === 'paid') {
        this.playSound('success');
        this.showToast('คำสั่งซื้อของคุณได้รับการอนุมัติแล้ว! กำลังรีเฟรช...', 'success');
      } else if (data.status === 'cancelled') {
        this.playSound('error');
        this.showToast('คำสั่งซื้อถูกปฏิเสธ กำลังรีเฟรช...', 'error');
      } else {
        this.showToast('สถานะคำสั่งซื้อเปลี่ยนแปลง กำลังรีเฟรช...', 'info');
      }
      setTimeout(() => window.location.reload(), 1800);
    },

    showToast(message, type = 'info') {
      this.toast = { show: true, message, type };
      setTimeout(() => { this.toast.show = false; }, 4000);
    },

    playSound(type) {
      try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.connect(g); g.connect(ctx.destination);
        o.type = 'sine';
        const now = ctx.currentTime;
        if (type === 'success') {
          o.frequency.setValueAtTime(523.25, now);
          o.frequency.setValueAtTime(659.25, now + 0.12);
          o.frequency.setValueAtTime(783.99, now + 0.24);
          g.gain.setValueAtTime(0.15, now);
          g.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
          o.start(now); o.stop(now + 0.5);
        } else {
          o.frequency.setValueAtTime(400, now);
          o.frequency.exponentialRampToValueAtTime(180, now + 0.3);
          g.gain.setValueAtTime(0.15, now);
          g.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
          o.start(now); o.stop(now + 0.35);
        }
      } catch (e) { /* autoplay blocked — no-op */ }
    }
  }
}
</script>
@endpush
@endsection
