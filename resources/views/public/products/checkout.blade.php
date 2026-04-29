@extends('layouts.app')

@section('title', 'ชำระเงิน — ' . $order->order_number)

@section('hero')
{{-- ═══════════════════════════════════════════════════════════════
     HERO — Gradient header with breadcrumb + title + stepper
     ═══════════════════════════════════════════════════════════════ --}}
<div class="relative overflow-hidden bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 dark:from-slate-900 dark:via-indigo-950 dark:to-purple-950">
  {{-- Decorative layers --}}
  <div class="absolute inset-0 pointer-events-none opacity-60 dark:opacity-100"
       style="background-image:url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 40L40 0M-10 10L10-10M30 50L50 30&quot; stroke=&quot;rgba(100,116,139,0.06)&quot; stroke-width=&quot;1&quot;/%3E%3C/svg%3E');"></div>
  <div class="absolute w-96 h-96 rounded-full bg-indigo-400/20 dark:bg-indigo-500/20 blur-3xl top-[-120px] right-[-80px] pointer-events-none"></div>
  <div class="absolute w-80 h-80 rounded-full bg-pink-400/15 dark:bg-pink-500/10 blur-3xl bottom-[-80px] left-[-80px] pointer-events-none"></div>

  <div class="relative max-w-7xl mx-auto px-4 py-8 md:py-10 lg:py-14">
    {{-- Breadcrumb --}}
    <nav aria-label="Breadcrumb" class="mb-4">
      <ol class="flex items-center gap-2 text-xs lg:text-sm text-slate-600 dark:text-slate-400 flex-wrap">
        <li><a href="{{ url('/') }}" class="hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"><i class="bi bi-house-door mr-1"></i>หน้าแรก</a></li>
        <li class="text-slate-400 dark:text-slate-500"><i class="bi bi-chevron-right text-[10px]"></i></li>
        <li><a href="{{ route('products.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors">สินค้าดิจิทัล</a></li>
        <li class="text-slate-400 dark:text-slate-500"><i class="bi bi-chevron-right text-[10px]"></i></li>
        <li class="font-semibold text-slate-800 dark:text-white">ชำระเงิน</li>
      </ol>
    </nav>

    {{-- Title row --}}
    <div class="flex items-start justify-between flex-wrap gap-4 mb-6 lg:mb-8">
      <div class="flex items-center gap-3 lg:gap-4">
        <span class="inline-flex items-center justify-center w-12 h-12 md:w-14 md:h-14 lg:w-16 lg:h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30">
          <i class="bi bi-credit-card-fill text-xl md:text-2xl lg:text-3xl"></i>
        </span>
        <div>
          <h1 class="text-2xl md:text-3xl lg:text-4xl font-bold text-slate-900 dark:text-white tracking-tight leading-tight">ชำระเงิน</h1>
          <p class="text-sm lg:text-base text-slate-500 dark:text-slate-400 mt-0.5 lg:mt-1 font-mono">{{ $order->order_number }}</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 lg:px-4 lg:py-2 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-xs lg:text-sm font-bold">
          <span class="w-1.5 h-1.5 lg:w-2 lg:h-2 rounded-full bg-amber-500 animate-pulse"></span>
          รอชำระเงิน
        </span>
      </div>
    </div>

    {{-- Progress Stepper --}}
    <div class="rounded-2xl bg-white/70 dark:bg-white/5 backdrop-blur-md border border-white/50 dark:border-white/10 shadow-sm p-5 lg:p-7">
      @php
        $steps = [
          ['icon' => 'bi-cart-check-fill',    'label' => 'สร้างคำสั่งซื้อ',  'state' => 'done'],
          ['icon' => 'bi-credit-card-fill',   'label' => 'ชำระเงิน',         'state' => 'active'],
          ['icon' => 'bi-hourglass-split',    'label' => 'รอตรวจสอบ',       'state' => 'pending'],
          ['icon' => 'bi-cloud-download-fill','label' => 'ดาวน์โหลด',       'state' => 'pending'],
        ];
      @endphp
      <div class="relative">
        {{-- Progress bar track --}}
        <div class="absolute top-5 lg:top-6 left-[calc(12.5%+1rem)] right-[calc(12.5%+1rem)] h-[3px] bg-slate-200 dark:bg-white/10 rounded-full"></div>
        {{-- Progress fill (done → active) --}}
        <div class="absolute top-5 lg:top-6 left-[calc(12.5%+1rem)] h-[3px] rounded-full bg-gradient-to-r from-emerald-500 via-teal-500 to-indigo-500 shadow-md shadow-emerald-500/30" style="width:calc(33.3% - 2rem)"></div>

        <div class="relative grid grid-cols-4">
          @foreach($steps as $i => $s)
            @php
              $stateClasses = match($s['state']) {
                'done'    => 'bg-gradient-to-br from-emerald-500 to-teal-500 text-white shadow-lg shadow-emerald-500/40',
                'active'  => 'bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/50 ring-4 ring-indigo-200 dark:ring-indigo-500/30 scale-110',
                default   => 'bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-white/10 text-slate-400 dark:text-slate-500',
              };
              $textClass = $s['state'] === 'pending' ? 'text-slate-500 dark:text-slate-400' : 'text-slate-900 dark:text-white';
            @endphp
            <div class="flex flex-col items-center text-center">
              <div class="relative w-10 h-10 md:w-11 md:h-11 lg:w-12 lg:h-12 rounded-full flex items-center justify-center transition-all duration-300 {{ $stateClasses }} {{ $s['state'] === 'active' ? 'animate-pulse' : '' }}">
                @if($s['state'] === 'done')
                  <i class="bi bi-check-lg text-lg lg:text-xl"></i>
                @else
                  <i class="bi {{ $s['icon'] }} text-sm md:text-base lg:text-lg"></i>
                @endif
              </div>
              <div class="mt-2.5 lg:mt-3 text-[11px] md:text-xs lg:text-sm font-semibold {{ $textClass }} leading-tight">{{ $s['label'] }}</div>
              @if($s['state'] === 'active')
                <div class="text-[10px] lg:text-xs text-indigo-600 dark:text-indigo-400 font-bold mt-0.5">· ตอนนี้ ·</div>
              @elseif($s['state'] === 'done')
                <div class="text-[10px] lg:text-xs text-emerald-600 dark:text-emerald-400 mt-0.5">เรียบร้อย</div>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
  <div class="absolute bottom-0 left-0 right-0 h-px" style="background:linear-gradient(90deg,transparent,rgba(99,102,241,0.3),transparent);"></div>
