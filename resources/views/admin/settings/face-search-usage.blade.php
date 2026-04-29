@extends('layouts.admin')

@section('title', 'การใช้งาน Face Search')

@section('content')
@php
  // Shorthand for common counts — the dashboard surfaces them several times.
  $today = $snapshot['today'] ?? [];
  $successToday = (int)($today['success'] ?? 0) + (int)($today['no_face'] ?? 0);
  $cacheToday   = (int)($today['cache_hit'] ?? 0);
  $deniedToday  = collect($today)
      ->only(['denied_kill_switch','denied_daily_cap_event','denied_daily_cap_user','denied_daily_cap_ip','denied_monthly_global','fallback_too_large'])
      ->sum();
  $errorToday   = (int)($today['error'] ?? 0);
  $totalToday   = $successToday + $cacheToday + $deniedToday + $errorToday;
@endphp

<div class="flex items-center justify-between mb-4">
  <h4 class="font-bold tracking-tight">
    <i class="bi bi-graph-up mr-2 text-fuchsia-500"></i>การใช้งาน Face Search
    <span class="text-xs font-normal text-gray-500 ml-2">(อัปเดตทุก 60 วินาที)</span>
  </h4>
  <div class="flex items-center gap-2">
    <a href="{{ route('admin.settings.face-search') }}" class="inline-flex items-center px-4 py-1.5 bg-fuchsia-50 dark:bg-fuchsia-500/10 text-fuchsia-600 dark:text-fuchsia-300 text-sm font-medium rounded-lg hover:bg-fuchsia-100 transition">
      <i class="bi bi-sliders mr-1"></i> ตั้งค่าโควต้า
    </a>
    <a href="{{ route('admin.settings.index') }}" class="inline-flex items-center px-4 py-1.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 text-sm font-medium rounded-lg hover:bg-indigo-100 transition">
      <i class="bi bi-arrow-left mr-1"></i> กลับ
    </a>
  </div>
</div>

