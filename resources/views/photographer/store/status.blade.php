@extends('layouts.photographer')

@section('title', 'แผน · บริการเสริม · การใช้งาน')

@php
    use Illuminate\Support\Number;

    /**
     * Helpers for byte / percent / number rendering. Defined inline so the
     * template stays self-contained and the SubscriptionService::dashboardSummary
     * fields drop straight into the cards below.
     */
    $fmtBytes = function (?int $bytes): string {
        if (!$bytes || $bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0; $v = $bytes;
        while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
        return number_format($v, $v < 10 && $i > 0 ? 2 : ($v < 100 ? 1 : 0)) . ' ' . $units[$i];
    };
    $fmtPct = function (?float $pct): string {
        return $pct === null ? '—' : number_format($pct, 1) . '%';
    };
    $fmtBaht = function ($amount): string {
        return '฿' . number_format((float) $amount, 0);
    };

    $usagePct = (float) ($summary['storage_used_pct'] ?? 0);
    $aiPct    = (float) ($summary['ai_credits_used_pct'] ?? 0);
@endphp

@section('content')
<div class="max-w-6xl mx-auto p-6 space-y-6">

    {{-- ─────────────────────── Page header ─────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white">📊 สถานะแผน &amp; การใช้งาน</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">
                ภาพรวมแผนปัจจุบัน บริการเสริมที่เปิดใช้ และยอดที่ใช้ไปในรอบบิลนี้
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('photographer.store.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold transition">
                <i class="bi bi-cart-plus"></i> ซื้อบริการเสริม
            </a>
            <a href="{{ route('photographer.store.history') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-900 dark:text-white font-semibold transition">
                <i class="bi bi-receipt"></i> ประวัติการซื้อ
            </a>
        </div>
    </div>

    {{-- ─────────────────────── Pending checkouts banner ─────────────────────── --}}
    @if($pendingPurchases->count() > 0)
        <div class="bg-amber-50 dark:bg-amber-900/30 border-l-4 border-amber-500 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <i class="bi bi-hourglass-split text-amber-600 text-2xl"></i>
                <div class="flex-1">
                    <h3 class="font-bold text-amber-900 dark:text-amber-200 mb-2">มี {{ $pendingPurchases->count() }} รายการรอชำระเงิน</h3>
                    <ul class="space-y-2">
                        @foreach($pendingPurchases as $p)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <span>
                                    <strong>{{ $p->snapshot_decoded['label'] ?? $p->sku }}</strong>
                                    · {{ $fmtBaht($p->price_thb) }}
                                    <span class="text-amber-700 dark:text-amber-400">
                                        — สั่งซื้อเมื่อ {{ \Carbon\Carbon::parse($p->created_at)->diffForHumans() }}
                                    </span>
                                </span>
                                @if($p->order_id)
                                    <a href="{{ route('payment.checkout', ['order' => $p->order_id]) }}"
                                       class="px-3 py-1 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold">
                                        ชำระเงิน
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- ─────────────────────── Plan section ─────────────────────── --}}
    @if(!empty($summary['plan']))
        <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 bg-gradient-to-r from-indigo-600 to-violet-600 text-white">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs uppercase tracking-wide opacity-80 mb-1">แผนปัจจุบัน</div>
                        <div class="text-3xl font-extrabold">{{ $summary['plan']->name }}</div>
                        @if($summary['plan']->badge)
                            <span class="inline-block mt-2 px-2 py-1 rounded-md bg-white/20 backdrop-blur text-xs font-semibold">
                                {{ $summary['plan']->badge }}
                            </span>
                        @endif
                    </div>
                    <div class="text-right">
                        @if(($summary['has_active_paid'] ?? false) && !empty($summary['current_period_end']))
                            <div class="text-xs opacity-80 mb-1">ต่ออายุ</div>
                            <div class="font-semibold">
                                {{ $summary['current_period_end']->format('d/m/Y') }}
                            </div>
                            <div class="text-xs opacity-80 mt-1">
                                อีก {{ $summary['days_until_renewal'] ?? 0 }} วัน
                            </div>
                        @elseif($summary['plan']->is_default_free ?? false)
                            <div class="text-xs opacity-80 mb-1">แผนฟรี</div>
                            <div class="font-semibold">ไม่มีค่าใช้จ่าย</div>
                        @endif
                    </div>
                </div>

                @if($summary['in_grace'] ?? false)
                    <div class="mt-4 px-3 py-2 rounded-lg bg-red-500/30 backdrop-blur text-sm">
                        ⚠️ การชำระล่าสุดล้มเหลว · กำลังอยู่ในช่วงผ่อนผัน
                        @if(!empty($summary['grace_ends_at']))
                            จนถึง {{ $summary['grace_ends_at']->format('d/m/Y') }}
                        @endif
                    </div>
                @endif
            </div>

            <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">ค่าคอมมิชชั่นแพลตฟอร์ม</div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $fmtPct($summary['commission_pct']) }}
                    </div>
                    <div class="text-xs text-emerald-600 mt-1">
                        คุณได้ {{ $fmtPct($summary['photographer_share_pct']) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">อีเวนต์ที่กำลังเปิด</div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $summary['events_used'] ?? 0 }}{{ ($summary['events_unlimited'] ?? false) ? '' : '/' . ($summary['events_cap'] ?? '∞') }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                        @if($summary['events_unlimited'] ?? false) ไม่จำกัด @else เพดานแพลน @endif
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">Team Seats</div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $summary['plan']->max_team_seats ?? 1 }}
                    </div>
                </div>
                <div>
                    <a href="{{ route('photographer.subscription.plans') }}"
                       class="block px-4 py-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold text-center hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition text-sm">
                        เปลี่ยน/อัปเกรดแผน →
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- ─────────────────────── Usage breakdown ─────────────────────── --}}
    <section class="grid md:grid-cols-2 gap-4">

        {{-- Storage --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-6">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white">
                        <i class="bi bi-cloud-arrow-up-fill text-sky-500"></i>
                        พื้นที่เก็บงาน
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">รวม quota จากแผน + storage top-ups</p>
                </div>
                @if($usagePct >= 95)
                    <span class="px-2 py-1 rounded-md bg-red-100 text-red-700 text-xs font-bold">เต็มเร็ว ๆ นี้</span>
                @elseif($usagePct >= 85)
                    <span class="px-2 py-1 rounded-md bg-amber-100 text-amber-700 text-xs font-bold">เริ่มใกล้เต็ม</span>
                @endif
            </div>

            <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1">
                {{ $fmtBytes((int) ($summary['storage_used_bytes'] ?? 0)) }}
                <span class="text-sm font-normal text-slate-500">
                    / {{ $fmtBytes((int) ($summary['storage_quota_bytes'] ?? 0)) }}
                </span>
            </div>

            <div class="h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden mb-2">
                <div class="h-full transition-all
                    {{ $usagePct >= 95 ? 'bg-red-500' : ($usagePct >= 85 ? 'bg-amber-500' : 'bg-sky-500') }}"
                     style="width: {{ min(100, $usagePct) }}%"></div>
            </div>

            <div class="flex items-center justify-between text-xs text-slate-500">
                <span>ใช้ไป {{ $fmtPct($usagePct) }}</span>
                @if($usagePct >= 80)
                    <a href="{{ route('photographer.store.index') }}#storage"
                       class="text-sky-600 hover:text-sky-700 font-semibold">
                        ซื้อพื้นที่เพิ่ม →
                    </a>
                @endif
            </div>
        </div>

        {{-- AI Credits --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-6">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white">
                        <i class="bi bi-cpu-fill text-purple-500"></i>
                        AI Credits
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">รีเซ็ตรอบบิลถัดไป</p>
                </div>
                @if($aiPct >= 90)
                    <span class="px-2 py-1 rounded-md bg-red-100 text-red-700 text-xs font-bold">ใกล้หมด</span>
                @endif
            </div>

            <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1">
                {{ number_format((int) ($summary['ai_credits_used'] ?? 0)) }}
                <span class="text-sm font-normal text-slate-500">
                    / {{ number_format((int) ($summary['ai_credits_cap'] ?? 0)) }}
                </span>
            </div>

            <div class="h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden mb-2">
                <div class="h-full transition-all
                    {{ $aiPct >= 90 ? 'bg-red-500' : ($aiPct >= 70 ? 'bg-amber-500' : 'bg-purple-500') }}"
                     style="width: {{ min(100, $aiPct) }}%"></div>
            </div>

            <div class="flex items-center justify-between text-xs text-slate-500">
                <span>เหลือ {{ number_format((int) ($summary['ai_credits_remaining'] ?? 0)) }} credits</span>
                @if($aiPct >= 70)
                    <a href="{{ route('photographer.store.index') }}#ai_credits"
                       class="text-purple-600 hover:text-purple-700 font-semibold">
                        เติม credits →
                    </a>
                @endif
            </div>

            @if(!empty($summary['ai_credits_period_end']))
                <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 text-xs text-slate-500">
                    <i class="bi bi-arrow-clockwise"></i>
                    รอบใหม่ {{ $summary['ai_credits_period_end']->format('d/m/Y') }}
                </div>
            @endif
        </div>
    </section>

    {{-- ─────────────────────── Active add-ons ─────────────────────── --}}
    <section>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-3">
            <i class="bi bi-stars text-amber-500"></i> บริการเสริมที่เปิดใช้
        </h2>

        @if(empty($byCategory) && empty($brandingFlags))
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-8 text-center text-slate-500">
                <i class="bi bi-bag text-4xl mb-3 block opacity-50"></i>
                ยังไม่มีบริการเสริมที่เปิดใช้งาน
                <div class="mt-3">
                    <a href="{{ route('photographer.store.index') }}" class="text-indigo-600 hover:underline font-semibold">
                        เลือกซื้อจาก Store →
                    </a>
                </div>
            </div>
        @else
            <div class="grid md:grid-cols-2 gap-4">

                {{-- Active Promotions --}}
                @if(!empty($byCategory['promotion'] ?? null))
                    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                        <h3 class="font-bold text-slate-900 dark:text-white mb-3">
                            <i class="bi bi-rocket-takeoff text-indigo-500"></i> โปรโมท
                        </h3>
                        <ul class="space-y-3">
                            @foreach($byCategory['promotion'] as $a)
                                <li class="flex items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-700 last:border-0 last:pb-0">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-slate-900 dark:text-white truncate">{{ $a->label }}</div>
                                        <div class="text-xs text-slate-500 truncate">{{ $a->tagline }}</div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        @if($a->expires_at)
                                            <div class="text-xs font-semibold
                                                {{ \Carbon\Carbon::parse($a->expires_at)->diffInDays(now()) <= 3 ? 'text-amber-600' : 'text-emerald-600' }}">
                                                หมด {{ \Carbon\Carbon::parse($a->expires_at)->diffForHumans() }}
                                            </div>
                                        @else
                                            <span class="text-xs font-semibold text-emerald-600">ตลอดชีพ</span>
                                        @endif
                                        <div class="text-xs text-slate-400">{{ $fmtBaht($a->price_thb) }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Storage Top-ups --}}
                @if(!empty($byCategory['storage'] ?? null))
                    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                        <h3 class="font-bold text-slate-900 dark:text-white mb-3">
                            <i class="bi bi-cloud-arrow-up-fill text-sky-500"></i> Storage Top-ups
                        </h3>
                        <ul class="space-y-3">
                            @foreach($byCategory['storage'] as $a)
                                <li class="flex items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-700 last:border-0 last:pb-0">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-white">{{ $a->label }}</div>
                                        <div class="text-xs text-slate-500">เพิ่มจาก {{ $a->activated_at ? \Carbon\Carbon::parse($a->activated_at)->format('d/m/Y') : '—' }}</div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-semibold text-emerald-600">ตลอดชีพ subscription</span>
                                        <div class="text-xs text-slate-400">{{ $fmtBaht($a->price_thb) }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- AI Credits Top-ups --}}
                @if(!empty($byCategory['ai_credits'] ?? null))
                    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                        <h3 class="font-bold text-slate-900 dark:text-white mb-3">
                            <i class="bi bi-cpu-fill text-purple-500"></i> AI Credits Packs
                        </h3>
                        <ul class="space-y-3">
                            @foreach($byCategory['ai_credits'] as $a)
                                <li class="flex items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-700 last:border-0 last:pb-0">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-white">{{ $a->label }}</div>
                                        <div class="text-xs text-slate-500">เพิ่มเข้ารอบบิลปัจจุบัน</div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-semibold text-emerald-600">ใช้ใน 30 วัน</span>
                                        <div class="text-xs text-slate-400">{{ $fmtBaht($a->price_thb) }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Branding / Priority flags --}}
                @if(!empty($byCategory['branding'] ?? null) || !empty($byCategory['priority'] ?? null) || !empty($brandingFlags))
                    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                        <h3 class="font-bold text-slate-900 dark:text-white mb-3">
                            <i class="bi bi-palette-fill text-emerald-500"></i> Branding &amp; Priority
                        </h3>
                        <ul class="space-y-3">
                            @foreach(($byCategory['branding'] ?? []) as $a)
                                <li class="flex items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-700 last:border-0 last:pb-0">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-white">{{ $a->label }}</div>
                                        <div class="text-xs text-slate-500">{{ $a->tagline }}</div>
                                    </div>
                                    <span class="text-xs font-bold text-emerald-600">✓ เปิดใช้</span>
                                </li>
                            @endforeach
                            @foreach(($byCategory['priority'] ?? []) as $a)
                                <li class="flex items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-700 last:border-0 last:pb-0">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-white">{{ $a->label }}</div>
                                        <div class="text-xs text-slate-500">
                                            @if($a->expires_at)
                                                ต่ออายุ {{ \Carbon\Carbon::parse($a->expires_at)->format('d/m/Y') }}
                                            @endif
                                        </div>
                                    </div>
                                    <span class="text-xs font-bold text-emerald-600">✓ เปิดใช้</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </section>

    {{-- ─────────────────────── Quick links ─────────────────────── --}}
    <section class="grid md:grid-cols-3 gap-3 mt-6">
        <a href="{{ route('photographer.subscription.invoices') }}"
           class="bg-white dark:bg-slate-800 rounded-xl p-4 hover:shadow-md transition flex items-center gap-3">
            <i class="bi bi-receipt text-2xl text-indigo-500"></i>
            <div>
                <div class="font-semibold text-slate-900 dark:text-white">ใบเสร็จ subscription</div>
                <div class="text-xs text-slate-500">ประวัติการชำระแผน</div>
            </div>
        </a>
        <a href="{{ route('photographer.store.history') }}"
           class="bg-white dark:bg-slate-800 rounded-xl p-4 hover:shadow-md transition flex items-center gap-3">
            <i class="bi bi-bag-check text-2xl text-emerald-500"></i>
            <div>
                <div class="font-semibold text-slate-900 dark:text-white">ประวัติซื้อบริการเสริม</div>
                <div class="text-xs text-slate-500">ทุก add-on ที่ซื้อมา</div>
            </div>
        </a>
        <a href="{{ route('photographer.earnings') }}"
           class="bg-white dark:bg-slate-800 rounded-xl p-4 hover:shadow-md transition flex items-center gap-3">
            <i class="bi bi-graph-up-arrow text-2xl text-amber-500"></i>
            <div>
                <div class="font-semibold text-slate-900 dark:text-white">รายได้ของคุณ</div>
                <div class="text-xs text-slate-500">ดูยอดขาย + payout</div>
            </div>
        </a>
    </section>
</div>
@endsection
