@extends('layouts.admin')

@section('title', 'Bookings — คิวงานทั้งระบบ')

@push('styles')
<style>
  .ad-hero {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
    color: white;
    border-radius: 24px;
    padding: 1.5rem 1.75rem;
    box-shadow: 0 16px 40px -12px rgba(139,92,246,0.4);
  }
  .ad-stat {
    background: white;
    border: 1px solid rgb(226 232 240);
    border-radius: 14px;
    padding: 1rem 1.25rem;
  }
  .dark .ad-stat { background: rgb(15 23 42); border-color: rgba(255,255,255,0.08); }
  .ad-stat-num { font-size: 1.5rem; font-weight: 800; line-height: 1; }
  .ad-stat-label { font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; color: rgb(100 116 139); }
  .dark .ad-stat-label { color: rgb(148 163 184); }
</style>
@endpush

@section('content')
<div class="max-w-[1400px] mx-auto pb-12">

  <div class="ad-hero mb-5">
    <div class="flex items-center justify-between gap-4 flex-wrap">
      <div>
        <div class="text-[10px] uppercase tracking-[0.25em] text-white/75 font-bold mb-1.5 flex items-center gap-1.5">
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
          Admin Oversight · Bookings
        </div>
        <h1 class="text-2xl font-extrabold leading-tight">คิวงานทั้งระบบ</h1>
        <p class="text-sm text-white/85 mt-1">ดู metrics · จัดการ booking · เกิดข้อพิพาท → admin override</p>
      </div>
      <div class="flex items-center gap-2 text-[11px]">
        <span class="px-3 py-1.5 rounded-full bg-white/15 backdrop-blur border border-white/30 font-bold">
          เดือนนี้: {{ number_format($stats['this_month']) }} bookings
        </span>
      </div>
    </div>
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

  {{-- ═══ Stats Grid ═══ --}}
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-5">
    <div class="ad-stat">
      <div class="ad-stat-label mb-1.5">รวม</div>
      <div class="ad-stat-num text-slate-900 dark:text-white">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="ad-stat">
      <div class="ad-stat-label mb-1.5"><i class="bi bi-hourglass-split text-amber-500"></i> รอยืนยัน</div>
      <div class="ad-stat-num text-amber-600">{{ number_format($stats['pending']) }}</div>
    </div>
    <div class="ad-stat">
      <div class="ad-stat-label mb-1.5"><i class="bi bi-check-circle-fill text-emerald-500"></i> ยืนยัน</div>
      <div class="ad-stat-num text-emerald-600">{{ number_format($stats['confirmed']) }}</div>
    </div>
    <div class="ad-stat">
      <div class="ad-stat-label mb-1.5"><i class="bi bi-flag-fill text-indigo-500"></i> เสร็จแล้ว</div>
      <div class="ad-stat-num text-indigo-600">{{ number_format($stats['completed']) }}</div>
    </div>
    <div class="ad-stat">
      <div class="ad-stat-label mb-1.5"><i class="bi bi-x-circle-fill text-rose-500"></i> ยกเลิก</div>
      <div class="ad-stat-num text-rose-600">{{ number_format($stats['cancelled'] + $stats['no_show']) }}</div>
    </div>
    <div class="ad-stat">
      <div class="ad-stat-label mb-1.5"><i class="bi bi-percent text-cyan-500"></i> Completion</div>
      <div class="ad-stat-num text-cyan-600">{{ $stats['completion_rate'] }}%</div>
    </div>
    <div class="ad-stat">
      <div class="ad-stat-label mb-1.5"><i class="bi bi-cash-coin text-emerald-500"></i> รายรับ</div>
      <div class="ad-stat-num text-emerald-600">฿{{ number_format($stats['revenue_completed']) }}</div>
    </div>
  </div>

  {{-- ═══ Filters ═══ --}}
  <form method="GET" class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-4 mb-4">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
      <div class="md:col-span-2">
        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase">ค้นหา</label>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ชื่องาน / สถานที่ / อีเมลลูกค้า"
               class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-sm">
      </div>
      <div>
        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase">สถานะ</label>
        <select name="status" class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-sm">
          <option value="">ทั้งหมด</option>
          @foreach(['pending'=>'รอยืนยัน','confirmed'=>'ยืนยันแล้ว','completed'=>'เสร็จสิ้น','cancelled'=>'ยกเลิก','no_show'=>'ไม่มาตามนัด'] as $k=>$v)
            <option value="{{ $k }}" {{ ($filters['status']??'')===$k ? 'selected':'' }}>{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase">วันเริ่ม</label>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
               class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-sm">
      </div>
      <div>
        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase">วันสิ้นสุด</label>
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
               class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-sm">
      </div>
    </div>
    <div class="flex items-center gap-2 mt-3 flex-wrap">
      <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-xs font-bold text-white bg-indigo-500 hover:bg-indigo-600 transition">
        <i class="bi bi-funnel"></i> Filter
      </button>
      <a href="{{ route('admin.bookings.index') }}" class="text-xs text-slate-500 hover:text-slate-700">Clear</a>
      <span class="ml-auto text-xs text-slate-500">แสดง {{ $bookings->count() }} จาก {{ $bookings->total() }} รายการ</span>
    </div>
  </form>

  {{-- ═══ Bookings Table ═══ --}}
  <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 overflow-hidden">
    @if($bookings->count() === 0)
      <div class="text-center py-16 text-slate-500">
        <i class="bi bi-calendar2-x text-4xl block mb-2"></i>
        ไม่มี booking ตรงเงื่อนไข
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 dark:bg-white/5 text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">
            <tr>
              <th class="px-4 py-3 text-left">#</th>
              <th class="px-4 py-3 text-left">งาน + ลูกค้า</th>
              <th class="px-4 py-3 text-left">ช่างภาพ</th>
              <th class="px-4 py-3 text-left">วันเวลา</th>
              <th class="px-4 py-3 text-right">ราคา</th>
              <th class="px-4 py-3 text-center">สถานะ</th>
              <th class="px-4 py-3 text-right"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-white/5">
            @foreach($bookings as $b)
              <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                <td class="px-4 py-3 text-xs text-slate-500">#{{ $b->id }}</td>
                <td class="px-4 py-3">
                  <div class="font-semibold text-slate-900 dark:text-white">{{ Str::limit($b->title, 35) }}</div>
                  <div class="text-[11px] text-slate-500">{{ $b->customer?->first_name ?? '?' }} · {{ $b->customer?->email ?? '-' }}</div>
                </td>
                <td class="px-4 py-3">
                  <div class="text-slate-700 dark:text-slate-200">{{ $b->photographerProfile?->display_name ?? $b->photographer?->first_name ?? '?' }}</div>
                </td>
                <td class="px-4 py-3 text-xs">
                  <div class="font-semibold text-slate-700 dark:text-slate-200">{{ $b->scheduled_at->format('d/m/Y') }}</div>
                  <div class="text-slate-500">{{ $b->scheduled_at->format('H:i') }} · {{ $b->duration_minutes }}m</div>
                </td>
                <td class="px-4 py-3 text-right">
                  @if($b->agreed_price)
                    <div class="font-bold text-emerald-600">฿{{ number_format($b->agreed_price) }}</div>
                    @if($b->deposit_paid > 0)
                      <div class="text-[10px] text-emerald-500">มัดจำ ฿{{ number_format($b->deposit_paid) }}</div>
                    @endif
                  @else
                    <span class="text-slate-400">-</span>
                  @endif
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap" style="background:{{ $b->color }}25; color:{{ $b->color }};">
                    {{ $b->status_label }}
                  </span>
                  @if($b->is_waitlist)
                    <div class="text-[9px] text-violet-600 mt-0.5">⏳ Waitlist</div>
                  @endif
                </td>
                <td class="px-4 py-3 text-right">
                  <a href="{{ route('admin.bookings.show', $b->id) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 hover:bg-indigo-500 hover:text-white transition">
                    <i class="bi bi-eye text-xs"></i>
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-slate-100 dark:border-white/5">{{ $bookings->links() }}</div>
    @endif
  </div>

</div>
@endsection
