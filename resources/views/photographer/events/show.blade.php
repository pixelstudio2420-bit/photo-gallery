@extends('layouts.photographer')

@section('title', 'รายละเอียดอีเวนต์')

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-calendar-event',
  'eyebrow'  => 'การทำงาน',
  'title'    => 'รายละเอียดอีเวนต์',
  'subtitle' => $event->name . ($event->shoot_date ? ' · ' . \Carbon\Carbon::parse($event->shoot_date)->format('d/m/Y') : ''),
])
<div class="flex justify-end mb-6 flex-wrap gap-3">
  <h4 class="hidden">_</h4>
  <div class="flex gap-2 flex-wrap">
    <a href="{{ route('photographer.events.photos.upload', $event) }}" class="bg-gradient-to-br from-indigo-600 to-indigo-700 text-white font-medium px-5 py-2 rounded-lg border-none inline-flex items-center gap-1 transition hover:shadow-lg">
      <i class="bi bi-cloud-upload mr-1"></i> อัพโหลดรูป
    </a>
    <a href="{{ route('photographer.events.photos.index', $event) }}" class="font-medium px-5 py-2 rounded-lg inline-flex items-center gap-1 transition" style="background:rgba(37,99,235,0.08);color:#2563eb;">
      <i class="bi bi-images mr-1"></i> จัดการรูปภาพ
    </a>
    <a href="{{ route('photographer.events.edit', $event) }}" class="font-medium px-5 py-2 rounded-lg inline-flex items-center gap-1 transition" style="background:rgba(245,158,11,0.08);color:#f59e0b;">
      <i class="bi bi-pencil mr-1"></i> แก้ไข
    </a>
    <a href="{{ route('photographer.events.index') }}" class="font-medium px-5 py-2 rounded-lg inline-flex items-center gap-1 transition" style="background:rgba(107,114,128,0.08);color:#6b7280;">
      <i class="bi bi-arrow-left mr-1"></i> กลับ
    </a>
  </div>
</div>

<div class="pg-card">
  <div class="p-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">ชื่ออีเวนต์</label>
        <p class="font-semibold mt-1 mb-3">{{ $event->name }}</p>
      </div>
      <div>
        <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">สถานะ</label>
        <p class="mt-1 mb-3">
          @php
            $statusColors = [
              'active' => ['bg' => 'rgba(16,185,129,0.1)', 'color' => '#10b981'],
              'published' => ['bg' => 'rgba(37,99,235,0.1)', 'color' => '#2563eb'],
              'draft' => ['bg' => 'rgba(107,114,128,0.1)', 'color' => '#6b7280'],
              'archived' => ['bg' => 'rgba(245,158,11,0.1)', 'color' => '#f59e0b'],
            ];
            $sc = $statusColors[$event->status] ?? ['bg' => 'rgba(107,114,128,0.1)', 'color' => '#6b7280'];
          @endphp
          <span class="inline-block text-xs font-medium px-3 py-1 rounded-full" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};">{{ ucfirst($event->status) }}</span>
        </p>
      </div>
      <div class="md:col-span-2">
        <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">รายละเอียด</label>
        <p class="mt-1 mb-3">{{ $event->description ?? '-' }}</p>
      </div>
      <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
          <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">สถานที่</label>
          <p class="font-semibold mt-1 mb-3">{{ $event->location ?? '-' }}</p>
        </div>
        <div>
          <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">วันที่ถ่าย</label>
          <p class="font-semibold mt-1 mb-3">{{ $event->shoot_date ? \Carbon\Carbon::parse($event->shoot_date)->format('d/m/Y') : '-' }}</p>
        </div>
        <div>
          <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">ราคาต่อภาพ</label>
          <p class="font-semibold mt-1 mb-3 text-indigo-600">{{ $event->price_per_photo ? number_format($event->price_per_photo, 0) . ' THB' : '-' }}</p>
        </div>
      </div>
      <div>
        <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">Google Drive Folder ID</label>
        <p class="mt-1 font-mono text-sm">{{ $event->drive_folder_id ?? '-' }}</p>
      </div>
    </div>
  </div>
</div>

<!-- Photo Gallery Section -->
@php $photoCount = $event->photos()->where('status','active')->count(); @endphp
<div class="pg-card mt-6 overflow-hidden">
  <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
    <h6 class="font-semibold"><i class="bi bi-images mr-2 text-indigo-600"></i>รูปภาพ ({{ $photoCount }})</h6>
    <a href="{{ route('photographer.events.photos.upload', $event) }}" class="bg-gradient-to-br from-indigo-600 to-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg border-none inline-flex items-center gap-1 transition hover:shadow-lg">
      <i class="bi bi-cloud-upload mr-1"></i> อัพโหลด
    </a>
  </div>
  <div class="p-4">
    @if($photoCount > 0)
      <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
        @foreach($event->photos()->where('status','active')->orderBy('sort_order')->limit(12)->get() as $photo)
        <div>
          <img src="{{ $photo->thumbnail_url }}" alt="" loading="lazy" class="w-full aspect-square object-cover rounded-lg">
        </div>
        @endforeach
      </div>
      @if($photoCount > 12)
        <div class="text-center mt-4">
          <a href="{{ route('photographer.events.photos.index', $event) }}" class="text-sm font-medium px-4 py-2 rounded-lg inline-flex items-center gap-1 transition" style="background:rgba(37,99,235,0.08);color:#2563eb;">ดูรูปทั้งหมด ({{ $photoCount }})</a>
        </div>
      @endif
    @else
      <div class="text-center py-8">
        <i class="bi bi-image text-3xl text-gray-300"></i>
        <p class="text-gray-500 mt-2">ยังไม่มีรูปภาพ — <a href="{{ route('photographer.events.photos.upload', $event) }}" class="text-indigo-600 hover:underline">อัพโหลดเลย</a></p>
      </div>
    @endif
  </div>
</div>
@endsection
