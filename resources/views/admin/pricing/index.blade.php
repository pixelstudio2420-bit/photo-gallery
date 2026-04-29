@extends('layouts.admin')

@section('title', 'จัดการราคา')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-tag mr-2" style="color:#6366f1;"></i>จัดการราคา
  </h4>
  <a href="{{ route('admin.pricing.create') }}" class="btn" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border-radius:10px;font-weight:500;padding:0.5rem 1.2rem;border:none;">
    <i class="bi bi-plus-lg mr-1"></i> เพิ่มราคา
  </a>
</div>

@if(session('success'))
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-4 text-sm " style="border-radius:10px;border:none;background:rgba(16,185,129,0.1);color:#059669;" role="alert">
  <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
  <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer" onclick="this.parentElement.remove()"></button>
</div>
@endif

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.pricing.index') }}">
    <div class="af-grid">
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="ค้นหาชื่ออีเวนต์..." value="{{ request('q') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>
      <div class="af-actions">
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>
    </div>
  </form>
</div>

<div id="admin-table-area">
<div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
  <div class="p-5 p-0">
    @if($prices->count() > 0)
    <div class="overflow-x-auto">
      <table class="table table-hover mb-0" style="font-size:0.9rem;">
        <thead>
          <tr >
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">ID</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">อีเวนต์</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">ราคาต่อรูป</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">ตั้งโดยแอดมิน</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500 text-end" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          @foreach($prices as $price)
          <tr>
            <td class="px-4 py-3 align-middle">{{ $price->event_id }}</td>
            <td class="px-4 py-3 align-middle font-medium">{{ $price->event->name ?? 'N/A' }}</td>
            <td class="px-4 py-3 align-middle">
              <span class="badge" style="background:rgba(99,102,241,0.1);color:#6366f1;font-weight:600;font-size:0.85rem;padding:0.4em 0.8em;border-radius:8px;">
                {{ number_format($price->price_per_photo, 2) }} บาท
              </span>
            </td>
            <td class="px-4 py-3 align-middle">
              @if($price->set_by_admin)
                <span class="badge" style="background:rgba(16,185,129,0.1);color:#059669;font-weight:500;padding:0.4em 0.8em;border-radius:8px;">
                  <i class="bi bi-check-circle mr-1"></i>ใช่
                </span>
              @else
                <span class="badge" style="background:rgba(148,163,184,0.15);color:#94a3b8;font-weight:500;padding:0.4em 0.8em;border-radius:8px;">
                  ไม่
                </span>
              @endif
            </td>
            <td class="px-4 py-3 align-middle text-end">
              <div class="flex gap-1 justify-end">
                <a href="{{ route('admin.pricing.edit', $price->event_id) }}" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;border:none;padding:0.3rem 0.7rem;" title="แก้ไข">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="POST" action="{{ route('admin.pricing.destroy', $price->event_id) }}" class="inline" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบราคานี้?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(239,68,68,0.08);color:#ef4444;border-radius:8px;border:none;padding:0.3rem 0.7rem;" title="ลบ">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @else
    <div class="p-5 text-center">
      <i class="bi bi-tag" style="font-size:3rem;color:#cbd5e1;"></i>
      <p class="text-gray-500 mt-3 mb-0">ยังไม่มีข้อมูลราคา</p>
    </div>
    @endif
  </div>
</div>
</div>{{-- end #admin-table-area --}}

<div id="admin-pagination-area">
@if($prices->hasPages())
<div class="flex justify-center mt-4">{{ $prices->withQueryString()->links() }}</div>
@endif
</div>{{-- end #admin-pagination-area --}}
@endsection
