@extends('layouts.admin')
@section('title', 'Photographer Credit Balances')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-people text-indigo-500"></i> Photographer Credit Balances
        <span class="text-xs font-normal text-gray-400 ml-2">/ ยอดเครดิตของช่างภาพ</span>
    </h4>
    <a href="{{ route('admin.credits.index') }}" class="text-sm text-gray-500 hover:underline">
        <i class="bi bi-arrow-left"></i> กลับแผงหลัก
    </a>
</div>

<form method="GET" action="{{ route('admin.credits.photographers.index') }}" class="mb-4 flex gap-2">
    <input type="text" name="q" value="{{ $search }}" placeholder="ค้นหาชื่อ / อีเมล / display name"
           class="flex-1 max-w-md rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
    <button type="submit" class="px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-sm">ค้นหา</button>
    @if($search)
        <a href="{{ route('admin.credits.photographers.index') }}" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">ล้าง</a>
    @endif
</form>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs uppercase tracking-wider text-gray-500">
            <tr>
                <th class="px-3 py-2 text-left">ช่างภาพ</th>
                <th class="px-3 py-2 text-left">Tier</th>
                <th class="px-3 py-2 text-left">โหมด</th>
                <th class="px-3 py-2 text-right">เครดิตคงเหลือ</th>
                <th class="px-3 py-2 text-right">ใช้ Storage</th>
                <th class="px-3 py-2 text-left">อัปเดต</th>
                <th class="px-3 py-2 text-right"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @forelse($profiles as $p)
                <tr>
                    <td class="px-3 py-2">
                        <div class="font-medium">{{ $p->user_name ?? $p->display_name }}</div>
                        <div class="text-[11px] text-gray-400">{{ $p->user_email }}</div>
                    </td>
                    <td class="px-3 py-2 text-xs">
                        <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-slate-700">{{ $p->tier }}</span>
                    </td>
                    <td class="px-3 py-2">
                        @if($p->billing_mode === 'credits')
                            <span class="px-2 py-0.5 rounded text-[11px] bg-emerald-500/15 text-emerald-700 dark:text-emerald-200">credits</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[11px] bg-gray-500/15 text-gray-500">commission</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right font-semibold text-indigo-500">
                        {{ number_format($p->credits_balance_cached ?? 0) }}
                    </td>
                    <td class="px-3 py-2 text-right text-xs text-gray-500">
                        {{ number_format(($p->storage_used_bytes ?? 0) / 1024 / 1024, 1) }} MB
                    </td>
                    <td class="px-3 py-2 text-xs text-gray-400">
                        {{ $p->credits_last_recalc_at ? \Illuminate\Support\Carbon::parse($p->credits_last_recalc_at)->diffForHumans() : '—' }}
                    </td>
                    <td class="px-3 py-2 text-right">
                        <a href="{{ route('admin.credits.photographers.show', ['photographer' => $p->id]) }}"
                           class="text-indigo-500 hover:underline text-xs">
                            จัดการ <i class="bi bi-arrow-right"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">ไม่พบช่างภาพที่ตรงกับการค้นหา</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $profiles->links() }}</div>
@endsection
