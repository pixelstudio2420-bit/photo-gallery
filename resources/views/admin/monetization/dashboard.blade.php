@extends('layouts.admin')

@section('title', 'รายได้จากการโฆษณา · Monetization')

@section('content')
<div class="max-w-[1400px] mx-auto px-4 py-6">

  <div class="flex items-end justify-between flex-wrap gap-3 mb-6">
    <div>
      <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Monetization Dashboard</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400">รายได้รวม · แคมเปญ Brand Ads · Promotion ช่างภาพ · CTR + Conversion</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('admin.monetization.campaigns.create') }}" class="px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
        <i class="bi bi-plus-lg"></i> เพิ่มแคมเปญ
      </a>
      <a href="{{ route('admin.monetization.addons.index') }}"
         class="px-3 py-2 rounded-lg ring-1 ring-indigo-300 text-indigo-700 dark:text-indigo-300 text-sm font-semibold transition hover:bg-indigo-50 dark:hover:bg-indigo-900/30"
         style="background:rgba(99,102,241,0.10);">
        <i class="bi bi-box-seam"></i> Addon Catalog
      </a>
      <a href="{{ route('admin.monetization.promotions') }}"
         class="px-3 py-2 rounded-lg ring-1 ring-amber-300 text-amber-700 dark:text-amber-300 text-sm font-semibold transition hover:bg-amber-50 dark:hover:bg-amber-900/30"
         style="background:rgba(245,158,11,0.10);">
        <i class="bi bi-stars"></i> Photographer Promotions
      </a>
    </div>
  </div>

  {{-- ─── Revenue tiles ─────────────────────────────────────────────── --}}
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    @foreach([
      ['วันนี้',       $revenue['today_thb'],      'bi-calendar-day',  'emerald'],
      ['เดือนนี้',      $revenue['this_month_thb'], 'bi-calendar-month','sky'],
      ['ตลอดเวลา',    $revenue['all_time_thb'],   'bi-piggy-bank',    'indigo'],
    ] as [$lbl, $val, $icon, $tone])
      <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5">
        <div class="flex items-center justify-between mb-1">
          <span class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">{{ $lbl }}</span>
          <i class="bi {{ $icon }} text-{{ $tone }}-500 text-xl"></i>
        </div>
        <div class="text-3xl font-extrabold text-slate-900 dark:text-white">฿{{ number_format($val, 2) }}</div>
      </div>
    @endforeach
  </div>

  {{-- ─── CTR + counts ──────────────────────────────────────────────── --}}
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    @foreach([
      ['Active Campaigns',    $campaignStats['active'],   'sky'],
      ['Paused Campaigns',    $campaignStats['paused'],   'amber'],
      ['Active Promotions',   $promoStats['active'],      'emerald'],
      ['Impressions / 7d',    number_format($ctr['impressions']),  'indigo'],
      ['CTR (7d)',            $ctr['rate_pct'] . '%',     'fuchsia'],
    ] as [$lbl, $val, $tone])
      <div class="rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-3">
        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold">{{ $lbl }}</div>
        <div class="text-xl font-extrabold text-{{ $tone }}-600 dark:text-{{ $tone }}-400 mt-1">{{ $val }}</div>
      </div>
    @endforeach
  </div>

  {{-- ─── Recent campaigns ─────────────────────────────────────────── --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-white/10">
        <h2 class="font-bold text-slate-900 dark:text-white">แคมเปญล่าสุด (Brand Ads)</h2>
        <a href="{{ route('admin.monetization.campaigns.index') }}" class="text-xs text-indigo-600 dark:text-indigo-300 hover:underline">ดูทั้งหมด →</a>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase text-slate-500">
          <tr>
            <th class="px-3 py-2 text-left">ชื่อ</th>
            <th class="px-3 py-2 text-left">Brand</th>
            <th class="px-3 py-2 text-right">ใช้แล้ว</th>
            <th class="px-3 py-2 text-left">สถานะ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-white/10">
          @forelse($recentCampaigns as $c)
          <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
            <td class="px-3 py-2"><a href="{{ route('admin.monetization.campaigns.show', $c) }}" class="text-indigo-600 dark:text-indigo-300 hover:underline font-medium">{{ $c->name }}</a></td>
            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $c->advertiser }}</td>
            <td class="px-3 py-2 text-right font-bold">฿{{ number_format($c->spent_thb, 0) }}</td>
            <td class="px-3 py-2">
              <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase
                @if($c->status === 'active')        bg-emerald-100 text-emerald-700
                @elseif($c->status === 'paused')    bg-amber-100 text-amber-700
                @elseif($c->status === 'exhausted') bg-rose-100 text-rose-700
                @else                                bg-slate-100 text-slate-600 @endif">
                {{ $c->status }}
              </span>
            </td>
          </tr>
          @empty
          <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500 text-xs">ยังไม่มีแคมเปญ</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-white/10">
        <h2 class="font-bold text-slate-900 dark:text-white">Promotion ช่างภาพ ล่าสุด</h2>
        <a href="{{ route('admin.monetization.promotions') }}" class="text-xs text-indigo-600 dark:text-indigo-300 hover:underline">ดูทั้งหมด →</a>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase text-slate-500">
          <tr>
            <th class="px-3 py-2 text-left">ช่างภาพ</th>
            <th class="px-3 py-2 text-left">รูปแบบ</th>
            <th class="px-3 py-2 text-right">฿</th>
            <th class="px-3 py-2 text-left">สถานะ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-white/10">
          @forelse($recentPromotions as $p)
          <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ optional($p->photographer)->first_name ?? '#'.$p->photographer_id }}</td>
            <td class="px-3 py-2 text-xs text-slate-500">{{ $p->kind }} · {{ $p->billing_cycle }}</td>
            <td class="px-3 py-2 text-right font-bold">฿{{ number_format($p->amount_thb, 0) }}</td>
            <td class="px-3 py-2">
              <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase
                @if($p->status === 'active')   bg-emerald-100 text-emerald-700
                @elseif($p->status === 'expired') bg-slate-100 text-slate-600
                @elseif($p->status === 'cancelled') bg-rose-100 text-rose-700
                @else bg-amber-100 text-amber-700 @endif">
                {{ $p->status }}
              </span>
            </td>
          </tr>
          @empty
          <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500 text-xs">ยังไม่มี promotion</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
