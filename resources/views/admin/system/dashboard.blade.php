@extends('layouts.admin')

@section('title', 'System Monitor')

@php
  use App\Services\SystemMonitorService;
  $b = fn($n) => SystemMonitorService::formatBytes((int) $n);
  $s = $snapshot;
@endphp

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-speedometer2 mr-2" style="color:#0ea5e9;"></i>System Monitor
    <span id="sm-ts" class="text-xs font-normal text-gray-400 ml-2">updated: —</span>
  </h4>
  <div class="flex gap-2">
    <a href="{{ route('admin.system.readiness') }}" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(16,185,129,0.08);color:#059669;border-radius:8px;font-weight:500;padding:0.4rem 1rem;">
      <i class="bi bi-shield-check mr-1"></i> Production Readiness
    </a>
    <button type="button" onclick="smRefresh()" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;font-weight:500;padding:0.4rem 1rem;">
      <i class="bi bi-arrow-clockwise mr-1"></i> Refresh
    </button>
  </div>
</div>

{{-- ── Top-line KPIs ──────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="text-xs text-gray-500 mb-1"><i class="bi bi-images mr-1"></i>Photos</div>
    <div class="text-2xl font-bold" id="kpi-photos">{{ number_format($s['data']['photos_active']) }}</div>
    <div class="text-[11px] text-gray-400">+{{ number_format($s['data']['new_photos_24h']) }} ใน 24 ชม.</div>
  </div>
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="text-xs text-gray-500 mb-1"><i class="bi bi-calendar-event mr-1"></i>Events</div>
    <div class="text-2xl font-bold" id="kpi-events">{{ number_format($s['data']['events_active']) }}</div>
    <div class="text-[11px] text-gray-400">+{{ number_format($s['data']['new_events_24h']) }} ใน 24 ชม.</div>
  </div>
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="text-xs text-gray-500 mb-1"><i class="bi bi-download mr-1"></i>Downloads (24h)</div>
    <div class="text-2xl font-bold text-sky-600" id="kpi-downloads">{{ number_format($s['downloads']['downloads_today']) }}</div>
    <div class="text-[11px] text-gray-400">all-time: {{ number_format($s['downloads']['downloads_all']) }}</div>
  </div>
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="text-xs text-gray-500 mb-1"><i class="bi bi-bag-check mr-1"></i>Orders (paid)</div>
    <div class="text-2xl font-bold text-emerald-600" id="kpi-orders">{{ number_format($s['data']['orders_paid']) }}</div>
    <div class="text-[11px] text-gray-400">+{{ number_format($s['data']['new_orders_24h']) }} ใน 24 ชม.</div>
  </div>
</div>

{{-- ── Server / DB / Cache / Queue row ────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
  {{-- Server --}}
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="flex items-center justify-between mb-2">
      <h6 class="font-semibold"><i class="bi bi-cpu mr-1 text-indigo-500"></i>Server</h6>
      <span class="text-[10px] px-2 py-0.5 rounded" style="background:{{ $s['server']['app_env'] === 'production' ? 'rgba(16,185,129,0.15);color:#059669' : 'rgba(245,158,11,0.15);color:#d97706' }};">
        {{ strtoupper($s['server']['app_env']) }}
      </span>
    </div>
    <table class="text-xs w-full">
      <tr><td class="text-gray-500 py-0.5">PHP</td><td class="text-right font-mono">{{ $s['server']['php_version'] }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">Laravel</td><td class="text-right font-mono">{{ $s['server']['laravel_version'] }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">OS</td><td class="text-right font-mono">{{ $s['server']['os'] }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">Memory</td><td class="text-right font-mono" id="sv-mem">{{ $b($s['server']['memory']['current']) }} / {{ $s['server']['memory']['limit_display'] }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">Disk free</td><td class="text-right font-mono" id="sv-disk">{{ $b($s['server']['disk']['free']) }}</td></tr>
      @if($s['server']['load_avg'])
      <tr><td class="text-gray-500 py-0.5">Load</td><td class="text-right font-mono">{{ implode(' / ', array_map(fn($l) => number_format($l, 2), $s['server']['load_avg'])) }}</td></tr>
      @endif
      <tr><td class="text-gray-500 py-0.5">OPcache</td><td class="text-right">
        @if($s['server']['opcache']['enabled'] ?? false)
          <span class="text-emerald-600">✓ {{ $s['server']['opcache']['hit_rate'] }}%</span>
        @else
          <span class="text-amber-500">off</span>
        @endif
      </td></tr>
    </table>
  </div>

  {{-- Database --}}
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="flex items-center justify-between mb-2">
      <h6 class="font-semibold"><i class="bi bi-database mr-1 text-amber-500"></i>Database</h6>
      <span class="text-[10px] px-2 py-0.5 rounded" style="background:{{ $s['database']['connected'] ?? false ? 'rgba(16,185,129,0.15);color:#059669' : 'rgba(239,68,68,0.15);color:#dc2626' }};">
        {{ ($s['database']['connected'] ?? false) ? 'ONLINE' : 'DOWN' }}
      </span>
    </div>
    <table class="text-xs w-full">
      <tr><td class="text-gray-500 py-0.5">Driver</td><td class="text-right font-mono">{{ $s['database']['driver'] }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">Version</td><td class="text-right font-mono">{{ $s['database']['version'] ?? '—' }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">Total size</td><td class="text-right font-mono" id="db-size">{{ $b($s['database']['total_bytes'] ?? 0) }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">Connections</td><td class="text-right font-mono" id="db-conn">{{ $s['database']['connections'] ?? 0 }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">Slow queries</td><td class="text-right font-mono">{{ $s['database']['slow_queries'] ?? 0 }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">Uptime</td><td class="text-right font-mono">{{ gmdate('d\d H\h', $s['database']['uptime_sec'] ?? 0) }}</td></tr>
    </table>
  </div>

  {{-- Cache --}}
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="flex items-center justify-between mb-2">
      <h6 class="font-semibold"><i class="bi bi-lightning-charge mr-1 text-yellow-500"></i>Cache</h6>
      <span class="text-[10px] px-2 py-0.5 rounded" style="background:{{ $s['cache']['ok'] ? 'rgba(16,185,129,0.15);color:#059669' : 'rgba(239,68,68,0.15);color:#dc2626' }};">
        {{ $s['cache']['ok'] ? 'OK' : 'DOWN' }}
      </span>
    </div>
    <table class="text-xs w-full">
      <tr><td class="text-gray-500 py-0.5">Driver</td><td class="text-right font-mono">{{ $s['cache']['driver'] }}</td></tr>
      @if($s['cache']['redis_memory'])
      <tr><td class="text-gray-500 py-0.5">Redis mem</td><td class="text-right font-mono">{{ $b($s['cache']['redis_memory']) }}</td></tr>
      @endif
      <tr><td class="text-gray-500 py-0.5">Session</td><td class="text-right font-mono">{{ config('session.driver') }}</td></tr>
    </table>
    <div class="mt-3 pt-2 border-t border-gray-100">
      <h6 class="font-semibold text-xs mb-1"><i class="bi bi-hdd-network mr-1"></i>Queue</h6>
      <table class="text-xs w-full">
        <tr><td class="text-gray-500 py-0.5">Driver</td><td class="text-right font-mono">{{ $s['queue']['driver'] }}</td></tr>
        <tr><td class="text-gray-500 py-0.5">Pending</td><td class="text-right font-mono" id="q-pending">{{ number_format($s['queue']['pending']) }}</td></tr>
        <tr><td class="text-gray-500 py-0.5">Failed</td><td class="text-right font-mono {{ $s['queue']['failed'] > 0 ? 'text-red-600' : '' }}" id="q-failed">{{ number_format($s['queue']['failed']) }}</td></tr>
      </table>
    </div>
  </div>

  {{-- Downloads --}}
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="flex items-center justify-between mb-2">
      <h6 class="font-semibold"><i class="bi bi-cloud-download mr-1 text-sky-500"></i>Downloads</h6>
      <span class="text-[10px] px-2 py-0.5 rounded" style="background:rgba(14,165,233,0.15);color:#0284c7;">LIVE</span>
    </div>
    <table class="text-xs w-full">
      <tr><td class="text-gray-500 py-0.5">Today</td><td class="text-right font-mono" id="dl-today">{{ number_format($s['downloads']['downloads_today']) }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">7 days</td><td class="text-right font-mono">{{ number_format($s['downloads']['downloads_7d']) }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">All time</td><td class="text-right font-mono">{{ number_format($s['downloads']['downloads_all']) }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">Active tokens</td><td class="text-right font-mono">{{ number_format($s['downloads']['tokens_active']) }}</td></tr>
      <tr><td class="text-gray-500 py-0.5">ZIP queue</td><td class="text-right font-mono {{ $s['downloads']['zip_pending'] > 10 ? 'text-amber-600' : '' }}" id="dl-zip">{{ number_format($s['downloads']['zip_pending']) }}</td></tr>
    </table>
  </div>
</div>

{{-- ── Storage per Driver ─────────────────────────────────────────── --}}
<div class="card border-0 mb-4 p-5" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
  <div class="flex items-center justify-between mb-3">
    <h6 class="font-semibold mb-0"><i class="bi bi-hdd-stack mr-1 text-purple-500"></i>Storage ต่อ Driver</h6>
    <span class="text-xs text-gray-500">
      Primary: <code>{{ strtoupper($s['storage']['resolved']['primary']) }}</code> ·
      Upload: <code>{{ strtoupper($s['storage']['resolved']['upload']) }}</code> ·
      ZIP: <code>{{ strtoupper($s['storage']['resolved']['zip']) }}</code>
    </span>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    @foreach($s['storage']['drivers'] as $dname => $di)
      @php
        $labels = ['r2' => 'Cloudflare R2', 's3' => 'AWS S3', 'drive' => 'Google Drive', 'public' => 'Local Disk'];
        $icons = ['r2' => 'bi-cloud', 's3' => 'bi-amazon', 'drive' => 'bi-google', 'public' => 'bi-hdd'];
        $color = $di['enabled'] ? '#0ea5e9' : '#9ca3af';
      @endphp
      <div class="p-3 rounded-lg border {{ $di['enabled'] ? 'border-sky-200' : 'border-gray-200' }}">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm font-semibold" style="color:{{ $color }};">
            <i class="bi {{ $icons[$dname] }} mr-1"></i> {{ $labels[$dname] }}
          </div>
          <span class="text-[10px] px-1.5 py-0.5 rounded" style="background:{{ $di['enabled'] ? 'rgba(16,185,129,0.15);color:#059669' : 'rgba(156,163,175,0.15);color:#6b7280' }};">
            {{ $di['enabled'] ? 'ON' : 'OFF' }}
          </span>
        </div>
        <div class="text-lg font-bold">{{ number_format($di['photo_count']) }} <span class="text-xs font-normal text-gray-400">photos</span></div>
        <div class="text-xs text-gray-500">{{ $b($di['total_bytes']) }}</div>
        @if($di['growth_24h'] > 0 || $di['growth_7d'] > 0)
        <div class="mt-2 text-[11px] text-gray-400">
          +{{ $b($di['growth_24h']) }} (24h) · +{{ $b($di['growth_7d']) }} (7d)
        </div>
        @endif
      </div>
    @endforeach
  </div>

  {{-- Local disk usage bar --}}
  @php
    $freeLocal = (int) $s['storage']['local_disk']['free'];
    $totalLocal = (int) $s['storage']['local_disk']['total'];
    $usedLocal = max(0, $totalLocal - $freeLocal);
    $pctLocal = $totalLocal > 0 ? round($usedLocal / $totalLocal * 100, 1) : 0;
    $barColor = $pctLocal > 90 ? '#dc2626' : ($pctLocal > 75 ? '#f59e0b' : '#10b981');
  @endphp
  @if($totalLocal > 0)
  <div class="mt-4 pt-3 border-t border-gray-100">
    <div class="flex justify-between text-xs mb-1">
      <span class="text-gray-500"><i class="bi bi-device-hdd mr-1"></i>Local disk ({{ $s['server']['hostname'] }})</span>
      <span class="font-mono">{{ $b($usedLocal) }} / {{ $b($totalLocal) }} · {{ $pctLocal }}%</span>
    </div>
    <div class="w-full h-2 rounded-full bg-gray-200 overflow-hidden">
      <div class="h-full rounded-full" style="width:{{ min(100, $pctLocal) }}%;background:{{ $barColor }};"></div>
    </div>
    @if($pctLocal > 85)
      <div class="mt-2 text-xs text-amber-600"><i class="bi bi-exclamation-triangle mr-1"></i>Disk เต็มเกิน 85% — ย้ายไป R2 ด้วย <code>photos:migrate-storage</code></div>
    @endif
  </div>
  @endif
</div>

{{-- ── Top events + DB tables ─────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
  {{-- Top events --}}
  <div class="card border-0 p-5" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <h6 class="font-semibold mb-3"><i class="bi bi-trophy mr-1 text-yellow-500"></i>อีเวนต์ขายดี 5 อันดับ</h6>
    @if(count($s['downloads']['top_events']) > 0)
      <table class="text-sm w-full">
        <thead class="text-xs text-gray-500 border-b border-gray-100">
          <tr><th class="text-left pb-1">#</th><th class="text-left">Event</th><th class="text-right">Orders</th></tr>
        </thead>
        <tbody>
          @foreach($s['downloads']['top_events'] as $i => $ev)
            <tr class="border-t border-gray-50">
              <td class="py-1 text-gray-400">{{ $i + 1 }}</td>
              <td class="py-1 truncate max-w-[200px]">{{ $ev['name'] }}</td>
              <td class="py-1 text-right font-mono font-semibold">{{ number_format($ev['orders']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="text-xs text-gray-400 py-4 text-center">ยังไม่มีข้อมูล</div>
    @endif
  </div>

  {{-- DB table sizes --}}
  <div class="card border-0 p-5" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <h6 class="font-semibold mb-3"><i class="bi bi-table mr-1 text-indigo-500"></i>ตาราง DB ใหญ่สุด 10 อันดับ</h6>
    <table class="text-sm w-full">
      <thead class="text-xs text-gray-500 border-b border-gray-100">
        <tr><th class="text-left pb-1">Table</th><th class="text-right">Rows</th><th class="text-right">Size</th></tr>
      </thead>
      <tbody>
        @foreach(array_slice($s['database']['tables_top15'] ?? [], 0, 10) as $t)
          <tr class="border-t border-gray-50">
            <td class="py-1 font-mono text-[11px]">{{ $t['name'] }}</td>
            <td class="py-1 text-right font-mono text-xs">{{ number_format($t['rows']) }}</td>
            <td class="py-1 text-right font-mono text-xs text-gray-600">{{ $b($t['bytes']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- ── Queue breakdown ────────────────────────────────────────────── --}}
@if(!empty($s['queue']['by_queue']))
<div class="card border-0 p-5 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
  <h6 class="font-semibold mb-3"><i class="bi bi-list-task mr-1 text-pink-500"></i>Queue breakdown</h6>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="queue-breakdown">
    @foreach($s['queue']['by_queue'] as $qname => $count)
      <div class="p-3 rounded-lg border border-gray-200">
        <div class="text-xs text-gray-500">{{ $qname }}</div>
        <div class="text-xl font-bold">{{ number_format($count) }}</div>
      </div>
    @endforeach
  </div>
  @if(($s['queue']['oldest_pending_s'] ?? 0) > 300)
    <div class="mt-3 text-xs text-amber-600"><i class="bi bi-clock-history mr-1"></i>
      Job ที่รอนานสุด: {{ gmdate('H\h i\m', $s['queue']['oldest_pending_s']) }} — worker อาจจะหยุด
    </div>
  @endif
</div>
@endif

<div class="text-xs text-gray-400 text-right">
  <i class="bi bi-arrow-repeat"></i> Auto-refresh ทุก 30 วินาที · cache TTL 60s
</div>

<script>
  const SM_ENDPOINT = '{{ route('admin.system.api.snapshot') }}';
  const SM_REFRESH  = '{{ route('admin.system.refresh') }}';

  function fmtBytes(b) {
    if (!b || b < 0) return '—';
    const u = ['B','KB','MB','GB','TB'];
    let p = Math.min(Math.floor(Math.log(b)/Math.log(1024)), u.length - 1);
    return (b / Math.pow(1024, p)).toFixed(1) + ' ' + u[p];
  }

  async function smPull() {
    try {
      const res = await fetch(SM_ENDPOINT);
      const d = await res.json();
      document.getElementById('sm-ts').textContent = 'updated: ' + new Date(d.generated_at).toLocaleTimeString();
      // KPIs
      document.getElementById('kpi-photos').textContent = d.data.photos_active.toLocaleString();
      document.getElementById('kpi-events').textContent = d.data.events_active.toLocaleString();
      document.getElementById('kpi-downloads').textContent = d.downloads.downloads_today.toLocaleString();
      document.getElementById('kpi-orders').textContent   = d.data.orders_paid.toLocaleString();
      // Server
      document.getElementById('sv-mem').textContent  = fmtBytes(d.server.memory.current) + ' / ' + d.server.memory.limit_display;
      document.getElementById('sv-disk').textContent = fmtBytes(d.server.disk.free);
      // DB
      document.getElementById('db-size').textContent = fmtBytes(d.database.total_bytes || 0);
      document.getElementById('db-conn').textContent = d.database.connections || 0;
      // Queue
      document.getElementById('q-pending').textContent = d.queue.pending.toLocaleString();
      document.getElementById('q-failed').textContent  = d.queue.failed.toLocaleString();
      // Downloads
      document.getElementById('dl-today').textContent = d.downloads.downloads_today.toLocaleString();
      document.getElementById('dl-zip').textContent   = d.downloads.zip_pending.toLocaleString();
    } catch (e) {
      console.warn('sysmon pull failed', e);
    }
  }

  async function smRefresh() {
    await fetch(SM_REFRESH, { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } }).catch(() => {});
    await smPull();
  }

  // Tick
  setInterval(smPull, 30000);
  smPull();
</script>
@endsection
