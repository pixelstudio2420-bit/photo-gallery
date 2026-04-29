@extends('layouts.app')

@section('title', 'แดชบอร์ดของฉัน')

@section('content')
@php
  $statusMap = [
    'pending_payment' => ['bg' => 'bg-amber-100 dark:bg-amber-500/20',     'text' => 'text-amber-700 dark:text-amber-300',     'label' => 'รอชำระ'],
    'pending_review'  => ['bg' => 'bg-blue-100 dark:bg-blue-500/20',        'text' => 'text-blue-700 dark:text-blue-300',       'label' => 'รอตรวจสอบ'],
    'paid'            => ['bg' => 'bg-emerald-100 dark:bg-emerald-500/20',  'text' => 'text-emerald-700 dark:text-emerald-300', 'label' => 'ชำระแล้ว'],
    'cancelled'       => ['bg' => 'bg-rose-100 dark:bg-rose-500/20',        'text' => 'text-rose-700 dark:text-rose-300',       'label' => 'ยกเลิก'],
  ];
@endphp

<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
        <i class="bi bi-person-circle"></i>
      </span>
      แดชบอร์ดของฉัน
    </h1>
    <a href="{{ route('profile.edit') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 text-sm font-medium transition">
      <i class="bi bi-pencil"></i> แก้ไขโปรไฟล์
    </a>
  </div>

  {{-- Tab Navigation --}}
  <div class="mb-6 flex items-center gap-1 overflow-x-auto pb-1 border-b border-slate-200 dark:border-white/10">
    @foreach([
      ['route' => route('profile'),           'label' => 'ภาพรวม',       'icon' => 'bi-grid',     'active' => true],
      ['route' => route('profile.orders'),    'label' => 'คำสั่งซื้อ',    'icon' => 'bi-receipt',  'active' => false],
      ['route' => route('profile.downloads'), 'label' => 'ดาวน์โหลด',  'icon' => 'bi-download', 'active' => false],
      ['route' => route('profile.reviews'),   'label' => 'รีวิว',         'icon' => 'bi-star',     'active' => false],
      ['route' => route('wishlist.index'),    'label' => 'รายการโปรด', 'icon' => 'bi-heart',    'active' => false],
      ['route' => route('profile.referrals'), 'label' => 'แนะนำเพื่อน',  'icon' => 'bi-people-fill','active' => false],
    ] as $tab)
      <a href="{{ $tab['route'] }}"
         class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition
            {{ $tab['active'] ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
        <i class="bi {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- ═══════════════ LEFT: Profile + Quick Menu ═══════════════ --}}
    <div class="lg:col-span-1 space-y-5">
      {{-- User info card --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="h-20 bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500"></div>
        <div class="px-5 pb-5 text-center -mt-10">
          <div class="inline-block ring-4 ring-white dark:ring-slate-800 rounded-full">
            <x-avatar :src="$user->avatar"
                 :name="$user->first_name . ' ' . $user->last_name"
                 :user-id="$user->id"
                 size="xl" />
          </div>
          <h3 class="font-bold text-slate-900 dark:text-white mt-3">{{ $user->first_name }} {{ $user->last_name }}</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $user->email }}</p>
          @if($user->phone)
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5"><i class="bi bi-telephone mr-1"></i>{{ $user->phone }}</p>
          @endif
          <div class="mt-4 pt-3 border-t border-slate-100 dark:border-white/5">
            <p class="text-xs text-slate-500 dark:text-slate-400">
              <i class="bi bi-calendar3 mr-1"></i>สมาชิกตั้งแต่ {{ $user->created_at ? $user->created_at->translatedFormat('M Y') : '-' }}
            </p>
          </div>
        </div>
      </div>

      {{-- Quick menu --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-3">
        <h3 class="px-2 mb-2 text-[11px] uppercase tracking-wide font-semibold text-slate-500 dark:text-slate-400">เมนูด่วน</h3>
        @foreach([
          ['route' => route('profile.orders'),    'icon' => 'bi-receipt',  'label' => 'คำสั่งซื้อทั้งหมด',   'count' => $orderCount,    'gradient' => 'from-indigo-500 to-purple-600', 'bg' => 'bg-indigo-100 dark:bg-indigo-500/20', 'text' => 'text-indigo-700 dark:text-indigo-300'],
          ['route' => route('profile.downloads'), 'icon' => 'bi-download', 'label' => 'ประวัติดาวน์โหลด',  'count' => $downloadCount, 'gradient' => 'from-blue-500 to-cyan-500',     'bg' => 'bg-blue-100 dark:bg-blue-500/20',    'text' => 'text-blue-700 dark:text-blue-300'],
          ['route' => route('profile.reviews'),   'icon' => 'bi-star',     'label' => 'รีวิวของฉัน',       'count' => $reviewCount,   'gradient' => 'from-amber-500 to-orange-500',  'bg' => 'bg-amber-100 dark:bg-amber-500/20',   'text' => 'text-amber-700 dark:text-amber-300'],
          ['route' => route('wishlist.index'),    'icon' => 'bi-heart',    'label' => 'รายการโปรด',      'count' => $wishlistCount, 'gradient' => 'from-rose-500 to-pink-500',     'bg' => 'bg-rose-100 dark:bg-rose-500/20',    'text' => 'text-rose-700 dark:text-rose-300'],
        ] as $item)
          <a href="{{ $item['route'] }}"
             class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-white/5 transition group">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br {{ $item['gradient'] }} text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition">
              <i class="bi {{ $item['icon'] }}"></i>
            </div>
            <span class="flex-1 font-medium text-sm text-slate-900 dark:text-white">{{ $item['label'] }}</span>
            <span class="inline-flex items-center justify-center min-w-[28px] px-2 py-0.5 rounded-full {{ $item['bg'] }} {{ $item['text'] }} text-xs font-semibold">{{ $item['count'] }}</span>
          </a>
        @endforeach
      </div>
    </div>

    {{-- ═══════════════ RIGHT: Stats + Recent ═══════════════ --}}
    <div class="lg:col-span-2 space-y-5">
      {{-- Stats --}}
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([
          ['icon' => 'bi-receipt',           'label' => 'คำสั่งซื้อ',      'value' => number_format($orderCount),    'gradient' => 'from-indigo-500 to-purple-600'],
          ['icon' => 'bi-currency-exchange', 'label' => 'ยอดใช้จ่าย (฿)',  'value' => number_format($totalSpent, 0),  'gradient' => 'from-emerald-500 to-teal-500'],
          ['icon' => 'bi-download',          'label' => 'ดาวน์โหลด',     'value' => number_format($downloadCount), 'gradient' => 'from-blue-500 to-cyan-500'],
          ['icon' => 'bi-star-fill',         'label' => 'รีวิว',            'value' => number_format($reviewCount),   'gradient' => 'from-amber-500 to-orange-500'],
        ] as $stat)
          <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-4">
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 rounded-xl bg-gradient-to-br {{ $stat['gradient'] }} text-white flex items-center justify-center shadow-md flex-shrink-0">
                <i class="bi {{ $stat['icon'] }} text-lg"></i>
              </div>
              <div class="min-w-0">
                <div class="text-xl font-bold text-slate-900 dark:text-white leading-none">{{ $stat['value'] }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $stat['label'] }}</div>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      {{-- Recent Orders --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
          <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5">
            <i class="bi bi-clock-history text-indigo-500"></i> คำสั่งซื้อล่าสุด
          </h3>
          <a href="{{ route('profile.orders') }}" class="text-xs px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 font-medium transition">
            ดูทั้งหมด
          </a>
        </div>
        @if($recentOrders->isEmpty())
          <div class="text-center py-10 px-4">
            <i class="bi bi-receipt text-4xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">ยังไม่มีคำสั่งซื้อ</p>
          </div>
        @else
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-white/5">
                <tr>
                  <th class="pl-5 pr-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">เลขที่</th>
                  <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">อีเวนต์</th>
                  <th class="px-3 py-3 text-right text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ยอด</th>
                  <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">สถานะ</th>
                  <th class="pr-5 py-3" style="width:50px;"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach($recentOrders as $order)
                  @php $sc = $statusMap[$order->status] ?? ['bg' => 'bg-slate-100 dark:bg-slate-500/20', 'text' => 'text-slate-700 dark:text-slate-300', 'label' => ucfirst($order->status)]; @endphp
                  <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition">
                    <td class="pl-5 pr-3 py-3 font-mono font-semibold text-sm text-slate-900 dark:text-white">#{{ $order->order_number ?? $order->id }}</td>
                    <td class="px-3 py-3 text-sm text-slate-700 dark:text-slate-300 truncate max-w-[200px]">{{ $order->event->name ?? '-' }}</td>
                    <td class="px-3 py-3 text-right font-semibold text-indigo-600 dark:text-indigo-400">{{ number_format($order->net_amount ?? $order->total_amount ?? 0, 0) }} ฿</td>
                    <td class="px-3 py-3">
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full {{ $sc['bg'] }} {{ $sc['text'] }} text-xs font-semibold">{{ $sc['label'] }}</span>
                    </td>
                    <td class="pr-5 py-3">
                      <a href="{{ route('orders.show', $order->id) }}"
                         class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500 hover:text-white transition">
                        <i class="bi bi-eye text-xs"></i>
                      </a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      {{-- Recent Downloads --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
          <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5">
            <i class="bi bi-download text-blue-500"></i> ดาวน์โหลดล่าสุด
          </h3>
          <a href="{{ route('profile.downloads') }}" class="text-xs px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-500/20 font-medium transition">
            ดูทั้งหมด
          </a>
        </div>
        <div class="p-5">
          @if($recentDownloads->isEmpty())
            <div class="text-center py-8">
              <i class="bi bi-cloud-download text-4xl text-slate-300 dark:text-slate-600"></i>
              <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">ยังไม่มีประวัติดาวน์โหลด</p>
            </div>
          @else
            <div class="space-y-2">
              @foreach($recentDownloads as $dl)
              @php
                $isExpired = $dl->expires_at && $dl->expires_at->isPast();
                $limitReached = $dl->max_downloads && $dl->download_count >= $dl->max_downloads;
                $isActive = !$isExpired && !$limitReached;
              @endphp
              <div class="flex items-center gap-3 p-3 rounded-xl {{ $isActive ? 'bg-blue-50 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-500/20' : 'bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-white/5' }}">
                <div class="w-10 h-10 rounded-xl {{ $isActive ? 'bg-gradient-to-br from-blue-500 to-cyan-500' : 'bg-slate-400 dark:bg-slate-600' }} text-white flex items-center justify-center shadow-sm flex-shrink-0">
                  <i class="bi bi-image"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-sm truncate {{ $isActive ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400' }}">
                    {{ $dl->order->event->name ?? 'อีเวนต์' }}
                  </div>
                  <div class="flex items-center gap-3 mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    <span><i class="bi bi-download mr-1"></i>{{ $dl->download_count }}/{{ $dl->max_downloads ?? '∞' }}</span>
                    @if($dl->expires_at)
                      <span class="{{ $isExpired ? 'text-rose-600 dark:text-rose-400' : '' }}"><i class="bi bi-clock mr-1"></i>{{ $dl->expires_at->format('d/m/Y') }}</span>
                    @endif
                  </div>
                </div>
                @if($isActive)
                  <a href="{{ route('download.show', $dl->token) }}"
                     class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 text-white text-xs font-semibold shadow-sm transition flex-shrink-0">
                    <i class="bi bi-download"></i> ดาวน์โหลด
                  </a>
                @else
                  <span class="inline-flex items-center px-2 py-1 rounded-lg bg-slate-200 dark:bg-white/5 text-slate-500 dark:text-slate-400 text-xs font-medium flex-shrink-0">{{ $isExpired ? 'หมดอายุ' : 'ครบแล้ว' }}</span>
                @endif
              </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
