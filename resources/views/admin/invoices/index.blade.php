@extends('layouts.admin')

@section('title', 'ใบเสร็จ / ใบแจ้งหนี้')

@section('content')

{{-- Page Header --}}
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-receipt mr-2" style="color:#6366f1;"></i>ใบเสร็จ / ใบแจ้งหนี้
  </h4>
</div>

{{-- Summary Stat Cards --}}
<div class="row g-3 mb-4">
  {{-- Total Invoices --}}
  <div class="col-xl">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
      <div class="p-5 py-3 px-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(99,102,241,0.1);">
            <i class="bi bi-receipt" style="font-size:1.2rem;color:#6366f1;"></i>
          </div>
          <div>
            <div class="font-bold" style="font-size:1.5rem;line-height:1.1;color:#1e293b;">{{ number_format($stats['total_invoices']) }}</div>
            <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">ใบเสร็จทั้งหมด</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  {{-- Total Revenue --}}
  <div class="col-xl">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
      <div class="p-5 py-3 px-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(34,197,94,0.1);">
            <i class="bi bi-cash-stack" style="font-size:1.2rem;color:#22c55e;"></i>
          </div>
          <div>
            <div class="font-bold" style="font-size:1.3rem;line-height:1.1;color:#22c55e;">{{ number_format($stats['total_revenue'], 0) }}</div>
            <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">รายได้รวม (฿)</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  {{-- This Month --}}
  <div class="col-xl">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
      <div class="p-5 py-3 px-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(59,130,246,0.1);">
            <i class="bi bi-calendar-month" style="font-size:1.2rem;color:#3b82f6;"></i>
          </div>
          <div>
            <div class="font-bold" style="font-size:1.3rem;line-height:1.1;color:#3b82f6;">{{ number_format($stats['this_month'], 0) }}</div>
            <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">เดือนนี้ (฿)</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  {{-- Today --}}
  <div class="col-xl">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
      <div class="p-5 py-3 px-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(16,185,129,0.1);">
            <i class="bi bi-clock" style="font-size:1.2rem;color:#10b981;"></i>
          </div>
          <div>
            <div class="font-bold" style="font-size:1.3rem;line-height:1.1;color:#10b981;">{{ number_format($stats['today'], 0) }}</div>
            <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">วันนี้ (฿)</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.invoices.index') }}">
    <div class="af-grid">

      {{-- Search field --}}
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="เลขใบเสร็จ, ชื่อลูกค้า, อีเมล..." value="{{ request('q') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>

      {{-- Period filter --}}
      <div>
        <label class="af-label">ช่วงเวลา</label>
        <select name="period" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="today" {{ request('period') === 'today' ? 'selected' : '' }}>วันนี้</option>
          <option value="week"  {{ request('period') === 'week'  ? 'selected' : '' }}>7 วันล่าสุด</option>
          <option value="month" {{ request('period') === 'month' ? 'selected' : '' }}>30 วันล่าสุด</option>
          <option value="year"  {{ request('period') === 'year'  ? 'selected' : '' }}>ปีนี้</option>
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

