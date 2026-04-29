@extends('layouts.admin')

@section('title', 'ใบเสร็จ INV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT))

@push('styles')
<style>
  @media print {
    /* Hide everything except the invoice */
    #admin-sidebar,
    header,
    .no-print,
    .admin-scrollbar,
    nav,
    .af-bar {
      display: none !important;
    }
    /* Remove sidebar margin */
    body,
    .min-h-screen > div {
      margin-left: 0 !important;
      padding: 0 !important;
    }
    main {
      padding: 0 !important;
      margin: 0 !important;
    }
    /* Make the invoice card full-width with no shadow */
    #invoice-card {
      box-shadow: none !important;
      border: none !important;
      border-radius: 0 !important;
      margin: 0 !important;
    }
    /* Ensure proper page break and sizing */
    @page {
      size: A4;
      margin: 15mm;
    }
    body {
      background: #fff !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
  }
</style>
@endpush

@section('content')

@php
  $invoiceNo = 'INV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT);

  // Thai date formatting
  $thaiMonths = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
  ];
  $createdAt = $order->created_at;
  $thaiYear  = $createdAt->year + 543;
  $thaiDate  = $createdAt->day . ' ' . $thaiMonths[$createdAt->month] . ' ' . $thaiYear;

  $customerName = trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: 'ไม่ระบุชื่อ';
  $customerEmail = $order->user->email ?? '-';

  // VAT calculation
  $subtotal = (float) $order->total;
  $vatEnabled = $settings['vat_enabled'] ?? false;
  $vatRate = $settings['vat_rate'] ?? 7;

  if ($vatEnabled) {
    // Total is VAT-inclusive: subtotal = total / (1 + vat_rate/100)
    $baseAmount = round($subtotal / (1 + $vatRate / 100), 2);
    $vatAmount  = round($subtotal - $baseAmount, 2);
  } else {
    $baseAmount = $subtotal;
    $vatAmount  = 0;
  }
@endphp

{{-- Action Bar (hidden on print) --}}
<div class="flex justify-between items-center mb-4 no-print">
  <div class="flex items-center gap-3">
    <a href="{{ route('admin.invoices.index') }}"
       class="inline-flex items-center justify-center"
       style="width:36px;height:36px;border-radius:10px;background:rgba(99,102,241,0.08);color:#6366f1;transition:background .15s;"
       title="กลับไปรายการใบเสร็จ">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
      <i class="bi bi-receipt mr-2" style="color:#6366f1;"></i>{{ $invoiceNo }}
    </h4>
  </div>
  <button onclick="window.print()"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white font-medium text-sm transition-all"
          style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 14px rgba(99,102,241,0.3);"
          onmouseover="this.style.boxShadow='0 6px 20px rgba(99,102,241,0.45)'"
          onmouseout="this.style.boxShadow='0 4px 14px rgba(99,102,241,0.3)'">
    <i class="bi bi-printer"></i>
    พิมพ์ใบเสร็จ
  </button>
</div>

