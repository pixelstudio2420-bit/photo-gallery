@extends('layouts.admin')
@section('title', 'Subscription Plans')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-boxes text-indigo-500"></i> Subscription Plans
        <span class="text-xs font-normal text-gray-400 ml-2">/ แผนที่เสนอขายให้ช่างภาพ</span>
    </h4>
    <a href="{{ route('admin.subscriptions.index') }}" class="px-3 py-1.5 bg-slate-600 hover:bg-slate-700 text-white rounded-lg text-sm">
        <i class="bi bi-arrow-left mr-1"></i>กลับ
    </a>
</div>

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">รหัส / ชื่อ</th>
                    <th class="px-5 py-3 text-right">พื้นที่</th>
                    <th class="px-5 py-3 text-right">ค่าคอม %</th>
                    <th class="px-5 py-3 text-right">ราคา/เดือน</th>
                    <th class="px-5 py-3 text-right">ราคา/ปี</th>
                    <th class="px-5 py-3 text-center">AI</th>
                    <th class="px-5 py-3 text-center">Active</th>
                    <th class="px-5 py-3 text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach($plans as $p)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-900/40">
                        <td class="px-5 py-3">
                            <div class="font-semibold" style="color: {{ $p->color_hex ?: '#6366f1' }}">
                                {{ $p->name }}
                                @if($p->badge)
                                    <span class="ml-1.5 inline-block px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px]">{{ $p->badge }}</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-gray-400 font-mono">{{ $p->code }}</div>
                        </td>
                        <td class="px-5 py-3 text-right">{{ number_format($p->storage_gb, 0) }} GB</td>
                        <td class="px-5 py-3 text-right">{{ (int) $p->commission_pct }}%</td>
                        <td class="px-5 py-3 text-right">{{ $p->isFree() ? '—' : '฿'.number_format((float) $p->price_thb, 0) }}</td>
                        <td class="px-5 py-3 text-right">{{ $p->price_annual_thb ? '฿'.number_format((float) $p->price_annual_thb, 0) : '—' }}</td>
                        <td class="px-5 py-3 text-center">{{ count($p->ai_features ?? []) }}</td>
                        <td class="px-5 py-3 text-center">
                            @if($p->is_active)
                                <span class="inline-block px-2 py-0.5 rounded text-[11px] bg-emerald-100 text-emerald-700">ON</span>
                            @else
                                <span class="inline-block px-2 py-0.5 rounded text-[11px] bg-gray-100 text-gray-500">OFF</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.subscriptions.plans.edit', $p) }}"
                               class="text-xs text-indigo-600 font-medium hover:underline mr-3">
                                <i class="bi bi-pencil"></i> แก้ไข
                            </a>
                            <form method="POST" action="{{ route('admin.subscriptions.plans.toggle', $p) }}" class="inline">
                                @csrf
                                <button class="text-xs {{ $p->is_active ? 'text-rose-600' : 'text-emerald-600' }} font-medium hover:underline">
                                    {{ $p->is_active ? 'ปิด' : 'เปิด' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 flex items-center justify-between">
  <p class="text-xs text-gray-500">
    <i class="bi bi-info-circle"></i>
    คลิก <strong>"แก้ไข"</strong> เพื่อปรับราคา / พื้นที่ / ฟีเจอร์ AI / ค่าคอม / ที่นั่งทีม / cap อีเวนต์ ของแต่ละแผน
  </p>
  <a href="{{ route('admin.features.index') }}" class="text-xs text-indigo-600 font-medium hover:underline">
    <i class="bi bi-toggles mr-1"></i> ไปหน้า Feature Flags →
  </a>
</div>
@endsection
