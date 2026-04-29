@extends('layouts.admin')

@section('title', 'Loyalty Program')

@push('styles')
<style>
  .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(250,204,21,.14) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(245,158,11,.10) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(249,115,22,.12) 0px, transparent 50%);
  }
  .dark .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(250,204,21,.20) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(245,158,11,.16) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(249,115,22,.18) 0px, transparent 50%);
  }
  @keyframes pending-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,.4); }
    50%      { box-shadow: 0 0 0 6px transparent; }
  }
  .has-changes-pulse { animation: pending-glow 1.8s ease-in-out infinite; }
</style>
@endpush

@section('content')
<div x-data="{ hasChanges: false }" class="max-w-[1300px] mx-auto pb-24 space-y-5">

  {{-- ── HERO HEADER ────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-amber-100 dark:border-amber-500/20 gradient-mesh">
    <div class="relative p-6 md:p-7">
      <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-3">
        <a href="{{ route('admin.marketing.index') }}" class="hover:text-amber-600 dark:hover:text-amber-400 transition">Marketing Hub</a>
        <i class="bi bi-chevron-right text-[0.6rem] opacity-50"></i>
        <span class="text-slate-700 dark:text-slate-200 font-medium">Loyalty Program</span>
      </nav>

      <div class="flex items-start justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-yellow-400 via-amber-500 to-orange-500 text-white flex items-center justify-center shadow-lg shrink-0">
            <i class="bi bi-trophy-fill text-2xl"></i>
          </div>
          <div class="min-w-0 flex-1">
            <h1 class="text-2xl md:text-[1.65rem] font-bold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">Loyalty Program</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">แต้มสะสม + tier (bronze/silver/gold/platinum) — ใช้เก็บ retention ของลูกค้า repeat</p>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                {{ number_format($summary['totalAccounts']) }} accounts
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-300">
                <i class="bi bi-coin"></i>
                {{ number_format($summary['totalPoints']) }} pts
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2"
         x-data="{ show: true }" x-show="show">
      <i class="bi bi-check-circle-fill text-emerald-500"></i>
      <span class="flex-1">{{ session('success') }}</span>
      <button type="button" @click="show = false" class="text-emerald-600/60 hover:text-emerald-700 dark:text-emerald-400/60 dark:hover:text-emerald-300">
        <i class="bi bi-x-lg text-sm"></i>
      </button>
    </div>
  @endif
  @if(session('error'))
    <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 text-sm flex items-center gap-2">
      <i class="bi bi-exclamation-triangle-fill text-rose-500"></i>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  {{-- ═══ Summary cards ═══ --}}
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 flex items-center justify-center">
          <i class="bi bi-people-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Accounts</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($summary['totalAccounts']) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">loyalty members</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
          <i class="bi bi-coin"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Points in Circulation</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($summary['totalPoints']) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">unredeemed balance</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
          <i class="bi bi-cash-stack"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Lifetime Spend</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">฿{{ number_format($summary['totalSpent'], 0) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">from members</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-indigo-100 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
          <i class="bi bi-bar-chart-steps"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tier Breakdown</span>
      </div>
      <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-xs">
        @foreach(['bronze', 'silver', 'gold', 'platinum'] as $tier)
          @php $n = $summary['tierBreakdown'][$tier] ?? 0; @endphp
          <span class="text-slate-700 dark:text-slate-300">{{ $tier }}: <strong class="text-slate-900 dark:text-slate-100">{{ $n }}</strong></span>
        @endforeach
      </div>
    </div>
  </div>

  {{-- ═══ Settings ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-amber-50/60 to-transparent dark:from-amber-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06] flex items-center gap-2">
      <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-yellow-400 via-amber-500 to-orange-500 text-white flex items-center justify-center shadow-md">
        <i class="bi bi-sliders"></i>
      </div>
      <h3 class="font-bold text-slate-800 dark:text-slate-100">Loyalty Settings</h3>
    </div>
    <form method="POST" action="{{ route('admin.marketing.loyalty.settings') }}" @submit="hasChanges = false" @input="hasChanges = true" class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
      @csrf
      <div>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Earn Rate (points per 1 THB)</label>
        <input type="number" step="0.01" name="earn_rate" value="{{ $settings['earn_rate'] }}"
            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
        <p class="text-[0.7rem] text-slate-500 dark:text-slate-400 mt-1">1 = 100 บาท → 100 points</p>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Redeem Rate (points per 1 THB discount)</label>
        <input type="number" step="0.01" name="redeem_rate" value="{{ $settings['redeem_rate'] }}"
            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
        <p class="text-[0.7rem] text-slate-500 dark:text-slate-400 mt-1">10 = 100 points → ฿10 ส่วนลด</p>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Min Redeem (points)</label>
        <input type="number" min="0" name="min_redeem" value="{{ $settings['min_redeem'] }}"
            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
      </div>

      <div class="md:col-span-3 pt-3 mt-2 border-t border-slate-200/60 dark:border-white/[0.06]">
        <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2 flex items-center gap-1.5">
          <i class="bi bi-bar-chart-steps text-amber-500"></i> Tier Thresholds (lifetime spend, THB)
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5"><i class="bi bi-award text-slate-500"></i> Silver</label>
        <input type="number" step="0.01" name="tier_silver_spend" value="{{ $settings['tier_silver_spend'] }}"
            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5"><i class="bi bi-trophy text-amber-500"></i> Gold</label>
        <input type="number" step="0.01" name="tier_gold_spend" value="{{ $settings['tier_gold_spend'] }}"
            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5"><i class="bi bi-gem text-indigo-500"></i> Platinum</label>
        <input type="number" step="0.01" name="tier_platinum_spend" value="{{ $settings['tier_platinum_spend'] }}"
            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
      </div>
      <div class="md:col-span-3 flex justify-end">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold bg-gradient-to-br from-yellow-400 via-amber-500 to-orange-500 text-white shadow-lg shadow-amber-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-check2"></i> บันทึก
        </button>
      </div>
    </form>
  </div>

  {{-- ═══ Manual adjustment ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-indigo-50/60 to-transparent dark:from-indigo-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06] flex items-center gap-2">
      <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 text-white flex items-center justify-center shadow-md">
        <i class="bi bi-pencil-square"></i>
      </div>
      <h3 class="font-bold text-slate-800 dark:text-slate-100">Manual Points Adjustment</h3>
    </div>
    <form method="POST" action="{{ route('admin.marketing.loyalty.adjust') }}" class="p-5 flex flex-wrap gap-3 items-end">
      @csrf
      <div class="flex-1 min-w-[150px]">
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">User ID</label>
        <input type="number" name="user_id" required
            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 outline-none transition">
      </div>
      <div class="flex-1 min-w-[150px]">
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Points (+/-)</label>
        <input type="number" name="points" required placeholder="100 หรือ -50"
            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 outline-none transition">
      </div>
      <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Reason</label>
        <input type="text" name="reason" placeholder="เช่น: goodwill, contest_prize"
            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 outline-none transition">
      </div>
      <button class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold bg-gradient-to-br from-indigo-500 to-violet-500 text-white shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all">
        <i class="bi bi-coin"></i> ปรับแต้ม
      </button>
    </form>
  </div>

  {{-- ═══ Accounts ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-amber-50/40 to-transparent dark:from-amber-500/5 p-4 border-b border-slate-200/60 dark:border-white/[0.06] flex items-center gap-3 flex-wrap">
      <h3 class="font-bold text-slate-800 dark:text-slate-100 flex-1">Loyalty Accounts</h3>
      <form method="GET" class="flex items-center gap-2 flex-wrap">
        <div class="relative">
          <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <input type="text" name="q" value="{{ $q }}" placeholder="Search user..."
              class="pl-9 pr-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
        </div>
        <select name="tier" class="px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
          <option value="">All Tiers</option>
          @foreach(['bronze', 'silver', 'gold', 'platinum'] as $t)
            <option value="{{ $t }}" {{ $tier === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
          @endforeach
        </select>
        <button class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700/60 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-medium transition">
          <i class="bi bi-funnel"></i> Filter
        </button>
      </form>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/80 dark:bg-slate-900/40 text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">
          <tr>
            <th class="text-left px-4 py-3 font-semibold">User</th>
            <th class="text-left px-4 py-3 font-semibold">Tier</th>
            <th class="text-right px-4 py-3 font-semibold">Balance</th>
            <th class="text-right px-4 py-3 font-semibold">Earned</th>
            <th class="text-right px-4 py-3 font-semibold">Redeemed</th>
            <th class="text-right px-4 py-3 font-semibold">Lifetime Spend</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200/60 dark:divide-white/[0.06]">
          @forelse($accounts as $a)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
              <td class="px-4 py-3">
                <div class="text-slate-800 dark:text-slate-100 text-sm">{{ $a->user?->name ?? 'User #' . $a->user_id }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $a->user?->email }}</div>
              </td>
              <td class="px-4 py-3">
                @php $c = $a->tierBadgeColor(); @endphp
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[0.68rem] font-semibold bg-{{ $c }}-100 text-{{ $c }}-700 dark:bg-{{ $c }}-500/15 dark:text-{{ $c }}-400 border border-{{ $c }}-200 dark:border-{{ $c }}-500/30">
                  <i class="bi {{ $a->tierIcon() }}"></i>
                  {{ strtoupper($a->tier) }}
                </span>
              </td>
              <td class="px-4 py-3 text-right tabular-nums font-semibold text-amber-600 dark:text-amber-300">{{ number_format($a->points_balance) }}</td>
              <td class="px-4 py-3 text-right tabular-nums text-emerald-600 dark:text-emerald-300">{{ number_format($a->points_earned_total) }}</td>
              <td class="px-4 py-3 text-right tabular-nums text-rose-600 dark:text-rose-300">{{ number_format($a->points_redeemed_total) }}</td>
              <td class="px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-300">฿{{ number_format($a->lifetime_spend, 0) }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
              <i class="bi bi-inbox text-4xl block mb-2 opacity-50"></i>
              ยังไม่มีบัญชี loyalty
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-4 border-t border-slate-200/60 dark:border-white/[0.06]">{{ $accounts->links() }}</div>
  </div>
</div>
@endsection
