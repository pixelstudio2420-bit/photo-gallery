@extends('layouts.admin')

@section('title', 'คำสั่งซื้อสินค้าดิจิทัล')

@section('content')
<div x-data="{
  showApproveModal: false,
  showRejectModal: false,
  orderId: '',
  orderNumber: '',
  productName: '',
  rejectReason: '',
  rejectCharCount: 0
}">
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-bag-check mr-2" style="color:#6366f1;"></i>Digital Product Orders
    <small class="text-gray-500 font-normal text-base ml-1">คำสั่งซื้อสินค้าดิจิทัล</small>
  </h4>
</div>

{{-- Flash Messages --}}
@if(session('success'))
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-4 text-sm flex items-center gap-2" role="alert" style="border-radius:12px;border:none;background:rgba(16,185,129,0.1);color:#059669;">
  <i class="bi bi-check-circle-fill"></i>
  <span>{{ session('success') }}</span>
  <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer" onclick="this.parentElement.remove()"></button>
</div>
@endif
@if(session('error'))
<div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm flex items-center gap-2" role="alert" style="border-radius:12px;border:none;background:rgba(239,68,68,0.1);color:#dc2626;">
  <i class="bi bi-exclamation-circle-fill"></i>
  <span>{{ session('error') }}</span>
  <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer" onclick="this.parentElement.remove()"></button>
</div>
@endif

{{-- Stats Cards --}}
<div class="row g-3 mb-4">
  {{-- Total Orders --}}
  <div class="">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,0.07);">
      <div class="p-5 flex items-center gap-3 py-3 px-4">
        <div class="flex items-center justify-center shrink-0" style="width:46px;height:46px;border-radius:12px;background:rgba(99,102,241,0.1);">
          <i class="bi bi-bag-check" style="font-size:1.3rem;color:#6366f1;"></i>
        </div>
        <div>
          <div class="font-bold text-xl lh-1 mb-1" style="color:#1e293b;">{{ number_format($stats->total ?? 0) }}</div>
          <div class="text-gray-500 small">คำสั่งซื้อทั้งหมด</div>
        </div>
      </div>
    </div>
  </div>
  {{-- Pending Review --}}
  <div class="">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,0.07);">
      <div class="p-5 flex items-center gap-3 py-3 px-4">
        <div class="flex items-center justify-center shrink-0" style="width:46px;height:46px;border-radius:12px;background:rgba(245,158,11,0.1);">
          <i class="bi bi-clock-history" style="font-size:1.3rem;color:#f59e0b;"></i>
        </div>
        <div>
          <div class="font-bold text-xl lh-1 mb-1" style="color:#1e293b;">{{ number_format($stats->pending_count ?? 0) }}</div>
          <div class="text-gray-500 small">รอตรวจสอบ</div>
        </div>
      </div>
    </div>
  </div>
  {{-- Paid --}}
  <div class="">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,0.07);">
      <div class="p-5 flex items-center gap-3 py-3 px-4">
        <div class="flex items-center justify-center shrink-0" style="width:46px;height:46px;border-radius:12px;background:rgba(16,185,129,0.1);">
          <i class="bi bi-check2-circle" style="font-size:1.3rem;color:#10b981;"></i>
        </div>
        <div>
          <div class="font-bold text-xl lh-1 mb-1" style="color:#1e293b;">{{ number_format($stats->paid_count ?? 0) }}</div>
          <div class="text-gray-500 small">ชำระแล้ว / สำเร็จ</div>
        </div>
      </div>
    </div>
  </div>
  {{-- Total Revenue --}}
  <div class="">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,0.07);">
      <div class="p-5 flex items-center gap-3 py-3 px-4">
        <div class="flex items-center justify-center shrink-0" style="width:46px;height:46px;border-radius:12px;background:rgba(244,63,94,0.1);">
          <i class="bi bi-cash-coin" style="font-size:1.3rem;color:#f43f5e;"></i>
        </div>
        <div>
          <div class="font-bold text-xl lh-1 mb-1" style="color:#1e293b;">฿{{ number_format($stats->total_revenue ?? 0, 0) }}</div>
          <div class="text-gray-500 small">รายได้รวม (ที่ชำระ)</div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Filter Bar --}}
