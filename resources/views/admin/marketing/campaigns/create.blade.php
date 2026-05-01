@extends('layouts.admin')

@section('title', 'สร้าง Campaign ใหม่')

@section('content')
<div class="max-w-4xl mx-auto pb-16">

  {{-- ═══ Page Header ═══ --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-pink-500 to-rose-600 text-white flex items-center justify-center shadow-lg shadow-pink-500/30">
        <i class="bi bi-envelope-plus text-lg"></i>
      </div>
      <div>
        {{-- Breadcrumb --}}
        <div class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400 mb-1">
          <a href="{{ route('admin.marketing.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Marketing Hub</a>
          <i class="bi bi-chevron-right text-[9px]"></i>
          <a href="{{ route('admin.marketing.campaigns.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Campaigns</a>
          <i class="bi bi-chevron-right text-[9px]"></i>
          <span class="text-slate-700 dark:text-slate-300">New</span>
        </div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">สร้าง Campaign ใหม่</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          เขียน subject + body + เลือก segment — บันทึกเป็น draft ก่อน ค่อยส่งในหน้า details
        </p>
      </div>
    </div>
    <a href="{{ route('admin.marketing.campaigns.index') }}"
       class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg
              bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10
              text-slate-700 dark:text-slate-200
              hover:bg-slate-50 dark:hover:bg-slate-700 transition">
      <i class="bi bi-arrow-left"></i> Back to Campaigns
    </a>
  </div>

  {{-- ═══ Validation Errors ═══ --}}
  @if($errors->any())
    <div class="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 text-sm space-y-1">
      <div class="font-semibold flex items-center gap-2 mb-1">
        <i class="bi bi-exclamation-triangle-fill"></i> กรุณาแก้ไขข้อมูลต่อไปนี้:
      </div>
      @foreach($errors->all() as $e)
        <div class="flex items-start gap-2 pl-1">
          <i class="bi bi-dot text-base leading-none -mt-0.5"></i>
          <span>{{ $e }}</span>
        </div>
      @endforeach
    </div>
  @endif

  <form method="POST" action="{{ route('admin.marketing.campaigns.store') }}" class="space-y-5">
    @csrf

    {{-- ═══ Card 1: Email Content ═══ --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 bg-gradient-to-r from-indigo-50 to-violet-50 dark:from-indigo-500/[0.08] dark:to-violet-500/[0.08]">
        <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-pencil-square text-indigo-600 dark:text-indigo-400"></i>
          เนื้อหาอีเมล
        </h3>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">หัวข้อและเนื้อหาที่ผู้รับจะเห็นในกล่องเมล</p>
      </div>
      <div class="p-5 space-y-4">

        {{-- Campaign Name (internal) --}}
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            Campaign Name <span class="text-slate-400 font-normal">(ใช้ภายในเท่านั้น)</span>
          </label>
          <input type="text" name="name" value="{{ old('name') }}" required
                 placeholder="เช่น: New Year Promo 2026"
                 class="w-full px-3 py-2 rounded-lg text-sm
                        bg-white dark:bg-slate-800
                        border border-slate-300 dark:border-white/10
                        text-slate-900 dark:text-slate-100
                        placeholder:text-slate-400
                        focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 outline-none transition">
        </div>

        {{-- Email Subject --}}
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            Subject Line <span class="text-rose-500">*</span>
          </label>
          <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="120"
                 placeholder="เช่น: 🎉 ส่วนลด 30% ต้อนรับปีใหม่"
                 class="w-full px-3 py-2 rounded-lg text-sm
                        bg-white dark:bg-slate-800
                        border border-slate-300 dark:border-white/10
                        text-slate-900 dark:text-slate-100
                        placeholder:text-slate-400
                        focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 outline-none transition">
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
            <i class="bi bi-lightbulb text-amber-500 mr-1"></i>
            ใช้ emoji + ตัวเลขเริ่มต้น เพิ่ม open rate 20-30%
          </p>
        </div>

        {{-- Body (Markdown) --}}
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            Body (Markdown) <span class="text-rose-500">*</span>
          </label>
          <textarea name="body_markdown" rows="12" required
                    placeholder="**Hi {{ '{{name}}' }}**

ข่าวดี! เรามี promotion พิเศษ..."
                    class="w-full px-3 py-2 rounded-lg text-sm font-mono leading-relaxed
                           bg-white dark:bg-slate-800
                           border border-slate-300 dark:border-white/10
                           text-slate-900 dark:text-slate-100
                           placeholder:text-slate-400
                           focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 outline-none transition resize-y">{{ old('body_markdown') }}</textarea>
          <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-slate-100 dark:bg-white/[0.06] text-slate-600 dark:text-slate-300">
              <i class="bi bi-type-bold"></i><code>**bold**</code>
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-slate-100 dark:bg-white/[0.06] text-slate-600 dark:text-slate-300">
              <i class="bi bi-link"></i><code>[link](url)</code>
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-indigo-100 dark:bg-indigo-500/[0.15] text-indigo-700 dark:text-indigo-300">
              <i class="bi bi-person"></i><code>{{ '{{name}}' }}</code>
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-indigo-100 dark:bg-indigo-500/[0.15] text-indigo-700 dark:text-indigo-300">
              <i class="bi bi-envelope"></i><code>{{ '{{email}}' }}</code>
            </span>
          </div>
        </div>

      </div>
    </div>

    {{-- ═══ Card 2: Audience Segment ═══ --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 bg-gradient-to-r from-sky-50 to-cyan-50 dark:from-sky-500/[0.08] dark:to-cyan-500/[0.08]">
        <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-people-fill text-sky-600 dark:text-sky-400"></i>
          กลุ่มผู้รับ (Segment)
        </h3>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">เลือกว่าจะส่งให้ใคร — กรองตามพฤติกรรมหรือ tag</p>
      </div>
      <div class="p-5 space-y-4">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Segment Type</label>
            <select name="segment_type"
                    class="w-full px-3 py-2 rounded-lg text-sm
                           bg-white dark:bg-slate-800
                           border border-slate-300 dark:border-white/10
                           text-slate-900 dark:text-slate-100
                           focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 outline-none transition">
              <option value="all">📧 All Confirmed Subscribers</option>
              <option value="vip">⭐ VIP (Gold + Platinum tier)</option>
              <option value="dormant">😴 Dormant (ไม่ซื้อ 90+ วัน)</option>
              <option value="tag">🏷️ By Tag</option>
              <option value="users">👥 All Users (email อยู่ในระบบ)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Tag <span class="text-slate-400 font-normal">(เฉพาะเมื่อเลือก By Tag)</span>
            </label>
            <input type="text" name="segment_value" value="{{ old('segment_value') }}"
                   placeholder="เช่น: photographer, frequent_buyer"
                   class="w-full px-3 py-2 rounded-lg text-sm
                          bg-white dark:bg-slate-800
                          border border-slate-300 dark:border-white/10
                          text-slate-900 dark:text-slate-100
                          placeholder:text-slate-400
                          focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 outline-none transition">
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            <i class="bi bi-clock mr-1"></i>Schedule
            <span class="text-slate-400 font-normal">(optional — เว้นว่าง = บันทึกเป็น draft)</span>
          </label>
          <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"
                 class="px-3 py-2 rounded-lg text-sm
                        bg-white dark:bg-slate-800
                        border border-slate-300 dark:border-white/10
                        text-slate-900 dark:text-slate-100
                        focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 outline-none transition">
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
            <i class="bi bi-info-circle text-sky-500 mr-1"></i>
            ส่งทันทีได้จากหน้า campaign details หลังบันทึก draft
          </p>
        </div>

      </div>
    </div>

    {{-- ═══ Actions ═══ --}}
    <div class="flex justify-end gap-2">
      <a href="{{ route('admin.marketing.campaigns.index') }}"
         class="px-4 py-2 rounded-lg text-sm font-medium
                bg-white dark:bg-slate-800
                border border-slate-200 dark:border-white/10
                text-slate-700 dark:text-slate-200
                hover:bg-slate-50 dark:hover:bg-slate-700 transition">ยกเลิก</a>
      <button type="submit"
              class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg text-sm font-semibold text-white
                     bg-gradient-to-r from-pink-600 to-rose-600
                     hover:from-pink-500 hover:to-rose-500
                     shadow-md shadow-pink-500/30 transition">
        <i class="bi bi-save"></i> บันทึก Draft
      </button>
    </div>
  </form>

</div>
@endsection
