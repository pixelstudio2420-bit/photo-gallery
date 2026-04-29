@extends('layouts.admin')

@section('title', 'จัดการคูปอง')

@section('content')
<div class="flex flex-wrap justify-between items-center gap-3 mb-4">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-ticket-perforated mr-2 text-indigo-500"></i>จัดการคูปอง
  </h4>
  <div class="flex gap-2 flex-wrap">
    <a href="{{ route('admin.coupons.dashboard') }}" class="px-4 py-2 border border-indigo-200 text-indigo-600 rounded-lg font-medium text-sm hover:bg-indigo-50">
      <i class="bi bi-graph-up mr-1"></i>Analytics
    </a>
    <a href="{{ route('admin.coupons.export') }}" class="px-4 py-2 border border-gray-200 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-50">
      <i class="bi bi-download mr-1"></i>Export CSV
    </a>
    <a href="{{ route('admin.coupons.bulk-create') }}" class="px-4 py-2 bg-emerald-500 text-white rounded-lg font-medium text-sm hover:bg-emerald-600">
      <i class="bi bi-plus-square-dotted mr-1"></i>สร้างหลายรายการ
    </a>
    <a href="{{ route('admin.coupons.create') }}" class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-5 py-2 text-sm inline-flex items-center gap-1 transition hover:from-indigo-600 hover:to-indigo-700">
      <i class="bi bi-plus-lg mr-1"></i>เพิ่มคูปอง
    </a>
  </div>
</div>

{{-- Show bulk codes if just created --}}
@if(session('bulk_codes'))
<div class="bg-gradient-to-br from-emerald-50 to-green-50 border-2 border-emerald-200 rounded-2xl p-5 mb-4">
  <div class="flex items-start gap-3">
    <i class="bi bi-check-circle-fill text-emerald-600 text-2xl"></i>
    <div class="flex-1">
      <h3 class="font-bold text-emerald-800 mb-1">สร้างคูปอง {{ count(session('bulk_codes')) }} รายการเรียบร้อย!</h3>
      <p class="text-sm text-emerald-700 mb-3">คัดลอกรหัสด้านล่างไปใช้งาน หรือ <a href="{{ route('admin.coupons.export') }}" class="underline font-semibold">Export CSV</a></p>
      <details class="bg-white rounded-lg border border-emerald-200 p-3">
        <summary class="cursor-pointer font-semibold text-emerald-700">ดูรหัสทั้งหมด ({{ count(session('bulk_codes')) }})</summary>
        <div class="mt-3 max-h-60 overflow-y-auto grid grid-cols-2 md:grid-cols-4 gap-1 text-xs font-mono">
          @foreach(session('bulk_codes') as $code)
          <div class="bg-emerald-50 px-2 py-1 rounded text-emerald-700">{{ $code }}</div>
          @endforeach
        </div>
        <button onclick="copyCodes()" class="mt-3 px-3 py-1.5 bg-emerald-500 text-white rounded text-sm">
          <i class="bi bi-clipboard"></i> คัดลอกทั้งหมด
        </button>
      </details>
    </div>
  </div>
</div>
<script>
function copyCodes() {
  const codes = @json(session('bulk_codes'));
  navigator.clipboard.writeText(codes.join('\n'));
  alert('คัดลอกแล้ว ' + codes.length + ' รหัส!');
}
</script>
@endif

