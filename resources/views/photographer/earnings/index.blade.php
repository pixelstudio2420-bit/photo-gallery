@extends('layouts.photographer')

@section('title', 'รายได้ของฉัน')

@php
  use App\Models\PhotographerDisbursement;
@endphp

@section('content')
@php
  // PromptPay status chip — not a real button, just an at-a-glance
  // "where the money goes" indicator. The wrapping <span> + leading
  // emerald icon styles it as a status pill on every breakpoint;
  // the page-scoped CSS below removes the .pg-btn-ghost button look
  // on mobile so it reads as a chip, not a CTA.
  $earningsActions = !empty($photographer->promptpay_number)
    ? '<div class="pg-btn-ghost earnings-payout-chip"><i class="bi bi-lightning-charge-fill text-emerald-500"></i> จ่ายอัตโนมัติ → PromptPay ***'.substr($photographer->promptpay_number, -4).'</div>'
    : null;
@endphp
{{--
  Page-scoped wrapper — only the earnings hero gets these mobile
  overrides. The shared partial + the global .pg-hero rules in
  public/css/photographer.css are left untouched so every other
  photographer page keeps the existing look.
--}}
<div class="earnings-page-hero">
  @include('photographer.partials.page-hero', [
    'icon'     => 'bi-wallet2',
    'eyebrow'  => 'การเงิน',
    'title'    => 'รายได้ของฉัน',
    'subtitle' => 'ภาพรวมรายได้ · ประวัติการโอน · ค่าคอมมิชชั่นที่ค้าง',
    'actions'  => $earningsActions,
  ])
</div>

@push('styles')
<style>
  /* ──────────────────────────────────────────────────────────────────
     Earnings page — only the bits that are SPECIFIC to this page live
     here now. Mobile layout for .pg-hero is handled globally in
     public/css/photographer.css (every photographer page benefits).

     What stays here:
       • .earnings-payout-chip — soft emerald status pill for the
         PromptPay indicator. Not a CTA button (cursor:default), so
         it deliberately doesn't look like .pg-btn-ghost on mobile.
       • Mobile padding-left on the actions row, so the chip aligns
         with the title baseline (40px icon + 12px gap = 52px) rather
         than the icon's left edge — keeps the eye-line clean.
     ────────────────────────────────────────────────────────────────── */

  /* Status-chip look for the PromptPay indicator (all breakpoints). */
  .earnings-page-hero .earnings-payout-chip{
    display: inline-flex; align-items: center; gap: .4rem;
    background: rgba(16,185,129,.08);
    border: 1px solid rgba(16,185,129,.22);
    color: #047857;
    padding: .4rem .75rem;
    border-radius: 999px;
    font-size: .78rem; font-weight: 600;
    line-height: 1.3;
    cursor: default;            /* not a button */
    box-shadow: none;
  }
  html.dark .earnings-page-hero .earnings-payout-chip{
    background: rgba(16,185,129,.14);
    border-color: rgba(16,185,129,.3);
    color: #6ee7b7;
  }

  @media (max-width: 640px){
    /* Indent the action row to align with the title baseline:
       40px icon + 12px gap (.flex.gap-3 in the partial) = 52px. */
    .earnings-page-hero .pg-hero-actions{
      padding-left: 52px;
    }
    /* Slightly tighter chip on phones so a 9-digit PromptPay tail
       fits on a 360px viewport without wrapping mid-number. */
    .earnings-page-hero .earnings-payout-chip{
      font-size: .72rem;
      padding: .3rem .6rem;
      max-width: 100%;
    }
  }

  /* Narrowest phones — drop the indent + allow word-break so a long
     PromptPay tail can wrap rather than overflow the row. */
  @media (max-width: 380px){
    .earnings-page-hero .pg-hero-actions{ padding-left: 0; }
    .earnings-page-hero .earnings-payout-chip{
      white-space: normal;
      word-break: break-word;
    }
  }
</style>
@endpush

