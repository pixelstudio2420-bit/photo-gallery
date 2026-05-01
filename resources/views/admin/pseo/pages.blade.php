@extends('layouts.admin')

@section('title', 'pSEO Pages')

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
      <a href="{{ route('admin.pseo.index') }}" class="text-xs text-slate-500 hover:text-indigo-500"><i class="bi bi-arrow-left"></i> Dashboard</a>
      <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white mt-1">pSEO Pages</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400">รายการหน้า landing ทั้งหมดในระบบ</p>
    </div>
  </div>

  @if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-sm">
      <i class="bi bi-check-circle-fill mr-1"></i>{{ session('success') }}
    </div>
  @endif

  {{-- Filters --}}
  <form method="GET" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-xl p-3 mb-4 grid grid-cols-2 md:grid-cols-4 gap-2">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="ค้นหา slug / title"
           class="px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">
    <select name="type" class="px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">
      <option value="">ทุก type</option>
      @foreach($types as $key => $label)
        <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
    <select name="status" class="px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">
      <option value="">ทุกสถานะ</option>
      <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published</option>
      <option value="unpublished" {{ request('status') === 'unpublished' ? 'selected' : '' }}>Unpublished</option>
      <option value="locked" {{ request('status') === 'locked' ? 'selected' : '' }}>Locked</option>
      <option value="stale" {{ request('status') === 'stale' ? 'selected' : '' }}>Stale (>7 days)</option>
    </select>
    <button class="px-4 py-2 rounded-lg bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-semibold"><i class="bi bi-funnel"></i> กรอง</button>
  </form>

  {{-- Pages Table --}}
  <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 dark:bg-white/[0.02]">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Slug / Title</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Type</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
          <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Views</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Updated</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 dark:divide-white/[0.04]">
        @forelse($pages as $p)
          <tr class="hover:bg-slate-50/50 dark:hover:bg-white/[0.02]">
            <td class="px-4 py-3 max-w-[400px]">
              <a href="{{ $p->url() }}" target="_blank" class="font-medium text-sm hover:text-indigo-600 truncate block">{{ $p->h1 ?? $p->title }}</a>
              <code class="text-[10px] text-slate-400 truncate block">/{{ $p->slug }}</code>
            </td>
            <td class="px-4 py-3"><span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/[0.06] text-slate-600 font-bold uppercase">{{ $p->type }}</span></td>
            <td class="px-4 py-3 text-xs space-x-1">
              @if($p->is_published)<span class="text-emerald-600">●</span> Published @else<span class="text-slate-400">○</span> Draft @endif
              @if($p->is_locked)<i class="bi bi-lock-fill text-amber-500" title="Locked"></i>@endif
            </td>
            <td class="px-4 py-3 text-right font-bold text-indigo-600">{{ number_format($p->view_count) }}</td>
            <td class="px-4 py-3 text-xs text-slate-400">{{ $p->updated_at?->diffForHumans() }}</td>
            <td class="px-4 py-3 text-right">
              <a href="{{ route('admin.pseo.page-edit', $p) }}" class="px-2 py-1 rounded bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 text-xs"><i class="bi bi-pencil"></i></a>
              <form method="POST" action="{{ route('admin.pseo.page-destroy', $p) }}" class="inline" onsubmit="return confirm('ลบ {{ $p->slug }}?')">@csrf @method('DELETE')<button class="px-2 py-1 rounded bg-rose-50 dark:bg-rose-500/10 text-rose-600 text-xs"><i class="bi bi-trash"></i></button></form>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center py-12 text-slate-400">ไม่มีหน้า — กดปุ่ม Run บน template ในหน้า dashboard</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $pages->links() }}</div>

</div>
@endsection
