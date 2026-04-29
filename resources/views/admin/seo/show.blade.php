@extends('layouts.admin')

@section('title', 'SEO · ' . $page->route_name)

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="{{ route('admin.seo.index') }}" class="hover:underline">SEO Management</a>
    <span>›</span>
    <span class="text-slate-700 dark:text-slate-300">{{ $page->route_name }}</span>
  </div>

  @if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 text-emerald-700 dark:text-emerald-300 text-sm">
      <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
  @endif

  <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5 mb-4">
    <div class="flex items-start justify-between">
      <div>
        <code class="text-sm text-indigo-600 dark:text-indigo-300">{{ $page->route_name }}</code>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $page->title ?: '— title ว่าง —' }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ $page->description ?: '— description ว่าง —' }}</p>
      </div>
      <a href="{{ route('admin.seo.edit', $page) }}" class="px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
        <i class="bi bi-pencil"></i> แก้ไข
      </a>
    </div>

    @if(!empty($warnings))
      <div class="mt-4 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 p-3 text-sm text-amber-700 dark:text-amber-300">
        <strong>{{ count($warnings) }} warning:</strong>
        <ul class="list-disc list-inside mt-1">@foreach($warnings as $w)<li>{{ $w }}</li>@endforeach</ul>
      </div>
    @endif
  </div>

  {{-- ── Revision history ────────────────────────────────────────────── --}}
  <h2 class="text-base font-bold text-slate-900 dark:text-white mb-3">ประวัติการแก้ไข ({{ $revisions->count() }} revisions)</h2>
  <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase text-slate-500 dark:text-slate-400">
        <tr>
          <th class="px-3 py-2 text-left">Version</th>
          <th class="px-3 py-2 text-left">Title</th>
          <th class="px-3 py-2 text-left">Reason</th>
          <th class="px-3 py-2 text-left">เมื่อ</th>
          <th class="px-3 py-2 text-right"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-white/10">
        @forelse($revisions as $rev)
        <tr>
          <td class="px-3 py-2 font-bold">v{{ $rev->version }}</td>
          <td class="px-3 py-2 text-slate-700 dark:text-slate-200 max-w-[260px]">
            <span class="line-clamp-1">{{ $rev->snapshot['title'] ?? '—' }}</span>
          </td>
          <td class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">{{ $rev->change_reason ?: '—' }}</td>
          <td class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">{{ $rev->created_at?->diffForHumans() }}</td>
          <td class="px-3 py-2 text-right">
            <form method="POST" action="{{ route('admin.seo.rollback', ['seoPage' => $page, 'revisionId' => $rev->id]) }}"
                  onsubmit="return confirm('Rollback ไป v{{ $rev->version }}?')">
              @csrf
              <button class="text-xs text-amber-600 dark:text-amber-400 hover:underline">↺ rollback</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">ยังไม่มีประวัติ</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
