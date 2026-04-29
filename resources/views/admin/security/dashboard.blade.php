@extends('layouts.admin')

@section('title', 'Security Dashboard')

@push('styles')
@include('admin.settings._shared-styles')
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ═══════════════════ Header ═══════════════════ --}}
  <div class="mb-8">
    <a href="{{ route('admin.dashboard') }}"
       class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition mb-4">
      <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div class="flex items-start gap-4">
        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
          <i class="bi bi-shield-shaded text-white text-xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
            Security Dashboard
          </h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Monitor threats, scan findings, and blocked IPs
          </p>
        </div>
      </div>

      <form method="POST" action="{{ route('admin.security.scan') }}" class="inline-flex">
        @csrf
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 shadow-lg shadow-indigo-500/20 transition">
          <i class="bi bi-arrow-repeat"></i>
          <span>Run Full Scan</span>
        </button>
      </form>
    </div>
  </div>

  {{-- ═══════════════════ Flash Messages ═══════════════════ --}}
  @if(session('success'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
      <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-lg"></i>
      <div class="flex-1 text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ session('success') }}</div>
    </div>
  @endif
  @if(session('error'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30">
      <i class="bi bi-exclamation-triangle-fill text-rose-600 dark:text-rose-400 text-lg"></i>
      <div class="flex-1 text-sm font-medium text-rose-800 dark:text-rose-300">{{ session('error') }}</div>
    </div>
  @endif

  {{-- ═══════════════════ Stats Grid ═══════════════════ --}}
  @php
    $criticalCount = 0; $highCount = 0; $mediumCount = 0;
    if ($scanResult && isset($scanResult['findings'])) {
      foreach ($scanResult['findings'] as $f) {
        if (($f['status'] ?? '') === 'fail') {
          if (($f['severity'] ?? '') === 'critical') $criticalCount++;
          elseif (($f['severity'] ?? '') === 'high') $highCount++;
          elseif (($f['severity'] ?? '') === 'medium') $mediumCount++;
        }
      }
    }
  @endphp

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    {{-- Security Score --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl p-6 shadow-sm">
      <div class="text-center">
        <h6 class="text-xs font-semibold tracking-widest uppercase text-slate-500 dark:text-slate-400 mb-3">Security Score</h6>
        @if($scanResult)
          @php
            $score = $scanResult['score'] ?? 0;
            $scoreRing = $score >= 80 ? 'border-emerald-500 dark:border-emerald-400 text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10'
                          : ($score >= 50 ? 'border-amber-500 dark:border-amber-400 text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10'
                          : 'border-rose-500 dark:border-rose-400 text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10');
            $scoreBadge = $score >= 80 ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30'
                          : ($score >= 50 ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30'
                          : 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30');
            $scoreLabel = $score >= 80 ? 'Good' : ($score >= 50 ? 'Fair' : 'Critical');
          @endphp
          <div class="mx-auto w-24 h-24 rounded-full border-4 flex items-center justify-center mb-3 {{ $scoreRing }}">
            <span class="text-3xl font-bold">{{ $score }}</span>
          </div>
          <div class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-semibold mb-2 {{ $scoreBadge }}">
            {{ $scoreLabel }}
          </div>
          <div class="text-xs text-slate-500 dark:text-slate-400">
            Last scan: {{ $scanResult['scanned_at'] ?? 'unknown' }}
          </div>
        @else
          <div class="mx-auto w-24 h-24 rounded-full border-4 border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-3">
            <i class="bi bi-question-lg text-3xl text-slate-400 dark:text-slate-500"></i>
          </div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mb-3">No scan yet</div>
          <form method="POST" action="{{ route('admin.security.scan') }}">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 transition">
              <i class="bi bi-play-fill"></i> Scan Now
            </button>
          </form>
        @endif
      </div>
    </div>

    {{-- Critical Issues --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl p-6 shadow-sm">
      <div class="flex items-center gap-4">
        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-rose-100 dark:bg-rose-500/10 flex items-center justify-center">
          <i class="bi bi-exclamation-octagon-fill text-rose-600 dark:text-rose-400 text-xl"></i>
        </div>
        <div>
          <div class="text-3xl font-bold text-slate-900 dark:text-white leading-none">{{ $criticalCount }}</div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-1">Critical Issues</div>
        </div>
      </div>
    </div>

    {{-- High Issues --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl p-6 shadow-sm">
      <div class="flex items-center gap-4">
        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-500/10 flex items-center justify-center">
          <i class="bi bi-exclamation-triangle-fill text-amber-600 dark:text-amber-400 text-xl"></i>
        </div>
        <div>
          <div class="text-3xl font-bold text-slate-900 dark:text-white leading-none">{{ $highCount }}</div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-1">High Issues</div>
        </div>
      </div>
    </div>

    {{-- Blocked IPs --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl p-6 shadow-sm">
      <div class="flex items-center gap-4">
        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-indigo-100 dark:bg-indigo-500/10 flex items-center justify-center">
          <i class="bi bi-geo-alt-fill text-indigo-600 dark:text-indigo-400 text-xl"></i>
        </div>
        <div>
          <div class="text-3xl font-bold text-slate-900 dark:text-white leading-none">{{ $blockedIps->count() }}</div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-1">Blocked IPs</div>
        </div>
      </div>
    </div>

  </div>

  {{-- ═══════════════════ Main Grid ═══════════════════ --}}
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    {{-- Scan Findings --}}
    @if($scanResult && isset($scanResult['findings']))
    <div class="lg:col-span-7">
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
        <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10">
          <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
            <i class="bi bi-list-check text-indigo-600 dark:text-indigo-400"></i>
            Scan Findings
            <span class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
              {{ count($scanResult['findings']) }} checks
            </span>
          </h2>
        </div>

        @php
          $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
          $findings = collect($scanResult['findings'] ?? [])->sortBy(fn($f) => $severityOrder[$f['severity'] ?? 'info'] ?? 99);
        @endphp

        <div class="p-6 space-y-2">
          @foreach($findings as $finding)
          @php
            $sev = $finding['severity'] ?? 'info';
            $st = $finding['status'] ?? 'fail';

            // Severity styles for the badge
            $sevClasses = [
              'critical' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30',
              'high'     => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30',
              'medium'   => 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30',
              'low'      => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10',
              'info'     => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-500/30',
            ];
            $sevClass = $sevClasses[$sev] ?? $sevClasses['info'];

            // Row background by status
            $rowClass = $st === 'pass'
              ? 'bg-emerald-50/50 dark:bg-emerald-500/5 border-emerald-200/60 dark:border-emerald-500/20'
              : ($st === 'warning'
                  ? 'bg-amber-50/50 dark:bg-amber-500/5 border-amber-200/60 dark:border-amber-500/20'
                  : 'bg-rose-50/50 dark:bg-rose-500/5 border-rose-200/60 dark:border-rose-500/20');

            // Status icon
            $statusIcon = $st === 'pass' ? 'bi-check-circle-fill' : ($st === 'warning' ? 'bi-exclamation-circle-fill' : 'bi-x-circle-fill');
            $statusColor = $st === 'pass' ? 'text-emerald-600 dark:text-emerald-400'
                          : ($st === 'warning' ? 'text-amber-600 dark:text-amber-400'
                          : 'text-rose-600 dark:text-rose-400');
          @endphp
          <div class="flex items-start gap-3 rounded-xl px-4 py-3 border {{ $rowClass }}">
            <i class="bi {{ $statusIcon }} shrink-0 mt-0.5 text-base {{ $statusColor }}"></i>
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2 mb-1">
                <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $finding['name'] ?? $finding['title'] ?? 'Unknown' }}</span>
                <span class="text-[10px] font-bold tracking-wide uppercase px-2 py-0.5 rounded-md {{ $sevClass }}">{{ $sev }}</span>
              </div>
              <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $finding['message'] ?? $finding['description'] ?? '' }}</p>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
    @endif

    {{-- Block IP + Blocked IPs list --}}
    <div class="{{ $scanResult ? 'lg:col-span-5' : 'lg:col-span-12' }} space-y-6">

      {{-- Block IP Form --}}
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
        <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10">
          <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
            <i class="bi bi-ban text-rose-600 dark:text-rose-400"></i>
            Block IP Address
          </h2>
        </div>
        <div class="p-6">
          <form method="POST" action="{{ route('admin.security.block-ip') }}" class="space-y-3">
            @csrf
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">IP Address</label>
              <input type="text" name="ip" required
                     class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                     placeholder="e.g. 192.168.1.100">
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Reason <span class="font-normal text-slate-400">(optional)</span></label>
              <input type="text" name="reason"
                     class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                     placeholder="Suspicious activity, brute force, etc.">
            </div>
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-rose-600 hover:bg-rose-500 transition">
              <i class="bi bi-ban"></i> Block IP
            </button>
          </form>
        </div>
      </div>

      {{-- Blocked IPs Table --}}
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
        <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex items-center gap-2">
          <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
            <i class="bi bi-slash-circle text-indigo-600 dark:text-indigo-400"></i>
            Blocked IPs
          </h2>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">
            {{ $blockedIps->count() }}
          </span>
        </div>

        @if($blockedIps->isEmpty())
          <div class="p-6 text-center">
            <i class="bi bi-check2-circle text-3xl text-emerald-500 dark:text-emerald-400 mb-2"></i>
            <p class="text-sm text-slate-500 dark:text-slate-400">No blocked IPs.</p>
          </div>
        @else
          <div class="overflow-x-auto max-h-80">
            <table class="w-full text-sm">
              <thead class="sticky top-0 bg-slate-50 dark:bg-slate-800/60 backdrop-blur">
                <tr class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  <th class="text-left px-6 py-3">IP</th>
                  <th class="text-left px-3 py-3">Reason</th>
                  <th class="text-left px-3 py-3 whitespace-nowrap">Date</th>
                  <th class="text-right px-6 py-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                @foreach($blockedIps as $rule)
                <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition">
                  <td class="px-6 py-3">
                    <code class="text-xs font-mono text-slate-900 dark:text-slate-100">{{ $rule->ip }}</code>
                  </td>
                  <td class="px-3 py-3 text-xs text-slate-500 dark:text-slate-400">{{ Str::limit($rule->reason ?? '—', 30) }}</td>
                  <td class="px-3 py-3 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                    {{ \Carbon\Carbon::parse($rule->created_at)->format('d/m/y') }}
                  </td>
                  <td class="px-6 py-3 text-right">
                    <form method="POST" action="{{ route('admin.security.unblock-ip') }}"
                          onsubmit="return confirm('Unblock {{ $rule->ip }}?');" class="inline">
                      @csrf
                      <input type="hidden" name="ip" value="{{ $rule->ip }}">
                      <button type="submit"
                              title="Unblock"
                              class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition">
                        <i class="bi bi-check-lg"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

    </div>

  </div>

  {{-- ═══════════════════ Security Event Log ═══════════════════ --}}
  <div class="mt-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
    <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex items-center gap-2">
      <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
        <i class="bi bi-journal-text text-indigo-600 dark:text-indigo-400"></i>
        Recent Security Events
      </h2>
      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30">
        {{ $securityLogs->count() }}
      </span>
    </div>

    @if($securityLogs->isEmpty())
      <div class="p-8 text-center">
        <i class="bi bi-journal-x text-3xl text-slate-400 dark:text-slate-500 mb-2"></i>
        <p class="text-sm text-slate-500 dark:text-slate-400">No security events recorded.</p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-800/60">
            <tr class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
              <th class="text-left px-6 py-3 whitespace-nowrap">Time</th>
              <th class="text-left px-3 py-3">IP</th>
              <th class="text-left px-3 py-3">Event</th>
              <th class="text-left px-3 py-3">URI</th>
              <th class="text-left px-6 py-3">Details</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-white/5">
            @foreach($securityLogs as $log)
            @php
              $evtKey = strtolower($log->event_type ?? '');
              $evtClasses = [
                'blocked'    => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30',
                'sql_inject' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30',
                'xss'        => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30',
                'rate_limit' => 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30',
              ];
              $evtClass = $evtClasses[$evtKey] ?? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10';
            @endphp
            <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition">
              <td class="px-6 py-3 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                {{ \Carbon\Carbon::parse($log->created_at)->format('d/m H:i') }}
              </td>
              <td class="px-3 py-3">
                <code class="text-xs font-mono text-slate-900 dark:text-slate-100">{{ $log->ip }}</code>
              </td>
              <td class="px-3 py-3">
                <span class="inline-flex items-center text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md {{ $evtClass }}">
                  {{ $log->event_type ?? '—' }}
                </span>
              </td>
              <td class="px-3 py-3 text-xs text-slate-500 dark:text-slate-400" title="{{ $log->request_uri ?? '' }}">
                {{ Str::limit($log->request_uri ?? '—', 40) }}
              </td>
              <td class="px-6 py-3 text-xs text-slate-500 dark:text-slate-400">{{ Str::limit($log->details ?? '—', 50) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

</div>
@endsection
