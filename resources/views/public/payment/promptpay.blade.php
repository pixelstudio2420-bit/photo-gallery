@extends('layouts.app')

@section('title', 'PromptPay — ชำระเงิน')

@section('content-full')
<div class="flex items-center justify-center px-4 py-8 min-h-[70vh]">
  <div class="w-full max-w-md">
    {{-- Countdown sits ABOVE the card so the urgency state is visible
         even before the user scrolls to the QR. --}}
    @include('public.payment._countdown', ['order' => $order])

    <div class="rounded-3xl overflow-hidden bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-2xl">

      {{-- Header --}}
      <div class="text-center py-6 px-5 bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md mb-3">
          <i class="bi bi-qr-code text-white text-2xl"></i>
        </div>
        <h1 class="font-bold text-white text-xl mb-1">PromptPay</h1>
        <p class="text-white/80 text-sm">สแกน QR Code เพื่อชำระเงิน</p>
      </div>

      <div class="p-5 text-center">
        {{-- Amount --}}
        <div class="mb-5">
          <div class="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
            {{ number_format((float)($amount ?? $transaction->amount ?? 0), 2) }} ฿
          </div>
          @if(!empty($order->order_number))
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">คำสั่งซื้อ: <strong class="text-slate-700 dark:text-slate-300">{{ $order->order_number }}</strong></div>
          @endif
          @if(!empty($transaction->transaction_id))
            <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">TX: {{ $transaction->transaction_id }}</div>
          @endif
        </div>

        {{-- QR Code --}}
        <div class="inline-block p-4 mb-4 rounded-2xl bg-white border-2 border-dashed border-emerald-300 dark:border-emerald-500/40 shadow-md">
          @if(!empty($qrUrl))
            <img src="{{ $qrUrl }}" alt="PromptPay QR Code" class="w-56 h-56">
          @elseif(!empty($qrPayload))
            <img src="https://api.qrserver.com/v1/create-qr-code/?{{ http_build_query(['data' => $qrPayload, 'size' => '220x220', 'ecc' => 'M', 'margin' => '10', 'format' => 'png']) }}"
                 alt="PromptPay QR Code" class="w-56 h-56">
          @else
            <div class="w-56 h-56 bg-slate-50 flex items-center justify-center rounded-lg">
              <span class="text-slate-400 text-sm">ไม่พบ QR Code</span>
            </div>
          @endif
        </div>

        @if(!empty($promptPayNumber))
          <div class="text-sm text-slate-500 dark:text-slate-400 mb-1">หมายเลข PromptPay:</div>
          <div class="inline-flex items-center gap-2 font-mono font-bold text-slate-900 dark:text-white mb-5">
            {{ $promptPayNumber }}
            <button onclick="navigator.clipboard.writeText('{{ $promptPayNumber }}'); this.innerHTML='<i class=\'bi bi-check2\'></i>'; setTimeout(()=>{this.innerHTML='<i class=\'bi bi-clipboard\'></i>'}, 1500)"
                    class="text-slate-400 hover:text-indigo-500 transition" title="คัดลอก">
              <i class="bi bi-clipboard text-xs"></i>
            </button>
          </div>
        @endif

        {{-- Steps --}}
        <div class="text-left p-4 mb-5 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20">
          <div class="text-sm font-semibold text-indigo-700 dark:text-indigo-300 mb-2 flex items-center gap-1.5">
            <i class="bi bi-list-ol"></i> วิธีชำระเงิน
          </div>
          <ol class="text-xs text-indigo-900 dark:text-indigo-300/90 pl-4 space-y-1 list-decimal">
            <li>เปิดแอปธนาคารของคุณ</li>
            <li>เลือก "สแกน QR" หรือ "PromptPay"</li>
            <li>สแกน QR Code ด้านบน</li>
            <li>ตรวจสอบยอดและยืนยันการโอน</li>
            <li>อัปโหลดสลิปด้านล่าง</li>
          </ol>
        </div>

        <hr class="border-slate-200 dark:border-white/10 mb-5">

        {{-- Slip Upload --}}
        <h3 class="font-semibold text-slate-900 dark:text-white mb-4 flex items-center justify-center gap-1.5">
          <i class="bi bi-cloud-upload-fill text-emerald-500"></i> อัปโหลดสลิปการโอน
        </h3>

        <form method="POST" action="{{ route('payment.slip.upload') }}" enctype="multipart/form-data" class="text-left space-y-4">
          @csrf
          <input type="hidden" name="transaction_id" value="{{ $transaction->transaction_id ?? '' }}">
          @if(request()->routeIs('payment.*') && isset($order))
            <input type="hidden" name="order_id" value="{{ $order->id }}">
          @endif
          <input type="hidden" name="payment_method" value="promptpay">
          <input type="hidden" name="transfer_amount" value="{{ $amount ?? $order->total ?? 0 }}">
          <input type="hidden" name="transfer_date" value="{{ date('Y-m-d') }}">

          <div>
            <label for="promptpayRefCode" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">หมายเลขอ้างอิง (ถ้ามี)</label>
            <input type="text" id="promptpayRefCode" name="ref_code"
                   placeholder="เช่น เลขที่รายการโอน"
                   class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
          </div>

          <label class="relative block cursor-pointer rounded-xl border-2 border-dashed border-slate-200 dark:border-white/10 hover:border-emerald-400 dark:hover:border-emerald-500 bg-slate-50 dark:bg-slate-900/50 transition p-5 text-center">
            <input type="file" id="slipFileInput" name="slip_image" accept="image/*" required
                   class="absolute inset-0 opacity-0 cursor-pointer"
                   onchange="document.getElementById('slipFileName').textContent = this.files[0]?.name || ''">
            <div class="w-11 h-11 mx-auto rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center mb-2 shadow-md">
              <i class="bi bi-cloud-upload"></i>
            </div>
            <div class="text-xs text-slate-600 dark:text-slate-300">คลิกเพื่อเลือกรูปสลิป (JPG, PNG, ≤5MB)</div>
            <div id="slipFileName" class="text-xs font-medium text-emerald-600 dark:text-emerald-400 mt-1"></div>
          </label>

          <button type="submit"
                  class="w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-semibold shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
            <i class="bi bi-upload"></i> อัปโหลดสลิป
          </button>
        </form>

        <div class="mt-4 text-center">
          <a href="{{ route('orders.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1">
            <i class="bi bi-arrow-left"></i> กลับไปยังคำสั่งซื้อของฉัน
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