{{-- Manual withdrawal request widget — sits ABOVE the summary cards
     so it's the first thing the photographer sees when they open the
     earnings page. Self-contained: handles the entire request flow
     plus a recent-history list with cancel actions. --}}
@include('photographer.partials.withdrawal-request-widget')

{{-- Summary Cards --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
  {{-- Total lifetime earnings --}}
  <div class="pg-card p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-gray-500 text-xs uppercase tracking-wider font-medium mb-1">รายได้สะสม</p>
        <h2 class="font-bold text-2xl tracking-tight text-gray-900">฿{{ number_format($totalEarnings ?? 0, 2) }}</h2>
        <p class="text-[11px] text-gray-400 mt-1">ยอดรวมจากทุกคำสั่งซื้อ</p>
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-500/10">
        <i class="bi bi-bar-chart-line-fill text-indigo-600"></i>
      </div>
    </div>
  </div>

  {{-- Paid out already --}}
  <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 text-white rounded-xl shadow-sm p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-emerald-100 text-xs uppercase tracking-wider font-medium mb-1">โอนเข้าบัญชีแล้ว</p>
        <h2 class="font-bold text-2xl tracking-tight">฿{{ number_format($totalPaid ?? 0, 2) }}</h2>
        <p class="text-[11px] text-emerald-100 mt-1">การจ่ายที่สำเร็จทั้งหมด</p>
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-white/20">
        <i class="bi bi-check-circle-fill"></i>
      </div>
    </div>
  </div>

  {{-- Pending --}}
  <div class="pg-card p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-gray-500 text-xs uppercase tracking-wider font-medium mb-1">รอจ่ายในรอบถัดไป</p>
        <h2 class="font-bold text-2xl tracking-tight text-amber-600">฿{{ number_format($pendingAmount ?? 0, 2) }}</h2>
        <p class="text-[11px] text-gray-400 mt-1">
          @if(empty($photographer->promptpay_number))
            ⚠️ ยังไม่ได้กรอก PromptPay
          @else
            ระบบจะโอนอัตโนมัติเมื่อถึงเกณฑ์
          @endif
        </p>
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-500/10">
        <i class="bi bi-hourglass-split text-amber-600"></i>
      </div>
    </div>
  </div>
</div>

{{-- ════════════════════════════════════════════════════════════════
     Payout schedule card — answers the #1 photographer question:
     "เงินจะเข้าวันไหน?"

     Pulls from EarningsController::buildPayoutInfo() which reads the
     admin AppSettings via PayoutEngine::loadConfig() — single source
     of truth, so changing the schedule in /admin/payouts/settings
     reflects here on the next page load.

     Three states:
       1. enabled + has PromptPay  → green card with next date + progress
       2. enabled + no PromptPay   → amber card nudging the user to add it
       3. disabled (manual mode)   → neutral card explaining how to withdraw
     ════════════════════════════════════════════════════════════════ --}}
@php
  $pi = $payoutInfo ?? null;

  // Color palette per state. Pre-computed so the markup below stays
  // readable — Tailwind classes pasted inline conditionally would
  // make the JSX-style logic dominate the visual structure.
  if (!$pi || !$pi['enabled']) {
      // Manual / disabled — neutral slate card
      $palette = [
          'gradient'  => 'from-slate-100 to-slate-50',
          'border'    => 'border-slate-200',
          'icon_bg'   => 'bg-slate-200',
          'icon_text' => 'text-slate-600',
          'pill_bg'   => 'bg-slate-200/70',
          'pill_text' => 'text-slate-700',
          'label'     => 'จ่ายแบบ manual',
      ];
  } elseif (!$pi['has_promptpay']) {
      // Action needed — amber
      $palette = [
          'gradient'  => 'from-amber-50 to-orange-50',
          'border'    => 'border-amber-200',
          'icon_bg'   => 'bg-amber-100',
          'icon_text' => 'text-amber-700',
          'pill_bg'   => 'bg-amber-100',
          'pill_text' => 'text-amber-800',
          'label'     => 'รอเพิ่ม PromptPay',
      ];
  } else {
      // Auto-payout active — green
      $palette = [
          'gradient'  => 'from-emerald-50 to-teal-50',
          'border'    => 'border-emerald-200',
          'icon_bg'   => 'bg-emerald-100',
          'icon_text' => 'text-emerald-700',
          'pill_bg'   => 'bg-emerald-100',
          'pill_text' => 'text-emerald-800',
          'label'     => 'จ่ายอัตโนมัติเปิดอยู่',
      ];
  }

  // Localised "วันถัดไป" — Thai short format: "จันทร์ 5 พ.ค. 2026"
  $nextLabel = null;
  $daysAway  = null;
  if ($pi && $pi['next_run']) {
      $nextLabel = $pi['next_run']->locale('th')->isoFormat('dddd D MMM YYYY');
      $daysAway  = (int) now('Asia/Bangkok')->startOfDay()->diffInDays($pi['next_run'], false);
  }
@endphp

<div class="rounded-2xl border {{ $palette['border'] }} bg-gradient-to-br {{ $palette['gradient'] }} p-4 md:p-5 mb-5 shadow-sm">
  <div class="flex flex-col md:flex-row md:items-stretch gap-4 md:gap-5">

    {{-- ▌Left column: schedule + status pill ▌ --}}
    <div class="flex items-start gap-3 md:flex-1 min-w-0">
      <div class="shrink-0 w-12 h-12 rounded-xl {{ $palette['icon_bg'] }} {{ $palette['icon_text'] }} flex items-center justify-center text-xl">
        <i class="bi bi-calendar-check-fill"></i>
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap mb-1">
          <h3 class="font-bold text-slate-900 text-base md:text-lg leading-tight">
            ตารางการจ่ายเงิน
          </h3>
          <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider {{ $palette['pill_bg'] }} {{ $palette['pill_text'] }} px-2 py-0.5 rounded-full">
            <i class="bi bi-circle-fill text-[7px]"></i>{{ $palette['label'] }}
          </span>
        </div>
        @if($pi)
          <p class="text-sm text-slate-700 mb-1">
            <span class="font-semibold">{{ $pi['schedule_label'] }}</span>
            @if($pi['threshold_thb'] > 0)
              <span class="text-slate-500 mx-1">·</span>
              <span class="text-slate-600">เริ่มจ่ายเมื่อยอดสะสม ≥ <span class="font-semibold text-slate-800">฿{{ number_format($pi['threshold_thb']) }}</span></span>
            @endif
          </p>
          <p class="text-xs text-slate-500 leading-relaxed">
            @if($pi['enabled'] && $pi['next_run'])
              รอบถัดไป:
              <span class="font-semibold text-slate-800">{{ $nextLabel }}</span>
              @if($daysAway === 0)
                <span class="text-emerald-700 font-semibold">(วันนี้)</span>
              @elseif($daysAway === 1)
                <span class="text-emerald-700 font-semibold">(พรุ่งนี้)</span>
              @elseif($daysAway > 0)
                <span class="text-slate-500">(อีก {{ $daysAway }} วัน)</span>
              @endif
            @elseif($pi['enabled'] && !$pi['next_run'])
              ระบบจะดำเนินการเมื่อแอดมินรันรอบจ่าย
            @else
              ระบบจ่ายเงินอัตโนมัติปิดอยู่ — ใช้ปุ่ม "ขอถอนเงิน" ในการแจ้งทีมงาน
            @endif
          </p>
          @if($pi['enabled'])
            <p class="text-[11px] text-slate-500 mt-1">
              <i class="bi bi-info-circle"></i>
              เงื่อนไขการจ่าย:
              @if($pi['trigger_logic'] === 'both')
                <span class="font-medium">ถึงวันจ่าย <strong>และ</strong> ครบยอดขั้นต่ำ</span>
              @else
                <span class="font-medium">ถึงวันจ่าย <strong>หรือ</strong> ครบยอดขั้นต่ำ</span>
              @endif
              @if($pi['delay_hours'] > 0)
                · ดีเลย์หลังลูกค้าซื้อ {{ $pi['delay_hours'] }} ชม.
              @endif
            </p>
          @endif
        @endif
      </div>
    </div>

    {{-- ▌Right column: progress to threshold ▌ --}}
    @if($pi && $pi['enabled'] && $pi['threshold_thb'] > 0)
      <div class="md:w-64 md:shrink-0 md:border-l md:border-slate-200/70 md:pl-5">
        <div class="flex items-baseline justify-between gap-2 mb-1.5">
          <span class="text-[11px] uppercase tracking-wider font-bold text-slate-500">ยอดรอจ่ายรอบนี้</span>
          <span class="text-xs font-semibold {{ $pi['threshold_met'] ? 'text-emerald-700' : 'text-slate-500' }}">
            {{ $pi['threshold_pct'] }}%
          </span>
        </div>
        <div class="flex items-baseline gap-1 mb-2">
          <span class="font-bold text-2xl text-slate-900 tracking-tight">฿{{ number_format($pi['pending_amount'], 0) }}</span>
          <span class="text-xs text-slate-400">/ ฿{{ number_format($pi['threshold_thb']) }}</span>
        </div>
        {{-- Progress bar — fills with emerald when threshold met,
             slate otherwise. Animated width transition keeps the
             visual feedback smooth on data refresh. --}}
        <div class="h-2 rounded-full bg-slate-200/70 overflow-hidden">
          <div class="h-full rounded-full transition-[width] duration-500
                      {{ $pi['threshold_met'] ? 'bg-gradient-to-r from-emerald-500 to-teal-500' : 'bg-gradient-to-r from-indigo-400 to-violet-500' }}"
               style="width: {{ max(2, $pi['threshold_pct']) }}%;"></div>
        </div>
        <p class="text-[11px] text-slate-500 mt-2 leading-snug">
          @if($pi['threshold_met'])
            <i class="bi bi-check-circle-fill text-emerald-600"></i>
            ครบยอดขั้นต่ำแล้ว — รอวันจ่ายตามรอบ
          @else
            อีก <span class="font-semibold text-slate-700">฿{{ number_format(max(0, $pi['threshold_thb'] - $pi['pending_amount']), 0) }}</span>
            ก็จะถึงยอดขั้นต่ำ
          @endif
        </p>
      </div>
    @endif
  </div>

  {{-- ▌Footer: PromptPay status / nudge ▌ --}}
  @if($pi && $pi['enabled'] && !$pi['has_promptpay'])
    <div class="mt-4 pt-4 border-t border-amber-200/70 flex items-start gap-2">
      <i class="bi bi-exclamation-triangle-fill text-amber-600 mt-0.5"></i>
      <div class="text-sm text-amber-900 flex-1">
        <strong>คุณยังไม่ได้กรอก PromptPay</strong> — ระบบจะไม่สามารถโอนเงินอัตโนมัติได้จนกว่าจะตั้งค่า
        <a href="{{ route('photographer.setup-bank') }}" class="font-semibold text-amber-700 hover:text-amber-800 underline ml-1 inline-flex items-center gap-1">
          ตั้งค่า PromptPay <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  @endif
</div>

{{-- Tab switcher (JS-free, uses URL hash) --}}
<div class="pg-card mb-3">
  <div class="py-2 px-3 flex gap-1 flex-wrap" role="tablist">
    <button type="button" data-tab-target="disbursements"
            class="tab-btn text-sm px-4 py-1.5 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-medium transition">
      <i class="bi bi-bank mr-1"></i> ประวัติการโอน
      @if($disbursements->total() > 0)
        <span class="ml-1 text-xs bg-white/25 px-1.5 py-0.5 rounded">{{ $disbursements->total() }}</span>
      @endif
    </button>
    <button type="button" data-tab-target="payouts"
            class="tab-btn text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
      <i class="bi bi-receipt mr-1"></i> รายการต่อคำสั่งซื้อ
      <span class="ml-1 text-xs bg-white/60 text-indigo-700 px-1.5 py-0.5 rounded">{{ $payouts->total() }}</span>
    </button>
  </div>
</div>

{{-- ──────────────────────────────────────────────────────── --}}
{{-- Tab 1: Disbursement history (money actually transferred)  --}}
{{-- ──────────────────────────────────────────────────────── --}}
<div data-tab-panel="disbursements" class="tab-panel pg-card overflow-hidden pg-anim d2">
  <div class="pg-card-header">
    <div>
      <h5 class="pg-section-title m-0"><i class="bi bi-bank"></i> ประวัติการโอนเงิน (Disbursements)</h5>
      <p class="text-xs text-gray-500 mt-1">แต่ละแถวคือการโอนเข้าบัญชี 1 ครั้ง รวมจากหลายคำสั่งซื้อ</p>
    </div>
  </div>
  <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
    <table class="pg-table">
      <thead>
        <tr>
          <th>วันที่</th>
          <th class="text-end">จำนวน</th>
          <th class="text-center">คำสั่งซื้อ</th>
          <th>วิธีโอน</th>
          <th>สถานะ</th>
          <th>เลขอ้างอิง</th>
        </tr>
      </thead>
      <tbody>
        @forelse($disbursements as $d)
          @php
            $statusPillMap = [
              PhotographerDisbursement::STATUS_PENDING    => ['pg-pill--gray',  'รอดำเนินการ',  'bi-clock'],
              PhotographerDisbursement::STATUS_PROCESSING => ['pg-pill--blue',  'กำลังโอน',     'bi-arrow-repeat'],
              PhotographerDisbursement::STATUS_SUCCEEDED  => ['pg-pill--green', 'โอนสำเร็จ',    'bi-check-circle-fill'],
              PhotographerDisbursement::STATUS_FAILED     => ['pg-pill--rose',  'โอนไม่สำเร็จ', 'bi-exclamation-triangle-fill'],
            ];
            $sp = $statusPillMap[$d->status] ?? $statusPillMap[PhotographerDisbursement::STATUS_PENDING];
          @endphp
          <tr>
            <td class="whitespace-nowrap">
              <div class="text-sm font-semibold text-gray-900">{{ $d->created_at?->format('d/m/Y') }}</div>
              <div class="text-xs text-gray-400">{{ $d->created_at?->format('H:i') }}</div>
            </td>
            <td class="text-end is-mono font-bold text-indigo-700 whitespace-nowrap">
              ฿{{ number_format((float) $d->amount_thb, 2) }}
            </td>
            <td class="text-center">
              <span class="pg-pill pg-pill--gray">{{ $d->payout_count }} รายการ</span>
            </td>
            <td class="text-xs uppercase is-mono text-gray-600">
              {{ $d->provider ?? '—' }}
            </td>
            <td>
              <span class="pg-pill {{ $sp[0] }}"><i class="bi {{ $sp[2] }}"></i> {{ $sp[1] }}</span>
              @if($d->status === PhotographerDisbursement::STATUS_FAILED && $d->error_message)
                <div class="text-[11px] text-rose-500 mt-1">{{ $d->error_message }}</div>
              @endif
            </td>
            <td>
              @if($d->provider_txn_id)
                <code class="text-[11px] bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-mono">{{ $d->provider_txn_id }}</code>
              @else
                <span class="text-xs text-gray-400">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6">
              <div class="pg-empty">
                <div class="pg-empty-icon"><i class="bi bi-bank"></i></div>
                <p class="font-medium">ยังไม่มีการโอนเงิน</p>
                <p class="text-xs mt-2">
                  @if(empty($photographer->promptpay_number))
                    กรอก PromptPay ที่หน้า <a href="{{ route('photographer.setup-bank') }}" class="text-indigo-600 font-bold underline">"ตั้งค่าบัญชีธนาคาร"</a> เพื่อเริ่มรับโอนอัตโนมัติ
                  @else
                    ระบบจะโอนเงินให้อัตโนมัติเมื่อถึงเกณฑ์ที่แอดมินตั้งไว้
                  @endif
                </p>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($disbursements->hasPages())
    <div class="pg-card-footer">
      {{ $disbursements->links() }}
    </div>
  @endif
</div>

{{-- ──────────────────────────────────────────────────────── --}}
{{-- Tab 2: Per-order earnings                                 --}}
{{-- ──────────────────────────────────────────────────────── --}}
<div data-tab-panel="payouts" class="tab-panel hidden pg-card overflow-hidden pg-anim d2">
  <div class="pg-card-header">
    <div>
      <h5 class="pg-section-title m-0"><i class="bi bi-receipt"></i> รายได้ต่อคำสั่งซื้อ (Earnings)</h5>
      <p class="text-xs text-gray-500 mt-1">แต่ละแถวคือยอดที่จะได้รับจาก 1 คำสั่งซื้อ</p>
    </div>
  </div>
  <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
    <table class="pg-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>คำสั่งซื้อ#</th>
          <th class="text-end">ยอดรวม</th>
          <th class="text-end">ค่าคอมฯ</th>
          <th class="text-end">รับจริง</th>
          <th>สถานะ</th>
          <th>วันที่</th>
        </tr>
      </thead>
      <tbody>
        @forelse($payouts as $payout)
          <tr>
            <td class="is-mono font-semibold">{{ $payout->id }}</td>
            <td><span class="pg-pill pg-pill--blue">#{{ $payout->order_id }}</span></td>
            <td class="text-end is-mono">{{ number_format($payout->gross_amount, 2) }}</td>
            <td class="text-end is-mono text-gray-500">{{ number_format($payout->platform_fee, 2) }}</td>
            <td class="text-end is-mono font-bold text-indigo-700">{{ number_format($payout->payout_amount, 2) }}</td>
            <td>
              @php
                $payoutPill = match($payout->status) {
                  'pending'   => ['pg-pill--amber', 'รอดำเนินการ'],
                  'requested' => ['pg-pill--blue',  'ขอถอนแล้ว'],
                  'paid'      => ['pg-pill--green', 'จ่ายแล้ว'],
                  'failed'    => ['pg-pill--rose',  'ล้มเหลว'],
                  default     => ['pg-pill--gray',  $payout->status],
                };
              @endphp
              <span class="pg-pill {{ $payoutPill[0] }}">{{ $payoutPill[1] }}</span>
            </td>
            <td class="text-gray-500 text-sm whitespace-nowrap">{{ $payout->created_at->format('d/m/Y H:i') }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="7">
              <div class="pg-empty">
                <div class="pg-empty-icon"><i class="bi bi-wallet2"></i></div>
                <p class="font-medium">ยังไม่มีรายได้</p>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($payouts->hasPages())
    <div class="pg-card-footer">
      {{ $payouts->links() }}
    </div>
  @endif
</div>

{{-- Minimal tab switcher — CSS-only would need :target selectors breaking deep-links,
     so tiny vanilla JS. Kept inline so it ships with the view, no build step. --}}
<script>
(function () {
  const btns   = document.querySelectorAll('[data-tab-target]');
  const panels = document.querySelectorAll('[data-tab-panel]');

  function activate(target) {
    btns.forEach(b => {
      const active = b.dataset.tabTarget === target;
      b.classList.toggle('bg-gradient-to-br', active);
      b.classList.toggle('from-indigo-500',   active);
      b.classList.toggle('to-indigo-600',     active);
      b.classList.toggle('text-white',        active);
      b.classList.toggle('bg-indigo-500/[0.08]', !active);
      b.classList.toggle('text-indigo-500',      !active);
    });
    panels.forEach(p => {
      p.classList.toggle('hidden', p.dataset.tabPanel !== target);
    });
  }

  btns.forEach(b => b.addEventListener('click', () => {
    activate(b.dataset.tabTarget);
    history.replaceState(null, '', '#' + b.dataset.tabTarget);
  }));

  // Honour hash on load (e.g. notification deep-link to #payouts).
  const initial = (location.hash || '#disbursements').replace('#', '');
  if (document.querySelector('[data-tab-target="' + initial + '"]')) {
    activate(initial);
  }
})();
</script>
@endsection
