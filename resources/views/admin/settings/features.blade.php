@extends('layouts.admin')

@section('title', 'Feature Toggles')

{{--
    Admin → Settings → Features
    System on/off switches for major subsystems. Currently:
      • Blog (public /blog/*)
    Future toggles slot into the same `<feature-card>` pattern.

    Form posts to admin.settings.features.update which calls
    Features::bulkSet([...]). Each toggle pairs a hidden '0' input
    with the checkbox so unchecked state still submits.
--}}

@section('content')
<div class="max-w-4xl mx-auto pb-16">

  {{-- ────────── PAGE HEADER ────────── --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h4 class="text-2xl font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-3 tracking-tight">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl shadow-md shadow-indigo-500/30"
              style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
          <i class="bi bi-toggles2 text-white text-xl"></i>
        </span>
        Feature Toggles
      </h4>
      <p class="text-sm text-slate-500 dark:text-slate-400 ml-14">
        เปิด/ปิดระบบย่อยของเว็บไซต์ — ปิดแล้วเส้นทางสาธารณะของระบบจะแสดง 404 (admin ยังเข้าจัดการได้ปกติ)
      </p>
    </div>
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold
              text-slate-600 dark:text-slate-300
              bg-white dark:bg-slate-900
              border border-slate-200 dark:border-white/10
              hover:border-indigo-300 dark:hover:border-indigo-400/40 transition-colors">
      <i class="bi bi-arrow-left"></i>กลับหน้าตั้งค่า
    </a>
  </div>

  {{-- ────────── FLASH ────────── --}}
  @if(session('success'))
    <div class="mb-5 px-4 py-3 rounded-xl flex items-start gap-2
                bg-emerald-50 dark:bg-emerald-500/10
                border border-emerald-200 dark:border-emerald-500/30
                text-emerald-800 dark:text-emerald-300">
      <i class="bi bi-check-circle-fill mt-0.5"></i>
      <div class="flex-1 text-sm">{{ session('success') }}</div>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.features.update') }}" class="space-y-4">
    @csrf

    {{-- ════════════════════════════════════════════════════════
         FEATURE: Blog
         ════════════════════════════════════════════════════════ --}}
    @php $blogOn = $features['blog'] ?? true; @endphp
    <div class="rounded-2xl bg-white dark:bg-slate-900
                border border-slate-200 dark:border-white/10
                shadow-sm shadow-slate-900/5 dark:shadow-black/20
                overflow-hidden"
         x-data="{ on: {{ $blogOn ? 'true' : 'false' }} }">

      <div class="flex items-start gap-4 p-5 sm:p-6">

        {{-- Icon --}}
        <span class="inline-flex items-center justify-center w-12 h-12 rounded-2xl shrink-0
                     bg-gradient-to-br from-indigo-500 to-violet-600 shadow-md shadow-indigo-500/25">
          <i class="bi bi-journal-text text-white text-xl"></i>
        </span>

        {{-- Body --}}
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap mb-1">
            <h5 class="font-bold text-slate-900 dark:text-white text-base">ระบบบทความ (Blog)</h5>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full"
                  :class="on ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                             : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'">
              <span class="w-1.5 h-1.5 rounded-full"
                    :class="on ? 'bg-emerald-500' : 'bg-rose-500'"></span>
              <span x-text="on ? 'เปิดใช้งาน' : 'ปิดใช้งาน'"></span>
            </span>
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-3 leading-relaxed">
            หน้าบทความสาธารณะ + RSS feed + การค้นหาบทความ + ลิงก์ในเมนู
          </p>

          {{-- Affected scope --}}
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <div class="flex items-start gap-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-white/[0.06]">
              <i class="bi bi-globe text-slate-400 mt-0.5"></i>
              <div>
                <p class="font-semibold text-slate-700 dark:text-slate-200">เส้นทางสาธารณะ</p>
                <code class="text-[11px] text-indigo-600 dark:text-indigo-300 font-mono">/blog/*</code>
              </div>
            </div>
            <div class="flex items-start gap-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-white/[0.06]">
              <i class="bi bi-shield-check text-emerald-500 mt-0.5"></i>
              <div>
                <p class="font-semibold text-slate-700 dark:text-slate-200">ผู้ดูแลระบบยังเข้าได้</p>
                <code class="text-[11px] text-emerald-600 dark:text-emerald-300 font-mono">/admin/blog/*</code>
              </div>
            </div>
            <div class="flex items-start gap-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-white/[0.06]">
              <i class="bi bi-list-ul text-slate-400 mt-0.5"></i>
              <div>
                <p class="font-semibold text-slate-700 dark:text-slate-200">เมนู Navigation</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">ซ่อนลิงก์อัตโนมัติเมื่อปิด</p>
              </div>
            </div>
            <div class="flex items-start gap-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-white/[0.06]">
              <i class="bi bi-search text-slate-400 mt-0.5"></i>
              <div>
                <p class="font-semibold text-slate-700 dark:text-slate-200">Sitemap & SEO</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">ลบ URL ออกจาก sitemap.xml</p>
              </div>
            </div>
          </div>
        </div>

        {{-- Toggle switch --}}
        <div class="shrink-0">
          <label class="relative inline-flex items-center cursor-pointer">
            {{-- Hidden '0' so unchecked submits as off --}}
            <input type="hidden" name="features[blog]" value="0">
            <input type="checkbox" name="features[blog]" value="1"
                   x-model="on"
                   class="sr-only peer">
            <div class="w-12 h-6 rounded-full transition-colors duration-200
                        bg-slate-300 dark:bg-slate-700
                        peer-checked:bg-gradient-to-r peer-checked:from-indigo-500 peer-checked:to-violet-600
                        peer-focus:ring-4 peer-focus:ring-indigo-500/30
                        relative">
              <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow-md transition-transform duration-200"
                    :class="on ? 'translate-x-6' : 'translate-x-0'"></span>
            </div>
          </label>
        </div>
      </div>
    </div>

    {{-- ════════════════════════════════════════════════════════
         FUTURE FEATURES placeholder (commented out — uncomment as added)
         ════════════════════════════════════════════════════════ --}}
    {{-- <div class="rounded-2xl ..."> ... </div> --}}

    {{-- Submit bar --}}
    <div class="flex items-center justify-end gap-3 pt-2">
      <a href="{{ route('admin.settings.index') }}"
         class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold
                text-slate-600 dark:text-slate-300
                bg-white dark:bg-slate-900
                border border-slate-200 dark:border-white/10
                hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
        ยกเลิก
      </a>
      <button type="submit"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white
                     bg-gradient-to-r from-indigo-600 to-violet-600
                     hover:from-indigo-700 hover:to-violet-700
                     shadow-md shadow-indigo-500/30 hover:shadow-lg hover:shadow-indigo-500/40
                     transition-all">
        <i class="bi bi-check2-circle"></i>บันทึกการเปลี่ยนแปลง
      </button>
    </div>
  </form>

  {{-- ────────── INFO BANNER ────────── --}}
  <div class="mt-6 px-4 py-3 rounded-xl flex items-start gap-3
              bg-blue-50 dark:bg-blue-500/10
              border border-blue-200 dark:border-blue-500/30
              text-blue-900 dark:text-blue-200">
    <i class="bi bi-info-circle-fill mt-0.5 shrink-0"></i>
    <div class="flex-1 text-xs leading-relaxed">
      <p class="font-semibold mb-1">ผลกระทบเมื่อปิดระบบบทความ:</p>
      <ul class="list-disc list-inside space-y-0.5 text-blue-800/80 dark:text-blue-300/80">
        <li>ผู้ใช้งานทั่วไปเปิด <code class="font-mono">/blog</code> หรือลิงก์บทความ → ได้หน้า 404</li>
        <li>ลิงก์ "บทความ" ใน navigation + footer จะถูกซ่อนอัตโนมัติ</li>
        <li>URL ของบทความจะถูกลบออกจาก <code class="font-mono">sitemap.xml</code> (Google ค่อยๆ drop จาก index)</li>
        <li>คุณยังเข้า <code class="font-mono">/admin/blog</code> เพื่อแก้ไข + เปิดใช้งานคืนได้ทุกเมื่อ</li>
        <li>ข้อมูลบทความ + หมวดหมู่ + แท็ก ทั้งหมด<strong>ไม่ถูกลบ</strong> — เปิดคืนได้สมบูรณ์</li>
      </ul>
    </div>
  </div>

</div>
@endsection
