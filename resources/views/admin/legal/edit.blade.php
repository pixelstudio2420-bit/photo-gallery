@extends('layouts.admin')

@section('title', 'แก้ไข ' . $page->title)

@push('styles')
<style>
  /* TinyMCE wrapper — match admin dark palette */
  .tox-tinymce { border-radius: 0.75rem !important; border-color: #e5e7eb !important; }
  .dark .tox-tinymce { border-color: rgba(255,255,255,0.1) !important; }
</style>
@endpush

@section('content')
<div class="flex flex-wrap justify-between items-center mb-4 gap-2">
  <div>
    <nav class="text-xs text-gray-500 mb-1">
      <a href="{{ route('admin.legal.index') }}" class="hover:text-indigo-500">หน้ากฎหมาย</a>
      <i class="bi bi-chevron-right mx-1 text-gray-300"></i>
      <span>{{ $page->title }}</span>
    </nav>
    <h4 class="font-bold mb-0 tracking-tight text-slate-800 dark:text-gray-100">
      <i class="bi bi-pencil-square mr-2 text-indigo-500"></i>แก้ไข {{ $page->title }}
      <span class="inline-flex items-center gap-1 text-[0.65rem] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400 align-middle ml-2">
        v{{ $page->version }}
      </span>
    </h4>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('admin.legal.history', $page) }}"
       class="bg-white dark:bg-slate-700 border border-gray-200 dark:border-white/10 text-slate-700 dark:text-gray-200 rounded-lg font-medium px-4 py-2 inline-flex items-center gap-1 text-sm hover:bg-gray-50 dark:hover:bg-white/5">
      <i class="bi bi-clock-history"></i> ประวัติเวอร์ชัน
    </a>
    <a href="{{ route('legal.show', $page->slug) }}" target="_blank"
       class="bg-white dark:bg-slate-700 border border-gray-200 dark:border-white/10 text-slate-700 dark:text-gray-200 rounded-lg font-medium px-4 py-2 inline-flex items-center gap-1 text-sm hover:bg-gray-50 dark:hover:bg-white/5">
      <i class="bi bi-box-arrow-up-right"></i> ดูหน้าสาธารณะ
    </a>
    <a href="{{ route('admin.legal.index') }}"
       class="bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 rounded-lg font-medium px-4 py-2 inline-flex items-center gap-1 text-sm hover:bg-indigo-100 dark:hover:bg-indigo-500/20">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
  </div>
</div>

