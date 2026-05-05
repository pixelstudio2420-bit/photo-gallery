@extends('layouts.admin')
@section('title', 'คำขอถอนเงิน — Withdrawal Requests')

@section('content')
{{-- ==============================================================
     Admin Withdrawal Queue
     --------------------------------------------------------------
     Pending photographer-initiated withdrawal requests. Lives
     beside the auto-payout admin tools at /admin/payments/payouts.
     ============================================================== --}}
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-arrow-down-circle text-emerald-500"></i>
        คำขอถอนเงิน (จากช่างภาพ)
    </h4>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.payments.withdrawals.settings') }}"
           class="text-xs text-indigo-600 font-semibold hover:underline">
            <i class="bi bi-sliders"></i> ตั้งค่ากฎ
        </a>
        <a href="{{ route('admin.payments.payouts') }}"
           class="text-xs px-3 py-1.5 bg-slate-600 text-white rounded-lg hover:bg-slate-700">
            <i class="bi bi-arrow-left mr-1"></i>กลับ
        </a>
    </div>
</div>

@if(session('success'))
  <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-sm px-4 py-3">
    <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
  </div>
@endif

{{-- KPI strip --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-[10px] uppercase tracking-wider font-bold text-amber-700">รอตรวจสอบ</p>
        <p class="text-2xl font-extrabold text-amber-700 tabular-nums mt-1">{{ $kpi['pending_count'] }}</p>
        <p class="text-[11px] text-amber-700/80 mt-1">รวม ฿{{ number_format($kpi['pending_amount'], 0) }}</p>
    </div>
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <p class="text-[10px] uppercase tracking-wider font-bold text-blue-700">อนุมัติแล้ว · รอโอน</p>
        <p class="text-2xl font-extrabold text-blue-700 tabular-nums mt-1">{{ $kpi['approved_count'] }}</p>
        <p class="text-[11px] text-blue-700/80 mt-1">รวม ฿{{ number_format($kpi['approved_amount'], 0) }}</p>
    </div>
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
        <p class="text-[10px] uppercase tracking-wider font-bold text-emerald-700">โอนแล้ว (เดือนนี้)</p>
        <p class="text-2xl font-extrabold text-emerald-700 tabular-nums mt-1">{{ $kpi['paid_month_count'] }}</p>
        <p class="text-[11px] text-emerald-700/80 mt-1">รวม ฿{{ number_format($kpi['paid_month_amount'], 0) }}</p>
    </div>
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
        <p class="text-[10px] uppercase tracking-wider font-bold text-slate-600">ทั้งหมด</p>
        <p class="text-2xl font-extrabold text-slate-700 tabular-nums mt-1">{{ $requests->total() }}</p>
        <p class="text-[11px] text-slate-500 mt-1">{{ ucfirst($status) }}</p>
    </div>
</div>

{{-- Filter chips --}}
<div class="mb-4 flex items-center gap-2 overflow-x-auto pb-1" style="scrollbar-width:none;">
    @foreach([
        'pending'   => ['รอตรวจสอบ',   'amber'],
        'approved'  => ['อนุมัติแล้ว',  'blue'],
        'paid'      => ['โอนแล้ว',     'emerald'],
        'rejected'  => ['ปฏิเสธ',      'rose'],
        'cancelled' => ['ยกเลิก',      'slate'],
        'all'       => ['ทั้งหมด',     'indigo'],
    ] as $key => $cfg)
        @php $isActive = $status === $key; @endphp
        <a href="{{ route('admin.payments.withdrawals.index', ['status' => $key]) }}"
           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition border-[1.5px]
                {{ $isActive
                    ? 'bg-' . $cfg[1] . '-500 text-white border-' . $cfg[1] . '-500 shadow'
                    : 'bg-white text-slate-600 border-slate-200 hover:border-' . $cfg[1] . '-300 hover:text-' . $cfg[1] . '-700' }}">
            {{ $cfg[0] }}
        </a>
    @endforeach
</div>

{{-- List --}}
<div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">ID</th>
                    <th class="px-4 py-3 text-left">ช่างภาพ</th>
                    <th class="px-4 py-3 text-right">ยอด</th>
                    <th class="px-4 py-3 text-left">วิธีรับ</th>
                    <th class="px-4 py-3 text-left">สถานะ</th>
                    <th class="px-4 py-3 text-left">ขอเมื่อ</th>
                    <th class="px-4 py-3 text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($requests as $r)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs">#{{ $r->id }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $r->photographer?->name ?? 'user #'.$r->photographer_id }}</div>
                            <div class="text-[10px] text-gray-400">{{ $r->photographer?->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums">
                            ฿{{ number_format($r->amount_thb, 2) }}
                            @if((float) $r->fee_thb > 0)
                                <div class="text-[10px] text-slate-500 font-normal">ค่าธรรมเนียม ฿{{ number_format($r->fee_thb, 0) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <div class="font-semibold">{{ $r->methodLabel() }}</div>
                            @if($r->method === 'bank_transfer' && !empty($r->method_details['account_number']))
                                <div class="text-[10px] text-gray-500 font-mono">{{ $r->method_details['bank_name'] ?? '' }} · {{ $r->method_details['account_number'] }}</div>
                            @elseif($r->method === 'promptpay' && !empty($r->method_details['promptpay_id']))
                                <div class="text-[10px] text-gray-500 font-mono">{{ $r->method_details['promptpay_id'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $color = $r->statusColor();
                                $badgeMap = [
                                    'amber'   => 'bg-amber-100 text-amber-700',
                                    'blue'    => 'bg-blue-100 text-blue-700',
                                    'emerald' => 'bg-emerald-100 text-emerald-700',
                                    'rose'    => 'bg-rose-100 text-rose-700',
                                    'gray'    => 'bg-gray-100 text-gray-600',
                                ];
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold {{ $badgeMap[$color] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $r->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $r->created_at?->format('d M Y') }}
                            <div class="text-[10px] text-gray-400">{{ $r->created_at?->format('H:i') }}</div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.payments.withdrawals.show', $r->id) }}"
                               class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                รายละเอียด <i class="bi bi-chevron-right"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-gray-500">
                        <i class="bi bi-inbox text-3xl text-gray-300 block mb-2"></i>
                        ยังไม่มีคำขอในหมวดนี้
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())
        <div class="px-4 py-4 border-t border-gray-100">
            {{ $requests->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
