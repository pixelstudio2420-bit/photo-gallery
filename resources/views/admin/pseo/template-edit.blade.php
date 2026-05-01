@extends('layouts.admin')

@section('title', 'แก้ไข Template — ' . $template->name)

@section('content')
<div class="max-w-4xl mx-auto pb-16">

  <div class="flex items-center justify-between gap-3 mb-6">
    <div>
      <a href="{{ route('admin.pseo.index') }}" class="text-xs text-slate-500 hover:text-indigo-500"><i class="bi bi-arrow-left"></i> Back to pSEO Dashboard</a>
      <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white mt-1">{{ $template->name }}</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400">Type: <code class="px-1.5 py-0.5 bg-slate-100 dark:bg-white/[0.06] rounded">{{ $template->type }}</code></p>
    </div>
  </div>

  @if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-sm">
      <i class="bi bi-check-circle-fill mr-1"></i>{{ session('success') }}
    </div>
  @endif
  @if($errors->any())
    <div class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 text-sm">
      <i class="bi bi-exclamation-circle-fill mr-1"></i>{{ $errors->first() }}
    </div>
  @endif

  <form method="POST" action="{{ route('admin.pseo.template-update', $template) }}" class="space-y-5">
    @csrf
    @method('PUT')

    {{-- Settings Card --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm p-5 space-y-4">
      <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
        <i class="bi bi-sliders text-indigo-500"></i>Configuration
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Template Name</label>
          <input type="text" name="name" value="{{ old('name', $template->name) }}" required
                 class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-slate-100">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Min Data Points</label>
          <input type="number" name="min_data_points" value="{{ old('min_data_points', $template->min_data_points) }}" required min="1"
                 class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-slate-100">
          <p class="text-[10px] text-slate-400 mt-1">ป้องกัน thin content — ข้ามหน้าที่ data น้อยกว่านี้</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Schema.org Type</label>
          <input type="text" name="schema_type" value="{{ old('schema_type', $template->schema_type) }}" placeholder="ItemList / Person / Event"
                 class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-slate-100">
        </div>
      </div>

      <label class="flex items-center gap-2 cursor-pointer">
        <input type="hidden" name="is_auto_enabled" value="0">
        <input type="checkbox" name="is_auto_enabled" value="1" {{ $template->is_auto_enabled ? 'checked' : '' }} class="w-4 h-4">
        <span class="text-sm text-slate-700 dark:text-slate-300">เปิด Auto-Generate (ระบบจะสร้างหน้าใหม่อัตโนมัติเมื่อ regen)</span>
      </label>
    </div>

    {{-- Patterns Card --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm p-5 space-y-4">
      <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
        <i class="bi bi-braces text-violet-500"></i>Patterns (รองรับ {variable} substitution)
      </h3>

      <div>
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Title Pattern</label>
        <input type="text" name="title_pattern" value="{{ old('title_pattern', $template->title_pattern) }}" required maxlength="500"
               class="w-full px-3 py-2 rounded-lg text-sm font-mono bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-slate-100">
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Meta Description Pattern</label>
        <input type="text" name="meta_description_pattern" value="{{ old('meta_description_pattern', $template->meta_description_pattern) }}" required maxlength="500"
               class="w-full px-3 py-2 rounded-lg text-sm font-mono bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-slate-100">
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">H1 Pattern</label>
        <input type="text" name="h1_pattern" value="{{ old('h1_pattern', $template->h1_pattern) }}" maxlength="500"
               class="w-full px-3 py-2 rounded-lg text-sm font-mono bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-slate-100">
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Body Template (markdown / plain text)</label>
        <textarea name="body_template" rows="8"
                  class="w-full px-3 py-2 rounded-lg text-sm font-mono bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-slate-100">{{ old('body_template', $template->body_template) }}</textarea>
      </div>

      {{-- Variable cheat-sheet --}}
      <div class="p-3 bg-slate-50 dark:bg-white/[0.04] rounded-lg text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
        <div class="font-semibold mb-1 text-slate-700 dark:text-slate-300"><i class="bi bi-info-circle"></i> Available variables:</div>
        <div class="flex flex-wrap gap-1.5">
          <code class="px-1.5 py-0.5 bg-white dark:bg-slate-800 rounded">&lbrace;location&rbrace;</code>
          <code class="px-1.5 py-0.5 bg-white dark:bg-slate-800 rounded">&lbrace;location_en&rbrace;</code>
          <code class="px-1.5 py-0.5 bg-white dark:bg-slate-800 rounded">&lbrace;category&rbrace;</code>
          <code class="px-1.5 py-0.5 bg-white dark:bg-slate-800 rounded">&lbrace;category_full&rbrace;</code>
          <code class="px-1.5 py-0.5 bg-white dark:bg-slate-800 rounded">&lbrace;event_count&rbrace;</code>
          <code class="px-1.5 py-0.5 bg-white dark:bg-slate-800 rounded">&lbrace;photographer_count&rbrace;</code>
          <code class="px-1.5 py-0.5 bg-white dark:bg-slate-800 rounded">&lbrace;name&rbrace;</code>
          <code class="px-1.5 py-0.5 bg-white dark:bg-slate-800 rounded">&lbrace;brand&rbrace;</code>
          <code class="px-1.5 py-0.5 bg-white dark:bg-slate-800 rounded">&lbrace;year&rbrace;</code>
        </div>
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <a href="{{ route('admin.pseo.index') }}" class="px-4 py-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 text-sm">ยกเลิก</a>
      <button type="submit" class="px-5 py-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-sm font-semibold shadow-md">
        <i class="bi bi-save"></i> บันทึก
      </button>
    </div>
  </form>

</div>
@endsection
