@extends('layouts.admin')

@section('title', 'SEO Audit')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="{{ route('admin.seo.index') }}" class="hover:underline">SEO Management</a>
    <span>›</span>
    <span class="text-slate-700 dark:text-slate-300">Audit</span>
  </div>

  <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-1">SEO Audit</h1>
  <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">หน้าเว็บที่มี warning ตามกฎคุณภาพ Google — ปรับให้ครบเพื่อ rank ดีขึ้น</p>

  {{-- Summary --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    @foreach([
      ['ทั้งหมด',          $summary['total'], 'sky'],
      ['มี warnings',      $summary['has_warnings'], 'amber'],
      ['ขาด title',        $summary['missing_title'], 'rose'],
      ['ขาด description',  $summary['missing_description'], 'rose'],
    ] as [$lbl, $val, $tone])
      <div class="rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-3">
        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold">{{ $lbl }}</div>
        <div class="text-2xl font-extrabold text-{{ $tone }}-600 dark:text-{{ $tone }}-400">{{ number_format($val) }}</div>
      </div>
    @endforeach
  </div>

  {{-- Issues list --}}
  @if($issues->isEmpty())
    <div class="rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 p-8 text-center">
      <i class="bi bi-emoji-smile text-4xl text-emerald-500"></i>
      <p class="font-bold text-lg text-emerald-700 dark:text-emerald-300 mt-2">ไม่มี SEO issue!</p>
      <p class="text-sm text-emerald-600 dark:text-emerald-400">ทุกหน้าที่มี override ผ่านเช็คคุณภาพ Google</p>
    </div>
  @else
    <div class="space-y-3">
      @foreach($issues as $issue)
        @php $page = $issue['page']; @endphp
        <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-amber-200 dark:border-amber-500/30 p-4">
          <div class="flex items-start justify-between gap-3 mb-2">
            <div class="min-w-0 flex-1">
              <code class="text-xs text-indigo-600 dark:text-indigo-300">{{ $page->route_name }}</code>
              <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $page->title ?: '— title ว่าง —' }}</p>
            </div>
            <a href="{{ route('admin.seo.edit', $page) }}" class="px-2.5 py-1 rounded-lg bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 hover:bg-amber-200 text-xs font-bold whitespace-nowrap">
              <i class="bi bi-tools"></i> แก้
            </a>
          </div>
          <ul class="list-disc list-inside text-xs text-amber-700 dark:text-amber-300 space-y-1">
            @foreach($issue['warnings'] as $w)<li>{{ $w }}</li>@endforeach
          </ul>
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection
