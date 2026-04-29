@extends('layouts.admin')

@section('title', $isNew ? 'เพิ่ม SEO override' : 'แก้ไข SEO override · ' . $page->route_name)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="{{ route('admin.seo.index') }}" class="hover:underline">SEO Management</a>
    <span>›</span>
    <span class="text-slate-700 dark:text-slate-300">{{ $isNew ? 'เพิ่มใหม่' : $page->route_name }}</span>
  </div>

  @if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm">
      <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
  @endif

  @if($errors->any())
    <div class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 text-sm">
      @foreach($errors->all() as $err)<div>· {{ $err }}</div>@endforeach
    </div>
  @endif

  @if(!empty($warnings))
    <div class="mb-4 p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-300 text-sm">
      <strong><i class="bi bi-exclamation-triangle-fill"></i> SEO warnings:</strong>
      <ul class="list-disc list-inside mt-1">
        @foreach($warnings as $w)<li>{{ $w }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ $isNew ? route('admin.seo.store') : route('admin.seo.update', $page) }}"
        x-data="{ title: @js($page->title ?? ''), description: @js($page->description ?? '') }"
        class="space-y-5">
    @csrf
    @if(!$isNew)@method('PATCH')@endif

    {{-- ── Identity (locked once created) ─────────────────────────────── --}}
    <fieldset class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5">
      <legend class="px-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Identity</legend>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Route name *</label>
          @if($isNew)
            <select name="route_name" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
              <option value="">— เลือก route ที่จะ override —</option>
              @foreach($routes as $r)
                <option value="{{ $r['name'] }}">{{ $r['name'] }}  ({{ $r['uri'] }})</option>
              @endforeach
            </select>
          @else
            <input type="text" value="{{ $page->route_name }}" disabled class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-slate-800 text-sm font-mono text-indigo-700 dark:text-indigo-300">
            <p class="text-[11px] text-slate-500 mt-1">Route name ล็อก — สร้างใหม่หากต้องการเปลี่ยน</p>
          @endif
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Locale *</label>
          <select name="locale" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
            <option value="th" {{ ($page->locale ?? 'th') === 'th' ? 'selected' : '' }}>ไทย (th)</option>
            <option value="en" {{ ($page->locale ?? '') === 'en' ? 'selected' : '' }}>English (en)</option>
          </select>
        </div>
      </div>

      <div class="mt-3">
        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Path preview (คำอธิบายให้ admin คนอื่นเห็น)</label>
        <input type="text" name="path_preview" value="{{ old('path_preview', $page->path_preview) }}"
               placeholder="เช่น /pro/wedding/bangkok"
               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
      </div>
    </fieldset>

    {{-- ── Meta tags ──────────────────────────────────────────────────── --}}
    <fieldset class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5">
      <legend class="px-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Meta Tags</legend>

      <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Title</label>
      <input type="text" name="title" x-model="title"
             value="{{ old('title', $page->title) }}"
             class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
      <p class="text-[11px] mt-1" :class="title.length > 60 ? 'text-rose-500' : 'text-slate-500'">
        <span x-text="title.length"></span> / 60 ตัวอักษร
      </p>

      <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mt-3 mb-1">Description</label>
      <textarea name="description" rows="2" x-model="description"
                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">{{ old('description', $page->description) }}</textarea>
      <p class="text-[11px] mt-1" :class="description.length > 160 ? 'text-rose-500' : 'text-slate-500'">
        <span x-text="description.length"></span> / 160 ตัวอักษร
      </p>

      <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mt-3 mb-1">Keywords (comma-separated)</label>
      <input type="text" name="keywords" value="{{ old('keywords', $page->keywords) }}"
             class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Canonical URL</label>
          <input type="text" name="canonical_url" value="{{ old('canonical_url', $page->canonical_url) }}"
                 placeholder="https://…"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Meta Robots</label>
          <input type="text" name="meta_robots" value="{{ old('meta_robots', $page->meta_robots) }}"
                 placeholder="index, follow"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm font-mono">
        </div>
      </div>
    </fieldset>

    {{-- ── Open Graph / Twitter ───────────────────────────────────────── --}}
    <fieldset class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5">
      <legend class="px-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Open Graph (Facebook / LINE / Twitter)</legend>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">OG Title (เว้นว่าง = ใช้ Title ข้างบน)</label>
          <input type="text" name="og_title" value="{{ old('og_title', $page->og_title) }}"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">OG Type</label>
          <select name="og_type" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
            @foreach(['' => '— ใช้ default (website) —', 'website' => 'website', 'article' => 'article', 'product' => 'product', 'profile' => 'profile'] as $val => $lbl)
              <option value="{{ $val }}" {{ old('og_type', $page->og_type) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mt-3 mb-1">OG Description</label>
      <textarea name="og_description" rows="2" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">{{ old('og_description', $page->og_description) }}</textarea>

      <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mt-3 mb-1">OG Image (1200×630 absolute URL)</label>
      <input type="text" name="og_image" value="{{ old('og_image', $page->og_image) }}"
             placeholder="https://cdn.example.com/og/wedding-bkk.jpg"
             class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
    </fieldset>

    {{-- ── JSON-LD ────────────────────────────────────────────────────── --}}
    <fieldset class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5">
      <legend class="px-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Structured Data (JSON-LD)</legend>
      <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">
        ใส่ array ของ schema objects (1+) — เช่น <code>[{"@type":"Event",...}]</code>.
        แต่ละ object ต้องมี <code>@type</code> ไม่งั้น Google ignore.
      </p>
      <textarea name="structured_data_text" rows="6"
                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-900 text-emerald-300 text-xs font-mono">{{ old('structured_data_text', $page->structured_data ? json_encode($page->structured_data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
    </fieldset>

    {{-- ── Image alt text overrides ─────────────────────────────────── --}}
    <fieldset class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5">
      <legend class="px-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Image Alt Text Overrides (JSON map)</legend>
      <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">
        คู่ key→value สำหรับ alt-text บน image ที่ view รู้จัก เช่น <code>{"hero":"ช่างภาพงานวิ่งกรุงเทพ"}</code>
      </p>
      <textarea name="alt_text_map_text" rows="3"
                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-900 text-emerald-300 text-xs font-mono">{{ old('alt_text_map_text', $page->alt_text_map ? json_encode($page->alt_text_map, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea>
    </fieldset>

    {{-- ── Lifecycle ────────────────────────────────────────────────── --}}
    <fieldset class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5">
      <legend class="px-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">Lifecycle</legend>
      <div class="flex flex-wrap gap-6">
        <label class="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" name="is_active" value="1" {{ old('is_active', $page->is_active ?? true) ? 'checked' : '' }}>
          Active (override applied to live site)
        </label>
        <label class="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" name="is_locked" value="1" {{ old('is_locked', $page->is_locked ?? false) ? 'checked' : '' }}>
          Lock (ห้ามแก้/ลบโดยไม่ปลด lock)
        </label>
      </div>
      <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mt-3 mb-1">Change reason (จะแสดงใน history)</label>
      <input type="text" name="change_reason" maxlength="200"
             placeholder="เช่น 'อัปเดต title เพราะ keyword research แสดง impressions ลดลง 30%'"
             class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
    </fieldset>

    {{-- ── Submit + delete ─────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-2 items-center">
      <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">
        <i class="bi bi-save"></i> {{ $isNew ? 'สร้าง override' : 'บันทึกเปลี่ยนแปลง' }}
      </button>
      @if(!$isNew)
        <a href="{{ route('admin.seo.show', $page) }}" class="px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-semibold">
          <i class="bi bi-clock-history"></i> ดูประวัติ
        </a>
      @endif
      <a href="{{ route('admin.seo.index') }}" class="ml-auto text-sm text-slate-500 hover:underline">ยกเลิก</a>
    </div>

    @if(!$isNew && !$page->is_locked)
      <div class="border-t border-slate-200 dark:border-white/10 pt-4 mt-6">
        <details>
          <summary class="cursor-pointer text-rose-600 dark:text-rose-400 text-sm font-semibold">⚠️ Danger zone — ลบ override</summary>
          <div class="mt-3">
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">การลบจะกลับไปใช้ค่าจาก controller ทันที. ประวัติยังเก็บไว้ใน revisions.</p>
            <form method="POST" action="{{ route('admin.seo.destroy', $page) }}" onsubmit="return confirm('ลบ SEO override นี้?')">
              @csrf @method('DELETE')
              <button class="px-3 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">ลบ override</button>
            </form>
          </div>
        </details>
      </div>
    @endif
  </form>
</div>
@endsection
