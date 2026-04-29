@extends('layouts.admin')

@section('title', 'ตั้งค่าตรวจสอบภาพ')

@section('content')
<div class="flex items-center justify-between mb-4">
  <h4 class="font-bold tracking-tight">
    <i class="bi bi-shield-check mr-2 text-indigo-500"></i>ตั้งค่าตรวจสอบเนื้อหาภาพ
  </h4>
  <a href="{{ route('admin.settings.index') }}" class="inline-flex items-center px-4 py-1.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 text-sm font-medium rounded-lg hover:bg-indigo-100 transition">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if(session('success'))
<div class="bg-emerald-50 text-emerald-700 rounded-lg p-4 text-sm mb-4">
  <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="bg-red-50 text-red-700 rounded-lg p-4 text-sm mb-4 space-y-1">
  @foreach($errors->all() as $err)
  <div><i class="bi bi-exclamation-circle mr-1"></i> {{ $err }}</div>
  @endforeach
</div>
@endif

{{-- Live Stats --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
  <a href="{{ route('admin.moderation.index', ['status' => 'pending']) }}"
     class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 hover:shadow-md transition">
    <div class="text-xs text-gray-500 dark:text-gray-400">รอสแกน</div>
    <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['pending']) }}</div>
  </a>
  <a href="{{ route('admin.moderation.index', ['status' => 'flagged']) }}"
     class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 hover:shadow-md transition">
    <div class="text-xs text-gray-500 dark:text-gray-400">ติดธง — รอตรวจ</div>
    <div class="text-2xl font-bold text-amber-600">{{ number_format($stats['flagged']) }}</div>
  </a>
  <a href="{{ route('admin.moderation.index', ['status' => 'rejected']) }}"
     class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 hover:shadow-md transition">
    <div class="text-xs text-gray-500 dark:text-gray-400">ถูกปฏิเสธ</div>
    <div class="text-2xl font-bold text-red-600">{{ number_format($stats['rejected']) }}</div>
  </a>
</div>

