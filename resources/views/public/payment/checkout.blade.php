@extends('layouts.app')

@section('title', 'ชำระเงิน — ' . ($order->order_number ?? '#' . $order->id))

@section('content')
<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  {{-- Pay-now countdown banner. See _countdown.blade.php for the
       Alpine logic + visual states. --}}
  @include('public.payment._countdown', ['order' => $order])

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
        <i class="bi bi-credit-card-fill"></i>
      </span>
      ชำระเงิน
    </h1>
    <a href="{{ route('orders.show', $order->id) }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 text-sm transition">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
  </div>

  @if(session('warning'))
    <div class="mb-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-amber-800 dark:text-amber-300 text-sm flex items-start gap-2">
      <i class="bi bi-exclamation-triangle-fill mt-0.5"></i> {{ session('warning') }}
    </div>
  @endif
  @if(session('error'))
    <div class="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 text-sm flex items-start gap-2">
      <i class="bi bi-x-circle-fill mt-0.5"></i> {{ session('error') }}
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ════════════════════════════════════════════════════
         LEFT — Payment method + inline details
         ════════════════════════════════════════════════════ --}}
    <div class="lg:col-span-2 space-y-5">

      <form method="POST" action="{{ route('payment.process') }}" id="paymentForm">
        @csrf
        <input type="hidden" name="order_id" value="{{ $order->id }}">
        {{-- Holds the Omise.js card token before submit (filled by JS).
             Empty for non-Omise methods. --}}
        <input type="hidden" name="omise_token" id="omiseTokenInput" value="">

        {{-- Payment method selector --}}
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-md">
              <i class="bi bi-wallet2"></i>
            </div>
            <div>
              <h3 class="font-semibold text-slate-900 dark:text-white">เลือกช่องทางชำระเงิน</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">เลือกวิธีที่สะดวกที่สุด</p>
            </div>
          </div>

          <div class="p-5">
            @if($paymentMethods->isEmpty())
              <div class="text-center py-10 px-4">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-rose-100 dark:bg-rose-500/20 text-rose-500 dark:text-rose-400 mb-3">
                  <i class="bi bi-exclamation-circle text-2xl"></i>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">ยังไม่มีช่องทางชำระเงินที่เปิดใช้งาน<br>กรุณาติดต่อผู้ดูแลระบบ</p>
              </div>
            @else
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($paymentMethods as $index => $method)
                @php
                  $type  = $method->method_type;
                  $icon  = $methodIcons[$type] ?? 'bi-cash-coin';
                  $first = $index === 0;
                @endphp
                <label for="method_{{ $type }}"
                       class="payment-method-card group cursor-pointer flex items-center gap-3 p-4 rounded-2xl border-2 transition
                          {{ $first ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10' : 'border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900/30 hover:border-indigo-300 dark:hover:border-indigo-500/30' }}"
                       data-type="{{ $type }}">
                  <input type="radio"
                         name="payment_method"
                         value="{{ $type }}"
                         id="method_{{ $type }}"
                         {{ $first ? 'checked' : '' }}
                         onchange="selectPaymentMethod(this)"
                         class="sr-only">
                  <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-sm flex-shrink-0">
                    <i class="bi {{ $icon }} text-xl"></i>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="font-semibold text-slate-900 dark:text-white text-sm truncate">{{ $method->method_name }}</div>
                    @if($method->description)
                      <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $method->description }}</div>
                    @endif
                  </div>
                  <div class="payment-check flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center border-2
                          {{ $first ? 'border-indigo-500 bg-indigo-500' : 'border-slate-300 dark:border-white/20 bg-transparent' }}">
                    @if($first)<i class="bi bi-check text-white text-xs"></i>@endif
                  </div>
                </label>
                @endforeach
              </div>

              {{-- Omise inline card form — shown only when "omise" is the
                   selected method AND a public key is configured. Captured
                   token is placed into #omiseTokenInput on form submit by
                   the JS handler at the bottom of this page.

                   For subscription orders, an opt-in checkbox below the
                   form lets the buyer decide whether to save the card
                   for auto-renewal (default ON) or pay one-time and let
                   the subscription expire at period_end (must re-subscribe
                   manually next period). Without the checkbox, every
                   Omise subscription payment would silently turn into a
                   recurring charge — which conflicts with users who
                   want simple month-by-month purchases. --}}
              @if(!empty($omisePublicKey))
                <div id="section-omise" style="display:none;" class="mt-5 p-5 rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/30">
                  <div class="flex items-center gap-2 mb-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    <i class="bi bi-credit-card text-indigo-500"></i>
                    ข้อมูลบัตรเครดิต/เดบิต
                  </div>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                      <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">ชื่อบนบัตร</label>
                      <input type="text" id="omiseCardName" autocomplete="cc-name"
                             class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                             placeholder="JOHN DOE">
                    </div>
                    <div class="md:col-span-2">
                      <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">หมายเลขบัตร</label>
                      <input type="text" id="omiseCardNumber" autocomplete="cc-number" inputmode="numeric"
                             class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono tracking-wider focus:outline-none focus:ring-2 focus:ring-indigo-500"
                             placeholder="1234 5678 9012 3456" maxlength="23">
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">วันหมดอายุ (MM/YY)</label>
                      <div class="flex gap-2">
                        <input type="text" id="omiseCardExpMonth" autocomplete="cc-exp-month" inputmode="numeric"
                               class="w-1/2 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                               placeholder="MM" maxlength="2">
                        <input type="text" id="omiseCardExpYear" autocomplete="cc-exp-year" inputmode="numeric"
                               class="w-1/2 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                               placeholder="YY" maxlength="2">
                      </div>
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">CVV</label>
                      <input type="text" id="omiseCardCvc" autocomplete="cc-csc" inputmode="numeric"
                             class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                             placeholder="123" maxlength="4">
                    </div>
                  </div>
                  <div id="omiseError" class="hidden mt-3 p-3 rounded-lg bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-700 dark:text-rose-300 text-xs"></div>

                  @if($isSubscriptionOrder)
                    {{-- Save-card opt-in. Default: ON — most users prefer
                         "set and forget" auto-renew. Unchecking is a
                         deliberate "month-by-month, no auto-charge" choice.
                         Backend reads `save_card=1` from the form; missing
                         or `0` skips Omise customer creation and the
                         renewal cron skips the sub for lack of an
                         omise_customer_id, so the period-end safety net
                         (subscriptions:expire-overdue) takes over. --}}
                    <label class="mt-4 flex items-start gap-3 p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 cursor-pointer hover:border-indigo-300 transition">
                      <input type="checkbox" name="save_card" id="saveCardCheckbox" value="1" checked
                             class="mt-0.5 w-4 h-4 rounded text-indigo-600 focus:ring-2 focus:ring-indigo-500 cursor-pointer">
                      <div class="flex-1 text-xs">
                        <div class="font-semibold text-slate-900 dark:text-white text-sm mb-0.5 flex items-center gap-1.5">
                          <i class="bi bi-arrow-repeat text-indigo-500"></i>
                          บันทึกบัตรเพื่อต่ออายุอัตโนมัติ
                        </div>
                        <div class="text-slate-500 dark:text-slate-400 leading-relaxed">
                          ระบบจะเก็บข้อมูลบัตรไว้ที่ Omise (ไม่ใช่ที่เซิร์ฟเวอร์เรา) และตัดยอดเองในเดือนถัดไป
                          <span class="block mt-1 text-amber-700 dark:text-amber-400">
                            <i class="bi bi-info-circle"></i>
                            ไม่ติ๊ก = จ่ายเดือนเดียว ไม่ผูกบัตร ครบเดือนถ้าจะใช้ต่อต้องสมัครใหม่เอง
                          </span>
                        </div>
                      </div>
                    </label>
                  @endif

                  <div class="mt-3 flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                    <i class="bi bi-shield-lock-fill text-emerald-500"></i>
                    ข้อมูลบัตรของคุณถูกส่งตรงไปที่ Omise — ไม่ผ่านเซิร์ฟเวอร์เรา
                  </div>
                </div>
              @endif

              {{-- Submit --}}
              <button type="submit" id="proceedPaymentBtn"
                      class="w-full py-3.5 mt-5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
                <i class="bi bi-lock-fill"></i> <span id="proceedPaymentBtnLabel">ชำระเงิน
                {{ number_format((float)($order->net_amount ?? $order->total_amount ?? $order->total ?? 0), 0) }} ฿</span>
              </button>
            @endif
          </div>
        </div>
      </form>

      {{-- PromptPay preview — branded savable card.
           Shown immediately when PromptPay is the default-selected method so
           the customer sees the QR without a second click. `promptpay-default`
           flag is read by toggleSections() on page load.

           The QR <img> stays PRISTINE (no logo overlay) — PromptPay EMVCo
           spec requires unmodified QR or banking apps may fail to scan.
           Branding is purely decorative AROUND the QR (header band +
           corner brackets + footer ribbon). --}}
      @if(!empty($promptPayNumber))
      @php
        $ppGateway = new \App\Services\Payment\PromptPayGateway();
        $orderAmt  = (float)($order->net_amount ?? $order->total_amount ?? $order->total ?? 0);
        $ppPayload = $ppGateway->generateEMVPayload($promptPayNumber, $orderAmt);
        $ppQrUrl   = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
          'data' => $ppPayload, 'size' => '220x220', 'ecc' => 'M', 'margin' => '10', 'format' => 'png'
        ]);
        $firstMethodType = $paymentMethods->first()->method_type ?? null;
        $ppIsDefault     = $firstMethodType === 'promptpay';

        // Brand info for the savable card.
        $_ppSiteName  = $siteName ?? \App\Models\AppSetting::get('site_name', '') ?: config('app.name', 'Loadroop');
        $_ppBrandHost = preg_replace('/^www\./i', '', parse_url(config('app.url', 'https://loadroop.com'), PHP_URL_HOST) ?: 'loadroop.com');
      @endphp
      <div id="section-promptpay" @if(!$ppIsDefault) style="display:none;" @endif>
        {{-- THE SAVABLE CARD — wrapped in #promptpay-card so html2canvas
             rasterises only this region. Action buttons sit below the
             card on purpose so they don't appear in the saved image. --}}
        <div id="promptpay-card" class="rounded-2xl overflow-hidden bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-lg">
          {{-- Brand header — gradient indigo→purple→pink with site name --}}
          <div class="relative px-5 py-5 text-center bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 overflow-hidden">
            <div class="absolute inset-0 opacity-30 pointer-events-none"
                 style="background: radial-gradient(circle at 20% 20%, rgba(255,255,255,.18), transparent 40%), radial-gradient(circle at 80% 80%, rgba(255,255,255,.10), transparent 50%);"></div>
            <div class="relative">
              <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-white/20 backdrop-blur-md mb-2 border border-white/30">
                <i class="bi bi-qr-code text-white text-xl"></i>
              </div>
              <h3 class="font-bold text-white text-lg mb-0.5">PromptPay QR Code</h3>
              <div class="text-[10px] tracking-[0.22em] uppercase font-bold text-white/85">
                {{ $_ppSiteName }}
              </div>
            </div>
          </div>

          <div class="p-6 text-center">
            {{-- QR with indigo+pink corner brackets — purely decorative,
                 sits OUTSIDE the QR's quiet zone so scanning isn't affected. --}}
            <div class="relative inline-block p-3 rounded-2xl bg-white shadow-md">
              <span class="absolute w-5 h-5" style="top:-2px;left:-2px;border-top:3px solid #7c3aed;border-left:3px solid #7c3aed;border-top-left-radius:14px;"></span>
              <span class="absolute w-5 h-5" style="top:-2px;right:-2px;border-top:3px solid #7c3aed;border-right:3px solid #7c3aed;border-top-right-radius:14px;"></span>
              <span class="absolute w-5 h-5" style="bottom:-2px;left:-2px;border-bottom:3px solid #ec4899;border-left:3px solid #ec4899;border-bottom-left-radius:14px;"></span>
              <span class="absolute w-5 h-5" style="bottom:-2px;right:-2px;border-bottom:3px solid #ec4899;border-right:3px solid #ec4899;border-bottom-right-radius:14px;"></span>
              <img id="promptpay-qr-img" src="{{ $ppQrUrl }}" alt="PromptPay QR" class="w-52 h-52" crossorigin="anonymous">
            </div>

            <div class="mt-4 text-2xl font-bold bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 bg-clip-text text-transparent">
              ฿{{ number_format($orderAmt, 2) }}
            </div>

            {{-- Recipient label — replaces the raw "PromptPay: 081xxx" line.
                 Shows the brand as recipient (matches banking-app UX where
                 scanning the QR reveals the recipient name for confirmation,
                 not the underlying phone number). Hides the phone publicly,
                 still gives customer a trust signal that they're paying to
                 a real, verified account. The "?" link at the end reveals
                 the number for users who need to enter it manually as a
                 fallback when QR scan fails (cached on click — no extra
                 round-trip). --}}
            <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium
                        bg-emerald-50 text-emerald-700 border border-emerald-100
                        dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
              <i class="bi bi-shield-check"></i>
              <span>ผู้รับ:</span>
              <strong>{{ $_ppSiteName }}</strong>
            </div>

            <div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400 flex items-center justify-center gap-2 flex-wrap">
              @if(!empty($order->order_number))
                <span>#{{ $order->order_number }}</span>
                <span aria-hidden="true">·</span>
              @endif
              <button type="button" id="ppShowNumberBtn"
                      onclick="(function(b){const v=document.getElementById('ppNumberFallback');if(v.classList.contains('hidden')){v.classList.remove('hidden');b.style.display='none';}})(this)"
                      class="text-indigo-600 dark:text-indigo-400 hover:underline">
                ใช้เลขแทน QR?
              </button>
            </div>

            {{-- Hidden by default — revealed on "ใช้เลขแทน QR?" click.
                 No extra request: the number is rendered but display:none
                 until needed. Provides the fallback path for the rare case
                 where a customer's bank app can't read the QR (broken
                 camera, blurry screen, etc.). --}}
            <div id="ppNumberFallback" class="hidden mt-2">
              <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700/50 text-sm">
                <span class="text-slate-500 dark:text-slate-400">PromptPay:</span>
                <strong class="text-slate-900 dark:text-white font-mono">{{ $promptPayNumber }}</strong>
                <button type="button"
                        onclick="navigator.clipboard.writeText('{{ $promptPayNumber }}'); this.innerHTML='<i class=\'bi bi-check2 text-emerald-500\'></i>'; setTimeout(()=>{this.innerHTML='<i class=\'bi bi-clipboard text-xs\'></i>'},1500)"
                        class="text-slate-400 hover:text-indigo-500 transition" title="คัดลอกเบอร์ PromptPay">
                  <i class="bi bi-clipboard text-xs"></i>
                </button>
              </div>
            </div>
          </div>

          {{-- Footer ribbon — brand domain --}}
          <div class="px-5 py-2.5 bg-gradient-to-br from-slate-900 to-indigo-950 text-white text-sm font-semibold flex items-center justify-center gap-2">
            <i class="bi bi-globe2 text-violet-300"></i>
            <span>{{ $_ppBrandHost }}</span>
          </div>
        </div>
        {{-- /promptpay-card --}}

        {{-- Save / share row — sits OUTSIDE the card so it's not in the
             saved image. Web Share API on iOS opens the native share
             sheet (Save to Photos / AirDrop / LINE), download fallback
             on Android + desktop. --}}
        <div class="mt-3 flex flex-col items-center gap-2">
          <button type="button" id="savePpQrBtn" onclick="savePromptPayQR(this)"
                  class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold shadow-md hover:shadow-lg transition-all">
            <i class="bi bi-download"></i>
            <span>บันทึกรูป QR Code</span>
          </button>
          <p id="iosLongPressHint" class="hidden text-[11px] text-slate-500 dark:text-slate-400 text-center max-w-xs">
            <i class="bi bi-info-circle"></i>
            iPhone: ถ้าหน้าต่างแชร์ไม่ขึ้น ให้ค้างนิ้วบน QR Code แล้วเลือก "Save Image"
          </p>
        </div>

        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400 text-center">
          <i class="bi bi-info-circle"></i> QR นี้ใช้ดูตัวอย่าง · ระบบจะสร้างใหม่ให้ออเดอร์นี้หลังกด "ชำระเงิน"
        </p>
      </div>
      @endif

      {{-- Bank transfer details --}}
      @if(isset($bankAccounts) && $bankAccounts->isNotEmpty())
      <div id="section-bank_transfer" class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden" style="display:none;">
        <div class="px-5 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-500/5 dark:to-indigo-500/5 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center shadow-md">
            <i class="bi bi-bank2"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">บัญชีธนาคาร</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">คัดลอกเลขบัญชีและโอนผ่านแอปธนาคาร</p>
          </div>
        </div>
        <div class="p-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($bankAccounts as $bank)
            <div class="relative overflow-hidden rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/30 group hover:border-indigo-300 dark:hover:border-indigo-500/30 transition">
              <div class="h-1 bg-gradient-to-r from-indigo-500 to-purple-600"></div>
              <div class="p-4 flex gap-3">
                <div class="w-11 h-11 rounded-xl bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center flex-shrink-0">
                  <i class="bi bi-bank text-indigo-600 dark:text-indigo-400 text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-semibold text-sm text-indigo-600 dark:text-indigo-400 truncate">{{ $bank->bank_name }}</div>
                  @if(!empty($bank->branch))
                    <div class="text-xs text-slate-500 dark:text-slate-400">สาขา {{ $bank->branch }}</div>
                  @endif
                  <div class="mt-1 font-mono font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    {{ $bank->account_number }}
                    <button type="button" onclick="navigator.clipboard.writeText('{{ $bank->account_number }}'); this.innerHTML='<i class=\'bi bi-check2 text-emerald-500\'></i>'"
                            class="text-slate-400 hover:text-indigo-500 transition" title="คัดลอก">
                      <i class="bi bi-clipboard text-xs"></i>
                    </button>
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $bank->account_holder_name ?? '' }}</div>
                </div>
              </div>
            </div>
            @endforeach
          </div>
          <div class="mt-4 p-3 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 text-xs text-indigo-900 dark:text-indigo-300">
            <i class="bi bi-info-circle mr-1"></i> กรุณากด "ชำระเงิน" แล้วอัปโหลดสลิปในหน้าถัดไป เพื่อให้เจ้าหน้าที่ตรวจสอบ
          </div>
        </div>
      </div>
      @endif

      {{-- Slip upload section --}}
      @if(View::exists('public.payment.partials.slip-upload'))
        @include('public.payment.partials.slip-upload', ['order' => $order])
      @endif
    </div>

    {{-- ════════════════════════════════════════════════════
         RIGHT — Order summary
         ════════════════════════════════════════════════════ --}}
    <div class="lg:col-span-1">
      <div class="lg:sticky lg:top-24 space-y-4">

        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-lg overflow-hidden">
          <div class="h-1.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
          <div class="p-5">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5 mb-4">
              <i class="bi bi-receipt text-indigo-500"></i> สรุปคำสั่งซื้อ
            </h3>

            <dl class="text-sm space-y-2.5 mb-4 pb-4 border-b border-slate-100 dark:border-white/5">
              <div class="flex justify-between">
                <dt class="text-slate-500 dark:text-slate-400">เลขคำสั่งซื้อ</dt>
                <dd class="font-mono font-medium text-slate-900 dark:text-white">{{ $order->order_number ?? '#' . $order->id }}</dd>
              </div>
              @if(!empty($order->event))
              <div class="flex justify-between gap-2">
                <dt class="text-slate-500 dark:text-slate-400 flex-shrink-0">อีเวนต์</dt>
                <dd class="font-medium text-slate-900 dark:text-white text-right truncate">{{ $order->event->name ?? '-' }}</dd>
              </div>
              @endif
              @if(!empty($order->package))
              <div class="flex justify-between gap-2">
                <dt class="text-slate-500 dark:text-slate-400 flex-shrink-0">แพ็กเกจ</dt>
                <dd class="font-medium text-indigo-600 dark:text-indigo-400 text-right">
                  <i class="bi bi-box-seam mr-0.5"></i>{{ $order->package->name }} ({{ $order->package->photo_count }})
                </dd>
              </div>
              @endif
              <div class="flex justify-between">
                <dt class="text-slate-500 dark:text-slate-400">จำนวนรูป</dt>
                <dd class="font-medium text-slate-900 dark:text-white">{{ $order->items->count() }} รูป</dd>
              </div>

              @if(isset($order->total_amount) && isset($order->net_amount) && $order->total_amount != $order->net_amount)
                <div class="flex justify-between">
                  <dt class="text-slate-500 dark:text-slate-400">ราคาเต็ม</dt>
                  <dd class="text-slate-500 dark:text-slate-400 line-through">{{ number_format((float)$order->total_amount, 0) }} ฿</dd>
                </div>
              @endif
              @if(!empty($order->discount_amount) && (float)$order->discount_amount > 0)
                <div class="flex justify-between text-rose-600 dark:text-rose-400">
                  <dt class="inline-flex items-center gap-1"><i class="bi bi-tag-fill"></i> ส่วนลด</dt>
                  <dd class="font-semibold">-{{ number_format((float)$order->discount_amount, 0) }} ฿</dd>
                </div>
              @endif
            </dl>

            <div class="flex items-baseline justify-between">
              <span class="font-bold text-slate-900 dark:text-white">ยอดชำระ</span>
              <span class="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                {{ number_format((float)($order->net_amount ?? $order->total_amount ?? $order->total ?? 0), 0) }} <span class="text-base">฿</span>
              </span>
            </div>
          </div>
        </div>

        {{-- Photo thumbnails --}}
        @if($order->items->isNotEmpty())
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-4">
          <div class="text-xs uppercase tracking-wide font-semibold text-slate-500 dark:text-slate-400 mb-2">
            <i class="bi bi-images mr-1"></i> รูปภาพที่เลือก
          </div>
          <div class="flex flex-wrap gap-1.5">
            @foreach($order->items->take(8) as $item)
              @if(!empty($item->thumbnail_url))
                <img src="{{ $item->thumbnail_url }}" alt="photo"
                     class="w-12 h-12 object-cover rounded-lg border border-slate-200 dark:border-white/10">
              @else
                <div class="w-12 h-12 rounded-lg bg-slate-100 dark:bg-white/5 flex items-center justify-center">
                  <i class="bi bi-image text-slate-400"></i>
                </div>
              @endif
            @endforeach
            @if($order->items->count() > 8)
              <div class="w-12 h-12 rounded-lg bg-slate-100 dark:bg-white/5 flex items-center justify-center">
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">+{{ $order->items->count() - 8 }}</span>
              </div>
            @endif
          </div>
        </div>
        @endif

        {{-- Security --}}
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 flex gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center flex-shrink-0 shadow-sm">
            <i class="bi bi-shield-fill-check"></i>
          </div>
          <div class="text-xs">
            <div class="font-semibold text-emerald-900 dark:text-emerald-200">การชำระเงินปลอดภัย</div>
            <p class="text-emerald-800 dark:text-emerald-300/90 mt-0.5">ข้อมูลของคุณถูกเข้ารหัสและได้รับการคุ้มครอง</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Payment methods requiring slip upload
