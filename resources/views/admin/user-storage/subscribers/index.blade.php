@extends('layouts.admin')
@section('title', 'สมาชิก Cloud Storage')

@php
  function fmtBytes2($bytes, $precision = 2) {
      if ($bytes <= 0) return '0 B';
      $units = ['B', 'KB', 'MB', 'GB', 'TB'];
      $pow = min(floor(log($bytes, 1024)), count($units) - 1);
      return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
  }
@endphp

@section('content')
<div class="flex items-center justify-between flex-wrap gap-2 mb-4">
  <div>
    <h4 class="font-bold tracking-tight flex items-center gap-2">
      <i class="bi bi-people-fill text-indigo-500"></i> สมาชิก Cloud Storage
      <span class="text-xs font-normal text-gray-400 ml-2">/ ผู้ใช้ทั้งหมด {{ $subscribers->total() }} คน</span>
    </h4>
  </div>
  <a href="{{ route('admin.user-storage.index') }}" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-sm">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-2.5">
    <i class="bi bi-check-circle-fill mr-1"></i>{{ session('success') }}
  </div>
@endif

{{-- Filters --}}
<form method="GET" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-4 mb-4">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div>
      <label class="text-xs text-gray-500 mb-1 block">ค้นหา (ชื่อ / อีเมล)</label>
      <input type="text" name="q" value="{{ $search }}" placeholder="eak@example.com"
             class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
    </div>
    <div>
      <label class="text-xs text-gray-500 mb-1 block">สถานะ</label>
      <select name="status" class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
        <option value="">ทั้งหมด</option>
        <option value="free"      {{ $status === 'free' ? 'selected' : '' }}>ใช้ Free</option>
        <option value="paid"      {{ $status === 'paid' ? 'selected' : '' }}>ใช้แผนเสียเงิน</option>
        <option value="active"    {{ $status === 'active' ? 'selected' : '' }}>Active</option>
        <option value="grace"     {{ $status === 'grace' ? 'selected' : '' }}>Grace</option>
        <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
        <option value="expired"   {{ $status === 'expired' ? 'selected' : '' }}>Expired</option>
      </select>
    </div>
    <div>
      <label class="text-xs text-gray-500 mb-1 block">แผน</label>
      <select name="plan" class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
        <option value="">ทุกแผน</option>
        @foreach($planOptions as $po)
          <option value="{{ $po->code }}" {{ $plan === $po->code ? 'selected' : '' }}>{{ $po->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="flex items-end gap-2">
      <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
        <i class="bi bi-filter mr-1"></i> กรอง
      </button>
      <a href="{{ route('admin.user-storage.subscribers.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm">
        ล้าง
      </a>
    </div>
  </div>
</form>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs text-gray-500">
        <tr>
          <th class="px-3 py-2 text-left">ผู้ใช้</th>
          <th class="px-3 py-2 text-left">แผน</th>
          <th class="px-3 py-2 text-left">สถานะ</th>
          <th class="px-3 py-2 text-right">การใช้งาน</th>
          <th class="px-3 py-2 text-left">รอบถัดไป</th>
          <th class="px-3 py-2 text-right">—</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/5">
        @forelse($subscribers as $s)
          @php
            $pct = $s->storage_quota_bytes > 0 ? min(100, round(($s->storage_used_bytes / $s->storage_quota_bytes) * 100, 1)) : 0;
            $barCls = $pct >= 95 ? 'bg-rose-500' : ($pct >= 85 ? 'bg-amber-500' : 'bg-indigo-500');
            $statusBadge = match(strtolower($s->sub_status ?? $s->storage_plan_status ?? 'active')) {
              'active'    => 'bg-emerald-100 text-emerald-700',
              'grace'     => 'bg-amber-100 text-amber-700',
              'pending'   => 'bg-blue-100 text-blue-700',
              'cancelled' => 'bg-gray-100 text-gray-600',
              'expired'   => 'bg-rose-100 text-rose-700',
              default     => 'bg-gray-100 text-gray-600',
            };
            $nextDate = $s->sub_period_end ?? $s->storage_renews_at;
            $fullName = trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? ''));
          @endphp
          <tr>
            <td class="px-3 py-2">
              <div class="font-medium text-gray-800 dark:text-white/90">{{ $fullName ?: '(ไม่ระบุชื่อ)' }}</div>
              <div class="text-[10px] text-gray-400">{{ $s->email }}</div>
            </td>
            <td class="px-3 py-2">
              <div class="flex items-center gap-1.5">
                <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:{{ $s->plan_color ?? '#9ca3af' }};"></span>
                <span class="text-xs font-medium">{{ $s->plan_name ?? ucfirst($s->storage_plan_code ?? 'free') }}</span>
              </div>
              @if($s->plan_price > 0)
                <div class="text-[10px] text-gray-400">฿{{ number_format((float) $s->plan_price, 0) }}/เดือน</div>
              @endif
            </td>
            <td class="px-3 py-2">
              <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold {{ $statusBadge }}">
                {{ strtoupper($s->sub_status ?? $s->storage_plan_status ?? 'active') }}
              </span>
              @if($s->sub_grace_ends)
                <div class="text-[10px] text-amber-600 mt-0.5">
                  Grace: {{ \Carbon\Carbon::parse($s->sub_grace_ends)->format('d/m') }}
                </div>
              @endif
            </td>
            <td class="px-3 py-2">
              <div class="text-xs text-right font-mono">
                {{ fmtBytes2($s->storage_used_bytes) }} / {{ fmtBytes2($s->storage_quota_bytes) }}
              </div>
              <div class="h-1 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden mt-1">
                <div class="h-full {{ $barCls }}" style="width:{{ $pct }}%"></div>
              </div>
              <div class="text-[10px] text-right text-gray-400 mt-0.5">{{ $pct }}%</div>
            </td>
            <td class="px-3 py-2 text-xs text-gray-500">
              @if($nextDate)
                {{ \Carbon\Carbon::parse($nextDate)->format('d/m/Y') }}
              @else
                <span class="text-gray-300">—</span>
              @endif
            </td>
            <td class="px-3 py-2 text-right">
              <a href="{{ route('admin.user-storage.subscribers.show', $s->id) }}" class="text-xs text-indigo-500 hover:underline">
                รายละเอียด →
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="px-3 py-10 text-center text-gray-400">ไม่พบสมาชิก</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-4">
  {{ $subscribers->links() }}
</div>
@endsection
