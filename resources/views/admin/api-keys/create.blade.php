@extends('layouts.admin')

@section('title', 'สร้าง API Key')

@section('content')
<div class="max-w-2xl mx-auto space-y-5">

  <div>
    <a href="{{ route('admin.api-keys.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
      <i class="bi bi-chevron-left"></i> กลับไปรายการ
    </a>
    <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100 mt-2">
      <i class="bi bi-key-fill text-amber-500 mr-2"></i>สร้าง API Key ใหม่
    </h1>
  </div>

  <form method="POST" action="{{ route('admin.api-keys.store') }}" class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-6 space-y-4">
    @csrf

    <div>
      <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">ชื่อ <span class="text-red-500">*</span></label>
      <input type="text" name="name" required maxlength="100" value="{{ old('name') }}"
             placeholder="เช่น Mobile App, Integration with XXX"
             class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg bg-white dark:bg-slate-900 dark:text-gray-200">
      <p class="text-xs text-gray-500 mt-1">ใช้สำหรับระบุว่า key นี้ถูกใช้ที่ไหน</p>
    </div>

    <div>
      <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Scopes (สิทธิ์)</label>
      <div class="grid grid-cols-2 gap-2">
        @foreach([
          'read:notifications'  => 'อ่าน notifications',
          'write:notifications' => 'จัดการ notifications',
          'read:cart'           => 'อ่านตะกร้า',
          'write:cart'          => 'แก้ไขตะกร้า',
          'read:events'         => 'อ่านอีเวนต์',
          'read:orders'         => 'อ่านคำสั่งซื้อ',
          'read:blog'           => 'อ่านบทความ',
          'write:blog'          => 'เขียน/แก้ไขบทความ',
          'blog:ai'             => 'ใช้ AI สร้างบทความ',
          'admin:*'             => 'Admin ทั้งหมด (อันตราย)',
        ] as $key => $label)
        <label class="flex items-center gap-2 p-2 rounded-lg border border-gray-100 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/[0.02] cursor-pointer">
          <input type="checkbox" name="scopes[]" value="{{ $key }}" class="rounded border-gray-300">
          <div>
            <div class="text-sm font-medium">{{ $label }}</div>
            <code class="text-xs text-gray-500">{{ $key }}</code>
          </div>
        </label>
        @endforeach
      </div>
      <p class="text-xs text-gray-500 mt-2">ไม่เลือก = key สามารถใช้ทุก scope</p>
    </div>

    <div>
      <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">IP Whitelist (ถ้ามี)</label>
      <input type="text" name="allowed_ips" value="{{ old('allowed_ips') }}"
             placeholder="เช่น 203.0.113.1, 10.0.0.5 (คั่นด้วย ,)"
             class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg bg-white dark:bg-slate-900 dark:text-gray-200 font-mono text-sm">
      <p class="text-xs text-gray-500 mt-1">หากไม่ใส่ = อนุญาตทุก IP</p>
    </div>

    <div>
      <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Rate Limit (requests/minute)</label>
      <input type="number" name="rate_limit_per_minute" value="{{ old('rate_limit_per_minute', 60) }}" min="1" max="1000"
             class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg bg-white dark:bg-slate-900 dark:text-gray-200">
    </div>

    <div>
      <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">วันหมดอายุ (ถ้ามี)</label>
      <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"
             class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg bg-white dark:bg-slate-900 dark:text-gray-200">
      <p class="text-xs text-gray-500 mt-1">แนะนำให้ตั้งวันหมดอายุ เช่น 1 ปี</p>
    </div>

    <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg">
      <p class="text-sm text-amber-800">
        <strong><i class="bi bi-exclamation-triangle"></i> สำคัญ:</strong>
        หลังจากสร้าง key เสร็จ ระบบจะแสดง plaintext key <strong>เพียงครั้งเดียว</strong> เท่านั้น
        กรุณาคัดลอกและเก็บไว้ในที่ปลอดภัย (เช่น password manager)
      </p>
    </div>

    <div class="flex gap-3 pt-4">
      <a href="{{ route('admin.api-keys.index') }}" class="px-5 py-2.5 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 rounded-xl font-medium hover:bg-gray-50 dark:hover:bg-white/5">ยกเลิก</a>
      <button type="submit" class="flex-1 px-5 py-2.5 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-medium hover:shadow-lg">
        <i class="bi bi-plus-lg mr-1"></i>สร้าง API Key
      </button>
    </div>
  </form>
</div>
@endsection
