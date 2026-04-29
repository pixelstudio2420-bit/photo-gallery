@extends('layouts.admin')

@section('title', 'ประวัติการเข้าสู่ระบบ')

@section('content')
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-shield-lock mr-2 text-indigo-500"></i>ประวัติการเข้าสู่ระบบ
    </h4>
    <p class="text-gray-500 mb-0 text-sm">ติดตามการล็อกอิน ล็อกเอาต์ และความพยายามที่น่าสงสัย</p>
  </div>
  <span class="bg-indigo-50 text-indigo-600 text-sm font-medium px-3 py-1.5 rounded-full">
    {{ number_format($logs->total()) }} รายการ
  </span>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-indigo-500/10">
        <i class="bi bi-box-arrow-in-right text-indigo-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['total_logins']) }}</div>
        <small class="text-gray-500">เข้าสู่ระบบทั้งหมด</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-red-500/10">
        <i class="bi bi-x-circle-fill text-red-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['failed_24h']) }}</div>
        <small class="text-gray-500">ล็อกอินล้มเหลว (24h)</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-amber-500/10">
        <i class="bi bi-exclamation-triangle-fill text-amber-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['suspicious_7d']) }}</div>
        <small class="text-gray-500">น่าสงสัย (7 วัน)</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-emerald-500/10">
        <i class="bi bi-globe2 text-emerald-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($stats['unique_ips_24h']) }}</div>
        <small class="text-gray-500">IP ไม่ซ้ำ (24h)</small>
      </div>
    </div>
  </div>
</div>

{{-- Filters --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4">
  <form method="GET" action="{{ url()->current() }}" class="p-4 grid grid-cols-1 md:grid-cols-6 gap-3">
    <input type="text" name="q" value="{{ request('q') }}"
           placeholder="ค้นหาชื่อ / email / IP"
           class="md:col-span-2 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">

    <select name="guard" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
      <option value="">ทุก guard</option>
      <option value="user"         {{ request('guard')==='user' ? 'selected' : '' }}>User</option>
      <option value="admin"        {{ request('guard')==='admin' ? 'selected' : '' }}>Admin</option>
      <option value="photographer" {{ request('guard')==='photographer' ? 'selected' : '' }}>Photographer</option>
    </select>

    <select name="event_type" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
      <option value="">ทุกกิจกรรม</option>
      <option value="login"        {{ request('event_type')==='login' ? 'selected' : '' }}>Login</option>
      <option value="logout"       {{ request('event_type')==='logout' ? 'selected' : '' }}>Logout</option>
      <option value="failed"       {{ request('event_type')==='failed' ? 'selected' : '' }}>Failed</option>
      <option value="2fa_required" {{ request('event_type')==='2fa_required' ? 'selected' : '' }}>2FA Required</option>
      <option value="2fa_success"  {{ request('event_type')==='2fa_success' ? 'selected' : '' }}>2FA Success</option>
      <option value="2fa_failed"   {{ request('event_type')==='2fa_failed' ? 'selected' : '' }}>2FA Failed</option>
    </select>

    <input type="date" name="date_from" value="{{ request('date_from') }}"
           class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
    <input type="date" name="date_to" value="{{ request('date_to') }}"
           class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">

    <div class="md:col-span-6 flex items-center justify-between gap-3 flex-wrap">
      <label class="inline-flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="suspicious" value="1" {{ request()->boolean('suspicious') ? 'checked' : '' }}
               class="rounded border-gray-300 text-indigo-500 focus:ring-indigo-300">
        แสดงเฉพาะรายการน่าสงสัย
      </label>

      <div class="flex gap-2">
        <a href="{{ url()->current() }}" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">ล้างฟิลเตอร์</a>
        <button type="submit" class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-4 py-2 text-sm">
          <i class="bi bi-funnel mr-1"></i>กรอง
        </button>
      </div>
    </div>
  </form>
</div>

{{-- Table --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">วันที่</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ผู้ใช้</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Guard</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">กิจกรรม</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">อุปกรณ์</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IP</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ประเทศ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">สถานะ</th>
        </tr>
      </thead>
      <tbody>
        @forelse($logs as $log)
          @php
            $actor = $log->admin ?? $log->user;
            $actorName = $actor
              ? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? ''))
              : '-';
            if (empty($actorName)) $actorName = $actor?->email ?? '-';
            $badgeClass = match($log->event_type) {
              'login'        => 'bg-indigo-50 text-indigo-600',
              '2fa_success'  => 'bg-emerald-50 text-emerald-600',
              'logout'       => 'bg-gray-100 text-gray-600',
              'failed'       => 'bg-red-50 text-red-600',
              '2fa_failed'   => 'bg-red-50 text-red-600',
              '2fa_required' => 'bg-amber-50 text-amber-600',
              default        => 'bg-gray-100 text-gray-600',
            };
            $guardBadge = match($log->guard) {
              'admin'        => 'bg-purple-50 text-purple-600',
              'photographer' => 'bg-blue-50 text-blue-600',
              default        => 'bg-emerald-50 text-emerald-600',
            };
          @endphp
          <tr class="hover:bg-gray-50 border-t border-gray-50 {{ $log->is_suspicious ? 'bg-red-50/30' : '' }}">
            <td class="px-4 py-3 text-gray-700 text-xs whitespace-nowrap">
              {{ $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') : '-' }}
            </td>
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800">{{ $actorName }}</div>
              @if($actor?->email)
                <div class="text-xs text-gray-500">{{ $actor->email }}</div>
              @endif
            </td>
            <td class="px-4 py-3">
              <span class="{{ $guardBadge }} text-xs font-medium px-2 py-1 rounded-full">
                {{ ucfirst($log->guard) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="{{ $badgeClass }} text-xs font-medium px-2 py-1 rounded-full">
                {{ $log->event_type }}
              </span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-700">
              <i class="bi {{ $log->icon }} mr-1"></i>{{ $log->browser_info }}
            </td>
            <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ $log->ip_address ?? '-' }}</td>
            <td class="px-4 py-3 text-xs text-gray-500">
              @if($log->country)
                {{ $log->country }}{{ $log->city ? ' / ' . $log->city : '' }}
              @else
                -
              @endif
            </td>
            <td class="px-4 py-3">
              @if($log->is_suspicious)
                <span class="bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded-full">
                  <i class="bi bi-exclamation-triangle-fill mr-1"></i>น่าสงสัย
                </span>
              @else
                <span class="text-gray-400 text-xs">-</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="text-center text-gray-400 py-12">
              <i class="bi bi-shield-lock" style="font-size:2rem;opacity:0.3;"></i>
              <p class="mt-2 mb-0">ไม่พบข้อมูล</p>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@if($logs->hasPages())
<div class="flex justify-center mt-6">
  {{ $logs->links() }}
</div>
@endif
@endsection
