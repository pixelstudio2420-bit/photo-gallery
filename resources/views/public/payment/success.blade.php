@extends('layouts.app')

@section('title', 'Payment Success')

@section('content-full')
{{--
  Success landing page — the return URL after the customer pays on an
  external gateway (Omise, Stripe, LINE Pay, PayPal). At this exact moment
  we typically do NOT yet know whether the payment succeeded — the gateway's
  webhook is what flips `orders.status` to `paid` and creates DownloadToken
  rows. So:
    • If $order exists and status != paid → show a "verifying" spinner and
      poll /payment/check-status/{order} until status flips. When it does,
      swap the UI to the success+download state without a full reload.
    • If $order is paid → show the download CTA immediately.
    • If no $order (legacy success flows, manual payments) → show the
      original success message.
--}}
<div class="flex items-center justify-center px-4 py-12 min-h-[70vh]">
  <div class="text-center max-w-md mx-auto w-full" id="successRoot">

    @php
      // Is this a gateway-driven order (Omise/Stripe/etc.) that might still
      // be waiting on its webhook? We only show the spinner/polling when we
      // have an order + it's not already paid.
      $showSpinner = $order && $order->status !== 'paid';
      $alreadyPaid = $order && $order->status === 'paid';
    @endphp

    {{-- ────────── Waiting-for-webhook state ────────── --}}
    <div id="paneWaiting" class="{{ $showSpinner ? '' : 'hidden' }}">
      <div class="relative inline-flex items-center justify-center w-28 h-28 rounded-full bg-indigo-100 dark:bg-indigo-500/20 mb-5 waiting-pulse">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg">
          <i class="bi bi-arrow-repeat text-4xl animate-spin" style="animation-duration: 2s"></i>
        </div>
      </div>
      <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white mb-2 tracking-tight">
        กำลังยืนยันการชำระเงิน...
      </h1>
      <p class="text-slate-500 dark:text-slate-400 mb-6 leading-relaxed">
        ระบบกำลังตรวจสอบสถานะจากผู้ให้บริการ — โดยทั่วไปใช้เวลาไม่เกิน 30 วินาที
        <br class="hidden sm:block">
        หน้านี้จะอัปเดตอัตโนมัติเมื่อยืนยันเสร็จ
      </p>

      @if($order)
        <div class="inline-flex items-center gap-4 px-5 py-3 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 text-left shadow-sm mb-6">
          <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 flex items-center justify-center">
            <i class="bi bi-receipt text-lg"></i>
          </div>
          <div>
            <div class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">คำสั่งซื้อ</div>
            <div class="font-mono text-sm font-semibold text-slate-900 dark:text-white">#{{ $order->order_number ?? $order->id }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              ยอด <span class="font-semibold text-slate-900 dark:text-white">{{ number_format((float)$order->total, 0) }} ฿</span>
            </div>
          </div>
        </div>
      @endif

      <div class="flex items-center justify-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
        <span id="pollStatusLabel">กำลังรอการยืนยัน...</span>
      </div>

      <div class="mt-8">
        <a href="{{ $order ? route('payment.status', $order->id) : route('orders.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
          <i class="bi bi-arrow-right"></i> ดูหน้ารายละเอียดคำสั่งซื้อ
        </a>
      </div>
    </div>

    {{-- ────────── Paid state (either on arrival or after polling flip) ────────── --}}
    <div id="paneSuccess" class="{{ $alreadyPaid || !$order ? '' : 'hidden' }}">
      <div class="relative inline-flex items-center justify-center w-28 h-28 rounded-full bg-emerald-100 dark:bg-emerald-500/20 mb-5 success-pulse">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-br from-emerald-500 to-teal-500 text-white shadow-lg">
          <i class="bi bi-check-lg text-4xl"></i>
        </div>
      </div>

      <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white mb-2 tracking-tight">ชำระเงินสำเร็จ!</h1>
      <p class="text-slate-500 dark:text-slate-400 mb-6 leading-relaxed">
        {{ $message ?? 'การชำระเงินของคุณได้รับการดำเนินการเรียบร้อยแล้ว ขอบคุณค่ะ' }}
      </p>

      <div class="flex flex-wrap gap-3 justify-center">
        <a id="downloadLink" href="#"
           class="hidden inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-semibold shadow-md hover:shadow-lg transition-all">
          <i class="bi bi-download"></i> <span>ดาวน์โหลดรูปภาพทั้งหมด</span>
        </a>
        <a href="{{ $order ? route('payment.status', $order->id) : route('orders.index') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-md hover:shadow-lg transition-all">
          <i class="bi bi-receipt"></i> ดูคำสั่งซื้อ
        </a>
        <a href="{{ route('events.index') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 dark:hover:bg-indigo-500/30 font-semibold transition">
          <i class="bi bi-images"></i> เลือกซื้อเพิ่ม
        </a>
      </div>
    </div>

  </div>
</div>

<style>
@keyframes success-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.3); }
  50%      { box-shadow: 0 0 0 24px rgba(16, 185, 129, 0); }
}
.success-pulse { animation: success-pulse 2s infinite; }
@keyframes waiting-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.3); }
  50%      { box-shadow: 0 0 0 20px rgba(99, 102, 241, 0); }
}
.waiting-pulse { animation: waiting-pulse 2s infinite; }
</style>

@if($order && $order->status !== 'paid')
<script>
// Poll the check-status endpoint until webhook marks the order paid, then
// flip the UI to the success pane in-place (no reload — smoother UX and
// keeps the analytics/conversion tracking on this URL).
//
// Gives up after ~3 minutes of polling — at that point the webhook almost
// certainly had an issue and we'd rather let the customer navigate to
// /payment/status/{order} (where the slip-upload fallback lives) than
// keep them staring at a spinner forever.
(function () {
  const checkUrl   = '{{ route('payment.check-status', $order->id) }}';
  const statusUrl  = '{{ route('payment.status', $order->id) }}';
  const paneWait   = document.getElementById('paneWaiting');
  const paneDone   = document.getElementById('paneSuccess');
  const dlLink     = document.getElementById('downloadLink');
  const statusLbl  = document.getElementById('pollStatusLabel');

  const maxAttempts = 36;      // 36 * 5s = 3 minutes
  let attempts = 0;
  let timer = null;

  function finishSuccess(data) {
    if (paneWait) paneWait.classList.add('hidden');
    if (paneDone) paneDone.classList.remove('hidden');
    if (dlLink && data.download_url) {
      dlLink.href = data.download_url;
      dlLink.classList.remove('hidden');
    }
  }

  function finishCancelled() {
    // Cancelled/failed → redirect to status page which has the retry UI.
    window.location.href = statusUrl;
  }

  function giveUp() {
    if (statusLbl) {
      statusLbl.innerHTML = 'ใช้เวลานานกว่าปกติ — <a href="' + statusUrl + '" class="underline text-indigo-600 dark:text-indigo-400">ตรวจสอบสถานะล่าสุด</a>';
    }
  }

  function poll() {
    attempts++;
    fetch(checkUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'paid') {
          clearInterval(timer);
          finishSuccess(data);
        } else if (data.status === 'cancelled' || data.status === 'failed') {
          clearInterval(timer);
          finishCancelled();
        } else if (attempts >= maxAttempts) {
          clearInterval(timer);
          giveUp();
        }
      })
      .catch(() => { /* transient network error — keep polling */ });
  }

  // Fire first poll immediately — catches the common case where the webhook
  // already ran by the time the customer hit this page from Omise.
  poll();
  timer = setInterval(poll, 5000);
})();
</script>
@endif
@endsection
