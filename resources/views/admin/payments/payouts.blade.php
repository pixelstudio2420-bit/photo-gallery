@extends('layouts.admin')

@section('title', 'การจ่ายเงินช่างภาพ')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-cash-stack mr-2" style="color:#6366f1;"></i>การจ่ายเงินช่างภาพ
  </h4>
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
      <a href="{{ route('admin.payments.banks') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-bank mr-1"></i> บัญชีธนาคาร
      </a>
      <a href="{{ route('admin.payments.payouts') }}" class="text-sm px-4 py-1.5 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-medium">
        <i class="bi bi-cash-stack mr-1"></i> การจ่ายช่างภาพ
      </a>
    </div>
  </div>
</div>

{{-- Flash Messages --}}
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

{{-- Stats Cards --}}
<div class="row g-3 mb-4">
  {{-- Total Gross --}}
  <div class="col-xl">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
      <div class="p-5 py-3 px-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(99,102,241,0.1);">
            <i class="bi bi-graph-up-arrow" style="font-size:1.2rem;color:#6366f1;"></i>
          </div>
          <div>
            <div class="font-bold" style="font-size:1.3rem;line-height:1.1;color:#6366f1;">{{ number_format($stats['total_gross'], 0) }} &#3647;</div>
            <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">รายได้รวม</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  {{-- Platform Fee --}}
  <div class="col-xl">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
      <div class="p-5 py-3 px-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(239,68,68,0.1);">
            <i class="bi bi-percent" style="font-size:1.2rem;color:#ef4444;"></i>
          </div>
          <div>
            <div class="font-bold" style="font-size:1.3rem;line-height:1.1;color:#ef4444;">{{ number_format($stats['total_platform_fee'], 0) }} &#3647;</div>
            <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">ค่าธรรมเนียม</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  {{-- Paid --}}
  <div class="col-xl">
    <a href="{{ route('admin.payments.payouts', ['status' => 'paid']) }}" class="no-underline">
      <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
        <div class="p-5 py-3 px-4">
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(16,185,129,0.1);">
              <i class="bi bi-check-circle" style="font-size:1.2rem;color:#10b981;"></i>
            </div>
            <div>
              <div class="font-bold" style="font-size:1.3rem;line-height:1.1;color:#10b981;">{{ number_format($stats['paid_amount'], 0) }} &#3647;</div>
              <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">จ่ายแล้ว ({{ number_format($stats['paid_count']) }} รายการ)</div>
            </div>
          </div>
        </div>
      </div>
    </a>
  </div>
  {{-- Pending --}}
  <div class="col-xl">
    <a href="{{ route('admin.payments.payouts', ['status' => 'pending']) }}" class="no-underline">
      <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
        <div class="p-5 py-3 px-4">
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(245,158,11,0.1);">
              <i class="bi bi-clock-history" style="font-size:1.2rem;color:#f59e0b;"></i>
            </div>
            <div>
              <div class="font-bold" style="font-size:1.3rem;line-height:1.1;color:#f59e0b;">{{ number_format($stats['pending_amount'], 0) }} &#3647;</div>
              <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">รอจ่าย ({{ number_format($stats['pending_count']) }} รายการ)</div>
            </div>
          </div>
        </div>
      </div>
    </a>
  </div>
</div>

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.payments.payouts') }}">
    <div class="af-grid">

      {{-- Search --}}
      <div class="af-search">
        <label class="af-label">ค้นหาช่างภาพ</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="ชื่อ, นามสกุล, อีเมล..." value="{{ request('q') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>

      {{-- Status --}}
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Pending</option>
          <option value="paid"      {{ request('status') === 'paid'      ? 'selected' : '' }}>Paid</option>
          <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
          <option value="failed"    {{ request('status') === 'failed'    ? 'selected' : '' }}>Failed</option>
        </select>
      </div>

      {{-- Period --}}
      <div>
        <label class="af-label">ช่วงเวลา</label>
        <select name="period" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="today" {{ request('period') === 'today' ? 'selected' : '' }}>วันนี้</option>
          <option value="week"  {{ request('period') === 'week'  ? 'selected' : '' }}>7 วัน</option>
          <option value="month" {{ request('period') === 'month' ? 'selected' : '' }}>30 วัน</option>
        </select>
      </div>

      {{-- Sort --}}
      <div>
        <label class="af-label">เรียงตาม</label>
        <select name="sort" class="af-input">
          <option value="">ใหม่สุด</option>
          <option value="oldest"      {{ request('sort') === 'oldest'      ? 'selected' : '' }}>เก่าสุด</option>
          <option value="amount_high" {{ request('sort') === 'amount_high' ? 'selected' : '' }}>จำนวนมาก</option>
          <option value="amount_low"  {{ request('sort') === 'amount_low'  ? 'selected' : '' }}>จำนวนน้อย</option>
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

