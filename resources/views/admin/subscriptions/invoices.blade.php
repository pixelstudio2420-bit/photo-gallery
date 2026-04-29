@extends('layouts.admin')
@section('title', 'Subscription Invoices')

@php use App\Models\SubscriptionInvoice; @endphp

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-receipt text-indigo-500"></i> Subscription Invoices
        <span class="text-xs font-normal text-gray-400 ml-2">/ ใบเสร็จของระบบสมัครสมาชิกทั้งหมด</span>
    </h4>
    <a href="{{ route('admin.subscriptions.index') }}" class="px-3 py-1.5 bg-slate-600 hover:bg-slate-700 text-white rounded-lg text-sm">
        <i class="bi bi-arrow-left mr-1"></i>กลับ
    </a>
</div>

{{-- Filters --}}
<div class="mb-4 flex gap-2 flex-wrap">
    @php
        $statusOpts = [
            '' => 'ทั้งหมด',
            SubscriptionInvoice::STATUS_PAID     => 'ชำระแล้ว',
            SubscriptionInvoice::STATUS_PENDING  => 'รอชำระ',
            SubscriptionInvoice::STATUS_FAILED   => 'ล้มเหลว',
            SubscriptionInvoice::STATUS_REFUNDED => 'คืนเงิน',
            SubscriptionInvoice::STATUS_VOIDED   => 'ยกเลิก',
        ];
    @endphp
    @foreach($statusOpts as $val => $label)
        <a href="{{ route('admin.subscriptions.invoices', $val === '' ? [] : ['status' => $val]) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-medium
                  {{ $currentStatus === $val || ($currentStatus === null && $val === '') ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">เลขที่</th>
                    <th class="px-5 py-3 text-left">ช่างภาพ</th>
                    <th class="px-5 py-3 text-left">แผน</th>
                    <th class="px-5 py-3 text-left">ช่วงเวลา</th>
                    <th class="px-5 py-3 text-right">ยอด</th>
                    <th class="px-5 py-3 text-left">สถานะ</th>
                    <th class="px-5 py-3 text-left">ชำระเมื่อ</th>
                    <th class="px-5 py-3 text-right">Order</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($invoices as $inv)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-900/40">
                        <td class="px-5 py-3 font-mono text-xs">{{ $inv->invoice_number }}</td>
                        <td class="px-5 py-3">
                            <div class="font-medium">{{ $inv->subscription?->photographer?->name ?? 'user #'.$inv->photographer_id }}</div>
                            <div class="text-[11px] text-gray-400">{{ $inv->subscription?->photographer?->email }}</div>
                        </td>
                        <td class="px-5 py-3 text-xs">{{ $inv->subscription?->plan?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500">
                            @if($inv->period_start)
                                {{ $inv->period_start->format('d M Y') }} – {{ $inv->period_end?->format('d M Y') ?? '—' }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right font-medium">฿{{ number_format((float) $inv->amount_thb, 2) }}</td>
                        <td class="px-5 py-3">
                            @php
                                $badge = match($inv->status) {
                                    SubscriptionInvoice::STATUS_PAID     => ['bg-emerald-100 text-emerald-700', 'paid'],
                                    SubscriptionInvoice::STATUS_PENDING  => ['bg-amber-100 text-amber-700',   'pending'],
                                    SubscriptionInvoice::STATUS_FAILED   => ['bg-rose-100 text-rose-700',     'failed'],
                                    SubscriptionInvoice::STATUS_REFUNDED => ['bg-sky-100 text-sky-700',       'refunded'],
                                    SubscriptionInvoice::STATUS_VOIDED   => ['bg-gray-100 text-gray-600',     'voided'],
                                    default                              => ['bg-gray-100 text-gray-700',     $inv->status],
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $badge[0] }}">{{ $badge[1] }}</span>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $inv->paid_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            @if($inv->order_id)
                                <a href="{{ url('/admin/orders/' . $inv->order_id) }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                    #{{ $inv->order_id }}
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-8 text-center text-gray-500">ไม่มีใบเสร็จ</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-4 border-t border-gray-100 dark:border-white/5">
        {{ $invoices->links() }}
    </div>
</div>
@endsection
