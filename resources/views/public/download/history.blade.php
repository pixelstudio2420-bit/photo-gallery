@extends('layouts.app')

@section('title', 'ประวัติดาวน์โหลด')

@section('content')
<div class="max-w-5xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
        <i class="bi bi-clock-history"></i>
      </span>
      ประวัติดาวน์โหลด
    </h1>
    <a href="{{ route('orders.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 text-sm font-medium transition">
      <i class="bi bi-receipt"></i> คำสั่งซื้อ
    </a>
  </div>

  @if($tokens->isEmpty())
    <div class="text-center py-20 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-500/20 dark:to-purple-500/20 text-indigo-500 dark:text-indigo-400 mb-4">
        <i class="bi bi-cloud-download text-3xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">ยังไม่มีประวัติดาวน์โหลด</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400">ลิงก์ดาวน์โหลดจะปรากฏที่นี่หลังจากชำระเงินสำเร็จ</p>
    </div>
  @else
    <div class="space-y-5">
    @foreach($grouped as $orderId => $orderTokens)
    @php $order = $orderTokens->first()->order; @endphp

    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      {{-- Order Header --}}
      <div class="px-5 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-500/5 dark:to-purple-500/5 border-b border-slate-200 dark:border-white/10 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-md">
            <i class="bi bi-receipt text-sm"></i>
          </div>
          <div>
            <div class="font-semibold text-sm text-slate-900 dark:text-white">
              คำสั่งซื้อ <span class="font-mono">#{{ $order->order_number ?? $orderId }}</span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $order->event->name ?? 'อีเวนต์' }}</div>
          </div>
        </div>
        <span class="text-xs text-slate-500 dark:text-slate-400">
          <i class="bi bi-calendar3 mr-1"></i>{{ $order->created_at ? $order->created_at->format('d/m/Y') : '-' }}
        </span>
      </div>

      {{-- Tokens --}}
      <div class="divide-y divide-slate-100 dark:divide-white/5">
        @foreach($orderTokens as $dl)
        @php
          $isExpired    = $dl->expires_at && $dl->expires_at->isPast();
          $limitReached = $dl->max_downloads && $dl->download_count >= $dl->max_downloads;
          $isActive     = !$isExpired && !$limitReached;
          $progress     = $dl->max_downloads ? min(100, round(($dl->download_count / $dl->max_downloads) * 100)) : 0;
          $tokenType    = $dl->photo_id ? 'single' : 'all';

          $statusMeta = $isActive
            ? ['bg' => 'bg-emerald-100 dark:bg-emerald-500/20', 'text' => 'text-emerald-700 dark:text-emerald-300', 'icon' => 'bi-check-circle-fill', 'label' => 'ใช้งานได้']
            : ($isExpired
              ? ['bg' => 'bg-rose-100 dark:bg-rose-500/20',     'text' => 'text-rose-700 dark:text-rose-300',     'icon' => 'bi-x-circle-fill', 'label' => 'หมดอายุ']
              : ['bg' => 'bg-amber-100 dark:bg-amber-500/20',   'text' => 'text-amber-700 dark:text-amber-300',   'icon' => 'bi-dash-circle-fill', 'label' => 'ครบแล้ว']);
        @endphp

        <div class="px-5 py-4 hover:bg-slate-50 dark:hover:bg-white/5 transition">
          <div class="grid grid-cols-2 md:grid-cols-5 items-center gap-3">
            {{-- Status --}}
            <div>
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full {{ $statusMeta['bg'] }} {{ $statusMeta['text'] }} text-xs font-semibold">
                <i class="bi {{ $statusMeta['icon'] }}"></i> {{ $statusMeta['label'] }}
              </span>
            </div>
            {{-- Type --}}
            <div class="text-xs text-slate-500 dark:text-slate-400">
              <i class="bi {{ $tokenType === 'all' ? 'bi-images' : 'bi-image' }} mr-1"></i>
              {{ $tokenType === 'all' ? 'ทั้งหมด' : 'รูปเดียว' }}
            </div>
            {{-- Progress --}}
            <div class="col-span-2 md:col-span-1">
              @if($dl->max_downloads)
                <div class="flex items-center gap-2">
                  <span class="text-xs font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">{{ $dl->download_count }}/{{ $dl->max_downloads }}</span>
                  <div class="flex-1 h-1.5 bg-slate-200 dark:bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full rounded-full {{ $isActive ? 'bg-gradient-to-r from-indigo-500 to-purple-600' : 'bg-slate-400' }}" style="width:{{ $progress }}%;"></div>
                  </div>
                </div>
              @else
                <span class="text-xs text-slate-500 dark:text-slate-400">โหลด {{ $dl->download_count }} ครั้ง</span>
              @endif
            </div>
            {{-- Expiry --}}
            <div class="text-xs {{ $isExpired ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400' }}">
              @if($dl->expires_at)
                <i class="bi bi-clock mr-1"></i>{{ $dl->expires_at->format('d/m/Y') }}
              @else
                <span class="text-slate-400 dark:text-slate-500">ไม่มีวันหมดอายุ</span>
              @endif
            </div>
            {{-- Action --}}
            <div class="text-right">
              @if($isActive)
                <a href="{{ route('download.show', $dl->token) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white text-xs font-semibold shadow-sm transition">
                  <i class="bi bi-download"></i> ดาวน์โหลด
                </a>
              @else
                <span class="text-xs text-slate-400 dark:text-slate-500">-</span>
              @endif
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
    @endforeach
    </div>

    @if($tokens->hasPages())
      <div class="mt-6 flex justify-center">
        {{ $tokens->withQueryString()->links() }}
      </div>
    @endif
  @endif
</div>
@endsection
