@extends('layouts.admin')

@section('title', 'Email Campaigns')

@push('styles')
<style>
  .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(244,63,94,.14) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(236,72,153,.10) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(217,70,239,.12) 0px, transparent 50%);
  }
  .dark .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(244,63,94,.20) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(236,72,153,.16) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(217,70,239,.18) 0px, transparent 50%);
  }
</style>
@endpush

@section('content')
<div class="max-w-[1400px] mx-auto pb-10 space-y-5">

  {{-- ── HERO HEADER ────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-rose-100 dark:border-rose-500/20 gradient-mesh">
    <div class="relative p-6 md:p-7">
      <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-3">
        <a href="{{ route('admin.marketing.index') }}" class="hover:text-rose-600 dark:hover:text-rose-400 transition">Marketing Hub</a>
        <i class="bi bi-chevron-right text-[0.6rem] opacity-50"></i>
        <span class="text-slate-700 dark:text-slate-200 font-medium">Email Campaigns</span>
      </nav>

      <div class="flex items-start justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-rose-500 via-pink-500 to-fuchsia-500 text-white flex items-center justify-center shadow-lg shrink-0">
            <i class="bi bi-envelope-paper-heart-fill text-2xl"></i>
          </div>
          <div class="min-w-0 flex-1">
            <h1 class="text-2xl md:text-[1.65rem] font-bold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">Email Campaigns</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">สร้าง + ส่ง newsletter, promotions, broadcast ไปถึง subscribers</p>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                {{ number_format($stats['total']) }} campaigns
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                <i class="bi bi-send-check-fill"></i>
                {{ number_format($stats['sent']) }} sent
              </span>
            </div>
          </div>
        </div>
        <a href="{{ route('admin.marketing.campaigns.create') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-br from-rose-500 via-pink-500 to-fuchsia-500 text-white shadow-lg shadow-rose-500/30 hover:shadow-xl hover:-translate-y-0.5 text-sm font-semibold transition-all duration-200">
          <i class="bi bi-plus-lg"></i> Campaign ใหม่
        </a>
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

  {{-- ═══ Stats cards ═══ --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <a href="?status=" class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 flex items-center justify-center">
          <i class="bi bi-collection-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">All</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($stats['total']) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">total campaigns</div>
    </a>
    <a href="?status=draft" class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 flex items-center justify-center">
          <i class="bi bi-pencil-square"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Draft</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($stats['draft']) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">in progress</div>
    </a>
    <a href="?status=scheduled" class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
          <i class="bi bi-clock-history"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Scheduled</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($stats['scheduled']) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">queued</div>
    </a>
    <a href="?status=sent" class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
          <i class="bi bi-send-check-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sent</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($stats['sent']) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">delivered</div>
    </a>
  </div>

  {{-- ═══ Campaigns table ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/80 dark:bg-slate-900/40 text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">
          <tr>
            <th class="text-left px-4 py-3 font-semibold">Name / Subject</th>
            <th class="text-left px-4 py-3 font-semibold">Segment</th>
            <th class="text-left px-4 py-3 font-semibold">Status</th>
            <th class="text-right px-4 py-3 font-semibold">Sent</th>
            <th class="text-right px-4 py-3 font-semibold">Open%</th>
            <th class="text-right px-4 py-3 font-semibold">Click%</th>
            <th class="text-left px-4 py-3 font-semibold">When</th>
            <th></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200/60 dark:divide-white/[0.06]">
          @forelse($campaigns as $c)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
              <td class="px-4 py-3">
                <div class="text-slate-800 dark:text-slate-100 font-semibold">{{ $c->name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $c->subject }}</div>
              </td>
              <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                {{ $c->segment['type'] ?? 'all' }}
                @if(!empty($c->segment['value'])) : {{ $c->segment['value'] }}@endif
              </td>
              <td class="px-4 py-3">
                @php
                  $col = match($c->status) {
                    'sent' => 'emerald', 'scheduled' => 'amber',
                    'sending' => 'sky', 'cancelled' => 'rose',
                    'failed' => 'rose', default => 'slate'
                  };
                @endphp
                <span class="inline-flex px-2 py-0.5 rounded-full text-[0.68rem] font-semibold bg-{{ $col }}-100 text-{{ $col }}-700 dark:bg-{{ $col }}-500/15 dark:text-{{ $col }}-400 border border-{{ $col }}-200 dark:border-{{ $col }}-500/30">{{ $c->status }}</span>
              </td>
              <td class="px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($c->sent_count) }}/{{ number_format($c->total_recipients) }}</td>
              <td class="px-4 py-3 text-right tabular-nums text-emerald-600 dark:text-emerald-300 font-semibold">{{ $c->openRate() }}%</td>
              <td class="px-4 py-3 text-right tabular-nums text-sky-600 dark:text-sky-300 font-semibold">{{ $c->clickRate() }}%</td>
              <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                @if($c->sent_at) Sent {{ $c->sent_at->diffForHumans() }}
                @elseif($c->scheduled_at) <i class="bi bi-calendar-event"></i> {{ $c->scheduled_at->format('d M H:i') }}
                @else {{ $c->created_at->diffForHumans() }}
                @endif
              </td>
              <td class="px-4 py-3 text-right">
                <a href="{{ route('admin.marketing.campaigns.show', $c) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition" title="View">
                  <i class="bi bi-arrow-right-circle"></i>
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
              <i class="bi bi-inbox text-4xl block mb-2 opacity-50"></i>
              ยังไม่มี campaign
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-4 border-t border-slate-200/60 dark:border-white/[0.06]">{{ $campaigns->links() }}</div>
  </div>
</div>
@endsection
