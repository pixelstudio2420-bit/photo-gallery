@extends('layouts.admin')
@section('title', 'Upload Credits')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-coin text-amber-500"></i> Upload Credits
        <span class="text-xs font-normal text-gray-400 ml-2">/ ภาพรวมระบบเครดิตอัปโหลด</span>
    </h4>
    <div class="flex gap-2">
        <a href="{{ route('admin.credits.packages.index') }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
            <i class="bi bi-boxes mr-1"></i>จัดการแพ็คเก็จ
        </a>
        <a href="{{ route('admin.credits.photographers.index') }}" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-sm">
            <i class="bi bi-people mr-1"></i>ดูบัญชีช่างภาพ
        </a>
    </div>
</div>

{{-- Flash messages --}}
@if(session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 dark:bg-emerald-500/10 dark:border-emerald-400/30 px-4 py-2 text-sm text-emerald-700 dark:text-emerald-300">
        <i class="bi bi-check-circle-fill mr-1"></i>{{ session('success') }}
    </div>
@endif
@if(session('warning'))
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-400/30 px-4 py-2 text-sm text-amber-700 dark:text-amber-300">
        <i class="bi bi-exclamation-triangle-fill mr-1"></i>{{ session('warning') }}
    </div>
@endif

{{-- ═══ System Toggle ═══════════════════════════════════════════════════
     Master on/off for the whole credits subsystem. When off:
       • Photographer menu entry hides
       • /photographer/credits/* redirects with warning
       • Dashboard widget self-hides
       • canUpload() bypasses the credits check so in-flight uploads still
         succeed (we don't want to trap photos mid-upload)
     Admin can still reach this page to flip it back.
--}}
<div class="bg-gradient-to-br {{ $systemEnabled ? 'from-emerald-500/10 to-emerald-600/5 border-emerald-200 dark:border-emerald-400/30' : 'from-slate-500/10 to-slate-600/5 border-slate-300 dark:border-white/10' }} rounded-xl border p-5 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center {{ $systemEnabled ? 'bg-emerald-500 text-white' : 'bg-slate-400 text-white' }}">
            <i class="bi {{ $systemEnabled ? 'bi-toggle-on' : 'bi-toggle-off' }} text-2xl"></i>
        </div>
        <div class="flex-1">
            <div class="flex items-center gap-2">
                <h3 class="font-bold text-gray-900 dark:text-white">สถานะระบบเครดิตอัปโหลด</h3>
                <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $systemEnabled ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-slate-200 text-slate-700 dark:bg-slate-600/30 dark:text-slate-300' }}">
                    {{ $systemEnabled ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
                </span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                @if($systemEnabled)
                    ช่างภาพเห็นเมนู "เครดิตอัปโหลด" และซื้อ/ใช้เครดิตได้ตามปกติ
                @else
                    เมนู "เครดิตอัปโหลด" ของช่างภาพถูกซ่อน — การอัปโหลดไม่ถูกตรวจสอบเครดิต (ผ่านได้หมด)
                @endif
            </p>
        </div>
        <form method="POST" action="{{ route('admin.credits.toggle') }}" onsubmit="return confirm('{{ $systemEnabled ? 'ยืนยันปิดระบบเครดิตอัปโหลด?' : 'ยืนยันเปิดระบบเครดิตอัปโหลด?' }}');">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold transition {{ $systemEnabled ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-emerald-600 hover:bg-emerald-700 text-white' }}">
                <i class="bi {{ $systemEnabled ? 'bi-power' : 'bi-play-fill' }} mr-1"></i>
                {{ $systemEnabled ? 'ปิดระบบ' : 'เปิดระบบ' }}
            </button>
        </form>
    </div>
</div>

{{-- KPI Cards --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">เครดิตคงเหลือในระบบ</div>
        <div class="text-2xl font-bold text-indigo-500">{{ number_format($totalOutstanding) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">Liability — ที่ยังใช้ไม่หมด</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">รายได้จากการขายเครดิต</div>
        <div class="text-2xl font-bold">฿{{ number_format($totalPurchasedRevenue, 0) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">ยอดจ่ายสะสมทั้งหมด</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">ช่างภาพโหมด Credits</div>
        <div class="text-2xl font-bold text-emerald-500">{{ number_format($photographersOnCredits) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">คนที่ใช้แพ็คเก็จเครดิต</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">ขายใน 30 วัน</div>
        <div class="text-2xl font-bold">{{ number_format($last30PurchaseCredits) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">เครดิตที่ขายออกไป</div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500">ใกล้หมดอายุใน 7 วัน</div>
        <div class="text-2xl font-bold text-rose-500">{{ number_format($bundlesExpiringSoon) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">Bundles ต้องแจ้งเตือน</div>
    </div>
</div>

{{-- Recent transactions --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
        <h5 class="font-semibold">รายการเคลื่อนไหวล่าสุด</h5>
        <span class="text-xs text-gray-400">20 รายการล่าสุด</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-3 py-2 text-left">วันที่</th>
                    <th class="px-3 py-2 text-left">ช่างภาพ</th>
                    <th class="px-3 py-2 text-left">ประเภท</th>
                    <th class="px-3 py-2 text-left">แพ็คเก็จ</th>
                    <th class="px-3 py-2 text-right">เปลี่ยนแปลง</th>
                    <th class="px-3 py-2 text-right">ยอดคงเหลือ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($recentTxns as $tx)
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap text-xs">{{ $tx->created_at?->format('d M H:i') }}</td>
                        <td class="px-3 py-2">
                            @if($tx->photographer)
                                <a href="{{ route('admin.credits.photographers.show', ['photographer' => $tx->photographer_id]) }}" class="text-indigo-500 hover:underline">
                                    {{ $tx->photographer->name }}
                                </a>
                                <div class="text-[10px] text-gray-400">{{ $tx->photographer->email }}</div>
                            @else
                                <span class="text-gray-400">#{{ $tx->photographer_id }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 dark:bg-slate-700">{{ $tx->kind }}</span>
                        </td>
                        <td class="px-3 py-2 text-xs">{{ $tx->bundle?->package?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-right font-semibold {{ $tx->delta >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">
                            {{ $tx->delta >= 0 ? '+' : '' }}{{ number_format($tx->delta) }}
                        </td>
                        <td class="px-3 py-2 text-right">{{ number_format($tx->balance_after) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">ยังไม่มีรายการเคลื่อนไหว</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
