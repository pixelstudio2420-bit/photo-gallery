@extends('layouts.admin')

@section('title', 'Pricing Audit Log')

@section('content')
<div class="mb-6">
  <h4 class="font-bold text-xl tracking-tight">
    <i class="bi bi-shield-check mr-2 text-indigo-500"></i>Pricing Audit Log
  </h4>
  <p class="text-xs text-gray-500 mt-1">
    บันทึกทุกการเปลี่ยนแปลงในตาราง <code>pricing_packages</code> — ใช้ตรวจสอบ disputes, anti-fraud
  </p>
</div>

{{-- Anti-fraud signal cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
  <div class="rounded-xl bg-white border border-gray-100 p-4">
    <div class="text-xs text-gray-500 mb-1"><i class="bi bi-clock-history mr-1"></i>การเปลี่ยน 24 ชม.</div>
    <div class="text-2xl font-bold text-gray-900">{{ number_format($signals['last_24h']) }}</div>
  </div>
  <div class="rounded-xl bg-white border border-gray-100 p-4">
    <div class="text-xs text-gray-500 mb-1"><i class="bi bi-person-badge mr-1 text-indigo-500"></i>ช่างภาพแก้ (24 ชม.)</div>
    <div class="text-2xl font-bold text-indigo-600">{{ number_format($signals['photographer_ops']) }}</div>
  </div>
  <div class="rounded-xl bg-white border border-gray-100 p-4">
    <div class="text-xs text-gray-500 mb-1"><i class="bi bi-cpu mr-1 text-emerald-500"></i>ระบบ auto (24 ชม.)</div>
    <div class="text-2xl font-bold text-emerald-600">{{ number_format($signals['system_ops']) }}</div>
  </div>
  <div class="rounded-xl bg-white border-2 {{ $signals['rapid_flips'] > 0 ? 'border-red-300 bg-red-50' : 'border-gray-100' }} p-4">
    <div class="text-xs {{ $signals['rapid_flips'] > 0 ? 'text-red-700' : 'text-gray-500' }} mb-1">
      <i class="bi bi-exclamation-triangle mr-1 {{ $signals['rapid_flips'] > 0 ? 'text-red-500' : 'text-amber-500' }}"></i>
      <strong>Rapid Flips</strong> (≥4 in 1h)
    </div>
    <div class="text-2xl font-bold {{ $signals['rapid_flips'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
      {{ number_format($signals['rapid_flips']) }}
      @if($signals['rapid_flips'] > 0)
        <span class="text-xs text-red-500 ml-1">⚠ ต้องตรวจ</span>
      @endif
    </div>
  </div>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-xl border border-gray-100 p-4 mb-4 grid grid-cols-2 md:grid-cols-5 gap-3">
  <div>
    <label class="block text-xs font-medium text-gray-600 mb-1">อีเวนต์</label>
    <select name="event_id" class="w-full text-sm rounded-lg border-gray-200">
      <option value="">ทั้งหมด</option>
      @foreach($events as $e)
        <option value="{{ $e->id }}" {{ request('event_id') == $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="block text-xs font-medium text-gray-600 mb-1">Action</label>
    <select name="action" class="w-full text-sm rounded-lg border-gray-200">
      <option value="">ทั้งหมด</option>
      @foreach(['create', 'update', 'delete', 'recalc', 'feature'] as $a)
        <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ $a }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="block text-xs font-medium text-gray-600 mb-1">โดย</label>
    <select name="role" class="w-full text-sm rounded-lg border-gray-200">
      <option value="">ทั้งหมด</option>
      @foreach(['photographer', 'admin', 'system'] as $r)
        <option value="{{ $r }}" {{ request('role') === $r ? 'selected' : '' }}>{{ $r }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="block text-xs font-medium text-gray-600 mb-1">User ID</label>
    <input type="text" name="user_id" value="{{ request('user_id') }}" class="w-full text-sm rounded-lg border-gray-200">
  </div>
  <div class="flex items-end">
    <button class="w-full text-sm px-4 py-2 rounded-lg bg-indigo-500 text-white hover:bg-indigo-600">
      <i class="bi bi-funnel mr-1"></i> กรอง
    </button>
  </div>
</form>

{{-- Logs table --}}
<div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50">
      <tr>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">เวลา</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">อีเวนต์ / แพ็กเกจ</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Action</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">การเปลี่ยน (Diff)</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">โดย</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IP</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @forelse($logs as $log)
      @php
        $diff = $log->diff();
        $actionStyle = match($log->action) {
            'create'  => 'bg-emerald-100 text-emerald-700',
            'update'  => 'bg-blue-100 text-blue-700',
            'delete'  => 'bg-red-100 text-red-700',
            'recalc'  => 'bg-purple-100 text-purple-700',
            'feature' => 'bg-amber-100 text-amber-700',
            default   => 'bg-gray-100 text-gray-700',
        };
        $roleStyle = match($log->changed_by_role) {
            'admin'        => 'bg-purple-100 text-purple-700',
            'photographer' => 'bg-indigo-100 text-indigo-700',
            'system'       => 'bg-gray-100 text-gray-600',
            default        => 'bg-gray-100 text-gray-600',
        };
      @endphp
      <tr class="hover:bg-gray-50/50">
        <td class="px-4 py-3 align-top">
          <div class="text-xs font-medium">{{ $log->created_at?->format('d/m H:i') }}</div>
          <div class="text-[10px] text-gray-400">{{ $log->created_at?->diffForHumans() }}</div>
        </td>
        <td class="px-4 py-3 align-top max-w-[220px]">
          <div class="text-xs font-medium truncate">{{ $log->event->name ?? '—' }}</div>
          <div class="text-[10px] text-gray-500">
            #{{ $log->package_id }} · {{ optional($log->package)->name ?? ($log->old_values['name'] ?? $log->new_values['name'] ?? '?') }}
          </div>
        </td>
        <td class="px-4 py-3 align-top">
          <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $actionStyle }}">{{ $log->action }}</span>
          @if($log->reason)
            <div class="text-[10px] text-gray-400 mt-1 max-w-[180px]">{{ $log->reason }}</div>
          @endif
        </td>
        <td class="px-4 py-3 align-top max-w-[280px]">
          @if(empty($diff))
            <span class="text-xs text-gray-400">—</span>
          @else
            <div class="text-[11px] space-y-0.5">
              @foreach($diff as $field => $change)
                @if(in_array($field, ['price', 'original_price', 'discount_pct', 'photo_count', 'is_featured', 'is_active']))
                  <div class="flex items-center gap-1">
                    <span class="text-gray-500 font-mono">{{ $field }}</span>
                    <span class="text-red-500 line-through">{{ is_numeric($change['old']) ? number_format((float) $change['old'], 2) : json_encode($change['old']) }}</span>
                    <i class="bi bi-arrow-right text-gray-400"></i>
                    <span class="text-emerald-600 font-bold">{{ is_numeric($change['new']) ? number_format((float) $change['new'], 2) : json_encode($change['new']) }}</span>
                  </div>
                @endif
              @endforeach
            </div>
          @endif
        </td>
        <td class="px-4 py-3 align-top">
          <div>
            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium {{ $roleStyle }}">{{ $log->changed_by_role ?? 'unknown' }}</span>
          </div>
          @if($log->changedBy)
            <div class="text-[10px] text-gray-500 mt-0.5">{{ $log->changedBy->name ?? '—' }}</div>
            <div class="text-[10px] text-gray-400">#{{ $log->changed_by }}</div>
          @endif
        </td>
        <td class="px-4 py-3 align-top">
          <code class="text-[10px] text-gray-500">{{ $log->ip_address ?? '—' }}</code>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-center py-12 text-gray-400">ไม่มีข้อมูล</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-4">{{ $logs->links() }}</div>
@endsection
