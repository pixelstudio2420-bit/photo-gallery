@extends('layouts.admin')

@section('title', 'Booking #' . $booking->id . ' — Admin')

@section('content')
<div class="max-w-5xl mx-auto pb-12">

  <div class="mb-4">
    <a href="{{ route('admin.bookings.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
      <i class="bi bi-arrow-left"></i> รายการ bookings
    </a>
  </div>

  @if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 text-sm">
      <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
  @endif
  @if(session('error'))
    <div class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 text-sm">
      <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-4">

      {{-- Header --}}
      <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10">
        <div class="px-5 py-4 text-white" style="background:linear-gradient(135deg,{{ $booking->color }},{{ $booking->color }}cc);">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
              <div class="text-[10px] uppercase tracking-widest opacity-80">Booking #{{ $booking->id }}</div>
              <h1 class="text-xl font-bold leading-tight mt-0.5">{{ $booking->title }}</h1>
            </div>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-white/20 backdrop-blur">
              {{ $booking->status_label }}
            </span>
          </div>
        </div>
        <div class="p-5 grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
          <div>
            <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">วัน-เวลา</div>
            <div class="font-bold text-slate-900 dark:text-white">{{ $booking->scheduled_at->format('d/m/Y H:i') }}</div>
            <div class="text-xs text-slate-500">{{ $booking->duration_minutes }} นาที</div>
          </div>
          <div>
            <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">ราคา</div>
            <div class="font-bold text-emerald-600">{{ $booking->agreed_price ? '฿' . number_format($booking->agreed_price) : '-' }}</div>
            @if($booking->deposit_paid > 0)
              <div class="text-xs text-emerald-500">มัดจำ ฿{{ number_format($booking->deposit_paid) }}</div>
            @endif
          </div>
          <div>
            <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">สร้างเมื่อ</div>
            <div class="text-slate-700 dark:text-slate-200">{{ $booking->created_at->format('d/m/Y H:i') }}</div>
          </div>
          @if($booking->location)
            <div class="md:col-span-3">
              <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">สถานที่</div>
              <div class="text-slate-700 dark:text-slate-200">{{ $booking->location }}</div>
            </div>
          @endif
          @if($booking->customer_notes)
            <div class="md:col-span-3">
              <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">ลูกค้าโน้ต</div>
              <div class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap p-3 rounded-lg bg-slate-50 dark:bg-white/5">{{ $booking->customer_notes }}</div>
            </div>
          @endif
          @if($booking->photographer_notes)
            <div class="md:col-span-3">
              <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">ช่างภาพโน้ต</div>
              <div class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap p-3 rounded-lg bg-slate-50 dark:bg-white/5">{{ $booking->photographer_notes }}</div>
            </div>
          @endif
        </div>
      </div>

      {{-- Admin notes --}}
      <div class="rounded-2xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 p-5">
        <div class="flex items-center gap-2 mb-2">
          <i class="bi bi-shield-fill-check text-amber-600"></i>
          <h3 class="font-bold text-sm text-slate-900 dark:text-white">Admin Notes <span class="text-[10px] font-normal text-slate-500">(audit trail)</span></h3>
        </div>
        <form action="{{ route('admin.bookings.note', $booking->id) }}" method="POST">
          @csrf
          <textarea name="admin_notes" rows="3" maxlength="2000"
                    placeholder="เหตุผล / ข้อมูลข้อพิพาท / decisions..."
                    class="w-full px-3 py-2 rounded-lg border border-amber-200 dark:border-amber-500/30 bg-white dark:bg-slate-800 text-sm">{{ $booking->admin_notes }}</textarea>
          <button type="submit" class="mt-2 px-4 py-1.5 rounded-lg text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 transition">
            บันทึก Admin Note
          </button>
        </form>
      </div>
    </div>

    {{-- Side: parties + actions --}}
    <div class="space-y-4">
      <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-5">
        <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-3 flex items-center gap-2">
          <i class="bi bi-person-circle text-emerald-500"></i> ลูกค้า
        </h3>
        <div class="space-y-1.5 text-sm">
          <div class="text-slate-700 dark:text-slate-200">{{ $booking->customer?->first_name ?? '-' }}</div>
          @if($booking->customer?->email)
            <a href="mailto:{{ $booking->customer->email }}" class="text-indigo-600 dark:text-indigo-400 text-xs hover:underline block">{{ $booking->customer->email }}</a>
          @endif
          @if($booking->customer_phone)
            <a href="tel:{{ $booking->customer_phone }}" class="text-emerald-600 text-xs hover:underline block"><i class="bi bi-telephone"></i> {{ $booking->customer_phone }}</a>
          @endif
        </div>
      </div>

      <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-5">
        <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-3 flex items-center gap-2">
          <i class="bi bi-camera-fill text-violet-500"></i> ช่างภาพ
        </h3>
        <div class="space-y-1.5 text-sm">
          <div class="text-slate-700 dark:text-slate-200">{{ $booking->photographerProfile?->display_name ?? $booking->photographer?->first_name ?? '-' }}</div>
          @if($booking->photographer?->email)
            <a href="mailto:{{ $booking->photographer->email }}" class="text-indigo-600 dark:text-indigo-400 text-xs hover:underline block">{{ $booking->photographer->email }}</a>
          @endif
        </div>
      </div>

      {{-- Admin actions --}}
      <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-5">
        <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-3 flex items-center gap-2">
          <i class="bi bi-shield-lock-fill text-rose-500"></i> Admin Actions
        </h3>
        <div class="space-y-2">
          @if(!$booking->isCancelled() && !$booking->isCompleted())
            <form action="{{ route('admin.bookings.cancel', $booking->id) }}" method="POST"
                  onsubmit="const r = prompt('เหตุผลที่ admin ยกเลิก:'); if (!r) return false; this.querySelector('[name=reason]').value = r;">
              @csrf
              <input type="hidden" name="reason">
              <button type="submit" class="w-full px-4 py-2 rounded-lg text-xs font-bold text-white bg-rose-500 hover:bg-rose-600 transition">
                <i class="bi bi-x-circle"></i> ยกเลิก (admin override)
              </button>
            </form>
          @endif
          @if($booking->isConfirmed())
            <form action="{{ route('admin.bookings.no-show', $booking->id) }}" method="POST"
                  onsubmit="const r = prompt('เหตุผล (เช่น ใครไม่มา?):') ?? ''; this.querySelector('[name=reason]').value = r;">
              @csrf
              <input type="hidden" name="reason">
              <button type="submit" class="w-full px-4 py-2 rounded-lg text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 transition">
                <i class="bi bi-flag"></i> Mark No-Show
              </button>
            </form>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
