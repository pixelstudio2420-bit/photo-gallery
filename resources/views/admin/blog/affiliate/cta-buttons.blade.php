@extends('layouts.admin')

@section('title', 'CTA Buttons')

@section('content')
<div class="space-y-5">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-hand-index-thumb-fill text-amber-500 mr-2"></i>CTA Buttons
      </h1>
      <p class="text-sm text-gray-500 mt-1">จัดการปุ่ม Call-to-Action สำหรับ affiliate links</p>
    </div>
    <button onclick="document.getElementById('createModal').classList.remove('hidden')"
            class="px-4 py-2 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl text-sm font-medium hover:shadow-lg">
      <i class="bi bi-plus-lg"></i> สร้าง CTA
    </button>
  </div>

  {{-- CTA List --}}
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($buttons as $btn)
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-semibold uppercase text-gray-500">{{ $btn->position ?? 'inline' }}</span>
        <span class="text-xs px-2 py-0.5 {{ $btn->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700' }} rounded font-medium">
          {{ $btn->is_active ? 'เปิด' : 'ปิด' }}
        </span>
      </div>

      <h3 class="font-semibold text-slate-800 mb-1">{{ $btn->name }}</h3>
      @if($btn->affiliateLink)
        <div class="text-xs text-gray-500 mb-3">
          <i class="bi bi-link-45deg"></i> {{ $btn->affiliateLink->name }}
        </div>
      @endif

      {{-- Preview --}}
      <div class="mb-3">
        <div class="inline-block px-5 py-2.5 rounded-xl text-white font-semibold text-sm
                    {{ match($btn->style ?? 'primary') {
                      'primary'   => 'bg-gradient-to-br from-indigo-500 to-indigo-600',
                      'success'   => 'bg-gradient-to-br from-emerald-500 to-emerald-600',
                      'warning'   => 'bg-gradient-to-br from-amber-500 to-orange-500',
                      'danger'    => 'bg-gradient-to-br from-red-500 to-red-600',
                      'dark'      => 'bg-gradient-to-br from-slate-700 to-slate-900',
                      default     => 'bg-gradient-to-br from-indigo-500 to-indigo-600',
                    } }}">
          @if($btn->icon)<i class="bi bi-{{ $btn->icon }} mr-1"></i>@endif
          {{ $btn->label }}
        </div>
        @if($btn->sub_label)
        <div class="text-xs text-gray-500 mt-1">{{ $btn->sub_label }}</div>
        @endif
      </div>

      {{-- Stats --}}
      <div class="grid grid-cols-3 gap-2 text-center text-xs border-t border-gray-100 pt-3">
        <div>
          <div class="text-gray-500">Impressions</div>
          <div class="font-bold">{{ number_format($btn->impressions ?? 0) }}</div>
        </div>
        <div>
          <div class="text-gray-500">Clicks</div>
          <div class="font-bold text-indigo-600">{{ number_format($btn->clicks ?? 0) }}</div>
        </div>
        <div>
          <div class="text-gray-500">CTR</div>
          <div class="font-bold text-emerald-600">
            {{ $btn->impressions > 0 ? number_format(($btn->clicks / $btn->impressions) * 100, 1) . '%' : '-' }}
          </div>
        </div>
      </div>

      {{-- Actions --}}
      <div class="flex gap-2 mt-3 pt-3 border-t border-gray-100">
        <form method="POST" action="{{ route('admin.blog.cta.destroy', $btn->id) }}" class="ml-auto"
              onsubmit="return confirm('ลบ CTA นี้?')">
          @csrf
          @method('DELETE')
          <button type="submit" class="px-3 py-1 bg-red-50 text-red-600 rounded-lg text-xs hover:bg-red-100">
            <i class="bi bi-trash"></i> ลบ
          </button>
        </form>
      </div>
    </div>
    @empty
    <div class="col-span-full bg-white border border-gray-100 rounded-2xl p-12 text-center">
      <i class="bi bi-hand-index-thumb text-4xl text-gray-300"></i>
      <p class="text-gray-500 mt-2">ยังไม่มี CTA Buttons</p>
      <button onclick="document.getElementById('createModal').classList.remove('hidden')"
              class="mt-3 px-4 py-2 bg-indigo-500 text-white rounded-xl text-sm">
        สร้าง CTA แรก
      </button>
    </div>
    @endforelse
  </div>

  {{-- Pagination --}}
  @if($buttons->hasPages())
  <div class="flex justify-center">{{ $buttons->links() }}</div>
  @endif

  {{-- Create Modal --}}
  <div id="createModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold">
          <i class="bi bi-plus-lg text-indigo-500 mr-1"></i>สร้าง CTA Button
        </h3>
        <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-gray-400">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <form method="POST" action="{{ route('admin.blog.cta.store') }}" class="space-y-3">
        @csrf
        <div>
          <label class="block text-sm font-semibold mb-1">ชื่อ <span class="text-red-500">*</span></label>
          <input type="text" name="name" required maxlength="255" class="w-full px-3 py-2 border border-gray-200 rounded-lg">
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Label ปุ่ม <span class="text-red-500">*</span></label>
          <input type="text" name="label" required maxlength="255" placeholder="เช่น ซื้อเลย!" class="w-full px-3 py-2 border border-gray-200 rounded-lg">
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Sub Label</label>
          <input type="text" name="sub_label" maxlength="255" class="w-full px-3 py-2 border border-gray-200 rounded-lg">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold mb-1">Icon (Bootstrap)</label>
            <input type="text" name="icon" placeholder="cart" class="w-full px-3 py-2 border border-gray-200 rounded-lg">
          </div>
          <div>
            <label class="block text-sm font-semibold mb-1">สไตล์</label>
            <select name="style" class="w-full px-3 py-2 border border-gray-200 rounded-lg">
              <option value="primary">Primary (indigo)</option>
              <option value="success">Success (green)</option>
              <option value="warning">Warning (amber)</option>
              <option value="danger">Danger (red)</option>
              <option value="dark">Dark</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Affiliate Link</label>
          <select name="affiliate_link_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg">
            <option value="">-- เลือก --</option>
            @foreach($affiliateLinks as $al)
            <option value="{{ $al->id }}">{{ $al->name }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Position</label>
          <select name="position" class="w-full px-3 py-2 border border-gray-200 rounded-lg">
            <option value="inline">Inline (ในเนื้อหา)</option>
            <option value="after_content">หลังเนื้อหา</option>
            <option value="sidebar">Sidebar</option>
            <option value="floating">Floating</option>
            <option value="popup">Popup</option>
          </select>
        </div>
        <label class="flex items-center gap-2">
          <input type="checkbox" name="is_active" value="1" checked class="rounded">
          <span class="text-sm">เปิดใช้งาน</span>
        </label>
        <div class="flex gap-2 pt-3">
          <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                  class="flex-1 px-4 py-2 border border-gray-200 rounded-lg">ยกเลิก</button>
          <button type="submit" class="flex-1 px-4 py-2 bg-indigo-500 text-white rounded-lg font-medium">สร้าง</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
