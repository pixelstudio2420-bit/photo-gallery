@extends('layouts.admin')

@section('title', 'Threat Intelligence')

@push('styles')
@include('admin.settings._shared-styles')
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ═══════════════════ Header ═══════════════════ --}}
  <div class="mb-8">
    <a href="{{ route('admin.security.dashboard') }}"
       class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition mb-4">
      <i class="bi bi-arrow-left"></i> Back to Security
    </a>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div class="flex items-start gap-4">
        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-rose-500 to-orange-600 flex items-center justify-center shadow-lg shadow-rose-500/20">
          <i class="bi bi-radar text-white text-xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
            Threat Intelligence
          </h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            6-signal risk analysis · auto-blocking · incident tracking
          </p>
        </div>
      </div>

      <form method="POST" action="{{ route('admin.security.threat-intelligence.cleanup') }}" class="inline-flex items-center gap-2">
        @csrf
        <input type="number" name="days" value="30" min="7" max="365"
               class="w-20 px-3 py-2 text-sm rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-slate-700 hover:bg-slate-600 transition">
          <i class="bi bi-trash"></i> Cleanup
        </button>
      </form>
    </div>
  </div>

  @if(session('success'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
      <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-lg"></i>
      <div class="flex-1 text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ session('success') }}</div>
    </div>
  @endif

  {{-- ═══════════════════ Stats Grid ═══════════════════ --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl p-5 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Patterns 24h</div>
      <div class="text-3xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['patterns_last_24h'] ?? 0) }}</div>
      <div class="text-xs text-slate-500 mt-1">{{ number_format($stats['patterns_last_hour'] ?? 0) }} in last hour</div>
    </div>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl p-5 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">High-Risk IPs</div>
      <div class="text-3xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($stats['high_risk_ips'] ?? 0) }}</div>
      <div class="text-xs text-slate-500 mt-1">score ≥ 71</div>
    </div>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl p-5 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Blocked Fingerprints</div>
      <div class="text-3xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($stats['blocked_fingerprints'] ?? 0) }}</div>
      <div class="text-xs text-slate-500 mt-1">currently active</div>
    </div>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl p-5 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Open Incidents</div>
      <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($stats['open_incidents'] ?? 0) }}</div>
      <div class="text-xs text-slate-500 mt-1">{{ number_format($stats['critical_incidents'] ?? 0) }} critical</div>
    </div>
  </div>

  {{-- ═══════════════════ Top Threat IPs ═══════════════════ --}}
  <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm mb-6 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
      <h3 class="text-base font-semibold text-slate-900 dark:text-white">Top Threat IPs</h3>
      <span class="text-xs text-slate-500">Top 20 by risk score</span>
    </div>
    @if($topScores->count() > 0)
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">
          <tr>
            <th class="px-6 py-3 text-left">IP Address</th>
            <th class="px-6 py-3 text-left">Score</th>
            <th class="px-6 py-3 text-left">Last Seen</th>
            <th class="px-6 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-white/5">
          @foreach($topScores as $row)
          @php
            $score = (int) $row->score;
            $tone = $score >= 71 ? 'rose' : ($score >= 51 ? 'amber' : 'slate');
          @endphp
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
            <td class="px-6 py-3 font-mono text-slate-900 dark:text-white">{{ $row->ip }}</td>
            <td class="px-6 py-3">
              <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-{{ $tone }}-100 dark:bg-{{ $tone }}-500/10 text-{{ $tone }}-700 dark:text-{{ $tone }}-300">{{ $score }}</span>
            </td>
            <td class="px-6 py-3 text-slate-500 dark:text-slate-400 text-xs">{{ $row->last_seen ?? '—' }}</td>
            <td class="px-6 py-3 text-right">
              <form method="POST" action="{{ route('admin.security.block-ip') }}" class="inline-block">
                @csrf
                <input type="hidden" name="ip" value="{{ $row->ip }}">
                <input type="hidden" name="reason" value="High risk score: {{ $score }}">
                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-rose-600 hover:bg-rose-500">
                  <i class="bi bi-shield-x"></i> Block
                </button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @else
      <div class="p-12 text-center text-slate-500 text-sm">No threat scores yet — system is healthy.</div>
    @endif
  </div>

  {{-- ═══════════════════ Recent Incidents ═══════════════════ --}}
  <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm mb-6 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10">
      <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recent Incidents</h3>
    </div>
    @if($incidents->count() > 0)
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">
          <tr>
            <th class="px-6 py-3 text-left">When</th>
            <th class="px-6 py-3 text-left">Type</th>
            <th class="px-6 py-3 text-left">Severity</th>
            <th class="px-6 py-3 text-left">IP</th>
            <th class="px-6 py-3 text-left">Status</th>
            <th class="px-6 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-white/5">
          @foreach($incidents as $inc)
          @php
            $sev = $inc->severity ?? 'low';
            $sevTone = match($sev) {'critical' => 'rose', 'high' => 'orange', 'medium' => 'amber', default => 'slate'};
          @endphp
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
            <td class="px-6 py-3 text-slate-500 dark:text-slate-400 text-xs whitespace-nowrap">{{ $inc->created_at }}</td>
            <td class="px-6 py-3 font-medium text-slate-900 dark:text-white">{{ $inc->type }}</td>
            <td class="px-6 py-3">
              <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-{{ $sevTone }}-100 dark:bg-{{ $sevTone }}-500/10 text-{{ $sevTone }}-700 dark:text-{{ $sevTone }}-300">{{ $sev }}</span>
            </td>
            <td class="px-6 py-3 font-mono text-xs">{{ $inc->ip ?? '—' }}</td>
            <td class="px-6 py-3 text-xs">
              @if($inc->status === 'resolved')
                <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                  <i class="bi bi-check-circle-fill"></i> resolved
                </span>
              @else
                <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400">
                  <i class="bi bi-clock-fill"></i> open
                </span>
              @endif
            </td>
            <td class="px-6 py-3 text-right">
              @if($inc->status !== 'resolved')
              <form method="POST" action="{{ route('admin.security.threat-intelligence.incidents.resolve', $inc->id) }}" class="inline-block">
                @csrf
                <input type="hidden" name="resolution" value="Manually resolved by admin">
                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500">
                  <i class="bi bi-check2"></i> Resolve
                </button>
              </form>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="px-6 py-3 border-t border-slate-200 dark:border-white/10">
      {{ $incidents->links() }}
    </div>
    @else
      <div class="p-12 text-center text-slate-500 text-sm">No incidents recorded.</div>
    @endif
  </div>

  {{-- ═══════════════════ Active Blocks ═══════════════════ --}}
  <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10">
      <h3 class="text-base font-semibold text-slate-900 dark:text-white">Active Fingerprint Blocks</h3>
    </div>
    @if(count($blockedFingerprints) > 0)
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">
          <tr>
            <th class="px-6 py-3 text-left">Fingerprint</th>
            <th class="px-6 py-3 text-left">Reason</th>
            <th class="px-6 py-3 text-left">Expires</th>
            <th class="px-6 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-white/5">
          @foreach($blockedFingerprints as $bf)
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
            <td class="px-6 py-3 font-mono text-xs text-slate-900 dark:text-white">{{ Str::limit($bf->fingerprint, 24) }}</td>
            <td class="px-6 py-3 text-slate-500 text-xs">{{ $bf->reason ?? '—' }}</td>
            <td class="px-6 py-3 text-slate-500 text-xs">{{ $bf->expires_at }}</td>
            <td class="px-6 py-3 text-right">
              <form method="POST" action="{{ route('admin.security.threat-intelligence.unblock-fingerprint') }}" class="inline-block">
                @csrf
                <input type="hidden" name="fingerprint" value="{{ $bf->fingerprint }}">
                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-slate-600 hover:bg-slate-500">
                  <i class="bi bi-unlock"></i> Unblock
                </button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @else
      <div class="p-12 text-center text-slate-500 text-sm">No active blocks.</div>
    @endif
  </div>

</div>
@endsection
