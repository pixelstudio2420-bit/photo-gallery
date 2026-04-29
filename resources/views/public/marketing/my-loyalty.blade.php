@extends('layouts.app')

@section('title', 'My Points')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">

    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
        <i class="bi bi-trophy-fill text-amber-500"></i>
        แต้มสะสม & Tier
    </h1>
    <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">สะสมแต้มทุกครั้งที่ซื้อ แลกเป็นส่วนลดได้เมื่อถึงขั้นต่ำ</p>

    {{-- Hero card --}}
    @php
        $tierColor = $account->tierBadgeColor();
        $tierIcon  = $account->tierIcon();
    @endphp
    <div class="rounded-2xl border border-{{ $tierColor }}-500/30 bg-gradient-to-br from-{{ $tierColor }}-500/10 to-amber-500/10 p-6 mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <div class="text-xs uppercase tracking-widest text-{{ $tierColor }}-600 dark:text-{{ $tierColor }}-400 font-semibold mb-1">Your Tier</div>
                <div class="flex items-center gap-2">
                    <i class="bi {{ $tierIcon }} text-4xl text-{{ $tierColor }}-500"></i>
                    <span class="text-3xl font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ $account->tier }}</span>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-widest text-amber-600 dark:text-amber-400 font-semibold mb-1">Points Balance</div>
                <div class="text-4xl md:text-5xl font-bold text-amber-500 tabular-nums">{{ number_format($account->points_balance) }}</div>
                <div class="text-xs text-slate-500 mt-1">pts</div>
            </div>
        </div>

        {{-- Tier progress --}}
        @php
            $tiers = [
                'bronze'   => 0,
                'silver'   => (float) \App\Models\AppSetting::get('marketing_loyalty_tier_silver_spend', 3000),
                'gold'     => (float) \App\Models\AppSetting::get('marketing_loyalty_tier_gold_spend', 15000),
                'platinum' => (float) \App\Models\AppSetting::get('marketing_loyalty_tier_platinum_spend', 50000),
            ];
            $nextTier = null;
            $nextThreshold = 0;
            foreach ($tiers as $t => $th) {
                if ($account->lifetime_spend < $th) {
                    $nextTier = $t;
                    $nextThreshold = $th;
                    break;
                }
            }
        @endphp
        @if($nextTier)
        <div class="mt-5 pt-4 border-t border-{{ $tierColor }}-500/20">
            @php
                $currentSpend = (float) $account->lifetime_spend;
                $currentTierSpend = $tiers[$account->tier] ?? 0;
                $range = max(1, $nextThreshold - $currentTierSpend);
                $progress = min(100, max(0, 100 * ($currentSpend - $currentTierSpend) / $range));
            @endphp
            <div class="text-xs text-slate-600 dark:text-slate-400 mb-1">
                อีก <strong class="text-amber-600 dark:text-amber-400">฿{{ number_format(max(0, $nextThreshold - $currentSpend), 0) }}</strong>
                เพื่อขึ้น tier <strong class="uppercase">{{ $nextTier }}</strong>
            </div>
            <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                <div class="h-full bg-gradient-to-r from-amber-400 to-amber-500" style="width: {{ $progress }}%"></div>
            </div>
        </div>
        @else
        <div class="mt-5 pt-4 border-t border-indigo-500/20 text-sm text-indigo-600 dark:text-indigo-400">
            <i class="bi bi-stars"></i> คุณอยู่ tier สูงสุดแล้ว! 🎉
        </div>
        @endif
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <div class="text-xs text-slate-500 uppercase">Lifetime Spend</div>
            <div class="text-xl font-bold text-slate-900 dark:text-white mt-1">฿{{ number_format($account->lifetime_spend, 0) }}</div>
        </div>
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-500/20 bg-emerald-50 dark:bg-emerald-500/5 p-4">
            <div class="text-xs text-emerald-600 dark:text-emerald-400 uppercase">Total Earned</div>
            <div class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($account->points_earned_total) }}</div>
        </div>
        <div class="rounded-xl border border-rose-200 dark:border-rose-500/20 bg-rose-50 dark:bg-rose-500/5 p-4">
            <div class="text-xs text-rose-600 dark:text-rose-400 uppercase">Total Redeemed</div>
            <div class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($account->points_redeemed_total) }}</div>
        </div>
    </div>

    {{-- Transactions --}}
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800">
            <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-clock-history text-indigo-500"></i> ประวัติ (50 รายการล่าสุด)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-950/40 text-xs text-slate-500 uppercase">
                    <tr>
                        <th class="text-left px-4 py-2">วันที่</th>
                        <th class="text-left px-4 py-2">ประเภท</th>
                        <th class="text-left px-4 py-2">เหตุผล</th>
                        <th class="text-right px-4 py-2">แต้ม</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($transactions as $t)
                        <tr>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $t->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-2">
                                @php $c = $t->typeBadgeColor(); @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[0.68rem] bg-{{ $c }}-100 dark:bg-{{ $c }}-500/15 text-{{ $c }}-700 dark:text-{{ $c }}-400">
                                    {{ $t->typeLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-xs text-slate-600 dark:text-slate-400">{{ $t->reason ?: '—' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums font-semibold {{ $t->points > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $t->points > 0 ? '+' : '' }}{{ number_format($t->points) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-slate-400">ยังไม่มีประวัติ — เริ่มช้อปเพื่อสะสมแต้มได้เลย</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
