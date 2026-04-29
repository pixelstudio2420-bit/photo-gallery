@extends('layouts.admin')

@section('title', 'บัญชีธนาคาร')

@section('content')
<div class="flex justify-between items-center mb-6">
  <h4 class="font-bold text-xl tracking-tight">
    <i class="bi bi-bank mr-2 text-indigo-500"></i>บัญชีธนาคาร
  </h4>
  <button type="button" onclick="openAddModal()"
    class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium text-sm px-5 py-2 transition hover:from-indigo-600 hover:to-indigo-700">
    <i class="bi bi-plus-lg mr-1"></i> เพิ่มบัญชีธนาคาร
  </button>
</div>

{{-- Navigation Tabs --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-3">
  <div class="py-2 px-3">
    <div class="flex gap-1 flex-wrap">
      <a href="{{ route('admin.payments.index') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-receipt mr-1"></i> ธุรกรรม
      </a>
      <a href="{{ route('admin.payments.methods') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-wallet2 mr-1"></i> วิธีการชำระ
      </a>
      <a href="{{ route('admin.payments.slips') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-image mr-1"></i> สลิปโอน
      </a>
      <a href="{{ route('admin.payments.banks') }}" class="text-sm px-4 py-1.5 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-medium">
        <i class="bi bi-bank mr-1"></i> บัญชีธนาคาร
      </a>
      <a href="{{ route('admin.payments.payouts') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-cash-stack mr-1"></i> การจ่ายช่างภาพ
      </a>
    </div>
  </div>
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

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-indigo-500/[0.03]">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider pl-5">ธนาคาร</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">เลขบัญชี</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ชื่อบัญชี</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สาขา</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ลำดับ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($accounts as $account)
        <tr class="hover:bg-gray-50/50 transition align-middle">
          <td class="pl-5 px-4 py-3">
            <div class="flex items-center gap-2">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:{{ $account->bank_color ?? '#6366f1' }}22;">
                <i class="bi bi-bank" style="color:{{ $account->bank_color ?? '#6366f1' }};"></i>
              </div>
              <div class="font-medium">{{ $account->bank_name }}</div>
            </div>
          </td>
          <td class="px-4 py-3">
            <code class="bg-indigo-500/[0.08] text-indigo-500 px-2 py-1 rounded-md text-xs">{{ $account->account_number }}</code>
          </td>
          <td class="px-4 py-3 font-medium">{{ $account->account_holder_name }}</td>
          <td class="px-4 py-3 text-gray-500 text-sm">{{ $account->branch ?? '-' }}</td>
          <td class="px-4 py-3 text-gray-500">{{ $account->sort_order ?? 0 }}</td>
          <td class="px-4 py-3">
            @if($account->is_active)
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
                onclick="openEditModal({{ json_encode($account) }})">
                <i class="bi bi-pencil text-xs"></i>
              </button>
              <button type="button"
                class="w-8 h-8 rounded-lg bg-red-500/[0.08] text-red-500 flex items-center justify-center transition hover:bg-red-500/[0.15]"
                title="ลบ"
                onclick="confirmDelete({{ $account->id }}, '{{ $account->bank_name }} {{ $account->account_number }}')">
                <i class="bi bi-trash3 text-xs"></i>
              </button>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="text-center py-12">
            <i class="bi bi-bank text-4xl text-gray-300"></i>
            <p class="text-gray-500 mt-2 text-sm">ยังไม่มีบัญชีธนาคาร</p>
            <button type="button" class="mt-3 text-sm px-4 py-1.5 rounded-lg bg-indigo-500/10 text-indigo-500 transition hover:bg-indigo-500/[0.15]" onclick="openAddModal()">
              <i class="bi bi-plus-lg mr-1"></i> เพิ่มบัญชีธนาคาร
            </button>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Add/Edit Bank Modal --}}
