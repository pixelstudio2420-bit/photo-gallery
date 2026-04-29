@extends('layouts.app')

@section('title', 'Booking #' . $booking->id)

@section('content')
<div class="max-w-3xl mx-auto py-8 px-4">

  <div class="mb-4">
    <a href="{{ route('profile.bookings') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
      <i class="bi bi-arrow-left"></i> กลับรายการจอง
    </a>
  </div>

  @if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 text-sm">
      <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
  @endif

  <div class="rounded-3xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10">
    <div class="px-6 py-6 text-white" style="background:linear-gradient(135deg,{{ $booking->color }},{{ $booking->color }}cc);">
      <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
          <div class="text-[10px] uppercase tracking-widest opacity-80 mb-1">Booking #{{ $booking->id }}</div>
          <h1 class="text-xl font-bold leading-tight">{{ $booking->title }}</h1>
        </div>
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-white/20 backdrop-blur">
          {{ $booking->status_label }}
        </span>
      </div>
    </div>

    <div class="p-6 space-y-4 text-sm">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">วันเวลา</div>
          <div class="font-bold text-slate-900 dark:text-white">{{ $booking->scheduled_at->format('d/m/Y H:i') }}</div>
          <div class="text-xs text-slate-500">{{ $booking->duration_minutes }} นาที (ถึง {{ $booking->ends_at->format('H:i') }})</div>
        </div>
        <div>
          <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">ช่างภาพ</div>
          <a href="{{ route('photographers.show', $booking->photographer_id) }}" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
            {{ $booking->photographerProfile?->display_name ?? $booking->photographer?->first_name ?? '?' }}
          </a>
        </div>
      </div>

      @if($booking->location)
        <div>
          <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">สถานที่</div>
          <div class="text-slate-700 dark:text-slate-200">{{ $booking->location }}</div>
        </div>
      @endif

      @if($booking->agreed_price)
        <div>
          <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">ราคา</div>
          <div class="text-lg font-extrabold text-emerald-600 dark:text-emerald-400">{{ number_format($booking->agreed_price) }} ฿</div>
        </div>
      @endif

      @if($booking->customer_notes)
        <div>
          <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">รายละเอียดที่คุณส่งไป</div>
          <div class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap p-3 rounded-lg bg-slate-50 dark:bg-white/5">{{ $booking->customer_notes }}</div>
        </div>
      @endif

      @if($booking->isCancelled() && $booking->cancellation_reason)
        <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30">
          <div class="font-bold text-rose-700 dark:text-rose-300 text-xs mb-1">
            <i class="bi bi-x-circle-fill"></i> ยกเลิกโดย {{ $booking->cancelled_by }}
          </div>
          <div class="text-rose-600 dark:text-rose-400 text-xs">{{ $booking->cancellation_reason }}</div>
        </div>
      @endif

      @if($booking->isPending() || $booking->isConfirmed())
        <div class="pt-2">
          <form action="{{ route('profile.bookings.cancel', $booking->id) }}" method="POST"
                onsubmit="const r = prompt('เหตุผลที่ยกเลิก:'); if (!r) return false; this.querySelector('[name=reason]').value = r;">
            @csrf
            <input type="hidden" name="reason">
            <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-medium text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-100 dark:hover:bg-rose-500/15 transition border border-rose-200 dark:border-rose-500/30">
              <i class="bi bi-x-circle"></i> ยกเลิกการจอง
            </button>
          </form>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
