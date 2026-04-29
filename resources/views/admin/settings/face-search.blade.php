@extends('layouts.admin')

@section('title', 'ตั้งค่าระบบค้นหาด้วยใบหน้า')

@section('content')
<div class="flex items-center justify-between mb-4">
  <h4 class="font-bold tracking-tight">
    <i class="bi bi-person-bounding-box mr-2 text-fuchsia-500"></i>ระบบค้นหาด้วยใบหน้า (AWS Rekognition)
  </h4>
  <div class="flex items-center gap-2">
    <a href="{{ route('admin.settings.face-search.usage') }}" class="inline-flex items-center px-4 py-1.5 bg-fuchsia-50 dark:bg-fuchsia-500/10 text-fuchsia-600 dark:text-fuchsia-300 text-sm font-medium rounded-lg hover:bg-fuchsia-100 transition">
      <i class="bi bi-graph-up mr-1"></i> ดูการใช้งาน
    </a>
    <a href="{{ route('admin.settings.index') }}" class="inline-flex items-center px-4 py-1.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 text-sm font-medium rounded-lg hover:bg-indigo-100 transition">
      <i class="bi bi-arrow-left mr-1"></i> กลับ
    </a>
  </div>
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

<div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 rounded-2xl p-4 text-sm text-amber-800 dark:text-amber-200 mb-5">
  <div class="font-semibold mb-1"><i class="bi bi-lightbulb mr-1"></i>ค่าเริ่มต้นเพื่อความปลอดภัย</div>
  <p class="leading-relaxed">
    AWS Rekognition คิดค่าบริการประมาณ <strong>$0.001 ต่อ API call</strong>. ค่าที่ตั้งไว้ด้านล่างเป็นค่าเริ่มต้นที่ปลอดภัย
    หากระบบถูกใช้งานเต็มโควต้าทั้งหมดตลอดเดือน ค่าใช้จ่ายสูงสุดประมาณ <strong>$20/เดือน</strong>.
    ตั้งค่าใดเป็น <code>0</code> เพื่อปิดเช็คนั้น ๆ (ไม่แนะนำ).
  </p>
</div>

