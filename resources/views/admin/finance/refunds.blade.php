@extends('layouts.admin')

@section('title', 'คำขอคืนเงิน')

@section('content')
<div x-data="{ showCreateModal: false, showProcessModal: false, processRefundId: '', processOrder: '', processAmount: '' }">
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-arrow-return-left mr-2" style="color:#6366f1;"></i>คำขอคืนเงิน
  </h4>
  <div class="flex gap-2">
    <button type="button" class="text-sm px-3 py-1.5 rounded-lg" style="background:#6366f1;color:#fff;border-radius:8px;font-weight:500;border:none;padding:0.4rem 1rem;"
        @click="showCreateModal = true">
      <i class="bi bi-plus-circle mr-1"></i> สร้างคำขอคืนเงิน
    </button>
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
@if(session('error'))
<div class="alert border-0 mb-4" style="background:rgba(244,63,94,0.08);color:#f43f5e;border-radius:12px;">
  <i class="bi bi-exclamation-circle mr-2"></i>{{ session('error') }}
</div>
@endif

{{-- Stats --}}
<div class="row g-3 mb-4">
  <div class="">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(99,102,241,0.08);">
            <i class="bi bi-list-ul" style="font-size:1.3rem;color:#6366f1;"></i>
          </div>
          <div>
            <div class="text-gray-500 small">คำขอทั้งหมด</div>
            <div class="font-bold text-lg" style="color:#1e293b;">{{ number_format($stats['total']) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(245,158,11,0.08);">
            <i class="bi bi-hourglass-split" style="font-size:1.3rem;color:#f59e0b;"></i>
          </div>
          <div>
            <div class="text-gray-500 small">รอดำเนินการ</div>
            <div class="font-bold text-lg" style="color:#d97706;">{{ number_format($stats['pending']) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.08);">
            <i class="bi bi-check-circle" style="font-size:1.3rem;color:#10b981;"></i>
          </div>
          <div>
            <div class="text-gray-500 small">อนุมัติแล้ว</div>
            <div class="font-bold text-lg" style="color:#059669;">{{ number_format($stats['approved']) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(244,63,94,0.08);">
            <i class="bi bi-cash-coin" style="font-size:1.3rem;color:#f43f5e;"></i>
          </div>
          <div>
            <div class="text-gray-500 small">ยอดคืนเงินรวม</div>
            <div class="font-bold text-lg" style="color:#f43f5e;">฿{{ number_format($stats['total_amount'], 2) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Filter --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.finance.refunds') }}">
    <div class="af-grid">

      {{-- Status --}}
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="pending"   {{ $status === 'pending'   ? 'selected' : '' }}>รอดำเนินการ</option>
          <option value="approved"  {{ $status === 'approved'  ? 'selected' : '' }}>อนุมัติแล้ว</option>
          <option value="rejected"  {{ $status === 'rejected'  ? 'selected' : '' }}>ปฏิเสธ</option>
          <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>เสร็จสิ้น</option>
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

{{-- Refunds Table --}}
<div id="admin-table-area">
<div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
  <div class="p-5 p-0">
    @if($refunds->count() > 0)
    <div class="overflow-x-auto">
      <table class="table table-hover mb-0" style="font-size:0.9rem;">
        <thead>
          <tr >
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">ID</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">คำสั่งซื้อ #</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">ลูกค้า</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500 text-end" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">ยอดคืน</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">เหตุผล</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">สถานะ</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">วันที่ขอ</th>
            <th class="border-0 px-4 py-3 font-semibold text-gray-500" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">การดำเนินการ</th>
          </tr>
        </thead>
        <tbody>
          @foreach($refunds as $refund)
          @php
            $statusColors = [
              'approved' => ['bg'=>'rgba(16,185,129,0.1)','text'=>'#059669','label'=>'อนุมัติแล้ว'],
              'pending'  => ['bg'=>'rgba(245,158,11,0.1)','text'=>'#d97706','label'=>'รอดำเนินการ'],
              'rejected' => ['bg'=>'rgba(239,68,68,0.1)','text'=>'#dc2626','label'=>'ปฏิเสธ'],
              'completed' => ['bg'=>'rgba(99,102,241,0.1)','text'=>'#6366f1','label'=>'เสร็จสิ้น'],
            ];
            $sc = $statusColors[$refund->status] ?? ['bg'=>'rgba(148,163,184,0.15)','text'=>'#64748b','label'=>$refund->status];
          @endphp
          <tr>
            <td class="px-4 py-3 align-middle text-gray-500">#{{ $refund->id }}</td>
            <td class="px-4 py-3 align-middle">
              @if($refund->order)
              <a href="{{ route('admin.orders.show', $refund->order_id) }}" class="no-underline">
                <span class="badge" style="background:rgba(99,102,241,0.1);color:#6366f1;font-weight:500;padding:0.3em 0.6em;border-radius:6px;">
                  {{ $refund->order->order_number ?? '#'.$refund->order_id }}
                </span>
              </a>
              @else
              <span class="text-gray-500">#{{ $refund->order_id }}</span>
              @endif
            </td>
            <td class="px-4 py-3 align-middle">
              @if($refund->user)
              <div class="font-medium">{{ $refund->user->full_name }}</div>
              <div class="text-gray-500 small">{{ $refund->user->email }}</div>
              @else
              <span class="text-gray-500">-</span>
              @endif
            </td>
            <td class="px-4 py-3 align-middle text-end font-semibold" style="color:#f43f5e;">
              ฿{{ number_format($refund->amount, 2) }}
            </td>
            <td class="px-4 py-3 align-middle text-gray-500" style="max-width:180px;">
              <span title="{{ $refund->reason }}">{{ Str::limit($refund->reason ?? '-', 45) }}</span>
            </td>
            <td class="px-4 py-3 align-middle">
              <span class="badge" style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};font-weight:500;padding:0.35em 0.75em;border-radius:8px;">
                {{ $sc['label'] }}
              </span>
            </td>
            <td class="px-4 py-3 align-middle text-gray-500" style="white-space:nowrap;">
              {{ $refund->created_at->format('d/m/Y H:i') }}
            </td>
            <td class="px-4 py-3 align-middle">
              @if($refund->status === 'pending')
              <button type="button" class="text-sm px-3 py-1.5 rounded-lg"
                  style="background:#6366f1;color:#fff;border-radius:7px;font-size:0.8rem;padding:0.25rem 0.7rem;border:none;"
                  @click="processRefundId = '{{ $refund->id }}'; processOrder = '{{ $refund->order->order_number ?? '#'.$refund->order_id }}'; processAmount = '{{ number_format($refund->amount, 2) }}'; showProcessModal = true">
                <i class="bi bi-gear mr-1"></i>ดำเนินการ
              </button>
              @else
              <span class="text-gray-500 small">-</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @if($refunds->hasPages())
    <div id="admin-pagination-area" class="px-4 py-3 border-t">
      {{ $refunds->links() }}
    </div>
    @endif
    @else
    <div class="p-5 text-center">
      <i class="bi bi-arrow-return-left" style="font-size:3rem;color:#cbd5e1;"></i>
      <p class="text-gray-500 mt-3 mb-0">ไม่พบคำขอคืนเงิน</p>
    </div>
    @endif
  </div>
</div>
</div>{{-- end admin-table-area --}}

{{-- Create Refund Modal --}}
<div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showCreateModal = false">
  <div class="fixed inset-0 bg-black/50" @click="showCreateModal = false"></div>
  <div class="flex min-h-screen items-center justify-center p-4">
    <div x-show="showCreateModal" x-transition class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full border-0" style="border-radius:16px;">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between border-0 pb-0 px-4 pt-4">
        <h5 class="text-lg font-semibold font-bold" style="color:#1e293b;">
          <i class="bi bi-plus-circle mr-2" style="color:#6366f1;"></i>สร้างคำขอคืนเงิน
        </h5>
        <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer" @click="showCreateModal = false"></button>
      </div>
      <form method="POST" action="{{ route('admin.finance.refunds.create') }}">
        @csrf
        <div class="p-6 px-4 py-3">
          <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700 mb-1.5 font-semibold small text-gray-500" style="text-transform:uppercase;letter-spacing:0.05em;">Order ID</label>
            <input type="number" name="order_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;border-color:#e2e8f0;"
                placeholder="ระบุ ID คำสั่งซื้อ" required min="1">
            <div class="text-gray-500 text-xs mt-1">ป้อน ID ของคำสั่งซื้อที่ต้องการคืนเงิน</div>
          </div>
          <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700 mb-1.5 font-semibold small text-gray-500" style="text-transform:uppercase;letter-spacing:0.05em;">ยอดคืนเงิน (บาท)</label>
            <div class="flex">
              <span class="px-3 py-2.5 bg-gray-50 border border-gray-300 text-gray-500" style="border-radius:10px 0 0 10px;border-color:#e2e8f0;background:#f8fafc;">฿</span>
              <input type="number" name="amount" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-color:#e2e8f0;border-radius:0 10px 10px 0;"
                  placeholder="0.00" step="0.01" min="0.01" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700 mb-1.5 font-semibold small text-gray-500" style="text-transform:uppercase;letter-spacing:0.05em;">เหตุผล</label>
            <textarea name="reason" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" rows="3"
                 placeholder="ระบุเหตุผลในการคืนเงิน..." required></textarea>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 border-0 px-4 pb-4 pt-0 gap-2">
          <button type="button" class="btn" @click="showCreateModal = false"
              style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:10px;border:none;font-weight:500;">
            ยกเลิก
          </button>
          <button type="submit" class="btn" style="background:#6366f1;color:#fff;border-radius:10px;font-weight:500;">
            <i class="bi bi-plus-circle mr-1"></i>สร้างคำขอ
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Process Refund Modal --}}
<div x-show="showProcessModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showProcessModal = false">
  <div class="fixed inset-0 bg-black/50" @click="showProcessModal = false"></div>
  <div class="flex min-h-screen items-center justify-center p-4">
    <div x-show="showProcessModal" x-transition class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full border-0" style="border-radius:16px;">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between border-0 pb-0 px-4 pt-4">
        <h5 class="text-lg font-semibold font-bold" style="color:#1e293b;">
          <i class="bi bi-gear mr-2" style="color:#6366f1;"></i>ดำเนินการคำขอคืนเงิน
        </h5>
        <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer" @click="showProcessModal = false"></button>
      </div>
      <form method="POST" :action="'{{ url("/admin/finance/refunds") }}/' + processRefundId + '/process'">
        @csrf
        <div class="p-6 px-4 py-3">
          <div class="p-3 mb-3" style="background:#f8fafc;border-radius:10px;">
            <div class="flex justify-between">
              <span class="text-gray-500 small">คำสั่งซื้อ</span>
              <span class="font-medium" x-text="processOrder">-</span>
            </div>
            <div class="flex justify-between mt-1">
              <span class="text-gray-500 small">ยอดคืนเงิน</span>
              <span class="font-bold" style="color:#f43f5e;" x-text="'฿' + processAmount">-</span>
            </div>
          </div>
          <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700 mb-1.5 font-semibold small text-gray-500" style="text-transform:uppercase;letter-spacing:0.05em;">การดำเนินการ</label>
            <div class="flex gap-2">
              <div class="grow">
                <input type="radio" class="btn-check" name="action" id="actionApprove" value="approve">
                <label class="btn w-full" for="actionApprove"
                    style="border-radius:10px;border:2px solid #e2e8f0;font-weight:500;color:#059669;">
                  <i class="bi bi-check-circle mr-1"></i>อนุมัติ
                </label>
              </div>
              <div class="grow">
                <input type="radio" class="btn-check" name="action" id="actionReject" value="reject">
                <label class="btn w-full" for="actionReject"
                    style="border-radius:10px;border:2px solid #e2e8f0;font-weight:500;color:#dc2626;">
                  <i class="bi bi-x-circle mr-1"></i>ปฏิเสธ
                </label>
              </div>
            </div>
          </div>
          <div class="mb-1">
            <label class="block text-sm font-medium text-gray-700 mb-1.5 font-semibold small text-gray-500" style="text-transform:uppercase;letter-spacing:0.05em;">หมายเหตุ</label>
            <textarea name="note" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" rows="2"
                 placeholder="หมายเหตุเพิ่มเติม (ไม่บังคับ)"></textarea>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 border-0 px-4 pb-4 pt-0 gap-2">
          <button type="button" class="btn" @click="showProcessModal = false"
              style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:10px;border:none;font-weight:500;">
            ยกเลิก
          </button>
          <button type="submit" class="btn" style="background:#6366f1;color:#fff;border-radius:10px;font-weight:500;">
            <i class="bi bi-send mr-1"></i>บันทึก
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
</div>{{-- end x-data wrapper --}}
@endsection

