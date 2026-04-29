@extends('layouts.admin')

@section('title', 'เพิ่มคูปอง')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-ticket-perforated mr-2 text-indigo-500"></i>เพิ่มคูปอง
  </h4>
  <a href="{{ route('admin.coupons.index') }}" class="bg-indigo-50 text-indigo-600 rounded-lg font-medium px-4 py-1.5 text-sm inline-flex items-center gap-1 transition hover:bg-indigo-100">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
  <div class="p-5">
    <form method="POST" action="{{ route('admin.coupons.store') }}">
      @csrf
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">รหัสคูปอง <span class="text-red-500">*</span></label>
          <input type="text" name="code" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="เช่น SAVE20" value="{{ old('code') }}">
          @error('code') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อคูปอง <span class="text-red-500">*</span></label>
          <input type="text" name="name" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="ชื่อคูปอง" value="{{ old('name') }}">
          @error('name') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">รายละเอียด</label>
          <textarea name="description" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" rows="2" placeholder="รายละเอียดคูปอง (ถ้ามี)">{{ old('description') }}</textarea>
          @error('description') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ประเภท <span class="text-red-500">*</span></label>
          <select name="type" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500">
            <option value="percent" {{ old('type') === 'percent' ? 'selected' : '' }}>เปอร์เซ็นต์ (%)</option>
            <option value="fixed" {{ old('type') === 'fixed' ? 'selected' : '' }}>จำนวนเงินคงที่ (฿)</option>
          </select>
          @error('type') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">มูลค่า <span class="text-red-500">*</span></label>
          <input type="number" name="value" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="0" step="0.01" value="{{ old('value') }}">
          @error('value') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ยอดสั่งซื้อขั้นต่ำ</label>
          <input type="number" name="min_order" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="0.00" step="0.01" value="{{ old('min_order') }}">
          @error('min_order') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ส่วนลดสูงสุด</label>
          <input type="number" name="max_discount" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="0.00" step="0.01" value="{{ old('max_discount') }}">
          @error('max_discount') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">จำนวนการใช้สูงสุด</label>
          <input type="number" name="usage_limit" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="ไม่จำกัด" value="{{ old('usage_limit') }}">
          @error('usage_limit') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">จำกัดต่อผู้ใช้</label>
          <input type="number" name="per_user_limit" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="ไม่จำกัด" value="{{ old('per_user_limit') }}">
          @error('per_user_limit') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">วันเริ่มต้น</label>
          <input type="datetime-local" name="start_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" value="{{ old('start_date') }}">
          @error('start_date') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">วันหมดอายุ</label>
          <input type="datetime-local" name="end_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" value="{{ old('end_date') }}">
          @error('end_date') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="md:col-span-2">
          <div class="flex items-center gap-2">
            <input class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
            <label class="text-sm font-medium text-gray-700" for="is_active">เปิดใช้งาน</label>
          </div>
        </div>
      </div>
      <hr class="my-4 border-gray-100">
      <div class="flex gap-2">
        <button type="submit" class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700 inline-flex items-center gap-1">
          <i class="bi bi-check-lg mr-1"></i> บันทึก
        </button>
        <a href="{{ route('admin.coupons.index') }}" class="bg-gray-100 text-gray-500 rounded-lg font-medium px-6 py-2.5 transition hover:bg-gray-200">
          ยกเลิก
        </a>
      </div>
    </form>
  </div>
</div>
@endsection