{{-- Invoices Table --}}
<div id="admin-table-area">
<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);overflow:hidden;">
  <div class="p-5 p-0">
    <div class="overflow-x-auto">
      <table class="table table-hover mb-0 align-middle">
        <thead style="background:rgba(99,102,241,0.04);border-b:1px solid rgba(99,102,241,0.08);">
          <tr>
            <th class="ps-4" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;padding-top:0.9rem;padding-bottom:0.9rem;">เลขใบเสร็จ</th>
            <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">ลูกค้า</th>
            <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">อีเวนต์</th>
            <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">ยอดรวม</th>
            <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">วันที่</th>
            <th style="width:56px;"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($orders as $order)
          @php
            $firstName = $order->user->first_name ?? 'U';
            $lastName  = $order->user->last_name ?? '';
          @endphp
          <tr style="transition:background .12s;">
            {{-- Invoice Number --}}
            <td class="ps-4">
              <a href="{{ route('admin.invoices.show', $order->id) }}" class="no-underline">
                <code style="font-size:0.82rem;color:#6366f1;font-weight:600;background:rgba(99,102,241,0.06);padding:0.2em 0.5em;border-radius:5px;">
                  INV-{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}
                </code>
              </a>
            </td>
            {{-- Customer --}}
            <td>
              <div class="flex items-center gap-2">
                <x-avatar :src="$order->user->avatar ?? null"
                     :name="trim($firstName . ' ' . $lastName)"
                     :user-id="$order->user_id"
                     size="md" />
                <div>
                  <div style="font-size:0.88rem;font-weight:600;color:#1e293b;line-height:1.3;">
                    {{ trim($firstName . ' ' . $lastName) ?: 'ไม่ระบุ' }}
                  </div>
                  <div style="font-size:0.75rem;color:#94a3b8;line-height:1.2;">
                    {{ $order->user->email ?? '' }}
                  </div>
                </div>
              </div>
            </td>
            {{-- Event --}}
            <td>
              <span style="font-size:0.85rem;color:#475569;">
                {{ $order->event->name ?? '-' }}
              </span>
            </td>
            {{-- Total --}}
            <td>
              <span class="font-bold" style="font-size:0.95rem;color:#1e293b;">
                ฿{{ number_format($order->total, 0) }}
              </span>
            </td>
            {{-- Date --}}
            <td>
              <div style="font-size:0.82rem;color:#475569;">{{ $order->created_at->format('d/m/Y') }}</div>
              <div style="font-size:0.73rem;color:#94a3b8;">{{ $order->created_at->format('H:i') }}</div>
            </td>
            {{-- View Button --}}
            <td class="pe-3">
              <a href="{{ route('admin.invoices.show', $order->id) }}"
                 class="inline-flex items-center justify-center"
                 style="width:34px;height:34px;border-radius:8px;background:rgba(99,102,241,0.07);border:none;color:#6366f1;transition:background .15s;"
                 title="ดูใบเสร็จ"
                 onmouseover="this.style.background='rgba(99,102,241,0.15)'"
                 onmouseout="this.style.background='rgba(99,102,241,0.07)'">
                <i class="bi bi-eye" style="font-size:0.9rem;"></i>
              </a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-5">
              <div style="color:#cbd5e1;">
                <i class="bi bi-receipt" style="font-size:3rem;display:block;margin-bottom:0.75rem;"></i>
              </div>
              <p class="text-gray-500 mb-1" style="font-size:0.95rem;font-weight:500;">ไม่พบใบเสร็จ</p>
              @if(request()->hasAny(['q','period']))
              <a href="{{ route('admin.invoices.index') }}" class="text-sm px-3 py-1.5 rounded-lg mt-2 inline-block" style="border-radius:8px;background:rgba(99,102,241,0.08);color:#6366f1;border:none;">
                <i class="bi bi-x-circle mr-1"></i>ล้างตัวกรอง
              </a>
              @endif
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Pagination --}}
  <div id="admin-pagination-area">
  @if($orders->hasPages())
  <div class="px-5 py-3 border-t border-gray-100 bg-white border-0 py-3 px-4" style="border-t:1px solid rgba(0,0,0,0.05);">
    <div class="flex flex-wrap justify-between items-center gap-2">
      <div class="text-gray-500" style="font-size:0.82rem;">
        แสดง <strong>{{ $orders->firstItem() }}</strong>–<strong>{{ $orders->lastItem() }}</strong>
        จาก <strong>{{ number_format($orders->total()) }}</strong> รายการ
      </div>
      <nav>
        <ul class="pagination pagination-sm mb-0 gap-1">
          {{-- Previous --}}
          @if($orders->onFirstPage())
          <li class="page-item disabled">
            <span class="page-link" style="border-radius:8px;border-color:#e2e8f0;color:#94a3b8;">
              <i class="bi bi-chevron-left" style="font-size:0.75rem;"></i>
            </span>
          </li>
          @else
          <li class="page-item">
            <a class="page-link" href="{{ $orders->withQueryString()->previousPageUrl() }}" style="border-radius:8px;border-color:#e2e8f0;color:#475569;">
              <i class="bi bi-chevron-left" style="font-size:0.75rem;"></i>
            </a>
          </li>
          @endif

          {{-- Page Numbers --}}
          @foreach($orders->withQueryString()->getUrlRange(max(1,$orders->currentPage()-2), min($orders->lastPage(),$orders->currentPage()+2)) as $page => $url)
          <li class="page-item {{ $page == $orders->currentPage() ? 'active' : '' }}">
            <a class="page-link" href="{{ $url }}"
              style="border-radius:8px;border-color:{{ $page == $orders->currentPage() ? '#6366f1' : '#e2e8f0' }};background:{{ $page == $orders->currentPage() ? '#6366f1' : '#fff' }};color:{{ $page == $orders->currentPage() ? '#fff' : '#475569' }};">
              {{ $page }}
            </a>
          </li>
          @endforeach

          {{-- Next --}}
          @if($orders->hasMorePages())
          <li class="page-item">
            <a class="page-link" href="{{ $orders->withQueryString()->nextPageUrl() }}" style="border-radius:8px;border-color:#e2e8f0;color:#475569;">
              <i class="bi bi-chevron-right" style="font-size:0.75rem;"></i>
            </a>
          </li>
          @else
          <li class="page-item disabled">
            <span class="page-link" style="border-radius:8px;border-color:#e2e8f0;color:#94a3b8;">
              <i class="bi bi-chevron-right" style="font-size:0.75rem;"></i>
            </span>
          </li>
          @endif
        </ul>
      </nav>
    </div>
  </div>
  @endif
  </div>{{-- end #admin-pagination-area --}}
</div>
</div>{{-- end #admin-table-area --}}

<style>
.table-hover tbody tr:hover { background: rgba(99,102,241,0.025) !important; }
</style>

@endsection
