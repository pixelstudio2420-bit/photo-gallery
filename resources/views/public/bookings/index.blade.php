@extends('layouts.app')

@section('title', 'การจองของฉัน')

@section('content')
<div class="max-w-5xl mx-auto py-8 px-4">
  <div class="mb-5">
    <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <i class="bi bi-calendar-check text-indigo-500"></i>
      การจองคิวงานของฉัน
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">ติดตามสถานะ + รับ LINE reminder ก่อนวันงาน</p>
  </div>

  @if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 text-sm">
      <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
  @endif

  @if($bookings->count() === 0)
    <div class="rounded-2xl p-12 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10">
      <i class="bi bi-calendar2-x text-4xl text-slate-300 dark:text-slate-600 block mb-3"></i>
      <h3 class="font-bold text-slate-700 dark:text-slate-300">ยังไม่มีการจอง</h3>
      <p class="text-sm text-slate-500 mt-1 mb-4">เลือกช่างภาพแล้วเริ่มจองคิวงานได้เลย</p>
      <a href="{{ url('/events') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-indigo-500 hover:bg-indigo-600 transition no-underline">
        <i class="bi bi-search"></i> หาช่างภาพ
      </a>
    </div>
  @else
    <div class="space-y-3">
      @foreach($bookings as $b)
        <a href="{{ route('profile.bookings.show', $b->id) }}" class="block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 hover:shadow-lg hover:border-indigo-300 dark:hover:border-indigo-500/40 transition overflow-hidden no-underline">
          <div class="flex items-stretch">
            <div class="w-20 flex-shrink-0 flex flex-col items-center justify-center text-white" style="background:linear-gradient(135deg,{{ $b->color }},{{ $b->color }}aa);">
              <div class="text-[9px] uppercase font-bold opacity-80">{{ $b->scheduled_at->format('M') }}</div>
              <div class="text-2xl font-extrabold leading-none my-0.5">{{ $b->scheduled_at->format('d') }}</div>
              <div class="text-[10px] opacity-80">{{ $b->scheduled_at->format('H:i') }}</div>
            </div>
            <div class="flex-1 p-4 min-w-0">
              <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                <div class="min-w-0">
                  <div class="font-bold text-slate-900 dark:text-white truncate">{{ $b->title }}</div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2 flex-wrap">
                    <span><i class="bi bi-camera"></i> {{ $b->photographerProfile?->display_name ?? $b->photographer?->first_name ?? '?' }}</span>
                    @if($b->location)<span class="truncate"><i class="bi bi-geo-alt"></i> {{ Str::limit($b->location, 30) }}</span>@endif
                  </div>
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap" style="background:{{ $b->color }}25; color:{{ $b->color }};">
                  {{ $b->status_label }}
                </span>
              </div>
              @if($b->agreed_price)
                <div class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                  <i class="bi bi-cash-coin"></i> {{ number_format($b->agreed_price) }} ฿
                </div>
              @endif
            </div>
          </div>
        </a>
      @endforeach
    </div>

    <div class="mt-5">{{ $bookings->links() }}</div>
  @endif
</div>
@endsection
