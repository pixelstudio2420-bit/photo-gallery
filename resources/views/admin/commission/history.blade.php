@extends('layouts.admin')

@section('title', 'ประวัติการเปลี่ยนแปลงคอมมิชชั่น')

@section('content')
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-clock-history mr-2 text-indigo-500"></i>ประวัติการเปลี่ยนแปลง
    </h4>
    <p class="text-gray-500 mb-0 text-sm">บันทึกการเปลี่ยนแปลงอัตราคอมมิชชั่นของช่างภาพทั้งหมด</p>
  </div>
  <a href="{{ route('admin.commission.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition dark:bg-white/[0.06] dark:text-gray-300">
    <i class="bi bi-arrow-left mr-1"></i> กลับหน้าหลัก
  </a>
</div>

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.commission.history') }}">
    <div class="af-grid">
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="ค้นหาช่างภาพ..." value="{{ request('q') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>
      <div>
        <label class="af-label">แหล่งที่มา</label>
        <select name="source" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="manual" {{ request('source') === 'manual' ? 'selected' : '' }}>manual</option>
          <option value="bulk" {{ request('source') === 'bulk' ? 'selected' : '' }}>bulk</option>
          <option value="tier_auto" {{ request('source') === 'tier_auto' ? 'selected' : '' }}>tier_auto</option>
          <option value="settings" {{ request('source') === 'settings' ? 'selected' : '' }}>settings</option>
        </select>
      </div>
      <div class="af-actions">
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>
    </div>
  </form>
</div>

{{-- History Table --}}
<div id="admin-table-area">
<div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-white/[0.02]">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider pl-5">วันที่</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ช่างภาพ</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">อัตราเดิม</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">อัตราใหม่</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">การเปลี่ยนแปลง</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">แหล่งที่มา</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider pr-5">เหตุผล</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
        @forelse($logs as $log)
        @php
          $diff = $log->new_rate - $log->old_rate;
          $sourceBadge = [
            'manual'    => ['bg' => 'bg-indigo-500/10', 'text' => 'text-indigo-600'],
            'bulk'      => ['bg' => 'bg-blue-500/10',   'text' => 'text-blue-600'],
            'tier_auto' => ['bg' => 'bg-amber-500/10',  'text' => 'text-amber-600'],
            'settings'  => ['bg' => 'bg-gray-500/10',   'text' => 'text-gray-600'],
          ];
          $badge = $sourceBadge[$log->source] ?? $sourceBadge['settings'];
        @endphp
        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
          <td class="pl-5 py-3 px-4 text-gray-500 whitespace-nowrap">
            {{ $log->created_at->format('d/m/Y H:i') }}
          </td>
          <td class="py-3 px-4">
            <span class="font-medium text-gray-800 dark:text-gray-100">{{ $log->photographer->display_name ?? 'Unknown' }}</span>
          </td>
          <td class="py-3 px-4 text-center">
            <span class="font-mono font-semibold text-red-500">{{ number_format($log->old_rate, 0) }}%</span>
          </td>
          <td class="py-3 px-4 text-center">
            <span class="font-mono font-semibold text-emerald-600">{{ number_format($log->new_rate, 0) }}%</span>
          </td>
          <td class="py-3 px-4 text-center">
            @if($diff > 0)
              <span class="inline-flex items-center gap-0.5 font-mono font-semibold text-emerald-600">
                <i class="bi bi-arrow-up-short"></i>+{{ number_format($diff, 0) }}%
              </span>
            @elseif($diff < 0)
              <span class="inline-flex items-center gap-0.5 font-mono font-semibold text-red-500">
                <i class="bi bi-arrow-down-short"></i>{{ number_format($diff, 0) }}%
              </span>
            @else
              <span class="text-gray-400 font-mono">0%</span>
            @endif
          </td>
          <td class="py-3 px-4 text-center">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $badge['bg'] }} {{ $badge['text'] }}">
              {{ $log->source }}
            </span>
          </td>
          <td class="py-3 px-4 pr-5 text-gray-500 text-sm max-w-xs truncate">
            {{ $log->reason ?? '-' }}
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="text-center py-12">
            <i class="bi bi-clock-history text-4xl text-gray-300 dark:text-gray-600"></i>
            <p class="text-gray-500 mt-2 mb-0">ยังไม่มีประวัติการเปลี่ยนแปลง</p>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
</div>{{-- end #admin-table-area --}}

<div id="admin-pagination-area">
@if($logs->hasPages())
<div class="flex justify-center mt-4">{{ $logs->withQueryString()->links() }}</div>
@endif
</div>
@endsection
