@extends('layouts.photographer')

@section('title', 'Booking #' . $booking->id)

@section('content')
<div class="max-w-5xl mx-auto pb-16">
  <div class="mb-4">
    <a href="{{ route('photographer.bookings') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
      <i class="bi bi-arrow-left"></i> กลับไปปฏิทินงาน
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
    {{-- Main detail --}}
    <div class="lg:col-span-2 space-y-4">
      <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10">
        <div class="px-5 py-4 text-white" style="background:linear-gradient(135deg,{{ $booking->color }},{{ $booking->color }}cc);">
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

        <div class="p-5 space-y-3 text-sm">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <div class="text-[11px] uppercase font-bold text-slate-500 dark:text-slate-400 mb-1">วันเวลา</div>
              <div class="font-semibold text-slate-900 dark:text-white">{{ $booking->scheduled_at->format('d/m/Y H:i') }}</div>
              <div class="text-xs text-slate-500">{{ $booking->duration_minutes }} นาที (ถึง {{ $booking->ends_at->format('H:i') }})</div>
            </div>
            <div>
              <div class="text-[11px] uppercase font-bold text-slate-500 dark:text-slate-400 mb-1">ราคาที่ตกลง</div>
              <div class="font-semibold text-slate-900 dark:text-white">
                {{ $booking->agreed_price ? number_format($booking->agreed_price) . ' ฿' : 'ยังไม่ระบุ' }}
              </div>
              @if($booking->deposit_paid > 0)<div class="text-xs text-emerald-600">มัดจำ {{ number_format($booking->deposit_paid) }} ฿</div>@endif
            </div>
          </div>

          @if($booking->location)
            <div>
              <div class="text-[11px] uppercase font-bold text-slate-500 dark:text-slate-400 mb-1">สถานที่</div>
              <div class="text-slate-700 dark:text-slate-200">{{ $booking->location }}</div>
            </div>
          @endif

          @if($booking->description)
            <div>
              <div class="text-[11px] uppercase font-bold text-slate-500 dark:text-slate-400 mb-1">รายละเอียด</div>
              <div class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $booking->description }}</div>
            </div>
          @endif

          @if($booking->customer_notes)
            <div>
              <div class="text-[11px] uppercase font-bold text-slate-500 dark:text-slate-400 mb-1">ข้อความจากลูกค้า</div>
              <div class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap p-3 rounded-lg bg-slate-50 dark:bg-white/5">{{ $booking->customer_notes }}</div>
            </div>
          @endif
        </div>
      </div>

      {{-- Photographer notes (private) --}}
      <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-5">
        <div class="flex items-center gap-2 mb-2">
          <i class="bi bi-pencil-square text-indigo-500"></i>
          <h3 class="font-bold text-sm text-slate-900 dark:text-white">โน้ตของฉัน <span class="text-[10px] text-slate-500 font-normal">(ลูกค้าไม่เห็น)</span></h3>
        </div>
        <form action="{{ route('photographer.bookings.notes', $booking->id) }}" method="POST">
          @csrf
          <textarea name="photographer_notes" rows="3" maxlength="2000"
                    placeholder="อุปกรณ์ที่ต้องเตรียม / ข้อสังเกต / ข้อตกลงเพิ่มเติม..."
                    class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">{{ $booking->photographer_notes }}</textarea>
          <button type="submit" class="mt-2 px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-indigo-500 hover:bg-indigo-600 transition">
            บันทึกโน้ต
          </button>
        </form>
      </div>
    </div>

    {{-- Side: customer + actions --}}
    <div class="space-y-4">
      <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-5">
        <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-3 flex items-center gap-2">
          <i class="bi bi-person-circle text-emerald-500"></i> ลูกค้า
        </h3>
        <div class="space-y-2 text-sm">
          <div>
            <div class="text-[10px] uppercase font-bold text-slate-500">ชื่อ</div>
            <div class="font-semibold text-slate-900 dark:text-white">{{ $booking->customer?->first_name ?? '-' }}</div>
          </div>
          @if($booking->customer_phone)
            <div>
              <div class="text-[10px] uppercase font-bold text-slate-500">เบอร์</div>
              <a href="tel:{{ $booking->customer_phone }}" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">{{ $booking->customer_phone }}</a>
            </div>
          @endif
          @if($booking->customer?->email)
            <div>
              <div class="text-[10px] uppercase font-bold text-slate-500">อีเมล</div>
              <a href="mailto:{{ $booking->customer->email }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $booking->customer->email }}</a>
            </div>
          @endif
        </div>
      </div>

      {{-- Actions --}}
      <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-5">
        <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-3 flex items-center gap-2">
          <i class="bi bi-lightning-charge-fill text-amber-500"></i> Actions
        </h3>
        <div class="space-y-2">
          @if($booking->isPending())
            <form action="{{ route('photographer.bookings.confirm', $booking->id) }}" method="POST">
              @csrf
              <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-500 hover:bg-emerald-600 transition">
                <i class="bi bi-check-circle"></i> ยืนยันคิวงาน
              </button>
            </form>
          @endif
          @if($booking->isConfirmed())
            <form action="{{ route('photographer.bookings.complete', $booking->id) }}" method="POST">
              @csrf
              <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-indigo-500 hover:bg-indigo-600 transition">
                <i class="bi bi-flag-fill"></i> Mark งานเสร็จ
              </button>
            </form>
          @endif
          @if($booking->isPending() || $booking->isConfirmed())
            <form action="{{ route('photographer.bookings.cancel', $booking->id) }}" method="POST"
                  onsubmit="const r = prompt('ระบุเหตุผลที่ยกเลิก:'); if (!r) return false; this.querySelector('[name=reason]').value = r;">
              @csrf
              <input type="hidden" name="reason">
              <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-medium text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-100 dark:hover:bg-rose-500/15 transition border border-rose-200 dark:border-rose-500/30">
                <i class="bi bi-x-circle"></i> ยกเลิกคิวงาน
              </button>
            </form>
          @endif

          @if($booking->isCancelled() && $booking->cancellation_reason)
            <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-500/10 text-xs">
              <div class="font-bold text-rose-700 dark:text-rose-300 mb-0.5">ยกเลิกโดย {{ $booking->cancelled_by }}</div>
              <div class="text-rose-600 dark:text-rose-400">{{ $booking->cancellation_reason }}</div>
            </div>
          @endif
        </div>
      </div>

      {{-- Reminder log --}}
      <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-5">
        <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-3 flex items-center gap-2">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="#06C755"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zM24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
          LINE Reminders
        </h3>
        <div class="space-y-1.5 text-[11px]">
          @php
            $reminders = [
              ['ก่อน 3 วัน', $booking->reminder_3d_sent_at],
              ['ก่อน 1 วัน', $booking->reminder_1d_sent_at],
              ['ก่อน 1 ชม.', $booking->reminder_1h_sent_at],
              ['วันงาน', $booking->reminder_day_sent_at],
              ['หลังงาน — รีวิว', $booking->post_shoot_review_sent_at],
            ];
          @endphp
          @foreach($reminders as [$label, $sentAt])
            <div class="flex items-center justify-between">
              <span class="text-slate-600 dark:text-slate-300">{{ $label }}</span>
              @if($sentAt)
                <span class="text-emerald-600 dark:text-emerald-400 font-semibold"><i class="bi bi-check2"></i> {{ $sentAt->format('d/m H:i') }}</span>
              @else
                <span class="text-slate-400">—</span>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
