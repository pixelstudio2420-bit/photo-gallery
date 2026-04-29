@extends('layouts.admin')
@section('title', 'Credit Packages')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-boxes text-indigo-500"></i> Credit Packages
        <span class="text-xs font-normal text-gray-400 ml-2">/ แพ็คเก็จเครดิตอัปโหลด</span>
    </h4>
    <a href="{{ route('admin.credits.packages.create') }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
        <i class="bi bi-plus-lg mr-1"></i>สร้างแพ็คเก็จใหม่
    </a>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 text-rose-800 dark:text-rose-200 rounded-xl p-3 mb-4 text-sm">{{ session('error') }}</div>
@endif

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs uppercase tracking-wider text-gray-500">
            <tr>
                <th class="px-3 py-2 text-left">#</th>
                <th class="px-3 py-2 text-left">ชื่อ / Code</th>
                <th class="px-3 py-2 text-right">เครดิต</th>
                <th class="px-3 py-2 text-right">ราคา (฿)</th>
                <th class="px-3 py-2 text-right">บาท/เครดิต</th>
                <th class="px-3 py-2 text-left">อายุ</th>
                <th class="px-3 py-2 text-left">Badge</th>
                <th class="px-3 py-2 text-center">สถานะ</th>
                <th class="px-3 py-2 text-right">เครื่องมือ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @forelse($packages as $p)
                @php $perCredit = $p->credits > 0 ? $p->price_thb / $p->credits : 0; @endphp
                <tr class="{{ !$p->is_active ? 'opacity-60' : '' }}">
                    <td class="px-3 py-2 text-gray-400 text-xs">{{ $p->sort_order }}</td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded" style="background:{{ $p->color_hex ?: '#6366f1' }}"></span>
                            <div>
                                <div class="font-medium">{{ $p->name }}</div>
                                <div class="text-xs text-gray-400 font-mono">{{ $p->code }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2 text-right font-semibold">{{ number_format($p->credits) }}</td>
                    <td class="px-3 py-2 text-right">฿{{ number_format($p->price_thb, 0) }}</td>
                    <td class="px-3 py-2 text-right text-xs text-gray-500">฿{{ number_format($perCredit, 2) }}</td>
                    <td class="px-3 py-2 text-xs">
                        {{ $p->validity_days > 0 ? $p->validity_days . ' วัน' : 'ไม่หมดอายุ' }}
                    </td>
                    <td class="px-3 py-2">
                        @if($p->badge)
                            <span class="px-2 py-0.5 rounded text-[11px] text-white" style="background:{{ $p->color_hex ?: '#6366f1' }}">{{ $p->badge }}</span>
                        @else
                            <span class="text-gray-400 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-center">
                        @if($p->is_active)
                            <span class="px-2 py-0.5 rounded text-[11px] bg-emerald-500/15 text-emerald-700 dark:text-emerald-200">Active</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[11px] bg-gray-500/15 text-gray-500">Archived</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                        <a href="{{ route('admin.credits.packages.edit', $p) }}" class="text-indigo-500 hover:underline text-sm">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @if($p->is_active)
                            <form method="POST" action="{{ route('admin.credits.packages.destroy', $p) }}" class="inline"
                                  onsubmit="return confirm('ปิดการขายแพ็คเก็จ {{ $p->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-rose-500 hover:underline text-sm ml-2"><i class="bi bi-archive"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400">ยังไม่มีแพ็คเก็จ — กดปุ่ม "สร้างแพ็คเก็จใหม่" ด้านบน</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
