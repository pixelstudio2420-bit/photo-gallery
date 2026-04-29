@extends('layouts.admin')

@section('title', 'จัดการผู้ใช้')

@section('content')

{{-- Page Header --}}
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-people mr-2 text-indigo-500"></i>จัดการผู้ใช้
    </h4>
    <p class="text-gray-500 mb-0 text-sm">จัดการบัญชีผู้ใช้ ตรวจสอบสถานะ และติดตามกิจกรรมการใช้งาน</p>
  </div>
  <div class="flex items-center gap-2">
    <span class="bg-indigo-50 text-indigo-600 text-sm font-medium px-3 py-1.5 rounded-full">{{ number_format($stats['total'] ?? 0) }} คน</span>
    <a href="{{ route('admin.users.export') }}" class="inline-flex items-center bg-emerald-500/10 text-emerald-600 rounded-lg font-medium px-4 py-2 transition hover:bg-emerald-500/20 text-sm">
      <i class="bi bi-download mr-1"></i> Export CSV
    </a>
    <a href="{{ route('admin.users.create') }}" class="inline-flex items-center bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-5 py-2 transition hover:from-indigo-600 hover:to-indigo-700">
      <i class="bi bi-person-plus mr-1"></i> เพิ่มผู้ใช้
    </a>
  </div>
</div>

