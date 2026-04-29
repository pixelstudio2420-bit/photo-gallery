@extends('layouts.admin')

@section('title', 'จัดการแอดมิน')

@section('content')
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-shield-lock mr-2 text-indigo-500"></i>จัดการแอดมิน
    </h4>
    <p class="text-gray-500 mb-0 text-sm">จัดการบัญชีแอดมิน กำหนดบทบาทและสิทธิ์การเข้าถึง</p>
  </div>
  <a href="{{ route('admin.admins.create') }}" class="inline-flex items-center bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-5 py-2 transition hover:from-indigo-600 hover:to-indigo-700">
    <i class="bi bi-person-plus mr-1"></i> เพิ่มแอดมินใหม่
  </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
  @php
    $allAdminsStats = \App\Models\Admin::all();
    $totalAdmins = $allAdminsStats->count();
    $activeAdmins = $allAdminsStats->where('is_active', true)->count();
    $roleCounts = $allAdminsStats->groupBy('role')->map->count();
  @endphp
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-indigo-500/10">
        <i class="bi bi-people-fill text-indigo-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ $totalAdmins }}</div>
        <small class="text-gray-500">ทั้งหมด</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-emerald-500/10">
        <i class="bi bi-check-circle-fill text-emerald-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ $activeAdmins }}</div>
        <small class="text-gray-500">ใช้งานอยู่</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-red-500/10">
        <i class="bi bi-shield-fill-check text-red-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ $roleCounts['superadmin'] ?? 0 }}</div>
        <small class="text-gray-500">Super Admin</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-blue-600/10">
        <i class="bi bi-shield-fill text-blue-600 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ ($roleCounts['admin'] ?? 0) + ($roleCounts['editor'] ?? 0) }}</div>
        <small class="text-gray-500">Admin & Editor</small>
      </div>
    </div>
  </div>
</div>

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.admins.index') }}">
    <div class="af-grid">
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="ชื่อหรืออีเมล..." value="{{ request('q') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>ใช้งาน</option>
          <option value="blocked" {{ request('status') === 'blocked' ? 'selected' : '' }}>ระงับ</option>
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

