@extends('layouts.admin')
@section('title', 'Subscriptions Overview')

@php
  use App\Models\PhotographerSubscription;
@endphp

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-stars text-indigo-500"></i> Subscriptions
        <span class="text-xs font-normal text-gray-400 ml-2">/ ภาพรวมแผนสมัครสมาชิก</span>
    </h4>
    <div class="flex gap-2">
        <a href="{{ route('admin.subscriptions.plans') }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
            <i class="bi bi-boxes mr-1"></i>จัดการแผน
        </a>
        <a href="{{ route('admin.subscriptions.invoices') }}" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-sm">
            <i class="bi bi-receipt mr-1"></i>ใบเสร็จทั้งหมด
        </a>
    </div>
</div>

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif

{{-- KPI Cards --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">สมาชิกที่ใช้งานอยู่</div>
        <div class="text-2xl font-bold text-indigo-500">{{ number_format($kpis['active_subscribers']) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">Active + Grace</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">MRR (รายเดือน)</div>
        <div class="text-2xl font-bold text-emerald-500">฿{{ number_format($kpis['mrr'], 0) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">รายรับที่คงที่ต่อเดือน</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">รายปี (Prepaid)</div>
        <div class="text-2xl font-bold">฿{{ number_format($kpis['annual_prepaid'], 0) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">ยอดจ่ายรายปีทั้งหมด</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">เก็บได้ 30 วันหลัง</div>
        <div class="text-2xl font-bold">฿{{ number_format($kpis['last30_paid'], 0) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">ยอดที่ชำระแล้ว</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">อยู่ในช่วงผ่อนผัน</div>
        <div class="text-2xl font-bold text-amber-500">{{ number_format($kpis['in_grace']) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">รอชำระ — ต้อง follow-up</div>
    </div>
</div>

{{-- Plan breakdown --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5 mb-6">
    <h5 class="font-semibold mb-3">การกระจายของแผน</h5>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @foreach($planCounts as $p)
            <div class="rounded-lg border border-gray-200 dark:border-white/10 p-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold" style="color: {{ $p->color_hex ?: '#6366f1' }}">{{ $p->name }}</span>
                    @if(!$p->is_active)
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">ปิด</span>
                    @endif
                </div>
                <div class="text-xl font-bold mt-1">{{ $p->subscribers }}</div>
                <div class="text-[11px] text-gray-400">{{ number_format((float) $p->price_thb, 0) }} THB/เดือน</div>
            </div>
        @endforeach
    </div>
</div>

{{-- Active subscribers table --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
        <h5 class="font-semibold">สมาชิกที่ใช้งานอยู่</h5>
        <span class="text-xs text-gray-400">{{ $activeSubs->total() }} รายการ</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">ช่างภาพ</th>
                    <th class="px-3 py-3 text-left">แผน</th>
                    <th class="px-3 py-3 text-left">สถานะ</th>
                    <th class="px-3 py-3 text-left" title="AI ใช้แล้ว / โควตาต่อเดือน — เพิ่มเมื่อ index รูป + buyer search">AI/เดือน</th>
                    <th class="px-3 py-3 text-center" title="LINE photo delivery / push notify — gated โดย ai_features='line_notify'">LINE</th>
                    <th class="px-3 py-3 text-left">เริ่ม</th>
                    <th class="px-3 py-3 text-left" title="วันที่หมดรอบ + จำนวนวันที่เหลือ">หมดรอบ</th>
                    <th class="px-4 py-3 text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($activeSubs as $s)
                    @php
                        $aiUsed = (int) ($aiUsageByUserId[$s->photographer_id] ?? 0);
                        $aiCap  = (int) ($s->plan?->monthly_ai_credits ?? 0);
                        $aiPct  = $aiCap > 0 ? min(100, round(($aiUsed / $aiCap) * 100)) : 0;
                        $hasLine = in_array('line_notify', (array) ($s->plan?->ai_features ?? []), true);
                        $isExpired = $s->current_period_end && $s->current_period_end->isPast();
                        $daysLeft  = $s->current_period_end ? max(0, (int) now()->diffInDays($s->current_period_end, false)) : null;
                        $expiringSoon = $daysLeft !== null && $daysLeft <= 3 && !$isExpired;
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-900/40">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $s->photographer?->name ?? 'user #'.$s->photographer_id }}</div>
                            <div class="text-[11px] text-gray-400">{{ $s->photographer?->email }}</div>
                        </td>
                        <td class="px-3 py-3">
                            <span class="font-semibold" style="color: {{ $s->plan?->color_hex ?: '#6366f1' }}">
                                {{ $s->plan?->name ?? '—' }}
                            </span>
                            <div class="text-[11px] text-gray-400">{{ number_format((int) ($s->plan?->storage_bytes ?? 0) / (1024 ** 3), 0) }} GB</div>
                        </td>
                        <td class="px-3 py-3">
                            @php
                                $badge = match($s->status) {
                                    PhotographerSubscription::STATUS_ACTIVE  => ['bg-emerald-100 text-emerald-700', 'active'],
                                    PhotographerSubscription::STATUS_GRACE   => ['bg-rose-100 text-rose-700',       'grace'],
                                    PhotographerSubscription::STATUS_PENDING => ['bg-amber-100 text-amber-700',     'pending'],
                                    default                                  => ['bg-gray-100 text-gray-700',       $s->status],
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $badge[0] }}">{{ $badge[1] }}</span>
                            @if($isExpired)
                                <div class="text-[10px] text-rose-600 mt-1 font-semibold">⚠ หมดอายุแล้ว</div>
                            @elseif($s->cancel_at_period_end)
                                <div class="text-[10px] text-amber-600 mt-1">จะไม่ต่ออายุ</div>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            <div class="text-xs font-semibold {{ $aiPct >= 100 ? 'text-rose-600' : ($aiPct >= 80 ? 'text-amber-600' : 'text-gray-700 dark:text-gray-300') }}">
                                {{ number_format($aiUsed) }}<span class="text-gray-400"> / </span>{{ $aiCap > 0 ? number_format($aiCap) : '∞' }}
                            </div>
                            @if($aiCap > 0)
                                <div class="mt-1 w-24 h-1.5 bg-gray-100 dark:bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-full {{ $aiPct >= 100 ? 'bg-rose-500' : ($aiPct >= 80 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                         style="width: {{ min(100, $aiPct) }}%"></div>
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-center">
                            @if($hasLine)
                                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] bg-emerald-100 text-emerald-700" title="LINE delivery enabled by plan">
                                    <i class="bi bi-line"></i> ON
                                </span>
                            @else
                                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-400" title="Plan does not include LINE delivery">OFF</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-gray-500 text-xs">{{ $s->started_at?->format('d M Y') ?? '—' }}</td>
                        <td class="px-3 py-3 text-xs">
                            <div class="text-gray-500">{{ $s->current_period_end?->format('d M Y') ?? '—' }}</div>
                            @if($isExpired)
                                <div class="text-[10px] text-rose-600 font-semibold">หมดอายุแล้ว</div>
                            @elseif($daysLeft !== null)
                                <div class="text-[10px] {{ $expiringSoon ? 'text-amber-600 font-semibold' : 'text-gray-400' }}">
                                    เหลือ {{ $daysLeft }} วัน
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.subscriptions.show', $s) }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                รายละเอียด <i class="bi bi-chevron-right"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-8 text-center text-gray-500">ยังไม่มีสมาชิก</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-4 border-t border-gray-100 dark:border-white/5">
        {{ $activeSubs->links() }}
    </div>
</div>
@endsection
