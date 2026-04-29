@extends('layouts.app')

@section('title', 'ใบเสร็จของฉัน — คลาวด์')

@section('content')
<div class="max-w-5xl mx-auto py-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-900">
      <i class="bi bi-receipt mr-1 text-indigo-500"></i> ใบเสร็จของฉัน
    </h1>
    <a href="{{ route('storage.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
        <tr>
          <th class="px-4 py-3">เลขใบเสร็จ</th>
          <th class="px-4 py-3">แผน</th>
          <th class="px-4 py-3">ช่วงเวลา</th>
          <th class="px-4 py-3 text-right">ยอดเงิน</th>
          <th class="px-4 py-3">สถานะ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($invoices as $inv)
          @php
            $badge = match($inv->status) {
              'paid'     => 'bg-emerald-50 text-emerald-700 border-emerald-200',
              'pending'  => 'bg-amber-50 text-amber-700 border-amber-200',
              'failed'   => 'bg-rose-50 text-rose-700 border-rose-200',
              'refunded' => 'bg-gray-100 text-gray-700 border-gray-200',
              default    => 'bg-gray-100 text-gray-700 border-gray-200',
            };
          @endphp
          <tr>
            <td class="px-4 py-3 font-mono text-xs text-gray-900">{{ $inv->invoice_number }}</td>
            <td class="px-4 py-3">{{ $inv->subscription->plan->name ?? '-' }}</td>
            <td class="px-4 py-3 text-xs text-gray-600">
              {{ optional($inv->period_start)->format('d/m/Y') }} –
              {{ optional($inv->period_end)->format('d/m/Y') }}
            </td>
            <td class="px-4 py-3 text-right font-semibold">฿{{ number_format((float) $inv->amount_thb, 2) }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded border text-[11px] font-semibold {{ $badge }}">
                {{ strtoupper($inv->status) }}
              </span>
              @if($inv->status === 'pending' && $inv->order_id)
                <a href="{{ route('payment.checkout', ['order' => $inv->order_id]) }}"
                   class="ml-2 text-xs text-indigo-600 hover:underline">ชำระเงิน</a>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">
              ยังไม่มีใบเสร็จ
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-4">
    {{ $invoices->links() }}
  </div>
</div>
@endsection
