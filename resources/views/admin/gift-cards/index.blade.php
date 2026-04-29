@extends('layouts.admin')
@section('title', 'Gift Cards')

@php
    $statusCls = [
        'active'   => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200',
        'redeemed' => 'bg-sky-500/15 text-sky-700 dark:text-sky-200',
        'expired'  => 'bg-amber-500/15 text-amber-700 dark:text-amber-200',
        'voided'   => 'bg-rose-500/15 text-rose-700 dark:text-rose-200',
    ];
@endphp

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-gift text-indigo-500"></i> Gift Cards
        <span class="text-xs font-normal text-gray-400 ml-2">/ บัตรของขวัญ</span>
    </h4>
    <a href="{{ route('admin.gift-cards.create') }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
        <i class="bi bi-plus-lg mr-1"></i>ออกบัตรใหม่
    </a>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif

{{-- KPI Cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">ยอดคงเหลือ (Liability)</div>
        <div class="text-2xl font-bold text-indigo-500">฿{{ number_format($kpis['liability_total'], 2) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">ที่ยังใช้ไม่หมดในระบบ</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">ออกแล้วทั้งหมด</div>
        <div class="text-2xl font-bold">฿{{ number_format($kpis['issued_total'], 2) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">{{ $kpis['total_cards'] }} ใบ</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">ถูกใช้แล้ว</div>
        <div class="text-2xl font-bold text-emerald-500">฿{{ number_format($kpis['redeemed_total'], 2) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">สถานะ</div>
        <div class="text-sm mt-1 space-x-1">
            <span class="px-1.5 py-0.5 rounded {{ $statusCls['active'] }}">Active {{ $kpis['active_cards'] }}</span>
            <span class="px-1.5 py-0.5 rounded {{ $statusCls['redeemed'] }}">Used {{ $kpis['redeemed_cards'] }}</span>
        </div>
        <div class="text-sm mt-1 space-x-1">
            <span class="px-1.5 py-0.5 rounded {{ $statusCls['expired'] }}">Exp {{ $kpis['expired_cards'] }}</span>
            <span class="px-1.5 py-0.5 rounded {{ $statusCls['voided'] }}">Void {{ $kpis['voided_cards'] }}</span>
        </div>
    </div>
</div>

{{-- Filter Bar --}}
<form method="GET" class="flex flex-wrap gap-2 mb-4 bg-white dark:bg-slate-800 rounded-xl p-3 border border-gray-100 dark:border-white/5">
    <input type="text" name="q" value="{{ $search }}" placeholder="ค้นรหัส / อีเมล / ชื่อ"
           class="flex-1 min-w-[200px] px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
    <select name="status" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
        <option value="">ทุกสถานะ</option>
        @foreach($statuses as $k => $v)
            <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $v }}</option>
        @endforeach
    </select>
    <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm"><i class="bi bi-search mr-1"></i>ค้นหา</button>
</form>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
            <tr>
                <th class="px-4 py-3">รหัสบัตร</th>
                <th class="px-4 py-3">คงเหลือ / หน้าบัตร</th>
                <th class="px-4 py-3">ผู้ซื้อ / ผู้รับ</th>
                <th class="px-4 py-3">สถานะ</th>
                <th class="px-4 py-3">หมดอายุ</th>
                <th class="px-4 py-3 text-right">ตรวจสอบ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @forelse($cards as $c)
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-mono font-semibold">{{ $c->code }}</div>
                        <div class="text-[10px] text-gray-400">{{ $sources[$c->source] ?? $c->source }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-bold">฿{{ number_format((float) $c->balance, 2) }}</div>
                        <div class="text-[11px] text-gray-400">จาก ฿{{ number_format((float) $c->initial_amount, 2) }}</div>
                    </td>
                    <td class="px-4 py-3 text-xs">
                        <div>{{ $c->purchaser_email ?? '—' }}</div>
                        @if($c->recipient_email)
                            <div class="text-gray-400"><i class="bi bi-arrow-right-short"></i>{{ $c->recipient_email }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded-full text-[11px] {{ $statusCls[$c->status] ?? 'bg-gray-200' }}">
                            {{ $statuses[$c->status] ?? $c->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ optional($c->expires_at)->format('d M Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.gift-cards.show', $c) }}" class="px-3 py-1 text-xs border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                            <i class="bi bi-eye mr-1"></i>ดู
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">ไม่มีบัตรในระบบ</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($cards->hasPages())
        <div class="p-3 border-t border-gray-100 dark:border-white/5">{{ $cards->links() }}</div>
    @endif
</div>
@endsection
