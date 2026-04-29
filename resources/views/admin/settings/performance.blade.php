@extends('layouts.admin')

@section('title', 'ประสิทธิภาพ')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-speedometer2 mr-2" style="color:#6366f1;"></i>จัดการประสิทธิภาพ
  </h4>
  <a href="{{ route('admin.settings.index') }}" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;font-weight:500;border:none;padding:0.4rem 1rem;">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

{{-- ============================== --}}
{{-- Cache Management        --}}
{{-- ============================== --}}
<div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
  <div class="p-5 p-4">
    <h6 class="font-semibold mb-3"><i class="bi bi-trash3 mr-1" style="color:#ef4444;"></i> จัดการ Cache</h6>
    <p class="text-gray-500 mb-3" style="font-size:0.85rem;">ล้าง cache เพื่อให้ระบบอัปเดตข้อมูลใหม่ ใช้เมื่อพบปัญหาข้อมูลไม่อัปเดต หรือเว็บทำงานช้า</p>
    <div class="flex flex-wrap gap-2">
      <form method="POST" action="{{ route('admin.settings.performance.clear-cache') }}" class="inline" onsubmit="return confirm('ล้าง Cache ทั้งหมด? (อาจต้อง login ใหม่)')">
        @csrf
        <input type="hidden" name="type" value="all">
        <button type="submit" class="text-sm px-3 py-1.5 rounded-lg font-medium" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border-radius:8px;border:none;padding:0.45rem 1rem;">
          <i class="bi bi-arrow-repeat mr-1"></i> ล้าง Cache ทั้งหมด
        </button>
      </form>
      <form method="POST" action="{{ route('admin.settings.performance.clear-cache') }}" class="inline">
        @csrf
        <input type="hidden" name="type" value="views">
        <button type="submit" class="text-sm px-3 py-1.5 rounded-lg font-medium" style="background:rgba(59,130,246,0.1);color:#3b82f6;border-radius:8px;border:none;padding:0.45rem 1rem;">
          <i class="bi bi-eye mr-1"></i> View Cache
        </button>
      </form>
      <form method="POST" action="{{ route('admin.settings.performance.clear-cache') }}" class="inline">
        @csrf
        <input type="hidden" name="type" value="drive">
        <button type="submit" class="text-sm px-3 py-1.5 rounded-lg font-medium" style="background:rgba(16,185,129,0.1);color:#10b981;border-radius:8px;border:none;padding:0.45rem 1rem;">
          <i class="bi bi-cloud mr-1"></i> Drive Cache
        </button>
      </form>
      <form method="POST" action="{{ route('admin.settings.performance.clear-cache') }}" class="inline">
        @csrf
        <input type="hidden" name="type" value="app">
        <button type="submit" class="text-sm px-3 py-1.5 rounded-lg font-medium" style="background:rgba(245,158,11,0.1);color:#f59e0b;border-radius:8px;border:none;padding:0.45rem 1rem;">
          <i class="bi bi-database mr-1"></i> Settings Cache
        </button>
      </form>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
  {{-- System Info --}}
  <div class="">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <h6 class="font-semibold mb-3"><i class="bi bi-database mr-1" style="color:#6366f1;"></i> Cache</h6>
        <table class="table table-sm mb-0" style="font-size:0.85rem;">
          <tr><td class="text-gray-500 border-0">Driver</td><td class="border-0 font-medium">{{ $cacheStats['driver'] ?? 'file' }}</td></tr>
          @if(isset($cacheStats['hits']))
          <tr><td class="text-gray-500">Hits</td><td class="font-medium text-green-600">{{ number_format($cacheStats['hits']) }}</td></tr>
          <tr><td class="text-gray-500">Misses</td><td class="font-medium text-red-600">{{ number_format($cacheStats['misses']) }}</td></tr>
          @endif
          @if(isset($cacheStats['error']))
          <tr><td class="text-gray-500">Error</td><td class="text-red-600 small">{{ $cacheStats['error'] }}</td></tr>
          @endif
          <tr><td class="text-gray-500">PHP Version</td><td class="font-medium">{{ phpversion() }}</td></tr>
          <tr><td class="text-gray-500">Laravel</td><td class="font-medium">{{ app()->version() }}</td></tr>
        </table>
      </div>
    </div>
  </div>

  {{-- Session Stats --}}
  <div class="">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <h6 class="font-semibold mb-3"><i class="bi bi-person-badge mr-1" style="color:#6366f1;"></i> Sessions</h6>
        <table class="table table-sm mb-0" style="font-size:0.85rem;">
          <tr><td class="text-gray-500 border-0">Driver</td><td class="border-0 font-medium">{{ $sessionStats['driver'] ?? 'file' }}</td></tr>
          @if(isset($sessionStats['active']))
          <tr><td class="text-gray-500">Active Sessions</td><td class="font-medium">{{ $sessionStats['active'] }}</td></tr>
          @endif
          <tr><td class="text-gray-500">Lifetime</td><td class="font-medium">{{ config('session.lifetime') }} นาที</td></tr>
        </table>
      </div>
    </div>
  </div>

  {{-- Sync Queue --}}
  <div class="">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <h6 class="font-semibold mb-3"><i class="bi bi-arrow-repeat mr-1" style="color:#6366f1;"></i> Sync Queue</h6>
        <div class="flex gap-3 flex-wrap">
          <div class="text-center">
            <div class="text-xl font-bold" style="color:#f59e0b;">{{ $syncQueue['pending'] ?? 0 }}</div>
            <small class="text-gray-500">Pending</small>
          </div>
          <div class="text-center">
            <div class="text-xl font-bold" style="color:#6366f1;">{{ $syncQueue['running'] ?? 0 }}</div>
            <small class="text-gray-500">Running</small>
          </div>
          <div class="text-center">
            <div class="text-xl font-bold" style="color:#10b981;">{{ $syncQueue['completed'] ?? 0 }}</div>
            <small class="text-gray-500">Completed</small>
          </div>
          <div class="text-center">
            <div class="text-xl font-bold" style="color:#ef4444;">{{ $syncQueue['failed'] ?? 0 }}</div>
            <small class="text-gray-500">Failed</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Existing Settings --}}
  <div class="">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <h6 class="font-semibold mb-3"><i class="bi bi-sliders mr-1" style="color:#6366f1;"></i> ค่าระบบปัจจุบัน</h6>
        @if(count($settings) > 0)
        <table class="table table-sm mb-0" style="font-size:0.85rem;">
          @foreach($settings as $key => $value)
          <tr>
            <td class="text-gray-500 {{ $loop->first ? 'border-0' : '' }}">{{ $key }}</td>
            <td class="font-medium {{ $loop->first ? 'border-0' : '' }}">{{ $value }}</td>
          </tr>
          @endforeach
        </table>
        @else
        <p class="text-gray-500 mb-0 small">ไม่มีการตั้งค่าเฉพาะ</p>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ============================== --}}
{{-- Performance Tuning Settings   --}}
{{-- ============================== --}}
<div class="card border-0 mt-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
  <div class="p-5 p-4">
    <h6 class="font-semibold mb-3"><i class="bi bi-gear mr-1" style="color:#6366f1;"></i> ตั้งค่าประสิทธิภาพ</h6>
    <form method="POST" action="{{ route('admin.settings.performance.update') }}">
      @csrf
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {{-- Lazy Loading --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 small font-medium text-gray-500">Lazy Loading รูปภาพ</label>
          <select name="perf_lazy_loading" class="form-select form-select-sm" style="border-radius:8px;">
            <option value="1" {{ ($perfSettings['perf_lazy_loading'] ?? '1') === '1' ? 'selected' : '' }}>เปิด (แนะนำ)</option>
            <option value="0" {{ ($perfSettings['perf_lazy_loading'] ?? '1') === '0' ? 'selected' : '' }}>ปิด</option>
          </select>
          <small class="text-gray-500">โหลดรูปเฉพาะที่เลื่อนมาถึง ช่วยเพิ่มความเร็ว</small>
        </div>

        {{-- Image Quality --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 small font-medium text-gray-500">คุณภาพ Thumbnail (%)</label>
          <input type="number" name="perf_image_quality" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500-sm" style="border-radius:8px;"
              value="{{ $perfSettings['perf_image_quality'] ?? 80 }}" min="30" max="100">
          <small class="text-gray-500">ค่าต่ำ = โหลดเร็วขึ้น ค่าสูง = รูปคมขึ้น (แนะนำ 70-85)</small>
        </div>

        {{-- Cache TTL --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 small font-medium text-gray-500">Cache TTL (นาที)</label>
          <input type="number" name="perf_cache_ttl_minutes" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500-sm" style="border-radius:8px;"
              value="{{ $perfSettings['perf_cache_ttl_minutes'] ?? 60 }}" min="5" max="1440">
          <small class="text-gray-500">ระยะเวลาเก็บ cache รูปภาพ (แนะนำ 60-360 นาที)</small>
        </div>

        {{-- Cache Grace --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 small font-medium text-gray-500">Cache Grace Period (ชั่วโมง)</label>
          <input type="number" name="perf_cache_grace_hours" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500-sm" style="border-radius:8px;"
              value="{{ $perfSettings['perf_cache_grace_hours'] ?? 24 }}" min="1" max="168">
          <small class="text-gray-500">ใช้ cache เก่าได้อีกกี่ชม.ขณะอัปเดต (แนะนำ 24)</small>
        </div>

        {{-- Gallery Page Size --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 small font-medium text-gray-500">จำนวนรูปต่อหน้า (Gallery)</label>
          <input type="number" name="perf_gallery_page_size" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500-sm" style="border-radius:8px;"
              value="{{ $perfSettings['perf_gallery_page_size'] ?? 50 }}" min="10" max="200">
          <small class="text-gray-500">จำนวนน้อย = โหลดเร็วขึ้น (แนะนำ 30-60)</small>
        </div>

        {{-- Minify HTML --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 small font-medium text-gray-500">Minify HTML</label>
          <select name="perf_minify_html" class="form-select form-select-sm" style="border-radius:8px;">
            <option value="1" {{ ($perfSettings['perf_minify_html'] ?? '0') === '1' ? 'selected' : '' }}>เปิด</option>
            <option value="0" {{ ($perfSettings['perf_minify_html'] ?? '0') === '0' ? 'selected' : '' }}>ปิด</option>
          </select>
          <small class="text-gray-500">ลดขนาด HTML ที่ส่งให้ผู้ใช้</small>
        </div>
      </div>

      <div class="mt-4">
        <button type="submit" class="text-sm px-3 py-1.5 rounded-lg font-medium" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border-radius:8px;border:none;padding:0.5rem 1.5rem;">
          <i class="bi bi-check-lg mr-1"></i> บันทึกการตั้งค่า
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Performance Tips --}}
<div class="card border-0 mt-4 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);background:rgba(99,102,241,0.03);">
  <div class="p-5 p-4">
    <h6 class="font-semibold mb-3"><i class="bi bi-lightbulb mr-1" style="color:#f59e0b;"></i> คำแนะนำเพิ่มความเร็ว</h6>
    <div class="row g-2" style="font-size:0.82rem;">
      <div class="">
        <div class="flex gap-2 items-start mb-2">
          <i class="bi bi-check-circle-fill text-green-600 mt-1" style="font-size:0.7rem;"></i>
          <span>เปิด Lazy Loading เพื่อลดการโหลดรูปภาพทั้งหมดพร้อมกัน</span>
        </div>
        <div class="flex gap-2 items-start mb-2">
          <i class="bi bi-check-circle-fill text-green-600 mt-1" style="font-size:0.7rem;"></i>
          <span>ตั้ง Cache TTL สูงขึ้น (360 นาที) ถ้าไม่ได้เปลี่ยนรูปบ่อย</span>
        </div>
        <div class="flex gap-2 items-start mb-2">
          <i class="bi bi-check-circle-fill text-green-600 mt-1" style="font-size:0.7rem;"></i>
          <span>ลดจำนวนรูปต่อหน้าเป็น 30-40 เพื่อโหลดเร็วขึ้น</span>
        </div>
      </div>
      <div class="">
        <div class="flex gap-2 items-start mb-2">
          <i class="bi bi-check-circle-fill text-green-600 mt-1" style="font-size:0.7rem;"></i>
          <span>ล้าง Cache เมื่อพบปัญหาข้อมูลไม่อัปเดต</span>
        </div>
        <div class="flex gap-2 items-start mb-2">
          <i class="bi bi-check-circle-fill text-green-600 mt-1" style="font-size:0.7rem;"></i>
          <span>ตั้ง Image Quality 70-80% เพื่อลดขนาดไฟล์</span>
        </div>
        <div class="flex gap-2 items-start mb-2">
          <i class="bi bi-check-circle-fill text-green-600 mt-1" style="font-size:0.7rem;"></i>
          <span>เปิด Minify HTML เพื่อลดขนาดหน้าเว็บ</span>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
