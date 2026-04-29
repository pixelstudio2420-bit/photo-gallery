@extends('layouts.admin')

@section('title', 'จัดการช่างภาพ')

@section('content')
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-camera2 mr-2 text-indigo-500"></i>จัดการช่างภาพ
    </h4>
    <p class="text-gray-500 mb-0 text-sm">จัดการบัญชีช่างภาพ อนุมัติ ระงับ และกำหนดค่าคอมมิชชั่น</p>
  </div>
  <a href="{{ route('admin.photographers.create') }}" class="inline-flex items-center bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-5 py-2 transition hover:from-indigo-600 hover:to-indigo-700">
    <i class="bi bi-person-plus mr-1"></i> เพิ่มช่างภาพ
  </a>
</div>

{{-- Stats Cards --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-indigo-500/10">
        <i class="bi bi-people-fill text-indigo-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ $stats['total'] }}</div>
        <small class="text-gray-500">ทั้งหมด</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-amber-500/10">
        <i class="bi bi-hourglass-split text-amber-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ $stats['pending'] }}</div>
        <small class="text-gray-500">รอตรวจสอบ</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-emerald-500/10">
        <i class="bi bi-check-circle-fill text-emerald-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ $stats['approved'] }}</div>
        <small class="text-gray-500">อนุมัติแล้ว</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-red-500/10">
        <i class="bi bi-x-circle-fill text-red-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ $stats['suspended'] }}</div>
        <small class="text-gray-500">ระงับ</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-emerald-600/10">
        <i class="bi bi-wallet2 text-emerald-600 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-lg">{{ number_format($stats['total_earnings'], 0) }}</div>
        <small class="text-gray-500">รายได้รวม (฿)</small>
      </div>
    </div>
  </div>
</div>

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.photographers.index') }}">
    <div class="af-grid">
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="ชื่อ, อีเมล, รหัสช่างภาพ..." value="{{ request('q') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>รอตรวจสอบ</option>
          <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>อนุมัติแล้ว</option>
          <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>ระงับ</option>
        </select>
      </div>
      <div>
        <label class="af-label">เรียงตาม</label>
        <select name="sort" class="af-input">
          <option value="">ค่าเริ่มต้น</option>
          <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>ใหม่ล่าสุด</option>
          <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>เก่าสุด</option>
          <option value="events" {{ request('sort') === 'events' ? 'selected' : '' }}>อีเวนต์มากสุด</option>
          <option value="rating" {{ request('sort') === 'rating' ? 'selected' : '' }}>คะแนนสูงสุด</option>
          <option value="commission" {{ request('sort') === 'commission' ? 'selected' : '' }}>คอมมิชชั่นสูงสุด</option>
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

{{-- Photographer List --}}
<div id="admin-table-area">
<div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-white/[0.02]">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider pl-5">ช่างภาพ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">รหัส</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">อีเวนต์</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">คะแนน</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">คอมมิชชั่น</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">วันสมัคร</th>
          <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider pr-5">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
        @forelse($photographers as $pg)
        @php
          $avgRating = $pg->reviews->avg('rating') ?? 0;
          $statusMap = [
            'approved'  => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-600', 'label' => 'อนุมัติ', 'dot' => 'bg-emerald-500'],
            'pending'   => ['bg' => 'bg-amber-500/10',   'text' => 'text-amber-600',   'label' => 'รอตรวจสอบ', 'dot' => 'bg-amber-500'],
            'suspended' => ['bg' => 'bg-red-500/10',     'text' => 'text-red-500',     'label' => 'ระงับ', 'dot' => 'bg-red-500'],
          ];
          $st = $statusMap[$pg->status] ?? $statusMap['pending'];
        @endphp
        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
          <td class="pl-5 py-3 px-4">
            <div class="flex items-center gap-3">
              <x-avatar :src="$pg->avatar"
                   :name="$pg->display_name ?? 'P'"
                   :user-id="$pg->user_id ?? $pg->id"
                   size="sm"
                   rounded="rounded" />
              <div>
                <a href="{{ route('admin.photographers.show', $pg) }}" class="font-semibold text-gray-800 dark:text-gray-100 hover:text-indigo-600 transition">
                  {{ $pg->display_name }}
                </a>
                <div class="text-xs text-gray-400">{{ $pg->user->email ?? '-' }}</div>
              </div>
            </div>
          </td>
          <td class="py-3 px-4">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-500/10 text-indigo-600">
              {{ $pg->photographer_code }}
            </span>
          </td>
          <td class="py-3 px-4">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $st['bg'] }} {{ $st['text'] }}">
              <span class="w-1.5 h-1.5 rounded-full {{ $st['dot'] }} mr-1.5"></span>
              {{ $st['label'] }}
            </span>
          </td>
          <td class="py-3 px-4 text-center">
            <span class="font-semibold">{{ $pg->events_count }}</span>
          </td>
          <td class="py-3 px-4 text-center">
            @if($pg->reviews_count > 0)
              <div class="flex items-center justify-center gap-1">
                <i class="bi bi-star-fill text-amber-400 text-xs"></i>
                <span class="font-semibold">{{ number_format($avgRating, 1) }}</span>
                <span class="text-gray-400 text-xs">({{ $pg->reviews_count }})</span>
              </div>
            @else
              <span class="text-gray-400">-</span>
            @endif
          </td>
          <td class="py-3 px-4 text-center">
            <span class="font-semibold text-indigo-600">{{ number_format($pg->commission_rate, 0) }}%</span>
          </td>
          <td class="py-3 px-4">
            <small class="text-gray-500">{{ $pg->created_at?->format('d/m/Y') }}</small>
          </td>
          <td class="py-3 pr-5 px-4 text-right">
            <div class="flex gap-1 justify-end">
              {{-- View --}}
              <a href="{{ route('admin.photographers.show', $pg) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-500/[0.08] text-indigo-600 transition hover:bg-indigo-500/[0.15]" title="ดูรายละเอียด">
                <i class="bi bi-eye text-sm"></i>
              </a>
              {{-- Edit --}}
              <a href="{{ route('admin.photographers.edit', $pg) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-600/[0.08] text-blue-600 transition hover:bg-blue-600/[0.15]" title="แก้ไข">
                <i class="bi bi-pencil text-sm"></i>
              </a>
              {{-- Approve (pending only) --}}
              @if($pg->status === 'pending')
              <form method="POST" action="{{ route('admin.photographers.approve', $pg) }}" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/[0.08] text-emerald-600 transition hover:bg-emerald-500/[0.15]" title="อนุมัติ">
                  <i class="bi bi-check-lg text-sm"></i>
                </button>
              </form>
              @endif
              {{-- Toggle Status (approved/suspended) --}}
              @if($pg->status !== 'pending')
              <form method="POST" action="{{ route('admin.photographers.toggle-status', $pg) }}" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition {{ $pg->status === 'approved' ? 'bg-amber-500/[0.08] text-amber-600 hover:bg-amber-500/[0.15]' : 'bg-emerald-500/[0.08] text-emerald-600 hover:bg-emerald-500/[0.15]' }}" title="{{ $pg->status === 'approved' ? 'ระงับ' : 'เปิดใช้งาน' }}">
                  <i class="bi {{ $pg->status === 'approved' ? 'bi-pause-circle' : 'bi-play-circle' }} text-sm"></i>
                </button>
              </form>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8" class="text-center py-12">
            <i class="bi bi-camera2 text-4xl text-gray-300 dark:text-gray-600"></i>
            <p class="text-gray-500 mt-2 mb-0">ไม่พบช่างภาพ</p>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
</div>{{-- end #admin-table-area --}}

<div id="admin-pagination-area">
@if($photographers->hasPages())
<div class="flex justify-center mt-4">{{ $photographers->withQueryString()->links() }}</div>
@endif
</div>
@endsection
