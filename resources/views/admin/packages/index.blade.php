@extends('layouts.admin')

@section('title', 'จัดการแพ็คเกจ')

@section('content')
<div class="flex justify-between items-center mb-6">
  <h4 class="font-bold text-xl tracking-tight">
    <i class="bi bi-box-seam mr-2 text-indigo-500"></i>จัดการแพ็คเกจ
  </h4>
  <button type="button" onclick="openAddModal()"
    class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium text-sm px-5 py-2 transition hover:from-indigo-600 hover:to-indigo-700">
    <i class="bi bi-plus-lg mr-1"></i> เพิ่มแพ็คเกจ
  </button>
</div>

{{-- Filters --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.packages.index') }}">
    <div class="af-grid">

      {{-- Event dropdown --}}
      <div>
        <label class="af-label">อีเวนต์</label>
        <select name="event_id" class="af-input">
          <option value="">ทั้งหมด</option>
          @foreach($events as $event)
            <option value="{{ $event->id }}" {{ request('event_id') == $event->id ? 'selected' : '' }}>{{ $event->name }}</option>
          @endforeach
        </select>
      </div>

      {{-- Status dropdown --}}
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>ใช้งาน</option>
          <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>ปิดใช้งาน</option>
        </select>
      </div>

      {{-- Actions --}}
      <div class="af-actions">
        <div class="af-spinner" x-show="loading" x-cloak></div>
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>

    </div>
  </form>
</div>

@if(session('success'))
<div class="flex items-center gap-2 mb-3 px-4 py-3 rounded-xl bg-emerald-500/10 text-emerald-800">
  <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="flex items-center gap-2 mb-3 px-4 py-3 rounded-xl bg-red-500/10 text-red-800">
  <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
</div>
@endif

<div id="admin-table-area">
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-indigo-500/[0.03]">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider pl-5">ชื่อแพ็คเกจ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">จำนวนรูป</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ราคา</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">อีเวนต์</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($packages as $pkg)
        <tr class="hover:bg-gray-50/50 transition align-middle">
          <td class="pl-5 px-4 py-3">
            <div class="flex items-center gap-2">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 bg-indigo-500/[0.08]">
                <i class="bi bi-box-seam text-indigo-500"></i>
              </div>
              <div>
                <div class="font-medium">{{ $pkg->name }}</div>
                @if($pkg->description)
                  <div class="text-xs text-gray-400 truncate max-w-[200px]">{{ $pkg->description }}</div>
                @endif
              </div>
            </div>
          </td>
          <td class="px-4 py-3">
            <code class="bg-indigo-500/[0.08] text-indigo-500 px-2 py-1 rounded-md text-xs">{{ number_format($pkg->photo_count) }} รูป</code>
          </td>
          <td class="px-4 py-3 font-medium">{{ number_format($pkg->price, 2) }} ฿</td>
          <td class="px-4 py-3 text-gray-500 text-sm">{{ $pkg->event->name ?? 'ทั่วไป' }}</td>
          <td class="px-4 py-3">
            @if($pkg->is_active)
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-500">ใช้งาน</span>
            @else
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-500/10 text-gray-500">ปิดใช้งาน</span>
            @endif
          </td>
          <td class="px-4 py-3">
            <div class="flex gap-1">
              <button type="button"
                class="w-8 h-8 rounded-lg bg-indigo-500/[0.08] text-indigo-500 flex items-center justify-center transition hover:bg-indigo-500/[0.15]"
                title="แก้ไข"
                onclick="openEditModal({{ json_encode($pkg) }})">
                <i class="bi bi-pencil text-xs"></i>
              </button>
              <button type="button"
                class="w-8 h-8 rounded-lg bg-red-500/[0.08] text-red-500 flex items-center justify-center transition hover:bg-red-500/[0.15]"
                title="ลบ"
                onclick="confirmDelete({{ $pkg->id }}, '{{ addslashes($pkg->name) }}')">
                <i class="bi bi-trash3 text-xs"></i>
              </button>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6" class="text-center py-12">
            <i class="bi bi-box-seam text-4xl text-gray-300"></i>
            <p class="text-gray-500 mt-2 text-sm">ยังไม่มีแพ็คเกจ</p>
            <button type="button" class="mt-3 text-sm px-4 py-1.5 rounded-lg bg-indigo-500/10 text-indigo-500 transition hover:bg-indigo-500/[0.15]" onclick="openAddModal()">
              <i class="bi bi-plus-lg mr-1"></i> เพิ่มแพ็คเกจ
            </button>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
</div>{{-- end #admin-table-area --}}

{{-- Pagination --}}
@if($packages->hasPages())
<div id="admin-pagination-area" class="mt-4">
  {{ $packages->withQueryString()->links() }}
</div>
@endif

{{-- Add/Edit Package Modal --}}
<div x-data="{ open: false }" x-on:open-pkg-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl max-w-md w-full">
      <div class="flex items-center justify-between px-6 pt-5 pb-0">
        <h5 class="font-bold text-lg" id="pkgModalTitle">เพิ่มแพ็คเกจ</h5>
        <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 transition">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="px-6 pb-6 pt-4">
        <form id="pkgForm" method="POST">
          @csrf
          <input type="hidden" name="_method" id="pkgMethod" value="POST">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อแพ็คเกจ <span class="text-red-500">*</span></label>
              <input type="text" name="name" id="pkgName" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required placeholder="เช่น Basic, Standard, Premium" maxlength="100">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">จำนวนรูป <span class="text-red-500">*</span></label>
              <input type="number" name="photo_count" id="pkgPhotoCount" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required min="1" placeholder="10">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">ราคา (บาท) <span class="text-red-500">*</span></label>
              <input type="number" name="price" id="pkgPrice" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required min="0" step="0.01" placeholder="199.00">
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">รายละเอียด</label>
              <textarea name="description" id="pkgDescription" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="รายละเอียดแพ็คเกจ..." maxlength="500"></textarea>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">อีเวนต์</label>
              <select name="event_id" id="pkgEventId" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">-- ทั่วไป (ไม่ระบุอีเวนต์) --</option>
                @foreach($events as $event)
                  <option value="{{ $event->id }}">{{ $event->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" id="pkgIsActive" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm font-medium text-gray-700">เปิดใช้งาน</span>
              </label>
            </div>
          </div>
          <div class="flex gap-2 justify-end mt-5">
            <button type="button" @click="open = false" class="rounded-lg bg-gray-500/10 text-gray-500 font-medium px-6 py-2.5 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
            <button type="submit" class="rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-medium px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700">
              <i class="bi bi-check-lg mr-1"></i> <span id="pkgSubmitText">เพิ่มแพ็คเกจ</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Delete Confirm Modal --}}
<div x-data="{ open: false }" x-on:open-delete-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
      <div class="text-center">
        <div class="w-14 h-14 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-4">
          <i class="bi bi-trash3-fill text-2xl text-red-500"></i>
        </div>
        <h6 class="font-bold text-lg mb-2">ลบแพ็คเกจ?</h6>
        <p class="text-gray-500 text-sm mb-6" id="deleteModalText">คุณต้องการลบแพ็คเกจนี้ใช่หรือไม่?</p>
        <div class="flex gap-2 justify-center">
          <button type="button" @click="open = false" class="text-sm rounded-lg bg-gray-500/10 text-gray-500 font-medium px-5 py-2 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
          <form id="deleteForm" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm rounded-lg bg-red-500 text-white font-medium px-5 py-2 transition hover:bg-red-600">ลบ</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
function openAddModal() {
  document.getElementById('pkgModalTitle').textContent = 'เพิ่มแพ็คเกจ';
  document.getElementById('pkgSubmitText').textContent = 'เพิ่มแพ็คเกจ';
  document.getElementById('pkgForm').action = '{{ route("admin.packages.store") }}';
  document.getElementById('pkgMethod').value = 'POST';
  document.getElementById('pkgName').value = '';
  document.getElementById('pkgPhotoCount').value = '';
  document.getElementById('pkgPrice').value = '';
  document.getElementById('pkgDescription').value = '';
  document.getElementById('pkgEventId').value = '';
  document.getElementById('pkgIsActive').checked = true;
  window.dispatchEvent(new CustomEvent('open-pkg-modal'));
}

function openEditModal(pkg) {
  document.getElementById('pkgModalTitle').textContent = 'แก้ไขแพ็คเกจ';
  document.getElementById('pkgSubmitText').textContent = 'บันทึกการแก้ไข';
  document.getElementById('pkgForm').action = '/admin/packages/' + pkg.id;
  document.getElementById('pkgMethod').value = 'PUT';
  document.getElementById('pkgName').value = pkg.name || '';
  document.getElementById('pkgPhotoCount').value = pkg.photo_count || '';
  document.getElementById('pkgPrice').value = pkg.price || '';
  document.getElementById('pkgDescription').value = pkg.description || '';
  document.getElementById('pkgEventId').value = pkg.event_id || '';
  document.getElementById('pkgIsActive').checked = !!pkg.is_active;
  window.dispatchEvent(new CustomEvent('open-pkg-modal'));
}

function confirmDelete(id, name) {
  document.getElementById('deleteModalText').textContent = 'คุณต้องการลบแพ็คเกจ "' + name + '" ใช่หรือไม่?';
  document.getElementById('deleteForm').action = '/admin/packages/' + id;
  window.dispatchEvent(new CustomEvent('open-delete-modal'));
}
</script>
@endpush

@endsection
