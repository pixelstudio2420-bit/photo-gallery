@extends('layouts.app')

@section('title', ($share->title ?: 'รายการโปรดที่แชร์') . ' - ' . ($owner?->full_name ?? 'ลูกค้า'))
@section('meta_description', $share->description ? Str::limit($share->description, 150) : 'รายการโปรดที่แชร์โดย ' . ($owner?->full_name ?? 'ลูกค้า'))

@section('content')
<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="rounded-3xl bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 dark:from-indigo-700 dark:via-purple-700 dark:to-pink-700 shadow-xl p-6 md:p-8 mb-6 text-white relative overflow-hidden">
    <div class="absolute inset-0 opacity-30 pointer-events-none">
      <div class="absolute -top-16 -right-16 w-64 h-64 bg-pink-400/30 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-10 -left-10 w-56 h-56 bg-indigo-400/30 rounded-full blur-3xl"></div>
    </div>
    <div class="relative">
      <div class="flex items-center gap-2 mb-3">
        <i class="bi bi-heart-fill text-white text-2xl drop-shadow"></i>
        <span class="text-xs font-semibold uppercase tracking-wider text-white/80">รายการโปรดที่แชร์</span>
      </div>
      <h1 class="text-2xl md:text-4xl font-bold mb-2 tracking-tight">
        {{ $share->title ?: 'รายการโปรดของ ' . ($owner?->first_name ?? 'ลูกค้า') }}
      </h1>
      @if($share->description)
        <p class="text-white/90 mb-4 text-base md:text-lg max-w-2xl">{{ $share->description }}</p>
      @endif
      <div class="flex flex-wrap items-center gap-4 text-sm text-white/80">
        <span class="inline-flex items-center gap-1.5">
          <i class="bi bi-person-circle"></i> {{ $owner?->full_name ?? 'ลูกค้า' }}
        </span>
        <span class="inline-flex items-center gap-1.5">
          <i class="bi bi-eye"></i> {{ number_format($share->view_count) }} ครั้งที่เปิดดู
        </span>
        @if($share->expires_at)
          <span class="inline-flex items-center gap-1.5">
            <i class="bi bi-clock"></i> หมดอายุ {{ $share->expires_at->format('d/m/Y H:i') }}
          </span>
        @endif
      </div>
    </div>
  </div>

  {{-- Items grid --}}
  @if($items->count() > 0)
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      @foreach($items as $item)
        @php
          $isEvent = $item->event_id && $item->event;
          $isProduct = $item->product_id && $item->product;
        @endphp

        @if($isEvent)
          <a href="{{ url('/events/' . ($item->event->slug ?: $item->event->id)) }}"
             class="group block rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all">
            @if($item->event->cover_image_url)
              <div class="aspect-video overflow-hidden bg-slate-100 dark:bg-slate-900">
                <img src="{{ $item->event->cover_image_url }}" alt="{{ $item->event->name }}"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
              </div>
            @endif
            <div class="p-4">
              <div class="flex items-start justify-between mb-2">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                  <i class="bi bi-calendar-event"></i>
                  {{ $item->event->category?->name ?? 'อีเวนต์' }}
                </span>
                <i class="bi bi-heart-fill text-rose-500 dark:text-rose-400"></i>
              </div>
              <h3 class="font-semibold text-slate-900 dark:text-white mb-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition line-clamp-1">{{ $item->event->name }}</h3>
              @if($item->event->description)
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-3 line-clamp-2">{{ Str::limit($item->event->description, 100) }}</p>
              @endif
              <div class="flex items-center justify-between mt-3">
                @if($item->event->shoot_date)
                  <span class="text-xs text-slate-500 dark:text-slate-400">
                    <i class="bi bi-calendar3 mr-1"></i>{{ \Carbon\Carbon::parse($item->event->shoot_date)->format('d/m/Y') }}
                  </span>
                @endif
                @if($item->event->is_free)
                  <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">ฟรี</span>
                @elseif($item->event->price_per_photo)
                  <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400">
                    {{ number_format($item->event->price_per_photo, 0) }} ฿
                  </span>
                @endif
              </div>
            </div>
          </a>

        @elseif($isProduct)
          <a href="{{ url('/products/' . ($item->product->slug ?? $item->product->id)) }}"
             class="group block rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all">
            <div class="p-4">
              <div class="flex items-start justify-between mb-2">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs font-semibold">
                  <i class="bi bi-box-seam"></i> สินค้าดิจิทัล
                </span>
                <i class="bi bi-heart-fill text-rose-500 dark:text-rose-400"></i>
              </div>
              <h3 class="font-semibold text-slate-900 dark:text-white mb-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition line-clamp-1">{{ $item->product->name }}</h3>
              @if($item->product->description)
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-3 line-clamp-2">{{ Str::limit($item->product->description, 100) }}</p>
              @endif
              @if($item->product->price)
                <p class="font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($item->product->price, 0) }} ฿</p>
              @endif
            </div>
          </a>
        @endif
      @endforeach
    </div>
  @else
    <div class="text-center py-20 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-rose-100 to-pink-100 dark:from-rose-500/20 dark:to-pink-500/20 text-rose-500 dark:text-rose-400 mb-4">
        <i class="bi bi-heart text-3xl"></i>
      </div>
      <p class="text-sm text-slate-500 dark:text-slate-400">ยังไม่มีรายการในลิสต์นี้</p>
    </div>
  @endif

  {{-- Footer --}}
  <div class="text-center mt-8 text-sm text-slate-500 dark:text-slate-400">
    <p>
      สร้างรายการโปรดของคุณเอง
      <a href="{{ route('login') }}" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">เข้าสู่ระบบ / สมัครสมาชิก</a>
    </p>
  </div>
</div>
@endsection
