@extends('layouts.admin')
@section('title', 'ไฟล์ผู้ใช้ทั้งหมด — Cloud Storage')

@php
  function fmtBytes4($bytes, $precision = 2) {
      if ($bytes <= 0) return '0 B';
      $units = ['B', 'KB', 'MB', 'GB', 'TB'];
      $pow = min(floor(log($bytes, 1024)), count($units) - 1);
      return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
  }
@endphp

@section('content')
<div class="flex items-center justify-between flex-wrap gap-2 mb-4">
  <div>
    <h4 class="font-bold tracking-tight flex items-center gap-2">
      <i class="bi bi-folder2-open text-indigo-500"></i> ไฟล์ผู้ใช้ทั้งหมด
      <span class="text-xs font-normal text-gray-400 ml-2">/ ตรวจสอบและจัดการไฟล์ที่ผู้ใช้อัปโหลด</span>
    </h4>
  </div>
  <a href="{{ route('admin.user-storage.index') }}" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-sm">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-2.5">
    <i class="bi bi-check-circle-fill mr-1"></i>{{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-2.5">
    <i class="bi bi-exclamation-triangle-fill mr-1"></i>{{ session('error') }}
  </div>
@endif

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
  <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
    <div class="text-xs text-gray-500">ไฟล์ทั้งหมด (active)</div>
    <div class="text-2xl font-bold text-indigo-500">{{ number_format($stats['total_active']) }}</div>
  </div>
  <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
    <div class="text-xs text-gray-500">ขนาดรวม</div>
    <div class="text-2xl font-bold">{{ fmtBytes4($stats['total_bytes']) }}</div>
  </div>
  <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
    <div class="text-xs text-gray-500">มีลิงก์แชร์</div>
    <div class="text-2xl font-bold text-emerald-500">{{ number_format($stats['total_shared']) }}</div>
  </div>
  <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
    <div class="text-xs text-gray-500">อยู่ในถังขยะ</div>
    <div class="text-2xl font-bold text-gray-500">{{ number_format($stats['total_trashed']) }}</div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">
  {{-- Top extensions --}}
  <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-4">
    <h5 class="font-semibold mb-3 text-sm">นามสกุลยอดนิยม</h5>
    <ul class="space-y-1.5">
      @forelse($topExt as $row)
        <li class="flex items-center justify-between text-xs">
          <span class="font-mono text-gray-700 dark:text-white/80">.{{ $row->ext ?: '(no-ext)' }}</span>
          <span class="text-right">
            <span class="font-semibold">{{ number_format($row->cnt) }}</span>
            <span class="text-gray-400 ml-1">({{ fmtBytes4($row->bytes, 1) }})</span>
          </span>
        </li>
      @empty
        <li class="text-xs text-gray-400">ยังไม่มีไฟล์</li>
      @endforelse
    </ul>
  </div>

  {{-- Filters --}}
  <form method="GET" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-4 lg:col-span-3">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <div>
        <label class="text-xs text-gray-500 mb-1 block">ค้นหา (ชื่อไฟล์ / ผู้ใช้)</label>
        <input type="text" name="q" value="{{ $search }}"
               class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
      </div>
      <div>
        <label class="text-xs text-gray-500 mb-1 block">ประเภท MIME</label>
        <select name="type" class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
          <option value="">ทั้งหมด</option>
          <option value="image/" {{ $type === 'image/' ? 'selected' : '' }}>รูปภาพ</option>
          <option value="video/" {{ $type === 'video/' ? 'selected' : '' }}>วิดีโอ</option>
          <option value="audio/" {{ $type === 'audio/' ? 'selected' : '' }}>เสียง</option>
          <option value="application/pdf" {{ $type === 'application/pdf' ? 'selected' : '' }}>PDF</option>
          <option value="application/" {{ $type === 'application/' ? 'selected' : '' }}>เอกสาร / อื่นๆ</option>
        </select>
      </div>
      <div class="flex items-end gap-2">
        <label class="flex items-center gap-1 text-xs">
          <input type="checkbox" name="trashed" value="1" {{ $trashed ? 'checked' : '' }}>
          ถังขยะเท่านั้น
        </label>
        <label class="flex items-center gap-1 text-xs">
          <input type="checkbox" name="shared" value="1" {{ $shared ? 'checked' : '' }}>
          ที่แชร์แล้ว
        </label>
      </div>
      <div class="md:col-span-3 flex justify-end gap-2">
        <a href="{{ route('admin.user-storage.files.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm">
          ล้าง
        </a>
        <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
          <i class="bi bi-filter mr-1"></i> กรอง
        </button>
      </div>
    </div>
  </form>
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs text-gray-500">
        <tr>
          <th class="px-3 py-2 text-left">ไฟล์</th>
          <th class="px-3 py-2 text-left">เจ้าของ</th>
          <th class="px-3 py-2 text-right">ขนาด</th>
          <th class="px-3 py-2 text-left">MIME</th>
          <th class="px-3 py-2 text-left">สถานะ</th>
          <th class="px-3 py-2 text-left">อัปโหลด</th>
          <th class="px-3 py-2 text-right">—</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/5">
        @forelse($files as $f)
          <tr>
            <td class="px-3 py-2">
              <div class="flex items-center gap-2">
                <i class="bi {{ $f->icon }} text-indigo-400 text-lg"></i>
                <div>
                  <div class="font-medium truncate max-w-[240px]" title="{{ $f->original_name }}">{{ $f->original_name }}</div>
                  <div class="text-[10px] text-gray-400 font-mono">#{{ $f->id }}</div>
                </div>
              </div>
            </td>
            <td class="px-3 py-2">
              @if($f->user)
                <a href="{{ route('admin.user-storage.subscribers.show', $f->user_id) }}" class="text-indigo-500 hover:underline text-xs">
                  {{ trim(($f->user->first_name ?? '') . ' ' . ($f->user->last_name ?? '')) ?: $f->user->email }}
                </a>
                <div class="text-[10px] text-gray-400">{{ $f->user->email }}</div>
              @else
                <span class="text-gray-400 text-xs">#{{ $f->user_id }}</span>
              @endif
            </td>
            <td class="px-3 py-2 text-right text-xs font-mono">{{ $f->human_size }}</td>
            <td class="px-3 py-2 text-xs text-gray-500 font-mono">{{ $f->mime_type ?: '—' }}</td>
            <td class="px-3 py-2 text-xs">
              @if($f->trashed())
                <span class="inline-block px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 text-[10px] font-bold">TRASHED</span>
              @else
                <span class="inline-block px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-bold">ACTIVE</span>
              @endif
              @if($f->share_token)
                <span class="inline-block px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px] font-bold ml-1">SHARED</span>
              @endif
            </td>
            <td class="px-3 py-2 text-xs text-gray-500">{{ $f->created_at?->format('d M H:i') }}</td>
            <td class="px-3 py-2 text-right">
              @if(!$f->trashed())
                <a href="{{ route('admin.user-storage.files.download', $f) }}" target="_blank" class="text-xs text-indigo-500 hover:underline mr-2">
                  <i class="bi bi-download"></i>
                </a>
                @if($f->share_token)
                  <form method="POST" action="{{ route('admin.user-storage.files.unshare', $f) }}" class="inline"
                        onsubmit="return confirm('ยกเลิกลิงก์แชร์?')">
                    @csrf
                    <button class="text-xs text-amber-500 hover:underline mr-2" title="ยกเลิกแชร์">
                      <i class="bi bi-link-45deg"></i>
                    </button>
                  </form>
                @endif
                <form method="POST" action="{{ route('admin.user-storage.files.takedown', $f) }}" class="inline"
                      onsubmit="return confirm('Take down ไฟล์นี้? (จะย้ายเข้าถังขยะ)')">
                  @csrf @method('DELETE')
                  <button class="text-xs text-rose-500 hover:underline">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              @else
                <form method="POST" action="{{ route('admin.user-storage.files.purge', $f->id) }}" class="inline"
                      onsubmit="return confirm('ลบถาวร? การกระทำนี้ย้อนกลับไม่ได้')">
                  @csrf @method('DELETE')
                  <button class="text-xs text-rose-600 hover:underline font-bold">
                    <i class="bi bi-fire"></i> PURGE
                  </button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="px-3 py-10 text-center text-gray-400">ไม่พบไฟล์</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-4">
  {{ $files->links() }}
</div>
@endsection