const slipMethods = ['promptpay', 'bank_transfer', 'banktransfer', 'prompt_pay'];
const inlineSections = ['promptpay', 'bank_transfer', 'omise'];

function selectPaymentMethod(radio) {
  document.querySelectorAll('.payment-method-card').forEach(function(card) {
    card.classList.remove('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-500/10');
    card.classList.add('border-slate-200', 'dark:border-white/10', 'bg-white', 'dark:bg-slate-900/30');
    const chk = card.querySelector('.payment-check');
    if (chk) {
      chk.classList.remove('border-indigo-500', 'bg-indigo-500');
      chk.classList.add('border-slate-300', 'dark:border-white/20', 'bg-transparent');
      chk.innerHTML = '';
    }
  });
  const card = radio.closest('.payment-method-card');
  card.classList.remove('border-slate-200', 'dark:border-white/10', 'bg-white', 'dark:bg-slate-900/30');
  card.classList.add('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-500/10');
  const chk = card.querySelector('.payment-check');
  if (chk) {
    chk.classList.remove('border-slate-300', 'dark:border-white/20', 'bg-transparent');
    chk.classList.add('border-indigo-500', 'bg-indigo-500');
    chk.innerHTML = '<i class="bi bi-check text-white text-xs"></i>';
  }
  toggleSections(radio.value);
}

