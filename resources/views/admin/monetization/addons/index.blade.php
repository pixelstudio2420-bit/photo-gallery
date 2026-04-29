@extends('layouts.admin')

@section('title', 'Addon Catalog · Monetization')

@php
    /** Tone styles dodging legacy darkmode.css overrides — same pattern
     *  used in admin/orders/show.blade.php. inline rgba bg + text colour
     *  per mode keeps badges visible in both light + dark themes. */
    $toneClasses = [
        'emerald' => 'text-emerald-700 dark:text-emerald-300 ring-emerald-300/50',
        'amber'   => 'text-amber-700   dark:text-amber-300   ring-amber-300/50',
        'rose'    => 'text-rose-700    dark:text-rose-300    ring-rose-300/50',
        'slate'   => 'text-slate-700   dark:text-slate-300   ring-slate-300/50',
        'indigo'  => 'text-indigo-700  dark:text-indigo-300  ring-indigo-300/50',
    ];
    $toneBg = [
        'emerald' => 'rgba(16,185,129,0.15)',
        'amber'   => 'rgba(245,158,11,0.18)',
        'rose'    => 'rgba(244, 63, 94,0.18)',
        'slate'   => 'rgba(100,116,139,0.18)',
        'indigo'  => 'rgba(99,102,241,0.15)',
    ];

    $categoryTone = [
        'promotion'  => 'indigo',
        'storage'    => 'emerald',
        'ai_credits' => 'amber',
        'branding'   => 'emerald',
        'priority'   => 'amber',
    ];
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.monetization.dashboard') }}"
               class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-indigo-600 mb-2">
                <i class="bi bi-arrow-left"></i> กลับ Monetization
            </a>
            <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                🛒 Addon Catalog
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                จัดการรายการบริการเสริมในร้านช่างภาพ — เพิ่ม/แก้/ลบ + เปิด-ปิด
            </p>
        </div>
        <a href="{{ route('admin.monetization.addons.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm transition">
            <i class="bi bi-plus-lg"></i> เพิ่ม Addon ใหม่
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 text-emerald-700 px-4 py-3 text-sm">
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">รายการทั้งหมด</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ $stats['total'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">เปิดขาย</div>
            <div class="text-2xl font-extrabold text-emerald-600">{{ $stats['active'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">ปิดอยู่</div>
            <div class="text-2xl font-extrabold text-slate-400">{{ $stats['inactive'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">หมวดหมู่</div>
            <div class="text-2xl font-extrabold text-indigo-600">{{ $stats['categories'] }}</div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="ค้นหา label/SKU"
                   class="rounded-lg border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm">
            <select name="category"
                    class="rounded-lg border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm">
                <option value="">— ทุกหมวด —</option>
                @foreach($categories as $code => $label)
                    <option value="{{ $code }}" @selected(request('category')===$code)>{{ $label }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="only_inactive" value="1" @checked(request('only_inactive'))
                       class="rounded">
                แสดงเฉพาะที่ปิดอยู่
            </label>
            <div class="flex gap-2">
                <button class="flex-1 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm">กรอง</button>
                <a href="{{ route('admin.monetization.addons.index') }}"
                   class="px-4 py-2 rounded-lg ring-1 ring-slate-300 dark:ring-slate-600 text-slate-700 dark:text-slate-300 text-sm">ล้าง</a>
            </div>
        </div>
    </form>

    {{-- List --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold">SKU</th>
                        <th class="text-left px-2 py-3 font-semibold">รายการ</th>
                        <th class="text-left px-2 py-3 font-semibold">หมวด</th>
                        <th class="text-right px-2 py-3 font-semibold">ราคา</th>
                        <th class="text-center px-2 py-3 font-semibold">ขายแล้ว</th>
                        <th class="text-center px-2 py-3 font-semibold">สถานะ</th>
                        <th class="text-right px-4 py-3 font-semibold">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($items as $item)
                    @php
                        $tone   = $categoryTone[$item->category] ?? 'slate';
                        $cls    = $toneClasses[$tone];
                        $bg     = $toneBg[$tone];
                        $sales  = (int) ($usage[$item->sku] ?? 0);
                    @endphp
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30 {{ $item->trashed() || !$item->is_active ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 font-mono text-xs">{{ $item->sku }}</td>
                        <td class="px-2 py-3">
                            <div class="font-semibold text-slate-900 dark:text-white">{{ $item->label }}</div>
                            @if($item->tagline)
                                <div class="text-xs text-slate-500 truncate max-w-xs">{{ $item->tagline }}</div>
                            @endif
                            @if($item->badge)
                                <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-xs font-semibold ring-1 ring-amber-300/60 text-amber-700 dark:text-amber-300"
                                      style="background:rgba(245,158,11,0.18);">{{ $item->badge }}</span>
                            @endif
                        </td>
                        <td class="px-2 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset {{ $cls }}"
                                  style="background:{{ $bg }};">
                                {{ $categories[$item->category] ?? $item->category }}
                            </span>
                        </td>
                        <td class="px-2 py-3 text-right font-semibold text-indigo-600">
                            ฿{{ number_format($item->price_thb, 0) }}
                        </td>
                        <td class="px-2 py-3 text-center text-slate-600">
                            {{ number_format($sales) }}
                        </td>
                        <td class="px-2 py-3 text-center">
                            <button type="button"
                                    onclick="toggleAddon({{ $item->id }}, this)"
                                    data-active="{{ $item->is_active ? '1' : '0' }}"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset transition
                                           {{ $item->is_active ? $toneClasses['emerald'] : $toneClasses['slate'] }}"
                                    style="background:{{ $item->is_active ? $toneBg['emerald'] : $toneBg['slate'] }};">
                                {{ $item->is_active ? '✓ เปิดขาย' : '✗ ปิด' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.monetization.addons.edit', $item->id) }}"
                               class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
                                <i class="bi bi-pencil"></i> แก้ไข
                            </a>
                            <form method="POST" action="{{ route('admin.monetization.addons.destroy', $item->id) }}" class="inline"
                                  onsubmit="return confirm('{{ $sales > 0 ? "รายการนี้มีประวัติการซื้อ {$sales} รายการ — จะ soft-delete (ปิดขายอย่างถาวร) ดำเนินการ?" : "ลบรายการนี้?" }}')">
                                @csrf @method('DELETE')
                                <button class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-semibold text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/30">
                                    <i class="bi bi-trash"></i> ลบ
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">
                        ยังไม่มีรายการในหมวดที่เลือก —
                        <a href="{{ route('admin.monetization.addons.create') }}" class="text-indigo-600 hover:underline">เพิ่ม Addon แรก</a>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-700">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>

<script>
async function toggleAddon(id, btn) {
    btn.disabled = true;
    try {
        const res = await fetch(`{{ url('/admin/monetization/addons') }}/${id}/toggle`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        });
        const data = await res.json();
        if (data.ok) {
            // Reload to refresh the badge — simplest given the styling
            // is server-rendered. Could SPA-style update in future.
            location.reload();
        }
    } catch (e) {
        alert('เปลี่ยนสถานะไม่สำเร็จ: ' + e.message);
        btn.disabled = false;
    }
}
</script>
@endsection
