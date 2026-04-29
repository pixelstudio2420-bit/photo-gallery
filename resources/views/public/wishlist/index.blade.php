@extends('layouts.app')

@section('title', 'รายการโปรด')

@section('content')
<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-pink-500 text-white shadow-md">
          <i class="bi bi-heart-fill"></i>
        </span>
        รายการโปรด
      </h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        @if($wishlists->count() > 0)
          {{ $wishlists->count() }} รายการที่คุณชื่นชอบ
        @else
          ยังไม่มีรายการโปรด
        @endif
      </p>
    </div>
  </div>

  @if($wishlists->count() > 0)
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      @foreach($wishlists as $wishlist)
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 overflow-hidden">
        @if($wishlist->event)
          <div class="p-5">
            <div class="flex items-start justify-between mb-3">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                <i class="bi bi-calendar-event"></i> อีเวนต์
              </span>
              <form action="{{ route('wishlist.toggle') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="event_id" value="{{ $wishlist->event_id }}">
                <button type="submit" class="w-8 h-8 rounded-full bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-500 hover:text-white flex items-center justify-center transition">
                  <i class="bi bi-heart-fill text-rose-500 dark:text-rose-400"></i>
                </button>
              </form>
            </div>
            <h3 class="font-semibold text-slate-900 dark:text-white mb-1 line-clamp-1">{{ $wishlist->event->name }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-3 line-clamp-2">{{ Str::limit($wishlist->event->description, 80) }}</p>
            @if($wishlist->event->shoot_date)
              <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                <i class="bi bi-calendar3"></i> {{ \Carbon\Carbon::parse($wishlist->event->shoot_date)->format('d/m/Y') }}
              </p>
            @endif
          </div>
        @elseif($wishlist->product)
          <div class="p-5">
            <div class="flex items-start justify-between mb-3">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs font-semibold">
                <i class="bi bi-box-seam"></i> สินค้าดิจิทัล
              </span>
              <form action="{{ route('wishlist.toggle') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="product_id" value="{{ $wishlist->product_id }}">
                <button type="submit" class="w-8 h-8 rounded-full bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-500 hover:text-white flex items-center justify-center transition">
                  <i class="bi bi-heart-fill text-rose-500 dark:text-rose-400"></i>
                </button>
              </form>
            </div>
            <h3 class="font-semibold text-slate-900 dark:text-white mb-1 line-clamp-1">{{ $wishlist->product->name }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-3 line-clamp-2">{{ Str::limit($wishlist->product->description, 80) }}</p>
            @if($wishlist->product->price)
              <p class="font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($wishlist->product->price, 0) }} ฿</p>
            @endif
          </div>
        @endif
      </div>
      @endforeach
    </div>
  @else
    <div class="text-center py-20 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-rose-100 to-pink-100 dark:from-rose-500/20 dark:to-pink-500/20 text-rose-500 dark:text-rose-400 mb-4">
        <i class="bi bi-heart text-3xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">ยังไม่มีรายการโปรด</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">กดรูปหัวใจเพื่อเพิ่มเข้ารายการโปรด</p>
      <a href="{{ route('events.index') }}"
         class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-md hover:shadow-lg transition-all">
        <i class="bi bi-images"></i> เลือกดูอีเวนต์
      </a>
    </div>
  @endif
</div>
@endsection
