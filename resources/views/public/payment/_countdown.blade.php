{{--
  Payment countdown banner.
  Renders a live MM:SS timer that ticks down to $order->payment_expires_at.

  Usage:
    @include('public.payment._countdown', ['order' => $order])

  Visual states (driven by Alpine x-bind so we don't reflow the DOM):
    - >5 min remaining  : amber background, neutral copy
    - ≤5 min remaining  : rose pulse, "หมดเวลาเร็วๆ นี้!"
    - 0 sec             : grey "หมดเวลาแล้ว — กรุณาสร้างคำสั่งซื้อใหม่"

  Hidden when the order has no expiry (older row pre-migration) or is
  already paid — both edge cases shouldn't show the banner.
--}}
@if(!empty($order) && $order->payment_expires_at && !$order->isPaid())
  @php
    $secondsLeft = max(0, (int) $order->paymentSecondsRemaining());
  @endphp

  <div x-data="paymentCountdown({{ $secondsLeft }})"
       x-init="start()"
       class="rounded-xl border-2 mb-3 sm:mb-4 overflow-hidden transition-colors"
       :class="state === 'urgent'
                  ? 'border-rose-300 bg-gradient-to-r from-rose-50 to-orange-50 dark:from-rose-500/10 dark:to-orange-500/10 dark:border-rose-500/30'
                  : (state === 'expired'
                       ? 'border-slate-300 bg-slate-100 dark:border-slate-700 dark:bg-slate-800/50'
                       : 'border-amber-300 bg-gradient-to-r from-amber-50 to-yellow-50 dark:from-amber-500/10 dark:to-yellow-500/10 dark:border-amber-500/30')">
    <div class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4">
      {{-- Icon + pulse ring on urgent --}}
      <div class="shrink-0 relative">
        <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full flex items-center justify-center text-white shadow-md transition-colors"
             :class="state === 'urgent' ? 'bg-rose-500' : (state === 'expired' ? 'bg-slate-500' : 'bg-amber-500')">
          <i class="bi" :class="state === 'expired' ? 'bi-x-circle-fill' : 'bi-stopwatch-fill'"
             style="font-size: 1.1rem;"></i>
        </div>
        {{-- Pulse ring — only when urgent, lights off otherwise --}}
        <span x-show="state === 'urgent'"
              class="absolute inset-0 rounded-full bg-rose-400 animate-ping opacity-40"
              aria-hidden="true"></span>
      </div>

      {{-- Copy + countdown --}}
      <div class="flex-1 min-w-0">
        <div class="text-xs sm:text-sm font-bold leading-tight"
             :class="state === 'urgent'
                        ? 'text-rose-700 dark:text-rose-300'
                        : (state === 'expired'
                             ? 'text-slate-600 dark:text-slate-400'
                             : 'text-amber-700 dark:text-amber-300')">
          <span x-show="state === 'normal'">เหลือเวลาในการชำระเงิน</span>
          <span x-show="state === 'urgent'" x-cloak>
            <i class="bi bi-exclamation-triangle-fill"></i> รีบชำระเงิน — ใกล้หมดเวลาแล้ว!
          </span>
          <span x-show="state === 'expired'" x-cloak>หมดเวลาชำระเงินแล้ว</span>
        </div>

        {{-- The countdown itself — large, monospace for the "stable" feel --}}
        <div class="font-mono font-extrabold mt-0.5 leading-none tracking-tight"
             :class="state === 'urgent'
                        ? 'text-rose-600 dark:text-rose-400 text-2xl sm:text-3xl'
                        : (state === 'expired'
                             ? 'text-slate-500 dark:text-slate-400 text-lg sm:text-xl'
                             : 'text-amber-700 dark:text-amber-300 text-2xl sm:text-3xl')">
          <span x-text="display">--:--</span>
        </div>

        <div x-show="state === 'expired'" x-cloak
             class="text-[11px] sm:text-xs text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed">
          กรุณากลับไปที่หน้า <a href="{{ route('orders.show', $order->id) }}" class="font-semibold underline">รายละเอียดคำสั่งซื้อ</a>
          และสั่งซื้อใหม่ ระบบจะออก QR/เลขบัญชีให้ใหม่อัตโนมัติ
        </div>
      </div>
    </div>
  </div>

  <script>
    /* Alpine factory function — define once globally; x-data wires up
       per-banner instances with their own initial second count. */
    if (!window.paymentCountdown) {
      window.paymentCountdown = function (initialSeconds) {
        return {
          remaining: Math.max(0, parseInt(initialSeconds) || 0),
          timer: null,
          /** UI state — keeps Alpine class-bindings simple. */
          get state() {
            if (this.remaining <= 0) return 'expired';
            if (this.remaining <= 300) return 'urgent';   // ≤ 5 min
            return 'normal';
          },
          /** MM:SS or HH:MM:SS depending on length. */
          get display() {
            const s = Math.max(0, this.remaining);
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            const pad = n => n.toString().padStart(2, '0');
            return h > 0 ? `${pad(h)}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
          },
          start() {
            // Tick every 1 second. Stops itself when expired so we
            // don't waste a setInterval running forever.
            this.timer = setInterval(() => {
              this.remaining = Math.max(0, this.remaining - 1);
              if (this.remaining <= 0 && this.timer) {
                clearInterval(this.timer);
                this.timer = null;
              }
            }, 1000);
          },
        };
      };
    }
  </script>
@endif
