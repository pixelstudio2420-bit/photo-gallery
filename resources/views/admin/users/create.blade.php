@extends('layouts.admin')

@section('title', 'เพิ่มผู้ใช้')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-person-plus mr-2 text-indigo-500"></i>เพิ่มผู้ใช้
  </h4>
  <a href="{{ route('admin.users.index') }}" class="bg-indigo-50 text-indigo-600 rounded-lg font-medium px-5 py-2 inline-flex items-center gap-1 transition hover:bg-indigo-100">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
  <div class="p-5">
    <form action="{{ route('admin.users.store') }}" method="POST">
      @csrf
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อ <span class="text-red-500">*</span></label>
          <input type="text" name="first_name" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('first_name') border-red-500 @enderror" value="{{ old('first_name') }}" required>
          @error('first_name')
            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">นามสกุล <span class="text-red-500">*</span></label>
          <input type="text" name="last_name" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('last_name') border-red-500 @enderror" value="{{ old('last_name') }}" required>
          @error('last_name')
            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">อีเมล <span class="text-red-500">*</span></label>
          <input type="email" name="email" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('email') border-red-500 @enderror" value="{{ old('email') }}" required>
          @error('email')
            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">รหัสผ่าน <span class="text-red-500">*</span></label>
          <input type="password" name="password" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('password') border-red-500 @enderror" required>
          @error('password')
            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
      </div>
      <div class="mt-4">
        <button type="submit" class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700 inline-flex items-center gap-1">
          <i class="bi bi-check-lg mr-1"></i> บันทึก
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
