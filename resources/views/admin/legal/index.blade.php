@extends('layouts.admin')

@section('title', 'หน้ากฎหมาย & นโยบาย')

@section('content')
<div class="flex flex-wrap justify-between items-center mb-6 gap-2">
  <div>
    <h4 class="font-bold mb-1 tracking-tight text-slate-800 dark:text-gray-100">
      <i class="bi bi-file-earmark-text mr-2 text-indigo-500"></i>หน้ากฎหมาย &amp; นโยบาย
    </h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-0">
      จัดการนโยบายความเป็นส่วนตัว ข้อกำหนดการให้บริการ และนโยบายการคืนเงิน
      <span class="inline-flex items-center gap-1 text-indigo-500 ml-2">
        <i class="bi bi-clock-history"></i> มี version history ครบทุกการแก้ไข
      </span>
    </p>
  </div>
  <a href="{{ route('admin.legal.create') }}"
     class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-5 py-2 inline-flex items-center gap-1 transition hover:from-indigo-600 hover:to-indigo-700">
    <i class="bi bi-plus-lg mr-1"></i> เพิ่มหน้าใหม่
  </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
  @forelse($pages as $page)
  <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 overflow-hidden flex flex-col">
    <div class="p-5 flex-1">
      <div class="flex items-start justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
          @php
            $icon = match($page->slug) {
              'privacy-policy'   => 'bi-shield-lock',
              'terms-of-service' => 'bi-file-earmark-ruled',
              'refund-policy'    => 'bi-cash-coin',
              default            => 'bi-file-earmark-text',
            };
            $color = match($page->slug) {
              'privacy-policy'   => 'text-blue-500',
              'terms-of-service' => 'text-indigo-500',
              'refund-policy'    => 'text-emerald-500',
              default            => 'text-gray-500',
            };
          @endphp
          <div class="w-10 h-10 rounded-lg bg-gray-50 dark:bg-white/5 flex items-center justify-center {{ $color }}">
            <i class="bi {{ $icon }} text-lg"></i>
          </div>
          <div>
            <h5 class="font-semibold text-slate-800 dark:text-gray-100 mb-0">{{ $page->title }}</h5>
            <code class="text-[0.7rem] text-gray-500">/{{ $page->slug }}</code>
          </div>
        </div>
        @if($page->is_published)
        <span class="inline-flex items-center gap-1 text-[0.65rem] font-semibold px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
          <i class="bi bi-check-circle-fill"></i> เผยแพร่
        </span>
        @else
        <span class="inline-flex items-center gap-1 text-[0.65rem] font-semibold px-2 py-1 rounded-full bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400">
          <i class="bi bi-eye-slash"></i> ซ่อน
        </span>
        @endif
      </div>

      <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs mt-4">
        <div>
          <dt class="text-gray-500 dark:text-gray-400">เวอร์ชัน</dt>
          <dd class="font-semibold text-slate-700 dark:text-gray-200">v{{ $page->version }}</dd>
        </div>
        <div>
          <dt class="text-gray-500 dark:text-gray-400">มีผลตั้งแต่</dt>
          <dd class="font-semibold text-slate-700 dark:text-gray-200">
            {{ $page->effective_date ? $page->effective_date->format('d/m/Y') : '—' }}
          </dd>
        </div>
        <div class="col-span-2">
          <dt class="text-gray-500 dark:text-gray-400">อัพเดทล่าสุด</dt>
          <dd class="font-medium text-slate-700 dark:text-gray-200 truncate">
            {{ $page->updated_at?->diffForHumans() ?? '—' }}
            @if($page->updatedBy)
              <span class="text-gray-400">· โดย {{ $page->updatedBy->full_name ?? $page->updatedBy->email }}</span>
            @endif
          </dd>
        </div>
      </dl>
    </div>

    <div class="px-5 py-3 bg-gray-50 dark:bg-white/[0.02] border-t border-gray-100 dark:border-white/5 flex items-center gap-2 flex-wrap">
      <a href="{{ route('admin.legal.edit', $page) }}"
         class="flex-1 min-w-0 text-center text-xs font-semibold bg-indigo-500 text-white rounded-lg px-3 py-2 hover:bg-indigo-600 transition inline-flex items-center justify-center gap-1">
        <i class="bi bi-pencil"></i> แก้ไข
      </a>
      <a href="{{ route('admin.legal.history', $page) }}"
         class="text-xs font-semibold bg-white dark:bg-slate-700 border border-gray-200 dark:border-white/10 text-slate-700 dark:text-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-white/5 transition inline-flex items-center gap-1" title="ประวัติเวอร์ชัน">
        <i class="bi bi-clock-history"></i>
      </a>
      <a href="{{ route('legal.show', $page->slug) }}" target="_blank"
         class="text-xs font-semibold bg-white dark:bg-slate-700 border border-gray-200 dark:border-white/10 text-slate-700 dark:text-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-white/5 transition inline-flex items-center gap-1" title="ดูหน้าสาธารณะ">
        <i class="bi bi-box-arrow-up-right"></i>
      </a>
      <form method="POST" action="{{ route('admin.legal.toggle-publish', $page) }}" class="inline">
        @csrf
        <button type="submit"
                class="text-xs font-semibold bg-white dark:bg-slate-700 border border-gray-200 dark:border-white/10 text-slate-700 dark:text-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-white/5 transition inline-flex items-center gap-1"
                title="{{ $page->is_published ? 'ซ่อนหน้านี้' : 'เผยแพร่หน้านี้' }}">
          <i class="bi {{ $page->is_published ? 'bi-eye-slash' : 'bi-eye' }}"></i>
        </button>
      </form>
    </div>
  </div>
  @empty
  <div class="md:col-span-2 xl:col-span-3 text-center py-12 bg-white dark:bg-slate-800 rounded-xl border border-dashed border-gray-200 dark:border-white/10">
    <i class="bi bi-file-earmark-text text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
    <p class="text-gray-500 dark:text-gray-400">ยังไม่มีหน้ากฎหมาย</p>
  </div>
  @endforelse
</div>
@endsection
