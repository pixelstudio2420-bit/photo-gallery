@extends('layouts.admin')

@section('title', 'ระดับคอมมิชชั่น')

@section('content')
@php
  // Tier rates feed into photographers when applyTiers() runs, so they
  // must respect the same floor/ceiling as direct edits — otherwise a
  // "Gold" tier at 99% would bypass the 95% cap the admin configured.
  $cMin = (float) \App\Models\AppSetting::get('min_commission_rate', 0);
  $cMax = (float) \App\Models\AppSetting::get('max_commission_rate', 100);
@endphp
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-award mr-2 text-indigo-500"></i>ระดับคอมมิชชั่น
    </h4>
    <p class="text-gray-500 mb-0 text-sm">กำหนดอัตราคอมมิชชั่นตามระดับรายได้ของช่างภาพ</p>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('admin.commission.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition dark:bg-white/[0.06] dark:text-gray-300">
      <i class="bi bi-arrow-left mr-1"></i> กลับ
    </a>
    <form action="{{ route('admin.commission.tiers.apply') }}" method="POST" onsubmit="return confirm('ต้องการปรับระดับคอมมิชชั่นช่างภาพทั้งหมดตามเกณฑ์รายได้อัตโนมัติหรือไม่?');">
      @csrf
      <button type="submit" class="inline-flex items-center bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-4 py-2 transition hover:from-indigo-600 hover:to-indigo-700 text-sm">
        <i class="bi bi-lightning mr-1"></i> ปรับตามระดับอัตโนมัติ
      </button>
    </form>
  </div>
</div>

{{-- Success / Error Messages --}}
@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400">
  <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400">
  <i class="bi bi-exclamation-circle mr-1"></i> {{ session('error') }}
</div>
@endif
@if($errors->any())
<div class="mb-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400">
  <i class="bi bi-exclamation-circle mr-1"></i>
  <ul class="list-disc list-inside mt-1">
    @foreach($errors->all() as $error)
      <li>{{ $error }}</li>
    @endforeach
  </ul>
</div>
@endif

{{-- Add New Tier --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06] mb-6" x-data="{ showForm: false }">
  <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
    <h6 class="font-semibold text-sm"><i class="bi bi-plus-circle mr-1 text-indigo-500"></i>เพิ่มระดับใหม่</h6>
    <button @click="showForm = !showForm" class="text-xs text-indigo-500 hover:text-indigo-700 font-medium transition">
      <span x-text="showForm ? 'ยกเลิก' : 'เพิ่มระดับ'"></span>
      <i class="bi" :class="showForm ? 'bi-x-lg' : 'bi-plus'"></i>
    </button>
  </div>
  <div x-show="showForm" x-transition x-cloak class="p-6">
    <form action="{{ route('admin.commission.tiers.store') }}" method="POST">
      @csrf
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ชื่อระดับ <span class="text-red-500">*</span></label>
          <input type="text" name="name" required placeholder="เช่น Gold, Platinum" value="{{ old('name') }}"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">รายได้ขั้นต่ำ (฿) <span class="text-red-500">*</span></label>
          <input type="number" name="min_revenue" required min="0" step="0.01" placeholder="0.00" value="{{ old('min_revenue') }}"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">อัตราคอมมิชชั่น (%) <span class="text-red-500">*</span> <small class="text-gray-400 font-normal">({{ $cMin }}–{{ $cMax }})</small></label>
          <input type="number" name="commission_rate" required min="{{ $cMin }}" max="{{ $cMax }}" step="0.01" placeholder="70" value="{{ old('commission_rate') }}"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">สี</label>
          <div class="flex items-center gap-2">
            <input type="color" name="color" value="{{ old('color', '#6366f1') }}"
              class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer dark:border-white/[0.1]">
            <input type="text" value="{{ old('color', '#6366f1') }}" readonly
              class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50 dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200"
              x-ref="colorText">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ไอคอน (Bootstrap Icons)</label>
          <input type="text" name="icon" placeholder="bi-award" value="{{ old('icon', 'bi-award') }}"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">
        </div>
        <div class="flex items-end">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" checked
              class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm text-gray-700 dark:text-gray-300">เปิดใช้งาน</span>
          </label>
        </div>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">คำอธิบาย</label>
        <textarea name="description" rows="2" placeholder="รายละเอียดของระดับนี้..."
          class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">{{ old('description') }}</textarea>
      </div>
      <div class="flex justify-end">
        <button type="submit" class="inline-flex items-center bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-medium px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700 text-sm">
          <i class="bi bi-plus-lg mr-1"></i> สร้างระดับ
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Existing Tiers --}}
@if($tiers->count())
<div class="space-y-4">
  @foreach($tiers as $tier)
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]" x-data="{ editing: false }">
    {{-- Tier Display --}}
    <div class="p-6" x-show="!editing">
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-xl flex items-center justify-center shrink-0" style="background: {{ $tier->color }}15; color: {{ $tier->color }};">
            <i class="bi {{ $tier->icon }} text-2xl"></i>
          </div>
          <div>
            <div class="flex items-center gap-2 mb-1">
              <h6 class="font-bold text-base">{{ $tier->name }}</h6>
              @if($tier->is_active)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">ใช้งาน</span>
              @else
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400">ปิด</span>
              @endif
            </div>
            <div class="flex items-center gap-4 text-sm text-gray-500">
              <span><i class="bi bi-cash mr-1"></i>รายได้ขั้นต่ำ ฿{{ number_format($tier->min_revenue, 0) }}</span>
              <span class="font-semibold" style="color: {{ $tier->color }};">
                <i class="bi bi-percent mr-0.5"></i>{{ number_format($tier->commission_rate, 1) }}%
              </span>
            </div>
            @if($tier->description)
              <p class="text-sm text-gray-400 mt-1 mb-0">{{ $tier->description }}</p>
            @endif
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button @click="editing = true" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition dark:bg-white/[0.06] dark:text-gray-300">
            <i class="bi bi-pencil mr-1"></i> แก้ไข
          </button>
          <form action="{{ route('admin.commission.tiers.destroy', $tier) }}" method="POST" onsubmit="return confirm('ต้องการลบระดับ &laquo;{{ $tier->name }}&raquo; หรือไม่?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-red-50 text-red-600 hover:bg-red-100 transition dark:bg-red-500/10 dark:text-red-400">
              <i class="bi bi-trash mr-1"></i> ลบ
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- Edit Form --}}
    <div x-show="editing" x-transition x-cloak class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h6 class="font-semibold text-sm"><i class="bi bi-pencil-square mr-1 text-indigo-500"></i>แก้ไขระดับ: {{ $tier->name }}</h6>
        <button @click="editing = false" class="text-xs text-gray-400 hover:text-gray-600 transition">
          <i class="bi bi-x-lg"></i> ยกเลิก
        </button>
      </div>
      <form action="{{ route('admin.commission.tiers.update', $tier) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ชื่อระดับ <span class="text-red-500">*</span></label>
            <input type="text" name="name" required value="{{ $tier->name }}"
              class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">รายได้ขั้นต่ำ (฿) <span class="text-red-500">*</span></label>
            <input type="number" name="min_revenue" required min="0" step="0.01" value="{{ $tier->min_revenue }}"
              class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">อัตราคอมมิชชั่น (%) <span class="text-red-500">*</span> <small class="text-gray-400 font-normal">({{ $cMin }}–{{ $cMax }})</small></label>
            <input type="number" name="commission_rate" required min="{{ $cMin }}" max="{{ $cMax }}" step="0.01" value="{{ $tier->commission_rate }}"
              class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">สี</label>
            <div class="flex items-center gap-2">
              <input type="color" name="color" value="{{ $tier->color }}"
                class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer dark:border-white/[0.1]">
              <span class="text-sm text-gray-500 font-mono">{{ $tier->color }}</span>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ไอคอน (Bootstrap Icons)</label>
            <input type="text" name="icon" value="{{ $tier->icon }}"
              class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">
          </div>
          <div class="flex items-end">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="is_active" value="1" {{ $tier->is_active ? 'checked' : '' }}
                class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
              <span class="text-sm text-gray-700 dark:text-gray-300">เปิดใช้งาน</span>
            </label>
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">คำอธิบาย</label>
          <textarea name="description" rows="2"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">{{ $tier->description }}</textarea>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="inline-flex items-center bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-medium px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700 text-sm">
            <i class="bi bi-check-lg mr-1"></i> บันทึก
          </button>
        </div>
      </form>
    </div>
  </div>
  @endforeach
</div>
@else
{{-- Empty State --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
  <div class="py-16 text-center">
    <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center mx-auto mb-4">
      <i class="bi bi-award text-3xl text-indigo-400"></i>
    </div>
    <h6 class="font-semibold text-gray-700 dark:text-gray-200 mb-1">ยังไม่มีระดับคอมมิชชั่น</h6>
    <p class="text-sm text-gray-400 mb-4">สร้างระดับเพื่อกำหนดอัตราคอมมิชชั่นตามรายได้ของช่างภาพ</p>
    <button onclick="document.querySelector('[x-data]').__x.$data.showForm = true; window.scrollTo({top: 0, behavior: 'smooth'});"
      class="inline-flex items-center bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-medium px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700 text-sm">
      <i class="bi bi-plus-lg mr-1"></i> สร้างระดับแรก
    </button>
  </div>
</div>
@endif
@endsection
