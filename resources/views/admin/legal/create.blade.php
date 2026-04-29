@extends('layouts.admin')

@section('title', 'เพิ่มหน้ากฎหมายใหม่')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0 tracking-tight text-slate-800 dark:text-gray-100">
    <i class="bi bi-plus-circle mr-2 text-indigo-500"></i>เพิ่มหน้ากฎหมายใหม่
  </h4>
  <a href="{{ route('admin.legal.index') }}"
     class="bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 rounded-lg font-medium px-4 py-2 inline-flex items-center gap-1 text-sm">
    <i class="bi bi-arrow-left"></i> กลับ
  </a>
</div>

@if($errors->any())
<div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-4">
  <ul class="mb-0 text-sm list-disc list-inside">
    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
  </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.legal.store') }}">
  @csrf
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">ชื่อหน้า <span class="text-red-500">*</span></label>
          <input type="text" name="title"
                 class="w-full px-4 py-2.5 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 dark:text-gray-100 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                 value="{{ old('title') }}" placeholder="เช่น นโยบายคุกกี้" required>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">Slug (URL) <span class="text-red-500">*</span></label>
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 font-mono">/legal/</span>
            <input type="text" name="slug"
                   class="flex-1 px-4 py-2.5 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 dark:text-gray-100 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono"
                   value="{{ old('slug') }}" placeholder="cookie-policy" required>
          </div>
          <p class="text-xs text-gray-500 mt-1">ตัวเล็ก คั่นด้วยขีด เช่น <code>cookie-policy</code>, <code>shipping-policy</code></p>
        </div>
        <div class="mb-2">
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">เนื้อหา</label>
          <textarea name="content" rows="24"
                    class="w-full px-4 py-3 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 dark:text-gray-100 rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="<h2>หัวข้อ</h2><p>เนื้อหา...</p>">{{ old('content') }}</textarea>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">คำอธิบาย SEO (meta description)</label>
        <textarea name="meta_description" rows="2" maxlength="500"
                  class="w-full px-4 py-2.5 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 dark:text-gray-100 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('meta_description') }}</textarea>
      </div>
    </div>

    <div class="space-y-4">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
        <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-3 text-sm">
          <i class="bi bi-gear text-indigo-500 mr-1"></i> การเผยแพร่
        </h6>
        <div class="mb-3">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_published" value="0">
            <input type="checkbox" name="is_published" value="1" {{ old('is_published', true) ? 'checked' : '' }}
                   class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">เผยแพร่ทันทีหลังสร้าง</span>
          </label>
        </div>
        <div class="mb-3">
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1">วันที่มีผลบังคับใช้</label>
          <input type="date" name="effective_date"
                 class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 dark:text-gray-100 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                 value="{{ old('effective_date', now()->toDateString()) }}">
        </div>
        <div class="mb-0">
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1">บันทึกการเปลี่ยนแปลง</label>
          <input type="text" name="change_note" maxlength="500"
                 class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 dark:text-gray-100 rounded-lg text-sm"
                 value="{{ old('change_note', 'Initial version') }}">
        </div>
      </div>

      <button type="submit"
              class="w-full bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-semibold px-4 py-2.5 hover:from-indigo-600 hover:to-indigo-700 transition inline-flex items-center justify-center gap-2">
        <i class="bi bi-check-lg"></i> สร้างหน้าใหม่
      </button>
    </div>
  </div>
</form>
@endsection