<div class="af-bar mb-3" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.digital-orders.index') }}">
    <div class="af-grid">

      {{-- Search field (span 2 cols) --}}
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="search" class="af-input" placeholder="หมายเลขคำสั่งซื้อ, อีเมล, ชื่อสินค้า..." value="{{ $search }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>

      {{-- Status filter --}}
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="pending_review"  {{ $status === 'pending_review'  ? 'selected' : '' }}>รอตรวจสอบ (Pending Review)</option>
          <option value="pending_payment" {{ $status === 'pending_payment' ? 'selected' : '' }}>รอชำระ (Pending Payment)</option>
          <option value="paid"            {{ $status === 'paid'            ? 'selected' : '' }}>ชำระแล้ว (Paid)</option>
          <option value="cancelled"       {{ $status === 'cancelled'       ? 'selected' : '' }}>ยกเลิก (Cancelled)</option>
        </select>
      </div>

      {{-- Actions --}}
      <div class="af-actions">
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>

    </div>
  </form>
</div>

{{-- Orders Table --}}
<div id="admin-table-area">
<div class="card border-0" style="border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,0.07);overflow:hidden;">
  <div class="p-5 p-0">
    <div class="overflow-x-auto">
      <table class="table table-hover mb-0 align-middle">
        <thead style="background:rgba(99,102,241,0.03);">
          <tr>
            <th class="ps-4" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;white-space:nowrap;">Order #</th>
            <th style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;">สินค้า</th>
            <th style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;">ผู้ซื้อ</th>
            <th style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;">ยอดเงิน</th>
            <th style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;">สถานะ</th>
            <th style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;">หลักฐาน</th>
            <th style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;white-space:nowrap;">วันที่</th>
            <th style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;width:120px;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          @forelse($orders as $order)
          @php
            $isPending = in_array($order->status, ['pending_review', 'pending_payment']);
            $buyerName = trim(
              !empty($order->display_name)
                ? $order->display_name
                : ($order->first_name . ' ' . $order->last_name)
            ) ?: $order->email;
            $initial = strtoupper(mb_substr($buyerName, 0, 1, 'UTF-8'));
          @endphp
          <tr>
            {{-- Order Number --}}
            <td class="ps-4">
              <code style="background:rgba(99,102,241,0.08);color:#6366f1;padding:0.2rem 0.5rem;border-radius:6px;font-size:0.8rem;">
                {{ $order->order_number }}
              </code>
            </td>

            {{-- Product --}}
            <td>
              <span class="font-medium" style="font-size:0.9rem;">{{ $order->product_name }}</span>
            </td>

            {{-- Buyer --}}
            <td>
              <div class="flex items-center gap-2">
                <div class="flex items-center justify-center shrink-0"
                   style="width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;font-size:0.65rem;font-weight:bold;">
                  {{ $initial }}
                </div>
                <div>
                  <div style="font-size:0.88rem;font-weight:500;color:#1e293b;">{{ $buyerName }}</div>
                  <div style="font-size:0.75rem;color:#94a3b8;">{{ $order->email }}</div>
                </div>
              </div>
            </td>

            {{-- Amount --}}
            <td>
              <span class="font-semibold" style="color:#6366f1;">฿{{ number_format($order->amount, 0) }}</span>
            </td>

            {{-- Status Badge --}}
            <td>
              @php
                $badge = match($order->status) {
                  'pending_review' => ['class' => 'bg-warning text-dark',  'label' => 'รอตรวจสอบ'],
                  'pending_payment' => ['class' => 'bg-info text-white',   'label' => 'รอชำระ'],
                  'paid'      => ['class' => 'bg-success text-white', 'label' => 'ชำระแล้ว'],
                  'cancelled'    => ['class' => 'bg-danger text-white',  'label' => 'ยกเลิก'],
                  default      => ['class' => 'bg-secondary text-white','label' => ucfirst($order->status)],
                };
              @endphp
              <span class="badge {{ $badge['class'] }}" style="border-radius:50px;padding:0.3rem 0.7rem;font-size:0.72rem;font-weight:500;">
                {{ $badge['label'] }}
              </span>
            </td>

            {{-- Payment Proof --}}
            <td>
              @if($order->slip_image)
                @php
                  $ext = strtolower(pathinfo($order->slip_image, PATHINFO_EXTENSION));
                  $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                  // $order is a raw stdClass from DB::table()->select() in
                  // DigitalOrderController::index — NOT an Eloquent model
                  // — so the slip_image_url accessor on DigitalOrder
                  // ($this->slip_image_url) doesn't apply. Resolve via
                  // StorageManager directly, same logic the accessor uses.
                  // Wrapped in try/catch so a single broken slip path
                  // can't 500 the entire orders list.
                  try {
                      $slipUrl = app(\App\Services\StorageManager::class)
                          ->resolveUrl($order->slip_image);
                  } catch (\Throwable) {
                      $slipUrl = '';
                  }
                @endphp
                @if($isImage)
                  <a href="{{ $slipUrl }}" target="_blank" title="ดูหลักฐานการชำระเงิน">
                    <img src="{{ $slipUrl }}"
                       alt="Payment Proof"
                       style="width:42px;height:42px;object-fit:cover;border-radius:8px;border:2px solid rgba(99,102,241,0.2);">
                  </a>
                @else
                  <a href="{{ $slipUrl }}" target="_blank"
                    class="text-sm px-3 py-1.5 rounded-lg" style="border-radius:8px;background:rgba(99,102,241,0.08);color:#6366f1;border:none;font-size:0.78rem;">
                    <i class="bi bi-file-earmark mr-1"></i>ดูไฟล์
                  </a>
                @endif
              @else
                <span class="text-gray-500 small">—</span>
              @endif
            </td>

            {{-- Date --}}
            <td class="text-gray-500 small" style="white-space:nowrap;">
              {{ \Carbon\Carbon::parse($order->created_at)->format('d/m/Y H:i') }}
            </td>

            {{-- Actions --}}
            <td>
              @if($isPending)
              <div class="flex gap-1">
                {{-- Approve Button --}}
                <button type="button"
                    class="text-sm px-3 py-1.5 rounded-lg flex items-center justify-center"
                    style="width:34px;height:34px;border-radius:8px;background:rgba(16,185,129,0.1);border:none;color:#10b981;"
                    title="อนุมัติ"
                    @click="orderId = '{{ $order->id }}'; orderNumber = '{{ $order->order_number }}'; productName = '{{ addslashes($order->product_name) }}'; showApproveModal = true">
                  <i class="bi bi-check-lg" style="font-size:0.85rem;"></i>
                </button>
                {{-- Reject Button --}}
                <button type="button"
                    class="text-sm px-3 py-1.5 rounded-lg flex items-center justify-center"
                    style="width:34px;height:34px;border-radius:8px;background:rgba(239,68,68,0.1);border:none;color:#ef4444;"
                    title="ปฏิเสธ"
                    @click="orderId = '{{ $order->id }}'; orderNumber = '{{ $order->order_number }}'; productName = '{{ addslashes($order->product_name) }}'; rejectReason = ''; rejectCharCount = 0; showRejectModal = true">
                  <i class="bi bi-x-lg" style="font-size:0.85rem;"></i>
                </button>
              </div>
              @else
                @if($order->status === 'paid')
                  <span class="text-gray-500 small"><i class="bi bi-check2-circle text-green-600 mr-1"></i>สำเร็จ</span>
                @elseif($order->status === 'cancelled')
                  <span class="text-gray-500 small"><i class="bi bi-slash-circle text-red-600 mr-1"></i>ยกเลิก</span>
                @endif
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8" class="text-center py-5">
              <i class="bi bi-bag-check" style="font-size:2.5rem;color:#cbd5e1;"></i>
              <p class="text-gray-500 mt-2 mb-0 small">
                @if($search || $status)
                  ไม่พบคำสั่งซื้อที่ตรงกับเงื่อนไขการค้นหา
                @else
                  ยังไม่มีคำสั่งซื้อสินค้าดิจิทัล
                @endif
              </p>
              @if($search || $status)
              <a href="{{ route('admin.digital-orders.index') }}" class="text-sm px-3 py-1.5 rounded-lg mt-2" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;border:none;">
                ล้างตัวกรอง
              </a>
              @endif
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

