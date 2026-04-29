@extends('layouts.admin')

@section('title', 'รายการธุรกรรม')

@section('content')

{{-- Page Header --}}
<div class="flex justify-between items-center mb-6">
  <h4 class="font-bold text-xl tracking-tight">
    <i class="bi bi-arrow-left-right mr-2 text-indigo-500"></i>รายการธุรกรรม
  </h4>
  <div class="flex gap-2">
    <a href="{{ route('admin.finance.index') }}" class="text-sm px-4 py-2 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
      <i class="bi bi-arrow-left mr-1"></i>กลับ
    </a>
  </div>
</div>

{{-- Summary Stat Cards --}}
@php
  use App\Models\PaymentTransaction;
  $statTotalCount  = PaymentTransaction::count();
  $statCompleted   = PaymentTransaction::where('status','completed')->sum('amount');
  $statPendingCount = PaymentTransaction::whereIn('status',['pending','processing'])->count();
  $statFailedCount  = PaymentTransaction::where('status','failed')->count();
  $statRefundedAmt  = PaymentTransaction::where('status','refunded')->sum('amount');
@endphp
<div class="grid grid-cols-2 xl:grid-cols-5 gap-3 mb-6">
  {{-- Total Transactions --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="py-3 px-4">
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center flex-shrink-0 w-11 h-11 rounded-xl bg-indigo-500/10">
          <i class="bi bi-list-ul text-lg text-indigo-500"></i>
        </div>
        <div>
          <div class="font-bold text-2xl leading-tight text-slate-800">{{ number_format($statTotalCount) }}</div>
          <div class="text-gray-500 text-xs mt-0.5">ธุรกรรมทั้งหมด</div>
        </div>
      </div>
    </div>
  </div>
  {{-- Completed Amount --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="py-3 px-4">
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center flex-shrink-0 w-11 h-11 rounded-xl bg-green-500/10">
          <i class="bi bi-check-circle-fill text-lg text-green-500"></i>
        </div>
        <div>
          <div class="font-bold text-xl leading-tight text-green-500">฿{{ number_format($statCompleted, 0) }}</div>
          <div class="text-gray-500 text-xs mt-0.5">ยอดสำเร็จ</div>
        </div>
      </div>
    </div>
  </div>
  {{-- Pending / Processing --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="py-3 px-4">
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center flex-shrink-0 w-11 h-11 rounded-xl bg-amber-500/10">
          <i class="bi bi-hourglass-split text-lg text-amber-500"></i>
        </div>
        <div>
          <div class="font-bold text-2xl leading-tight text-slate-800">{{ number_format($statPendingCount) }}</div>
          <div class="text-gray-500 text-xs mt-0.5">รอดำเนินการ</div>
        </div>
      </div>
    </div>
  </div>
  {{-- Failed --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="py-3 px-4">
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center flex-shrink-0 w-11 h-11 rounded-xl bg-red-500/10">
          <i class="bi bi-x-circle-fill text-lg text-red-500"></i>
        </div>
        <div>
          <div class="font-bold text-2xl leading-tight text-slate-800">{{ number_format($statFailedCount) }}</div>
          <div class="text-gray-500 text-xs mt-0.5">ล้มเหลว</div>
        </div>
      </div>
    </div>
  </div>
  {{-- Refunded --}}
  <div class="col-span-2 xl:col-span-1 bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="py-3 px-4">
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center flex-shrink-0 w-11 h-11 rounded-xl bg-violet-500/10">
          <i class="bi bi-arrow-counterclockwise text-lg text-violet-500"></i>
        </div>
        <div>
          <div class="font-bold text-xl leading-tight text-violet-500">฿{{ number_format($statRefundedAmt, 0) }}</div>
          <div class="text-gray-500 text-xs mt-0.5">ยอดคืนเงิน</div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.finance.transactions') }}">
    <div class="af-grid">

      {{-- Search field (span 2 cols) --}}
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="TXN ID, เลขออเดอร์, อีเมล..." value="{{ request('q') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>

      {{-- Status --}}
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="pending"     {{ request('status') === 'pending'     ? 'selected' : '' }}>รอดำเนินการ</option>
          <option value="processing"  {{ request('status') === 'processing'  ? 'selected' : '' }}>กำลังดำเนินการ</option>
          <option value="completed"   {{ request('status') === 'completed'   ? 'selected' : '' }}>สำเร็จ</option>
          <option value="failed"      {{ request('status') === 'failed'      ? 'selected' : '' }}>ล้มเหลว</option>
          <option value="refunded"    {{ request('status') === 'refunded'    ? 'selected' : '' }}>คืนเงินแล้ว</option>
        </select>
      </div>

      {{-- Payment Gateway --}}
      <div>
        <label class="af-label">ช่องทางชำระ</label>
        <select name="gateway" class="af-input">
          <option value="">ทุกช่องทาง</option>
          <option value="promptpay"    {{ request('gateway') === 'promptpay'    ? 'selected' : '' }}>PromptPay</option>
          <option value="bank_transfer" {{ request('gateway') === 'bank_transfer' ? 'selected' : '' }}>โอนธนาคาร</option>
          <option value="stripe"       {{ request('gateway') === 'stripe'       ? 'selected' : '' }}>Stripe</option>
          <option value="truemoney"    {{ request('gateway') === 'truemoney'    ? 'selected' : '' }}>TrueMoney</option>
          <option value="line_pay"     {{ request('gateway') === 'line_pay'     ? 'selected' : '' }}>LINE Pay</option>
          <option value="omise"        {{ request('gateway') === 'omise'        ? 'selected' : '' }}>Omise</option>
          <option value="2c2p"         {{ request('gateway') === '2c2p'         ? 'selected' : '' }}>2C2P</option>
          <option value="manual"       {{ request('gateway') === 'manual'       ? 'selected' : '' }}>Manual</option>
        </select>
      </div>

      {{-- Date From --}}
      <div>
        <label class="af-label">ตั้งแต่</label>
        <input type="date" name="from" class="af-input" value="{{ request('from') }}">
      </div>

      {{-- Date To --}}
      <div>
        <label class="af-label">ถึง</label>
        <input type="date" name="to" class="af-input" value="{{ request('to') }}">
      </div>

      {{-- Actions --}}
      <div class="af-actions">
        <div class="af-spinner" x-show="loading" x-cloak></div>
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>

    </div>
  </form>
</div>

{{-- Transactions Table --}}
<div id="admin-table-area">
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  @if($transactions->count() > 0)
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-indigo-500/[0.04] border-b border-indigo-500/[0.08]">
        <tr>
          <th class="pl-5 px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Transaction ID</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">คำสั่งซื้อ</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">ลูกค้า</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">ช่องทาง</th>
          <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">จำนวนเงิน</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">สถานะ</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">วันที่</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @foreach($transactions as $txn)
        @php
          $statusMap = [
            'completed' => ['bg'=>'rgba(34,197,94,0.1)', 'color'=>'#22c55e', 'dot'=>'#22c55e', 'label'=>'สำเร็จ'],
            'pending'  => ['bg'=>'rgba(245,158,11,0.1)','color'=>'#f59e0b', 'dot'=>'#f59e0b', 'label'=>'รอดำเนินการ'],
            'processing' => ['bg'=>'rgba(59,130,246,0.1)','color'=>'#3b82f6', 'dot'=>'#3b82f6', 'label'=>'กำลังดำเนินการ'],
            'failed'   => ['bg'=>'rgba(239,68,68,0.1)', 'color'=>'#ef4444', 'dot'=>'#ef4444', 'label'=>'ล้มเหลว'],
            'refunded'  => ['bg'=>'rgba(139,92,246,0.1)','color'=>'#8b5cf6', 'dot'=>'#8b5cf6', 'label'=>'คืนเงินแล้ว'],
          ];
          $sc = $statusMap[$txn->status] ?? ['bg'=>'rgba(100,116,139,0.1)','color'=>'#64748b','dot'=>'#94a3b8','label'=>$txn->status];

          $gwMap = [
            'promptpay'   => ['label'=>'PromptPay',  'icon'=>'bi-qr-code',    'color'=>'#0d47a1'],
            'bank_transfer' => ['label'=>'โอนธนาคาร', 'icon'=>'bi-bank',      'color'=>'#1565c0'],
            'stripe'    => ['label'=>'Stripe',   'icon'=>'bi-credit-card',   'color'=>'#635bff'],
            'truemoney'   => ['label'=>'TrueMoney',  'icon'=>'bi-wallet2',     'color'=>'#ff6b00'],
            'line_pay'   => ['label'=>'LINE Pay',  'icon'=>'bi-chat-dots-fill', 'color'=>'#00c300'],
            'omise'     => ['label'=>'Omise',    'icon'=>'bi-credit-card-2-front','color'=>'#1a56db'],
            '2c2p'     => ['label'=>'2C2P',    'icon'=>'bi-shield-check',  'color'=>'#e11d48'],
            'manual'    => ['label'=>'Manual',   'icon'=>'bi-person-check',  'color'=>'#64748b'],
          ];
          $gw = $gwMap[$txn->payment_gateway] ?? ['label'=>($txn->payment_gateway ?? '-'),'icon'=>'bi-credit-card','color'=>'#64748b'];

          $firstName = $txn->user->first_name ?? '';
          $lastName = $txn->user->last_name ?? '';
        @endphp
        <tr class="hover:bg-indigo-500/[0.02] transition">
          {{-- Transaction ID --}}
          <td class="pl-5 px-4 py-3">
            <code class="font-mono text-xs text-slate-600 bg-indigo-500/[0.05] px-2 py-1 rounded inline-block max-w-[180px] overflow-hidden text-ellipsis whitespace-nowrap" title="{{ $txn->transaction_id }}">
              {{ $txn->transaction_id ?? '-' }}
            </code>
          </td>
          {{-- Order Link --}}
          <td class="px-4 py-3">
            @if($txn->order)
            <a href="{{ route('admin.orders.show', $txn->order_id) }}" class="no-underline">
              <span class="inline-flex items-center gap-1 bg-indigo-500/[0.08] text-indigo-500 rounded-lg px-2.5 py-1 text-xs font-semibold">
                <i class="bi bi-receipt text-[0.72rem]"></i>
                {{ $txn->order->order_number ?? '#'.$txn->order_id }}
              </span>
            </a>
            @else
            <span class="text-gray-500 text-sm">#{{ $txn->order_id }}</span>
            @endif
          </td>
          {{-- Customer --}}
          <td class="px-4 py-3">
            @if($txn->user)
            <div class="flex items-center gap-2">
              <x-avatar :src="$txn->user->avatar ?? null"
                   :name="trim($firstName . ' ' . $lastName)"
                   :user-id="$txn->user_id"
                   size="sm" />
              <div>
                <div class="text-sm font-semibold text-slate-800 leading-tight">
                  {{ trim($firstName . ' ' . $lastName) ?: 'ไม่ระบุ' }}
                </div>
                <div class="text-xs text-gray-400 leading-tight">
                  {{ $txn->user->email ?? '' }}
                </div>
              </div>
            </div>
            @else
            <span class="text-gray-500 text-sm">-</span>
            @endif
          </td>
          {{-- Gateway Badge --}}
          <td class="px-4 py-3">
            <span class="inline-flex items-center gap-1 bg-black/[0.04] rounded-lg px-3 py-1 text-xs font-semibold" style="color:{{ $gw['color'] }};">
              <i class="bi {{ $gw['icon'] }} text-xs"></i>
              {{ $gw['label'] }}
            </span>
          </td>
          {{-- Amount --}}
          <td class="px-4 py-3 text-right">
            <span class="font-bold text-base" style="color:{{ $txn->status === 'completed' ? '#22c55e' : '#1e293b' }};">
              ฿{{ number_format($txn->amount, 2) }}
            </span>
          </td>
          {{-- Status Badge --}}
          <td class="px-4 py-3">
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold whitespace-nowrap" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};">
              <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:{{ $sc['dot'] }};"></span>
              {{ $sc['label'] }}
            </span>
          </td>
          {{-- Date --}}
          <td class="px-4 py-3">
            <div class="text-sm text-slate-600">{{ $txn->created_at->format('d/m/Y') }}</div>
            <div class="text-xs text-gray-400">{{ $txn->created_at->format('H:i') }}</div>
            @if($txn->paid_at)
            <div class="text-[0.7rem] text-green-500 mt-0.5">
              <i class="bi bi-check2 text-[0.7rem]"></i> {{ $txn->paid_at->format('d/m H:i') }}
            </div>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- Pagination --}}
  @if($transactions->hasPages())
  <div id="admin-pagination-area">
  <div class="bg-white border-t border-gray-100 py-3 px-5">
    <div class="flex flex-wrap justify-between items-center gap-2">
      <div class="text-gray-500 text-sm">
        แสดง <strong>{{ $transactions->firstItem() }}</strong>-<strong>{{ $transactions->lastItem() }}</strong>
        จาก <strong>{{ number_format($transactions->total()) }}</strong> ธุรกรรม
      </div>
      <nav aria-label="Transactions pagination">
        <ul class="flex gap-1">
          {{-- Previous --}}
          @if($transactions->onFirstPage())
          <li>
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 text-gray-400">
              <i class="bi bi-chevron-left text-xs"></i>
            </span>
          </li>
          @else
          <li>
            <a class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 text-slate-600 hover:border-indigo-500 hover:text-indigo-500 transition" href="{{ $transactions->withQueryString()->previousPageUrl() }}">
              <i class="bi bi-chevron-left text-xs"></i>
            </a>
          </li>
          @endif

          {{-- Page Numbers --}}
          @foreach($transactions->withQueryString()->getUrlRange(max(1,$transactions->currentPage()-2), min($transactions->lastPage(),$transactions->currentPage()+2)) as $page => $url)
          <li>
            <a class="inline-flex items-center justify-center w-9 h-9 rounded-lg border text-sm transition {{ $page == $transactions->currentPage() ? 'border-indigo-500 bg-indigo-500 text-white' : 'border-gray-200 text-slate-600 hover:border-indigo-500 hover:text-indigo-500' }}" href="{{ $url }}">
              {{ $page }}
            </a>
          </li>
          @endforeach

          {{-- Next --}}
          @if($transactions->hasMorePages())
          <li>
            <a class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 text-slate-600 hover:border-indigo-500 hover:text-indigo-500 transition" href="{{ $transactions->withQueryString()->nextPageUrl() }}">
              <i class="bi bi-chevron-right text-xs"></i>
            </a>
          </li>
          @else
          <li>
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 text-gray-400">
              <i class="bi bi-chevron-right text-xs"></i>
            </span>
          </li>
          @endif
        </ul>
      </nav>
    </div>
  </div>
  </div>{{-- end admin-pagination-area --}}
  @endif

  @else
  {{-- Empty State --}}
  <div class="p-12 text-center">
    <div class="text-gray-300">
      <i class="bi bi-arrow-left-right text-5xl block mb-3"></i>
    </div>
    <p class="font-semibold text-slate-600">ยังไม่มีรายการธุรกรรม</p>
    <p class="text-gray-500 text-sm">รายการชำระเงินจะปรากฏที่นี่</p>
  </div>
  @endif
</div>
</div>{{-- end admin-table-area --}}

@push('styles')
<style>
table tbody tr:hover { background: rgba(99,102,241,0.025) !important; }
</style>
@endpush

@endsection
