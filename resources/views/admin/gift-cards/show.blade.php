@extends('layouts.admin')
@section('title', 'Gift Card ' . $gc->code)

@php
    $statusCls = [
        'active'   => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200',
        'redeemed' => 'bg-sky-500/15 text-sky-700 dark:text-sky-200',
        'expired'  => 'bg-amber-500/15 text-amber-700 dark:text-amber-200',
        'voided'   => 'bg-rose-500/15 text-rose-700 dark:text-rose-200',
    ];
    $typeCls = [
        'issue'  => 'text-indigo-500',
        'redeem' => 'text-emerald-500',
        'refund' => 'text-sky-500',
        'adjust' => 'text-amber-500',
        'expire' => 'text-gray-500',
        'void'   => 'text-rose-500',
    ];
@endphp

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-gift text-indigo-500"></i> <span class="font-mono">{{ $gc->code }}</span>
    </h4>
    <a href="{{ route('admin.gift-cards.index') }}" class="text-sm text-gray-500 hover:text-indigo-500">
        <i class="bi bi-arrow-left mr-1"></i>กลับ
    </a>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    {{-- Main details --}}
    <div class="md:col-span-2 bg-white dark:bg-slate-800 rounded-xl p-6 border border-gray-100 dark:border-white/5 space-y-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <div class="text-gray-500">ยอดคงเหลือ</div>
                <div class="text-3xl font-bold text-indigo-500">฿{{ number_format((float) $gc->balance, 2) }}</div>
                <div class="text-xs text-gray-400">จาก ฿{{ number_format((float) $gc->initial_amount, 2) }} · {{ $gc->currency }}</div>
            </div>
            <div>
                <div class="text-gray-500">สถานะ</div>
                <span class="inline-block mt-1 px-3 py-1 rounded-full text-sm {{ $statusCls[$gc->status] ?? 'bg-gray-200' }}">
                    {{ \App\Models\GiftCard::statuses()[$gc->status] ?? $gc->status }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm pt-3 border-t border-gray-100 dark:border-white/5">
            <div>
                <div class="text-gray-500">ที่มา</div>
                <div>{{ \App\Models\GiftCard::sources()[$gc->source] ?? $gc->source }}</div>
            </div>
            <div>
                <div class="text-gray-500">หมดอายุ</div>
                <div>{{ optional($gc->expires_at)->format('d M Y H:i') ?? '—' }}</div>
            </div>
            <div>
                <div class="text-gray-500">ผู้ซื้อ</div>
                <div>{{ $gc->purchaser_name ?? '—' }}</div>
                <div class="text-xs text-gray-400">{{ $gc->purchaser_email ?? '—' }}</div>
            </div>
            <div>
                <div class="text-gray-500">ผู้รับ</div>
                <div>{{ $gc->recipient_name ?? '—' }}</div>
                <div class="text-xs text-gray-400">{{ $gc->recipient_email ?? '—' }}</div>
            </div>
        </div>

        @if($gc->personal_message)
            <div class="pt-3 border-t border-gray-100 dark:border-white/5">
                <div class="text-sm text-gray-500 mb-1">ข้อความส่วนตัว</div>
                <div class="text-sm bg-gray-50 dark:bg-slate-900 p-3 rounded italic">"{{ $gc->personal_message }}"</div>
            </div>
        @endif

        @if($gc->admin_note)
            <div class="pt-3 border-t border-gray-100 dark:border-white/5">
                <div class="text-sm text-gray-500 mb-1">Admin note</div>
                <div class="text-sm bg-amber-50 dark:bg-amber-900/20 p-3 rounded">{{ $gc->admin_note }}</div>
            </div>
        @endif

        {{-- Transactions --}}
        <div class="pt-3 border-t border-gray-100 dark:border-white/5">
            <div class="text-sm font-semibold text-gray-500 mb-2">ประวัติรายการ</div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="py-2">เวลา</th>
                            <th class="py-2">ประเภท</th>
                            <th class="py-2 text-right">จำนวน</th>
                            <th class="py-2 text-right">คงเหลือ</th>
                            <th class="py-2">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($gc->transactions as $t)
                            <tr>
                                <td class="py-2 text-gray-500">{{ $t->created_at->format('d M H:i') }}</td>
                                <td class="py-2 {{ $typeCls[$t->type] ?? '' }} font-semibold uppercase">{{ $t->type }}</td>
                                <td class="py-2 text-right font-mono {{ (float)$t->amount < 0 ? 'text-rose-500' : 'text-emerald-500' }}">
                                    {{ number_format((float) $t->amount, 2) }}
                                </td>
                                <td class="py-2 text-right font-mono">{{ number_format((float) $t->balance_after, 2) }}</td>
                                <td class="py-2 text-gray-500">{{ $t->note ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-gray-400">ไม่มีรายการ</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Actions column --}}
    <div class="space-y-3">
        @if($gc->status !== 'voided')
            <form action="{{ route('admin.gift-cards.adjust', $gc) }}" method="POST"
                  class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5 space-y-2">
                @csrf
                <div class="text-sm font-semibold">ปรับยอดคงเหลือ</div>
                <input type="number" name="delta" step="0.01" placeholder="+500 หรือ -200" required
                       class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                <input type="text" name="note" placeholder="หมายเหตุ"
                       class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                <button class="w-full px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm">
                    <i class="bi bi-sliders mr-1"></i>บันทึก
                </button>
            </form>

            <form action="{{ route('admin.gift-cards.void', $gc) }}" method="POST"
                  onsubmit="return confirm('ยกเลิกบัตรนี้? การกระทำนี้ไม่สามารถย้อนกลับได้')"
                  class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5 space-y-2">
                @csrf
                <div class="text-sm font-semibold text-rose-500">ยกเลิกบัตร</div>
                <input type="text" name="reason" placeholder="เหตุผล"
                       class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                <button class="w-full px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm">
                    <i class="bi bi-x-circle mr-1"></i>Void
                </button>
            </form>
        @else
            <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 rounded-xl p-4 text-sm text-rose-700 dark:text-rose-200">
                บัตรนี้ถูกยกเลิกแล้ว
            </div>
        @endif
    </div>
</div>
@endsection
