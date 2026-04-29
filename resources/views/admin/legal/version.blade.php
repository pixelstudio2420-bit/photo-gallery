@extends('layouts.admin')

@section('title', 'เวอร์ชัน v' . $version->version . ' — ' . $page->title)

@section('content')
<div class="flex flex-wrap justify-between items-center mb-4 gap-2">
  <div>
    <nav class="text-xs text-gray-500 mb-1">
      <a href="{{ route('admin.legal.index') }}" class="hover:text-indigo-500">หน้ากฎหมาย</a>
      <i class="bi bi-chevron-right mx-1 text-gray-300"></i>
      <a href="{{ route('admin.legal.edit', $page) }}" class="hover:text-indigo-500">{{ $page->title }}</a>
      <i class="bi bi-chevron-right mx-1 text-gray-300"></i>
      <a href="{{ route('admin.legal.history', $page) }}" class="hover:text-indigo-500">ประวัติ</a>
      <i class="bi bi-chevron-right mx-1 text-gray-300"></i>
      <span>v{{ $version->version }}</span>
    </nav>
    <h4 class="font-bold mb-0 tracking-tight text-slate-800 dark:text-gray-100">
      <i class="bi bi-eye mr-2 text-indigo-500"></i>เนื้อหาเวอร์ชัน v{{ $version->version }}
    </h4>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('admin.legal.history', $page) }}"
       class="bg-white dark:bg-slate-700 border border-gray-200 dark:border-white/10 text-slate-700 dark:text-gray-200 rounded-lg font-medium px-4 py-2 inline-flex items-center gap-1 text-sm">
      <i class="bi bi-arrow-left"></i> กลับไปยังประวัติ
    </a>
    @if($version->version !== $page->version)
    <form method="POST" action="{{ route('admin.legal.versions.restore', [$page, $version]) }}" class="inline" id="restoreForm">
      @csrf
      <button type="button" onclick="confirmRestore()"
              class="bg-amber-500 text-white rounded-lg font-semibold px-4 py-2 hover:bg-amber-600 inline-flex items-center gap-1 text-sm">
        <i class="bi bi-arrow-counterclockwise"></i> คืนค่าเป็น v{{ $version->version }}
      </button>
    </form>
    @endif
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-6">
    <h2 class="text-2xl font-bold text-slate-800 dark:text-gray-100 mb-4">{{ $version->title }}</h2>
    <div class="prose prose-slate dark:prose-invert max-w-none text-sm">
      {!! $version->content !!}
    </div>
  </div>

  <div class="space-y-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
      <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-3 text-sm">
        <i class="bi bi-info-circle text-indigo-500 mr-1"></i> ข้อมูลเวอร์ชัน
      </h6>
      <dl class="text-xs space-y-2">
        <div class="flex justify-between">
          <dt class="text-gray-500 dark:text-gray-400">เวอร์ชัน</dt>
          <dd class="font-semibold text-slate-700 dark:text-gray-200">v{{ $version->version }}
            @if($version->version === $page->version)
              <span class="text-emerald-500">(ปัจจุบัน)</span>
            @endif
          </dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-gray-500 dark:text-gray-400">บันทึกเมื่อ</dt>
          <dd class="text-slate-700 dark:text-gray-200">{{ $version->created_at?->format('d/m/Y H:i') }}</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-gray-500 dark:text-gray-400">มีผลตั้งแต่</dt>
          <dd class="text-slate-700 dark:text-gray-200">{{ $version->effective_date?->format('d/m/Y') ?? '—' }}</dd>
        </div>
        @if($version->admin)
        <div class="flex justify-between">
          <dt class="text-gray-500 dark:text-gray-400">แก้ไขโดย</dt>
          <dd class="text-slate-700 dark:text-gray-200 truncate max-w-[140px]" title="{{ $version->admin->email }}">{{ $version->admin->full_name ?? $version->admin->email }}</dd>
        </div>
        @endif
      </dl>
      @if($version->change_note)
      <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/5">
        <h6 class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">บันทึกการเปลี่ยนแปลง</h6>
        <p class="text-xs text-slate-700 dark:text-gray-200">{{ $version->change_note }}</p>
      </div>
      @endif
    </div>

    @if($version->meta_description)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
      <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-2 text-sm">
        <i class="bi bi-card-text text-indigo-500 mr-1"></i> Meta Description
      </h6>
      <p class="text-xs text-slate-600 dark:text-gray-300">{{ $version->meta_description }}</p>
    </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
function confirmRestore() {
  Swal.fire({
    title: 'คืนค่าเป็น v{{ $version->version }}?',
    html: 'เนื้อหาปัจจุบันจะถูกบันทึกเป็นประวัติก่อน แล้วเวอร์ชัน v{{ $version->version }} จะกลายเป็นเนื้อหาหลัก',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'คืนค่า',
    cancelButtonText: 'ยกเลิก',
    confirmButtonColor: '#6366f1',
  }).then(result => {
    if (result.isConfirmed) document.getElementById('restoreForm').submit();
  });
}
</script>
@endpush
