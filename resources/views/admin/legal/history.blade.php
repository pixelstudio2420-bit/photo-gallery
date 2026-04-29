@extends('layouts.admin')

@section('title', 'ประวัติเวอร์ชัน — ' . $page->title)

@section('content')
<div class="flex flex-wrap justify-between items-center mb-4 gap-2">
  <div>
    <nav class="text-xs text-gray-500 mb-1">
      <a href="{{ route('admin.legal.index') }}" class="hover:text-indigo-500">หน้ากฎหมาย</a>
      <i class="bi bi-chevron-right mx-1 text-gray-300"></i>
      <a href="{{ route('admin.legal.edit', $page) }}" class="hover:text-indigo-500">{{ $page->title }}</a>
      <i class="bi bi-chevron-right mx-1 text-gray-300"></i>
      <span>ประวัติ</span>
    </nav>
    <h4 class="font-bold mb-0 tracking-tight text-slate-800 dark:text-gray-100">
      <i class="bi bi-clock-history mr-2 text-indigo-500"></i>ประวัติเวอร์ชัน
      <span class="text-sm font-normal text-gray-500">· {{ $page->title }}</span>
    </h4>
  </div>
  <a href="{{ route('admin.legal.edit', $page) }}"
     class="bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 rounded-lg font-medium px-4 py-2 inline-flex items-center gap-1 text-sm">
    <i class="bi bi-pencil"></i> แก้ไขเวอร์ชันปัจจุบัน
  </a>
</div>

<div class="bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 text-blue-700 dark:text-blue-300 rounded-xl p-4 mb-4 text-sm flex items-start gap-2">
  <i class="bi bi-info-circle-fill mt-0.5"></i>
  <div>
    ทุกการแก้ไขจะบันทึก <strong>snapshot ของเวอร์ชันก่อนหน้า</strong> ไว้ที่นี่ คุณสามารถดูรายละเอียดหรือคืนค่าเป็นเวอร์ชันก่อนหน้าได้
    <br>
    <span class="text-xs">เวอร์ชันปัจจุบัน: <strong>v{{ $page->version }}</strong> · มีผลตั้งแต่: {{ $page->effective_date?->format('d/m/Y') ?? '—' }}</span>
  </div>
</div>

@if($versions->isEmpty())
<div class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl border border-dashed border-gray-200 dark:border-white/10">
  <i class="bi bi-clock-history text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
  <p class="text-gray-500 dark:text-gray-400">ยังไม่มีประวัติการแก้ไข</p>
</div>
@else
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 overflow-hidden">
  <div class="divide-y divide-gray-100 dark:divide-white/5">
    @foreach($versions as $v)
    <div class="p-5 hover:bg-gray-50 dark:hover:bg-white/[0.02] transition">
      <div class="flex items-start gap-4 flex-wrap">
        <div class="shrink-0 w-14 h-14 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex flex-col items-center justify-center">
          <span class="text-[0.6rem] uppercase opacity-70">v</span>
          <span class="font-bold text-sm leading-none">{{ $v->version }}</span>
        </div>
        <div class="flex-1 min-w-0">
          <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-1 text-sm">
            {{ $v->title }}
            @if($v->version === $page->version)
              <span class="ml-2 inline-flex items-center gap-1 text-[0.6rem] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                <i class="bi bi-check-circle-fill"></i> เวอร์ชันปัจจุบัน
              </span>
            @endif
          </h6>
          <p class="text-xs text-slate-600 dark:text-gray-300 mb-2">
            <i class="bi bi-journal-text text-gray-400"></i>
            {{ $v->change_note ?: 'ไม่ได้ระบุการเปลี่ยนแปลง' }}
          </p>
          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.7rem] text-gray-500 dark:text-gray-400">
            <span><i class="bi bi-clock"></i> {{ $v->created_at?->format('d/m/Y H:i') }} ({{ $v->created_at?->diffForHumans() }})</span>
            @if($v->admin)
              <span><i class="bi bi-person"></i> {{ $v->admin->full_name ?? $v->admin->email }}</span>
            @endif
            @if($v->effective_date)
              <span><i class="bi bi-calendar-check"></i> มีผล: {{ $v->effective_date->format('d/m/Y') }}</span>
            @endif
            <span><i class="bi bi-file-earmark-text"></i> {{ number_format(strlen($v->content ?? '')) }} ตัวอักษร</span>
          </div>
        </div>
        <div class="flex gap-2 flex-wrap shrink-0">
          <a href="{{ route('admin.legal.versions.show', [$page, $v]) }}"
             class="text-xs font-semibold bg-white dark:bg-slate-700 border border-gray-200 dark:border-white/10 text-slate-700 dark:text-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-white/5 inline-flex items-center gap-1">
            <i class="bi bi-eye"></i> ดูเนื้อหา
          </a>
          @if($v->version !== $page->version)
          <form method="POST" action="{{ route('admin.legal.versions.restore', [$page, $v]) }}" class="inline restore-form">
            @csrf
            <input type="hidden" name="_version" value="{{ $v->version }}">
            <button type="button" onclick="confirmRestore(this)"
                    class="text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20 rounded-lg px-3 py-1.5 hover:bg-amber-100 dark:hover:bg-amber-500/20 inline-flex items-center gap-1">
              <i class="bi bi-arrow-counterclockwise"></i> คืนค่า
            </button>
          </form>
          @endif
        </div>
      </div>
    </div>
    @endforeach
  </div>
</div>

<div class="mt-4">
  {{ $versions->links() }}
</div>
@endif
@endsection

@push('scripts')
<script>
function confirmRestore(btn) {
  const form = btn.closest('form');
  const version = form.querySelector('input[name="_version"]').value;
  Swal.fire({
    title: 'คืนค่าเป็น v' + version + '?',
    html: 'เนื้อหาปัจจุบันจะถูกบันทึกเป็นประวัติก่อน แล้วเวอร์ชัน v' + version + ' จะกลายเป็นเนื้อหาหลัก<br><br><span class="text-xs text-gray-500">การย้อนกลับสามารถทำได้ทุกเมื่อจากหน้าประวัติ</span>',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'คืนค่า',
    cancelButtonText: 'ยกเลิก',
    confirmButtonColor: '#6366f1',
  }).then(result => {
    if (result.isConfirmed) form.submit();
  });
}
</script>
@endpush