function toggleSections(type) {
  inlineSections.forEach(function(t) {
    const el = document.getElementById('section-' + t);
    if (el) el.style.display = (type === t) ? '' : 'none';
  });
  const slipSection = document.getElementById('slipUploadSection');
  const proceedBtn  = document.getElementById('proceedPaymentBtn');
  const needsSlip   = slipMethods.indexOf(type.toLowerCase()) !== -1;
  if (slipSection) slipSection.style.display = needsSlip ? '' : 'none';
  if (proceedBtn) proceedBtn.style.display = needsSlip ? 'none' : '';
}

document.addEventListener('DOMContentLoaded', function() {
  const checked = document.querySelector('input[name="payment_method"]:checked');
  if (checked) toggleSections(checked.value);
});
</script>

@if(!empty($omisePublicKey))
{{-- ── Omise.js card tokenisation ─────────────────────────────────────
     Loaded only when the Omise gateway is enabled AND a public key is
     configured. Captures the card client-side, posts the resulting
     `tokn_xxx` to our backend in a hidden form input. The card details
     never touch our server — the backend only sees the opaque token.

     For subscription orders, the backend uses the token to ALSO create
     an Omise Customer object so future renewal cycles can charge
     without prompting the user again. ──────────────────────────────── --}}
<script src="https://cdn.omise.co/omise.js"></script>
<script>
(function () {
  if (typeof Omise === 'undefined') return;
  Omise.setPublicKey(@json($omisePublicKey));

  // Light formatting on the card-number input — groups of 4 digits.
  const $num = document.getElementById('omiseCardNumber');
  if ($num) {
    $num.addEventListener('input', function (e) {
      const v = e.target.value.replace(/\D/g, '').slice(0, 19);
      e.target.value = v.replace(/(.{4})/g, '$1 ').trim();
    });
  }

  // Strip non-digits in MM / YY / CVV so users can paste freely.
  ['omiseCardExpMonth', 'omiseCardExpYear', 'omiseCardCvc'].forEach(function (id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', function (e) {
      e.target.value = e.target.value.replace(/\D/g, '');
    });
  });

  function showError(msg) {
    const box = document.getElementById('omiseError');
    if (box) {
      box.textContent = msg;
      box.classList.remove('hidden');
    }
  }
  function clearError() {
    const box = document.getElementById('omiseError');
    if (box) {
      box.textContent = '';
      box.classList.add('hidden');
    }
  }

  // Intercept the form submit when Omise is the selected method.
  // Steps:
  //   1. Validate inputs are filled in.
  //   2. Call Omise.createToken (sends card details DIRECTLY to Omise,
  //      bypassing our server).
  //   3. Stuff the resulting tokn_xxx into #omiseTokenInput.
  //   4. Allow the form to actually submit (re-trigger requestSubmit).
  // If anything fails, we surface the error inline and prevent submit.
  const form    = document.getElementById('paymentForm');
  const tokenIn = document.getElementById('omiseTokenInput');
  if (!form || !tokenIn) return;

  let submittingViaOmise = false;
  form.addEventListener('submit', function (e) {
    const method = (document.querySelector('input[name="payment_method"]:checked') || {}).value;
    if (method !== 'omise') return;
    if (submittingViaOmise) return;       // already tokenised, allow native submit
    if (tokenIn.value)        return;     // already have a token (back-button reload)

    e.preventDefault();
    clearError();

    const number = (document.getElementById('omiseCardNumber').value || '').replace(/\s+/g, '');
    const month  = (document.getElementById('omiseCardExpMonth').value || '').trim();
    const year   = (document.getElementById('omiseCardExpYear').value || '').trim();
    const cvc    = (document.getElementById('omiseCardCvc').value || '').trim();
    const name   = (document.getElementById('omiseCardName').value || '').trim();

    if (!number || number.length < 13)  return showError('กรุณากรอกหมายเลขบัตรให้ถูกต้อง');
    if (!month || +month < 1 || +month > 12) return showError('เดือนหมดอายุไม่ถูกต้อง');
    if (!year || year.length !== 2)     return showError('กรุณากรอกปีหมดอายุ (YY)');
    if (!cvc || cvc.length < 3)         return showError('CVV ไม่ถูกต้อง');
    if (!name)                          return showError('กรุณากรอกชื่อบนบัตร');

    const expirationYear = parseInt(year, 10) + 2000;

    const btn = document.getElementById('proceedPaymentBtn');
    const lbl = document.getElementById('proceedPaymentBtnLabel');
    if (btn) btn.disabled = true;
    if (lbl) lbl.textContent = 'กำลังตรวจสอบบัตร…';

    Omise.createToken('card', {
      name:             name,
      number:           number,
      expiration_month: month,
      expiration_year:  String(expirationYear),
      security_code:    cvc,
    }, function (statusCode, response) {
      if (btn) btn.disabled = false;

      if (statusCode === 200 && response && response.id) {
        tokenIn.value = response.id;
        submittingViaOmise = true;

        // Re-submit the form. requestSubmit fires the submit event again,
        // but submittingViaOmise=true makes us skip the intercept.
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
      } else {
        const msg = (response && (response.message || response.failure_message)) || 'ไม่สามารถยืนยันข้อมูลบัตรได้';
        showError('Omise: ' + msg);
        if (lbl) lbl.textContent = 'ลองใหม่อีกครั้ง';
      }
    });
  });
})();
</script>
@endif

