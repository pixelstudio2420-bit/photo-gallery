{{-- =======================================================================
     PHOTOGRAPHER RETENTION STATUS WIDGET
     -------------------------------------------------------------------
     Consumes $retention = PhotographerRetentionService::statusFor(user_id)
     Hidden silently if data isn't loaded — older dashboards still render.
     ====================================================================== --}}
@if(!empty($retention) && is_array($retention))
@php
  $expToday = (int) ($retention['expiring_today'] ?? 0);
  $expWeek  = (int) ($retention['expiring_this_week'] ?? 0);
  $expMonth = (int) ($retention['expiring_this_month'] ?? 0);
  $hasUrgent = ($expToday + $expWeek) > 0;
  $tier = $retention['tier_label'] ?? '-';
  $days = (int) ($retention['retention_days'] ?? 0);
  $mode = $retention['retention_mode'] ?? 'portfolio';
@endphp

<div class="rounded-2xl border shadow-sm mb-5 overflow-hidden
            {{ $hasUrgent ? 'border-amber-300 dark:border-amber-500/50' : 'border-slate-200 dark:border-white/10' }}
            bg-white dark:bg-slate-800/60">

  {{-- Header --}}
  <div class="flex items-center justify-between px-5 pt-4 pb-3 border-b border-slate-100 dark:border-white/5">
    <div class="flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl
                   {{ $hasUrgent ? 'bg-gradient-to-br from-amber-500 to-orange-600' : 'bg-gradient-to-br from-cyan-500 to-teal-600' }}
                   text-white">
        <i class="bi {{ $hasUrgent ? 'bi-clock-history' : 'bi-shield-check' }} text-sm"></i>
      </span>
      <div>
        <div class="font-semibold text-sm text-slate-900 dark:text-white leading-tight">
          การเก็บข้อมูลอีเวนต์ของคุณ
        </div>
        <div class="text-[10px] text-slate-500 dark:text-slate-400">
          แผน {{ $tier }} · เก็บได้ {{ $days }} วันก่อนเก็บเข้าโชว์ผลงาน
        </div>
      </div>
    </div>
    @if($hasUrgent)
      <a href="{{ url('/photographer/upgrade') }}"
         class="text-[11px] font-semibold px-3 py-1.5 rounded-full transition
                bg-gradient-to-r from-amber-500 to-orange-600 text-white
                hover:shadow-lg">
        <i class="bi bi-arrow-up-circle mr-1"></i> อัปเกรดแผน
      </a>
    @endif
  </div>

  {{-- Body --}}
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3 p-5">
    <div class="p-3 rounded-xl {{ $expToday > 0 ? 'bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30' : 'bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-white/10' }}">
      <div class="text-[10px] uppercase tracking-wider font-semibold text-slate-500 dark:text-slate-400">วันนี้</div>
      <div class="text-2xl font-bold {{ $expToday > 0 ? 'text-rose-600 dark:text-rose-300' : 'text-slate-400' }} tabular-nums">{{ $expToday }}</div>
      <div class="text-[10px] text-slate-500 dark:text-slate-400">อีเวนต์</div>
    </div>
    <div class="p-3 rounded-xl {{ $expWeek > 0 ? 'bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30' : 'bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-white/10' }}">
      <div class="text-[10px] uppercase tracking-wider font-semibold text-slate-500 dark:text-slate-400">สัปดาห์นี้</div>
      <div class="text-2xl font-bold {{ $expWeek > 0 ? 'text-amber-600 dark:text-amber-300' : 'text-slate-400' }} tabular-nums">{{ $expWeek }}</div>
      <div class="text-[10px] text-slate-500 dark:text-slate-400">อีเวนต์</div>
    </div>
    <div class="p-3 rounded-xl {{ $expMonth > 0 ? 'bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30' : 'bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-white/10' }}">
      <div class="text-[10px] uppercase tracking-wider font-semibold text-slate-500 dark:text-slate-400">เดือนนี้</div>
      <div class="text-2xl font-bold {{ $expMonth > 0 ? 'text-blue-600 dark:text-blue-300' : 'text-slate-400' }} tabular-nums">{{ $expMonth }}</div>
      <div class="text-[10px] text-slate-500 dark:text-slate-400">อีเวนต์</div>
    </div>
  </div>

  {{-- Upcoming events table --}}
  @if(!empty($retention['upcoming']))
    <div class="border-t border-slate-100 dark:border-white/5 px-5 py-3">
      <div class="text-[11px] font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-2">
        อีเวนต์ที่จะเก็บเข้าโชว์ผลงานเร็วๆ นี้
      </div>
      <div class="space-y-1.5">
        @foreach($retention['upcoming'] as $u)
          <a href="{{ url('/photographer/events/' . $u['id']) }}"
             class="flex items-center justify-between gap-3 p-2 rounded-lg
                    hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
            <div class="flex-1 min-w-0">
              <div class="text-[13px] font-medium text-slate-900 dark:text-white truncate">
                {{ $u['name'] }}
              </div>
              <div class="text-[10px] text-slate-500 dark:text-slate-400">
                {{ $u['photo_count'] }} รูป · {{ $u['eta'] }}
              </div>
            </div>
            <div class="text-right shrink-0">
              @if($u['days_left'] <= 0)
                <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300">
                  วันนี้
                </span>
              @elseif($u['days_left'] <= 2)
                <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                  อีก {{ $u['days_left'] }} วัน
                </span>
              @else
                <span class="text-[10px] font-medium px-2 py-1 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                  อีก {{ $u['days_left'] }} วัน
                </span>
              @endif
            </div>
          </a>
        @endforeach
      </div>
    </div>
  @endif

  {{-- Footer info --}}
  <div class="bg-slate-50 dark:bg-slate-900/40 px-5 py-3 text-[11px] text-slate-600 dark:text-slate-400 border-t border-slate-100 dark:border-white/5">
    <i class="bi bi-info-circle mr-1 text-slate-500"></i>
    @if($mode === 'portfolio')
      เมื่อหมดอายุ ระบบจะ <strong>เก็บปก + พรีวิว + ลายน้ำ</strong> ไว้บนหน้าโชว์ผลงานของคุณ
      ต้นฉบับจะถูกลบเพื่อประหยัดพื้นที่
    @else
      เมื่อหมดอายุ ระบบจะ <strong>ลบอีเวนต์ทั้งหมด</strong> (รวมปกและพรีวิว)
    @endif
    @if(($retention['already_archived'] ?? 0) > 0)
      · มี {{ $retention['already_archived'] }} อีเวนต์เก่าอยู่บนโชว์ผลงานแล้ว
    @endif
  </div>
</div>
@endif