@if($errors->any())
<div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-4">
  <ul class="mb-0 text-sm list-disc list-inside">
    @foreach($errors->all() as $error)
    <li>{{ $error }}</li>
    @endforeach
  </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.legal.update', $page) }}">
  @csrf
  @method('PUT')

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    {{-- Main editor --}}
    <div class="lg:col-span-2 space-y-4">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">ชื่อหน้า <span class="text-red-500">*</span></label>
          <input type="text" name="title"
                 class="w-full px-4 py-2.5 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 dark:text-gray-100 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                 value="{{ old('title', $page->title) }}" required>
        </div>

        <div class="mb-2 flex items-center justify-between gap-2">
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-0">เนื้อหา</label>
          <span class="text-xs text-gray-500">รองรับ HTML พื้นฐาน — heading, list, link, bold, italic</span>
        </div>
        <textarea id="content-editor" name="content" rows="24"
                  class="w-full px-4 py-3 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 dark:text-gray-100 rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('content', $page->content) }}</textarea>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">คำอธิบาย SEO (meta description)</label>
        <textarea name="meta_description" rows="2" maxlength="500"
                  class="w-full px-4 py-2.5 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 dark:text-gray-100 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="สรุปสั้นๆ เพื่อ SEO (ไม่เกิน 500 ตัวอักษร)">{{ old('meta_description', $page->meta_description) }}</textarea>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">
          <i class="bi bi-journal-text text-indigo-500"></i> บันทึกการเปลี่ยนแปลง (ไม่บังคับ)
        </label>
        <input type="text" name="change_note" maxlength="500"
               class="w-full px-4 py-2.5 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 dark:text-gray-100 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
               placeholder="เช่น: แก้ไขข้อ 5 เรื่องการเก็บข้อมูล, เพิ่มข้อ 11 เรื่อง cookies">
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
          ข้อความนี้จะถูกบันทึกในประวัติเวอร์ชันเพื่อช่วยให้ย้อนกลับไปดูการแก้ไขได้ง่ายขึ้น
        </p>
      </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-4">
      {{-- Publish settings --}}
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
        <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-3 text-sm">
          <i class="bi bi-gear text-indigo-500 mr-1"></i> การเผยแพร่
        </h6>

        <div class="mb-3">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_published" value="0">
            <input type="checkbox" name="is_published" value="1" {{ old('is_published', $page->is_published) ? 'checked' : '' }}
                   class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">เผยแพร่หน้านี้ (สาธารณะ)</span>
          </label>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-6">
            หากปิด หน้านี้จะไม่ปรากฏในเว็บสาธารณะ
          </p>
        </div>

        <div class="mb-3">
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1">วันที่มีผลบังคับใช้</label>
          <input type="date" name="effective_date"
                 class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 dark:text-gray-100 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                 value="{{ old('effective_date', optional($page->effective_date)->toDateString()) }}">
        </div>

        <div class="mb-1">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="bump_version" value="0">
            <input type="checkbox" name="bump_version" value="1"
                   class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
              เพิ่มเวอร์ชันเป็น v{{ \App\Models\LegalPage::bumpVersion($page->version) }}
            </span>
          </label>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-6">
            ติ๊กเมื่อเป็นการเปลี่ยนแปลงสำคัญที่ควรแจ้งผู้ใช้ มิฉะนั้นเก็บเวอร์ชันเดิม
          </p>
        </div>
      </div>

      {{-- Info --}}
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
        <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-3 text-sm">
          <i class="bi bi-info-circle text-indigo-500 mr-1"></i> ข้อมูลหน้า
        </h6>
        <dl class="text-xs space-y-2">
          <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">Slug (URL)</dt>
            <dd class="font-mono text-slate-700 dark:text-gray-200">/{{ $page->slug }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">เวอร์ชัน</dt>
            <dd class="font-semibold text-slate-700 dark:text-gray-200">v{{ $page->version }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">สร้างเมื่อ</dt>
            <dd class="text-slate-700 dark:text-gray-200">{{ $page->created_at?->format('d/m/Y') ?? '—' }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">แก้ไขล่าสุด</dt>
            <dd class="text-slate-700 dark:text-gray-200">{{ $page->updated_at?->diffForHumans() ?? '—' }}</dd>
          </div>
          @if($page->isCanonical())
          <div class="mt-3 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 text-xs">
            <i class="bi bi-star-fill"></i> หน้ามาตรฐานของระบบ — ไม่สามารถลบได้ (ยกเลิกเผยแพร่ได้)
          </div>
          @endif
        </dl>
      </div>

      {{-- Recent versions --}}
      @if($recentVersions->isNotEmpty())
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
        <div class="flex items-center justify-between mb-3">
          <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-0 text-sm">
            <i class="bi bi-clock-history text-indigo-500 mr-1"></i> เวอร์ชันล่าสุด
          </h6>
          <a href="{{ route('admin.legal.history', $page) }}" class="text-[0.7rem] text-indigo-500 hover:text-indigo-700">ดูทั้งหมด</a>
        </div>
        <ul class="space-y-2">
          @foreach($recentVersions as $v)
          <li class="flex items-start gap-2 text-xs">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-semibold shrink-0">
              v{{ $v->version }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="text-slate-700 dark:text-gray-200 truncate">
                {{ $v->change_note ?: 'ไม่ได้ระบุการเปลี่ยนแปลง' }}
              </div>
              <div class="text-gray-500 dark:text-gray-400 text-[0.65rem]">
                {{ $v->created_at?->diffForHumans() }}
                @if($v->admin)
                  · {{ $v->admin->full_name ?? $v->admin->email }}
                @endif
              </div>
            </div>
          </li>
          @endforeach
        </ul>
      </div>
      @endif

      {{-- Actions --}}
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 p-5">
        <button type="submit"
                class="w-full bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-semibold px-4 py-2.5 hover:from-indigo-600 hover:to-indigo-700 transition inline-flex items-center justify-center gap-2">
          <i class="bi bi-check-lg"></i> บันทึกการแก้ไข
        </button>
        @if(!$page->isCanonical())
        <button type="button" onclick="confirmDelete()"
                class="w-full mt-2 bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 rounded-lg font-semibold px-4 py-2.5 hover:bg-red-100 dark:hover:bg-red-500/20 transition inline-flex items-center justify-center gap-2 text-sm">
          <i class="bi bi-trash"></i> ลบหน้านี้
        </button>
        @endif
      </div>
    </div>
  </div>
</form>

@if(!$page->isCanonical())
<form method="POST" action="{{ route('admin.legal.destroy', $page) }}" id="deleteForm" class="hidden">
  @csrf
  @method('DELETE')
</form>
@endif
@endsection

@push('scripts')
<script>
function confirmDelete() {
  Swal.fire({
    title: 'ยืนยันการลบ?',
    text: 'หน้านี้จะถูกลบอย่างถาวรรวมถึงประวัติเวอร์ชันทั้งหมด',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'ลบ',
    cancelButtonText: 'ยกเลิก',
    confirmButtonColor: '#dc2626',
  }).then(result => {
    if (result.isConfirmed) document.getElementById('deleteForm').submit();
  });
}
</script>
@endpush
