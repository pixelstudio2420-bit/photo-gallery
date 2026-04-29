@extends('layouts.admin')

@section('title', 'รายละเอียดสินค้า')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-box-seam mr-2" style="color:#6366f1;"></i>รายละเอียดสินค้า
  </h4>
  <div class="flex gap-2">
    <a href="{{ route('admin.products.edit', $product->id) }}" class="btn" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:10px;font-weight:500;padding:0.5rem 1.2rem;border:none;">
      <i class="bi bi-pencil mr-1"></i> แก้ไข
    </a>
    <a href="{{ route('admin.products.index') }}" class="btn" style="background:rgba(107,114,128,0.08);color:#6b7280;border-radius:10px;font-weight:500;padding:0.5rem 1.2rem;border:none;">
      <i class="bi bi-arrow-left mr-1"></i> กลับ
    </a>
  </div>
</div>

<div class="row g-4">
  <div class="lg:col-span-2">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <div class="flex items-center justify-between mb-4">
          <h5 class="font-bold mb-0">{{ $product->name }}</h5>
          @php $isActive = ($product->status ?? 'active') === 'active'; @endphp
          <span class="badge" style="background:{{ $isActive ? 'rgba(16,185,129,0.1)' : 'rgba(107,114,128,0.1)' }};color:{{ $isActive ? '#10b981' : '#6b7280' }};border-radius:50px;padding:0.4rem 0.9rem;font-size:0.75rem;">
            {{ ucfirst($product->status ?? 'active') }}
          </span>
        </div>

        @if($product->description)
        <div class="mb-4">
          <label class="text-gray-500 small block mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">รายละเอียด</label>
          <p class="mb-0">{{ $product->description }}</p>
        </div>
        @endif

        <div class="row g-3">
          <div class="">
            <label class="text-gray-500 small block mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">ราคา</label>
            <p class="font-bold mb-0" style="font-size:1.3rem;color:#6366f1;">{{ number_format($product->price, 0) }} THB</p>
          </div>
          <div class="">
            <label class="text-gray-500 small block mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">ราคาลด</label>
            <p class="mb-0" style="font-size:1.3rem;">{{ $product->sale_price ? number_format($product->sale_price, 0) . ' THB' : '-' }}</p>
          </div>
          <div class="">
            <label class="text-gray-500 small block mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">ประเภทสินค้า</label>
            <p class="mb-0">{{ $product->product_type ?? '-' }}</p>
          </div>
          <div class="">
            <label class="text-gray-500 small block mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Slug</label>
            <p class="mb-0 text-gray-500">{{ $product->slug ?? '-' }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <h6 class="font-semibold mb-3"><i class="bi bi-bar-chart mr-1"></i> สถิติ</h6>
        <div class="mb-3">
          <label class="text-gray-500 small block mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">ยอดขาย</label>
          <span class="font-bold" style="font-size:1.5rem;color:#6366f1;">{{ number_format($product->total_sales ?? 0) }}</span>
        </div>
        <div class="mb-0">
          <label class="text-gray-500 small block mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">รายได้รวม</label>
          <span class="font-bold" style="font-size:1.5rem;color:#10b981;">{{ number_format($product->total_revenue ?? 0) }} THB</span>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