{{-- Invoice Card --}}
<div id="invoice-card" class="card border-0 mx-auto" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);max-width:800px;">
  <div class="p-6 lg:p-10">

    {{-- ═══ Company Header ═══ --}}
    <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-8 pb-6" style="border-bottom:2px solid #6366f1;">
      <div>
        <h2 class="font-bold mb-1" style="font-size:1.5rem;color:#1e293b;letter-spacing:-0.02em;">
          {{ $settings['company_name'] }}
        </h2>
        @if($settings['company_address'])
        <div style="font-size:0.85rem;color:#64748b;line-height:1.6;">{{ $settings['company_address'] }}</div>
        @endif
        @if($settings['company_phone'])
        <div style="font-size:0.85rem;color:#64748b;">
          <i class="bi bi-telephone mr-1" style="font-size:0.75rem;"></i>{{ $settings['company_phone'] }}
        </div>
        @endif
        @if($settings['company_email'])
        <div style="font-size:0.85rem;color:#64748b;">
          <i class="bi bi-envelope mr-1" style="font-size:0.75rem;"></i>{{ $settings['company_email'] }}
        </div>
        @endif
        @if($settings['company_tax_id'])
        <div style="font-size:0.85rem;color:#64748b;">
          <i class="bi bi-building mr-1" style="font-size:0.75rem;"></i>เลขประจำตัวผู้เสียภาษี: {{ $settings['company_tax_id'] }}
        </div>
        @endif
      </div>
      <div class="text-right sm:text-right">
        <div class="font-bold" style="font-size:1.4rem;color:#6366f1;letter-spacing:0.02em;">ใบเสร็จรับเงิน</div>
        <div style="font-size:0.8rem;color:#94a3b8;margin-top:2px;">RECEIPT / INVOICE</div>
      </div>
    </div>

    {{-- ═══ Invoice Info + Customer ═══ --}}
    <div class="flex flex-col sm:flex-row justify-between gap-6 mb-8">
      {{-- Invoice Details --}}
      <div>
        <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:#94a3b8;font-weight:700;margin-bottom:6px;">รายละเอียดใบเสร็จ</div>
        <table style="font-size:0.88rem;color:#475569;">
          <tr>
            <td class="pr-3 py-0.5 font-semibold" style="color:#1e293b;">เลขที่:</td>
            <td class="py-0.5">
              <code style="color:#6366f1;font-weight:600;background:rgba(99,102,241,0.06);padding:0.15em 0.4em;border-radius:4px;">{{ $invoiceNo }}</code>
            </td>
          </tr>
          <tr>
            <td class="pr-3 py-0.5 font-semibold" style="color:#1e293b;">วันที่:</td>
            <td class="py-0.5">{{ $thaiDate }}</td>
          </tr>
          <tr>
            <td class="pr-3 py-0.5 font-semibold" style="color:#1e293b;">สถานะ:</td>
            <td class="py-0.5">
              <span style="display:inline-flex;align-items:center;gap:5px;background:rgba(34,197,94,0.1);color:#22c55e;border-radius:50px;padding:0.2rem 0.6rem;font-size:0.75rem;font-weight:600;">
                <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;flex-shrink:0;"></span>
                ชำระเงินแล้ว
              </span>
            </td>
          </tr>
        </table>
      </div>

      {{-- Customer Details --}}
      <div class="sm:text-right">
        <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:#94a3b8;font-weight:700;margin-bottom:6px;">ข้อมูลลูกค้า</div>
        <div style="font-size:0.95rem;font-weight:600;color:#1e293b;">{{ $customerName }}</div>
        <div style="font-size:0.85rem;color:#64748b;">{{ $customerEmail }}</div>
        @if($order->user->phone ?? null)
        <div style="font-size:0.85rem;color:#64748b;">{{ $order->user->phone }}</div>
        @endif
      </div>
    </div>

    {{-- ═══ Event Info ═══ --}}
    @if($order->event)
    <div class="mb-6 px-4 py-3 rounded-xl" style="background:rgba(99,102,241,0.04);border:1px solid rgba(99,102,241,0.08);">
      <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:#94a3b8;font-weight:700;margin-bottom:4px;">อีเวนต์</div>
      <div style="font-size:0.95rem;font-weight:600;color:#1e293b;">
        <i class="bi bi-calendar-event mr-1" style="color:#6366f1;font-size:0.85rem;"></i>{{ $order->event->name }}
      </div>
      @if($order->event->shoot_date ?? null)
      <div style="font-size:0.82rem;color:#64748b;margin-top:2px;">
        วันที่จัดงาน: {{ \Carbon\Carbon::parse($order->event->shoot_date)->format('d/m/Y') }}
      </div>
      @endif
    </div>
    @endif

    {{-- ═══ Items Table ═══ --}}
    <div class="overflow-x-auto mb-6">
      <table class="w-full" style="font-size:0.88rem;">
        <thead>
          <tr style="border-bottom:2px solid #e2e8f0;">
            <th class="text-left py-3 px-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">#</th>
            <th class="text-left py-3 px-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">รายการ</th>
            <th class="text-right py-3 px-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">จำนวน</th>
            <th class="text-right py-3 px-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">ราคา (฿)</th>
          </tr>
        </thead>
        <tbody>
          @if($order->items && $order->items->count() > 0)
            @foreach($order->items as $idx => $item)
            <tr style="border-bottom:1px solid #f1f5f9;">
              <td class="py-3 px-2" style="color:#94a3b8;">{{ $idx + 1 }}</td>
              <td class="py-3 px-2">
                <div class="flex items-center gap-2">
                  @if($item->thumbnail_url)
                  <img src="{{ $item->thumbnail_url }}" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:6px;background:#f1f5f9;" loading="lazy">
                  @else
                  <div style="width:36px;height:36px;border-radius:6px;background:rgba(99,102,241,0.06);display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-image" style="color:#6366f1;font-size:0.8rem;"></i>
                  </div>
                  @endif
                  <span style="color:#1e293b;font-weight:500;">รูปภาพ #{{ $item->photo_id ?? ($idx + 1) }}</span>
                </div>
              </td>
              <td class="py-3 px-2 text-right" style="color:#475569;">1</td>
              <td class="py-3 px-2 text-right font-semibold" style="color:#1e293b;">{{ number_format($item->price, 2) }}</td>
            </tr>
            @endforeach
          @else
            {{-- No items relationship data; show the order as a single line --}}
            <tr style="border-bottom:1px solid #f1f5f9;">
              <td class="py-3 px-2" style="color:#94a3b8;">1</td>
              <td class="py-3 px-2">
                <div class="flex items-center gap-2">
                  <div style="width:36px;height:36px;border-radius:6px;background:rgba(99,102,241,0.06);display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-images" style="color:#6366f1;font-size:0.8rem;"></i>
                  </div>
                  <span style="color:#1e293b;font-weight:500;">
                    ชุดรูปภาพ {{ $order->event->name ?? '' }}
                    @if($order->package)
                      ({{ $order->package->name ?? 'แพ็กเกจ' }})
                    @endif
                  </span>
                </div>
              </td>
              <td class="py-3 px-2 text-right" style="color:#475569;">1</td>
              <td class="py-3 px-2 text-right font-semibold" style="color:#1e293b;">{{ number_format($subtotal, 2) }}</td>
            </tr>
          @endif
        </tbody>
      </table>
    </div>

    {{-- ═══ Totals ═══ --}}
    <div class="flex justify-end mb-8">
      <div style="width:280px;">
        <div class="flex justify-between py-2" style="border-bottom:1px solid #f1f5f9;">
          <span style="color:#64748b;font-size:0.88rem;">ยอดรวมสินค้า</span>
          <span style="color:#1e293b;font-weight:600;font-size:0.88rem;">฿{{ number_format($baseAmount, 2) }}</span>
        </div>
        @if($vatEnabled)
        <div class="flex justify-between py-2" style="border-bottom:1px solid #f1f5f9;">
          <span style="color:#64748b;font-size:0.88rem;">VAT ({{ number_format($vatRate, 0) }}%)</span>
          <span style="color:#1e293b;font-weight:600;font-size:0.88rem;">฿{{ number_format($vatAmount, 2) }}</span>
        </div>
        @endif
        <div class="flex justify-between py-3" style="border-bottom:2px solid #6366f1;">
          <span style="color:#1e293b;font-weight:700;font-size:1rem;">ยอดรวมทั้งสิ้น</span>
          <span style="color:#6366f1;font-weight:700;font-size:1.2rem;">฿{{ number_format($subtotal, 2) }}</span>
        </div>
      </div>
    </div>

    {{-- ═══ Thank You ═══ --}}
    <div class="text-center py-6" style="border-top:1px dashed #e2e8f0;">
      <div style="font-size:1rem;color:#6366f1;font-weight:600;margin-bottom:4px;">ขอบคุณที่ใช้บริการ</div>
      <div style="font-size:0.82rem;color:#94a3b8;">
        {{ $settings['company_name'] }} &mdash; ใบเสร็จนี้ออกโดยระบบอัตโนมัติ
      </div>
      @if($settings['company_phone'] || $settings['company_email'])
      <div style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">
        หากมีข้อสงสัย กรุณาติดต่อ
        @if($settings['company_phone']) {{ $settings['company_phone'] }} @endif
        @if($settings['company_phone'] && $settings['company_email']) | @endif
        @if($settings['company_email']) {{ $settings['company_email'] }} @endif
      </div>
      @endif
    </div>

  </div>
</div>

@endsection
