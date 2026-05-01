@extends('layouts.admin')

@section('title', 'แก้ไขหน้า — ' . $page->slug)

@section('content')
<div class="max-w-4xl mx-auto pb-16">

  <div class="flex items-center justify-between gap-3 mb-6">
    <div>
      <a href="{{ route('admin.pseo.pages') }}" class="text-xs text-slate-500 hover:text-indigo-500"><i class="bi bi-arrow-left"></i> Back to Pages</a>
      <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white mt-1">แก้ไขหน้า</h1>
      <code class="text-xs text-slate-500 dark:text-slate-400">/{{ $page->slug }}</code>
    </div>
    <a href="{{ $page->url() }}" target="_blank" class="px-3 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 text-sm">
      <i class="bi bi-box-arrow-up-right"></i> ดูหน้าจริง
    </a>
  </div>

  @if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-sm">
      <i class="bi bi-check-circle-fill mr-1"></i>{{ session('success') }}
    </div>
  @endif

  <form method="POST" action="{{ route('admin.pseo.page-update', $page) }}" class="space-y-5">
    @csrf
    @method('PUT')

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm p-5 space-y-4">
      <h3 class="font-semibold text-slate-900 dark:text-white"><i class="bi bi-pencil text-indigo-500"></i> SEO Meta</h3>

      <div>
        <label class="block text-xs font-semibold mb-1.5">Title (บน &lt;title&gt;)</label>
        <input type="text" name="title" value="{{ old('title', $page->title) }}" required maxlength="500"
               class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1.5">Meta Description</label>
        <textarea name="meta_description" required maxlength="500" rows="2"
                  class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">{{ old('meta_description', $page->meta_description) }}</textarea>
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1.5">H1 (หัวข้อบนหน้า)</label>
        <input type="text" name="h1" value="{{ old('h1', $page->h1) }}" maxlength="500"
               class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1.5">Body</label>
        <textarea name="body_html" rows="10"
                  class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">{{ old('body_html', $page->body_html) }}</textarea>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="hidden" name="is_published" value="0">
          <input type="checkbox" name="is_published" value="1" {{ $page->is_published ? 'checked' : '' }} class="w-4 h-4">
          <span class="text-sm">เผยแพร่</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="hidden" name="is_locked" value="0">
          <input type="checkbox" name="is_locked" value="1" {{ $page->is_locked ? 'checked' : '' }} class="w-4 h-4">
          <span class="text-sm"><i class="bi bi-lock-fill text-amber-500"></i> ล็อก (ไม่ให้ regen ทับ)</span>
        </label>
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <a href="{{ route('admin.pseo.pages') }}" class="px-4 py-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 text-sm">ยกเลิก</a>
      <button class="px-5 py-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 text-white text-sm font-semibold shadow-md"><i class="bi bi-save"></i> บันทึก</button>
    </div>
  </form>

</div>
@endsection
