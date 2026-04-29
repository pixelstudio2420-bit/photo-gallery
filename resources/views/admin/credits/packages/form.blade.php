@extends('layouts.admin')
@section('title', ($mode === 'edit' ? 'แก้ไข' : 'สร้าง') . ' Credit Package')

@php
    $isEdit  = $mode === 'edit';
    $action  = $isEdit
        ? route('admin.credits.packages.update', $package)
        : route('admin.credits.packages.store');
@endphp

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-box-seam text-indigo-500"></i>
        {{ $isEdit ? 'แก้ไข' : 'สร้าง' }} Credit Package
    </h4>
    <a href="{{ route('admin.credits.packages.index') }}" class="text-sm text-gray-500 hover:underline">
        <i class="bi bi-arrow-left"></i> กลับ
    </a>
</div>

@if ($errors->any())
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 text-rose-800 dark:text-rose-200 rounded-xl p-3 mb-4 text-sm">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-6 max-w-2xl">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-500 mb-1">ชื่อแพ็คเก็จ *</label>
            <input name="name" value="{{ old('name', $package->name) }}" required maxlength="120"
                   class="w-full rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Code (slug)</label>
            <input name="code" value="{{ old('code', $package->code) }}" maxlength="40" pattern="[a-z0-9_\-]+"
                   placeholder="เว้นว่างได้ — ระบบจะสร้างจากชื่อ"
                   class="w-full rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm font-mono">
            <p class="text-[11px] text-gray-400 mt-1">ใช้ใน URL — ตัวพิมพ์เล็ก/ตัวเลข/ขีดเท่านั้น</p>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">ลำดับแสดงผล</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $package->sort_order ?? 0) }}" min="0" max="999"
                   class="w-full rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-500 mb-1">คำอธิบาย (ขาย / ชูจุดเด่น)</label>
            <textarea name="description" rows="2" maxlength="500"
                      class="w-full rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">{{ old('description', $package->description) }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">จำนวนเครดิต *</label>
            <input type="number" name="credits" value="{{ old('credits', $package->credits) }}" required min="1" max="1000000"
                   class="w-full rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">ราคา (บาท) *</label>
            <input type="number" step="0.01" name="price_thb" value="{{ old('price_thb', $package->price_thb) }}" required min="0" max="10000000"
                   class="w-full rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">อายุเครดิต (วัน)</label>
            <input type="number" name="validity_days" value="{{ old('validity_days', $package->validity_days ?? 365) }}" min="0" max="3650"
                   class="w-full rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
            <p class="text-[11px] text-gray-400 mt-1">0 = ไม่มีวันหมดอายุ</p>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Badge (ไม่บังคับ)</label>
            <input name="badge" value="{{ old('badge', $package->badge) }}" maxlength="40" placeholder="เช่น POPULAR, BEST VALUE"
                   class="w-full rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">สี (hex)</label>
            <div class="flex items-center gap-2">
                <input type="color" name="color_hex" value="{{ old('color_hex', $package->color_hex ?: '#6366f1') }}"
                       class="h-9 w-14 rounded border border-gray-200 dark:border-white/10 bg-transparent cursor-pointer">
                <span class="text-xs text-gray-500 font-mono">{{ old('color_hex', $package->color_hex ?: '#6366f1') }}</span>
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $package->is_active) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span>เปิดขาย (Active)</span>
            </label>
        </div>
    </div>

    <div class="mt-6 flex items-center justify-end gap-2">
        <a href="{{ route('admin.credits.packages.index') }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">ยกเลิก</a>
        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
            <i class="bi bi-check-lg mr-1"></i>{{ $isEdit ? 'บันทึกการแก้ไข' : 'สร้างแพ็คเก็จ' }}
        </button>
    </div>
</form>
@endsection
