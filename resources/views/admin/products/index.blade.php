@extends('layouts.admin')

@section('title', 'จัดการสินค้าดิจิทัล')

@section('content')
<div class="flex items-center justify-between mb-6">
  <div class="flex items-center gap-3">
    <div class="h-11 w-11 rounded-2xl bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 flex items-center justify-center">
      <i class="bi bi-box-seam text-xl"></i>
    </div>
    <div>
      <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">สินค้าดิจิทัล</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400">Preset / Template / Overlay และสินค้าดิจิทัลอื่นๆ</p>
    </div>
  </div>
  <a href="{{ route('admin.products.create') }}"
     class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white px-4 py-2 text-sm font-medium shadow-sm transition-colors">
    <i class="bi bi-plus-lg"></i>
    <span>เพิ่มสินค้า</span>
  </a>
</div>

@if(session('success'))
<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm flex items-start justify-between gap-3">
  <div class="flex items-start gap-2">
    <i class="bi bi-check-circle-fill mt-0.5"></i>
    <span>{{ session('success') }}</span>
  </div>
  <button type="button" class="text-emerald-600/80 hover:text-emerald-700 dark:text-emerald-300/80 dark:hover:text-emerald-200" onclick="this.parentElement.remove()" aria-label="Dismiss">
    <i class="bi bi-x-lg"></i>
  </button>
</div>
@endif

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.products.index') }}">
    <div class="af-grid">
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="ค้นหาชื่อหรือคำอธิบาย..." value="{{ request('q') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
          <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
          <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
        </select>
      </div>
      <div>
        <label class="af-label">ประเภท</label>
        <select name="type" class="af-input">
          <option value="">ทุกประเภท</option>
          <option value="preset" {{ request('type') === 'preset' ? 'selected' : '' }}>Preset</option>
          <option value="template" {{ request('type') === 'template' ? 'selected' : '' }}>Template</option>
          <option value="overlay" {{ request('type') === 'overlay' ? 'selected' : '' }}>Overlay</option>
          <option value="other" {{ request('type') === 'other' ? 'selected' : '' }}>Other</option>
        </select>
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
  <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-white/10">
          <tr class="text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <th class="px-5 py-3">ID</th>
            <th class="px-4 py-3">ชื่อ</th>
            <th class="px-4 py-3">ราคา</th>
            <th class="px-4 py-3">ยอดขาย</th>
            <th class="px-4 py-3">สถานะ</th>
            <th class="px-4 py-3 text-right">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
          @forelse($products as $product)
          @php
            $isActive = ($product->status ?? 'active') === 'active';
          @endphp
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 font-mono text-xs">#{{ $product->id }}</td>
            <td class="px-4 py-3">
              <span class="font-medium text-slate-900 dark:text-slate-100">{{ $product->name }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="font-semibold text-indigo-600 dark:text-indigo-300">{{ number_format($product->price, 0) }} THB</span>
              @if($product->sale_price)
              <div class="text-xs text-slate-400 dark:text-slate-500 line-through mt-0.5">{{ number_format($product->sale_price, 0) }} THB</div>
              @endif
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 px-2.5 py-1 text-xs font-medium">
                {{ $product->total_sales ?? 0 }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                @if($isActive)
                  bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300
                @else
                  bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300
                @endif">
                {{ ucfirst($product->status ?? 'active') }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1.5">
                <a href="{{ route('admin.products.show', $product->id) }}"
                   class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-300 hover:bg-sky-100 dark:hover:bg-sky-500/20 transition-colors"
                   title="ดู">
                  <i class="bi bi-eye text-sm"></i>
                </a>
                <a href="{{ route('admin.products.edit', $product->id) }}"
                   class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors"
                   title="แก้ไข">
                  <i class="bi bi-pencil text-sm"></i>
                </a>
                <form method="POST" action="{{ route('admin.products.destroy', $product->id) }}" onsubmit="return confirm('ต้องการลบสินค้านี้?')">
                  @csrf @method('DELETE')
                  <button type="submit"
                          class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors"
                          title="ลบ">
                    <i class="bi bi-trash3 text-sm"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="px-4 py-16 text-center">
              <div class="flex flex-col items-center gap-3">
                <div class="h-14 w-14 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 flex items-center justify-center">
                  <i class="bi bi-box-seam text-2xl"></i>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">ไม่พบข้อมูลสินค้า</p>
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>{{-- end #admin-table-area --}}

<div id="admin-pagination-area">
@if($products->hasPages())
<div class="flex justify-center mt-6">{{ $products->withQueryString()->links() }}</div>
@endif
</div>{{-- end #admin-pagination-area --}}
@endsection
