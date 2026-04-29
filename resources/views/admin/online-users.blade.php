@extends('layouts.admin')

@section('title', 'ผู้ใช้ออนไลน์')

@push('styles')
<style>
  @keyframes pulse-green {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
  }
  .pulse-green-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    animation: pulse-green 1.5s ease-in-out infinite;
  }
  .online-left-border {
    border-left: 3px solid #22c55e;
  }

  /* ═══ Stat Cards ═══ */
  .stat-card {
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid rgba(0,0,0,0.04);
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
  }

  /* ═══ Dark Mode ═══ */
  .dark .stat-card {
    background: linear-gradient(145deg, #1e293b 0%, #1a2332 100%) !important;
    box-shadow: 0 4px 20px rgba(0,0,0,0.35), inset 0 1px 0 rgba(255,255,255,0.04);
    border-color: rgba(255,255,255,0.08);
  }
  .dark .stat-card:hover {
    box-shadow: 0 8px 28px rgba(0,0,0,0.45);
  }
  .dark .stat-card .stat-value { color: #ffffff !important; }
  .dark .stat-card .stat-label { color: #94a3b8 !important; }

  .dark .ou-main-card {
    background: linear-gradient(145deg, #1e293b 0%, #1a2332 100%) !important;
    box-shadow: 0 4px 20px rgba(0,0,0,0.35), inset 0 1px 0 rgba(255,255,255,0.04);
    border-color: rgba(255,255,255,0.08);
  }
  .dark .ou-main-card thead { background: rgba(99,102,241,0.1) !important; }
  .dark .ou-main-card thead th { color: #e2e8f0 !important; }
  .dark .ou-main-card tr:hover { background: rgba(99,102,241,0.08) !important; }
  .dark .ou-main-card td { color: #e2e8f0; }
  .dark .ou-main-card td .text-gray-500 { color: #94a3b8 !important; }
  .dark .ou-main-card .border-gray-100 { border-color: rgba(255,255,255,0.06) !important; }

  /* Refresh spin animation */
  @keyframes spin-refresh {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }
  .spin-refresh { animation: spin-refresh 1s linear infinite; }
</style>
@endpush

@section('content')
{{-- ═══════════════════════════════════════════
     Header
     ═══════════════════════════════════════════ --}}
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
  <div class="flex items-center gap-3">
    <h4 class="font-bold mb-0 tracking-tight flex items-center gap-2">
      <i class="bi bi-broadcast mr-1" style="color:#22c55e; font-size:1.3rem;"></i>
      <span>ผู้ใช้ออนไลน์</span>
      <span class="inline-flex items-center gap-1.5 ml-1">
        <span class="pulse-green-dot"></span>
        <span class="inline-flex items-center justify-center text-xs font-semibold text-white px-2.5 py-0.5 rounded-full"
              id="online-count-badge"
              style="background:#22c55e; min-width:26px;">
          {{ $onlineCount }}
        </span>
      </span>
    </h4>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
    <span class="text-gray-400 dark:text-gray-500 text-xs flex items-center gap-1" id="refresh-indicator">
      <i class="bi bi-arrow-repeat" id="refresh-icon"></i>
      <span id="refresh-text">รีเฟรชอัตโนมัติ 30 วินาที</span>
    </span>
    <button type="button" onclick="manualRefresh()"
            class="inline-flex items-center text-sm font-medium px-3 py-1.5 rounded-lg transition
                   bg-green-50 text-green-600 hover:bg-green-100
                   dark:bg-green-500/10 dark:text-green-400 dark:hover:bg-green-500/20"
            id="btn-refresh">
      <i class="bi bi-arrow-clockwise mr-1"></i> รีเฟรช
    </button>
    <a href="{{ route('admin.dashboard') }}"
       class="inline-flex items-center text-sm font-medium px-4 py-1.5 rounded-lg transition
              bg-indigo-50 text-indigo-600 hover:bg-indigo-100
              dark:bg-indigo-500/10 dark:text-indigo-400 dark:hover:bg-indigo-500/20">
      <i class="bi bi-arrow-left mr-1"></i> แดชบอร์ด
    </a>
  </div>
</div>

{{-- ═══════════════════════════════════════════
     Stats Row
     ═══════════════════════════════════════════ --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6" id="stats-row">
  {{-- Online Total --}}
  <div class="stat-card bg-white dark:bg-slate-800 p-5">
    <div class="flex items-center gap-3">
      <span class="inline-flex items-center justify-center shrink-0"
            style="width:44px; height:44px; border-radius:12px; background:rgba(34,197,94,0.1);">
        <i class="bi bi-people-fill" style="color:#22c55e; font-size:1.2rem;"></i>
      </span>
      <div>
        <div class="stat-value text-2xl font-bold text-gray-800" id="stat-total">{{ $onlineCount }}</div>
        <div class="stat-label text-xs text-gray-500 font-medium">ออนไลน์ทั้งหมด</div>
      </div>
    </div>
  </div>

  {{-- Desktop --}}
  @php
    $desktopCount = $onlineUsers->where('device_type', 'desktop')->count();
  @endphp
  <div class="stat-card bg-white dark:bg-slate-800 p-5">
    <div class="flex items-center gap-3">
      <span class="inline-flex items-center justify-center shrink-0"
            style="width:44px; height:44px; border-radius:12px; background:rgba(59,130,246,0.1);">
        <i class="bi bi-laptop" style="color:#3b82f6; font-size:1.2rem;"></i>
      </span>
      <div>
        <div class="stat-value text-2xl font-bold text-gray-800" id="stat-desktop">{{ $desktopCount }}</div>
        <div class="stat-label text-xs text-gray-500 font-medium">เดสก์ท็อป</div>
      </div>
    </div>
  </div>

  {{-- Mobile / Tablet --}}
  @php
    $mobileCount = $onlineUsers->whereIn('device_type', ['mobile', 'tablet'])->count();
  @endphp
  <div class="stat-card bg-white dark:bg-slate-800 p-5">
    <div class="flex items-center gap-3">
      <span class="inline-flex items-center justify-center shrink-0"
            style="width:44px; height:44px; border-radius:12px; background:rgba(139,92,246,0.1);">
        <i class="bi bi-phone" style="color:#8b5cf6; font-size:1.2rem;"></i>
      </span>
      <div>
        <div class="stat-value text-2xl font-bold text-gray-800" id="stat-mobile">{{ $mobileCount }}</div>
        <div class="stat-label text-xs text-gray-500 font-medium">มือถือ / แท็บเล็ต</div>
      </div>
    </div>
  </div>
</div>

{{-- ═══════════════════════════════════════════
     Main Table Card
     ═══════════════════════════════════════════ --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/[0.06] overflow-hidden ou-main-card">
  <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
    <div class="flex items-center gap-2">
      <span class="inline-flex items-center justify-center"
            style="width:32px; height:32px; border-radius:8px; background:rgba(34,197,94,0.1);">
        <i class="bi bi-person-check-fill" style="color:#22c55e; font-size:1rem;"></i>
      </span>
      <h6 class="mb-0 font-semibold text-gray-800 dark:text-white">รายชื่อผู้ใช้ออนไลน์</h6>
    </div>
    <span class="text-xs text-gray-400 dark:text-gray-500" id="last-updated">
      อัปเดตล่าสุด: {{ now()->format('H:i:s') }}
    </span>
  </div>

  <div id="online-users-table-wrapper">
    @if($onlineUsers->isEmpty())
      {{-- Empty State --}}
      <div class="text-center py-16 text-gray-400" id="online-empty-state">
        <i class="bi bi-wifi-off" style="font-size:3rem; opacity:0.4;"></i>
        <p class="mt-3 mb-1 text-base font-medium text-gray-500 dark:text-gray-400">ไม่มีผู้ใช้ออนไลน์ในขณะนี้</p>
        <p class="text-sm text-gray-400 dark:text-gray-500">ระบบจะรีเฟรชอัตโนมัติทุก 30 วินาที</p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-white/[0.04]">
            <tr>
              <th class="pl-5 pr-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ผู้ใช้</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">อุปกรณ์</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">เบราว์เซอร์</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ระบบปฏิบัติการ</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">กิจกรรมล่าสุด</th>
            </tr>
          </thead>
          <tbody id="online-users-tbody">
            @foreach($onlineUsers as $u)
            <tr class="online-left-border hover:bg-gray-50 dark:hover:bg-white/[0.04] transition border-b border-gray-50 dark:border-white/[0.03]">
              <td class="pl-5 pr-3 py-3">
                <div class="flex items-center gap-2.5">
                  <span class="inline-flex items-center justify-center shrink-0"
                        style="width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#22c55e,#16a34a); color:#fff; font-size:0.7rem; font-weight:700;">
                    {{ strtoupper(mb_substr($u->first_name ?? 'U', 0, 1)) }}{{ strtoupper(mb_substr($u->last_name ?? '', 0, 1)) }}
                  </span>
                  <div>
                    <div class="font-semibold leading-tight text-gray-800 dark:text-white">
                      {{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: 'ผู้ใช้ #' . $u->user_id }}
                    </div>
                    <div class="text-gray-400 dark:text-gray-500" style="font-size:0.72rem;">{{ $u->email ?? '' }}</div>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3">
                @php
                  $deviceIcon = match($u->device_type ?? 'desktop') {
                    'mobile'  => 'bi-phone',
                    'tablet'  => 'bi-tablet',
                    default   => 'bi-laptop',
                  };
                  $deviceLabel = match($u->device_type ?? 'desktop') {
                    'mobile'  => 'มือถือ',
                    'tablet'  => 'แท็บเล็ต',
                    default   => 'เดสก์ท็อป',
                  };
                @endphp
                <span class="inline-flex items-center gap-1.5">
                  <i class="bi {{ $deviceIcon }}" style="color:#6b7280; font-size:1rem;"></i>
                  <span class="text-gray-600 dark:text-gray-300">{{ $deviceLabel }}</span>
                </span>
              </td>
              <td class="px-3 py-3">
                <span class="font-medium text-gray-700 dark:text-gray-200">{{ $u->browser ?? 'ไม่ทราบ' }}</span>
              </td>
              <td class="px-3 py-3">
                <span class="text-gray-600 dark:text-gray-300">{{ $u->os ?? 'ไม่ทราบ' }}</span>
              </td>
              <td class="px-3 py-3">
                <code class="text-xs font-mono px-2 py-0.5 rounded bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">{{ $u->ip_address ?? '-' }}</code>
              </td>
              <td class="px-3 py-3">
                @if($u->last_activity)
                  <span class="text-gray-600 dark:text-gray-300" title="{{ \Carbon\Carbon::parse($u->last_activity)->format('d/m/Y H:i:s') }}">
                    {{ \Carbon\Carbon::parse($u->last_activity)->diffForHumans() }}
                  </span>
                @else
                  <span class="text-gray-400">-</span>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var apiUrl = @json(route('admin.api.online-users'));
  var refreshInterval = 30000;
  var refreshTimer = null;

  var deviceIconMap  = { mobile: 'bi-phone', tablet: 'bi-tablet', desktop: 'bi-laptop' };
  var deviceLabelMap = { mobile: 'มือถือ', tablet: 'แท็บเล็ต', desktop: 'เดสก์ท็อป' };

  /**
   * Escape HTML to prevent XSS
   */
  function esc(str) {
    if (!str) return '';
    var el = document.createElement('span');
    el.textContent = String(str);
    return el.innerHTML;
  }

  /**
   * Format a timestamp into Thai-friendly relative time
   */
  function timeAgo(dateStr) {
    if (!dateStr) return '-';
    var now = Date.now();
    var then = new Date(dateStr).getTime();
    var diff = Math.floor((now - then) / 1000);
    if (diff < 60) return diff + ' วินาทีที่แล้ว';
    if (diff < 3600) return Math.floor(diff / 60) + ' นาทีที่แล้ว';
    if (diff < 86400) return Math.floor(diff / 3600) + ' ชั่วโมงที่แล้ว';
    return Math.floor(diff / 86400) + ' วันที่แล้ว';
  }

  /**
   * Format date for tooltip
   */
  function formatDate(dateStr) {
    if (!dateStr) return '';
    var d = new Date(dateStr);
    var dd = String(d.getDate()).padStart(2, '0');
    var mm = String(d.getMonth() + 1).padStart(2, '0');
    var yy = d.getFullYear();
    var hh = String(d.getHours()).padStart(2, '0');
    var mi = String(d.getMinutes()).padStart(2, '0');
    var ss = String(d.getSeconds()).padStart(2, '0');
    return dd + '/' + mm + '/' + yy + ' ' + hh + ':' + mi + ':' + ss;
  }

  /**
   * Get initials from name
   */
  function getInitials(firstName, lastName) {
    var f = (firstName || 'U').charAt(0).toUpperCase();
    var l = (lastName || '').charAt(0).toUpperCase();
    return esc(f + l);
  }

  /**
   * Build a single table row
   */
  function buildRow(u) {
    var name = ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || ('ผู้ใช้ #' + (u.user_id || u.id || ''));
    var device = u.device_type || u.device || 'desktop';
    var icon = deviceIconMap[device] || 'bi-laptop';
    var label = deviceLabelMap[device] || 'เดสก์ท็อป';
    var activity = u.last_activity;
    var activityTitle = formatDate(activity);
    var activityText = timeAgo(activity);

    return '<tr class="online-left-border hover:bg-gray-50 dark:hover:bg-white/[0.04] transition border-b border-gray-50 dark:border-white/[0.03]">' +
      '<td class="pl-5 pr-3 py-3">' +
        '<div class="flex items-center gap-2.5">' +
          '<span class="inline-flex items-center justify-center shrink-0" style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;font-size:0.7rem;font-weight:700;">' +
            getInitials(u.first_name, u.last_name) +
          '</span>' +
          '<div>' +
            '<div class="font-semibold leading-tight text-gray-800 dark:text-white">' + esc(name) + '</div>' +
            '<div class="text-gray-400 dark:text-gray-500" style="font-size:0.72rem;">' + esc(u.email || '') + '</div>' +
          '</div>' +
        '</div>' +
      '</td>' +
      '<td class="px-3 py-3">' +
        '<span class="inline-flex items-center gap-1.5">' +
          '<i class="bi ' + icon + '" style="color:#6b7280;font-size:1rem;"></i>' +
          '<span class="text-gray-600 dark:text-gray-300">' + esc(label) + '</span>' +
        '</span>' +
      '</td>' +
      '<td class="px-3 py-3"><span class="font-medium text-gray-700 dark:text-gray-200">' + esc(u.browser || 'ไม่ทราบ') + '</span></td>' +
      '<td class="px-3 py-3"><span class="text-gray-600 dark:text-gray-300">' + esc(u.os || 'ไม่ทราบ') + '</span></td>' +
      '<td class="px-3 py-3"><code class="text-xs font-mono px-2 py-0.5 rounded bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">' + esc(u.ip_address || '-') + '</code></td>' +
      '<td class="px-3 py-3">' +
        (activity
          ? '<span class="text-gray-600 dark:text-gray-300" title="' + esc(activityTitle) + '">' + esc(activityText) + '</span>'
          : '<span class="text-gray-400">-</span>') +
      '</td>' +
    '</tr>';
  }

  /**
   * Compute device stats from users array
   */
  function computeStats(users) {
    var desktop = 0, mobile = 0;
    for (var i = 0; i < users.length; i++) {
      var d = users[i].device_type || users[i].device || 'desktop';
      if (d === 'desktop') desktop++;
      else mobile++;
    }
    return { total: users.length, desktop: desktop, mobile: mobile };
  }

  /**
   * Main refresh function
   */
  function refreshOnlineUsers() {
    var refreshIcon = document.getElementById('refresh-icon');
    if (refreshIcon) refreshIcon.classList.add('spin-refresh');

    fetch(apiUrl, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var count = data.count !== undefined ? data.count : (data.users ? data.users.length : 0);
      var users = data.users || [];

      // Update badge
      var badge = document.getElementById('online-count-badge');
      if (badge) badge.textContent = count;

      // Update stats
      var stats = computeStats(users);
      var statTotal = document.getElementById('stat-total');
      var statDesktop = document.getElementById('stat-desktop');
      var statMobile = document.getElementById('stat-mobile');
      if (statTotal) statTotal.textContent = stats.total;
      if (statDesktop) statDesktop.textContent = stats.desktop;
      if (statMobile) statMobile.textContent = stats.mobile;

      // Update last-updated timestamp
      var lastUpdated = document.getElementById('last-updated');
      if (lastUpdated) {
        var now = new Date();
        lastUpdated.textContent = 'อัปเดตล่าสุด: ' +
          String(now.getHours()).padStart(2, '0') + ':' +
          String(now.getMinutes()).padStart(2, '0') + ':' +
          String(now.getSeconds()).padStart(2, '0');
      }

      // Rebuild table
      var wrapper = document.getElementById('online-users-table-wrapper');
      if (!wrapper) return;

      if (count === 0) {
        wrapper.innerHTML =
          '<div class="text-center py-16 text-gray-400" id="online-empty-state">' +
            '<i class="bi bi-wifi-off" style="font-size:3rem;opacity:0.4;"></i>' +
            '<p class="mt-3 mb-1 text-base font-medium text-gray-500 dark:text-gray-400">ไม่มีผู้ใช้ออนไลน์ในขณะนี้</p>' +
            '<p class="text-sm text-gray-400 dark:text-gray-500">ระบบจะรีเฟรชอัตโนมัติทุก 30 วินาที</p>' +
          '</div>';
        return;
      }

      var rows = '';
      for (var i = 0; i < users.length; i++) {
        rows += buildRow(users[i]);
      }

      wrapper.innerHTML =
        '<div class="overflow-x-auto"><table class="w-full text-sm">' +
          '<thead class="bg-gray-50 dark:bg-white/[0.04]"><tr>' +
            '<th class="pl-5 pr-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ผู้ใช้</th>' +
            '<th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">อุปกรณ์</th>' +
            '<th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">เบราว์เซอร์</th>' +
            '<th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ระบบปฏิบัติการ</th>' +
            '<th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP</th>' +
            '<th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">กิจกรรมล่าสุด</th>' +
          '</tr></thead>' +
          '<tbody id="online-users-tbody">' + rows + '</tbody>' +
        '</table></div>';
    })
    .catch(function (err) {
      console.error('Online users refresh failed:', err);
    })
    .finally(function () {
      var refreshIcon = document.getElementById('refresh-icon');
      if (refreshIcon) refreshIcon.classList.remove('spin-refresh');
    });
  }

  /**
   * Manual refresh (exposed globally)
   */
  window.manualRefresh = function () {
    refreshOnlineUsers();
  };

  // Start auto-refresh
  refreshTimer = setInterval(refreshOnlineUsers, refreshInterval);
})();
</script>
@endpush