{{-- Bulk Action + Table --}}
<div x-data="{
  selected: [],
  selectAll: false,
  toggleAll() {
    if (this.selectAll) {
      this.selected = [...document.querySelectorAll('[data-payout-pending]')].map(el => el.value);
    } else {
      this.selected = [];
    }
  }
}" x-cloak>

  {{-- Bulk Action Bar --}}
  <div x-show="selected.length > 0" x-transition
    class="flex items-center gap-3 mb-3 px-4 py-3 rounded-xl border border-indigo-200"
    style="background:rgba(99,102,241,0.04);">
    <span class="text-sm font-semibold text-indigo-600">
      <i class="bi bi-check2-square mr-1"></i>เลือกแล้ว <span x-text="selected.length"></span> รายการ
    </span>
    <form method="POST" action="{{ route('admin.payments.payouts.bulk-mark-paid') }}" class="inline"
      onsubmit="return confirm('ยืนยันจ่ายเงินรายการที่เลือก?');">
      @csrf
      <template x-for="id in selected" :key="id">
        <input type="hidden" name="payout_ids[]" :value="id">
      </template>
      <button type="submit"
        class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-1.5 rounded-lg text-white transition"
        style="background:linear-gradient(135deg,#10b981,#059669);">
        <i class="bi bi-cash-coin"></i> จ่ายเงินที่เลือก
      </button>
    </form>
    <button type="button" @click="selected = []; selectAll = false"
      class="text-sm text-gray-500 hover:text-gray-700 ml-auto">
      <i class="bi bi-x-lg mr-1"></i>ยกเลิก
    </button>
  </div>

  {{-- Table --}}
  <div id="admin-table-area">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);overflow:hidden;">
      <div class="p-5 p-0">
        <div class="overflow-x-auto">
          <table class="table table-hover mb-0 align-middle">
            <thead style="background:rgba(99,102,241,0.04);border-b:1px solid rgba(99,102,241,0.08);">
              <tr>
                <th style="width:42px;padding-left:1rem;">
                  <input type="checkbox" x-model="selectAll" @change="toggleAll()"
                    class="rounded border-gray-300 text-indigo-500 focus:ring-indigo-500"
                    style="width:16px;height:16px;">
                </th>
                <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;padding-top:0.9rem;padding-bottom:0.9rem;">ID</th>
                <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">ช่างภาพ</th>
                <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">ออร์เดอร์</th>
                <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">ยอดรวม</th>
                <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">ค่าธรรมเนียม</th>
                <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">จ่ายจริง</th>
                <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">สถานะ</th>
                <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">วันที่</th>
                <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;width:100px;">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              @forelse($payouts as $payout)
              @php
                $statusConfig = match($payout->status ?? 'pending') {
                  'paid', 'completed' => ['bg' => 'rgba(16,185,129,0.1)', 'color' => '#10b981', 'label' => $payout->status === 'completed' ? 'Completed' : 'Paid', 'dot' => '#10b981'],
                  'pending'           => ['bg' => 'rgba(245,158,11,0.1)', 'color' => '#f59e0b', 'label' => 'Pending', 'dot' => '#f59e0b'],
                  'processing'        => ['bg' => 'rgba(59,130,246,0.1)', 'color' => '#3b82f6', 'label' => 'Processing', 'dot' => '#3b82f6'],
                  'failed'            => ['bg' => 'rgba(239,68,68,0.1)', 'color' => '#ef4444', 'label' => 'Failed', 'dot' => '#ef4444'],
                  default             => ['bg' => 'rgba(100,116,139,0.1)', 'color' => '#64748b', 'label' => ucfirst($payout->status ?? 'pending'), 'dot' => '#94a3b8'],
                };
                $photographer = $payout->photographer;
                $pName = trim(($photographer->first_name ?? '') . ' ' . ($photographer->last_name ?? '')) ?: '-';
                $pEmail = $photographer->email ?? '';
                $pAvatar = $photographer->avatar ?? null;
                $pInitial = mb_substr($photographer->first_name ?? 'U', 0, 1);
              @endphp
              <tr style="transition:background .12s;">
                {{-- Checkbox --}}
                <td style="padding-left:1rem;">
                  @if(($payout->status ?? 'pending') === 'pending')
                  <input type="checkbox" value="{{ $payout->id }}" x-model="selected" data-payout-pending
                    class="rounded border-gray-300 text-indigo-500 focus:ring-indigo-500"
                    style="width:16px;height:16px;">
                  @else
                  <span class="inline-block" style="width:16px;height:16px;"></span>
                  @endif
                </td>
                {{-- ID --}}
                <td>
                  <code style="font-size:0.82rem;color:#6366f1;font-weight:600;background:rgba(99,102,241,0.06);padding:0.2em 0.5em;border-radius:5px;">#{{ $payout->id }}</code>
                </td>
                {{-- Photographer --}}
                <td>
                  <div class="flex items-center gap-2">
                    @if($pAvatar)
                    <img src="{{ $pAvatar }}" alt="" class="rounded-full object-cover" style="width:34px;height:34px;">
                    @else
                    <div class="flex items-center justify-center rounded-full text-white font-bold" style="width:34px;height:34px;font-size:0.8rem;background:linear-gradient(135deg,#6366f1,#8b5cf6);">{{ $pInitial }}</div>
                    @endif
                    <div>
                      <div style="font-size:0.88rem;font-weight:600;color:#1e293b;line-height:1.3;">{{ $pName }}</div>
                      <div style="font-size:0.75rem;color:#94a3b8;line-height:1.2;">{{ $pEmail }}</div>
                    </div>
                  </div>
                </td>
                {{-- Order --}}
                <td>
                  @if($payout->order)
                  <a href="{{ route('admin.orders.show', $payout->order_id) }}" class="no-underline">
                    <code style="font-size:0.8rem;color:#6366f1;font-weight:600;background:rgba(99,102,241,0.06);padding:0.2em 0.5em;border-radius:5px;">
                      #{{ $payout->order->order_number ?? $payout->order_id }}
                    </code>
                  </a>
                  @else
                  <span class="text-gray-400 text-sm">-</span>
                  @endif
                </td>
                {{-- Gross --}}
                <td>
                  <span class="font-bold" style="font-size:0.92rem;color:#1e293b;">{{ number_format($payout->gross_amount, 2) }} &#3647;</span>
                </td>
                {{-- Platform Fee --}}
                <td>
                  <span style="font-size:0.88rem;color:#ef4444;font-weight:500;">-{{ number_format($payout->platform_fee, 2) }} &#3647;</span>
                </td>
                {{-- Payout Amount --}}
                <td>
                  <span class="font-bold" style="font-size:0.92rem;color:#10b981;">{{ number_format($payout->payout_amount, 2) }} &#3647;</span>
                </td>
                {{-- Status --}}
                <td>
                  <span style="display:inline-flex;align-items:center;gap:5px;background:{{ $statusConfig['bg'] }};color:{{ $statusConfig['color'] }};border-radius:50px;padding:0.3rem 0.75rem;font-size:0.75rem;font-weight:600;white-space:nowrap;">
                    <span style="width:6px;height:6px;border-radius:50%;background:{{ $statusConfig['dot'] }};flex-shrink:0;"></span>
                    {{ $statusConfig['label'] }}
                  </span>
                </td>
                {{-- Date --}}
                <td>
                  <div style="font-size:0.82rem;color:#475569;">{{ $payout->created_at?->format('d/m/Y') }}</div>
                  <div style="font-size:0.73rem;color:#94a3b8;">{{ $payout->created_at?->format('H:i') }}</div>
                </td>
                {{-- Action --}}
                <td>
                  @if(($payout->status ?? 'pending') === 'pending')
                  <form method="POST" action="{{ route('admin.payments.payouts.mark-paid', $payout->id) }}"
                    onsubmit="return confirm('ยืนยันจ่ายเงินรายการ #{{ $payout->id }}?');">
                    @csrf
                    <button type="submit"
                      class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg text-white transition hover:opacity-90"
                      style="background:linear-gradient(135deg,#10b981,#059669);border:none;">
                      <i class="bi bi-cash-coin"></i> จ่ายเงิน
                    </button>
                  </form>
                  @else
                  <span class="text-xs text-gray-400">-</span>
                  @endif
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="10" class="text-center py-10">
                  <div class="flex flex-col items-center">
                    <i class="bi bi-cash-stack" style="font-size:2.5rem;color:#cbd5e1;"></i>
                    <p class="text-gray-500 mt-3 mb-1 font-medium text-sm">ยังไม่มีรายการจ่ายเงิน</p>
                    <p class="text-gray-400 text-xs mb-0">รายการจ่ายเงินจะปรากฏเมื่อมีคำสั่งซื้อที่ได้รับการอนุมัติ</p>
                  </div>
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  {{-- Pagination --}}
  <div id="admin-pagination-area">
    @if($payouts->hasPages())
    <div class="flex justify-center mt-6">{{ $payouts->withQueryString()->links() }}</div>
    @endif
  </div>

</div>
@endsection
