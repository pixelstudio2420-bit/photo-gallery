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
     Earnings page hero — mobile-only proportional rebalance.

     Why a page-scoped block: the shared .pg-hero rules live in
     public/css/photographer.css and are reused on Dashboard, Events,
     Bookings, Analytics, Profile, etc. Tweaking them globally would
     ripple to those pages. Confining the rules under
     `.earnings-page-hero` keeps the change surgical to /earnings.

     Goals on phones (≤ 640px):
       1. Stack icon + text + payout chip in a clean vertical column
          (the default flex-wrap was wrapping into uneven rows).
       2. Shrink the icon (48 → 40) so the headline gets more width.
       3. Tighten title size so "รายได้ของฉัน" doesn't compete with
          the icon for breathing room.
       4. Demote the PromptPay action from button-look to a soft
          emerald status chip — it isn't actually clickable.
       5. Trim hero padding so the summary cards below sit higher in
          the first viewport (mobile users see the numbers sooner).
     ────────────────────────────────────────────────────────────────── */
  .earnings-page-hero .pg-hero{
    /* Tighter rhythm — saves ~12px so the cards rise into view. */
    padding-bottom: .9rem;
    margin-bottom: 1.1rem;
    gap: .75rem;
  }

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
    /* Stack vertically — icon+text on top, payout chip below.
       The default rule has justify-content:space-between which on
       narrow screens left a half-empty row; reset to flex-start so
       the column reads naturally. */
    .earnings-page-hero .pg-hero{
      flex-direction: column;
      align-items: flex-start;
      gap: .65rem;
      padding-bottom: .75rem;
      margin-bottom: .9rem;
    }

    /* Smaller icon — frees ~8px of horizontal headroom for the
       title without clipping the wallet glyph. */
    .earnings-page-hero .pg-hero-icon{
      width: 40px; height: 40px;
      border-radius: 11px;
      font-size: 1.15rem;
    }

    /* Title — shrink one notch so it never wraps onto two lines
       awkwardly next to the small icon. */
    .earnings-page-hero .pg-hero-title{
      font-size: 1.25rem;
      line-height: 1.25;
    }

    /* Eyebrow — let the spacing breathe a bit less. */
    .earnings-page-hero .pg-hero-eyebrow{
      font-size: .62rem;
      letter-spacing: .14em;
      margin-bottom: .15rem;
    }

    /* Subtitle — smaller, stays on one line where possible. */
    .earnings-page-hero .pg-hero-subtitle{
      font-size: .78rem;
      margin-top: .2rem;
    }

    /* Actions — full-width row below the title block, left-aligned
       with the title (not the icon) so the eye-line stays clean. */
    .earnings-page-hero .pg-hero-actions{
      width: 100%;
      padding-left: 52px;        /* 40px icon + 12px gap from .flex.gap-3 */
      margin-top: .15rem;
    }

    /* Chip itself — slightly smaller text + tighter padding so the
       full PromptPay tail fits on a 360px viewport without wrapping
       mid-number. */
    .earnings-page-hero .earnings-payout-chip{
      font-size: .72rem;
      padding: .3rem .6rem;
      max-width: 100%;
    }
  }

  /* Even narrower phones (~320–360px): drop the action indent so the
     chip can use the whole row, and let the chip text wrap if the
     PromptPay number forces it. */
  @media (max-width: 380px){
    .earnings-page-hero .pg-hero-actions{ padding-left: 0; }
    .earnings-page-hero .earnings-payout-chip{
      white-space: normal;
      word-break: break-word;
    }
  }
</style>
@endpush

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
