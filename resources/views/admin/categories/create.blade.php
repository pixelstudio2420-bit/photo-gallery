@extends('layouts.admin')

@section('title', 'เพิ่มหมวดหมู่')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-plus-lg mr-2 text-indigo-500"></i>เพิ่มหมวดหมู่
  </h4>
  <a href="{{ route('admin.categories.index') }}" class="bg-indigo-50 text-indigo-600 rounded-lg font-medium px-5 py-2 inline-flex items-center gap-1 transition hover:bg-indigo-100">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if($errors->any())
<div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-4">
  <ul class="mb-0 text-sm list-disc list-inside">
    @foreach($errors->all() as $error)
    <li>{{ $error }}</li>
    @endforeach
  </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.categories.store') }}">
  @csrf

  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-5">
      <div class="mb-3">
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">ชื่อหมวดหมู่ <span class="text-red-500">*</span></label>
        <input type="text" name="name" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" value="{{ old('name') }}" required>
      </div>
      <div class="mb-3">
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Slug</label>
        <input type="text" name="slug" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" value="{{ old('slug') }}" placeholder="auto-generate if empty">
      </div>
      <div class="mb-3">
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Icon</label>
        <input type="text" name="icon" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" value="{{ old('icon') }}" placeholder="e.g. camera, image, star">
        <small class="text-gray-500 text-xs mt-1">Bootstrap Icons name (without bi- prefix)</small>
      </div>
      <div class="mb-3">
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">สถานะ</label>
        <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500">
          <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
          <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
      </div>
      <button type="submit" class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-semibold px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700 inline-flex items-center gap-1">
        <i class="bi bi-check-lg mr-1"></i> บันทึก
      </button>
    </div>
  </div>
</form>
@endsection
