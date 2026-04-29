@extends('layouts.admin')

@section('title', 'Geo Access Control')

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
        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/20">
          <i class="bi bi-globe2 text-white text-xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
            Geo Access Control
          </h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Country-based access rules · IP geolocation cache
          </p>
        </div>
      </div>
      <div>
        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold {{ $enabled ? 'bg-emerald-100 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
          <span class="w-2 h-2 rounded-full {{ $enabled ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
          {{ $enabled ? 'Enforcing' : 'Disabled' }}
        </span>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
      <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-lg"></i>
      <div class="flex-1 text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ session('success') }}</div>
    </div>
  @endif

  {{-- ═══════════════════ Cache Stats ═══════════════════ --}}
  <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl p-5 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Cached IPs</div>
      <div class="text-3xl font-bold text-slate-900 dark:text-white">{{ number_format($cacheStats['total_entries']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl p-5 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Fresh (24h)</div>
      <div class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($cacheStats['fresh_entries']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl p-5 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Stale (>7 days)</div>
      <div class="text-3xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($cacheStats['stale_entries']) }}</div>
    </div>
  </div>

  {{-- ═══════════════════ Settings Form ═══════════════════ --}}
  <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm mb-6 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10">
      <h3 class="text-base font-semibold text-slate-900 dark:text-white">Settings</h3>
    </div>
    <form method="POST" action="{{ route('admin.security.geo-access.update') }}" class="p-6 space-y-5">
      @csrf

      <div class="flex items-start gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-white/5">
        <input type="checkbox" name="enabled" id="enabled" value="1" {{ $enabled ? 'checked' : '' }}
               class="mt-1 w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500">
        <label for="enabled" class="flex-1 cursor-pointer">
          <span class="block text-sm font-semibold text-slate-900 dark:text-white">Enable geo-access enforcement</span>
          <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">When off, all countries are allowed regardless of mode/list below.</span>
        </label>
      </div>

      <div>
        <label class="block text-sm font-semibold text-slate-900 dark:text-white mb-2">Mode</label>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          @foreach([['allow_all','Allow All','Don\'t restrict by country'], ['allow_list','Allow List','Only countries in the list pass'], ['block_list','Block List','Countries in the list are blocked']] as [$value, $label, $desc])
            <label class="cursor-pointer p-4 rounded-xl border-2 transition {{ $mode === $value ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10' : 'border-slate-200 dark:border-white/10 hover:border-slate-300' }}">
              <input type="radio" name="mode" value="{{ $value }}" {{ $mode === $value ? 'checked' : '' }} class="sr-only">
              <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $label }}</span>
                @if($mode === $value)<i class="bi bi-check-circle-fill text-indigo-500"></i>@endif
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $desc }}</p>
            </label>
          @endforeach
        </div>
      </div>

      <div>
        <label class="block text-sm font-semibold text-slate-900 dark:text-white mb-2">Country codes (ISO 2-letter)</label>
        <input type="text" name="countries" value="{{ $countriesRaw }}"
               placeholder="TH,US,SG,JP"
               class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
          Comma-separated. Examples: <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800">TH</code>, <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800">US</code>, <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800">SG</code>.
        </p>
        @if(count($countries) > 0)
          <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach($countries as $cc)
              <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-mono font-semibold bg-indigo-100 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300">{{ $cc }}</span>
            @endforeach
          </div>
        @endif
      </div>

      <div class="flex justify-end pt-2">
        <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500">
          <i class="bi bi-check-lg"></i> Save Settings
        </button>
      </div>
    </form>
  </div>

  {{-- ═══════════════════ IP Lookup ═══════════════════ --}}
  <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm mb-6 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10">
      <h3 class="text-base font-semibold text-slate-900 dark:text-white">IP Diagnostic</h3>
      <p class="text-xs text-slate-500 mt-1">Look up any IP address — uses the same path as production traffic.</p>
    </div>
    <div class="p-6">
      <form method="POST" action="{{ route('admin.security.geo-access.lookup') }}" class="flex gap-2">
        @csrf
        <input type="text" name="ip" value="{{ old('ip') }}"
               placeholder="8.8.8.8"
               class="flex-1 px-4 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 font-mono">
        <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-slate-700 hover:bg-slate-600">
          <i class="bi bi-search"></i> Lookup
        </button>
      </form>

      @if(session('lookup_result'))
        @php $lr = session('lookup_result'); @endphp
        <div class="mt-4 p-4 rounded-xl border-2 {{ $lr['allowed'] ? 'border-emerald-300 bg-emerald-50 dark:bg-emerald-500/5 dark:border-emerald-500/30' : 'border-rose-300 bg-rose-50 dark:bg-rose-500/5 dark:border-rose-500/30' }}">
          <div class="flex items-center gap-2 mb-3">
            <i class="bi {{ $lr['allowed'] ? 'bi-check-circle-fill text-emerald-500' : 'bi-x-circle-fill text-rose-500' }} text-xl"></i>
            <span class="font-mono font-semibold">{{ $lr['ip'] }}</span>
            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $lr['allowed'] ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700' : 'bg-rose-100 dark:bg-rose-500/20 text-rose-700' }}">
              {{ $lr['allowed'] ? 'ALLOWED' : 'BLOCKED' }}
            </span>
          </div>
          @if($lr['data'])
          <dl class="grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
            <div>
              <dt class="text-xs uppercase tracking-widest text-slate-500">Country</dt>
              <dd class="font-mono font-semibold">{{ $lr['data']['country_code'] ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-widest text-slate-500">Name</dt>
              <dd class="font-medium">{{ $lr['data']['country_name'] ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-widest text-slate-500">Region</dt>
              <dd>{{ $lr['data']['region'] ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-widest text-slate-500">City</dt>
              <dd>{{ $lr['data']['city'] ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-widest text-slate-500">ISP</dt>
              <dd class="text-xs">{{ $lr['data']['isp'] ?? '—' }}</dd>
            </div>
          </dl>
          @else
            <p class="text-sm text-slate-500">No data returned (private IP or lookup failed).</p>
          @endif
        </div>
      @endif
    </div>
  </div>

  {{-- ═══════════════════ Top Countries + Cache Purge ═══════════════════ --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Top Countries (last 7 days)</h3>
      </div>
      @if($topCountries->count() > 0)
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">
            <tr>
              <th class="px-6 py-3 text-left">Code</th>
              <th class="px-6 py-3 text-left">Country</th>
              <th class="px-6 py-3 text-right">IPs Seen</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-white/5">
            @foreach($topCountries as $row)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
              <td class="px-6 py-3 font-mono font-semibold">{{ $row->country_code }}</td>
              <td class="px-6 py-3">{{ $row->country_name }}</td>
              <td class="px-6 py-3 text-right font-mono">{{ number_format($row->ip_count) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @else
        <div class="p-8 text-center text-slate-500 text-sm">No data yet — cache fills as visitors arrive.</div>
      @endif
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Purge Cache</h3>
      </div>
      <form method="POST" action="{{ route('admin.security.geo-access.purge-cache') }}" class="p-6 space-y-3">
        @csrf
        <label class="block text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">Older than</label>
        <div class="flex gap-2">
          <input type="number" name="days" value="7" min="1" max="365"
                 class="w-20 px-3 py-2 text-sm rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 font-mono">
          <span class="self-center text-sm text-slate-600 dark:text-slate-400">days</span>
        </div>
        <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-rose-600 hover:bg-rose-500">
          <i class="bi bi-trash"></i> Purge Stale Entries
        </button>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          Stale entries are re-fetched on next visit. ip-api.com offers ~45 lookups/min free.
        </p>
      </form>
    </div>

  </div>

</div>
@endsection
