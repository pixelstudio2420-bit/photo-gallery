@extends('layouts.admin')
@section('title', 'Photographer Credits — ' . ($user->name ?? $photographer->display_name))

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-person-circle text-indigo-500"></i>
        {{ $user->name ?? $photographer->display_name }}
        <span class="text-xs font-normal text-gray-400 ml-2">{{ $user->email ?? '' }}</span>
    </h4>
    <a href="{{ route('admin.credits.photographers.index') }}" class="text-sm text-gray-500 hover:underline">
        <i class="bi bi-arrow-left"></i> กลับ
    </a>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 text-rose-800 dark:text-rose-200 rounded-xl p-3 mb-4 text-sm">{{ session('error') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    {{-- Balance panel --}}
    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-xl p-5">
        <div class="text-xs uppercase tracking-wider text-indigo-100">เครดิตคงเหลือ</div>
        <div class="text-4xl font-extrabold tracking-tight mt-1">{{ number_format($balance) }}</div>
        <div class="text-[11px] text-indigo-100 mt-1">
            อัปเดต: {{ $photographer->credits_last_recalc_at ? \Illuminate\Support\Carbon::parse($photographer->credits_last_recalc_at)->diffForHumans() : '—' }}
        </div>
        <form method="POST" action="{{ route('admin.credits.photographers.recalc', $photographer) }}" class="mt-3">
            @csrf
            <button type="submit" class="px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white rounded-lg text-xs">
                <i class="bi bi-arrow-clockwise"></i> คำนวณใหม่
            </button>
        </form>
    </div>

    {{-- Grant form --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5">
        <h5 class="font-semibold mb-3 text-emerald-600 dark:text-emerald-400">
            <i class="bi bi-gift"></i> แจกเครดิต (Grant)
        </h5>
        <form method="POST" action="{{ route('admin.credits.photographers.grant', $photographer) }}" class="space-y-2">
            @csrf
            <div class="grid grid-cols-2 gap-2">
                <input type="number" name="credits" min="1" max="100000" required placeholder="จำนวน"
                       class="rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
                <input type="number" name="expires_days" min="0" max="3650" placeholder="อายุ (วัน) — 0=ไม่หมด"
                       class="rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
            </div>
            <input name="note" maxlength="300" placeholder="หมายเหตุ (ไม่บังคับ)"
                   class="w-full rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
            <button type="submit" class="w-full px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm">
                <i class="bi bi-plus-lg"></i> แจกเครดิต
            </button>
        </form>
    </div>

    {{-- Adjust form --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5">
        <h5 class="font-semibold mb-3 text-amber-600 dark:text-amber-400">
            <i class="bi bi-sliders"></i> ปรับยอด (Adjust ±)
        </h5>
        <form method="POST" action="{{ route('admin.credits.photographers.adjust', $photographer) }}" class="space-y-2">
            @csrf
            <input type="number" name="delta" min="-100000" max="100000" required placeholder="+100 หรือ -50"
                   class="w-full rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
            <input name="note" maxlength="300" placeholder="เหตุผลในการปรับ"
                   class="w-full rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
            <button type="submit" class="w-full px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm">
                <i class="bi bi-check-lg"></i> ปรับยอด
            </button>
        </form>
    </div>
</div>

{{-- Billing mode toggle --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5 mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h5 class="font-semibold">โหมดการเรียกเก็บ</h5>
            <p class="text-xs text-gray-500 mt-0.5">
                โหมดปัจจุบัน:
                <strong class="{{ $photographer->billing_mode === 'credits' ? 'text-emerald-600' : 'text-gray-600' }}">
                    {{ $photographer->billing_mode }}
                </strong>
            </p>
        </div>
        <form method="POST" action="{{ route('admin.credits.photographers.billing-mode', $photographer) }}" class="flex items-center gap-2">
            @csrf
            <select name="billing_mode" class="rounded-lg border border-gray-200 dark:border-white/10 dark:bg-slate-900 px-3 py-2 text-sm">
                <option value="credits" {{ $photographer->billing_mode === 'credits' ? 'selected' : '' }}>credits</option>
                <option value="commission" {{ $photographer->billing_mode === 'commission' ? 'selected' : '' }}>commission</option>
            </select>
            <button type="submit" class="px-3 py-2 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-sm">เปลี่ยนโหมด</button>
        </form>
    </div>
</div>

{{-- Bundles --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden mb-6">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-white/5">
        <h5 class="font-semibold">Credit Bundles ทั้งหมด</h5>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs uppercase tracking-wider text-gray-500">
            <tr>
                <th class="px-3 py-2 text-left">#</th>
                <th class="px-3 py-2 text-left">แพ็คเก็จ</th>
                <th class="px-3 py-2 text-left">ที่มา</th>
                <th class="px-3 py-2 text-right">เริ่ม</th>
                <th class="px-3 py-2 text-right">เหลือ</th>
                <th class="px-3 py-2 text-right">ราคา</th>
                <th class="px-3 py-2 text-left">หมดอายุ</th>
                <th class="px-3 py-2 text-left">สร้างเมื่อ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @forelse($bundles as $b)
                @php $expired = $b->expires_at && $b->expires_at->isPast(); @endphp
                <tr class="{{ $expired || $b->credits_remaining <= 0 ? 'opacity-60' : '' }}">
                    <td class="px-3 py-2 text-xs text-gray-400">#{{ $b->id }}</td>
                    <td class="px-3 py-2 text-sm">{{ $b->package?->name ?? ($b->note ?: '—') }}</td>
                    <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-[11px] bg-gray-100 dark:bg-slate-700">{{ $b->source }}</span></td>
                    <td class="px-3 py-2 text-right">{{ number_format($b->credits_initial) }}</td>
                    <td class="px-3 py-2 text-right font-semibold {{ $expired ? 'text-gray-400' : 'text-indigo-500' }}">
                        {{ number_format($b->credits_remaining) }}
                    </td>
                    <td class="px-3 py-2 text-right text-xs">฿{{ number_format($b->price_paid_thb, 0) }}</td>
                    <td class="px-3 py-2 text-xs {{ $expired ? 'text-rose-500' : 'text-gray-500' }}">
                        {{ $b->expires_at?->format('d M Y') ?: '—' }}
                    </td>
                    <td class="px-3 py-2 text-xs text-gray-400">{{ $b->created_at?->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-3 py-6 text-center text-gray-400">ยังไม่มี bundle</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Transactions --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
        <h5 class="font-semibold">Ledger (100 รายการล่าสุด)</h5>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs uppercase tracking-wider text-gray-500">
            <tr>
                <th class="px-3 py-2 text-left">วันที่</th>
                <th class="px-3 py-2 text-left">ประเภท</th>
                <th class="px-3 py-2 text-left">อ้างอิง</th>
                <th class="px-3 py-2 text-left">Actor</th>
                <th class="px-3 py-2 text-right">Delta</th>
                <th class="px-3 py-2 text-right">Balance</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @forelse($transactions as $tx)
                <tr>
                    <td class="px-3 py-2 text-xs">{{ $tx->created_at?->format('d M H:i') }}</td>
                    <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-[11px] bg-gray-100 dark:bg-slate-700">{{ $tx->kind }}</span></td>
                    <td class="px-3 py-2 text-xs text-gray-500">
                        {{ $tx->reference_type }}{{ $tx->reference_id ? ' #'.$tx->reference_id : '' }}
                    </td>
                    <td class="px-3 py-2 text-xs text-gray-500">{{ $tx->actor?->name ?? ($tx->actor_user_id ? '#'.$tx->actor_user_id : 'system') }}</td>
                    <td class="px-3 py-2 text-right font-semibold {{ $tx->delta >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">
                        {{ $tx->delta >= 0 ? '+' : '' }}{{ number_format($tx->delta) }}
                    </td>
                    <td class="px-3 py-2 text-right">{{ number_format($tx->balance_after) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">ยังไม่มีรายการ</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
