@extends('layouts.app')

@section('title', isset($plan) && $plan ? 'สถานะการสมัครแผน ' . $plan->name : 'สถานะการชำระเงิน')

@section('content')
@php
  $statuses = ['pending_payment', 'pending_review', 'paid'];
  $statusIdx = array_search($order->status, $statuses);
  $isCancelled = $order->status === 'cancelled';

  // $subscription and $plan come from the controller when this order is a
  // subscription purchase. Falsy = regular photo order → template uses the
  // legacy download-link panel. The view-level branch keeps both flows in
  // a single template so layout, polling, slip-status sidebar etc. stay
  // shared — only the paid-state body and order-summary card swap.
  $isSubscription = $order->isSubscriptionOrder() && !empty($plan);
@endphp

<div class="max-w-5xl mx-auto px-4 md:px-6 py-6">

  {{-- Header — subscription orders get an emerald gradient + plan-specific
       title to make it visually distinct from photo-order receipts. The
       back-link also routes to the photographer subscription index for
       subscription orders (vs orders.index for photo orders). --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-white shadow-md
                   {{ $isSubscription
                      ? 'bg-gradient-to-br from-emerald-500 to-teal-600'
                      : 'bg-gradient-to-br from-indigo-500 to-purple-600' }}">
        <i class="bi {{ $isSubscription ? 'bi-stars' : 'bi-receipt' }}"></i>
      </span>
      @if($isSubscription)
        สมัครแผน {{ $plan->name }}
      @else
        สถานะการชำระเงิน
      @endif
    </h1>
    @if($isSubscription && \Illuminate\Support\Facades\Route::has('photographer.subscription.index'))
      <a href="{{ route('photographer.subscription.index') }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 text-sm transition">
        <i class="bi bi-arrow-left"></i> แผนของฉัน
      </a>
    @else
      <a href="{{ route('orders.index') }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 text-sm transition">
        <i class="bi bi-arrow-left"></i> คำสั่งซื้อทั้งหมด
      </a>
    @endif
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-5">

      {{-- Progress Stepper --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-md">
            <i class="bi bi-diagram-3"></i>
          </div>
          <h3 class="font-semibold text-slate-900 dark:text-white">ขั้นตอนการชำระเงิน</h3>
        </div>

        <div class="p-5">
          <div class="grid grid-cols-3 gap-2 relative mb-5">
            @foreach([
              ['key' => 'pending_payment', 'label' => 'รอชำระเงิน', 'icon' => 'bi-clock'],
              ['key' => 'pending_review',  'label' => 'รอตรวจสอบ',  'icon' => 'bi-search'],
              ['key' => 'paid',            'label' => 'ชำระสำเร็จ',   'icon' => 'bi-check-circle'],
            ] as $i => $step)
              @php
                $stepIdx = array_search($step['key'], $statuses);
                $done    = !$isCancelled && $statusIdx !== false && $statusIdx >= $stepIdx;
                $active  = !$isCancelled && $statusIdx === $stepIdx;
              @endphp
              <div class="relative">
                <div class="flex flex-col items-center text-center">
                  <div class="w-11 h-11 rounded-full flex items-center justify-center z-10 transition
                      {{ $done && !$active ? 'bg-gradient-to-br from-emerald-500 to-teal-500 text-white shadow-md' : '' }}
                      {{ $active ? 'bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md ring-4 ring-indigo-100 dark:ring-indigo-500/20 animate-pulse' : '' }}
                      {{ $isCancelled && $i === 0 ? 'bg-gradient-to-br from-rose-500 to-red-500 text-white' : '' }}
                      {{ !$done && !$active && !$isCancelled ? 'bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-slate-500' : '' }}
                      {{ $isCancelled && $i !== 0 ? 'bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-slate-500' : '' }}">
                    <i class="bi {{ $done && !$active ? 'bi-check-lg' : $step['icon'] }} text-lg"></i>
                  </div>
                  <div class="mt-2 text-xs font-medium {{ $done || $active ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400' }}">
                    {{ $step['label'] }}
                  </div>
                </div>
                @if(!$loop->last)
                  <div class="absolute top-[22px] left-1/2 w-full h-0.5 -z-0
                      {{ $done && $statusIdx > $stepIdx ? 'bg-gradient-to-r from-emerald-500 to-teal-500' : 'bg-slate-200 dark:bg-white/10' }}"></div>
                @endif
              </div>
            @endforeach
          </div>

          {{-- Status-specific content --}}
          @if($order->status === 'pending_payment')
            <div class="text-center p-5 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20">
              <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white mb-3 shadow-md">
                <i class="bi bi-wallet2 text-2xl"></i>
              </div>
              <p class="text-sm text-slate-700 dark:text-slate-300 mb-4">
                @if($isSubscription)
                  กรุณาชำระเงินเพื่อเปิดใช้งาน <strong>{{ $plan->name }}</strong>
                @else
                  กรุณาอัปโหลดสลิปการโอนเงินเพื่อดำเนินการต่อ
                @endif
              </p>
              <a href="{{ route('payment.checkout', $order->id) }}"
                 class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-md hover:shadow-lg transition-all">
                <i class="bi bi-credit-card-fill"></i>
                {{ $isSubscription ? 'ดำเนินการชำระเงิน' : 'อัปโหลดสลิป' }}
              </a>
            </div>

          @elseif($order->status === 'pending_review')
            @php
              // Slip upload moment — preferred input for the elapsed
              // counter. We fall back to order created_at when no slip
              // row is present (shouldn't happen in pending_review state
              // but the guard keeps the page from blowing up).
              $slipUploadedAt = ($latestSlip?->created_at ?? $order->created_at);
              $slipUploadedTs = $slipUploadedAt ? $slipUploadedAt->timestamp : time();

              // SLA copy + threshold are AppSetting-tunable so an
              // operator can ratchet the expectation as their team
              // grows or contracts.
              $slaMinutes      = (int) (\App\Models\AppSetting::get('slip_review_sla_minutes', '15') ?: 15);
              $autoRefreshSec  = max(15, (int) (\App\Models\AppSetting::get('slip_review_poll_seconds', '30') ?: 30));
              $contactLineOA   = (string) (\App\Models\AppSetting::get('contact_line_oa', '') ?: '@jabphap');
            @endphp

            <div x-data="slipReviewWatcher({{ $slipUploadedTs }}, {{ $slaMinutes * 60 }}, {{ $autoRefreshSec }})"
                 x-init="start()"
                 class="relative overflow-hidden rounded-2xl border-2 transition-colors duration-500"
                 :class="overdue
                            ? 'bg-gradient-to-br from-rose-50 to-orange-50 dark:from-rose-500/10 dark:to-orange-500/10 border-rose-300 dark:border-rose-500/30'
                            : 'bg-gradient-to-br from-amber-50 to-yellow-50 dark:from-amber-500/10 dark:to-yellow-500/10 border-amber-300 dark:border-amber-500/30'">

              {{-- Top icon row --}}
              <div class="text-center pt-6 pb-3">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl text-white shadow-lg transition-colors"
                     :class="overdue
                                ? 'bg-gradient-to-br from-rose-500 to-orange-500'
                                : 'bg-gradient-to-br from-amber-500 to-orange-500'">
                  <i class="bi bi-hourglass-split text-3xl animate-pulse"></i>
                </div>
              </div>

              {{-- Headline + sub-copy. Three states: normal / overdue /
                   redirected_after_approval. The third triggers when the
                   poll detects status flip — we reload the page so the
                   user lands on the green "paid" panel instead of seeing
                   stale text. --}}
              <div class="text-center px-5">
                <p class="font-bold text-base sm:text-lg transition-colors"
                   :class="overdue ? 'text-rose-700 dark:text-rose-300' : 'text-amber-900 dark:text-amber-200'">
                  <span x-show="!overdue">กำลังตรวจสอบสลิปการโอนเงิน</span>
                  <span x-show="overdue" x-cloak>
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    ใช้เวลานานกว่าปกติ
                  </span>
                </p>
                <p class="text-xs sm:text-sm mt-1 leading-relaxed transition-colors"
                   :class="overdue ? 'text-rose-700/80 dark:text-rose-300/80' : 'text-amber-800/80 dark:text-amber-300/80'">
                  <span x-show="!overdue">SLA ปกติ ~{{ $slaMinutes }} นาที — เราจะอัปเดตหน้านี้อัตโนมัติเมื่อเสร็จ</span>
                  <span x-show="overdue" x-cloak>
                    ทีมแอดมินกำลังเร่งตรวจ — สามารถติดต่อ LINE OA <strong>{{ $contactLineOA }}</strong> ได้เลย
                  </span>
                </p>
              </div>

              {{-- Big elapsed-time counter — count UP, not down. The
                   uncertain duration of the wait is part of why
                   counting up feels less stressful than a count-down
                   that can hit zero with no resolution. --}}
              <div class="px-5 pt-5">
                <div class="rounded-xl bg-white/70 dark:bg-slate-900/40 border border-white/40 dark:border-white/5 p-4 backdrop-blur-sm">
                  <div class="text-[10px] uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 font-bold text-center">
                    เวลารอ
                  </div>
                  <div class="font-mono font-extrabold text-3xl sm:text-4xl text-center mt-1 leading-none tabular-nums transition-colors"
                       :class="overdue ? 'text-rose-600 dark:text-rose-400' : 'text-amber-700 dark:text-amber-300'">
                    <span x-text="elapsedDisplay">--:--</span>
                  </div>
                  <div class="mt-2 h-1.5 rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500"
                         :class="overdue ? 'bg-rose-500' : 'bg-gradient-to-r from-amber-500 to-orange-500'"
                         :style="`width: ${Math.min(100, progressPct)}%`"></div>
                  </div>
                  <div class="flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400 mt-1.5">
                    <span>0 นาที</span>
                    <span class="opacity-50" x-show="!overdue">SLA {{ $slaMinutes }} นาที</span>
                    <span x-show="overdue" x-cloak class="text-rose-500 font-bold">เกิน SLA</span>
                  </div>
                </div>
              </div>

              {{-- Live polling indicator. Shows when last checked +
                   when next refresh is. The auto-reload below quietly
                   re-fetches the page status; this ribbon makes the
                   activity visible to the user so they don't think
                   the page is frozen. --}}
              <div class="px-5 py-4 flex items-center justify-center gap-2 text-[11px] sm:text-xs text-slate-600 dark:text-slate-400">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" aria-hidden="true"></span>
                <span>กำลังรอผลแบบเรียลไทม์</span>
                <span class="text-slate-400">·</span>
                <span>อัปเดตอัตโนมัติทุก {{ $autoRefreshSec }} วินาที</span>
              </div>

              {{-- Overdue contact buttons — only render once we cross SLA. --}}
              <div x-show="overdue" x-cloak
                   class="px-5 pb-5 grid grid-cols-1 sm:grid-cols-2 gap-2">
                <a href="https://line.me/R/ti/p/{{ urlencode($contactLineOA) }}"
                   target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold text-sm shadow-md transition no-underline">
                  <svg viewBox="0 0 24 24" class="w-4 h-4" fill="#fff" aria-hidden="true"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zM24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
                  ติดต่อ LINE OA
                </a>
                <button type="button" @click="window.location.reload()"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border-2 border-rose-300 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 font-semibold text-sm bg-transparent hover:bg-rose-50 dark:hover:bg-rose-500/10 transition cursor-pointer">
                  <i class="bi bi-arrow-clockwise"></i> ตรวจสอบเดี๋ยวนี้
                </button>
              </div>
            </div>

            <script>
              /* Alpine factory — count up from $slipUploadedTs and
                 quietly reload the page every $autoRefreshSec to pick
                 up admin approval. Defined once globally so multiple
                 widgets can share it; status.blade.php only renders
                 one. */
              if (!window.slipReviewWatcher) {
                window.slipReviewWatcher = function (uploadedTs, slaSeconds, refreshSeconds) {
                  return {
                    elapsed: 0,           // seconds since slip upload
                    overdue: false,
                    progressPct: 0,
                    elapsedDisplay: '0:00',
                    timer: null,
                    refreshTimer: null,
                    /** Tick every second, format MM:SS / HH:MM:SS, set
                        overdue + progress bar % once SLA passes. */
                    tick() {
                      const nowMs = Date.now();
                      this.elapsed = Math.max(0, Math.floor((nowMs - uploadedTs * 1000) / 1000));
                      this.overdue = this.elapsed > slaSeconds;
                      // Progress bar tops out at 100% at SLA, then
                      // stays at 100% (overdue state shifts colour
                      // instead of overflowing).
                      this.progressPct = Math.min(100, (this.elapsed / Math.max(1, slaSeconds)) * 100);
                      const s = this.elapsed;
                      const h = Math.floor(s / 3600);
                      const m = Math.floor((s % 3600) / 60);
                      const sec = s % 60;
                      const pad = n => n.toString().padStart(2, '0');
                      this.elapsedDisplay = h > 0 ? `${pad(h)}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
                    },
                    start() {
                      this.tick();
                      this.timer = setInterval(() => this.tick(), 1000);
                      // Quiet auto-reload — when admin approves, the
                      // refreshed page lands on the green paid state
                      // with download tokens already populated.
                      this.refreshTimer = setTimeout(
                        () => window.location.reload(),
                        refreshSeconds * 1000
                      );
                    },
                  };
                };
              }
            </script>

          @elseif($order->status === 'paid')
            @php
              // Detect whether photos were dispatched to the buyer's LINE.
              // delivery_status enum: pending|sent|delivered|failed|partial.
              // 'sent' / 'delivered' / 'partial' all mean "we successfully
              // handed it off to LINE" (or at least most of it). We treat
              // them as a single "delivered to LINE" UI state.
              $sentToLine = in_array($order->delivery_status ?? null, ['sent','delivered','partial'], true);

              // Show the LINE notice only if the buyer actually has a
              // LINE account linked — otherwise the message is a lie.
              $userHasLine = false;
              try {
                  $userHasLine = $order->user_id && \Illuminate\Support\Facades\DB::table('auth_social_logins')
                      ->where('user_id', $order->user_id)
                      ->where('provider', 'line')
                      ->exists();
              } catch (\Throwable) {}
            @endphp

            @if($isSubscription)
              {{-- ═══════════ SUBSCRIPTION PAID PANEL ═══════════
                   Plan purchase landed — celebrate it specifically.
                   Show plan name, what just unlocked (storage / AI /
                   commission), and the period dates so the user knows
                   exactly what they paid for. Two CTAs: dashboard +
                   subscription detail (history + cancel). --}}
              @php
                $storageGb = $plan ? (int) round(($plan->storage_bytes ?? 0) / 1073741824) : 0;
                $aiCredits = (int) ($plan->monthly_ai_credits ?? 0);
                $commissionPct = (float) ($plan->commission_pct ?? 0);
                $periodEnd = $subscription?->current_period_end?->locale('th')->isoFormat('D MMMM YYYY');
                $cycleLabel = ($subscription?->meta['billing_cycle'] ?? 'monthly') === 'annual' ? 'รายปี' : 'รายเดือน';
              @endphp

              <div class="rounded-2xl overflow-hidden bg-gradient-to-br from-emerald-500 via-teal-500 to-cyan-500 text-white shadow-xl">
                <div class="px-5 sm:px-6 pt-6 pb-5 text-center relative overflow-hidden">
                  {{-- Confetti dots (decorative, no JS) --}}
                  <div class="absolute -top-4 -right-4 w-32 h-32 rounded-full bg-white/15 blur-2xl pointer-events-none"></div>
                  <div class="absolute -bottom-6 -left-6 w-32 h-32 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>

                  <div class="relative">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm mb-3 shadow-lg">
                      <i class="bi bi-stars text-3xl"></i>
                    </div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] opacity-90 mb-1.5">
                      🎉 เปิดใช้งานสำเร็จ
                    </p>
                    <h3 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight">
                      ยินดีต้อนรับสู่ {{ $plan->name }}
                    </h3>
                    @if($periodEnd)
                      <p class="text-sm opacity-90 mt-2">
                        ใช้งานได้ถึง <strong>{{ $periodEnd }}</strong> · ต่ออายุ{{ $cycleLabel }}อัตโนมัติ
                      </p>
                    @endif
                  </div>
                </div>

                {{-- What just unlocked (3 stat tiles) --}}
                <div class="px-4 pb-4 grid grid-cols-3 gap-2">
                  <div class="rounded-xl bg-white/15 backdrop-blur-sm p-3 text-center">
                    <p class="text-[10px] uppercase tracking-wider opacity-75 font-bold">พื้นที่</p>
                    <p class="text-lg font-extrabold mt-0.5">{{ number_format($storageGb) }}</p>
                    <p class="text-[10px] opacity-80">GB</p>
                  </div>
                  <div class="rounded-xl bg-white/15 backdrop-blur-sm p-3 text-center">
                    <p class="text-[10px] uppercase tracking-wider opacity-75 font-bold">AI/เดือน</p>
                    <p class="text-lg font-extrabold mt-0.5">{{ number_format($aiCredits) }}</p>
                    <p class="text-[10px] opacity-80">เครดิต</p>
                  </div>
                  <div class="rounded-xl bg-white/15 backdrop-blur-sm p-3 text-center">
                    <p class="text-[10px] uppercase tracking-wider opacity-75 font-bold">ค่าคอม</p>
                    <p class="text-lg font-extrabold mt-0.5">{{ rtrim(rtrim(number_format($commissionPct, 1), '0'), '.') }}%</p>
                    <p class="text-[10px] opacity-80">หักจากยอดขาย</p>
                  </div>
                </div>

                {{-- CTAs --}}
                <div class="px-4 sm:px-5 pb-5 space-y-2">
                  <a href="{{ route('photographer.dashboard') }}"
                     class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-white text-emerald-700 font-bold text-sm shadow-md hover:shadow-lg transition-all">
                    <i class="bi bi-grid-1x2-fill"></i>
                    ไปที่ Dashboard ของฉัน
                  </a>
                  @if(\Illuminate\Support\Facades\Route::has('photographer.subscription.index'))
                    <a href="{{ route('photographer.subscription.index') }}"
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white/15 backdrop-blur-sm text-white font-semibold text-xs hover:bg-white/25 transition border border-white/20">
                      <i class="bi bi-stars"></i>
                      ดูรายละเอียดแผน + ประวัติบิล
                    </a>
                  @endif
                </div>
              </div>

              {{-- Receipt link — same as photo orders, but framed as a
                   plan-purchase receipt. --}}
              <div class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">
                <i class="bi bi-receipt"></i>
                ใบเสร็จเลขที่ <span class="font-mono font-semibold text-slate-700 dark:text-slate-300">#{{ $order->order_number ?? $order->id }}</span>
                · เก็บไว้สำหรับอ้างอิง
              </div>

            @else
              {{-- ═══════════ PHOTO-ORDER PAID PANEL (unchanged) ═══════════ --}}
              <div class="text-center p-5 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white mb-3 shadow-lg">
                  <i class="bi bi-check-circle-fill text-3xl"></i>
                </div>
                <h3 class="text-lg font-bold text-emerald-900 dark:text-emerald-200">ชำระเงินสำเร็จ!</h3>
                <p class="text-sm text-emerald-800 dark:text-emerald-300/80 mb-4">คำสั่งซื้อของคุณได้รับการยืนยันแล้ว</p>

                {{-- LINE delivery confirmation pill — appears only when the
                     buyer has a linked LINE account AND the delivery job
                     has handed the message off. --}}
                @if($userHasLine && $sentToLine)
                  <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-800 dark:text-emerald-200 text-xs font-bold mb-3">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="currentColor" aria-hidden="true"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zM24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
                    ส่งรูปเข้า LINE ของคุณแล้ว
                  </div>
                @elseif($userHasLine)
                  <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-800 dark:text-amber-200 text-xs font-bold mb-3">
                    <i class="bi bi-arrow-repeat animate-spin"></i>
                    กำลังส่งรูปเข้า LINE…
                  </div>
                @endif

                @if($downloadTokens->isNotEmpty())
                  @php $allPhotosToken = $downloadTokens->whereNull('photo_id')->first() ?? $downloadTokens->first(); @endphp
                  <div class="block">
                    <a href="{{ route('download.show', $allPhotosToken->token) }}"
                       class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-semibold shadow-md hover:shadow-lg transition-all">
                      <i class="bi bi-download"></i> ดาวน์โหลดรูปภาพทั้งหมด ({{ $order->items->count() }} รูป)
                    </a>
                  </div>
                @else
                  <p class="text-xs text-slate-500 dark:text-slate-400">ลิงก์ดาวน์โหลดจะถูกส่งทางอีเมลของคุณ</p>
                @endif
              </div>
            @endif

          @elseif($order->status === 'cancelled')
            <div class="text-center p-5 rounded-2xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20">
              <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-rose-500 to-red-500 text-white mb-3 shadow-md">
                <i class="bi bi-x-circle-fill text-2xl"></i>
              </div>
              <p class="font-semibold text-rose-900 dark:text-rose-200 mb-2">คำสั่งซื้อถูกยกเลิก</p>
              @if($latestSlip?->reject_reason)
                <div class="inline-block p-3 rounded-xl bg-white/50 dark:bg-black/20 border border-rose-200 dark:border-rose-500/20 text-xs text-rose-800 dark:text-rose-300 text-left mb-3 max-w-md">
                  <strong>เหตุผล:</strong> {{ $latestSlip->reject_reason }}
                </div><br>
              @endif
              <a href="{{ route('payment.checkout', $order->id) }}"
                 class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border border-indigo-500 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500 hover:text-white transition font-medium">
                <i class="bi bi-arrow-clockwise"></i> อัปโหลดสลิปใหม่
              </a>
            </div>
          @endif
        </div>
      </div>

      {{-- Slip info --}}
      @if($latestSlip)
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 text-white flex items-center justify-center shadow-md">
            <i class="bi bi-image"></i>
          </div>
          <h3 class="font-semibold text-slate-900 dark:text-white">สลิปที่อัปโหลด</h3>
        </div>
        <div class="p-5">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-1">
              @php $slipUrl = $latestSlip->slip_url ?? ''; @endphp
              <a href="{{ $slipUrl }}" target="_blank" class="block group">
                <img src="{{ $slipUrl }}"
                     alt="Payment Slip"
                     class="w-full rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900 max-h-60 object-contain group-hover:scale-[1.02] transition">
                <p class="text-center text-xs text-slate-500 dark:text-slate-400 mt-2 group-hover:text-indigo-500 transition">
                  <i class="bi bi-zoom-in mr-1"></i> คลิกดูเต็ม
                </p>
              </a>
            </div>
            <div class="md:col-span-2">
              <dl class="text-sm divide-y divide-slate-100 dark:divide-white/5">
                <div class="flex items-center justify-between py-2.5">
                  <dt class="text-slate-500 dark:text-slate-400">ยอดที่โอน</dt>
                  <dd class="font-semibold text-slate-900 dark:text-white">{{ number_format((float)($latestSlip->transfer_amount ?? $latestSlip->amount ?? 0), 2) }} ฿</dd>
                </div>
                <div class="flex items-center justify-between py-2.5">
                  <dt class="text-slate-500 dark:text-slate-400">วันที่โอน</dt>
                  <dd class="text-slate-900 dark:text-white">{{ $latestSlip->transfer_date ? \Carbon\Carbon::parse($latestSlip->transfer_date)->format('d/m/Y') : '-' }}</dd>
                </div>
                @if($latestSlip->ref_code ?? $latestSlip->reference_code ?? null)
                <div class="flex items-center justify-between py-2.5">
                  <dt class="text-slate-500 dark:text-slate-400">รหัสอ้างอิง</dt>
                  <dd class="font-mono text-xs text-slate-900 dark:text-white">{{ $latestSlip->ref_code ?? $latestSlip->reference_code }}</dd>
                </div>
                @endif
                <div class="flex items-center justify-between py-2.5">
                  <dt class="text-slate-500 dark:text-slate-400">สถานะการตรวจสอบ</dt>
                  <dd>
                    @php
                      $slipStatus = $latestSlip->verify_status ?? 'pending';
                      $badgeMap = [
                        'pending'  => ['bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300',     'bi-hourglass-split', 'รอตรวจสอบ'],
                        'approved' => ['bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300', 'bi-check-circle',   'อนุมัติแล้ว'],
                        'rejected' => ['bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300',           'bi-x-circle',       'ปฏิเสธ'],
                      ];
                      [$badgeCls, $badgeIcon, $badgeLabel] = $badgeMap[$slipStatus] ?? ['bg-slate-100 dark:bg-slate-500/20 text-slate-700 dark:text-slate-300', 'bi-question', $slipStatus];
                    @endphp
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full {{ $badgeCls }} text-xs font-semibold">
                      <i class="bi {{ $badgeIcon }}"></i> {{ $badgeLabel }}
                    </span>
                  </dd>
                </div>
                @if($latestSlip->verify_score !== null)
                <div class="flex items-center justify-between py-2.5">
                  <dt class="text-slate-500 dark:text-slate-400">คะแนน</dt>
                  <dd class="font-semibold text-slate-900 dark:text-white">{{ $latestSlip->verify_score }}/100</dd>
                </div>
                @endif
              </dl>
            </div>
          </div>
        </div>
      </div>
      @endif
    </div>

    {{-- Sidebar — order summary. For subscription orders we swap "จำนวนรายการ"
         (which would say "1 รายการ" for any plan, useless info) with plan-
         specific rows: storage, AI quota, billing cycle. The accent stripe
         shifts to emerald for plans (signals "investment in growth")
         vs the default pink-purple for photo orders. --}}
    <div class="lg:col-span-1">
      <div class="lg:sticky lg:top-24">
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-lg overflow-hidden">
          <div class="h-1.5 {{ $isSubscription
              ? 'bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500'
              : 'bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500' }}"></div>
          <div class="p-5">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5 mb-4">
              @if($isSubscription)
                <i class="bi bi-stars text-emerald-500"></i> สรุปการสมัครแผน
              @else
                <i class="bi bi-bag text-indigo-500"></i> สรุปคำสั่งซื้อ
              @endif
            </h3>
            <dl class="text-sm space-y-2.5 mb-4 pb-4 border-b border-slate-100 dark:border-white/5">
              <div class="flex justify-between">
                <dt class="text-slate-500 dark:text-slate-400">{{ $isSubscription ? 'เลขที่ใบเสร็จ' : 'เลขที่คำสั่งซื้อ' }}</dt>
                <dd class="font-mono font-medium text-slate-900 dark:text-white">#{{ $order->order_number ?? $order->id }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-slate-500 dark:text-slate-400">วันที่สั่งซื้อ</dt>
                <dd class="text-slate-900 dark:text-white">{{ $order->created_at?->format('d/m/Y') }}</dd>
              </div>
              @if($isSubscription)
                <div class="flex justify-between">
                  <dt class="text-slate-500 dark:text-slate-400">แผน</dt>
                  <dd class="font-bold text-slate-900 dark:text-white">{{ $plan->name }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-slate-500 dark:text-slate-400">รอบบิล</dt>
                  <dd class="text-slate-900 dark:text-white">{{ ($subscription?->meta['billing_cycle'] ?? 'monthly') === 'annual' ? 'รายปี' : 'รายเดือน' }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-slate-500 dark:text-slate-400">พื้นที่</dt>
                  <dd class="text-slate-900 dark:text-white">{{ number_format((int) round(($plan->storage_bytes ?? 0) / 1073741824)) }} GB</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-slate-500 dark:text-slate-400">AI Credits / เดือน</dt>
                  <dd class="text-slate-900 dark:text-white">{{ number_format((int) ($plan->monthly_ai_credits ?? 0)) }}</dd>
                </div>
                @if((float) ($plan->commission_pct ?? 0) === 0.0)
                  <div class="flex justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">ค่าคอมมิชชั่น</dt>
                    <dd class="font-bold text-emerald-600 dark:text-emerald-400">0% (เก็บเต็ม)</dd>
                  </div>
                @endif
              @else
                <div class="flex justify-between">
                  <dt class="text-slate-500 dark:text-slate-400">จำนวนรายการ</dt>
                  <dd class="text-slate-900 dark:text-white">{{ $order->items->count() }} รายการ</dd>
                </div>
              @endif
            </dl>
            <div class="flex items-baseline justify-between">
              <span class="font-bold text-slate-900 dark:text-white">ยอดรวม</span>
              <span class="text-2xl font-bold {{ $isSubscription
                  ? 'bg-gradient-to-r from-emerald-600 to-teal-600'
                  : 'bg-gradient-to-r from-indigo-600 to-purple-600' }} bg-clip-text text-transparent">
                {{ number_format((float)$order->total, 0) }} ฿
              </span>
            </div>

            {{-- Subscription guarantees — small reminders for buyer
                 confidence. Each line maps to actual code paths in
                 SubscriptionService (cancel preserves files, grace
                 period, no contract). --}}
            @if($isSubscription)
              <div class="mt-4 pt-4 border-t border-slate-100 dark:border-white/5 space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                <div class="flex items-start gap-1.5">
                  <i class="bi bi-shield-check text-emerald-500 mt-0.5"></i>
                  <span>ยกเลิกได้ทุกเมื่อ ไม่มีค่าธรรมเนียม</span>
                </div>
                <div class="flex items-start gap-1.5">
                  <i class="bi bi-folder-fill text-indigo-500 mt-0.5"></i>
                  <span>ดาวน์เกรด → ไฟล์ทั้งหมดยังอยู่</span>
                </div>
                <div class="flex items-start gap-1.5">
                  <i class="bi bi-clock-history text-amber-500 mt-0.5"></i>
                  <span>ผ่อนผัน 7 วันถ้าตัดบัตรไม่ผ่าน</span>
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@if(in_array($order->status, ['pending_payment', 'pending_review'], true))
<script>
// Poll for status changes while order is still in a "waiting" state. Covers
// two flows:
//   • pending_review — slip uploaded, admin reviewing / auto-verifier running
//   • pending_payment — gateway-driven (Omise / Stripe / LINE Pay), waiting
//     on webhook to flip status to paid after customer returns from the
//     gateway's hosted page
// In either case, reload as soon as we see paid/cancelled so the page
// re-renders with download buttons or the "try again" fallback.
(function () {
  const checkUrl = '{{ route('payment.check-status', $order->id) }}';
  let pollTimer = null;
  function poll() {
    fetch(checkUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'paid' || data.status === 'cancelled') {
          clearInterval(pollTimer);
          window.location.reload();
        }
      })
      .catch(() => {});
  }
  pollTimer = setInterval(poll, 5000);
})();
</script>
@endif
@endsection
