@extends('layouts.admin')

@section('title', 'Webhook Monitor')

@section('content')

@php
  use Carbon\Carbon;

  $statCards = [
    [
      'icon'      => 'bi-broadcast',
      'label'     => 'Webhooks วันนี้',
      'value'     => number_format($stats['total_today']),
      'iconClass' => 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-300',
      'valueClass'=> 'text-indigo-600 dark:text-indigo-300',
    ],
    [
      'icon'      => 'bi-check-circle-fill',
      'label'     => 'สำเร็จ',
      'value'     => number_format($stats['success_today']),
      'iconClass' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300',
      'valueClass'=> 'text-emerald-600 dark:text-emerald-300',
    ],
    [
      'icon'      => 'bi-x-circle-fill',
      'label'     => 'ล้มเหลว',
      'value'     => number_format($stats['failed_today']),
      'iconClass' => 'bg-rose-500/15 text-rose-600 dark:text-rose-300',
      'valueClass'=> 'text-rose-600 dark:text-rose-300',
    ],
  ];

  // Gateway pill color map (literal classes for Tailwind JIT)
  $gatewayTones = [
    'stripe'    => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
    'omise'     => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
    'promptpay' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    'linepay'   => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    'line'      => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    'scb'       => 'bg-pink-50 text-pink-700 dark:bg-pink-500/10 dark:text-pink-300',
    'slipok'    => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    'default'   => 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300',
  ];
@endphp

