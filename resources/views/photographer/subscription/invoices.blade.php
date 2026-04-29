@extends('layouts.photographer')

@section('title', 'ใบเสร็จสมัครสมาชิก')

@php
  use App\Models\SubscriptionInvoice;
@endphp

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-receipt',
  'eyebrow'  => 'การเงิน',
  'title'    => 'ใบเสร็จสมัครสมาชิก',
  'subtitle' => 'ประวัติการชำระเงินค่าแผน · ดาวน์โหลดใบเสร็จ',
  'actions'  => '<a href="'.route('photographer.subscription.index').'" class="pg-btn-ghost"><i class="bi bi-arrow-left"></i> กลับ</a>',
])

@if($invoices->isEmpty())
  <div class="pg-card pg-anim d1">
    <div class="pg-empty">
      <div class="pg-empty-icon"><i class="bi bi-receipt"></i></div>
      <p class="font-medium">ยังไม่มีใบเสร็จในระบบ</p>
      <a href="{{ route('photographer.subscription.plans') }}" class="pg-btn-primary mt-4">
        <i class="bi bi-stars"></i> เลือกแผนสมัครสมาชิก
      </a>
    </div>
  </div>
@else
  <div class="pg-card overflow-hidden pg-anim d1">
    <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
      <table class="pg-table">
        <thead>
          <tr>
            <th>เลขที่</th>
            <th>แผน</th>
            <th>ช่วงเวลา</th>
            <th class="text-end">ยอด</th>
            <th>สถานะ</th>
            <th class="text-end">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          @foreach($invoices as $inv)
            <tr>
              <td class="is-mono text-gray-700">{{ $inv->invoice_number }}</td>
              <td class="font-medium">{{ $inv->subscription?->plan?->name ?? '—' }}</td>
              <td class="text-gray-600 text-xs">
                @if($inv->period_start)
                  {{ $inv->period_start->format('d M Y') }} – {{ $inv->period_end?->format('d M Y') ?? '—' }}
                @else
                  <span class="text-gray-400">—</span>
                @endif
              </td>
              <td class="text-end is-mono font-bold">฿{{ number_format((float) $inv->amount_thb, 2) }}</td>
              <td>
                @php
                  $invoicePill = match($inv->status) {
                    SubscriptionInvoice::STATUS_PAID     => ['pg-pill--green', 'ชำระแล้ว'],
                    SubscriptionInvoice::STATUS_PENDING  => ['pg-pill--amber', 'รอชำระ'],
                    SubscriptionInvoice::STATUS_FAILED   => ['pg-pill--rose',  'ล้มเหลว'],
                    SubscriptionInvoice::STATUS_REFUNDED => ['pg-pill--blue',  'คืนเงิน'],
                    SubscriptionInvoice::STATUS_VOIDED   => ['pg-pill--gray',  'ยกเลิก'],
                    default                              => ['pg-pill--gray',  $inv->status],
                  };
                @endphp
                <span class="pg-pill {{ $invoicePill[0] }}">{{ $invoicePill[1] }}</span>
              </td>
              <td class="text-end">
                @if($inv->order && $inv->status === SubscriptionInvoice::STATUS_PENDING)
                  <a href="{{ route('payment.checkout', ['order' => $inv->order_id]) }}" class="pg-btn-primary text-xs">
                    <i class="bi bi-credit-card"></i> ชำระเงิน
                  </a>
                @elseif($inv->order)
                  <a href="{{ url('/orders/' . $inv->order_id) }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-bold no-underline">
                    ดูคำสั่งซื้อ
                  </a>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="pg-card-footer">
      {{ $invoices->links() }}
    </div>
  </div>
@endif
@endsection
