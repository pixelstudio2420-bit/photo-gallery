@extends('layouts.admin')

@section('title', 'pSEO Dashboard')

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- Page Header --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-lg shadow-emerald-500/30">
        <i class="bi bi-globe text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">pSEO Dashboard</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          ระบบ Programmatic-SEO สร้างหน้า landing อัตโนมัติจากข้อมูลอีเวนต์ + ช่างภาพ
        </p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <a href="{{ route('admin.pseo.pages') }}"
         class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
        <i class="bi bi-list-ul"></i> ดูหน้าทั้งหมด
      </a>
      <form method="POST" action="{{ route('admin.pseo.regenerate-all') }}"
            onsubmit="return confirm('สร้าง landing page ใหม่ทั้งหมดจาก template (จะเขียนทับยกเว้นหน้าที่ locked) ยืนยันหรือไม่?');">
        @csrf
        <button class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-md transition">
          <i class="bi bi-arrow-clockwise"></i> Regenerate All
        </button>
      </form>
    </div>
  </div>

  {{-- Flash --}}
  @if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 text-sm">
      <i class="bi bi-check-circle-fill mr-1"></i>{{ session('success') }}
    </div>
  @endif

  {{-- KPI Cards --}}
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/[0.06] p-4">
      <div class="text-xs text-slate-500 mb-1"><i class="bi bi-globe mr-1"></i>หน้าทั้งหมด</div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['total_pages']) }}</div>
    </div>
    <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/[0.06] p-4">
      <div class="text-xs text-slate-500 mb-1"><i class="bi bi-check-circle mr-1 text-emerald-500"></i>เผยแพร่</div>
      <div class="text-2xl font-bold text-emerald-600">{{ number_format($stats['published_pages']) }}</div>
    </div>
    <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/[0.06] p-4">
      <div class="text-xs text-slate-500 mb-1"><i class="bi bi-lock mr-1 text-amber-500"></i>ล็อก (admin-edited)</div>
      <div class="text-2xl font-bold text-amber-600">{{ number_format($stats['locked_pages']) }}</div>
    </div>
    <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/[0.06] p-4">
      <div class="text-xs text-slate-500 mb-1"><i class="bi bi-eye mr-1 text-indigo-500"></i>ยอดเข้าชม</div>
      <div class="text-2xl font-bold text-indigo-600">{{ number_format($stats['total_views']) }}</div>
    </div>
    <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/[0.06] p-4">
      <div class="text-xs text-slate-500 mb-1"><i class="bi bi-clock-history mr-1 text-rose-500"></i>หน้าเก่ารอ regen</div>
      <div class="text-2xl font-bold text-rose-600">{{ number_format($stats['stale_pages']) }}</div>
    </div>
  </div>

  {{-- Templates Grid --}}
  <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-6 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 bg-gradient-to-r from-indigo-50 to-violet-50 dark:from-indigo-500/[0.08] dark:to-violet-500/[0.08]">
      <h2 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
        <i class="bi bi-layers text-indigo-600 dark:text-indigo-400"></i>Page Templates
      </h2>
      <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
        เปิด/ปิด auto-generate ของแต่ละ page type ได้แยก — ปิดแล้วไม่สร้างใหม่ แต่หน้าที่มีอยู่ยังเปิด
      </p>
    </div>
    <div class="divide-y divide-slate-100 dark:divide-white/[0.04]">
      @forelse($templates as $t)
        @php
          $typeBadges = [
            'location'      => ['icon' => 'bi-geo-alt-fill',         'color' => 'sky'],
            'category'      => ['icon' => 'bi-tags-fill',            'color' => 'violet'],
            'combo'         => ['icon' => 'bi-shuffle',              'color' => 'pink'],
            'photographer'  => ['icon' => 'bi-person-badge-fill',    'color' => 'amber'],
            'event_archive' => ['icon' => 'bi-calendar-week-fill',   'color' => 'emerald'],
            'custom'        => ['icon' => 'bi-pencil-fill',          'color' => 'slate'],
          ];
          $badge = $typeBadges[$t->type] ?? $typeBadges['custom'];
          $pageCount = $t->landingPages()->count();
        @endphp
        <div class="p-4 flex items-center gap-4 hover:bg-slate-50/50 dark:hover:bg-white/[0.02] transition">
          <div class="w-10 h-10 rounded-lg bg-{{ $badge['color'] }}-500/10 text-{{ $badge['color'] }}-600 dark:text-{{ $badge['color'] }}-400 flex items-center justify-center shrink-0">
            <i class="bi {{ $badge['icon'] }}"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-sm text-slate-900 dark:text-white">{{ $t->name }}</span>
              <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-{{ $badge['color'] }}-100 dark:bg-{{ $badge['color'] }}-500/20 text-{{ $badge['color'] }}-700 dark:text-{{ $badge['color'] }}-300 font-bold">{{ $t->type }}</span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate font-mono">{{ $t->title_pattern }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">{{ $pageCount }} หน้า · min {{ $t->min_data_points }} data points</div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <form method="POST" action="{{ route('admin.pseo.template-toggle', $t) }}">
              @csrf
              <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $t->is_auto_enabled ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600' }}">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $t->is_auto_enabled ? 'translate-x-6' : 'translate-x-1' }}"></span>
              </button>
            </form>
            <a href="{{ route('admin.pseo.template-edit', $t) }}" class="px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-xs font-semibold hover:bg-indigo-100 transition">
              <i class="bi bi-pencil"></i> แก้ไข
            </a>
            <form method="POST" action="{{ route('admin.pseo.regenerate-template', $t) }}" onsubmit="return confirm('Regenerate {{ $pageCount }} pages?');">
              @csrf
              <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs font-semibold hover:bg-emerald-100 transition">
                <i class="bi bi-arrow-clockwise"></i> Run
              </button>
            </form>
          </div>
        </div>
      @empty
        <div class="p-12 text-center text-slate-400">
          <i class="bi bi-layers text-4xl"></i>
          <p class="mt-2 text-sm">ยังไม่มี template — รัน seeder เพื่อเริ่มต้น</p>
        </div>
      @endforelse
    </div>
  </div>

  {{-- Top Pages + Per-Type Breakdown --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    {{-- Top by Views --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
        <h2 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-trophy text-amber-500"></i>หน้าที่มีผู้เข้าชมสูงสุด
        </h2>
      </div>
      <div class="divide-y divide-slate-100 dark:divide-white/[0.04] max-h-96 overflow-y-auto">
        @forelse($topPages as $p)
          <div class="p-3 flex items-center justify-between gap-3">
            <a href="{{ $p->url() }}" target="_blank" class="flex-1 min-w-0 hover:text-indigo-600">
              <div class="font-medium text-sm truncate">{{ $p->h1 ?? $p->title }}</div>
              <div class="text-[11px] text-slate-400 font-mono truncate">/{{ $p->slug }}</div>
            </a>
            <div class="text-right shrink-0">
              <div class="font-bold text-indigo-600">{{ number_format($p->view_count) }}</div>
              <div class="text-[10px] text-slate-400">views</div>
            </div>
          </div>
        @empty
          <div class="p-8 text-center text-slate-400 text-sm">ยังไม่มีหน้า — กดปุ่ม Run บน template เพื่อสร้าง</div>
        @endforelse
      </div>
    </div>

    {{-- By Type --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
        <h2 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-pie-chart text-violet-500"></i>หน้าตามประเภท
        </h2>
      </div>
      <div class="divide-y divide-slate-100 dark:divide-white/[0.04]">
        @forelse($byType as $row)
          <div class="p-3 flex items-center justify-between gap-3">
            <div>
              <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ $row->type }}</div>
              <div class="text-[11px] text-slate-400">{{ number_format($row->views ?? 0) }} views</div>
            </div>
            <div class="text-2xl font-bold text-violet-600">{{ number_format($row->cnt) }}</div>
          </div>
        @empty
          <div class="p-8 text-center text-slate-400 text-sm">ไม่มีข้อมูล</div>
        @endforelse
      </div>
    </div>
  </div>

</div>
@endsection