{{-- Page Header --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div class="flex items-center gap-3">
    <div class="h-11 w-11 rounded-2xl bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 flex items-center justify-center">
      <i class="bi bi-activity text-xl"></i>
    </div>
    <div>
      <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Webhook Monitor</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400">ติดตามสถานะและประวัติ Webhook จาก Payment Gateway</p>
    </div>
  </div>
  <div class="flex items-center gap-2">
    <button type="button"
            onclick="refreshPage()"
            class="inline-flex items-center gap-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 px-3.5 py-2 text-sm font-medium transition-colors">
      <i class="bi bi-arrow-clockwise" id="refreshIcon"></i>
      <span>รีเฟรช</span>
    </button>
    <a href="{{ route('admin.settings.line') }}"
       class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/60 px-3.5 py-2 text-sm font-medium transition-colors">
      <i class="bi bi-arrow-left"></i>
      <span>ตั้งค่า LINE</span>
    </a>
  </div>
</div>

{{-- Summary Stat Cards --}}
<div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-6">
  @foreach($statCards as $card)
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 p-4 shadow-sm">
      <div class="flex items-center gap-3">
        <div class="h-11 w-11 rounded-xl flex items-center justify-center shrink-0 {{ $card['iconClass'] }}">
          <i class="bi {{ $card['icon'] }} text-lg"></i>
        </div>
        <div class="min-w-0">
          <div class="text-2xl font-bold leading-tight {{ $card['valueClass'] }} truncate">{{ $card['value'] }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $card['label'] }}</div>
        </div>
      </div>
    </div>
  @endforeach

  {{-- Last Received (special format) --}}
  <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 p-4 shadow-sm">
    <div class="flex items-center gap-3">
      <div class="h-11 w-11 rounded-xl flex items-center justify-center shrink-0 bg-amber-500/15 text-amber-600 dark:text-amber-300">
        <i class="bi bi-clock-history text-lg"></i>
      </div>
      <div class="min-w-0">
        @if($stats['last_received'])
          <div class="text-base font-semibold leading-tight text-slate-900 dark:text-slate-100 truncate">
            {{ Carbon::parse($stats['last_received'])->diffForHumans() }}
          </div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">รับล่าสุด</div>
        @else
          <div class="text-xl font-bold leading-tight text-slate-400 dark:text-slate-500">—</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">ยังไม่มีข้อมูล</div>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Webhook Endpoint Status Table --}}
<div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 overflow-hidden shadow-sm mb-6">
  <div class="px-5 py-3.5 border-b border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-2">
    <h6 class="font-semibold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
      <i class="bi bi-link-45deg text-indigo-500 dark:text-indigo-300"></i>
      สถานะ Webhook Endpoints
    </h6>
    <span class="text-[11px] text-slate-500 dark:text-slate-400 inline-flex items-center gap-1">
      <i class="bi bi-info-circle"></i>
      คัดลอก URL ไปตั้งค่าใน Dashboard ของแต่ละ Gateway
    </span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-white/10">
        <tr class="text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          <th class="px-5 py-3">Gateway</th>
          <th class="px-4 py-3">Webhook URL</th>
          <th class="px-4 py-3">รับล่าสุด</th>
          <th class="px-4 py-3">รับวันนี้</th>
          <th class="px-4 py-3">สถานะ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 dark:divide-white/5">
        @forelse($endpoints as $ep)
          @php
            $gwKey     = strtolower($ep['gateway'] ?? 'default');
            $gwPill    = $gatewayTones[$gwKey] ?? $gatewayTones['default'];
          @endphp
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
            <td class="px-5 py-3">
              <span class="inline-flex items-center rounded-md px-2 py-1 text-[11px] font-bold uppercase tracking-wider {{ $gwPill }}">
                {{ $ep['label'] }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <code class="inline-block rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-2 py-1 text-xs font-mono max-w-[320px] truncate"
                      title="{{ $ep['url'] }}">{{ $ep['url'] }}</code>
                <button type="button"
                        onclick="copyToClipboard('{{ $ep['url'] }}', this)"
                        title="คัดลอก URL"
                        class="inline-flex items-center justify-center h-7 w-7 rounded-md border border-slate-200 dark:border-white/10 text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-300 hover:border-indigo-400 dark:hover:border-indigo-500/40 transition-colors">
                  <i class="bi bi-clipboard text-xs"></i>
                </button>
              </div>
            </td>
            <td class="px-4 py-3">
              @if($ep['last_received'])
                <div class="text-sm text-slate-700 dark:text-slate-200">{{ Carbon::parse($ep['last_received'])->format('d/m/Y H:i') }}</div>
                <div class="text-xs text-slate-400 dark:text-slate-500">{{ Carbon::parse($ep['last_received'])->diffForHumans() }}</div>
              @else
                <span class="text-xs text-slate-400 dark:text-slate-500">ยังไม่มีข้อมูล</span>
              @endif
            </td>
            <td class="px-4 py-3">
              <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $ep['count_today'] }}</span>
              <span class="text-xs text-slate-500 dark:text-slate-400 ml-0.5">ครั้ง</span>
            </td>
            <td class="px-4 py-3">
              @if($ep['active'])
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300 px-2.5 py-1 text-xs font-semibold">
                  <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                  Active
                </span>
              @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300 px-2.5 py-1 text-xs font-semibold">
                  <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                  ไม่ได้ใช้งาน
                </span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-4 py-10 text-center">
              <div class="flex flex-col items-center gap-2 text-slate-500 dark:text-slate-400">
                <i class="bi bi-inbox text-2xl opacity-50"></i>
                <span class="text-sm">ยังไม่มี endpoint ที่กำหนด</span>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Filter Bar --}}
<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="{{ route('admin.settings.webhooks') }}">
    <div class="af-grid">

      {{-- Event type search --}}
      <div class="af-search">
        <label class="af-label">Event Type</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="event_type" class="af-input"
                 placeholder="เช่น payment.success"
                 value="{{ request('event_type') }}">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>

      {{-- Gateway dropdown --}}
      <div>
        <label class="af-label">Gateway</label>
        <select name="gateway" class="af-input">
          <option value="">ทั้งหมด</option>
          @foreach($gateways as $gw)
            <option value="{{ $gw }}" {{ request('gateway') === $gw ? 'selected' : '' }}>{{ ucfirst($gw) }}</option>
          @endforeach
        </select>
      </div>

      {{-- Status dropdown --}}
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>สำเร็จ</option>
          <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>ล้มเหลว</option>
        </select>
      </div>

      {{-- Actions --}}
      <div class="af-actions">
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>

    </div>
  </form>
