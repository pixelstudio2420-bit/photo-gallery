@extends('layouts.admin')
@section('title', 'Brand Ad Campaigns')
@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-5">
    <div>
      <a href="{{ route('admin.monetization.dashboard') }}" class="text-xs text-slate-500 hover:underline">← Monetization</a>
      <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Brand Ad Campaigns</h1>
    </div>
    <a href="{{ route('admin.monetization.campaigns.create') }}" class="px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
      <i class="bi bi-plus-lg"></i> สร้างแคมเปญ
    </a>
  </div>
  <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase text-slate-500">
        <tr>
          <th class="px-3 py-2 text-left">ชื่อ</th>
          <th class="px-3 py-2 text-left">Brand</th>
          <th class="px-3 py-2 text-left">Pricing</th>
          <th class="px-3 py-2 text-right">Rate ฿</th>
          <th class="px-3 py-2 text-right">Cap</th>
          <th class="px-3 py-2 text-right">Spent</th>
          <th class="px-3 py-2 text-left">เริ่ม</th>
          <th class="px-3 py-2 text-left">สิ้นสุด</th>
          <th class="px-3 py-2 text-left">สถานะ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-white/10">
        @forelse($campaigns as $c)
        <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
          <td class="px-3 py-2"><a href="{{ route('admin.monetization.campaigns.show', $c) }}" class="text-indigo-600 dark:text-indigo-300 hover:underline font-semibold">{{ $c->name }}</a></td>
          <td class="px-3 py-2 text-slate-700">{{ $c->advertiser }}</td>
          <td class="px-3 py-2 text-xs uppercase text-slate-500">{{ $c->pricing_model }}</td>
          <td class="px-3 py-2 text-right">{{ number_format($c->rate_thb, 2) }}</td>
          <td class="px-3 py-2 text-right">{{ $c->budget_cap_thb !== null ? number_format($c->budget_cap_thb, 0) : '∞' }}</td>
          <td class="px-3 py-2 text-right font-bold">{{ number_format($c->spent_thb, 0) }}</td>
          <td class="px-3 py-2 text-xs text-slate-500">{{ $c->starts_at?->format('d M y') }}</td>
          <td class="px-3 py-2 text-xs text-slate-500">{{ $c->ends_at?->format('d M y') }}</td>
          <td class="px-3 py-2">
            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase
              @if($c->status === 'active')        bg-emerald-100 text-emerald-700
              @elseif($c->status === 'paused')    bg-amber-100 text-amber-700
              @elseif($c->status === 'exhausted') bg-rose-100 text-rose-700
              @else                                bg-slate-100 text-slate-600 @endif">{{ $c->status }}</span>
          </td>
        </tr>
        @empty
        <tr><td colspan="9" class="px-3 py-12 text-center text-slate-500">ยังไม่มีแคมเปญ — <a href="{{ route('admin.monetization.campaigns.create') }}" class="text-indigo-600 hover:underline">สร้างแรก</a></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $campaigns->links() }}</div>
</div>
@endsection
