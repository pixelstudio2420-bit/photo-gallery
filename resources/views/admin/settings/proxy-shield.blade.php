@extends('layouts.admin')

@section('title', 'Proxy Shield')

@push('styles')
@include('admin.settings._shared-styles')
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16" x-data="{ activeTab: 'settings' }">

  {{-- ═══ Page Header ═══ --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center shadow-lg shadow-indigo-500/30">
        <i class="bi bi-shield-shaded text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Proxy Shield</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Detect and block proxy, VPN, TOR, and datacenter traffic.
        </p>
      </div>
    </div>
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg
              bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10
              text-slate-700 dark:text-slate-200
              hover:bg-slate-50 dark:hover:bg-slate-700 transition">
      <i class="bi bi-arrow-left"></i> Back to Settings
    </a>
  </div>

  {{-- ═══ Flash Messages ═══ --}}
  @if(session('success'))
  <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl
              bg-emerald-50 dark:bg-emerald-500/10
              text-emerald-700 dark:text-emerald-300
              border border-emerald-200 dark:border-emerald-500/30 text-sm">
    <i class="bi bi-check-circle-fill"></i>
    <span>{{ session('success') }}</span>
  </div>
  @endif
  @if(session('error'))
  <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl
              bg-rose-50 dark:bg-rose-500/10
              text-rose-700 dark:text-rose-300
              border border-rose-200 dark:border-rose-500/30 text-sm">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span>{{ session('error') }}</span>
  </div>
  @endif

  {{-- ═══ Tabs container ═══ --}}
  <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">

    {{-- Tab Buttons --}}
    <div class="flex items-center border-b border-slate-200 dark:border-white/10 px-2">
      <button type="button" @click="activeTab = 'settings'"
              :class="activeTab === 'settings' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
              class="px-4 py-3 text-sm font-medium border-b-2 transition">
        <i class="bi bi-gear mr-1"></i> Settings
      </button>
      <button type="button" @click="activeTab = 'log'"
              :class="activeTab === 'log' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
              class="px-4 py-3 text-sm font-medium border-b-2 transition">
        <i class="bi bi-journal-text mr-1"></i> Detection Log
        @if($stats['total'] > 0)
          <span class="ml-1 inline-flex items-center justify-center min-w-[22px] h-[18px] px-1.5 rounded-full text-[10px] font-semibold bg-indigo-600 text-white">{{ $stats['total'] }}</span>
        @endif
      </button>
    </div>

    {{-- ══════════ SETTINGS TAB ══════════ --}}
    <div x-show="activeTab === 'settings'" x-cloak>
      <form method="POST" action="{{ route('admin.settings.proxy-shield.update') }}">
        @csrf
        <div class="p-5">

          {{-- Master Enable --}}
          <div class="rounded-xl p-4 mb-5 flex items-center justify-between gap-4
                      bg-indigo-50 dark:bg-indigo-500/10
                      border border-indigo-200 dark:border-indigo-500/30">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-indigo-100 dark:bg-indigo-500/15">
                <i class="bi bi-power text-indigo-600 dark:text-indigo-400 text-lg"></i>
              </div>
              <div>
                <h3 class="font-bold text-slate-900 dark:text-white">Enable Proxy Shield</h3>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                  Activate detection and protection against proxy / VPN visitors.
                </p>
              </div>
            </div>
            <label class="tw-switch">
              <input type="checkbox" name="proxy_shield_enabled" id="proxy_shield_enabled" value="1"
                     {{ ($settings['proxy_shield_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
              <span class="tw-switch-track"></span>
              <span class="tw-switch-knob"></span>
            </label>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            {{-- ── Detection Methods ── --}}
            <div class="rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-slate-800/40">
              <div class="px-4 py-3 border-b border-slate-200 dark:border-white/10">
                <h3 class="font-semibold text-slate-900 dark:text-white">
                  <i class="bi bi-radar mr-2 text-indigo-600 dark:text-indigo-400"></i>Detection Methods
                </h3>
              </div>
              @php
              $detectionToggles = [
                ['key' => 'proxy_detect_headers',    'label' => 'HTTP Header Detection',    'desc' => 'Inspect X-Forwarded-For and related headers'],
                ['key' => 'proxy_detect_tor',        'label' => 'TOR Exit Node Detection',  'desc' => 'Match against known TOR exit node IP list'],
                ['key' => 'proxy_detect_vpn',        'label' => 'VPN Detection',            'desc' => 'Identify common VPN provider IP ranges'],
                ['key' => 'proxy_detect_datacenter', 'label' => 'Datacenter IP Detection',  'desc' => 'Flag ASNs belonging to cloud / hosting providers'],
                ['key' => 'proxy_detect_anomalies',  'label' => 'Anomaly Detection',        'desc' => 'Detect suspicious behavioural patterns'],
                ['key' => 'proxy_client_detection',  'label' => 'Client-side JS Detection', 'desc' => 'Use JavaScript checks to detect WebRTC leaks'],
              ];
              @endphp
              <div class="divide-y divide-slate-200 dark:divide-white/10">
                @foreach($detectionToggles as $t)
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                  <div class="min-w-0">
                    <div class="font-medium text-sm text-slate-900 dark:text-white">{{ $t['label'] }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $t['desc'] }}</div>
                  </div>
                  <label class="tw-switch">
                    <input type="checkbox" name="{{ $t['key'] }}" id="{{ $t['key'] }}" value="1"
                           {{ ($settings[$t['key']] ?? '0') === '1' ? 'checked' : '' }}>
                    <span class="tw-switch-track"></span>
                    <span class="tw-switch-knob"></span>
                  </label>
                </div>
                @endforeach
              </div>
            </div>

            {{-- ── Actions + Auto-Block ── --}}
            <div class="space-y-4">

              {{-- Action on Detection --}}
              <div class="rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-slate-800/40">
                <div class="px-4 py-3 border-b border-slate-200 dark:border-white/10">
                  <h3 class="font-semibold text-slate-900 dark:text-white">
                    <i class="bi bi-shield-exclamation mr-2 text-indigo-600 dark:text-indigo-400"></i>Action on Detection
                  </h3>
                </div>
                <div class="p-4">
                  <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">When a proxy / VPN is detected</label>
                  <select name="proxy_action"
                          class="w-full px-3 py-2 rounded-lg text-sm
                                 bg-white dark:bg-slate-800
                                 border border-slate-300 dark:border-white/10
                                 text-slate-900 dark:text-slate-100
                                 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    <option value="monitor" {{ ($settings['proxy_action'] ?? 'monitor') === 'monitor' ? 'selected' : '' }}>Monitor Only (log but allow)</option>
                    <option value="block" {{ ($settings['proxy_action'] ?? '') === 'block' ? 'selected' : '' }}>Block Access</option>
                  </select>
                </div>
              </div>

              {{-- Auto-Block Rules --}}
              <div class="rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-slate-800/40">
                <div class="px-4 py-3 border-b border-slate-200 dark:border-white/10">
                  <h3 class="font-semibold text-slate-900 dark:text-white">
                    <i class="bi bi-ban mr-2 text-rose-500"></i>Auto-Block Rules
                  </h3>
                </div>
                <div class="divide-y divide-slate-200 dark:divide-white/10">
                  <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div>
                      <div class="font-medium text-sm text-slate-900 dark:text-white">Always Block TOR</div>
                      <div class="text-xs text-slate-500 dark:text-slate-400">Block all TOR exit node IPs regardless of action setting</div>
                    </div>
                    <label class="tw-switch">
                      <input type="checkbox" name="proxy_block_tor" id="proxy_block_tor" value="1"
                             {{ ($settings['proxy_block_tor'] ?? '0') === '1' ? 'checked' : '' }}>
                      <span class="tw-switch-track"></span>
                      <span class="tw-switch-knob"></span>
                    </label>
                  </div>
                  <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div>
                      <div class="font-medium text-sm text-slate-900 dark:text-white">Always Block Datacenters</div>
                      <div class="text-xs text-slate-500 dark:text-slate-400">Block all known datacenter / cloud hosting IPs</div>
                    </div>
                    <label class="tw-switch">
                      <input type="checkbox" name="proxy_block_datacenter" id="proxy_block_datacenter" value="1"
                             {{ ($settings['proxy_block_datacenter'] ?? '0') === '1' ? 'checked' : '' }}>
                      <span class="tw-switch-track"></span>
                      <span class="tw-switch-knob"></span>
                    </label>
                  </div>
                </div>
              </div>

              {{-- Cache TTL --}}
              <div class="rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-slate-800/40 p-4">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="proxy_cache_ttl">
                  <i class="bi bi-clock mr-1 text-indigo-600 dark:text-indigo-400"></i>Detection Cache TTL
                </label>
                <div class="flex max-w-[220px]">
                  <input type="number" name="proxy_cache_ttl" id="proxy_cache_ttl"
                         min="1" max="168"
                         value="{{ $settings['proxy_cache_ttl'] ?: '24' }}"
                         class="flex-1 px-3 py-2 rounded-l-lg text-sm
                                bg-white dark:bg-slate-800
                                border border-slate-300 dark:border-white/10
                                text-slate-900 dark:text-slate-100
                                focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                  <span class="px-3 py-2 text-sm rounded-r-lg
                               bg-slate-100 dark:bg-slate-700
                               text-slate-500 dark:text-slate-400
                               border border-l-0 border-slate-300 dark:border-white/10">hrs</span>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Cache IP reputation results to reduce API calls</div>
              </div>
            </div>
          </div>

          {{-- Submit --}}
          <div class="flex justify-end mt-5">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg text-sm font-semibold text-white
                           bg-gradient-to-r from-indigo-600 to-violet-600
                           hover:from-indigo-500 hover:to-violet-500
                           shadow-md shadow-indigo-500/30 transition">
              <i class="bi bi-save"></i> Save Proxy Shield Settings
            </button>
          </div>
        </div>
      </form>
    </div>

    {{-- ══════════ DETECTION LOG TAB ══════════ --}}
    <div x-show="activeTab === 'log'" x-cloak>
      <div class="p-5">

        {{-- Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
          <div class="rounded-xl p-4 border border-indigo-200 dark:border-indigo-500/30 bg-indigo-50 dark:bg-indigo-500/10">
            <div class="text-3xl font-black text-indigo-700 dark:text-indigo-300">{{ number_format($stats['total']) }}</div>
            <div class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-1">Total Detections</div>
          </div>
          <div class="rounded-xl p-4 border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10">
            <div class="text-3xl font-black text-rose-700 dark:text-rose-300">{{ number_format($stats['blocked']) }}</div>
            <div class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-1">Blocked</div>
          </div>
          <div class="rounded-xl p-4 border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10">
            <div class="text-3xl font-black text-amber-700 dark:text-amber-300">{{ number_format($stats['monitored']) }}</div>
            <div class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-1">Monitored</div>
          </div>
        </div>

        {{-- Table --}}
        @if($detectionLogs->isEmpty())
          <div class="py-10 text-center text-slate-500 dark:text-slate-400">
            <i class="bi bi-journal-x text-4xl opacity-40 block mb-3"></i>
            <p class="text-sm">ยังไม่มีข้อมูล</p>
            <p class="text-xs mt-1">Detection logs will appear here once Proxy Shield starts recording activity.</p>
          </div>
        @else
          <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-white/10">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase tracking-wide">
                <tr class="text-slate-500 dark:text-slate-400">
                  <th class="px-4 py-3 text-left font-semibold">Date / Time</th>
                  <th class="px-4 py-3 text-left font-semibold">IP Address</th>
                  <th class="px-4 py-3 text-left font-semibold">Type</th>
                  <th class="px-4 py-3 text-left font-semibold">Action</th>
                  <th class="px-4 py-3 text-left font-semibold">Country</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                @foreach($detectionLogs as $log)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                  <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                    {{ isset($log->created_at) ? \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i') : '-' }}
                  </td>
                  <td class="px-4 py-2.5">
                    <code class="text-xs text-slate-700 dark:text-slate-200">{{ $log->ip_address ?? '-' }}</code>
                  </td>
                  <td class="px-4 py-2.5">
                    @php $type = $log->detection_type ?? $log->type ?? '-'; @endphp
                    <span class="inline-block px-2 py-0.5 rounded-md text-xs font-medium
                                 bg-indigo-100 dark:bg-indigo-500/15
                                 text-indigo-700 dark:text-indigo-300">{{ $type }}</span>
                  </td>
                  <td class="px-4 py-2.5">
                    @php $action = $log->action ?? '-'; @endphp
                    @if($action === 'block')
                      <span class="inline-block px-2 py-0.5 rounded-md text-xs font-medium
                                   bg-rose-100 dark:bg-rose-500/15
                                   text-rose-700 dark:text-rose-300">Blocked</span>
                    @elseif($action === 'monitor')
                      <span class="inline-block px-2 py-0.5 rounded-md text-xs font-medium
                                   bg-amber-100 dark:bg-amber-500/15
                                   text-amber-700 dark:text-amber-300">Monitored</span>
                    @else
                      <span class="text-slate-500 dark:text-slate-400">{{ $action }}</span>
                    @endif
                  </td>
                  <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $log->country ?? '-' }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>

  </div>
</div>
@endsection
