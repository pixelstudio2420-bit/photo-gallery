@extends('layouts.admin')

@section('title', 'จัดการคำสั่งซื้อ')

@section('content')

@php
  use App\Models\Order;
  $statTotal      = Order::count();
  $statPaid       = Order::where('status','paid')->count();
  $statPending    = Order::whereIn('status',['pending_payment','pending_review'])->count();
  $statCancelled  = Order::where('status','cancelled')->count();
  $todayRevenue   = Order::where('status','paid')->whereDate('created_at', today())->sum('total');

  $statCards = [
    ['href' => route('admin.orders.index'),                      'icon' => 'bi-bag',              'label' => 'ทั้งหมด',    'value' => number_format($statTotal),      'accent' => 'indigo',  'iconClass' => 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-300'],
    ['href' => route('admin.orders.index', ['status'=>'paid']),  'icon' => 'bi-check-circle',     'label' => 'ชำระแล้ว',   'value' => number_format($statPaid),       'accent' => 'emerald', 'iconClass' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300'],
    ['href' => route('admin.orders.index', ['status'=>'pending_payment']), 'icon' => 'bi-clock-history', 'label' => 'รอดำเนินการ','value' => number_format($statPending), 'accent' => 'amber',   'iconClass' => 'bg-amber-500/15 text-amber-600 dark:text-amber-300'],
    ['href' => route('admin.orders.index', ['status'=>'cancelled']), 'icon' => 'bi-x-circle',    'label' => 'ยกเลิก',     'value' => number_format($statCancelled),  'accent' => 'rose',    'iconClass' => 'bg-rose-500/15 text-rose-600 dark:text-rose-300'],
    ['href' => null,                                              'icon' => 'bi-currency-exchange','label' => 'รายได้วันนี้','value' => '฿'.number_format($todayRevenue, 0), 'accent' => 'teal', 'iconClass' => 'bg-teal-500/15 text-teal-600 dark:text-teal-300'],
  ];
@endphp

{{-- Page Header --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div class="flex items-center gap-3">
    <div class="h-11 w-11 rounded-2xl bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 flex items-center justify-center">
      <i class="bi bi-receipt text-xl"></i>
    </div>
    <div>
      <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">คำสั่งซื้อ</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400">ติดตามและจัดการออเดอร์ลูกค้า</p>
    </div>
  </div>
  <a href="{{ route('admin.orders.export') }}"
     class="inline-flex items-center gap-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 px-4 py-2 text-sm font-medium transition-colors">
    <i class="bi bi-download"></i>
    <span>Export CSV</span>
  </a>
</div>

{{-- Summary Stat Cards --}}
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mb-6">
  @foreach($statCards as $card)
    @php $isLink = !empty($card['href']); @endphp
    <{{ $isLink ? 'a' : 'div' }}
      @if($isLink) href="{{ $card['href'] }}" @endif
      class="group rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 p-4 shadow-sm @if($isLink) hover:border-{{ $card['accent'] }}-400 dark:hover:border-{{ $card['accent'] }}-500/40 hover:shadow-md transition-all @endif">
      <div class="flex items-center gap-3">
        <div class="h-11 w-11 rounded-xl flex items-center justify-center shrink-0 {{ $card['iconClass'] }}">
          <i class="bi {{ $card['icon'] }} text-lg"></i>
        </div>
        <div class="min-w-0">
          <div class="text-xl font-bold leading-tight text-slate-900 dark:text-slate-100 truncate">{{ $card['value'] }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $card['label'] }}</div>
        </div>
      </div>
    </{{ $isLink ? 'a' : 'div' }}>
  @endforeach
</div>

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.orders.index') }}">
    <div class="af-grid">

      {{-- Search field (span 2 cols) --}}
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="ชื่อลูกค้า, อีเมล, เลขออเดอร์..." value="{{ request('q') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>

      {{-- Status filter --}}
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="cart"           {{ request('status') === 'cart'           ? 'selected' : '' }}>ตะกร้า</option>
          <option value="pending_payment" {{ request('status') === 'pending_payment' ? 'selected' : '' }}>รอชำระ</option>
          <option value="pending_review"  {{ request('status') === 'pending_review'  ? 'selected' : '' }}>รอตรวจสอบ</option>
          <option value="paid"           {{ request('status') === 'paid'           ? 'selected' : '' }}>ชำระแล้ว</option>
          <option value="cancelled"      {{ request('status') === 'cancelled'      ? 'selected' : '' }}>ยกเลิก</option>
          <option value="refunded"       {{ request('status') === 'refunded'       ? 'selected' : '' }}>คืนเงิน</option>
        </select>
      </div>

      {{-- Date from --}}
      <div>
        <label class="af-label">ตั้งแต่</label>
        <input type="date" name="from" class="af-input" value="{{ request('from') }}">
      </div>

      {{-- Date to --}}
      <div>
        <label class="af-label">ถึงวันที่</label>
        <input type="date" name="to" class="af-input" value="{{ request('to') }}">
      </div>

      {{-- Actions --}}
      <div class="af-actions">
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>

    </div>
  </form>
</div>

{{-- Orders Table --}}
<div id="admin-table-area">
  <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-white/10">
          <tr class="text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <th class="px-5 py-3">เลขออเดอร์</th>
            <th class="px-4 py-3">ลูกค้า</th>
            <th class="px-4 py-3">รายการ</th>
            <th class="px-4 py-3">ยอดรวม</th>
            <th class="px-4 py-3">สถานะ</th>
            <th class="px-4 py-3">วันที่</th>
            <th class="px-4 py-3 w-14"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
          @forelse($orders as $order)
          @php
            $statusConfig = match($order->status) {
              'paid'             => ['tone' => 'emerald', 'label' => 'ชำระแล้ว'],
              'pending_payment'  => ['tone' => 'amber',   'label' => 'รอชำระ'],
              'pending_review'   => ['tone' => 'sky',     'label' => 'รอตรวจสอบ'],
              'cancelled'        => ['tone' => 'rose',    'label' => 'ยกเลิก'],
              'refunded'         => ['tone' => 'violet',  'label' => 'คืนเงิน'],
              'cart'             => ['tone' => 'slate',   'label' => 'ตะกร้า'],
              default            => ['tone' => 'slate',   'label' => ucfirst($order->status)],
            };

            $toneMap = [
              'emerald' => ['pill' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'dot' => 'bg-emerald-500'],
              'amber'   => ['pill' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',         'dot' => 'bg-amber-500'],
              'sky'     => ['pill' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',                 'dot' => 'bg-sky-500'],
              'rose'    => ['pill' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',             'dot' => 'bg-rose-500'],
              'violet'  => ['pill' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',     'dot' => 'bg-violet-500'],
              'slate'   => ['pill' => 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300',        'dot' => 'bg-slate-400'],
            ];
            $tone = $toneMap[$statusConfig['tone']];

            $firstName = $order->user->first_name ?? 'U';
            $lastName  = $order->user->last_name ?? '';
          @endphp
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
            {{-- Order Number --}}
            <td class="px-5 py-3">
              <a href="{{ route('admin.orders.show', $order->id) }}"
                 class="inline-flex items-center rounded-md bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 px-2 py-1 text-xs font-semibold font-mono hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors">
                {{ $order->order_number ?? '#' . $order->id }}
              </a>
            </td>
            {{-- Customer --}}
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <x-avatar :src="$order->user->avatar ?? null"
                         :name="trim($firstName . ' ' . $lastName)"
                         :user-id="$order->user_id"
                         size="md" />
                <div class="min-w-0">
                  <div class="text-sm font-semibold text-slate-900 dark:text-slate-100 leading-tight truncate">
                    {{ trim($firstName . ' ' . $lastName) ?: 'ไม่ระบุ' }}
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 truncate">
                    {{ $order->user->email ?? '' }}
                  </div>
                </div>
              </div>
            </td>
            {{-- Items Count --}}
            <td class="px-4 py-3">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 px-2.5 py-1 text-xs font-semibold">
                <i class="bi bi-image text-[0.7rem]"></i>
                {{ $order->items_count ?? 0 }} รูป
              </span>
            </td>
            {{-- Total --}}
            <td class="px-4 py-3">
              <span class="font-bold text-slate-900 dark:text-slate-100">
                ฿{{ number_format($order->total, 0) }}
              </span>
            </td>
            {{-- Status Badge --}}
            <td class="px-4 py-3">
              <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap {{ $tone['pill'] }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $tone['dot'] }}"></span>
                {{ $statusConfig['label'] }}
              </span>
            </td>
            {{-- Date --}}
            <td class="px-4 py-3">
              <div class="text-sm text-slate-700 dark:text-slate-200">{{ $order->created_at->format('d/m/Y') }}</div>
              <div class="text-xs text-slate-400 dark:text-slate-500">{{ $order->created_at->format('H:i') }}</div>
            </td>
            {{-- Action Dropdown --}}
            <td class="px-4 py-3">
              <div x-data="{ open: false }" class="relative">
                <button @click="open = !open"
                        class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"
                        aria-label="Actions">
                  <i class="bi bi-three-dots-vertical"></i>
                </button>
                <div x-show="open" @click.away="open = false" x-cloak x-transition
                     class="absolute right-0 z-10 mt-1 w-44 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 shadow-lg py-1 text-sm">
                  <a class="flex items-center gap-2 px-3 py-2 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                     href="{{ route('admin.orders.show', $order->id) }}">
                    <i class="bi bi-eye text-indigo-500 w-4"></i>
                    ดูรายละเอียด
                  </a>
                  @if($order->status === 'pending_payment' || $order->status === 'pending_review')
                  <a class="flex items-center gap-2 px-3 py-2 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/60" href="#"
                     onclick="event.preventDefault(); if(confirm('ยืนยันเปลี่ยนสถานะเป็น ชำระแล้ว?')) document.getElementById('markPaid{{ $order->id }}').submit();">
                    <i class="bi bi-check-circle text-emerald-500 w-4"></i>
                    ทำเครื่องหมายชำระแล้ว
                  </a>
                  @endif
                  @if($order->status !== 'cancelled' && $order->status !== 'refunded')
                  <div class="my-1 border-t border-slate-100 dark:border-white/5"></div>
                  <a class="flex items-center gap-2 px-3 py-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10" href="#"
                     onclick="event.preventDefault(); if(confirm('ยืนยันยกเลิกออเดอร์นี้?')) document.getElementById('cancelOrder{{ $order->id }}').submit();">
                    <i class="bi bi-x-circle w-4"></i>
                    ยกเลิกออเดอร์
                  </a>
                  @endif
                </div>
              </div>
              {{-- Hidden forms for status updates --}}
              @if($order->status === 'pending_payment' || $order->status === 'pending_review')
              <form id="markPaid{{ $order->id }}" method="POST" action="{{ route('admin.orders.update', $order->id) }}" class="hidden">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="paid">
              </form>
              @endif
              @if($order->status !== 'cancelled' && $order->status !== 'refunded')
              <form id="cancelOrder{{ $order->id }}" method="POST" action="{{ route('admin.orders.update', $order->id) }}" class="hidden">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="cancelled">
              </form>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="px-4 py-16 text-center">
              <div class="flex flex-col items-center gap-3">
                <div class="h-14 w-14 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 flex items-center justify-center">
                  <i class="bi bi-bag-x text-2xl"></i>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-300 font-medium">ไม่พบคำสั่งซื้อ</p>
                @if(request()->hasAny(['q','status','from','to']))
                <a href="{{ route('admin.orders.index') }}"
                   class="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 px-3 py-1.5 text-xs font-medium hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors">
                  <i class="bi bi-x-circle"></i>
                  ล้างตัวกรอง
                </a>
                @endif
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    <div id="admin-pagination-area">
      @if($orders->hasPages())
      <div class="px-5 py-3 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-slate-800/30">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="text-xs text-slate-500 dark:text-slate-400">
            แสดง <strong class="text-slate-700 dark:text-slate-200">{{ $orders->firstItem() }}</strong>–<strong class="text-slate-700 dark:text-slate-200">{{ $orders->lastItem() }}</strong>
            จาก <strong class="text-slate-700 dark:text-slate-200">{{ number_format($orders->total()) }}</strong> รายการ
          </div>
          <nav>
            <ul class="flex items-center gap-1">
              {{-- Previous --}}
              @if($orders->onFirstPage())
              <li>
                <span class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-200 dark:border-white/10 text-slate-300 dark:text-slate-600 cursor-not-allowed">
                  <i class="bi bi-chevron-left text-xs"></i>
                </span>
              </li>
              @else
              <li>
                <a href="{{ $orders->withQueryString()->previousPageUrl() }}"
                   class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                  <i class="bi bi-chevron-left text-xs"></i>
                </a>
              </li>
              @endif

              {{-- Page Numbers --}}
              @foreach($orders->withQueryString()->getUrlRange(max(1,$orders->currentPage()-2), min($orders->lastPage(),$orders->currentPage()+2)) as $page => $url)
              <li>
                @if($page == $orders->currentPage())
                <span class="inline-flex items-center justify-center h-8 min-w-8 px-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold">{{ $page }}</span>
                @else
                <a href="{{ $url }}"
                   class="inline-flex items-center justify-center h-8 min-w-8 px-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs transition-colors">
                  {{ $page }}
                </a>
                @endif
              </li>
              @endforeach

              {{-- Next --}}
              @if($orders->hasMorePages())
              <li>
                <a href="{{ $orders->withQueryString()->nextPageUrl() }}"
                   class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                  <i class="bi bi-chevron-right text-xs"></i>
                </a>
              </li>
              @else
              <li>
                <span class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-200 dark:border-white/10 text-slate-300 dark:text-slate-600 cursor-not-allowed">
                  <i class="bi bi-chevron-right text-xs"></i>
                </span>
              </li>
              @endif
            </ul>
          </nav>
        </div>
      </div>
      @endif
    </div>{{-- end #admin-pagination-area --}}
  </div>
</div>{{-- end #admin-table-area --}}

@endsection
