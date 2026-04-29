@extends('layouts.admin')
@section('title', 'แผน Cloud Storage')

@section('content')
<div class="flex items-center justify-between flex-wrap gap-2 mb-4">
  <div>
    <h4 class="font-bold tracking-tight flex items-center gap-2">
      <i class="bi bi-layers text-indigo-500"></i> แผน Cloud Storage
      <span class="text-xs font-normal text-gray-400 ml-2">/ กำหนดราคาและฟีเจอร์ของแต่ละแผน</span>
    </h4>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('admin.user-storage.index') }}" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-sm">
      <i class="bi bi-arrow-left mr-1"></i> กลับ
    </a>
    <a href="{{ route('admin.user-storage.plans.create') }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
      <i class="bi bi-plus-circle mr-1"></i> สร้างแผนใหม่
    </a>
  </div>
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

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs text-gray-500">
      <tr>
        <th class="px-3 py-2 text-left w-12">ลำดับ</th>
        <th class="px-3 py-2 text-left">แผน</th>
        <th class="px-3 py-2 text-left">โค้ด</th>
        <th class="px-3 py-2 text-right">พื้นที่</th>
        <th class="px-3 py-2 text-right">ราคา/เดือน</th>
        <th class="px-3 py-2 text-right">ราคา/ปี</th>
        <th class="px-3 py-2 text-left">สถานะ</th>
        <th class="px-3 py-2 text-right">—</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
      @forelse($plans as $p)
        <tr>
          <td class="px-3 py-2 text-xs text-gray-500 font-mono">{{ $p->sort_order }}</td>
          <td class="px-3 py-2">
            <div class="flex items-center gap-2">
              <span class="inline-block w-3 h-3 rounded-sm" style="background:{{ $p->color_hex ?? '#6366f1' }};"></span>
              <div>
                <div class="font-semibold">{{ $p->name }}</div>
                @if($p->tagline)
                  <div class="text-[10px] text-gray-400">{{ $p->tagline }}</div>
                @endif
              </div>
              @if($p->badge)
                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold text-white" style="background:{{ $p->color_hex ?? '#6366f1' }};">{{ $p->badge }}</span>
              @endif
            </div>
          </td>
          <td class="px-3 py-2 font-mono text-xs">{{ $p->code }}</td>
          <td class="px-3 py-2 text-right font-semibold">{{ number_format($p->storage_gb, 0) }} GB</td>
          <td class="px-3 py-2 text-right">
            @if($p->isFree())
              <span class="text-gray-400">ฟรี</span>
            @else
              ฿{{ number_format((float) $p->price_thb, 0) }}
            @endif
          </td>
          <td class="px-3 py-2 text-right">
            @if($p->price_annual_thb)
              ฿{{ number_format((float) $p->price_annual_thb, 0) }}
            @else
              <span class="text-gray-300">—</span>
            @endif
          </td>
          <td class="px-3 py-2 text-xs">
            @if($p->is_active)
              <span class="inline-block px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-bold">ACTIVE</span>
            @else
              <span class="inline-block px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 text-[10px] font-bold">INACTIVE</span>
            @endif
            @if($p->is_public)
              <span class="inline-block px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px] font-bold ml-1">PUBLIC</span>
            @endif
            @if($p->isFree())
              <span class="inline-block px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-bold ml-1">DEFAULT</span>
            @endif
          </td>
          <td class="px-3 py-2 text-right">
            <a href="{{ route('admin.user-storage.plans.edit', $p) }}" class="text-xs text-indigo-500 hover:underline mr-2">
              <i class="bi bi-pencil-square"></i> แก้ไข
            </a>
            @if(!$p->isFree())
              <form method="POST" action="{{ route('admin.user-storage.plans.destroy', $p) }}" class="inline"
                    onsubmit="return confirm('ยืนยันปิดการขายแผน {{ $p->name }}?')">
                @csrf @method('DELETE')
                <button class="text-xs text-rose-500 hover:underline">
                  <i class="bi bi-archive"></i> ปิดการขาย
                </button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="px-3 py-10 text-center text-gray-400">ยังไม่มีแผน — กด "สร้างแผนใหม่"</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
