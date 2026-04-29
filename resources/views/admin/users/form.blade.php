@extends('layouts.admin')

@section('title', isset($user) ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้ใหม่')

@section('content')
<div class="flex justify-between items-center mb-4">
  <div>
    <h4 class="font-bold mb-0 tracking-tight">
      <i class="bi bi-{{ isset($user) ? 'person-gear' : 'person-plus' }} mr-2 text-indigo-500"></i>
      {{ isset($user) ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้ใหม่' }}
    </h4>
    @if(isset($user))
      <p class="text-gray-500 text-sm mt-1 mb-0">{{ $user->first_name }} {{ $user->last_name }}</p>
    @endif
  </div>
  <a href="{{ route('admin.users.index') }}" class="bg-indigo-50 text-indigo-600 rounded-lg font-medium px-5 py-2 inline-flex items-center gap-1 transition hover:bg-indigo-100">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

{{-- Info Box --}}
<div class="bg-blue-50 border border-blue-200 text-blue-700 rounded-xl p-4 mb-4">
  <div class="flex items-center gap-2">
    <i class="bi bi-info-circle-fill text-blue-500"></i>
    <span class="text-sm">ฟิลด์ที่มีเครื่องหมาย <span class="text-red-500 font-semibold">*</span> จำเป็นต้องกรอก</span>
  </div>
</div>

{{-- Validation Errors --}}
@if($errors->any())
<div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-4">
  <div class="flex items-center gap-2 mb-1">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span class="font-semibold text-sm">พบข้อผิดพลาด กรุณาตรวจสอบข้อมูล</span>
  </div>
  <ul class="mb-0 text-sm list-disc list-inside">
    @foreach($errors->all() as $error)
    <li>{{ $error }}</li>
    @endforeach
  </ul>
</div>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
  <div class="p-5">
    <form action="{{ isset($user) ? route('admin.users.update', $user) : route('admin.users.store') }}" method="POST">
      @csrf
      @if(isset($user)) @method('PUT') @endif

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Row 1: ชื่อ / นามสกุล --}}
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อ <span class="text-red-500">*</span></label>
          <input type="text" name="first_name"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('first_name') border-red-500 @enderror"
            value="{{ old('first_name', $user->first_name ?? '') }}" required>
          @error('first_name')
            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">นามสกุล <span class="text-red-500">*</span></label>
          <input type="text" name="last_name"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('last_name') border-red-500 @enderror"
            value="{{ old('last_name', $user->last_name ?? '') }}" required>
          @error('last_name')
            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>

        {{-- Row 2: อีเมล / เบอร์โทร --}}
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">อีเมล <span class="text-red-500">*</span></label>
          <input type="email" name="email"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('email') border-red-500 @enderror"
            value="{{ old('email', $user->email ?? '') }}" required>
          @error('email')
            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">เบอร์โทร</label>
          <input type="tel" name="phone"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('phone') border-red-500 @enderror"
            value="{{ old('phone', $user->phone ?? '') }}">
          @error('phone')
            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>

        {{-- Row 3: รหัสผ่าน / ยืนยันรหัสผ่าน --}}
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">
            รหัสผ่าน
            @if(!isset($user))
              <span class="text-red-500">*</span>
            @else
              <span class="text-gray-500 text-xs">(เว้นว่างถ้าไม่ต้องการเปลี่ยน)</span>
            @endif
          </label>
          <input type="password" name="password"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('password') border-red-500 @enderror"
            {{ !isset($user) ? 'required' : '' }}>
          @error('password')
            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">
            ยืนยันรหัสผ่าน
            @if(!isset($user))
              <span class="text-red-500">*</span>
            @endif
          </label>
          <input type="password" name="password_confirmation"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            {{ !isset($user) ? 'required' : '' }}>
        </div>

        {{-- Row 4: สถานะ / ยืนยันอีเมล --}}
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">สถานะ</label>
          <select name="status"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('status') border-red-500 @enderror">
            <option value="active" {{ old('status', $user->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="suspended" {{ old('status', $user->status ?? '') === 'suspended' ? 'selected' : '' }}>Suspended</option>
          </select>
          @error('status')
            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ยืนยันอีเมล</label>
          <div class="flex items-center mt-2">
            <input type="hidden" name="email_verified" value="0">
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" name="email_verified" value="1"
                class="sr-only peer"
                @checked(old('email_verified', isset($user) && $user->email_verified_at ? '1' : '0') === '1')>
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              <span class="ml-3 text-sm text-gray-700">อีเมลได้รับการยืนยันแล้ว</span>
            </label>
          </div>
          @error('email_verified')
            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>

      </div>

      {{-- Submit Button --}}
      <div class="mt-5">
        <button type="submit" class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700 inline-flex items-center gap-1">
          <i class="bi bi-check-lg mr-1"></i> {{ isset($user) ? 'อัปเดต' : 'บันทึก' }}
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
