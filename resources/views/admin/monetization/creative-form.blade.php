@extends('layouts.admin')
@section('title', $isNew ? 'เพิ่ม Creative ใหม่' : 'แก้ไข Creative')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">

  <div class="text-xs text-slate-500 mb-2">
    <a href="{{ route('admin.monetization.dashboard') }}" class="hover:underline">Monetization</a>
    <span>›</span>
    <a href="{{ route('admin.monetization.campaigns.show', $campaign) }}" class="hover:underline">{{ $campaign->name }}</a>
    <span>›</span>
    <span class="text-slate-700 dark:text-slate-300">{{ $isNew ? 'เพิ่ม creative' : ('แก้: ' . $creative->headline) }}</span>
  </div>

  <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-1">{{ $isNew ? 'เพิ่ม Creative ใหม่' : 'แก้ไข Creative' }}</h1>
  <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">
    Campaign: <strong>{{ $campaign->name }}</strong> · Brand: <strong>{{ $campaign->advertiser }}</strong>
  </p>

  @if($errors->any())
    <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
      @foreach($errors->all() as $e)<div>· {{ $e }}</div>@endforeach
    </div>
  @endif

  {{-- enctype required for file upload --}}
  <form method="POST"
        action="{{ $isNew ? route('admin.monetization.campaigns.creatives.store', $campaign) : route('admin.monetization.campaigns.creatives.update', ['campaign' => $campaign, 'creative' => $creative]) }}"
        enctype="multipart/form-data"
        class="space-y-5"
        x-data="{ preview: '{{ $isNew ? '' : ($creative->image_url ?? '') }}' }">
    @csrf
    @if(!$isNew)@method('PATCH')@endif

    {{-- ── Image upload — primary action of this form ─────────────────── --}}
    <fieldset class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5">
      <legend class="px-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">รูปภาพ Banner</legend>

      <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
        ขนาดแนะนำ: <strong>1200×600 px</strong> (5:1 ratio) · ไฟล์สูงสุด 8 MB · jpg / png / webp
      </p>

      {{-- Live preview — updates when admin picks a file --}}
      <div class="mb-3 rounded-xl overflow-hidden border border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-slate-800"
           style="aspect-ratio: 5/1;"
           x-show="preview">
        <img :src="preview" alt="" class="w-full h-full object-cover">
      </div>
      <div class="mb-3 rounded-xl border-2 border-dashed border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/40 flex items-center justify-center text-slate-400"
           style="aspect-ratio: 5/1;"
           x-show="!preview">
        <div class="text-center">
          <i class="bi bi-image text-3xl"></i>
          <p class="text-sm mt-2">ยังไม่ได้เลือกไฟล์</p>
        </div>
      </div>

      <input type="file" name="image"
             accept="image/jpeg,image/png,image/webp"
             {{ $isNew ? 'required' : '' }}
             @change="
               const f = $event.target.files[0];
               if (!f) return;
               const reader = new FileReader();
               reader.onload = e => preview = e.target.result;
               reader.readAsDataURL(f);
             "
             class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
      @if(!$isNew)
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
          เว้นว่าง = ใช้รูปเดิม. เลือกไฟล์ใหม่เพื่อแทนที่
        </p>
      @endif
    </fieldset>

    {{-- ── Copy ─────────────────────────────────────────────────────── --}}
    <fieldset class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5">
      <legend class="px-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">ข้อความและปุ่ม</legend>

      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">หัวข้อ (Headline) *</label>
        <input type="text" name="headline" required maxlength="120"
               value="{{ old('headline', $creative->headline) }}"
               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
      </div>

      <div class="mt-3">
        <label class="block text-xs font-bold text-slate-600 mb-1">คำอธิบาย (Body) <span class="text-slate-400 text-[10px]">ไม่บังคับ</span></label>
        <textarea name="body" rows="2" maxlength="300"
                  class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">{{ old('body', $creative->body) }}</textarea>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">ป้ายปุ่ม (CTA Label)</label>
          <input type="text" name="cta_label" maxlength="40"
                 value="{{ old('cta_label', $creative->cta_label ?? 'เรียนรู้เพิ่มเติม') }}"
                 placeholder="เรียนรู้เพิ่มเติม"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">URL ปลายทาง (Click URL) *</label>
          <input type="url" name="click_url" required maxlength="500"
                 value="{{ old('click_url', $creative->click_url) }}"
                 placeholder="https://brand.example.com/landing"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
      </div>
    </fieldset>

    {{-- ── Targeting ────────────────────────────────────────────────── --}}
    <fieldset class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5">
      <legend class="px-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">ตำแหน่งและน้ำหนัก</legend>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-600 mb-1">Placement *</label>
          <select name="placement" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
            @foreach([
              'homepage_banner' => 'หน้าแรก · banner ใหญ่',
              'sidebar'         => 'แถบข้าง · sidebar',
              'search_inline'   => 'ผลการค้นหา · inline',
              'landing_native'  => 'หน้า Landing · native',
            ] as $val => $lbl)
              <option value="{{ $val }}" {{ old('placement', $creative->placement) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">น้ำหนัก (Weight) *</label>
          <input type="number" name="weight" required min="1" max="1000" step="1"
                 value="{{ old('weight', $creative->weight ?? 100) }}"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
          <p class="text-[10px] text-slate-500 mt-1">สูง = แสดงบ่อยกว่า. ใช้สำหรับ A/B test (100/100 = 50/50, 200/100 = 67/33)</p>
        </div>
      </div>

      <label class="inline-flex items-center gap-2 mt-3 text-sm">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $creative->is_active ?? true) ? 'checked' : '' }}>
        Active (พร้อมเสิร์ฟทันที)
      </label>
    </fieldset>

    {{-- ── Submit + delete ───────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-2">
      <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">
        <i class="bi bi-save"></i> {{ $isNew ? 'สร้าง Creative' : 'บันทึกการแก้ไข' }}
      </button>
      <a href="{{ route('admin.monetization.campaigns.show', $campaign) }}" class="text-sm text-slate-500 hover:underline ml-2">ยกเลิก</a>
    </div>

    @if(!$isNew)
      <div class="border-t border-slate-200 dark:border-white/10 pt-4 mt-6">
        <details>
          <summary class="cursor-pointer text-rose-600 dark:text-rose-400 text-sm font-semibold">⚠️ Danger zone — ลบ creative</summary>
          <div class="mt-3">
            <p class="text-xs text-slate-500 mb-2">การลบจะหยุดเสิร์ฟทันที. ข้อมูล impression/click ในอดีตยังเก็บไว้สำหรับ analytics.</p>
            <form method="POST"
                  action="{{ route('admin.monetization.campaigns.creatives.destroy', ['campaign' => $campaign, 'creative' => $creative]) }}"
                  onsubmit="return confirm('ลบ creative \"{{ $creative->headline }}\" จริงๆ ?');">
              @csrf @method('DELETE')
              <button class="px-3 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">ลบ creative</button>
            </form>
          </div>
        </details>
      </div>
    @endif
  </form>
</div>
@endsection