{{-- ── Branded QR card "Save" — html2canvas + Web Share API ──────────
     Loads only on this page (not via app.js). Capture region is
     #promptpay-card; action buttons are deliberately outside it so
     they don't appear in the saved image. ────────────────────────── --}}
{{-- html2canvas-pro: fork supporting oklch/lab/lch (Tailwind v4 colors). --}}
<script src="https://cdn.jsdelivr.net/npm/html2canvas-pro@1.5.8/dist/html2canvas-pro.min.js" defer></script>
<script>
(function () {
  // Show the iPhone "long-press" hint only on iOS — Android/desktop
  // users get the direct download path and don't need this hint.
  // Newer iPads report as MacIntel, so test for ontouchend as well.
  const isIOS = /iPad|iPhone|iPod/.test(navigator.platform || '')
             || (navigator.userAgent.includes('Mac') && 'ontouchend' in document);
  if (isIOS) {
    const hint = document.getElementById('iosLongPressHint');
    if (hint) hint.classList.remove('hidden');
  }

  // Wait for the QR <img> to actually finish loading before capture —
  // html2canvas would otherwise rasterise an empty <img> if user clicks
  // Save before the network round-trip finishes.
  const qrReady = new Promise((resolve) => {
    const img = document.getElementById('promptpay-qr-img');
    if (!img) return resolve();
    if (img.complete && img.naturalWidth > 0) return resolve();
    img.addEventListener('load',  resolve, { once: true });
    img.addEventListener('error', resolve, { once: true });
  });

  window.savePromptPayQR = async function (btn) {
    if (typeof html2canvas !== 'function') {
      alert('กำลังโหลดเครื่องมือบันทึก กรุณารอสักครู่');
      return;
    }
    const card = document.getElementById('promptpay-card');
    if (!card) return;

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat" style="display:inline-block;animation:spin 1s linear infinite"></i> <span>กำลังสร้างภาพ…</span>';

    try {
      await qrReady;
      // 2× scale = retina-quality without bloating the file. useCORS
      // lets html2canvas read the api.qrserver.com QR (which sets
      // Access-Control-Allow-Origin: *).
      const canvas = await html2canvas(card, {
        scale: 2,
        backgroundColor: null,
        useCORS: true,
        logging: false,
      });

      // toBlob is more memory-efficient than toDataURL for retina-sized
      // canvases — older iPhones can OOM on the bigger 2× output.
      const blob = await new Promise((res) => canvas.toBlob(res, 'image/png', 0.95));
      if (!blob) throw new Error('toBlob returned null');

      const fileName = 'promptpay-qr-' + Date.now() + '.png';
      const file = new File([blob], fileName, { type: 'image/png' });

      // Strategy 1 — Web Share API (the iOS-friendly path).
      // iOS Safari 15+ supports navigator.share with files. This is
      // the ONLY reliable way to save to camera roll on iOS — Safari
      // ignores anchor[download] and opens the image in a new tab.
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        try {
          await navigator.share({
            files: [file],
            title: 'PromptPay QR Code',
            text: 'QR Code สำหรับชำระเงิน',
          });
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-check2"></i> <span>บันทึกแล้ว</span>';
          setTimeout(() => { btn.innerHTML = original; }, 2200);
          return;
        } catch (err) {
          // AbortError = user cancelled the share sheet — silent restore.
          // Anything else falls through to download fallback.
          if (err.name === 'AbortError') {
            btn.disabled = false;
            btn.innerHTML = original;
            return;
          }
        }
      }

      // Strategy 2 — Download via blob URL (Android Chrome + desktop).
      // On iOS this opens the image in a new tab, but the long-press
      // hint we showed earlier covers that fallback.
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = fileName;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(() => URL.revokeObjectURL(url), 500);

      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check2"></i> <span>บันทึกแล้ว</span>';
      setTimeout(() => { btn.innerHTML = original; }, 2200);
    } catch (err) {
      console.error('savePromptPayQR failed', err);
      btn.disabled = false;
      btn.innerHTML = original;
      alert('บันทึกไม่สำเร็จ — iPhone: ลองค้างนิ้วบน QR Code แล้วเลือก "Save Image"');
    }
  };
})();
</script>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endsection