{{-- Stats Cards --}}
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 gap-3 mb-6">
  {{-- Total --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-indigo-500/10">
        <i class="bi bi-people-fill text-indigo-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['total'] ?? 0) }}</div>
        <small class="text-gray-500">ทั้งหมด</small>
      </div>
    </div>
  </div>
  {{-- Active --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-emerald-500/10">
        <i class="bi bi-check-circle-fill text-emerald-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['active'] ?? 0) }}</div>
        <small class="text-gray-500">ใช้งาน</small>
      </div>
    </div>
  </div>
  {{-- Suspended --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-red-500/10">
        <i class="bi bi-x-circle-fill text-red-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['suspended'] ?? 0) }}</div>
        <small class="text-gray-500">ระงับ</small>
      </div>
    </div>
  </div>
  {{-- Verified --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-blue-500/10">
        <i class="bi bi-patch-check-fill text-blue-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['verified'] ?? 0) }}</div>
        <small class="text-gray-500">ยืนยันแล้ว</small>
      </div>
    </div>
  </div>
  {{-- New Today --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-violet-500/10">
        <i class="bi bi-person-plus-fill text-violet-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['new_today'] ?? 0) }}</div>
        <small class="text-gray-500">ใหม่วันนี้</small>
      </div>
    </div>
  </div>
  {{-- New This Week --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-cyan-500/10">
        <i class="bi bi-calendar-week text-cyan-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['new_this_week'] ?? 0) }}</div>
        <small class="text-gray-500">ใหม่ 7 วัน</small>
      </div>
    </div>
  </div>
  {{-- New This Month --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-amber-500/10">
        <i class="bi bi-calendar-month text-amber-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['new_this_month'] ?? 0) }}</div>
        <small class="text-gray-500">ใหม่ 30 วัน</small>
      </div>
    </div>
  </div>
  {{-- Active Today (Login) --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-green-500/10">
        <i class="bi bi-box-arrow-in-right text-green-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['active_today'] ?? 0) }}</div>
        <small class="text-gray-500">ล็อกอินวันนี้</small>
      </div>
    </div>
  </div>
</div>

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.users.index') }}">
    <div class="af-grid">

      {{-- Search field (span 2 cols) --}}
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="ค้นหาชื่อ อีเมล หรือเบอร์โทร..." value="{{ request('q') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>

      {{-- Status filter --}}
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
          <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
        </select>
      </div>

      {{-- Verified filter --}}
      <div>
        <label class="af-label">ยืนยันอีเมล</label>
        <select name="verified" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="yes" {{ request('verified') === 'yes' ? 'selected' : '' }}>ยืนยันแล้ว</option>
          <option value="no" {{ request('verified') === 'no' ? 'selected' : '' }}>ยังไม่ยืนยัน</option>
        </select>
      </div>

      {{-- Provider filter --}}
      <div>
        <label class="af-label">Provider</label>
        <select name="provider" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="email" {{ request('provider') === 'email' ? 'selected' : '' }}>Email</option>
          <option value="google" {{ request('provider') === 'google' ? 'selected' : '' }}>Google</option>
          <option value="facebook" {{ request('provider') === 'facebook' ? 'selected' : '' }}>Facebook</option>
          <option value="line" {{ request('provider') === 'line' ? 'selected' : '' }}>LINE</option>
        </select>
      </div>

      {{-- Period filter --}}
      <div>
        <label class="af-label">ช่วงเวลา</label>
        <select name="period" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="today" {{ request('period') === 'today' ? 'selected' : '' }}>วันนี้</option>
          <option value="7days" {{ request('period') === '7days' ? 'selected' : '' }}>7 วัน</option>
          <option value="30days" {{ request('period') === '30days' ? 'selected' : '' }}>30 วัน</option>
          <option value="3months" {{ request('period') === '3months' ? 'selected' : '' }}>3 เดือน</option>
        </select>
      </div>

      {{-- Sort --}}
      <div>
        <label class="af-label">เรียงตาม</label>
        <select name="sort" class="af-input">
          <option value="">ใหม่สุด</option>
          <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>ใหม่สุด</option>
          <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>เก่าสุด</option>
          <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>ชื่อ</option>
          <option value="email" {{ request('sort') === 'email' ? 'selected' : '' }}>อีเมล</option>
          <option value="orders" {{ request('sort') === 'orders' ? 'selected' : '' }}>จำนวนสั่งซื้อ</option>
          <option value="last_login" {{ request('sort') === 'last_login' ? 'selected' : '' }}>ล็อกอินล่าสุด</option>
        </select>
      </div>

      {{-- Clear --}}
      <div class="af-actions">
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>

    </div>
  </form>
</div>

{{-- Users Table --}}
<div id="admin-table-area">
<div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-white/[0.02]">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider pl-5">ผู้ใช้</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">เบอร์โทร</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Provider</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">คำสั่งซื้อ</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">รีวิว</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">ยืนยันอีเมล</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ล็อกอินล่าสุด</th>
          <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider pr-5">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
        @forelse($users as $user)
        @php
          $isActive = ($user->status ?? 'active') === 'active';
          $providerMap = [
            'google'   => ['icon' => 'bi-google',   'color' => 'text-red-500',    'bg' => 'bg-red-500/10',    'label' => 'Google'],
            'facebook' => ['icon' => 'bi-facebook',  'color' => 'text-blue-600',   'bg' => 'bg-blue-600/10',   'label' => 'Facebook'],
            'line'     => ['icon' => 'bi-chat-fill', 'color' => 'text-green-500',  'bg' => 'bg-green-500/10',  'label' => 'LINE'],
            'email'    => ['icon' => 'bi-envelope',  'color' => 'text-gray-500',   'bg' => 'bg-gray-500/10',   'label' => 'Email'],
          ];
          $provider = $providerMap[$user->auth_provider ?? 'email'] ?? $providerMap['email'];
        @endphp
        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
          {{-- User: Avatar + Name + Email --}}
          <td class="pl-5 py-3 px-4">
            <div class="flex items-center gap-3">
              <div class="flex items-center justify-center w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-600 text-white text-xs font-bold flex-shrink-0">
                {{ strtoupper(mb_substr($user->first_name ?? 'U', 0, 1)) }}
              </div>
              <div class="min-w-0">
                <div class="font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $user->first_name }} {{ $user->last_name }}</div>
                <div class="text-xs text-gray-400 truncate">{{ $user->email }}</div>
              </div>
            </div>
          </td>
          {{-- Phone --}}
          <td class="py-3 px-4">
            @if($user->phone)
              <span class="text-gray-700 dark:text-gray-300">{{ $user->phone }}</span>
            @else
              <span class="text-gray-400">&mdash;</span>
            @endif
          </td>
          {{-- Provider --}}
          <td class="py-3 px-4">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $provider['bg'] }} {{ $provider['color'] }}">
              <i class="bi {{ $provider['icon'] }}" style="font-size:0.7rem;"></i>
              {{ $provider['label'] }}
            </span>
          </td>
          {{-- Orders Count --}}
          <td class="py-3 px-4 text-center">
            <span class="inline-flex items-center justify-center min-w-[1.75rem] px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-500/10 text-indigo-600">
              {{ $user->orders_count ?? 0 }}
            </span>
          </td>
          {{-- Reviews Count --}}
          <td class="py-3 px-4 text-center">
            <span class="inline-flex items-center justify-center min-w-[1.75rem] px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-600">
              {{ $user->reviews_count ?? 0 }}
            </span>
          </td>
          {{-- Email Verified --}}
          <td class="py-3 px-4 text-center">
            @if($user->email_verified_at)
              <i class="bi bi-check-circle-fill text-emerald-500"></i>
            @else
              <i class="bi bi-x-circle-fill text-red-400"></i>
            @endif
          </td>
          {{-- Status --}}
          <td class="py-3 px-4">
            @if($isActive)
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-600">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                Active
              </span>
            @else
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-500/10 text-red-500">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span>
                Suspended
              </span>
            @endif
          </td>
          {{-- Last Login --}}
          <td class="py-3 px-4">
            @if($user->last_login_at)
              <small class="text-gray-500">{{ $user->last_login_at->diffForHumans() }}</small>
            @else
              <span class="text-gray-400">&mdash;</span>
            @endif
          </td>
          {{-- Actions --}}
          <td class="py-3 pr-5 px-4 text-right">
            <div class="flex gap-1 justify-end">
              {{-- View --}}
              <a href="{{ route('admin.users.show', $user->id) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-600/[0.08] text-blue-600 transition hover:bg-blue-600/[0.15]" title="ดูรายละเอียด">
                <i class="bi bi-eye text-sm"></i>
              </a>
              {{-- Edit --}}
              <a href="{{ route('admin.users.edit', $user->id) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-500/[0.08] text-indigo-600 transition hover:bg-indigo-500/[0.15]" title="แก้ไข">
                <i class="bi bi-pencil text-sm"></i>
              </a>
              {{-- Block / Unblock Toggle --}}
              <form method="POST" action="{{ route('admin.users.update', $user->id) }}" class="inline">
                @csrf @method('PUT')
                <input type="hidden" name="toggle_block" value="1">
                @if($isActive)
                  <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/[0.08] text-red-500 transition hover:bg-red-500/[0.15]" title="ระงับผู้ใช้">
                    <i class="bi bi-lock text-sm"></i>
                  </button>
                @else
                  <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/[0.08] text-emerald-600 transition hover:bg-emerald-500/[0.15]" title="ปลดบล็อค">
                    <i class="bi bi-unlock text-sm"></i>
                  </button>
                @endif
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9" class="text-center py-12">
            <i class="bi bi-people text-4xl text-gray-300 dark:text-gray-600"></i>
            <p class="text-gray-500 mt-2 mb-0 text-sm">ไม่พบผู้ใช้</p>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
</div>{{-- end #admin-table-area --}}

<div id="admin-pagination-area">
@if($users->hasPages())
<div class="flex justify-center mt-4">{{ $users->withQueryString()->links() }}</div>
@endif
</div>{{-- end #admin-pagination-area --}}
@endsection
