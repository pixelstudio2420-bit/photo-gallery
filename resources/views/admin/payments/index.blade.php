@extends('layouts.admin')

@section('title', 'ธุรกรรมการชำระเงิน')

{{-- =======================================================================
     PAYMENTS INDEX — LIGHT/DARK DUAL-THEME REDESIGN
     -------------------------------------------------------------------
     • Data contract unchanged: $transactions (paginated collection).
     • Filter form field names (search, status, payment_method) unchanged.
     • Tab navigation routes unchanged.
     • Replaced inline styles with Tailwind tokens + dark variants.
     • Table has zebra rows, sticky header, cleaner status/gateway pills.
     ====================================================================== --}}

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ────────── PAGE HEADER ────────── --}}
  <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div>
      <h4 class="text-2xl font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-3 tracking-tight">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl shadow-md shadow-indigo-500/30"
              style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
          <i class="bi bi-credit-card-2-front-fill text-white text-xl"></i>
        </span>
        ธุรกรรมการชำระเงิน
      </h4>
      <p class="text-sm text-slate-500 dark:text-slate-400 ml-14">
        รายการ transaction ทั้งหมดจาก payment gateway + สลิปโอน
      </p>
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════
       NAVIGATION TABS
       ══════════════════════════════════════════════════════════════ --}}
  @php
    $tabs = [
      ['url' => route('admin.payments.index'),   'icon' => 'bi-receipt',     'label' => 'ธุรกรรม',       'active' => true],
      ['url' => route('admin.payments.methods'), 'icon' => 'bi-wallet2',     'label' => 'วิธีการชำระ',  'active' => false],
      ['url' => route('admin.payments.slips'),   'icon' => 'bi-image',       'label' => 'สลิปโอน',       'active' => false],
      ['url' => route('admin.payments.banks'),   'icon' => 'bi-bank',        'label' => 'บัญชีธนาคาร', 'active' => false],
      ['url' => route('admin.payments.payouts'), 'icon' => 'bi-cash-stack',  'label' => 'การจ่ายช่างภาพ', 'active' => false],
    ];
  @endphp

  <div class="mb-5 rounded-2xl
              bg-white dark:bg-slate-900
              border border-slate-200 dark:border-white/10
              shadow-sm shadow-slate-900/5 dark:shadow-black/20
              p-2">
    <div class="flex gap-1 flex-wrap">
      @foreach($tabs as $t)
        @if($t['active'])
          <a href="{{ $t['url'] }}"
             class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl text-white
                    bg-gradient-to-br from-indigo-600 to-violet-600
                    shadow-md shadow-indigo-500/30">
            <i class="bi {{ $t['icon'] }}"></i> {{ $t['label'] }}
          </a>
        @else
          <a href="{{ $t['url'] }}"
             class="inline-flex items-center gap-1.5 text-sm font-medium px-4 py-2 rounded-xl transition
                    text-slate-700 dark:text-slate-300
                    hover:bg-slate-100 dark:hover:bg-slate-800
                    hover:text-slate-900 dark:hover:text-white">
            <i class="bi {{ $t['icon'] }}"></i> {{ $t['label'] }}
          </a>
        @endif
      @endforeach
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════
       FILTER BAR
       ══════════════════════════════════════════════════════════════ --}}
  <div class="mb-5 rounded-2xl
              bg-white dark:bg-slate-900
              border border-slate-200 dark:border-white/10
              shadow-sm shadow-slate-900/5 dark:shadow-black/20
              p-4" x-data="adminFilter()">
    <form method="GET" action="{{ route('admin.payments.index') }}"
          class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">

      {{-- Search field --}}
      <div class="md:col-span-2">
        <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wider">
          ค้นหา
        </label>
        <div class="relative">
          <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
          <input type="text" name="search" value="{{ request('search') }}"
                 placeholder="Transaction ID, เลขออเดอร์, อีเมล..."
                 class="w-full pl-9 pr-3 py-2.5 rounded-lg text-sm
                        bg-white dark:bg-slate-800
                        border border-slate-300 dark:border-white/10
                        text-slate-900 dark:text-white
                        placeholder:text-slate-400 dark:placeholder:text-slate-500
                        focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">
          <div x-show="loading" x-cloak class="absolute right-3 top-1/2 -translate-y-1/2">
            <div class="w-4 h-4 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
          </div>
        </div>
      </div>

      {{-- Status filter --}}
      <div>
        <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wider">
          สถานะ
        </label>
        <select name="status"
                class="w-full px-3 py-2.5 rounded-lg text-sm
                       bg-white dark:bg-slate-800
                       border border-slate-300 dark:border-white/10
                       text-slate-900 dark:text-white
                       focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">
          <option value="">ทุกสถานะ</option>
          <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>รอดำเนินการ</option>
          <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>สำเร็จ</option>
          <option value="failed"    {{ request('status') === 'failed'    ? 'selected' : '' }}>ล้มเหลว</option>
          <option value="refunded"  {{ request('status') === 'refunded'  ? 'selected' : '' }}>คืนเงิน</option>
        </select>
      </div>

      {{-- Payment method filter --}}
      <div>
        <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wider">
          วิธีชำระ
        </label>
        <div class="flex gap-2">
          <select name="payment_method"
                  class="flex-1 px-3 py-2.5 rounded-lg text-sm
                         bg-white dark:bg-slate-800
                         border border-slate-300 dark:border-white/10
                         text-slate-900 dark:text-white
                         focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">
            <option value="">ทุกวิธี</option>
            <option value="bank_transfer" {{ request('payment_method') === 'bank_transfer' ? 'selected' : '' }}>โอนธนาคาร</option>
            <option value="promptpay"     {{ request('payment_method') === 'promptpay'     ? 'selected' : '' }}>PromptPay</option>
            <option value="stripe"        {{ request('payment_method') === 'stripe'        ? 'selected' : '' }}>Stripe</option>
            <option value="omise"         {{ request('payment_method') === 'omise'         ? 'selected' : '' }}>Omise</option>
          </select>
          <button type="button" @click="clearFilters()"
                  class="inline-flex items-center gap-1 px-3 py-2.5 rounded-lg text-sm font-medium transition shrink-0
                         bg-slate-100 dark:bg-slate-800
                         border border-slate-200 dark:border-white/10
                         text-slate-700 dark:text-slate-300
                         hover:bg-slate-200 dark:hover:bg-slate-700"
                  title="ล้างตัวกรอง">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>
    </form>
  </div>

  {{-- ══════════════════════════════════════════════════════════════
       TABLE
       ══════════════════════════════════════════════════════════════ --}}
  <div id="admin-table-area">
    <div class="rounded-2xl overflow-hidden
                bg-white dark:bg-slate-900
                border border-slate-200 dark:border-white/10
                shadow-sm shadow-slate-900/5 dark:shadow-black/20">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-white/10">
              <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ID</th>
              <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Transaction ID</th>
              <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ออเดอร์</th>
              <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ลูกค้า</th>
              <th class="px-4 py-3 text-right text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ยอดเงิน</th>
              <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">วิธีชำระ</th>
              <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">สถานะ</th>
              <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">วันที่</th>
              <th class="px-4 py-3 text-center text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">สลิป</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-white/5">
            @forelse($transactions as $txn)
              @php
                // Status styling map — Tailwind classes with both light/dark.
                $statusMap = [
                  'completed' => ['cls' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'label' => 'สำเร็จ',     'icon' => 'bi-check-circle-fill'],
                  'pending'   => ['cls' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',         'label' => 'รอดำเนินการ', 'icon' => 'bi-hourglass-split'],
                  'failed'    => ['cls' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',             'label' => 'ล้มเหลว',    'icon' => 'bi-x-circle-fill'],
                  'refunded'  => ['cls' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',     'label' => 'คืนเงิน',    'icon' => 'bi-arrow-counterclockwise'],
                ];
                $sc = $statusMap[$txn->status ?? 'pending'] ?? [
                  'cls'   => 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300',
                  'label' => $txn->status ?? '-',
                  'icon'  => 'bi-dash-circle',
                ];
                $isSlipPayment = in_array($txn->payment_gateway ?? '', ['bank_transfer','promptpay','slip']);
              @endphp
              <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 font-mono text-[13px]">{{ $txn->id }}</td>
                <td class="px-4 py-3">
                  <code class="inline-block text-[11px] font-mono px-2 py-0.5 rounded-md
                              bg-indigo-50 dark:bg-indigo-500/10
                              text-indigo-700 dark:text-indigo-300
                              border border-indigo-200/50 dark:border-indigo-500/20">
                    {{ Str::limit($txn->transaction_id ?? '-', 18) }}
                  </code>
                </td>
                <td class="px-4 py-3">
                  @if($txn->order)
                    <span class="font-semibold text-indigo-600 dark:text-indigo-400">#{{ $txn->order->order_number }}</span>
                  @else
                    <span class="text-slate-400 dark:text-slate-500">—</span>
                  @endif
                </td>
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-900 dark:text-white text-[13px]">{{ $txn->user?->first_name ?? '-' }}</div>
                  @if($txn->user?->email)
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate max-w-[200px]">{{ $txn->user->email }}</div>
                  @endif
                </td>
                <td class="px-4 py-3 text-right">
                  <span class="font-bold text-slate-900 dark:text-white font-mono">
                    <span class="text-[11px] font-normal text-slate-400 dark:text-slate-500 mr-0.5">฿</span>{{ number_format($txn->amount, 2) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-block text-[11px] font-semibold px-2.5 py-0.5 rounded-full
                              bg-slate-100 dark:bg-slate-800
                              text-slate-700 dark:text-slate-300
                              border border-slate-200 dark:border-white/10">
                    {{ $txn->payment_gateway ?? '—' }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $sc['cls'] }}">
                    <i class="bi {{ $sc['icon'] }} text-[10px]"></i>
                    {{ $sc['label'] }}
                  </span>
                </td>
                <td class="px-4 py-3 text-[12px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                  {{ $txn->created_at?->format('d/m/Y H:i') }}
                </td>
                <td class="px-4 py-3 text-center">
                  @if($isSlipPayment && $txn->order_id)
                    <a href="{{ route('admin.payments.slips', ['search' => $txn->order?->order_number]) }}"
                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition
                              bg-indigo-50 dark:bg-indigo-500/10
                              text-indigo-600 dark:text-indigo-300
                              hover:bg-indigo-100 dark:hover:bg-indigo-500/20
                              hover:scale-110"
                       title="ดูสลิป">
                      <i class="bi bi-image text-sm"></i>
                    </a>
                  @else
                    <span class="text-slate-300 dark:text-slate-600">—</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="px-4 py-16 text-center">
                  <div class="inline-flex flex-col items-center gap-3 text-slate-400 dark:text-slate-500">
                    <span class="inline-flex items-center justify-center w-16 h-16 rounded-2xl
                                 bg-slate-100 dark:bg-slate-800/50">
                      <i class="bi bi-credit-card-2-front text-3xl"></i>
                    </span>
                    <p class="text-sm font-medium">ยังไม่มีรายการธุรกรรม</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>{{-- /#admin-table-area --}}

  @if($transactions->hasPages())
    <div id="admin-pagination-area" class="flex justify-center mt-6">
      {{ $transactions->withQueryString()->links() }}
    </div>
  @endif
</div>
@endsection