<form method="POST" action="{{ route('admin.settings.moderation.update') }}" class="space-y-5">
  @csrf

  {{-- Master Toggle --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <label class="flex items-start gap-3 cursor-pointer">
      <input type="checkbox" name="moderation_enabled" value="1"
             {{ ($settings['moderation_enabled'] ?? '1') === '1' ? 'checked' : '' }}
             class="mt-1 w-5 h-5 accent-indigo-500">
      <div>
        <div class="font-semibold text-slate-800 dark:text-gray-100">เปิดใช้งานการตรวจสอบภาพอัตโนมัติ</div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
          เมื่อเปิด ภาพที่อัปโหลดใหม่ทุกภาพจะถูกส่งเข้า AWS Rekognition เพื่อตรวจหาเนื้อหาไม่เหมาะสม
          (ประมาณ <strong>$0.001 ต่อภาพ</strong>)
        </div>
      </div>
    </label>
  </div>

  {{-- Thresholds --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
      <i class="bi bi-sliders text-indigo-500 mr-1"></i> เกณฑ์คะแนน (Confidence Threshold)
    </h3>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
      AWS ตอบกลับคะแนน 0-100% ต่อ label ถ้าสูงสุด ≥ <strong>คะแนนปฏิเสธ</strong> → auto-reject
      ถ้าอยู่ระหว่าง <strong>คะแนนติดธง</strong> ถึง <strong>ปฏิเสธ</strong> → ส่งให้แอดมินตัดสิน
    </p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          คะแนนปฏิเสธอัตโนมัติ
        </label>
        <div class="flex">
          <input type="number" step="0.1" min="50" max="100"
                 name="moderation_auto_reject_threshold"
                 value="{{ $settings['moderation_auto_reject_threshold'] ?? 90 }}"
                 class="w-full px-3 py-2 border border-gray-300 dark:border-white/10 bg-white dark:bg-slate-900 rounded-l-lg text-sm">
          <span class="inline-flex items-center px-3 bg-gray-50 dark:bg-white/5 border border-l-0 border-gray-300 dark:border-white/10 rounded-r-lg text-sm text-gray-500">%</span>
        </div>
        <small class="text-gray-500 text-xs">แนะนำ 85-95 (สูง = เข้มงวดน้อย)</small>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          คะแนนติดธง (ต้องมนุษย์ตรวจ)
        </label>
        <div class="flex">
          <input type="number" step="0.1" min="10" max="99"
                 name="moderation_flag_threshold"
                 value="{{ $settings['moderation_flag_threshold'] ?? 50 }}"
                 class="w-full px-3 py-2 border border-gray-300 dark:border-white/10 bg-white dark:bg-slate-900 rounded-l-lg text-sm">
          <span class="inline-flex items-center px-3 bg-gray-50 dark:bg-white/5 border border-l-0 border-gray-300 dark:border-white/10 rounded-r-lg text-sm text-gray-500">%</span>
        </div>
        <small class="text-gray-500 text-xs">แนะนำ 40-60 (ต่ำ = ระมัดระวังมาก)</small>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          คะแนนขั้นต่ำที่จะรายงาน
        </label>
        <div class="flex">
          <input type="number" step="0.1" min="0" max="99"
                 name="moderation_min_confidence"
                 value="{{ $settings['moderation_min_confidence'] ?? 40 }}"
                 class="w-full px-3 py-2 border border-gray-300 dark:border-white/10 bg-white dark:bg-slate-900 rounded-l-lg text-sm">
          <span class="inline-flex items-center px-3 bg-gray-50 dark:bg-white/5 border border-l-0 border-gray-300 dark:border-white/10 rounded-r-lg text-sm text-gray-500">%</span>
        </div>
        <small class="text-gray-500 text-xs">AWS ไม่ส่งกลับ label ที่ต่ำกว่าค่านี้</small>
      </div>
    </div>
  </div>

  {{-- Categories --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
      <i class="bi bi-list-check text-indigo-500 mr-1"></i> หมวดที่ต้องการตรวจสอบ
    </h3>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
      ติ๊กเลือกหมวดที่ถือว่า "ไม่เหมาะสม" สำหรับเว็บไซต์นี้ — งานที่ต่างกันอาจมีเกณฑ์ต่างกัน
      (เช่น งานชายทะเลอาจอนุญาต "Suggestive")
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
      @foreach($allCategories as $cat)
      <label class="flex items-center gap-2 p-2 rounded-lg border border-gray-200 dark:border-white/10 hover:bg-indigo-50 dark:hover:bg-indigo-500/5 cursor-pointer">
        <input type="checkbox" name="moderation_categories[]" value="{{ $cat }}"
               {{ in_array($cat, $enabledCategories) ? 'checked' : '' }}
               class="w-4 h-4 accent-indigo-500">
        <span class="text-sm text-slate-700 dark:text-gray-200">{{ $cat }}</span>
      </label>
      @endforeach
    </div>
  </div>

  {{-- Advanced --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5 space-y-3">
    <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-2">
      <i class="bi bi-gear text-indigo-500 mr-1"></i> ตัวเลือกขั้นสูง
    </h3>

    <label class="flex items-start gap-3 cursor-pointer p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5">
      <input type="checkbox" name="moderation_skip_verified_photographers" value="1"
             {{ ($settings['moderation_skip_verified_photographers'] ?? '0') === '1' ? 'checked' : '' }}
             class="mt-1 w-4 h-4 accent-indigo-500">
      <div>
        <div class="font-medium text-sm text-slate-700 dark:text-gray-200">ยกเว้นช่างภาพที่ได้รับการอนุมัติแล้ว</div>
        <div class="text-xs text-gray-500 dark:text-gray-400">ประหยัดค่าใช้จ่าย AWS — เชื่อใจช่างภาพที่ verified แล้ว</div>
      </div>
    </label>

    <label class="flex items-start gap-3 cursor-pointer p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5">
      <input type="checkbox" name="moderation_notify_uploader" value="1"
             {{ ($settings['moderation_notify_uploader'] ?? '1') === '1' ? 'checked' : '' }}
             class="mt-1 w-4 h-4 accent-indigo-500">
      <div>
        <div class="font-medium text-sm text-slate-700 dark:text-gray-200">ส่งอีเมลแจ้งช่างภาพเมื่อภาพถูกปฏิเสธ</div>
        <div class="text-xs text-gray-500 dark:text-gray-400">ช่วยให้ช่างภาพเข้าใจว่าทำไมภาพถูกซ่อน + ส่งเหตุผล</div>
      </div>
    </label>

    <label class="flex items-start gap-3 cursor-pointer p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5">
      <input type="checkbox" name="moderation_client_prefilter" value="1"
             {{ ($settings['moderation_client_prefilter'] ?? '0') === '1' ? 'checked' : '' }}
             class="mt-1 w-4 h-4 accent-indigo-500">
      <div>
        <div class="font-medium text-sm text-slate-700 dark:text-gray-200">
          เปิดใช้ NSFWJS กรองในบราวเซอร์ (Client-side) <span class="text-[0.65rem] ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">Beta</span>
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400">
          ตรวจเบื้องต้นก่อนอัปโหลด — ลดค่า AWS ลง 70% แต่ต้องโหลดโมเดล ~4MB ในบราวเซอร์
        </div>
      </div>
    </label>
  </div>

  <div class="flex gap-2">
    <button type="submit" class="px-6 py-2.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg font-medium">
      <i class="bi bi-save mr-1"></i> บันทึก
    </button>
    <a href="{{ route('admin.moderation.index') }}" class="px-6 py-2.5 border border-gray-200 dark:border-white/10 rounded-lg font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5">
      <i class="bi bi-eye mr-1"></i> ดูภาพที่รอตรวจ
    </a>
  </div>
</form>
@endsection