{{-- Stats Summary --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
  <div class="bg-white border border-gray-100 rounded-xl p-3">
    <div class="text-xs text-gray-500">ทั้งหมด</div>
    <div class="text-2xl font-bold">{{ number_format($stats['total']) }}</div>
  </div>
  <div class="bg-white border border-gray-100 rounded-xl p-3">
    <div class="text-xs text-gray-500">เปิดใช้งาน</div>
    <div class="text-2xl font-bold text-emerald-600">{{ number_format($stats['active']) }}</div>
  </div>
  <div class="bg-white border border-gray-100 rounded-xl p-3">
    <div class="text-xs text-gray-500">ใกล้หมดอายุ</div>
    <div class="text-2xl font-bold text-amber-600">{{ number_format($stats['expiring_soon']) }}</div>
  </div>
  <div class="bg-white border border-gray-100 rounded-xl p-3">
    <div class="text-xs text-gray-500">หมดอายุ</div>
    <div class="text-2xl font-bold text-red-600">{{ number_format($stats['expired']) }}</div>
  </div>
  <div class="bg-white border border-gray-100 rounded-xl p-3">
    <div class="text-xs text-gray-500">Redemptions</div>
    <div class="text-2xl font-bold text-indigo-600">{{ number_format($stats['total_usage']) }}</div>
  </div>
</div>

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.coupons.index') }}">
    <div class="af-grid">
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="รหัสคูปองหรือชื่อ..." value="{{ request('q') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>เปิดใช้งาน</option>
          <option value="expiring" {{ request('status') === 'expiring' ? 'selected' : '' }}>ใกล้หมดอายุ (7 วัน)</option>
          <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>หมดอายุ</option>
          <option value="exhausted" {{ request('status') === 'exhausted' ? 'selected' : '' }}>ใช้ครบแล้ว</option>
          <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>ปิดใช้งาน</option>
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
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="p-0">
    <div class="overflow-x-auto">
      <table class="w-full text-sm [&_tbody_tr]:hover:bg-gray-50">
        <thead class="bg-gray-50/80">
          <tr>
            <th class="pl-4 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">รหัสคูปอง</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ชื่อ</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ประเภท</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">มูลค่า</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ใช้แล้ว/จำกัด</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">วันหมดอายุ</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          @forelse($coupons as $coupon)
          <tr>
            <td class="pl-4 px-4 py-3 text-gray-500">{{ $coupon->id }}</td>
            <td class="px-4 py-3">
              <code class="bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-md text-xs">{{ $coupon->code }}</code>
            </td>
            <td class="px-4 py-3 font-medium">{{ $coupon->name }}</td>
            <td class="px-4 py-3">
              @if($coupon->type === 'percent')
                <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-600">เปอร์เซ็นต์</span>
              @else
                <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full bg-green-50 text-green-600">จำนวนเงิน</span>
              @endif
            </td>
            <td class="px-4 py-3 font-medium">
              {{ $coupon->type === 'percent' ? $coupon->value . '%' : number_format($coupon->value, 2) . ' ฿' }}
            </td>
            <td class="px-4 py-3 text-gray-500">
              {{ $coupon->usage_count ?? 0 }} / {{ $coupon->usage_limit ?? '∞' }}
            </td>
            <td class="px-4 py-3">
              @if($coupon->is_active)
                <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full" style="background:rgba(16,185,129,0.1);color:#10b981;">ใช้งาน</span>
              @else
                <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full" style="background:rgba(107,114,128,0.1);color:#6b7280;">ปิดใช้งาน</span>
              @endif
            </td>
            <td class="px-4 py-3 text-gray-500 text-sm">
              {{ $coupon->end_date ? \Carbon\Carbon::parse($coupon->end_date)->format('d/m/Y H:i') : '-' }}
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1">
                <a href="{{ route('admin.coupons.show', $coupon->id) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-500 hover:bg-blue-100 transition" title="ดู">
                  <i class="bi bi-eye text-xs"></i>
                </a>
                <a href="{{ route('admin.coupons.edit', $coupon->id) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-500 hover:bg-indigo-100 transition" title="แก้ไข">
                  <i class="bi bi-pencil text-xs"></i>
                </a>
                <form method="POST" action="{{ route('admin.coupons.destroy', $coupon->id) }}" onsubmit="return confirm('ต้องการลบคูปองนี้?')">
                  @csrf @method('DELETE')
                  <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition" title="ลบ">
                    <i class="bi bi-trash3 text-xs"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="9" class="text-center py-12">
              <i class="bi bi-ticket-perforated text-4xl text-gray-300"></i>
              <p class="text-gray-500 mt-2 mb-0 text-sm">ยังไม่มีคูปอง</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

</div>{{-- end #admin-table-area --}}

<div id="admin-pagination-area">
@if($coupons->hasPages())
<div class="flex justify-center mt-4">{{ $coupons->withQueryString()->links() }}</div>
@endif
</div>{{-- end #admin-pagination-area --}}
@endsection
