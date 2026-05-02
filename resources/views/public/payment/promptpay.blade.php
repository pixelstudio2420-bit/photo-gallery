@extends('layouts.app')

@section('title', 'PromptPay — ชำระเงิน')

@php
  // Brand info for the savable card. Resolved from AppSetting → config
  // (same chain ViewServiceProvider uses) so admin re-branding flows
  // through automatically.
  $_ppSiteName  = $siteName ?? \App\Models\AppSetting::get('site_name', '') ?: config('app.name', 'Loadroop');
  $_ppBrandHost = preg_replace('/^www\./i', '', parse_url(config('app.url', 'https://loadroop.com'), PHP_URL_HOST) ?: 'loadroop.com');
  $_ppAmountStr = number_format((float)($amount ?? $transaction->amount ?? 0), 2);
@endphp

@section('content-full')
<div class="flex items-center justify-center px-4 py-8 min-h-[70vh]">
  <div class="w-full max-w-md">
    {{-- Countdown sits ABOVE the card so the urgency state is visible
         even before the user scrolls to the QR. --}}
    @include('public.payment._countdown', ['order' => $order])

    {{-- ── BRANDED PROMPTPAY CARD ──────────────────────────────────────
         The whole card is wrapped in #promptpay-card so html2canvas can
         rasterise just this region when user clicks "บันทึกรูป QR".
         Save button + slip uploader sit OUTSIDE this wrapper on purpose.

         Important: the QR <img> itself stays PRISTINE (no logo overlay)
         because PromptPay QR follows the BOT EMVCo spec — banking apps
         scan the entire QR including its quiet-zone, and overlaying
         anything risks scan failure. The branding goes AROUND the QR
         (header band + corner brackets + footer ribbon), never on it.
    ──────────────────────────────────────────────────────────────── --}}
    <div id="promptpay-card" class="rounded-3xl overflow-hidden bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-2xl">

      {{-- Header band — gradient + brand name --}}
      <div class="relative text-center py-6 px-5 bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 overflow-hidden">
        <div class="absolute inset-0 opacity-30 pointer-events-none"
             style="background: radial-gradient(circle at 20% 20%, rgba(255,255,255,.18), transparent 40%), radial-gradient(circle at 80% 80%, rgba(255,255,255,.10), transparent 50%);"></div>
        <div class="relative">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md mb-3 border border-white/30">
            <i class="bi bi-qr-code text-white text-2xl"></i>
          </div>
          <h1 class="font-bold text-white text-xl mb-1">PromptPay</h1>
          <div class="inline-block text-[10px] tracking-[0.22em] uppercase font-bold text-white/85 mt-1">
            {{ $_ppSiteName }}
          </div>
        </div>
      </div>

      <div class="p-5 text-center">
        {{-- Amount — gradient text, prominent --}}
        <div class="mb-5">
          <div class="text-3xl font-bold bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 bg-clip-text text-transparent">
            ฿{{ $_ppAmountStr }}
          </div>
          @if(!empty($order->order_number))
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">คำสั่งซื้อ: <strong class="text-slate-700 dark:text-slate-300">{{ $order->order_number }}</strong></div>
          @endif
          @if(!empty($transaction->transaction_id))
            <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">TX: {{ $transaction->transaction_id }}</div>
          @endif
        </div>

        {{-- QR Code — framed with indigo corner brackets for polish.
             The QR image itself stays untouched (no overlay); brackets
             sit OUTSIDE the QR padding so they don't interfere with
             scanner detection of the QR's quiet zone. --}}
        <div class="relative inline-block p-4 mb-4 rounded-2xl bg-white shadow-md">
          {{-- Corner brackets (purely decorative) --}}
          <span class="absolute w-5 h-5" style="top:-2px;left:-2px;border-top:3px solid #7c3aed;border-left:3px solid #7c3aed;border-top-left-radius:14px;"></span>
          <span class="absolute w-5 h-5" style="top:-2px;right:-2px;border-top:3px solid #7c3aed;border-right:3px solid #7c3aed;border-top-right-radius:14px;"></span>
          <span class="absolute w-5 h-5" style="bottom:-2px;left:-2px;border-bottom:3px solid #7c3aed;border-left:3px solid #7c3aed;border-bottom-left-radius:14px;"></span>
          <span class="absolute w-5 h-5" style="bottom:-2px;right:-2px;border-bottom:3px solid #ec4899;border-right:3px solid #ec4899;border-bottom-right-radius:14px;"></span>

          @if(!empty($qrUrl))
            <img id="promptpay-qr-img" src="{{ $qrUrl }}" alt="PromptPay QR Code" class="w-56 h-56" crossorigin="anonymous">
          @elseif(!empty($qrPayload))
            <img id="promptpay-qr-img"
                 src="https://api.qrserver.com/v1/create-qr-code/?{{ http_build_query(['data' => $qrPayload, 'size' => '220x220', 'ecc' => 'M', 'margin' => '10', 'format' => 'png']) }}"
                 alt="PromptPay QR Code" class="w-56 h-56" crossorigin="anonymous">
          @else
            <div class="w-56 h-56 bg-slate-50 flex items-center justify-center rounded-lg">
              <span class="text-slate-400 text-sm">ไม่พบ QR Code</span>
            </div>
          @endif
        </div>

        @if(!empty($promptPayNumber))
          <div class="text-sm text-slate-500 dark:text-slate-400 mb-1">หมายเลข PromptPay:</div>
          <div class="inline-flex items-center gap-2 font-mono font-bold text-slate-900 dark:text-white mb-3">
            {{ $promptPayNumber }}
            <button onclick="navigator.clipboard.writeText('{{ $promptPayNumber }}'); this.innerHTML='<i class=\'bi bi-check2\'></i>'; setTimeout(()=>{this.innerHTML='<i class=\'bi bi-clipboard\'></i>'}, 1500)"
                    class="text-slate-400 hover:text-indigo-500 transition" title="คัดลอก">
              <i class="bi bi-clipboard text-xs"></i>
            </button>
          </div>
        @endif

        {{-- Footer ribbon inside the card — brand domain --}}
        <div class="-mx-5 -mb-5 mt-5 px-5 py-3 bg-gradient-to-br from-slate-900 to-indigo-950 text-white text-sm font-semibold flex items-center justify-center gap-2">
          <i class="bi bi-globe2 text-violet-300"></i>
          <span>{{ $_ppBrandHost }}</span>
        </div>
      </div>
    </div>
    {{-- ── END BRANDED CARD ─────────────────────────────────────────── --}}

    {{-- Save QR button — sits OUTSIDE the card so it's not in the saved
         image. Uses Web Share API on iOS (opens native share sheet with
         "Save to Photos"), falls back to direct download elsewhere. --}}
    <div class="mt-4 flex flex-col items-center gap-2">
      <button type="button" id="savePpQrBtn" onclick="savePromptPayQR(this)"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold shadow-md hover:shadow-lg transition-all">
        <i class="bi bi-download"></i>
        <span>บันทึกรูป QR Code</span>
      </button>
      {{-- iPhone hint — Safari without Web Share fallback. The hint
           always shows on iOS so users with older iOS know they can
           also long-press. Hidden on Android/desktop to reduce noise. --}}
      <p id="iosLongPressHint" class="hidden text-[11px] text-slate-500 dark:text-slate-400 text-center max-w-xs">
        <i class="bi bi-info-circle"></i>
        iPhone: ถ้าหน้าต่างแชร์ไม่ขึ้น ให้ค้างนิ้วบน QR Code แล้วเลือก "Save Image"
      </p>
    </div>

    <div class="mt-6 rounded-3xl overflow-hidden bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-2xl p-5 text-center">
        @php /* spacer wrapper so the existing instruction + upload UI
               below isn't visually merged with the card we just built. */ @endphp

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
    </div>{{-- /upload card --}}
  </div>{{-- /max-w-md --}}
</div>{{-- /flex outer --}}

@push('scripts')
{{-- html2canvas for rasterising the card to a PNG. Same library used
     by the event QR card — already battle-tested. ~200KB but only
     loaded on this page. --}}
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>
<script>
(function () {
  // Show the iPhone "long-press" hint only on iOS — Android/desktop
  // users get the direct download path and don't need this hint.
  // navigator.platform is deprecated but still the simplest reliable
  // iOS sniff; we fall back to userAgent for newer iPads that report
  // as MacIntel.
  const isIOS = /iPad|iPhone|iPod/.test(navigator.platform || '')
             || (navigator.userAgent.includes('Mac') && 'ontouchend' in document);
  if (isIOS) {
    const hint = document.getElementById('iosLongPressHint');
    if (hint) hint.classList.remove('hidden');
  }

  // Wait for the QR <img> to actually finish loading before allowing
  // capture — html2canvas would otherwise rasterise an empty image
  // and produce a card with a missing QR.
  const qrReady = new Promise((resolve) => {
    const img = document.getElementById('promptpay-qr-img');
    if (!img) return resolve();
    if (img.complete && img.naturalWidth > 0) return resolve();
    img.addEventListener('load',  resolve, { once: true });
    img.addEventListener('error', resolve, { once: true });  // resolve anyway
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
      // 2× scale = retina-quality. Background null preserves rounded
      // corners on the card. useCORS lets html2canvas read the QR
      // image even when it comes from api.qrserver.com (it returns
      // Access-Control-Allow-Origin: *).
      const canvas = await html2canvas(card, {
        scale: 2,
        backgroundColor: null,
        useCORS: true,
        logging: false,
      });

      // Convert to a Blob first — toBlob is more memory-efficient than
      // toDataURL for retina-sized canvases (toDataURL can OOM on
      // older iPhones with the bigger 2× output).
      const blob = await new Promise((res) => canvas.toBlob(res, 'image/png', 0.95));
      if (!blob) throw new Error('toBlob returned null');

      const fileName = 'promptpay-qr-' + Date.now() + '.png';
      const file = new File([blob], fileName, { type: 'image/png' });

      // Strategy 1 — Web Share API (the iOS-friendly path).
      // iOS Safari 15+ supports navigator.share with files, which
      // opens the native share sheet → user taps "Save to Photos".
      // This is the ONLY reliable way to save to camera roll on iOS
      // (anchor[download] doesn't work on Safari; it opens the image
      // in a new tab instead).
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        try {
          await navigator.share({
            files: [file],
            title: 'PromptPay QR Code',
            text: 'QR Code สำหรับชำระเงิน',
          });
          // User completed share (or cancelled — both are "success"
          // for our purposes; we don't want to fall through to download
          // because that would prompt twice).
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-check2"></i> <span>บันทึกแล้ว</span>';
          setTimeout(() => { btn.innerHTML = original; }, 2200);
          return;
        } catch (err) {
          // User explicitly cancelled the share sheet — don't show an
          // error, just restore the button. AbortError is the cancel
          // signal; anything else falls through to download fallback.
          if (err.name === 'AbortError') {
            btn.disabled = false;
            btn.innerHTML = original;
            return;
          }
          // Fall through to download
        }
      }

      // Strategy 2 — Download via blob URL.
      // Works on Android Chrome + desktop browsers. On iOS this opens
      // the image in a new tab (Safari quirk), but the user can then
      // long-press to save — the hint we showed earlier covers this.
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
@endpush
@endsection