<div x-data="{ open: false }" x-on:open-bank-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl max-w-md w-full">
      <div class="flex items-center justify-between px-6 pt-5 pb-0">
        <h5 class="font-bold text-lg" id="bankModalTitle">เพิ่มบัญชีธนาคาร</h5>
        <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 transition">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="px-6 pb-6 pt-4">
        <form id="bankForm" method="POST">
          @csrf
          <input type="hidden" name="_method" id="bankMethod" value="POST">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อธนาคาร <span class="text-red-500">*</span></label>
              <select name="bank_name" id="bankName" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                <option value="">-- เลือกธนาคาร --</option>
                <option value="ธนาคารกสิกรไทย">ธนาคารกสิกรไทย (KBank)</option>
                <option value="ธนาคารไทยพาณิชย์">ธนาคารไทยพาณิชย์ (SCB)</option>
                <option value="ธนาคารกรุงเทพ">ธนาคารกรุงเทพ (BBL)</option>
                <option value="ธนาคารกรุงไทย">ธนาคารกรุงไทย (KTB)</option>
                <option value="ธนาคารทหารไทยธนชาต">ธนาคารทหารไทยธนชาต (TTB)</option>
                <option value="ธนาคารกรุงศรีอยุธยา">ธนาคารกรุงศรีอยุธยา (BAY)</option>
                <option value="ธนาคารออมสิน">ธนาคารออมสิน (GSB)</option>
                <option value="อื่นๆ">อื่นๆ</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">เลขที่บัญชี <span class="text-red-500">*</span></label>
              <input type="text" name="account_number" id="bankAccountNumber" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required placeholder="xxx-x-xxxxx-x">
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อบัญชี <span class="text-red-500">*</span></label>
              <input type="text" name="account_holder_name" id="bankAccountHolder" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required placeholder="ชื่อ นามสกุล">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">สาขา</label>
              <input type="text" name="branch" id="bankBranch" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="สาขา...">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">ลำดับ</label>
              <input type="number" name="sort_order" id="bankSortOrder" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" value="0" min="0">
            </div>
            <div class="md:col-span-2">
              <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" id="bankIsActive" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm font-medium text-gray-700">เปิดใช้งาน</span>
              </label>
            </div>
          </div>
          <div class="flex gap-2 justify-end mt-5">
            <button type="button" @click="open = false" class="rounded-lg bg-gray-500/10 text-gray-500 font-medium px-6 py-2.5 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
            <button type="submit" class="rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-medium px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700">
              <i class="bi bi-check-lg mr-1"></i> <span id="bankSubmitText">เพิ่มบัญชี</span>
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
        <h6 class="font-bold text-lg mb-2">ลบบัญชีธนาคาร?</h6>
        <p class="text-gray-500 text-sm mb-6" id="deleteModalText">คุณต้องการลบบัญชีนี้ใช่หรือไม่?</p>
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
  document.getElementById('bankModalTitle').textContent = 'เพิ่มบัญชีธนาคาร';
  document.getElementById('bankSubmitText').textContent = 'เพิ่มบัญชี';
  document.getElementById('bankForm').action = '/admin/payments/banks';
  document.getElementById('bankMethod').value = 'POST';
  document.getElementById('bankName').value = '';
  document.getElementById('bankAccountNumber').value = '';
  document.getElementById('bankAccountHolder').value = '';
  document.getElementById('bankBranch').value = '';
  document.getElementById('bankSortOrder').value = '0';
  document.getElementById('bankIsActive').checked = true;
  window.dispatchEvent(new CustomEvent('open-bank-modal'));
}

function openEditModal(account) {
  document.getElementById('bankModalTitle').textContent = 'แก้ไขบัญชีธนาคาร';
  document.getElementById('bankSubmitText').textContent = 'บันทึกการแก้ไข';
  document.getElementById('bankForm').action = '/admin/payments/banks/' + account.id;
  document.getElementById('bankMethod').value = 'PUT';
  document.getElementById('bankName').value = account.bank_name || '';
  document.getElementById('bankAccountNumber').value = account.account_number || '';
  document.getElementById('bankAccountHolder').value = account.account_holder_name || '';
  document.getElementById('bankBranch').value = account.branch || '';
  document.getElementById('bankSortOrder').value = account.sort_order || 0;
  document.getElementById('bankIsActive').checked = !!account.is_active;
  window.dispatchEvent(new CustomEvent('open-bank-modal'));
}

function confirmDelete(id, name) {
  document.getElementById('deleteModalText').textContent = name;
  document.getElementById('deleteForm').action = '/admin/payments/banks/' + id;
  window.dispatchEvent(new CustomEvent('open-delete-modal'));
}
</script>
@endpush

@endsection