</div>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 lg:py-10" x-data="productCheckout()">

  @if(session('error'))
    <div class="mb-5 p-4 rounded-2xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 text-sm flex items-start gap-2">
      <i class="bi bi-exclamation-circle-fill mt-0.5 flex-shrink-0"></i>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  <div class="grid grid-cols-1  gap-6 lg:gap-8 xl:gap-10">

    {{-- ═══════════════════════════════════════════════════════════════
         LEFT — Payment methods + upload slip
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="lg:col-span-3 space-y-6 min-w-0">

      {{-- ────────────── Bank Transfer Section ────────────── --}}
      @if($bankAccounts->count() > 0)
      <section class="rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
        <header class="relative px-6 py-5 lg:px-7 lg:py-6 overflow-hidden">
          <div class="absolute inset-0 bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500 opacity-10 dark:opacity-15"></div>
          <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-blue-400/20 blur-2xl pointer-events-none"></div>
          <div class="relative flex items-center gap-3">
            <div class="w-11 h-11 lg:w-12 lg:h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center shadow-lg shadow-blue-500/30 flex-shrink-0">
              <i class="bi bi-bank2 text-lg lg:text-xl"></i>
            </div>
            <div class="min-w-0 flex-1">
              <h3 class="font-bold text-slate-900 dark:text-white truncate flex items-center gap-2 lg:text-lg">
                โอนเงินผ่านธนาคาร
                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 text-[10px] lg:text-xs font-bold">{{ $bankAccounts->count() }}</span>
              </h3>
              <p class="text-xs lg:text-sm text-slate-500 dark:text-slate-400 mt-0.5">คลิกปุ่ม <i class="bi bi-clipboard"></i> เพื่อคัดลอกเลขบัญชี</p>
            </div>
          </div>
        </header>

        <div class="p-3 md:p-4 lg:p-4 grid grid-cols-1 lg:grid-cols-2 gap-2 lg:gap-3">
          @foreach($bankAccounts as $bank)
          <div class="relative group rounded-2xl hover:bg-slate-50 dark:hover:bg-white/5 lg:border lg:border-slate-200/70 lg:dark:border-white/10 lg:hover:border-blue-300 lg:dark:hover:border-blue-500/40 lg:hover:shadow-md lg:hover:shadow-blue-500/10 transition-all overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-blue-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
            <div class="relative px-3 py-3 lg:px-4 lg:py-4 flex items-center gap-3 lg:gap-4">
              <div class="w-12 h-12 lg:w-14 lg:h-14 rounded-2xl bg-gradient-to-br from-blue-500/10 to-indigo-500/10 dark:from-blue-500/20 dark:to-indigo-500/20 border border-blue-200 dark:border-blue-500/20 flex items-center justify-center flex-shrink-0">
                <i class="bi bi-bank text-blue-600 dark:text-blue-400 text-xl lg:text-2xl"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                  <span class="font-bold text-slate-900 dark:text-white text-sm lg:text-base truncate">{{ $bank->bank_name }}</span>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate mb-1">{{ $bank->account_holder_name }}</div>
                <div class="font-mono text-base lg:text-lg font-bold text-slate-900 dark:text-white tracking-[0.05em]">{{ $bank->account_number }}</div>
              </div>
              <button type="button"
                      data-copy="{{ $bank->account_number }}"
                      title="คัดลอกเลขบัญชี"
                      class="copy-btn inline-flex items-center justify-center w-11 h-11 lg:w-12 lg:h-12 rounded-xl bg-slate-100 dark:bg-white/5 hover:bg-gradient-to-br hover:from-blue-500 hover:to-indigo-600 hover:text-white text-slate-600 dark:text-slate-300 transition-all duration-200 hover:scale-110 active:scale-95 hover:shadow-lg hover:shadow-blue-500/30 flex-shrink-0">
                <i class="bi bi-clipboard text-base lg:text-lg"></i>
              </button>
            </div>
          </div>
          @endforeach
        </div>
      </section>
      @endif

      {{-- ────────────── PromptPay Section ────────────── --}}
      @php $promptpay = collect($paymentMethods)->firstWhere('method_type', 'promptpay'); @endphp
      @if($promptpay)
      <section class="rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
        <header class="relative px-6 py-5 lg:px-7 lg:py-6 overflow-hidden">
          <div class="absolute inset-0 bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 opacity-10 dark:opacity-15"></div>
          <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-emerald-400/20 blur-2xl pointer-events-none"></div>
          <div class="relative flex items-center gap-3">
            <div class="w-11 h-11 lg:w-12 lg:h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center shadow-lg shadow-emerald-500/30 flex-shrink-0">
              <i class="bi bi-qr-code-scan text-lg lg:text-xl"></i>
            </div>
            <div class="min-w-0 flex-1">
              <h3 class="font-bold text-slate-900 dark:text-white truncate lg:text-lg">{{ $promptpay->method_name }}</h3>
              <p class="text-xs lg:text-sm text-slate-500 dark:text-slate-400 mt-0.5">สแกน QR Code ด้วยแอปธนาคาร</p>
            </div>
          </div>
        </header>

        <div class="p-6 lg:p-7 flex items-start gap-5 lg:gap-7 flex-wrap">
          {{-- QR --}}
          <div class="relative flex-shrink-0 group mx-auto lg:mx-0">
            <div class="absolute -inset-1 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl blur opacity-25 group-hover:opacity-40 transition-opacity"></div>
            @if(!empty($promptpay->qr_image))
              <div class="relative w-40 h-40 lg:w-52 lg:h-52 p-3 bg-white rounded-2xl border-2 border-emerald-200 dark:border-emerald-500/30 shadow-lg">
                <img src="{{ asset('storage/' . $promptpay->qr_image) }}" alt="PromptPay QR" class="w-full h-full object-contain">
                {{-- Scan line animation --}}
                <div class="absolute inset-3 overflow-hidden rounded-lg pointer-events-none">
                  <div class="absolute left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-emerald-500 to-transparent shadow-[0_0_10px_rgba(16,185,129,0.8)] animate-qr-scan"></div>
                </div>
              </div>
            @else
              <div class="relative w-40 h-40 lg:w-52 lg:h-52 p-3 bg-slate-50 dark:bg-slate-900 rounded-2xl border-2 border-dashed border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-400">
                <i class="bi bi-qr-code text-5xl lg:text-6xl opacity-30"></i>
              </div>
            @endif
          </div>

          {{-- Info --}}
          <div class="flex-1 min-w-[200px] space-y-3 lg:space-y-4">
            @if(!empty($promptpay->account_number))
            <div>
              <div class="text-[10px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-bold mb-1">เบอร์/เลข PromptPay</div>
              <div class="flex items-center gap-2">
                <code class="flex-1 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-white/10 font-mono font-bold text-slate-900 dark:text-white break-all">{{ $promptpay->account_number }}</code>
                <button type="button" data-copy="{{ $promptpay->account_number }}"
                        class="copy-btn inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 dark:bg-white/5 hover:bg-gradient-to-br hover:from-emerald-500 hover:to-teal-500 hover:text-white text-slate-600 dark:text-slate-300 transition-all duration-200 hover:scale-110 active:scale-95 flex-shrink-0">
                  <i class="bi bi-clipboard"></i>
                </button>
              </div>
            </div>
            @endif

            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20">
              <div class="text-xs text-emerald-900 dark:text-emerald-200 leading-relaxed">
                <i class="bi bi-info-circle-fill text-emerald-500 mr-1"></i>
                สแกน QR ด้วยแอปธนาคาร → ตรวจสอบยอด → ชำระ → อัปโหลดสลิปด้านล่าง
              </div>
            </div>
          </div>
        </div>
      </section>
      @endif

      {{-- ────────────── Upload Slip Form ────────────── --}}
      <form method="POST" action="{{ route('products.upload-slip', $order->id) }}" enctype="multipart/form-data"
            class="rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
        @csrf
        <header class="relative px-6 py-5 lg:px-7 lg:py-6 overflow-hidden">
          <div class="absolute inset-0 bg-gradient-to-r from-purple-500 via-pink-500 to-rose-500 opacity-10 dark:opacity-15"></div>
          <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-purple-400/20 blur-2xl pointer-events-none"></div>
          <div class="relative flex items-center gap-3">
            <div class="w-11 h-11 lg:w-12 lg:h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 text-white flex items-center justify-center shadow-lg shadow-purple-500/30 flex-shrink-0">
              <i class="bi bi-cloud-upload-fill text-lg lg:text-xl"></i>
            </div>
            <div class="min-w-0 flex-1">
              <h3 class="font-bold text-slate-900 dark:text-white truncate lg:text-lg">อัปโหลดหลักฐานการชำระเงิน</h3>
              <p class="text-xs lg:text-sm text-slate-500 dark:text-slate-400 mt-0.5">แอดมินจะตรวจสอบภายใน 24 ชั่วโมง</p>
            </div>
          </div>
        </header>

        <div class="p-6 lg:p-7 space-y-5 lg:space-y-6">
          {{-- Payment method selector --}}
          <div>
            <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
              <i class="bi bi-wallet2 text-indigo-500"></i> เลือกวิธีที่ใช้ชำระ
              <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <select name="payment_method" x-model="selectedMethod" required
                      class="w-full pl-4 pr-10 py-3 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white dark:focus:bg-slate-900 transition text-sm appearance-none cursor-pointer">
                <option value="">— เลือกวิธีชำระเงิน —</option>
                @if($bankAccounts->count() > 0)
                  <optgroup label="🏦 โอนผ่านธนาคาร">
                    @foreach($bankAccounts as $bank)
                      <option value="bank_{{ $bank->id }}">{{ $bank->bank_name }} — {{ $bank->account_number }}</option>
                    @endforeach
                  </optgroup>
                @endif
                @if($paymentMethods->count() > 0)
                  <optgroup label="💳 วิธีอื่นๆ">
                    @foreach($paymentMethods as $method)
                      <option value="{{ $method->method_type }}">{{ $method->method_name }}</option>
                    @endforeach
                  </optgroup>
                @endif
              </select>
              <i class="bi bi-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
            </div>
            @error('payment_method')<p class="text-xs text-rose-500 mt-1 flex items-center gap-1"><i class="bi bi-exclamation-circle"></i>{{ $message }}</p>@enderror
          </div>

          {{-- Slip upload dropzone --}}
          <div>
            <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
              <i class="bi bi-image text-indigo-500"></i> สลิปการโอนเงิน
              <span class="text-rose-500">*</span>
            </label>
            <label class="relative block cursor-pointer rounded-2xl border-2 border-dashed transition-all duration-300 overflow-hidden group"
                   :class="slipPreview ? 'border-emerald-400 dark:border-emerald-500 bg-emerald-50/50 dark:bg-emerald-500/5' : (dragging ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10 scale-[1.01]' : 'border-slate-300 dark:border-white/10 hover:border-indigo-400 dark:hover:border-indigo-500 bg-slate-50 dark:bg-slate-900/50 hover:bg-indigo-50/50 dark:hover:bg-indigo-500/5')"
                   @dragover.prevent="dragging = true"
                   @dragleave.prevent="dragging = false"
                   @drop.prevent="handleDrop($event)">
              <input type="file" name="slip_image" accept="image/*" required x-ref="slipInput"
                     @change="handleFile($event)"
                     class="absolute inset-0 opacity-0 cursor-pointer z-10">

              {{-- Empty state --}}
              <div x-show="!slipPreview" class="py-10 lg:py-14 px-6 text-center">
                <div class="relative inline-block mb-4 lg:mb-5">
                  <div class="absolute -inset-2 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-3xl blur-xl opacity-30 group-hover:opacity-60 transition-opacity"></div>
                  <div class="relative w-16 h-16 lg:w-20 lg:h-20 rounded-3xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center mx-auto shadow-xl shadow-indigo-500/40 group-hover:scale-110 transition-transform duration-300">
                    <i class="bi bi-cloud-upload-fill text-2xl lg:text-3xl"></i>
                  </div>
                </div>
                <div class="font-bold text-base lg:text-lg text-slate-900 dark:text-white mb-1">คลิกเพื่อเลือกไฟล์</div>
                <div class="text-sm lg:text-base text-slate-500 dark:text-slate-400 mb-3">หรือ <span class="font-semibold text-indigo-500">ลากไฟล์มาวาง</span> ที่นี่</div>
                <div class="inline-flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                  <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10">
                    <i class="bi bi-file-earmark-image"></i> JPG
                  </span>
                  <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10">
                    <i class="bi bi-file-earmark-image"></i> PNG
                  </span>
                  <span>· สูงสุด 5MB</span>
                </div>
              </div>

              {{-- Preview state --}}
              <div x-show="slipPreview" x-cloak class="relative p-4">
                <div class="relative rounded-xl overflow-hidden shadow-lg bg-slate-100 dark:bg-slate-900">
                  <img :src="slipPreview" alt="Slip preview" class="w-full max-h-96 object-contain">
                  {{-- Success badge --}}
                  <div class="absolute top-3 right-3 inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500 text-white text-xs font-bold shadow-lg">
                    <i class="bi bi-check-circle-fill"></i> พร้อมส่ง
                  </div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 flex-wrap">
                  <div class="flex items-center gap-2 min-w-0 flex-1">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0">
                      <i class="bi bi-file-earmark-image-fill"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div class="text-xs font-semibold text-slate-900 dark:text-white truncate" x-text="slipName"></div>
                      <div class="text-[10px] text-slate-500 dark:text-slate-400" x-text="slipSize"></div>
                    </div>
                  </div>
                  <button type="button"
                          @click.prevent.stop="clearSlip()"
                          class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-500/20 transition text-xs font-medium relative z-20">
                    <i class="bi bi-trash"></i> ลบ
                  </button>
                </div>
              </div>
            </label>
            @error('slip_image')<p class="text-xs text-rose-500 mt-1 flex items-center gap-1"><i class="bi bi-exclamation-circle"></i>{{ $message }}</p>@enderror
          </div>

          {{-- Submit --}}
          <button type="submit"
                  class="relative w-full py-3.5 lg:py-4 rounded-xl bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold text-base lg:text-lg shadow-lg shadow-emerald-500/40 hover:shadow-xl hover:shadow-emerald-500/50 transition-all duration-200 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:shadow-lg overflow-hidden group"
                  :disabled="!selectedMethod || !slipPreview">
            <span class="absolute inset-0 bg-white/20 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700 skew-x-12"></span>
            <i class="bi bi-check-circle-fill text-lg lg:text-xl relative"></i>
            <span class="relative">ส่งหลักฐานการชำระเงิน</span>
            <i class="bi bi-arrow-right relative"></i>
          </button>

          {{-- Security notice --}}
          <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 justify-center flex-wrap">
            <span class="inline-flex items-center gap-1"><i class="bi bi-shield-check text-emerald-500"></i> ปลอดภัย</span>
            <span class="text-slate-300 dark:text-slate-600">·</span>
            <span class="inline-flex items-center gap-1"><i class="bi bi-lock-fill text-emerald-500"></i> เข้ารหัส SSL</span>
            <span class="text-slate-300 dark:text-slate-600">·</span>
            <span class="inline-flex items-center gap-1"><i class="bi bi-clock-history text-emerald-500"></i> ตรวจสอบใน 24 ชม.</span>
          </div>
        </div>
      </form>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         RIGHT — Order summary (sticky, premium)
         ═══════════════════════════════════════════════════════════════ --}}
    <aside class="lg:col-span-2 min-w-0">
      <div class="lg:sticky lg:top-24 space-y-4">

        {{-- Main summary card --}}
        <div class="relative rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-xl overflow-hidden">
          {{-- Decorative top gradient --}}
          <div class="absolute inset-x-0 top-0 h-32 bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 opacity-10 dark:opacity-20 pointer-events-none"></div>
          <div class="absolute -top-8 -right-8 w-40 h-40 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 opacity-10 blur-3xl pointer-events-none"></div>

          <div class="relative p-6 lg:p-7">
            {{-- Label --}}
            <div class="flex items-center justify-between mb-4 lg:mb-5">
              <div class="inline-flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 lg:w-9 lg:h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
                  <i class="bi bi-receipt text-sm lg:text-base"></i>
                </span>
                <h3 class="font-bold text-slate-900 dark:text-white lg:text-lg">สรุปคำสั่งซื้อ</h3>
              </div>
            </div>

            {{-- Product preview --}}
            <div class="flex gap-3 p-3 rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-900/50 border border-slate-200 dark:border-white/5 mb-5">
              @if($order->product && $order->product->cover_image)
                <div class="relative w-20 h-20 rounded-xl overflow-hidden flex-shrink-0 shadow-md">
                  <img src="{{ $order->product->cover_image_url }}" class="w-full h-full object-cover">
                </div>
              @else
                <div class="w-20 h-20 rounded-xl bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center flex-shrink-0 shadow-md">
                  <i class="bi bi-box-seam text-white text-2xl opacity-70"></i>
                </div>
              @endif
              <div class="min-w-0 flex-1 flex flex-col justify-between">
                <div>
                  <div class="font-bold text-slate-900 dark:text-white text-sm line-clamp-2 leading-snug">{{ $order->product->name ?? '—' }}</div>
                  @if($order->product && $order->product->product_type)
                    <div class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-[10px] font-semibold">
                      <i class="bi bi-box-seam"></i> {{ ucfirst($order->product->product_type) }}
                    </div>
                  @endif
                </div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono truncate">{{ $order->order_number }}</div>
              </div>
            </div>

            {{-- Pricing breakdown --}}
            <dl class="space-y-2.5 mb-5 pb-5 border-b border-slate-100 dark:border-white/5">
              <div class="flex justify-between items-center text-sm">
                <dt class="text-slate-500 dark:text-slate-400 inline-flex items-center gap-1.5">
                  <i class="bi bi-tag text-xs"></i> ราคาสินค้า
                </dt>
                <dd class="text-slate-900 dark:text-white font-semibold">{{ number_format($order->amount, 2) }} ฿</dd>
              </div>
              <div class="flex justify-between items-center text-sm">
                <dt class="text-slate-500 dark:text-slate-400 inline-flex items-center gap-1.5">
                  <i class="bi bi-percent text-xs"></i> ค่าธรรมเนียม
                </dt>
                <dd class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold">
                  <i class="bi bi-check-circle-fill text-xs"></i> ฟรี
                </dd>
              </div>
              <div class="flex justify-between items-center text-sm">
                <dt class="text-slate-500 dark:text-slate-400 inline-flex items-center gap-1.5">
                  <i class="bi bi-truck text-xs"></i> ค่าจัดส่ง
                </dt>
                <dd class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold">
                  <i class="bi bi-lightning-charge-fill text-xs"></i> ดาวน์โหลดทันที
                </dd>
              </div>
            </dl>

            {{-- Total --}}
            <div class="relative rounded-2xl bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 p-0.5 shadow-lg shadow-indigo-500/20 mb-4">
              <div class="rounded-[15px] bg-white dark:bg-slate-800 px-4 py-3 lg:px-5 lg:py-4">
                <div class="flex items-baseline justify-between gap-2">
                  <div>
                    <div class="text-[10px] lg:text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">ยอดที่ต้องโอน</div>
                    <div class="text-xs lg:text-sm text-slate-500 dark:text-slate-400">รวมทั้งสิ้น</div>
                  </div>
                  <div class="text-right">
                    <div class="text-3xl lg:text-4xl font-extrabold bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 bg-clip-text text-transparent leading-none">
                      {{ number_format($order->amount, 2) }}
                    </div>
                    <div class="text-xs lg:text-sm text-slate-500 dark:text-slate-400 font-semibold mt-0.5 lg:mt-1">THB</div>
                  </div>
                </div>
              </div>
            </div>

            {{-- Tip --}}
            <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
              <div class="flex items-start gap-2 text-xs text-amber-900 dark:text-amber-200 leading-relaxed">
                <i class="bi bi-lightbulb-fill text-amber-500 flex-shrink-0 mt-0.5"></i>
                <span><strong class="font-bold">เคล็ดลับ:</strong> โอนจำนวนตามยอดเต็ม (รวมทศนิยม) เพื่อให้ระบบตรวจสอบอัตโนมัติได้เร็วขึ้น</span>
              </div>
            </div>
          </div>
        </div>

        {{-- Benefits mini strip --}}
        <div class="grid grid-cols-3 gap-2 lg:gap-3">
          <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 p-3 lg:p-4 text-center hover:shadow-md transition">
            <div class="w-8 h-8 lg:w-10 lg:h-10 mx-auto mb-1.5 lg:mb-2 rounded-lg lg:rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center shadow-sm">
              <i class="bi bi-lightning-charge-fill text-sm lg:text-base"></i>
            </div>
            <div class="text-[10px] lg:text-xs font-semibold text-slate-900 dark:text-white leading-tight">ทันที</div>
          </div>
          <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 p-3 lg:p-4 text-center hover:shadow-md transition">
            <div class="w-8 h-8 lg:w-10 lg:h-10 mx-auto mb-1.5 lg:mb-2 rounded-lg lg:rounded-xl bg-gradient-to-br from-blue-500 to-indigo-500 text-white flex items-center justify-center shadow-sm">
              <i class="bi bi-shield-fill-check text-sm lg:text-base"></i>
            </div>
            <div class="text-[10px] lg:text-xs font-semibold text-slate-900 dark:text-white leading-tight">ปลอดภัย</div>
          </div>
          <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 p-3 lg:p-4 text-center hover:shadow-md transition">
            <div class="w-8 h-8 lg:w-10 lg:h-10 mx-auto mb-1.5 lg:mb-2 rounded-lg lg:rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 text-white flex items-center justify-center shadow-sm">
              <i class="bi bi-infinity text-sm lg:text-base"></i>
            </div>
            <div class="text-[10px] lg:text-xs font-semibold text-slate-900 dark:text-white leading-tight">ตลอดชีพ</div>
          </div>
        </div>

        {{-- Help link --}}
        <a href="{{ route('support.index') }}"
           class="group flex items-center gap-3 p-4 lg:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-md transition-all">
          <div class="w-10 h-10 lg:w-12 lg:h-12 rounded-xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-500/20 dark:to-purple-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
            <i class="bi bi-chat-heart-fill lg:text-lg"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs lg:text-sm font-bold text-slate-900 dark:text-white">มีปัญหาการชำระเงิน?</div>
            <div class="text-[10px] lg:text-xs text-slate-500 dark:text-slate-400">ติดต่อทีมซัพพอร์ต · ตอบเร็ว</div>
          </div>
          <i class="bi bi-arrow-right text-slate-400 group-hover:text-indigo-500 group-hover:translate-x-1 transition-all flex-shrink-0"></i>
        </a>
      </div>
    </aside>
  </div>