{{-- ── KPI cards (today) ────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400">ค้นหาสำเร็จวันนี้</div>
    <div class="text-2xl font-bold text-emerald-600">{{ number_format($successToday) }}</div>
    <div class="text-[11px] text-gray-500 mt-1">ใช้งานจริง (รวม no-face)</div>
  </div>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400">Cache Hit วันนี้</div>
    <div class="text-2xl font-bold text-amber-600">{{ number_format($cacheToday) }}</div>
    <div class="text-[11px] text-gray-500 mt-1">ไม่เรียก AWS (ประหยัด)</div>
  </div>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400">ถูกปฏิเสธวันนี้</div>
    <div class="text-2xl font-bold text-red-600">{{ number_format($deniedToday) }}</div>
    <div class="text-[11px] text-gray-500 mt-1">โดน cap ตัดทิ้ง (ประหยัด)</div>
  </div>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400">Error วันนี้</div>
    <div class="text-2xl font-bold text-gray-600">{{ number_format($errorToday) }}</div>
    <div class="text-[11px] text-gray-500 mt-1">ตรวจสอบ log หากสูงผิดปกติ</div>
  </div>
</div>

{{-- ── Cost estimate ──────────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
  <div class="bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-500/10 dark:to-teal-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-2xl p-5">
    <div class="text-xs text-emerald-700 dark:text-emerald-300 uppercase tracking-wide">AWS API Calls วันนี้</div>
    <div class="text-3xl font-bold text-emerald-900 dark:text-emerald-100 mt-1">{{ number_format($snapshot['api_today'] ?? 0) }}</div>
    <div class="text-sm text-emerald-700 dark:text-emerald-300 mt-2">
      <i class="bi bi-cash-coin mr-1"></i> ค่าใช้จ่ายประมาณ <strong>${{ number_format($snapshot['cost_today'] ?? 0, 2) }}</strong>
    </div>
  </div>
  <div class="bg-gradient-to-br from-fuchsia-50 to-pink-50 dark:from-fuchsia-500/10 dark:to-pink-500/10 border border-fuchsia-200 dark:border-fuchsia-500/20 rounded-2xl p-5">
    <div class="text-xs text-fuchsia-700 dark:text-fuchsia-300 uppercase tracking-wide">AWS API Calls 30 วันล่าสุด</div>
    <div class="text-3xl font-bold text-fuchsia-900 dark:text-fuchsia-100 mt-1">{{ number_format($snapshot['api_30d'] ?? 0) }}</div>
    <div class="text-sm text-fuchsia-700 dark:text-fuchsia-300 mt-2">
      <i class="bi bi-cash-coin mr-1"></i> ค่าใช้จ่ายประมาณ <strong>${{ number_format($snapshot['cost_30d'] ?? 0, 2) }}</strong>
    </div>
  </div>
</div>

{{-- ── Status breakdown (today) ──────────────────────────────── --}}
@if($totalToday > 0)
<div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5 mb-5">
  <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
    <i class="bi bi-pie-chart text-indigo-500 mr-1"></i> สถานะการค้นหาวันนี้
  </h3>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
    @foreach($today as $status => $count)
      @php
        $label = match($status) {
          'success'                 => ['text' => 'สำเร็จ', 'color' => 'emerald'],
          'no_face'                 => ['text' => 'ไม่พบใบหน้า', 'color' => 'amber'],
          'cache_hit'               => ['text' => 'Cache Hit', 'color' => 'sky'],
          'denied_kill_switch'      => ['text' => 'ปิดสวิตช์หลัก', 'color' => 'red'],
          'denied_daily_cap_event'  => ['text' => 'เกิน cap / event', 'color' => 'red'],
          'denied_daily_cap_user'   => ['text' => 'เกิน cap / user', 'color' => 'red'],
          'denied_daily_cap_ip'     => ['text' => 'เกิน cap / IP', 'color' => 'red'],
          'denied_monthly_global'   => ['text' => 'เกิน cap เดือน', 'color' => 'red'],
          'fallback_too_large'      => ['text' => 'Fallback เกินเพดาน', 'color' => 'orange'],
          'error'                   => ['text' => 'ผิดพลาด', 'color' => 'gray'],
          default                   => ['text' => $status, 'color' => 'gray'],
        };
      @endphp
      <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-{{ $label['color'] }}-50 dark:bg-{{ $label['color'] }}-500/10">
        <span class="text-{{ $label['color'] }}-700 dark:text-{{ $label['color'] }}-300">{{ $label['text'] }}</span>
        <span class="font-semibold text-{{ $label['color'] }}-900 dark:text-{{ $label['color'] }}-100">{{ number_format($count) }}</span>
      </div>
    @endforeach
  </div>
</div>
@endif

{{-- ── Top events by usage ───────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
      <i class="bi bi-calendar-event text-indigo-500 mr-1"></i> Event ที่ใช้มากสุดวันนี้
    </h3>
    @if(count($snapshot['top_events'] ?? []) === 0)
      <div class="text-sm text-gray-500 py-4 text-center">ยังไม่มีการค้นหาวันนี้</div>
    @else
      <div class="space-y-2">
        @foreach($snapshot['top_events'] as $row)
          @php $ev = $events->get($row->event_id); @endphp
          <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-700/30">
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-slate-900 dark:text-gray-100 truncate">
                {{ $ev->name ?? ('Event #' . $row->event_id) }}
              </div>
              <div class="text-[11px] text-gray-500">ID: {{ $row->event_id }} • API calls: {{ number_format($row->calls) }}</div>
            </div>
            <div class="text-sm font-bold text-indigo-600 ml-3">{{ number_format($row->total) }}</div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
      <i class="bi bi-person-circle text-red-500 mr-1"></i> IP ที่ค้นหามากสุดวันนี้
    </h3>
    @if(count($snapshot['top_ips'] ?? []) === 0)
      <div class="text-sm text-gray-500 py-4 text-center">ยังไม่มีการค้นหาวันนี้</div>
    @else
      <div class="space-y-2">
        @foreach($snapshot['top_ips'] as $row)
          <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-700/30">
            <div class="min-w-0 flex-1">
              <div class="text-sm font-mono text-slate-900 dark:text-gray-100 truncate">{{ $row->ip_address }}</div>
              <div class="text-[11px] text-gray-500">API calls: {{ number_format($row->calls) }}</div>
            </div>
            <div class="text-sm font-bold text-red-600 ml-3">{{ number_format($row->total) }}</div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>

{{-- ── Recent log rows (50) ──────────────────────────────────── --}}
<div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
  <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
    <i class="bi bi-clock-history text-gray-500 mr-1"></i> Log ล่าสุด 50 รายการ
  </h3>
  @if($recent->isEmpty())
    <div class="text-sm text-gray-500 py-8 text-center">ยังไม่มีข้อมูล</div>
  @else
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-200 dark:border-white/10">
            <th class="py-2 px-2">เวลา</th>
            <th class="py-2 px-2">Event</th>
            <th class="py-2 px-2">User / IP</th>
            <th class="py-2 px-2">Type</th>
            <th class="py-2 px-2 text-right">API</th>
            <th class="py-2 px-2 text-right">Matches</th>
            <th class="py-2 px-2 text-right">ms</th>
            <th class="py-2 px-2">Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($recent as $row)
            @php
              $isError  = $row->status === 'error';
              $isDenied = str_starts_with($row->status, 'denied_') || $row->status === 'fallback_too_large';
              $rowColor = $isError ? 'text-red-600' : ($isDenied ? 'text-amber-600' : 'text-gray-700 dark:text-gray-300');
            @endphp
            <tr class="border-b border-gray-100 dark:border-white/5 {{ $rowColor }}">
              <td class="py-1.5 px-2 whitespace-nowrap text-xs">{{ optional($row->created_at)->format('H:i:s') }}</td>
              <td class="py-1.5 px-2 text-xs">#{{ $row->event_id ?? '-' }}</td>
              <td class="py-1.5 px-2 font-mono text-[11px]">
                {{ $row->user_id ? 'u:' . $row->user_id : $row->ip_address }}
              </td>
              <td class="py-1.5 px-2 text-xs">{{ $row->search_type }}</td>
              <td class="py-1.5 px-2 text-right text-xs">{{ $row->api_calls }}</td>
              <td class="py-1.5 px-2 text-right text-xs">{{ $row->match_count }}</td>
              <td class="py-1.5 px-2 text-right text-xs">{{ $row->duration_ms }}</td>
              <td class="py-1.5 px-2 text-xs">{{ $row->status }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<div class="mt-5 text-[11px] text-gray-500 text-right">
  Snapshot cached until: {{ $snapshot['computed_at'] ?? '-' }}
</div>
@endsection
