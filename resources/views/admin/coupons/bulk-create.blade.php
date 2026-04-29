@extends('layouts.admin')

@section('title', 'สร้างคูปองหลายรายการ')

@section('content')
<div class="max-w-3xl mx-auto space-y-5">

  <div>
    <a href="{{ route('admin.coupons.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
      <i class="bi bi-chevron-left"></i> กลับไปรายการ
    </a>
    <h1 class="text-2xl font-bold text-slate-800 mt-2">
      <i class="bi bi-plus-square-dotted text-emerald-500 mr-2"></i>สร้างคูปองหลายรายการ
    </h1>
    <p class="text-sm text-gray-500 mt-1">สร้างคูปองจำนวนมากพร้อมกัน เช่น สำหรับ email campaign, ลูกค้า VIP, หรือ event giveaway</p>
  </div>

  <form method="POST" action="{{ route('admin.coupons.bulk-store') }}" class="bg-white border border-gray-100 rounded-2xl p-6 space-y-5">
    @csrf

    {{-- Count + Code Settings --}}
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
      <h3 class="text-sm font-bold text-emerald-800 mb-3">
        <i class="bi bi-hash mr-1"></i>รูปแบบรหัส
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">จำนวน <span class="text-red-500">*</span></label>
          <input type="number" name="count" required min="1" max="500" value="{{ old('count', 10) }}"
                 class="w-full px-3 py-2 border border-emerald-200 rounded-lg bg-white">
          <p class="text-xs text-gray-500 mt-1">สูงสุด 500 รายการ/ครั้ง</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Prefix (ถ้ามี)</label>
          <input type="text" name="prefix" maxlength="10" value="{{ old('prefix') }}"
                 placeholder="เช่น VIP, SALE"
                 class="w-full px-3 py-2 border border-emerald-200 rounded-lg bg-white uppercase font-mono">
          <p class="text-xs text-gray-500 mt-1">จะถูกใส่หน้ารหัสทุกตัว</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">ความยาวรหัส</label>
          <input type="number" name="code_length" min="4" max="16" value="{{ old('code_length', 8) }}"
                 class="w-full px-3 py-2 border border-emerald-200 rounded-lg bg-white">
          <p class="text-xs text-gray-500 mt-1">จำนวนตัวอักษรสุ่ม</p>
        </div>
      </div>
      <div class="mt-3 text-xs text-emerald-700 bg-white rounded-lg p-2 font-mono">
        ตัวอย่าง: <span id="preview">VIP-ABCD1234</span>
      </div>
    </div>

    {{-- Coupon Details --}}
    <div>
      <h3 class="text-sm font-bold text-gray-700 mb-3">
        <i class="bi bi-info-circle mr-1"></i>รายละเอียด
      </h3>
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อคูปอง <span class="text-red-500">*</span></label>
          <input type="text" name="name" required maxlength="255" value="{{ old('name') }}"
                 placeholder="เช่น Summer Sale 2026"
                 class="w-full px-3 py-2 border border-gray-200 rounded-lg">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">คำอธิบาย</label>
          <textarea name="description" rows="2" maxlength="500"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg">{{ old('description') }}</textarea>
        </div>
      </div>
    </div>

    {{-- Discount --}}
    <div>
      <h3 class="text-sm font-bold text-gray-700 mb-3">
        <i class="bi bi-tag mr-1"></i>ส่วนลด
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">ประเภท <span class="text-red-500">*</span></label>
          <select name="type" required class="w-full px-3 py-2 border border-gray-200 rounded-lg">
            <option value="percent" {{ old('type') === 'percent' ? 'selected' : '' }}>เปอร์เซ็นต์ (%)</option>
            <option value="fixed" {{ old('type') === 'fixed' ? 'selected' : '' }}>จำนวนคงที่ (฿)</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">มูลค่า <span class="text-red-500">*</span></label>
          <input type="number" name="value" required step="0.01" min="0" value="{{ old('value', 10) }}"
                 class="w-full px-3 py-2 border border-gray-200 rounded-lg">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">ยอดขั้นต่ำ (฿)</label>
          <input type="number" name="min_order" step="0.01" min="0" value="{{ old('min_order') }}"
                 placeholder="ไม่กำหนด"
                 class="w-full px-3 py-2 border border-gray-200 rounded-lg">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">ส่วนลดสูงสุด (฿)</label>
          <input type="number" name="max_discount" step="0.01" min="0" value="{{ old('max_discount') }}"
                 placeholder="สำหรับ % เท่านั้น"
                 class="w-full px-3 py-2 border border-gray-200 rounded-lg">
        </div>
      </div>
    </div>

    {{-- Usage Limits --}}
    <div>
      <h3 class="text-sm font-bold text-gray-700 mb-3">
        <i class="bi bi-restrict mr-1"></i>ข้อจำกัดการใช้งาน
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">จำกัดต่อรหัส</label>
          <input type="number" name="usage_limit" min="0" value="{{ old('usage_limit', 1) }}"
                 class="w-full px-3 py-2 border border-gray-200 rounded-lg">
          <p class="text-xs text-gray-500 mt-1">1 = ใช้ได้ 1 ครั้งต่อรหัส (แนะนำ)</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">จำกัดต่อผู้ใช้</label>
          <input type="number" name="per_user_limit" min="0" value="{{ old('per_user_limit', 1) }}"
                 class="w-full px-3 py-2 border border-gray-200 rounded-lg">
        </div>
      </div>
    </div>

    {{-- Validity --}}
    <div>
      <h3 class="text-sm font-bold text-gray-700 mb-3">
        <i class="bi bi-calendar-range mr-1"></i>ระยะเวลา
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">วันเริ่มต้น</label>
          <input type="datetime-local" name="start_date" value="{{ old('start_date') }}"
                 class="w-full px-3 py-2 border border-gray-200 rounded-lg">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">วันหมดอายุ</label>
          <input type="datetime-local" name="end_date" value="{{ old('end_date') }}"
                 class="w-full px-3 py-2 border border-gray-200 rounded-lg">
        </div>
      </div>
    </div>

    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="is_active" value="1" checked class="rounded">
      <span>เปิดใช้งานทันที</span>
    </label>

    {{-- Alert --}}
    <div class="bg-blue-50 border-l-4 border-blue-400 rounded-r-lg p-3 text-sm text-blue-800">
      <i class="bi bi-info-circle-fill"></i>
      <strong>คำแนะนำ:</strong> ระบบจะสร้างรหัสสุ่มไม่ซ้ำให้อัตโนมัติ คุณสามารถ Export CSV ได้หลังสร้างเสร็จ
    </div>

    {{-- Submit --}}
    <div class="flex gap-3 pt-4">
      <a href="{{ route('admin.coupons.index') }}" class="px-5 py-2.5 border border-gray-200 text-gray-700 rounded-xl font-medium hover:bg-gray-50">ยกเลิก</a>
      <button type="submit" class="flex-1 px-5 py-2.5 bg-gradient-to-br from-emerald-500 to-emerald-600 text-white rounded-xl font-medium hover:shadow-lg">
        <i class="bi bi-lightning-charge-fill mr-1"></i>สร้างคูปอง
      </button>
    </div>
  </form>
</div>

@push('scripts')
<script>
const prefix = document.querySelector('input[name="prefix"]');
const length = document.querySelector('input[name="code_length"]');
const preview = document.getElementById('preview');

function updatePreview() {
  const p = (prefix.value || '').toUpperCase();
  const len = parseInt(length.value || 8);
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let random = '';
  for (let i = 0; i < len; i++) random += chars[Math.floor(Math.random() * chars.length)];
  preview.textContent = p + random;
}

prefix.addEventListener('input', updatePreview);
length.addEventListener('input', updatePreview);
setInterval(updatePreview, 1500);
updatePreview();
</script>
@endpush
@endsection
