@extends('layouts.admin')

@section('title', 'แก้ไขแอดมิน — ' . $admin->full_name)

@section('content')
<div class="flex items-center gap-2 mb-6">
  <a href="{{ route('admin.admins.index') }}" class="inline-flex items-center justify-center text-sm px-3 py-1.5 rounded-lg bg-gray-500/[0.08] text-gray-500 transition hover:bg-gray-500/[0.15]">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h4 class="font-bold text-xl tracking-tight">
    <i class="bi bi-pencil-square mr-2 text-indigo-500"></i>แก้ไขแอดมิน
  </h4>
  @php $roleInfo = $admin->role_info; @endphp
  <span class="inline-flex items-center gap-1 rounded-full text-xs px-3 py-1" style="background:{{ $roleInfo['color'] }}15;color:{{ $roleInfo['color'] }};">
    <i class="bi {{ $roleInfo['icon'] }}" style="font-size:0.7rem;"></i>
    {{ $roleInfo['thai'] }}
  </span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2">
    <form method="POST" action="{{ route('admin.admins.update', $admin) }}">
      @csrf
      @method('PUT')

      {{-- Basic Info --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
        <div class="p-5">
          <h6 class="font-semibold mb-3"><i class="bi bi-person mr-1 text-indigo-600"></i>ข้อมูลพื้นฐาน</h6>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อ <span class="text-red-500">*</span></label>
              <input type="text" name="first_name" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('first_name') border-red-500 @enderror" value="{{ old('first_name', $admin->first_name) }}" required>
              @error('first_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">นามสกุล <span class="text-red-500">*</span></label>
              <input type="text" name="last_name" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('last_name') border-red-500 @enderror" value="{{ old('last_name', $admin->last_name) }}" required>
              @error('last_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">อีเมล <span class="text-red-500">*</span></label>
              <input type="email" name="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('email') border-red-500 @enderror" value="{{ old('email', $admin->email) }}" required>
              @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">รหัสผ่านใหม่ <span class="text-gray-500">(เว้นว่างถ้าไม่เปลี่ยน)</span></label>
              <input type="password" name="password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('password') border-red-500 @enderror" minlength="8">
              @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">ยืนยันรหัสผ่าน</label>
              <input type="password" name="password_confirmation" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" minlength="8">
            </div>
          </div>
        </div>
      </div>

      @if(!$admin->isSuperAdmin())
      {{-- Role Selection --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
        <div class="p-5">
          <h6 class="font-semibold mb-3"><i class="bi bi-shield mr-1 text-indigo-600"></i>บทบาท</h6>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach(\App\Models\Admin::ROLES as $roleKey => $roleData)
              @if($roleKey === 'superadmin') @continue @endif
              <label class="block">
                <input type="radio" name="role" value="{{ $roleKey }}" class="hidden role-radio"
                    {{ old('role', $admin->role) === $roleKey ? 'checked' : '' }}>
                <div class="role-option p-4 border-2 border-gray-200 rounded-xl cursor-pointer transition-all hover:border-indigo-300">
                  <div class="flex items-center gap-2 mb-1">
                    <i class="bi {{ $roleData['icon'] }} text-lg" style="color:{{ $roleData['color'] }};"></i>
                    <span class="font-semibold">{{ $roleData['thai'] }}</span>
                    <span class="text-gray-500 text-sm">({{ $roleData['label'] }})</span>
                  </div>
                  <small class="text-gray-500">{{ $roleData['desc'] }}</small>
                </div>
              </label>
            @endforeach
          </div>
        </div>
      </div>

      {{-- Permissions --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
        <div class="p-5">
          <div class="flex justify-between items-center mb-3">
            <h6 class="font-semibold"><i class="bi bi-key mr-1 text-indigo-600"></i>สิทธิ์การเข้าถึง</h6>
            <div class="flex gap-2">
              <button type="button" class="text-sm px-3 py-1.5 rounded-lg bg-emerald-500/[0.08] text-emerald-500 transition hover:bg-emerald-500/[0.15] text-xs" onclick="selectAllPerms()">
                <i class="bi bi-check-all mr-1"></i>เลือกทั้งหมด
              </button>
              <button type="button" class="text-sm px-3 py-1.5 rounded-lg bg-gray-500/[0.08] text-gray-500 transition hover:bg-gray-500/[0.15] text-xs" onclick="deselectAllPerms()">
                ยกเลิกทั้งหมด
              </button>
            </div>
          </div>

          @php $currentPerms = old('permissions', $admin->permissions ?? []); @endphp

          @foreach(\App\Models\Admin::PERMISSION_GROUPS as $groupName => $perms)
          <div class="mb-4">
            <div class="font-medium text-sm mb-2 text-slate-500">{{ $groupName }}</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
              @foreach($perms as $permKey => $permData)
              <label class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg cursor-pointer transition hover:bg-gray-100 perm-label">
                <input type="checkbox" name="permissions[]" value="{{ $permKey }}" class="rounded-md border-gray-300 text-indigo-600 focus:ring-indigo-500 perm-cb"
                    {{ in_array($permKey, $currentPerms) ? 'checked' : '' }}>
                <i class="bi {{ $permData['icon'] }} text-indigo-500 text-sm"></i>
                <span class="text-sm">{{ $permData['label'] }}</span>
              </label>
              @endforeach
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @else
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
        <div class="p-5 text-center">
          <i class="bi bi-shield-fill-check text-red-500 text-3xl"></i>
          <h6 class="font-semibold mt-2">Super Admin</h6>
          <p class="text-gray-500 text-sm">Super Admin มีสิทธิ์เข้าถึงทุกฟีเจอร์โดยอัตโนมัติ ไม่สามารถเปลี่ยนบทบาทหรือจำกัดสิทธิ์ได้</p>
        </div>
      </div>
      @endif

      <div class="flex gap-2">
        <button type="submit" class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-8 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700">
          <i class="bi bi-check-lg mr-1"></i> บันทึกการเปลี่ยนแปลง
        </button>
        <a href="{{ route('admin.admins.index') }}" class="bg-gray-500/[0.08] text-gray-500 rounded-lg font-medium px-6 py-2.5 transition hover:bg-gray-500/[0.15]">
          ยกเลิก
        </a>
      </div>
    </form>
  </div>

  {{-- Sidebar Info --}}
  <div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
      <div class="p-5">
        <h6 class="font-semibold mb-3"><i class="bi bi-person-badge mr-1 text-indigo-600"></i>ข้อมูลบัญชี</h6>
        <div class="flex flex-col gap-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500">สร้างเมื่อ</span>
            <span class="font-medium">{{ $admin->created_at ? $admin->created_at->format('d M Y H:i') : '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">เข้าสู่ระบบล่าสุด</span>
            <span class="font-medium">{{ $admin->last_login_at ? $admin->last_login_at->diffForHumans() : 'ยังไม่เคย' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">สถานะ</span>
            @if($admin->is_active)
              <span class="font-medium text-emerald-500"><i class="bi bi-circle-fill mr-1" style="font-size:6px;"></i>ใช้งาน</span>
            @else
              <span class="font-medium text-red-500"><i class="bi bi-circle-fill mr-1" style="font-size:6px;"></i>ระงับ</span>
            @endif
          </div>
        </div>
      </div>
    </div>

    @if(!$admin->isSuperAdmin())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <div class="p-5">
        <h6 class="font-semibold mb-3"><i class="bi bi-lightbulb mr-1 text-amber-500"></i>คำแนะนำ</h6>
        <ul class="space-y-2 text-sm">
          <li class="flex gap-2">
            <i class="bi bi-dot text-indigo-600 text-2xl leading-none flex-shrink-0"></i>
            <span class="text-gray-500">การเปลี่ยนบทบาทจะเซ็ตสิทธิ์เป็นค่าเริ่มต้นของบทบาทนั้น</span>
          </li>
          <li class="flex gap-2">
            <i class="bi bi-dot text-indigo-600 text-2xl leading-none flex-shrink-0"></i>
            <span class="text-gray-500">เว้นรหัสผ่านว่างถ้าไม่ต้องการเปลี่ยน</span>
          </li>
          <li class="flex gap-2">
            <i class="bi bi-dot text-indigo-600 text-2xl leading-none flex-shrink-0"></i>
            <span class="text-gray-500">สิทธิ์ที่เปลี่ยนจะมีผลทันทีในครั้งถัดไปที่เข้าใช้งาน</span>
          </li>
        </ul>
      </div>
    </div>
    @endif
  </div>
</div>
@endsection

@push('styles')
<style>
.role-radio:checked + .role-option {
  border-color: #6366f1 !important;
  background: rgba(99,102,241,0.04);
}
.perm-label:hover { background: #f1f5f9 !important; }
.perm-cb:checked + i { color: #2563eb !important; }
</style>
@endpush

@push('scripts')
<script>
function selectAllPerms() {
  document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = true);
}
function deselectAllPerms() {
  document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = false);
}
</script>
@endpush