</div>

@push('styles')
<style>
  [x-cloak] { display: none !important; }

  @keyframes qr-scan {
    0%   { top: 0; opacity: 0; }
    10%  { opacity: 1; }
    90%  { opacity: 1; }
    100% { top: 100%; opacity: 0; }
  }
  .animate-qr-scan {
    animation: qr-scan 2.5s ease-in-out infinite;
  }
</style>
@endpush

@push('scripts')
<script>
function productCheckout() {
  return {
    selectedMethod: '',
    slipPreview: null,
    slipName: '',
    slipSize: '',
    dragging: false,

    handleFile(e) {
      const file = e.target.files && e.target.files[0];
      this.loadFile(file);
    },

    handleDrop(e) {
      this.dragging = false;
      const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (!file) return;
      // Assign to input so form submission includes it
      try {
        const dt = new DataTransfer();
        dt.items.add(file);
        if (this.$refs.slipInput) this.$refs.slipInput.files = dt.files;
      } catch (_) { /* older browsers */ }
      this.loadFile(file);
    },

    loadFile(file) {
      if (!file) {
        this.slipPreview = null;
        this.slipName = '';
        this.slipSize = '';
        return;
      }
      this.slipName = file.name;
      this.slipSize = this.formatSize(file.size);
      const reader = new FileReader();
      reader.onload = ev => { this.slipPreview = ev.target.result; };
      reader.readAsDataURL(file);
    },

    formatSize(bytes) {
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / 1024 / 1024).toFixed(2) + ' MB';
    },

    clearSlip() {
      this.slipPreview = null;
      this.slipName = '';
      this.slipSize = '';
      if (this.$refs.slipInput) this.$refs.slipInput.value = '';
    },
  };
}

// Copy-to-clipboard for all .copy-btn buttons (works even if Alpine fails)
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
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (_) {}
    document.body.removeChild(ta);
    return Promise.resolve();
  };

  doCopy(text).then(() => {
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2-circle"></i>';
    btn.classList.add('!bg-emerald-500', '!text-white', 'scale-110');
    setTimeout(() => {
      btn.innerHTML = old;
      btn.classList.remove('!bg-emerald-500', '!text-white', 'scale-110');
    }, 1500);
  });
});
</script>
@endpush
@endsection
