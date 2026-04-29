@extends('layouts.admin')

@section('title', 'แก้ไขราคา')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-tag mr-2" style="color:#6366f1;"></i>แก้ไขราคา
  </h4>
  <a href="{{ route('admin.pricing.index') }}" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;font-weight:500;border:none;padding:0.4rem 1rem;">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

<div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
  <div class="p-5 p-4">
    <form method="POST" action="{{ route('admin.pricing.update', $price->event_id) }}">
      @csrf
      @method('PUT')
      <input type="hidden" name="event_id" value="{{ $price->event_id }}">
      <div class="mb-3">
        <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">อีเวนต์</label>
        <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;border-color:#e2e8f0;background:#f8fafc;" value="{{ $price->event->name ?? 'N/A' }}" readonly>
      </div>
      <div class="mb-3">
        <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">ราคาต่อรูป (บาท)</label>
        <input type="number" name="price_per_photo" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" placeholder="0.00" step="0.01" min="0" value="{{ old('price_per_photo', $price->price_per_photo) }}">
        @error('price_per_photo') <div class="text-red-600 small mt-1">{{ $message }}</div> @enderror
      </div>
      <div class="flex gap-2">
        <button type="submit" class="btn" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border-radius:10px;font-weight:500;padding:0.5rem 1.5rem;border:none;">
          <i class="bi bi-check-lg mr-1"></i> อัปเดต
        </button>
        <a href="{{ route('admin.pricing.index') }}" class="btn" style="background:#f1f5f9;color:#64748b;border-radius:10px;font-weight:500;padding:0.5rem 1.5rem;border:none;">
          ยกเลิก
        </a>
      </div>
    </form>
  </div>
</div>
@endsection
