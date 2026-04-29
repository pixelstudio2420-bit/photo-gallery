@extends('layouts.app')

@section('title', 'สถานะการชำระเงิน')

@section('content')
@php
  $statuses = ['pending_payment', 'pending_review', 'paid'];
  $statusIdx = array_search($order->status, $statuses);
  $isCancelled = $order->status === 'cancelled';
@endphp

<div class="max-w-5xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
        <i class="bi bi-receipt"></i>
      </span>
      สถานะการชำระเงิน
    </h1>
    <a href="{{ route('orders.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 text-sm transition">
      <i class="bi bi-arrow-left"></i> คำสั่งซื้อทั้งหมด
    </a>
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
              <p class="text-sm text-slate-700 dark:text-slate-300 mb-4">กรุณาอัปโหลดสลิปการโอนเงินเพื่อดำเนินการต่อ</p>
              <a href="{{ route('payment.checkout', $order->id) }}"
                 class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-md hover:shadow-lg transition-all">
                <i class="bi bi-upload"></i> อัปโหลดสลิป
              </a>
            </div>

          @elseif($order->status === 'pending_review')
            <div class="text-center p-5 rounded-2xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
              <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-500 text-white mb-3 shadow-md">
                <i class="bi bi-hourglass-split text-2xl animate-pulse"></i>
              </div>
              <p class="font-semibold text-amber-900 dark:text-amber-200">กำลังตรวจสอบหลักฐานการโอนเงิน</p>
              <p class="text-xs text-amber-800 dark:text-amber-300/80 mt-1">ปกติใช้เวลา 15–30 นาที ในช่วงเวลาทำการ</p>
            </div>

          @elseif($order->status === 'paid')
            <div class="text-center p-5 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20">
              <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white mb-3 shadow-lg">
                <i class="bi bi-check-circle-fill text-3xl"></i>
              </div>
              <h3 class="text-lg font-bold text-emerald-900 dark:text-emerald-200">ชำระเงินสำเร็จ!</h3>
              <p class="text-sm text-emerald-800 dark:text-emerald-300/80 mb-4">คำสั่งซื้อของคุณได้รับการยืนยันแล้ว</p>
              @if($downloadTokens->isNotEmpty())
                @php $allPhotosToken = $downloadTokens->whereNull('photo_id')->first() ?? $downloadTokens->first(); @endphp
                <a href="{{ route('download.show', $allPhotosToken->token) }}"
                   class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-semibold shadow-md hover:shadow-lg transition-all">
                  <i class="bi bi-download"></i> ดาวน์โหลดรูปภาพทั้งหมด ({{ $order->items->count() }} รูป)
                </a>
              @else
                <p class="text-xs text-slate-500 dark:text-slate-400">ลิงก์ดาวน์โหลดจะถูกส่งทางอีเมลของคุณ</p>
              @endif
            </div>

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

    {{-- Sidebar --}}
    <div class="lg:col-span-1">
      <div class="lg:sticky lg:top-24">
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-lg overflow-hidden">
          <div class="h-1.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
          <div class="p-5">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5 mb-4">
              <i class="bi bi-bag text-indigo-500"></i> สรุปคำสั่งซื้อ
            </h3>
            <dl class="text-sm space-y-2.5 mb-4 pb-4 border-b border-slate-100 dark:border-white/5">
              <div class="flex justify-between">
                <dt class="text-slate-500 dark:text-slate-400">เลขที่คำสั่งซื้อ</dt>
                <dd class="font-mono font-medium text-slate-900 dark:text-white">#{{ $order->order_number ?? $order->id }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-slate-500 dark:text-slate-400">วันที่สั่งซื้อ</dt>
                <dd class="text-slate-900 dark:text-white">{{ $order->created_at?->format('d/m/Y') }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-slate-500 dark:text-slate-400">จำนวนรายการ</dt>
                <dd class="text-slate-900 dark:text-white">{{ $order->items->count() }} รายการ</dd>
              </div>
            </dl>
            <div class="flex items-baseline justify-between">
              <span class="font-bold text-slate-900 dark:text-white">ยอดรวม</span>
              <span class="text-2xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                {{ number_format((float)$order->total, 0) }} ฿
              </span>
            </div>
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