</div>{{-- /#admin-table-area --}}

{{-- Pagination --}}
@if($orders->hasPages())
<div id="admin-pagination-area" class="flex justify-center mt-4">
  {{ $orders->appends(request()->query())->links() }}
</div>
@endif

{{-- ======================================================= --}}
{{-- Approve Modal                      --}}
{{-- ======================================================= --}}
<div x-show="showApproveModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showApproveModal = false">
  <div class="fixed inset-0 bg-black/50" @click="showApproveModal = false"></div>
  <div class="flex min-h-screen items-center justify-center p-4">
    <div x-show="showApproveModal" x-transition class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full" style="border-radius:16px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.12);">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between border-0 pb-0 px-4 pt-4">
        <div class="flex items-center gap-2">
          <div style="width:40px;height:40px;border-radius:10px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-check-circle" style="color:#10b981;font-size:1.1rem;"></i>
          </div>
          <h5 class="text-lg font-semibold font-bold mb-0" style="color:#1e293b;">อนุมัติคำสั่งซื้อ</h5>
        </div>
        <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer" @click="showApproveModal = false"></button>
      </div>
      <div class="p-6 px-4 py-3">
        <p class="text-gray-500 mb-2" style="font-size:0.9rem;">คุณต้องการอนุมัติคำสั่งซื้อนี้ใช่หรือไม่?</p>
        <div class="p-3 mb-0" style="background:rgba(16,185,129,0.05);border-radius:10px;border:1px solid rgba(16,185,129,0.15);">
          <div class="row g-1 small">
            <div class=" text-gray-500">Order #:</div>
            <div class="col-8 font-semibold" x-text="orderNumber">—</div>
            <div class=" text-gray-500">สินค้า:</div>
            <div class="col-8 font-medium" x-text="productName">—</div>
          </div>
        </div>
        <p class="text-gray-500 mt-3 mb-0 small">
          <i class="bi bi-info-circle mr-1"></i>
          ระบบจะสร้างลิงก์ดาวน์โหลดและส่งการแจ้งเตือนให้ผู้ซื้อโดยอัตโนมัติ
        </p>
      </div>
      <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 border-0 px-4 pb-4 pt-2 gap-2">
        <button type="button" class="btn" @click="showApproveModal = false"
            style="border-radius:10px;border:1px solid #e2e8f0;color:#64748b;font-weight:500;padding:0.5rem 1.2rem;">
          ยกเลิก
        </button>
        <form method="POST" :action="'{{ route("admin.digital-orders.approve", ["id" => "__ID__"]) }}'.replace('__ID__', orderId)">
          @csrf
          <button type="submit" class="btn" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;border-radius:10px;font-weight:500;border:none;padding:0.5rem 1.5rem;">
            <i class="bi bi-check-lg mr-1"></i>อนุมัติ
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- ======================================================= --}}
{{-- Reject Modal                      --}}
{{-- ======================================================= --}}
<div x-show="showRejectModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showRejectModal = false">
  <div class="fixed inset-0 bg-black/50" @click="showRejectModal = false"></div>
  <div class="flex min-h-screen items-center justify-center p-4">
    <div x-show="showRejectModal" x-transition class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full" style="border-radius:16px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.12);">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between border-0 pb-0 px-4 pt-4">
        <div class="flex items-center gap-2">
          <div style="width:40px;height:40px;border-radius:10px;background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-x-circle" style="color:#ef4444;font-size:1.1rem;"></i>
          </div>
          <h5 class="text-lg font-semibold font-bold mb-0" style="color:#1e293b;">ปฏิเสธคำสั่งซื้อ</h5>
        </div>
        <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer" @click="showRejectModal = false"></button>
      </div>
      <div class="p-6 px-4 py-3">
        <div class="mb-3 p-3" style="background:rgba(239,68,68,0.05);border-radius:10px;border:1px solid rgba(239,68,68,0.15);">
          <div class="row g-1 small">
            <div class=" text-gray-500">Order #:</div>
            <div class="col-8 font-semibold" x-text="orderNumber">—</div>
            <div class=" text-gray-500">สินค้า:</div>
            <div class="col-8 font-medium" x-text="productName">—</div>
          </div>
        </div>
        <form id="rejectForm" method="POST" :action="'{{ route("admin.digital-orders.reject", ["id" => "__ID__"]) }}'.replace('__ID__', orderId)">
          @csrf
          <div class="mb-1">
            <label for="rejectReason" class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small" style="color:#1e293b;">
              เหตุผลในการปฏิเสธ <span class="text-red-600">*</span>
            </label>
            <textarea id="rejectReason" name="reason" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" rows="3"
                 placeholder="ระบุเหตุผล..."
                 maxlength="500"
                 required
                 x-model="rejectReason"
                 @input="rejectCharCount = rejectReason.length"
                 style="border-radius:10px;border-color:#e2e8f0;font-size:0.9rem;resize:none;"></textarea>
            <div class="text-end mt-1">
              <small class="text-gray-500" x-text="rejectCharCount + ' / 500'">0 / 500</small>
            </div>
          </div>
        </form>
      </div>
      <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 border-0 px-4 pb-4 pt-2 gap-2">
        <button type="button" class="btn" @click="showRejectModal = false"
            style="border-radius:10px;border:1px solid #e2e8f0;color:#64748b;font-weight:500;padding:0.5rem 1.2rem;">
          ยกเลิก
        </button>
        <button type="submit" form="rejectForm" class="btn" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border-radius:10px;font-weight:500;border:none;padding:0.5rem 1.5rem;">
          <i class="bi bi-x-lg mr-1"></i>ปฏิเสธคำสั่งซื้อ
        </button>
      </div>
    </div>
  </div>
</div>
</div>{{-- end x-data wrapper --}}
@endsection
