@extends('layouts.admin')

@section('title', 'Marketing Analytics')

@push('styles')
<style>
  .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(139,92,246,.14) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(168,85,247,.10) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(217,70,239,.12) 0px, transparent 50%);
  }
  .dark .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(139,92,246,.20) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(168,85,247,.16) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(217,70,239,.18) 0px, transparent 50%);
  }
</style>
@endpush

@section('content')
<div class="max-w-[1400px] mx-auto pb-10 space-y-5">

  {{-- ── HERO HEADER ────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-violet-100 dark:border-violet-500/20 gradient-mesh">
    <div class="relative p-6 md:p-7">
      <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-3">
        <a href="{{ route('admin.marketing.index') }}" class="hover:text-violet-600 dark:hover:text-violet-400 transition">Marketing Hub</a>
        <i class="bi bi-chevron-right text-[0.6rem] opacity-50"></i>
        <span class="text-slate-700 dark:text-slate-200 font-medium">Analytics</span>
      </nav>

      <div class="flex items-start justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-500 via-purple-500 to-fuchsia-500 text-white flex items-center justify-center shadow-lg shrink-0">
            <i class="bi bi-bar-chart-line-fill text-2xl"></i>
          </div>
          <div class="min-w-0 flex-1">
            <h1 class="text-2xl md:text-[1.65rem] font-bold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">Marketing Analytics</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
              ผลงานช่องทางการตลาด — ข้อมูล UTM จาก {{ $days ?? 30 }} วันล่าสุด
            </p>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse"></span>
                First-touch attribution
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300">
                <i class="bi bi-tag-fill"></i> UTM-tagged orders
              </span>
            </div>
          </div>
        </div>
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-800 text-xs font-medium text-slate-600 dark:text-slate-300 shrink-0">
          <i class="bi bi-calendar-range text-violet-500"></i>
          <span>{{ now()->subDays($days ?? 30)->format('d M Y') }} — {{ now()->format('d M Y') }}</span>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══ Summary tiles ═══ --}}
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-indigo-100 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
          <i class="bi bi-eye-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Visits</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($summary['visits'] ?? 0) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">tracked sessions</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
          <i class="bi bi-cart-check-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Orders</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($summary['orders'] ?? 0) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">attributed conversions</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
          <i class="bi bi-cash-stack"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Revenue</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">฿{{ number_format($summary['revenue'] ?? 0, 0) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">from UTM-tagged orders</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 flex items-center justify-center">
          <i class="bi bi-percent"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Conv. Rate</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">
        {{ ($summary['visits'] ?? 0) > 0 ? number_format(100 * ($summary['orders'] ?? 0) / $summary['visits'], 2) : '0.00' }}%
      </div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">orders / visits</div>
    </div>
  </div>

  {{-- ═══ Channel performance table ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-violet-50/60 to-transparent dark:from-violet-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06] flex items-center justify-between flex-wrap gap-2">
      <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center shadow-md">
          <i class="bi bi-diagram-3"></i>
        </div>
        Channel Performance
      </h3>
      <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700/50">utm_source × utm_medium</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/80 dark:bg-slate-900/40 text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">
          <tr>
            <th class="text-left px-4 py-3 font-semibold">Source</th>
            <th class="text-left px-4 py-3 font-semibold">Medium</th>
            <th class="text-right px-4 py-3 font-semibold">Visits</th>
            <th class="text-right px-4 py-3 font-semibold">Orders</th>
            <th class="text-right px-4 py-3 font-semibold">Revenue</th>
            <th class="text-right px-4 py-3 font-semibold">Conv. Rate</th>
            <th class="text-right px-4 py-3 font-semibold">Avg. Order</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200/60 dark:divide-white/[0.06]">
          @forelse($channels ?? [] as $ch)
            @php
              $visits   = (int) ($ch['visits'] ?? $ch->visits ?? 0);
              $orders   = (int) ($ch['orders'] ?? $ch->orders ?? 0);
              $revenue  = (float) ($ch['revenue'] ?? $ch->revenue ?? 0);
              $source   = $ch['source'] ?? $ch->source ?? '(direct)';
              $medium   = $ch['medium'] ?? $ch->medium ?? '(none)';
              $convRate = $visits > 0 ? 100 * $orders / $visits : 0;
              $aov      = $orders > 0 ? $revenue / $orders : 0;
            @endphp
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-2 font-medium text-slate-800 dark:text-slate-100">
                  @switch(strtolower($source))
                    @case('line') <i class="bi bi-chat-dots-fill text-emerald-500"></i> @break
                    @case('facebook') @case('fb') @case('meta') <i class="bi bi-facebook text-blue-500"></i> @break
                    @case('google') <i class="bi bi-google text-amber-500"></i> @break
                    @case('instagram') @case('ig') <i class="bi bi-instagram text-rose-500"></i> @break
                    @case('tiktok') <i class="bi bi-tiktok text-pink-500"></i> @break
                    @case('youtube') <i class="bi bi-youtube text-red-500"></i> @break
                    @case('twitter') @case('x') <i class="bi bi-twitter-x text-slate-700 dark:text-slate-300"></i> @break
                    @case('(direct)') <i class="bi bi-globe2 text-slate-400"></i> @break
                    @default <i class="bi bi-link-45deg text-violet-500"></i>
                  @endswitch
                  {{ $source }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $medium }}</td>
              <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-200 tabular-nums">{{ number_format($visits) }}</td>
              <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-300 tabular-nums">{{ number_format($orders) }}</td>
              <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-300 tabular-nums font-semibold">฿{{ number_format($revenue, 0) }}</td>
              <td class="px-4 py-3 text-right tabular-nums {{ $convRate >= 2 ? 'text-emerald-600 dark:text-emerald-400' : ($convRate >= 0.5 ? 'text-slate-700 dark:text-slate-300' : 'text-slate-500 dark:text-slate-500') }}">
                {{ number_format($convRate, 2) }}%
              </td>
              <td class="px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-300">
                {{ $aov > 0 ? '฿' . number_format($aov, 0) : '—' }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                <i class="bi bi-inbox text-4xl block mb-2 opacity-50"></i>
                ยังไม่มีข้อมูล UTM — รอให้ user เข้าเว็บผ่าน link ที่มี UTM parameters
                <div class="mt-3 text-[0.7rem]">
                  เช่น <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-900/40 text-violet-600 dark:text-violet-400">?utm_source=line&utm_medium=oa_broadcast&utm_campaign=newyear</code>
                </div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- ═══ Top campaigns ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-amber-50/60 to-transparent dark:from-amber-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06]">
      <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-md">
          <i class="bi bi-trophy-fill"></i>
        </div>
        Top 10 Campaigns (by revenue)
      </h3>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/80 dark:bg-slate-900/40 text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">
          <tr>
            <th class="text-left px-4 py-3 font-semibold">#</th>
            <th class="text-left px-4 py-3 font-semibold">Campaign</th>
            <th class="text-right px-4 py-3 font-semibold">Visits</th>
            <th class="text-right px-4 py-3 font-semibold">Orders</th>
            <th class="text-right px-4 py-3 font-semibold">Revenue</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200/60 dark:divide-white/[0.06]">
          @forelse($summary['top_campaigns'] ?? [] as $i => $c)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
              <td class="px-4 py-3 text-slate-500 dark:text-slate-400 tabular-nums">
                @if($i < 3)
                  <span class="font-bold" style="color: {{ ['#f59e0b','#94a3b8','#cd7c2f'][$i] }};">{{ $i + 1 }}</span>
                @else
                  {{ $i + 1 }}
                @endif
              </td>
              <td class="px-4 py-3 text-slate-800 dark:text-slate-100 font-medium">{{ $c['campaign'] }}</td>
              <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-200 tabular-nums">{{ number_format($c['visits']) }}</td>
              <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-300 tabular-nums">{{ number_format($c['orders']) }}</td>
              <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-300 tabular-nums font-semibold">฿{{ number_format($c['revenue'], 0) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                <i class="bi bi-inbox text-4xl block mb-2 opacity-50"></i>
                ยังไม่มี campaign — ลอง tag link ด้วย <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-900/40 text-violet-600 dark:text-violet-400">utm_campaign=</code>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Info footer --}}
  <div class="text-xs text-slate-500 dark:text-slate-400 px-1">
    <i class="bi bi-info-circle"></i> Attribution model: <strong class="text-slate-700 dark:text-slate-300">First-touch</strong> — credits the original source that brought the visitor, preserved across session.
    Orders matched by session &rarr; order_id linkage.
  </div>
</div>
@endsection