<form method="POST" action="{{ route('admin.settings.face-search.update') }}" class="space-y-5">
  @csrf

  {{-- Master kill switch --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <label class="flex items-start gap-3 cursor-pointer">
      <input type="checkbox" name="face_search_enabled_globally" value="1"
             {{ ($settings['face_search_enabled_globally'] ?? '1') === '1' ? 'checked' : '' }}
             class="mt-1 w-5 h-5 accent-fuchsia-500">
      <div>
        <div class="font-semibold text-slate-800 dark:text-gray-100">เปิดใช้งานระบบค้นหาด้วยใบหน้า (Master Switch)</div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
          ปิดสวิตช์นี้เพื่อหยุดระบบทันทีทุก event โดยไม่ต้องแก้ไขแต่ละงาน
          (ใช้เมื่อสงสัยว่าถูกโจมตี หรือ AWS Bill เกินงบ)
        </div>
      </div>
    </label>
  </div>

  {{-- Daily caps --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
      <i class="bi bi-sliders text-fuchsia-500 mr-1"></i> โควต้ารายวัน (Daily Caps)
    </h3>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
      นับการค้นหาทั้งหมด (รวมที่ถูกปฏิเสธและ cache hit) ต่อ event / ต่อ user / ต่อ IP — รีเซ็ตตอนเที่ยงคืนของโซนเวลาเซิร์ฟเวอร์
    </p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ต่อ Event / วัน</label>
        <input type="number" name="face_search_daily_cap_per_event" min="0" max="100000"
               value="{{ old('face_search_daily_cap_per_event', $settings['face_search_daily_cap_per_event'] ?? '500') }}"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
        <div class="text-[11px] text-gray-500 mt-1">แนะนำ 500 (0 = ปิด)</div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ต่อ User / วัน</label>
        <input type="number" name="face_search_daily_cap_per_user" min="0" max="100000"
               value="{{ old('face_search_daily_cap_per_user', $settings['face_search_daily_cap_per_user'] ?? '50') }}"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
        <div class="text-[11px] text-gray-500 mt-1">แนะนำ 50 (ผู้ใช้ทั่วไปค้นไม่เกิน 10-20)</div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ต่อ IP / วัน</label>
        <input type="number" name="face_search_daily_cap_per_ip" min="0" max="100000"
               value="{{ old('face_search_daily_cap_per_ip', $settings['face_search_daily_cap_per_ip'] ?? '100') }}"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
        <div class="text-[11px] text-gray-500 mt-1">แนะนำ 100 (รองรับ NAT ออฟฟิศ/มือถือ)</div>
      </div>
    </div>
  </div>

  {{-- Monthly global ceiling --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
      <i class="bi bi-piggy-bank text-emerald-500 mr-1"></i> เพดานรวมต่อเดือน (Global Monthly Cap)
    </h3>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
      รวม request ทั้งหมดทุก event 30 วันล่าสุด — ป้องกันกรณีที่ cap ต่อ event ไม่ถึงแต่รวมกันเกินงบ
    </p>
    <div class="flex items-center gap-3 max-w-md">
      <input type="number" name="face_search_monthly_global_cap" min="0" max="10000000"
             value="{{ old('face_search_monthly_global_cap', $settings['face_search_monthly_global_cap'] ?? '10000') }}"
             class="flex-1 px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
      <span class="text-sm text-gray-600 dark:text-gray-400">ครั้ง/เดือน</span>
    </div>
    <div class="text-[11px] text-gray-500 mt-2">
      10,000 ครั้ง ≈ <strong>~$20/เดือน</strong> (2 API calls เฉลี่ยต่อการค้นหา 1 ครั้ง)
    </div>
  </div>

  {{-- Fallback photo cap --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
      <i class="bi bi-shield-exclamation text-red-500 mr-1"></i> เพดาน Fallback Path (รูปที่ยังไม่ index)
    </h3>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
      เมื่อรูปในงานยังไม่ได้ index ลง Rekognition collection ระบบจะ fallback ไปใช้ <code>compareFaces</code>
      ซึ่งเรียก API <strong>1 call ต่อ 1 รูป</strong> — ถ้ารูปในงานเกินจำนวนด้านล่าง ระบบจะปฏิเสธการค้นหาเพื่อกันบิลระเบิด
      (แอดมินต้องสั่ง <code>rekognition:reindex-event</code> ก่อน)
    </p>
    <div class="flex items-center gap-3 max-w-md">
      <input type="number" name="face_search_fallback_max_photos" min="0" max="500"
             value="{{ old('face_search_fallback_max_photos', $settings['face_search_fallback_max_photos'] ?? '20') }}"
             class="flex-1 px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
      <span class="text-sm text-gray-600 dark:text-gray-400">รูป</span>
    </div>
    <div class="text-[11px] text-gray-500 mt-2">
      แนะนำ 20 (=~$0.02/request). ตั้งเกิน 100 ไม่ปลอดภัย.
    </div>
  </div>

  {{-- Cache TTL --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
      <i class="bi bi-lightning-charge text-amber-500 mr-1"></i> Cache ผลลัพธ์ (Dedup)
    </h3>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
      ถ้าผู้ใช้อัปโหลดรูปเซลฟี่เดิมซ้ำภายในเวลาที่กำหนด ระบบจะคืนผลจาก cache ทันที — <strong>ไม่เรียก AWS</strong>
      (คีย์ cache = sha256 ของไฟล์ selfie + event id; ไม่เก็บไฟล์จริง)
    </p>
    <div class="flex items-center gap-3 max-w-md">
      <input type="number" name="face_search_cache_ttl_minutes" min="0" max="1440"
             value="{{ old('face_search_cache_ttl_minutes', $settings['face_search_cache_ttl_minutes'] ?? '10') }}"
             class="flex-1 px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
      <span class="text-sm text-gray-600 dark:text-gray-400">นาที</span>
    </div>
    <div class="text-[11px] text-gray-500 mt-2">
      แนะนำ 10 นาที (0 = ปิด cache). นานเกินไปเสี่ยงโชว์ผลเก่าถ้ามีอัปโหลดรูปเพิ่ม.
    </div>
  </div>

  <div class="flex justify-end">
    <button type="submit"
            class="inline-flex items-center px-6 py-2.5 bg-gradient-to-r from-fuchsia-500 to-pink-500 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition">
      <i class="bi bi-check-lg mr-2"></i> บันทึก
    </button>
  </div>
</form>
@endsection
