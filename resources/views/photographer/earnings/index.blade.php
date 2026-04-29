@extends('layouts.photographer')

@section('title', 'รายได้ของฉัน')

@php
  use App\Models\PhotographerDisbursement;
@endphp

@section('content')
@php
  $earningsActions = !empty($photographer->promptpay_number)
    ? '<div class="pg-btn-ghost"><i class="bi bi-lightning-charge-fill text-emerald-500"></i> จ่ายอัตโนมัติ → PromptPay ***'.substr($photographer->promptpay_number, -4).'</div>'
    : null;
@endphp
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-wallet2',
  'eyebrow'  => 'การเงิน',
  'title'    => 'รายได้ของฉัน',
  'subtitle' => 'ภาพรวมรายได้ · ประวัติการโอน · ค่าคอมมิชชั่นที่ค้าง',
  'actions'  => $earningsActions,
])

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
