@extends('layouts.photographer')

@section('title', 'สร้างอีเวนต์ใหม่')

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-plus-circle',
  'eyebrow'  => 'การทำงาน',
  'title'    => 'สร้างอีเวนต์ใหม่',
  'subtitle' => 'ตั้งชื่อ · วันที่ · ราคาต่อภาพ · ตั้งค่าการเข้าถึง',
  'actions'  => '<a href="'.route('photographer.events.index').'" class="pg-btn-ghost"><i class="bi bi-arrow-left"></i> กลับ</a>',
])

<div class="pg-card">
  <div class="p-5">
    <form action="{{ route('photographer.events.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่ออีเวนต์ <span class="text-red-500">*</span></label>
          <input type="text" name="name" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('name') border-red-500 @enderror" value="{{ old('name') }}" required>
          @error('name')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">สถานที่</label>
          <input type="text" name="location" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('location') border-red-500 @enderror" value="{{ old('location') }}">
          @error('location')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">รายละเอียด</label>
          <textarea name="description" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('description') border-red-500 @enderror" rows="3">{{ old('description') }}</textarea>
          @error('description')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">วันที่ถ่าย <span class="text-red-500">*</span></label>
            <input type="date" name="shoot_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('shoot_date') border-red-500 @enderror" value="{{ old('shoot_date') }}" required>
            @error('shoot_date')
              <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              ราคาต่อภาพ (THB) <span class="text-red-500">*</span>
            </label>
            <input type="number" name="price_per_photo"
                   class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('price_per_photo') border-red-500 @enderror"
                   value="{{ old('price_per_photo', $minPrice ?? 100) }}"
                   step="0.01"
                   min="{{ $minPrice ?? 100 }}"
                   required>
            <p class="text-gray-500 text-xs mt-1">
              ขั้นต่ำ: <span class="font-semibold">{{ number_format($minPrice ?? 100, 2) }}</span> บาท/ภาพ
            </p>
            @error('price_per_photo')
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
            @enderror
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">การมองเห็น</label>
            <select name="visibility" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('visibility') border-red-500 @enderror">
              <option value="public" {{ old('visibility') === 'public' ? 'selected' : '' }}>สาธารณะ</option>
              <option value="private" {{ old('visibility') === 'private' ? 'selected' : '' }}>ส่วนตัว</option>
              <option value="unlisted" {{ old('visibility') === 'unlisted' ? 'selected' : '' }}>ไม่แสดงในรายการ</option>
            </select>
            @error('visibility')
              <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">สถานะ</label>
          <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('status') border-red-500 @enderror">
            <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>ร่าง</option>
            <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>เปิดใช้งาน</option>
            <option value="published" {{ old('status') === 'published' ? 'selected' : '' }}>เผยแพร่</option>
          </select>
          @error('status')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
      </div>
      <div class="mt-6">
        <button type="submit" class="bg-gradient-to-br from-indigo-600 to-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg border-none inline-flex items-center gap-1 transition hover:shadow-lg">
          <i class="bi bi-check-lg mr-1"></i> สร้างอีเวนต์
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
