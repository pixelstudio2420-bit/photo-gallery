@extends('layouts.admin')
@section('title', $campaign->name . ' · Campaign Performance')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
  <a href="{{ route('admin.monetization.campaigns.index') }}" class="text-xs text-slate-500 hover:underline">← Campaigns</a>

  <div class="flex items-end justify-between gap-3 mb-5">
    <div>
      <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ $campaign->name }}</h1>
      <p class="text-sm text-slate-500">{{ $campaign->advertiser }} · {{ $campaign->pricing_model }} · ฿{{ number_format($campaign->rate_thb, 2) }}</p>
    </div>
    <form method="POST" action="{{ route('admin.monetization.campaigns.toggle', ['campaign' => $campaign, 'action' => $campaign->status === 'active' ? 'paused' : 'active']) }}">
      @csrf
      <button class="px-3 py-2 rounded-lg text-sm font-semibold
        {{ $campaign->status === 'active' ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-emerald-600 hover:bg-emerald-700 text-white' }}">
        {{ $campaign->status === 'active' ? '⏸ Pause' : '▶ Resume' }}
      </button>
    </form>
  </div>

  {{-- Performance tiles --}}
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
    @foreach([
      ['Impressions (30d)', number_format($stats['impressions']),  'sky'],
      ['Valid Imps',        number_format($stats['valid_imps']),    'emerald'],
      ['Clicks (30d)',      number_format($stats['clicks']),        'indigo'],
      ['Valid Clicks',      number_format($stats['valid_clks']),    'emerald'],
      ['CTR',               $stats['ctr_pct'] . '%',                'fuchsia'],
    ] as [$lbl, $val, $tone])
      <div class="rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-3">
        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold">{{ $lbl }}</div>
        <div class="text-xl font-extrabold text-{{ $tone }}-600 dark:text-{{ $tone }}-400 mt-1">{{ $val }}</div>
      </div>
    @endforeach
  </div>

  {{-- Budget bar --}}
  @php
    $cap = (float) $campaign->budget_cap_thb;
    $pct = $cap > 0 ? min(100, round((float) $campaign->spent_thb / $cap * 100, 1)) : 0;
  @endphp
  @if($cap > 0)
    <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5 mb-5">
      <div class="flex justify-between items-baseline mb-2">
        <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Budget</span>
        <span class="text-sm">฿{{ number_format($campaign->spent_thb, 2) }} / ฿{{ number_format($cap, 0) }} ({{ $pct }}%)</span>
      </div>
      <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
        <div class="h-full {{ $pct >= 100 ? 'bg-rose-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-emerald-500') }}"
             style="width: {{ $pct }}%;"></div>
      </div>
    </div>
  @endif

  {{-- Creatives --}}
  <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 dark:border-white/10 flex items-center justify-between">
      <h3 class="font-bold text-slate-900 dark:text-white">Creatives ({{ $creatives->count() }})</h3>
      <a href="{{ route('admin.monetization.campaigns.creatives.create', $campaign) }}"
         class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">
        <i class="bi bi-plus-lg"></i> เพิ่ม Creative + รูปภาพ
      </a>
    </div>
    @if($creatives->isEmpty())
      <div class="p-8 text-center text-slate-500 text-sm">
        ยังไม่มี creative — กด "เพิ่ม Creative + รูปภาพ" เพื่อสร้างใหม่
      </div>
    @else
    <table class="w-full text-sm">
      <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase text-slate-500">
        <tr>
          <th class="px-3 py-2 text-left">Image</th>
          <th class="px-3 py-2 text-left">Headline</th>
          <th class="px-3 py-2 text-left">Placement</th>
          <th class="px-3 py-2 text-right">Weight</th>
          <th class="px-3 py-2 text-left">URL</th>
          <th class="px-3 py-2 text-left">Active</th>
          <th class="px-3 py-2 text-right">การจัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-white/10">
        @foreach($creatives as $cr)
        <tr>
          <td class="px-3 py-2">
            @if($cr->image_url)
              <img src="{{ $cr->image_url }}" alt="{{ $cr->headline }}"
                   loading="lazy" class="w-20 h-10 object-cover rounded border border-slate-200 dark:border-white/10">
            @else
              <span class="text-slate-400 text-xs">—</span>
            @endif
          </td>
          <td class="px-3 py-2 font-medium">{{ $cr->headline }}</td>
          <td class="px-3 py-2 text-xs uppercase text-slate-500">{{ $cr->placement }}</td>
          <td class="px-3 py-2 text-right">{{ $cr->weight }}</td>
          <td class="px-3 py-2 text-xs"><a href="{{ $cr->click_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">{{ \Illuminate\Support\Str::limit($cr->click_url, 50) }}</a></td>
          <td class="px-3 py-2">{!! $cr->is_active ? '<span class="text-emerald-600">●</span>' : '<span class="text-slate-400">○</span>' !!}</td>
          <td class="px-3 py-2 text-right text-xs">
            <a href="{{ route('admin.monetization.campaigns.creatives.edit', ['campaign' => $campaign, 'creative' => $cr]) }}"
               class="text-indigo-600 dark:text-indigo-300 hover:underline">แก้ไข</a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>
@endsection
