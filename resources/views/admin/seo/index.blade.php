@extends('layouts.admin')

@section('title', 'SEO Management')

@section('content')
<div class="max-w-[1600px] mx-auto px-4 py-6">

  {{-- ─── Header + dashboard summary ─────────────────────────────────── --}}
  <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
    <div>
      <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">SEO Management</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400">จัดการ Title / Description / Keywords / OG / JSON-LD ของทุกหน้า</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('admin.seo.audit') }}" class="px-3 py-2 rounded-lg bg-amber-100 dark:bg-amber-500/15 hover:bg-amber-200 text-amber-700 dark:text-amber-300 text-sm font-semibold">
        <i class="bi bi-bug-fill"></i> Audit
        @if($summary['has_warnings'] > 0)
          <span class="ml-1 inline-block px-1.5 rounded bg-amber-500 text-white text-[10px]">{{ $summary['has_warnings'] }}</span>
        @endif
      </a>
      <a href="{{ route('admin.seo.create') }}" class="px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
        <i class="bi bi-plus-lg"></i> เพิ่ม SEO override
      </a>
    </div>
  </div>

  {{-- Summary tiles — 7 numbers, derived from SeoValidator::dashboardSummary --}}
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
    @foreach([
      ['ทั้งหมด',          $summary['total'],                'bi-files',         'sky'],
      ['Active',           $summary['active'],               'bi-check-circle',  'emerald'],
      ['ขาด title',        $summary['missing_title'],        'bi-exclamation-triangle', 'rose'],
      ['ขาด description',  $summary['missing_description'],  'bi-exclamation-triangle', 'rose'],
      ['title ยาวเกิน',    $summary['too_long_title'],       'bi-arrow-bar-right', 'amber'],
      ['desc ยาวเกิน',     $summary['too_long_description'], 'bi-arrow-bar-right', 'amber'],
      ['noindex',          $summary['noindex'],              'bi-eye-slash',     'slate'],
    ] as [$lbl, $val, $icon, $tone])
      <div class="rounded-2xl p-4 bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10">
        <div class="flex items-center justify-between mb-1">
          <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold">{{ $lbl }}</span>
          <i class="bi {{ $icon }} text-{{ $tone }}-500"></i>
        </div>
        <div class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ number_format($val) }}</div>
      </div>
    @endforeach
  </div>

  {{-- ─── Filters ─────────────────────────────────────────────────────── --}}
  <form method="GET" class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-4 mb-4 grid grid-cols-1 md:grid-cols-5 gap-3">
    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
           placeholder="ค้นหา route_name / title / description…"
           class="md:col-span-2 px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
    <select name="locale" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
      <option value="">— ทุกภาษา —</option>
      @foreach($locales as $code => $label)
        <option value="{{ $code }}" {{ ($filters['locale'] ?? '') === $code ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
      <input type="checkbox" name="warnings_only" value="1" {{ !empty($filters['warnings_only']) ? 'checked' : '' }}>
      เฉพาะที่มีปัญหา
    </label>
    <button type="submit" class="px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold">
      <i class="bi bi-search"></i> กรอง
    </button>
  </form>

  {{-- ─── Bulk action bar (only shows when rows selected) ───────────────── --}}
  <form method="POST" action="{{ route('admin.seo.bulk') }}" id="bulk-form">
    @csrf
    <div x-data="{ selected: [] }"
         @selection-changed.window="selected = $event.detail.ids"
         x-show="selected.length > 0" x-cloak
         class="sticky top-0 z-30 mb-4 rounded-2xl bg-indigo-600 text-white p-3 flex flex-wrap items-center gap-3 shadow-lg">
      <span class="font-semibold text-sm" x-text="selected.length + ' หน้าถูกเลือก'"></span>
      <template x-for="id in selected" :key="id">
        <input type="hidden" name="ids[]" :value="id">
      </template>
      <select name="field" class="px-2 py-1 rounded-lg text-slate-900 text-sm">
        <option value="meta_robots">meta_robots</option>
        <option value="is_active">is_active</option>
        <option value="og_image">og_image</option>
        <option value="append_keywords">append keywords</option>
      </select>
      <input type="text" name="value" placeholder="ค่าใหม่"
             class="flex-1 min-w-[200px] px-2 py-1 rounded-lg text-slate-900 text-sm">
      <input type="text" name="append_with" placeholder="(เฉพาะ append_keywords)"
             class="px-2 py-1 rounded-lg text-slate-900 text-sm">
      <button type="submit" class="px-3 py-1.5 rounded-lg bg-white text-indigo-700 hover:bg-indigo-50 text-sm font-bold">
        ใช้กับทั้งหมดที่เลือก
      </button>
    </div>
  </form>

  {{-- ─── Pages table ────────────────────────────────────────────────── --}}
  <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 overflow-hidden"
       x-data="{
         ids: [],
         toggle(id) {
           const i = this.ids.indexOf(id);
           if (i === -1) this.ids.push(id); else this.ids.splice(i, 1);
           window.dispatchEvent(new CustomEvent('selection-changed', {detail: {ids: this.ids}}));
         },
         all(ev) {
           this.ids = ev.target.checked ? Array.from(document.querySelectorAll('[data-row-id]')).map(e => +e.dataset.rowId) : [];
           document.querySelectorAll('[data-row-cb]').forEach(c => c.checked = ev.target.checked);
           window.dispatchEvent(new CustomEvent('selection-changed', {detail: {ids: this.ids}}));
         },
       }">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
        <tr>
          <th class="px-3 py-2 text-left"><input type="checkbox" @change="all"></th>
          <th class="px-3 py-2 text-left">Route / Path</th>
          <th class="px-3 py-2 text-left">Title</th>
          <th class="px-3 py-2 text-left">Locale</th>
          <th class="px-3 py-2 text-left">สถานะ</th>
          <th class="px-3 py-2 text-left">อัปเดต</th>
          <th class="px-3 py-2 text-right">การจัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-white/10">
        @forelse($pages as $p)
        <tr class="hover:bg-slate-50 dark:hover:bg-white/5" data-row-id="{{ $p->id }}">
          <td class="px-3 py-2">
            <input type="checkbox" data-row-cb @change="toggle({{ $p->id }})" form="bulk-form">
          </td>
          <td class="px-3 py-2">
            <code class="text-xs text-indigo-600 dark:text-indigo-300">{{ $p->route_name }}</code>
            @if($p->path_preview)
              <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate max-w-[280px]">{{ $p->path_preview }}</div>
            @endif
            @if(!empty($p->validation_warnings))
              <div class="mt-0.5"><span class="inline-block px-1.5 py-0.5 text-[10px] font-bold rounded bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300">{{ count($p->validation_warnings) }} warning</span></div>
            @endif
          </td>
          <td class="px-3 py-2 text-slate-700 dark:text-slate-200 max-w-[260px]">
            <span class="line-clamp-2">{{ $p->title ?: '—' }}</span>
            @if($p->title)
              <div class="text-[11px] text-slate-400 mt-0.5">{{ mb_strlen($p->title) }} ตัวอักษร</div>
            @endif
          </td>
          <td class="px-3 py-2 text-xs uppercase text-slate-600 dark:text-slate-400">{{ $p->locale }}</td>
          <td class="px-3 py-2">
            @if($p->is_active)
              <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">active</span>
            @else
              <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400">inactive</span>
            @endif
            @if($p->is_locked)
              <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300"><i class="bi bi-lock-fill"></i></span>
            @endif
          </td>
          <td class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">{{ $p->updated_at?->diffForHumans() }}</td>
          <td class="px-3 py-2 text-right">
            <a href="{{ route('admin.seo.edit', $p) }}" class="text-indigo-600 dark:text-indigo-300 hover:underline text-sm">แก้ไข</a>
            <span class="text-slate-300 mx-1">·</span>
            <a href="{{ route('admin.seo.show', $p) }}" class="text-slate-600 dark:text-slate-400 hover:underline text-sm">ประวัติ</a>
          </td>
        </tr>
        @empty
        <tr><td colspan="7" class="px-3 py-12 text-center text-slate-500">
          ยังไม่มี SEO override — กด <a href="{{ route('admin.seo.create') }}" class="text-indigo-600 hover:underline">เพิ่ม</a> เพื่อสร้างรายการแรก
        </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-4">{{ $pages->links() }}</div>
</div>
@endsection
