@extends('layouts.admin')
@section('title', ($mode === 'create' ? 'สร้างแผนใหม่' : 'แก้ไขแผน: ' . $plan->name))

@php
  $storageGb = $plan->storage_bytes ? round($plan->storage_bytes / (1024 ** 3), 2) : 5;
  $maxFileMb = $plan->max_file_size_bytes ? round($plan->max_file_size_bytes / (1024 ** 2), 0) : null;
  $features  = implode("\n", (array) ($plan->features_json ?? []));
@endphp

@section('content')
<div class="flex items-center justify-between flex-wrap gap-2 mb-4">
  <h4 class="font-bold tracking-tight flex items-center gap-2">
    <i class="bi bi-{{ $mode === 'create' ? 'plus-circle' : 'pencil-square' }} text-indigo-500"></i>
    {{ $mode === 'create' ? 'สร้างแผนใหม่' : 'แก้ไขแผน: ' . $plan->name }}
  </h4>
  <a href="{{ route('admin.user-storage.plans.index') }}" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-sm">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if($errors->any())
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-3">
    <div class="font-semibold mb-1"><i class="bi bi-exclamation-triangle-fill mr-1"></i>โปรดตรวจสอบข้อมูล:</div>
    <ul class="list-disc pl-5 text-xs">
      @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
    </ul>
  </div>
@endif

<form method="POST" action="{{ $mode === 'create' ? route('admin.user-storage.plans.store') : route('admin.user-storage.plans.update', $plan) }}" class="space-y-5">
  @csrf
  @if($mode === 'edit') @method('PUT') @endif

  <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="text-xs text-gray-500 mb-1 block">ชื่อแผน *</label>
      <input type="text" name="name" required maxlength="120" value="{{ old('name', $plan->name) }}"
             class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
    </div>
    <div>
      <label class="text-xs text-gray-500 mb-1 block">โค้ด (slug) {{ $mode === 'create' ? '— ถ้าเว้นว่างจะสร้างจากชื่อ' : '' }}</label>
      <input type="text" name="code" maxlength="40" value="{{ old('code', $plan->code) }}"
             @if($plan->code === \App\Models\StoragePlan::CODE_FREE) readonly @endif
             class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm font-mono">
    </div>
    <div class="md:col-span-2">
      <label class="text-xs text-gray-500 mb-1 block">คำโปรย (tagline)</label>
      <input type="text" name="tagline" maxlength="160" value="{{ old('tagline', $plan->tagline) }}"
             class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5">
    <h5 class="font-semibold mb-3 text-sm">ราคาและพื้นที่</h5>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div>
        <label class="text-xs text-gray-500 mb-1 block">พื้นที่ (GB) *</label>
        <input type="number" step="0.01" min="0.1" max="10240" name="storage_gb" required
               value="{{ old('storage_gb', $storageGb) }}"
               class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
      </div>
      <div>
        <label class="text-xs text-gray-500 mb-1 block">ขนาดไฟล์สูงสุด (MB)</label>
        <input type="number" step="1" min="1" name="max_file_size_mb"
               value="{{ old('max_file_size_mb', $maxFileMb) }}"
               class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
      </div>
      <div>
        <label class="text-xs text-gray-500 mb-1 block">ราคา/เดือน (บาท) *</label>
        <input type="number" step="1" min="0" name="price_thb" required
               value="{{ old('price_thb', (int) $plan->price_thb) }}"
               class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
      </div>
      <div>
        <label class="text-xs text-gray-500 mb-1 block">ราคา/ปี (บาท)</label>
        <input type="number" step="1" min="0" name="price_annual_thb"
               value="{{ old('price_annual_thb', $plan->price_annual_thb ? (int) $plan->price_annual_thb : '') }}"
               class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
      </div>
      <div>
        <label class="text-xs text-gray-500 mb-1 block">รอบชำระเงิน *</label>
        <select name="billing_cycle" required class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
          <option value="monthly" {{ old('billing_cycle', $plan->billing_cycle) === 'monthly' ? 'selected' : '' }}>รายเดือน</option>
          <option value="annual"  {{ old('billing_cycle', $plan->billing_cycle) === 'annual'  ? 'selected' : '' }}>รายปี</option>
        </select>
      </div>
      <div>
        <label class="text-xs text-gray-500 mb-1 block">จำนวนลิงก์แชร์สูงสุด</label>
        <input type="number" min="0" max="10000" name="max_shared_links"
               value="{{ old('max_shared_links', $plan->max_shared_links ?? 50) }}"
               class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
      </div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5">
    <h5 class="font-semibold mb-3 text-sm">แสดงผล</h5>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div>
        <label class="text-xs text-gray-500 mb-1 block">Badge (ป้าย)</label>
        <input type="text" name="badge" maxlength="40" value="{{ old('badge', $plan->badge) }}"
               placeholder="เช่น ยอดนิยม"
               class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
      </div>
      <div>
        <label class="text-xs text-gray-500 mb-1 block">สี (hex)</label>
        <input type="color" name="color_hex" value="{{ old('color_hex', $plan->color_hex ?? '#6366f1') }}"
               class="w-full h-10 rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10">
      </div>
      <div>
        <label class="text-xs text-gray-500 mb-1 block">ลำดับ (sort) *</label>
        <input type="number" name="sort_order" required min="0" max="999"
               value="{{ old('sort_order', $plan->sort_order) }}"
               class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
      </div>
      <div class="flex flex-col gap-1 mt-5">
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="is_active" value="1" {{ old('is_active', $plan->is_active) ? 'checked' : '' }}>
          เปิดใช้งาน (is_active)
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="is_public" value="1" {{ old('is_public', $plan->is_public) ? 'checked' : '' }}>
          แสดงในหน้า pricing
        </label>
      </div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5">
    <h5 class="font-semibold mb-3 text-sm">ฟีเจอร์ (ข้อความโชว์บนบัตรราคา — บรรทัดละ 1 ข้อ)</h5>
    <textarea name="feature_list" rows="6"
              class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm font-mono"
              placeholder="แชร์ไฟล์ผ่านลิงก์&#10;ดูตัวอย่างไฟล์ในเว็บ&#10;..">{{ old('feature_list', $features) }}</textarea>
    <p class="text-[11px] text-gray-400 mt-1">
      เช่น: <span class="font-mono">แชร์ไฟล์ผ่านลิงก์</span>, <span class="font-mono">ลิงก์หมดอายุอัตโนมัติ</span>
    </p>
  </div>

  <div class="flex items-center justify-end gap-2">
    <a href="{{ route('admin.user-storage.plans.index') }}" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm">
      ยกเลิก
    </a>
    <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold">
      <i class="bi bi-save mr-1"></i>
      {{ $mode === 'create' ? 'สร้างแผน' : 'บันทึก' }}
    </button>
  </div>
</form>
@endsection
