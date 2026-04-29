@extends('layouts.admin')

@section('title', 'ตรวจสอบกระทบยอด')

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-shield-check mr-2" style="color:#6366f1;"></i>ตรวจสอบกระทบยอด
  </h4>
  <div class="flex gap-2">
    <a href="{{ route('admin.finance.reconciliation', array_merge(request()->query(), ['export'=>1])) }}"
      class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(16,185,129,0.08);color:#059669;border-radius:8px;font-weight:500;border:none;padding:0.4rem 1rem;">
      <i class="bi bi-download mr-1"></i> Export
    </a>
    <a href="{{ route('admin.finance.index') }}" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;font-weight:500;border:none;padding:0.4rem 1rem;">
      <i class="bi bi-arrow-left mr-1"></i> กลับ
    </a>
  </div>
</div>

{{-- Flash Messages --}}
@if(session('success'))
<div class="alert border-0 mb-4" style="background:rgba(16,185,129,0.1);color:#059669;border-radius:12px;">
  <i class="bi bi-check-circle mr-2"></i>{{ session('success') }}
</div>
@endif

{{-- Date Range Filter --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.finance.reconciliation') }}">
    <div class="af-grid">

      {{-- Date From --}}
      <div>
        <label class="af-label">จากวันที่</label>
        <input type="date" name="from" class="af-input" value="{{ $from }}">
      </div>

      {{-- Date To --}}
      <div>
        <label class="af-label">ถึงวันที่</label>
        <input type="date" name="to" class="af-input" value="{{ $to }}">
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

<div id="admin-table-area">
{{-- Summary Cards --}}
<div class="row g-3 mb-4">
  <div class="">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.08);">
            <i class="bi bi-check2-circle" style="font-size:1.3rem;color:#10b981;"></i>
          </div>
          <div>
            <div class="text-gray-500 small">รายการที่กระทบยอดแล้ว</div>
            <div class="font-bold text-lg" style="color:#059669;">{{ number_format($totalMatched) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(244,63,94,0.08);">
            <i class="bi bi-exclamation-triangle" style="font-size:1.3rem;color:#f43f5e;"></i>
          </div>
          <div>
            <div class="text-gray-500 small">ความคลาดเคลื่อน</div>
            <div class="font-bold text-lg" style="color:#f43f5e;">{{ number_format($totalDiscrepancies) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(245,158,11,0.08);">
            <i class="bi bi-currency-exchange" style="font-size:1.3rem;color:#f59e0b;"></i>
          </div>
          <div>
            <div class="text-gray-500 small">ยอดที่ยังไม่กระทบยอด</div>
            <div class="font-bold text-lg" style="color:#d97706;">฿{{ number_format($unreconciledAmount, 2) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(99,102,241,0.08);">
            <i class="bi bi-patch-check" style="font-size:1.3rem;color:#6366f1;"></i>
          </div>
          <div>
            <div class="text-gray-500 small">สลิปที่ยืนยันแล้ว</div>
            <div class="font-bold text-lg" style="color:#6366f1;">{{ number_format($totalVerified) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Discrepancies Table --}}
<div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
  <div class="px-5 py-4 border-b border-gray-100 border-0 px-4 py-3" style="background:rgba(244,63,94,0.04);border-radius:14px 14px 0 0;">
    <h6 class="font-semibold mb-0" style="color:#f43f5e;">
      <i class="bi bi-exclamation-triangle mr-2"></i>ความคลาดเคลื่อนที่พบ
      @if($totalDiscrepancies > 0)
      <span class="badge ml-1" style="background:#f43f5e;font-size:0.75rem;border-radius:8px;">{{ $totalDiscrepancies }}</span>
      @endif
    </h6>
  </div>
  <div class="p-5 p-0">
    @if($discrepancies->count() > 0)
    <div class="overflow-x-auto">
      <table class="table table-hover mb-0" style="font-size:0.88rem;">
        <thead>
          <tr >
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">คำสั่งซื้อ #</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">ธุรกรรม</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500 text-end" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">ยอดที่คาดหวัง</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500 text-end" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">ยอดจริง</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500 text-end" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">ส่วนต่าง</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">ประเภท</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          @foreach($discrepancies as $item)
          @php
            $typeColors = [
              'missing_transaction' => ['bg'=>'rgba(244,63,94,0.1)','text'=>'#f43f5e'],
              'order_not_paid'   => ['bg'=>'rgba(245,158,11,0.1)','text'=>'#d97706'],
              'amount_mismatch'   => ['bg'=>'rgba(59,130,246,0.1)','text'=>'#2563eb'],
              'orphan'       => ['bg'=>'rgba(148,163,184,0.15)','text'=>'#64748b'],
            ];
            $tc = $typeColors[$item['type']] ?? ['bg'=>'rgba(148,163,184,0.15)','text'=>'#64748b'];
          @endphp
          <tr>
            <td class="px-4 py-3 align-middle font-medium">{{ $item['order_number'] }}</td>
            <td class="px-4 py-3 align-middle">
              <code style="font-size:0.82rem;color:#64748b;">{{ $item['transaction_id'] }}</code>
            </td>
            <td class="px-4 py-3 align-middle text-end">
              {{ $item['expected_amount'] > 0 ? '฿'.number_format($item['expected_amount'], 2) : '-' }}
            </td>
            <td class="px-4 py-3 align-middle text-end">
              {{ $item['actual_amount'] > 0 ? '฿'.number_format($item['actual_amount'], 2) : '-' }}
            </td>
            <td class="px-4 py-3 align-middle text-end font-semibold" style="color:#f43f5e;">
              ฿{{ number_format($item['difference'], 2) }}
            </td>
            <td class="px-4 py-3 align-middle">
              <span class="badge" style="background:{{ $tc['bg'] }};color:{{ $tc['text'] }};font-weight:500;padding:0.35em 0.7em;border-radius:8px;">
                {{ $item['type_label'] }}
              </span>
            </td>
            <td class="px-4 py-3 align-middle">
              <a href="{{ $item['resolve_url'] }}" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:7px;font-size:0.8rem;padding:0.25rem 0.7rem;border:none;">
                <i class="bi bi-arrow-right-circle mr-1"></i>แก้ไข
              </a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @else
    <div class="p-5 text-center">
      <i class="bi bi-check2-all" style="font-size:3rem;color:#10b981;"></i>
      <p class="font-semibold mt-3 mb-1" style="color:#059669;">ไม่พบความคลาดเคลื่อน</p>
      <p class="text-gray-500 small mb-0">ข้อมูลในช่วงวันที่ที่เลือกสอดคล้องกัน</p>
    </div>
    @endif
  </div>
</div>

{{-- Matched Transactions (collapsible) --}}
<div x-data="{ expanded: false }" class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
  <div @click="expanded = !expanded"
     class="px-5 py-4 border-b border-gray-100 border-0 px-4 py-3 flex justify-between items-center"
     style="background:rgba(16,185,129,0.04);border-radius:14px 14px 0 0;cursor:pointer;">
    <h6 class="font-semibold mb-0" style="color:#059669;">
      <i class="bi bi-check2-circle mr-2"></i>รายการที่กระทบยอดแล้ว
      <span class="badge ml-1" style="background:#059669;font-size:0.75rem;border-radius:8px;">{{ $totalMatched }}</span>
    </h6>
    <i class="bi bi-chevron-down transition-transform duration-200" :class="{ 'rotate-180': expanded }" style="color:#059669;"></i>
  </div>
  <div x-show="expanded" x-collapse x-cloak
    <div class="p-5 p-0">
      @if($matched->count() > 0)
      <div class="overflow-x-auto">
        <table class="table table-hover mb-0" style="font-size:0.88rem;">
          <thead>
            <tr >
              <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">คำสั่งซื้อ #</th>
              <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">ธุรกรรม</th>
              <th class="border-0 px-4 py-3 font-semibold text-gray-500 text-end" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">ยอดเงิน</th>
              <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">ช่องทาง</th>
              <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">วันที่ยืนยัน</th>
            </tr>
          </thead>
          <tbody>
            @foreach($matched as $txn)
            <tr>
              <td class="px-4 py-3 align-middle font-medium">{{ $txn->order_number ?? '#'.$txn->order_id }}</td>
              <td class="px-4 py-3 align-middle">
                <code style="font-size:0.82rem;color:#64748b;">{{ $txn->transaction_id }}</code>
              </td>
              <td class="px-4 py-3 align-middle text-end font-semibold" style="color:#059669;">฿{{ number_format($txn->amount, 2) }}</td>
              <td class="px-4 py-3 align-middle text-gray-500">{{ $txn->payment_gateway ?? $txn->payment_method_id ?? '-' }}</td>
              <td class="px-4 py-3 align-middle text-gray-500">
                {{ $txn->paid_at ? $txn->paid_at->format('d/m/Y H:i') : $txn->created_at->format('d/m/Y H:i') }}
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @else
      <div class="p-4 text-center text-gray-500">ไม่มีรายการ</div>
      @endif
    </div>
  </div>
</div>
</div>{{-- end admin-table-area --}}
@endsection