{{-- Admin List --}}
<div id="admin-table-area">
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider pl-5">แอดมิน</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">อีเมล</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">บทบาท</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สิทธิ์</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">เข้าสู่ระบบล่าสุด</th>
          <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider pr-5">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @foreach($admins as $admin)
        @php $roleInfo = $admin->role_info; @endphp
        <tr class="hover:bg-gray-50/50 transition">
          <td class="pl-5 py-3 px-4">
            <div class="flex items-center gap-2">
              <div class="flex items-center justify-center w-9 h-9 rounded-lg font-bold text-sm" style="background:linear-gradient(135deg,{{ $roleInfo['color'] }}20,{{ $roleInfo['color'] }}10);color:{{ $roleInfo['color'] }};">
                {{ mb_strtoupper(mb_substr($admin->first_name ?? '', 0, 1, 'UTF-8'), 'UTF-8') }}{{ mb_strtoupper(mb_substr($admin->last_name ?? '', 0, 1, 'UTF-8'), 'UTF-8') }}
              </div>
              <div>
                <div class="font-semibold">{{ $admin->full_name }}</div>
                @if($admin->id === Auth::guard('admin')->id())
                <small class="text-indigo-600">(คุณ)</small>
                @endif
              </div>
            </div>
          </td>
          <td class="py-3 px-4">
            <span class="text-gray-500">{{ $admin->email }}</span>
          </td>
          <td class="py-3 px-4">
            <span class="inline-flex items-center gap-1 rounded-full text-xs px-3 py-1" style="background:{{ $roleInfo['color'] }}15;color:{{ $roleInfo['color'] }};">
              <i class="bi {{ $roleInfo['icon'] }}" style="font-size:0.7rem;"></i>
              {{ $roleInfo['thai'] }}
            </span>
          </td>
          <td class="py-3 px-4">
            @if($admin->isSuperAdmin())
              <span class="text-gray-500 text-sm"><i class="bi bi-infinity mr-1"></i>ทั้งหมด</span>
            @else
              @php $permCount = count($admin->permissions ?? []); $totalPerms = count(\App\Models\Admin::allPermissionKeys()); @endphp
              <span class="text-sm">
                <span class="font-semibold" style="color:{{ $permCount === $totalPerms ? '#10b981' : ($permCount > 0 ? '#f59e0b' : '#ef4444') }}">{{ $permCount }}</span>
                <span class="text-gray-500"> / {{ $totalPerms }}</span>
              </span>
            @endif
          </td>
          <td class="py-3 px-4">
            @if($admin->is_active)
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-500">
                <i class="bi bi-circle-fill mr-1" style="font-size:6px;"></i>ใช้งาน
              </span>
            @else
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-500/10 text-red-500">
                <i class="bi bi-circle-fill mr-1" style="font-size:6px;"></i>ระงับ
              </span>
            @endif
          </td>
          <td class="py-3 px-4">
            @if($admin->last_login_at)
              <small class="text-gray-500">{{ $admin->last_login_at->diffForHumans() }}</small>
            @else
              <small class="text-gray-500">ยังไม่เคย</small>
            @endif
          </td>
          <td class="py-3 pr-5 px-4 text-right">
            <div class="flex gap-1 justify-end">
              @if($admin->isSuperAdmin() && $admin->id !== Auth::guard('admin')->id())
                <span class="text-gray-500 text-sm"><i class="bi bi-lock"></i></span>
              @else
                <a href="{{ route('admin.admins.edit', $admin) }}" class="inline-flex items-center justify-center text-sm px-2.5 py-1 rounded-lg bg-blue-600/[0.08] text-blue-600 transition hover:bg-blue-600/[0.15]" title="แก้ไข">
                  <i class="bi bi-pencil"></i>
                </a>
                @if(!$admin->isSuperAdmin() && $admin->id !== Auth::guard('admin')->id())
                  <form method="POST" action="{{ route('admin.admins.toggle-status', $admin) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center text-sm px-2.5 py-1 rounded-lg transition" style="background:rgba({{ $admin->is_active ? '245,158,11' : '16,185,129' }},0.08);color:{{ $admin->is_active ? '#f59e0b' : '#10b981' }};" title="{{ $admin->is_active ? 'ระงับ' : 'เปิดใช้งาน' }}">
                      <i class="bi {{ $admin->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                    </button>
                  </form>
                  <form method="POST" action="{{ route('admin.admins.destroy', $admin) }}" class="inline" onsubmit="return confirm('ยืนยันลบบัญชี {{ $admin->full_name }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center justify-center text-sm px-2.5 py-1 rounded-lg bg-red-500/[0.08] text-red-500 transition hover:bg-red-500/[0.15]" title="ลบ">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                @endif
              @endif
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
</div>{{-- end #admin-table-area --}}

<div id="admin-pagination-area">
@if($admins->hasPages())
<div class="flex justify-center mt-4">{{ $admins->withQueryString()->links() }}</div>
@endif
</div>

{{-- Role Legend --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mt-6">
  <div class="p-5">
    <h6 class="font-semibold mb-3"><i class="bi bi-info-circle mr-1 text-indigo-600"></i>คำอธิบายบทบาท</h6>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      @foreach(\App\Models\Admin::ROLES as $roleKey => $roleData)
      <div class="p-3 rounded-xl" style="background:{{ $roleData['color'] }}08;border-left:3px solid {{ $roleData['color'] }};">
        <div class="flex items-center gap-2 mb-1">
          <i class="bi {{ $roleData['icon'] }}" style="color:{{ $roleData['color'] }};"></i>
          <span class="font-semibold">{{ $roleData['thai'] }}</span>
          <span class="text-gray-500 text-sm">({{ $roleData['label'] }})</span>
        </div>
        <small class="text-gray-500">{{ $roleData['desc'] }}</small>
      </div>
      @endforeach
    </div>
  </div>
</div>
@endsection