</div>

{{-- Webhook Log Table --}}
<div id="admin-table-area">
  <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">

    {{-- Table Header --}}
    <div class="px-5 py-3.5 border-b border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-2">
      <h6 class="font-semibold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
        <i class="bi bi-journal-text text-indigo-500 dark:text-indigo-300"></i>
        Webhook Log ล่าสุด
      </h6>
      <div class="flex items-center gap-2">
        <span class="text-[11px] text-slate-500 dark:text-slate-400">
          ทั้งหมด {{ number_format($logs->total()) }} รายการ
        </span>
        @if(request()->hasAny(['gateway','status','event_type']))
          <span class="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 px-2 py-0.5 text-[11px] font-medium">
            กำลังกรอง
          </span>
        @endif
      </div>
    </div>

    @if($logs->count() > 0)
      <div class="overflow-x-auto">
        <table class="w-full text-sm" id="logTable">
          <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-white/10">
            <tr class="text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
              <th class="px-4 py-3 w-8"></th>
              <th class="px-4 py-3">เวลา</th>
              <th class="px-4 py-3">Gateway</th>
              <th class="px-4 py-3">Event Type</th>
              <th class="px-4 py-3">สถานะ</th>
              <th class="px-4 py-3">IP</th>
              <th class="px-4 py-3 w-16"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-white/5">
          @foreach($logs as $log)
            @php
              $gwKey  = strtolower($log->gateway ?? 'default');
              $gwPill = $gatewayTones[$gwKey] ?? $gatewayTones['default'];

              $payload = is_string($log->payload) ? json_decode($log->payload, true) : (array)$log->payload;
              $status  = $payload['status'] ?? ($payload['result'] ?? null);
              $isOk    = in_array(strtolower((string)$status), ['success','ok','200','paid','complete','completed','1','true']);
              $isFail  = in_array(strtolower((string)$status), ['failed','fail','error','0','false']);
            @endphp
            <tr class="log-row cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
                data-log-id="{{ $log->id }}"
                onclick="togglePayload({{ $log->id }})">
              <td class="px-4 py-3 text-center">
                <i class="bi bi-chevron-down expand-icon text-xs text-slate-400 dark:text-slate-500 transition-transform"
                   id="icon-{{ $log->id }}"></i>
              </td>
              <td class="px-4 py-3">
                <div class="text-sm text-slate-700 dark:text-slate-200">{{ Carbon::parse($log->created_at)->format('d/m/Y') }}</div>
                <div class="text-xs text-slate-400 dark:text-slate-500">{{ Carbon::parse($log->created_at)->format('H:i:s') }}</div>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center rounded-md px-2 py-1 text-[11px] font-bold uppercase tracking-wider {{ $gwPill }}">
                  {{ $log->gateway ?? '-' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="font-mono text-xs text-slate-700 dark:text-slate-200">
                  {{ $log->event_type ?? '-' }}
                </span>
              </td>
              <td class="px-4 py-3">
                @if($isOk)
                  <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300 px-2 py-0.5 text-xs font-semibold">
                    <i class="bi bi-check-circle"></i>สำเร็จ
                  </span>
                @elseif($isFail)
                  <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300 px-2 py-0.5 text-xs font-semibold">
                    <i class="bi bi-x-circle"></i>ล้มเหลว
                  </span>
                @else
                  <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300 px-2 py-0.5 text-xs font-semibold">
                    <i class="bi bi-dash-circle"></i>{{ $status ?? 'รับแล้ว' }}
                  </span>
                @endif
              </td>
              <td class="px-4 py-3">
                <code class="inline-block rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-1.5 py-0.5 text-xs font-mono">
                  {{ $log->ip_address ?? '-' }}
                </code>
              </td>
              <td class="px-4 py-3 text-right" onclick="event.stopPropagation()">
                <button type="button"
                        onclick="copyPayload({{ $log->id }})"
                        title="คัดลอก Payload"
                        class="inline-flex items-center justify-center h-7 w-7 rounded-md border border-slate-200 dark:border-white/10 text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-300 hover:border-indigo-400 dark:hover:border-indigo-500/40 transition-colors">
                  <i class="bi bi-clipboard text-xs"></i>
                </button>
              </td>
            </tr>
            {{-- Payload row (hidden by default) --}}
            <tr class="payload-row hidden" id="payload-{{ $log->id }}">
              <td colspan="7" class="p-0">
                <div class="border-t border-slate-200 dark:border-white/10">
                  {{-- Payload meta strip (dark in both modes) --}}
                  <div class="bg-slate-950 text-slate-400 px-5 py-2 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                      <span class="text-violet-300 font-semibold">{{ strtoupper($log->gateway ?? 'UNKNOWN') }}</span>
                      <span>→ {{ $log->event_type ?? 'event' }}</span>
                      <span class="text-slate-500">{{ Carbon::parse($log->created_at)->format('d M Y H:i:s') }}</span>
                    </div>
                    <button type="button"
                            onclick="copyPayload({{ $log->id }})"
                            class="inline-flex items-center gap-1 rounded border border-slate-700 text-slate-400 hover:text-violet-300 hover:border-indigo-500 px-2 py-0.5 text-[11px] transition-colors">
                      <i class="bi bi-clipboard"></i>
                      Copy JSON
                    </button>
                  </div>
                  {{-- JSON viewer (always dark) --}}
                  <pre id="payloadContent-{{ $log->id }}"
                       class="bg-slate-900 text-slate-100 px-5 py-4 m-0 overflow-auto max-h-80 text-xs font-mono leading-relaxed whitespace-pre-wrap break-words">{{ is_string($log->payload) ? json_encode(json_decode($log->payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>

      {{-- Pagination --}}
      <div id="admin-pagination-area" class="px-5 py-3 border-t border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-slate-800/30">
        <div class="flex flex-wrap justify-between items-center gap-2">
          <div class="text-xs text-slate-500 dark:text-slate-400">
            แสดง <strong class="text-slate-700 dark:text-slate-200">{{ $logs->firstItem() }}</strong>–<strong class="text-slate-700 dark:text-slate-200">{{ $logs->lastItem() }}</strong>
            จาก <strong class="text-slate-700 dark:text-slate-200">{{ number_format($logs->total()) }}</strong> รายการ
          </div>
          <nav>
            <ul class="flex items-center gap-1">
              @if($logs->onFirstPage())
                <li>
                  <span class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-200 dark:border-white/10 text-slate-300 dark:text-slate-600 cursor-not-allowed">
                    <i class="bi bi-chevron-left text-xs"></i>
                  </span>
                </li>
              @else
                <li>
                  <a href="{{ $logs->withQueryString()->previousPageUrl() }}"
                     class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <i class="bi bi-chevron-left text-xs"></i>
                  </a>
                </li>
              @endif

              @foreach($logs->withQueryString()->getUrlRange(max(1,$logs->currentPage()-2), min($logs->lastPage(),$logs->currentPage()+2)) as $page => $url)
                <li>
                  @if($page == $logs->currentPage())
                    <span class="inline-flex items-center justify-center h-8 min-w-8 px-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold">{{ $page }}</span>
                  @else
                    <a href="{{ $url }}"
                       class="inline-flex items-center justify-center h-8 min-w-8 px-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs transition-colors">
                      {{ $page }}
                    </a>
                  @endif
                </li>
              @endforeach

              @if($logs->hasMorePages())
                <li>
                  <a href="{{ $logs->withQueryString()->nextPageUrl() }}"
                     class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <i class="bi bi-chevron-right text-xs"></i>
                  </a>
                </li>
              @else
                <li>
                  <span class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-200 dark:border-white/10 text-slate-300 dark:text-slate-600 cursor-not-allowed">
                    <i class="bi bi-chevron-right text-xs"></i>
                  </span>
                </li>
              @endif
            </ul>
          </nav>
        </div>
      </div>

    @else
      {{-- Empty State --}}
      <div class="px-4 py-16 text-center">
        <div class="flex flex-col items-center gap-3">
          <div class="h-14 w-14 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 flex items-center justify-center">
            <i class="bi bi-inbox text-2xl"></i>
          </div>
          <p class="text-sm font-medium text-slate-600 dark:text-slate-300">ไม่พบ Webhook Log</p>
          <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md">
            @if(request()->hasAny(['gateway','status','event_type']))
              ไม่มีรายการที่ตรงกับเงื่อนไขที่เลือก
            @else
              ยังไม่มี Webhook ที่รับเข้ามา ระบบจะแสดงที่นี่เมื่อมีข้อมูล
            @endif
          </p>
          @if(request()->hasAny(['gateway','status','event_type']))
            <a href="{{ route('admin.settings.webhooks') }}"
               class="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 px-3 py-1.5 text-xs font-medium hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors">
              <i class="bi bi-x-circle"></i>
              ล้างตัวกรอง
            </a>
          @endif
        </div>
      </div>
    @endif

  </div>
</div>{{-- end #admin-table-area --}}

@endsection

@push('scripts')
<script>
// ─── Expand / Collapse Payload ────────────────────────────────────────────
const expandedRows = new Set();

function togglePayload(logId) {
  const payloadRow = document.getElementById('payload-' + logId);
  const icon       = document.getElementById('icon-' + logId);
  const logRow     = document.querySelector('[data-log-id="' + logId + '"]');

  if (!payloadRow) return;

  if (expandedRows.has(logId)) {
    payloadRow.classList.add('hidden');
    if (icon) icon.style.transform = '';
    if (logRow) logRow.classList.remove('bg-indigo-50/50', 'dark:bg-indigo-500/10');
    expandedRows.delete(logId);
  } else {
    payloadRow.classList.remove('hidden');
    if (icon) icon.style.transform = 'rotate(180deg)';
    if (logRow) logRow.classList.add('bg-indigo-50/50', 'dark:bg-indigo-500/10');
    expandedRows.add(logId);
  }
}

// ─── Copy Payload ─────────────────────────────────────────────────────────
function copyPayload(logId) {
  const contentEl = document.getElementById('payloadContent-' + logId);
  if (!contentEl) return;
  const text = contentEl.textContent;
  navigator.clipboard.writeText(text).then(() => {
    showToast('success', 'คัดลอก Payload แล้ว');
  }).catch(() => {
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    showToast('success', 'คัดลอก Payload แล้ว');
  });
}

// ─── Copy arbitrary text ──────────────────────────────────────────────────
function copyToClipboard(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check text-xs"></i>';
    btn.classList.add('text-emerald-600', 'dark:text-emerald-300', 'border-emerald-400');
    setTimeout(() => {
      btn.innerHTML = original;
      btn.classList.remove('text-emerald-600', 'dark:text-emerald-300', 'border-emerald-400');
    }, 1800);
  });
}

// ─── Toast (SweetAlert2 wrapper) ──────────────────────────────────────────
function showToast(icon, title) {
  if (typeof Swal !== 'undefined') {
    Swal.fire({
      toast: true, position: 'top-end',
      icon, title,
      showConfirmButton: false,
      timer: 2500, timerProgressBar: true,
    });
  }
}

// ─── Refresh page with spin ───────────────────────────────────────────────
function refreshPage() {
  const icon = document.getElementById('refreshIcon');
  if (icon) {
    icon.style.animation = 'spin 0.6s linear';
  }
  setTimeout(() => { window.location.reload(); }, 300);
}

// ─── Keyboard shortcut: R to refresh ──────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key === 'r' && !e.ctrlKey && !e.metaKey
      && document.activeElement.tagName !== 'INPUT'
      && document.activeElement.tagName !== 'TEXTAREA') {
    refreshPage();
  }
});
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush
