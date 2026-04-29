@extends('layouts.app')

@section('title', 'ไฟล์ของฉัน — คลาวด์')

@push('styles')
<style>
  .drop-zone.drag { outline: 2px dashed #6366f1; background: #eef2ff; }
</style>
@endpush

@section('content')
@php
  $plan = $summary['plan'];
  $usedPct = $summary['storage_used_pct'] ?? 0;
  $barCls  = $summary['storage_critical'] ? 'bg-rose-500'
           : ($summary['storage_warn'] ? 'bg-amber-500' : 'bg-indigo-500');
  $folderId = $folder?->id;
@endphp

<div class="max-w-7xl mx-auto py-6" x-data="fileManager({ folderId: {{ $folderId ? $folderId : 'null' }} })">
  {{-- Header --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
    <div>
      <h1 class="text-xl font-bold text-gray-900">
        <i class="bi bi-folder2-open mr-1 text-indigo-500"></i> ไฟล์ของฉัน
      </h1>
      <nav class="flex flex-wrap items-center gap-1 text-xs text-gray-500 mt-1">
        <a href="{{ route('storage.files.index') }}" class="hover:text-indigo-600">Home</a>
        @foreach($breadcrumbs as $b)
          <i class="bi bi-chevron-right text-[10px]"></i>
          <a href="{{ route('storage.files.show', ['folder' => $b->id]) }}"
             class="hover:text-indigo-600 {{ $loop->last ? 'text-gray-900 font-semibold' : '' }}">
            {{ $b->name }}
          </a>
        @endforeach
      </nav>
    </div>
    <div class="flex items-center gap-2">
      <a href="{{ route('storage.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
        <i class="bi bi-speedometer2"></i> แดชบอร์ด
      </a>
    </div>
  </div>

  {{-- Quota bar --}}
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4 flex items-center gap-4">
    <div class="flex-1">
      <div class="flex justify-between text-xs mb-1">
        <span class="text-gray-600">
          ใช้ไป <span class="font-semibold text-gray-900">{{ number_format($summary['storage_used_gb'], 2) }} GB</span>
          / {{ number_format($summary['storage_quota_gb'], 0) }} GB ({{ $plan->name }})
        </span>
        <span class="font-semibold text-gray-700">{{ $usedPct }}%</span>
      </div>
      <div class="h-2 w-full rounded-full bg-gray-100 overflow-hidden">
        <div class="h-full {{ $barCls }}" style="width: {{ min(100, $usedPct) }}%"></div>
      </div>
    </div>
    <a href="{{ route('storage.plans') }}" class="text-xs font-semibold text-indigo-600 hover:underline whitespace-nowrap">
      อัปเกรด
    </a>
  </div>

  @if(session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
      <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
    </div>
  @endif
  @if(session('error'))
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-3">
      <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
    </div>
  @endif

  {{-- Toolbar --}}
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-2">
    <button type="button" @click="$refs.fileInput.click()"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
      <i class="bi bi-upload"></i> อัปโหลด
    </button>

    <button type="button" @click="showNewFolder = true"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-sm text-gray-700 hover:bg-gray-50">
      <i class="bi bi-folder-plus"></i> สร้างโฟลเดอร์
    </button>

    <div class="flex-1"></div>
    <div class="text-xs text-gray-500">
      {{ $folders->count() }} โฟลเดอร์ · {{ $files->count() }} ไฟล์
    </div>
  </div>

  <form x-ref="uploadForm" method="POST" action="{{ route('storage.files.upload') }}" enctype="multipart/form-data" class="hidden">
    @csrf
    <input type="hidden" name="folder_id" value="{{ $folderId }}">
    <input type="file" name="file" x-ref="fileInput" @change="$refs.uploadForm.submit()">
  </form>

  {{-- New folder modal --}}
  <div x-show="showNewFolder" x-cloak x-transition.opacity
       class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
      <h3 class="font-bold text-gray-900 mb-3">สร้างโฟลเดอร์ใหม่</h3>
      <form method="POST" action="{{ route('storage.files.folders.store') }}">
        @csrf
        <input type="hidden" name="parent_id" value="{{ $folderId }}">
        <input type="text" name="name" required maxlength="120" placeholder="ชื่อโฟลเดอร์"
               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200">
        <div class="flex justify-end gap-2 mt-4">
          <button type="button" @click="showNewFolder = false"
                  class="px-3 py-1.5 rounded-lg text-sm text-gray-600 hover:bg-gray-100">ยกเลิก</button>
          <button type="submit"
                  class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
            สร้าง
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- File grid / drop zone --}}
  <div class="drop-zone bg-white rounded-xl border border-gray-200 shadow-sm p-4"
       @dragover.prevent="$event.currentTarget.classList.add('drag')"
       @dragleave.prevent="$event.currentTarget.classList.remove('drag')"
       @drop.prevent="onDrop($event)">

    {{-- Folders --}}
    @if($folders->count())
      <div class="text-xs uppercase font-semibold text-gray-500 mb-2">โฟลเดอร์</div>
      <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-5">
        @foreach($folders as $f)
          <a href="{{ route('storage.files.show', ['folder' => $f->id]) }}"
             class="group rounded-xl border border-gray-200 hover:border-indigo-300 hover:shadow-sm bg-white p-3 flex flex-col items-center text-center transition">
            <i class="bi bi-folder-fill text-4xl text-amber-400 group-hover:text-indigo-500"></i>
            <div class="text-sm font-semibold text-gray-900 mt-2 line-clamp-1 w-full">{{ $f->name }}</div>
            <div class="text-[11px] text-gray-500">{{ $f->files_count }} ไฟล์</div>
          </a>
        @endforeach
      </div>
    @endif

    {{-- Files --}}
    @if($files->count())
      <div class="text-xs uppercase font-semibold text-gray-500 mb-2">ไฟล์</div>
      <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        @foreach($files as $file)
          <div class="relative group rounded-xl border border-gray-200 hover:border-indigo-300 bg-white p-3 flex flex-col items-center text-center transition">
            <i class="bi {{ $file->icon }} text-4xl text-indigo-500"></i>
            <div class="text-sm font-semibold text-gray-900 mt-2 line-clamp-1 w-full" title="{{ $file->original_name }}">
              {{ $file->original_name }}
            </div>
            <div class="text-[11px] text-gray-500">{{ $file->human_size }}</div>

            <div class="mt-2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
              <a href="{{ route('storage.files.download', ['file' => $file->id]) }}"
                 class="p-1.5 rounded bg-gray-100 hover:bg-indigo-100 text-gray-700 hover:text-indigo-700 text-xs" title="ดาวน์โหลด">
                <i class="bi bi-download"></i>
              </a>
              <button type="button" @click="openShare({{ $file->id }})"
                      class="p-1.5 rounded bg-gray-100 hover:bg-indigo-100 text-gray-700 hover:text-indigo-700 text-xs" title="แชร์">
                <i class="bi bi-share"></i>
              </button>
              <form method="POST" action="{{ route('storage.files.destroy', ['file' => $file->id]) }}"
                    onsubmit="return confirm('ย้าย {{ $file->original_name }} ไปถังขยะ?')">
                @csrf @method('DELETE')
                <button class="p-1.5 rounded bg-gray-100 hover:bg-rose-100 text-gray-700 hover:text-rose-700 text-xs" title="ลบ">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>

            @if($file->isShareActive())
              <span class="absolute top-2 right-2 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-semibold">
                <i class="bi bi-link-45deg"></i> แชร์อยู่
              </span>
            @endif
          </div>
        @endforeach
      </div>
    @endif

    @if($folders->isEmpty() && $files->isEmpty())
      <div class="text-center py-16 text-gray-500">
        <i class="bi bi-cloud-upload text-4xl text-gray-300"></i>
        <p class="mt-3 text-sm">โฟลเดอร์นี้ว่างเปล่า — ลากไฟล์มาวางเพื่ออัปโหลด หรือกดปุ่มอัปโหลดด้านบน</p>
      </div>
    @endif
  </div>

  {{-- Share modal --}}
  <div x-show="sharingId" x-cloak x-transition.opacity
       class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
      <h3 class="font-bold text-gray-900 mb-3">สร้างลิงก์แชร์</h3>
      <form :action="shareUrl" method="POST">
        @csrf
        <label class="block text-xs text-gray-600 mb-1">รหัสผ่าน (ไม่บังคับ)</label>
        <input type="password" name="password" minlength="4" maxlength="60"
               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-indigo-200">
        <label class="block text-xs text-gray-600 mb-1">วันหมดอายุ (ไม่บังคับ)</label>
        <input type="datetime-local" name="expires_at"
               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200">
        <div class="flex justify-end gap-2 mt-4">
          <button type="button" @click="sharingId = null"
                  class="px-3 py-1.5 rounded-lg text-sm text-gray-600 hover:bg-gray-100">ยกเลิก</button>
          <button type="submit"
                  class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
            สร้างลิงก์
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
function fileManager(opts) {
  return {
    folderId: opts.folderId,
    showNewFolder: false,
    sharingId: null,
    get shareUrl() {
      return this.sharingId ? `{{ url('storage/files') }}/${this.sharingId}/share` : '#';
    },
    openShare(id) {
      this.sharingId = id;
    },
    onDrop(e) {
      e.currentTarget.classList.remove('drag');
      const files = Array.from(e.dataTransfer.files || []);
      if (!files.length) return;
      const uploadFile = (file) => {
        const fd = new FormData();
        fd.append('file', file);
        if (this.folderId) fd.append('folder_id', this.folderId);
        fd.append('_token', '{{ csrf_token() }}');
        return fetch('{{ route("storage.files.upload") }}', {
          method: 'POST',
          body: fd,
          headers: { 'Accept': 'application/json' },
        }).then(r => r.json());
      };
      Promise.all(files.map(uploadFile)).then(() => window.location.reload());
    },
  };
}
</script>
@endpush
@endsection
