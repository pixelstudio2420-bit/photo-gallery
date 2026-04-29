@extends('layouts.admin')

@section('title', 'Email Logs')

@push('styles')
@include('admin.settings._shared-styles')
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ═══ Page Header ═══ --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center shadow-lg shadow-indigo-500/30">
        <i class="bi bi-envelope-paper text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Email Logs</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          All outbound email activity recorded by the system.
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

  {{-- ═══ Stat Cards ═══ --}}
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="rounded-2xl p-5 border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-emerald-100 dark:bg-emerald-500/20">
          <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-xl"></i>
        </div>
        <div>
          <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 leading-tight">{{ number_format($stats['sent']) }}</div>
          <div class="text-xs font-medium text-slate-600 dark:text-slate-400">Sent</div>
        </div>
      </div>
    </div>
    <div class="rounded-2xl p-5 border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-rose-100 dark:bg-rose-500/20">
          <i class="bi bi-x-circle-fill text-rose-600 dark:text-rose-400 text-xl"></i>
        </div>
        <div>
          <div class="text-2xl font-black text-rose-700 dark:text-rose-300 leading-tight">{{ number_format($stats['failed']) }}</div>
          <div class="text-xs font-medium text-slate-600 dark:text-slate-400">Failed</div>
        </div>
      </div>
    </div>
    <div class="rounded-2xl p-5 border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-800/40">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-slate-200 dark:bg-slate-700">
          <i class="bi bi-dash-circle-fill text-slate-600 dark:text-slate-400 text-xl"></i>
        </div>
        <div>
          <div class="text-2xl font-black text-slate-700 dark:text-slate-200 leading-tight">{{ number_format($stats['skipped']) }}</div>
          <div class="text-xs font-medium text-slate-600 dark:text-slate-400">Skipped</div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══ Filters ═══ --}}
  <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm mb-5 p-4" x-data="adminFilter()">
    <form method="GET" action="{{ route('admin.settings.email-logs') }}">
      <div class="grid grid-cols-1 md:grid-cols-6 gap-3">

        {{-- Search --}}
        <div class="md:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ค้นหา</label>
          <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Email or subject…"
                   class="w-full pl-9 pr-3 py-2 rounded-lg text-sm
                          bg-white dark:bg-slate-800
                          border border-slate-300 dark:border-white/10
                          text-slate-900 dark:text-slate-100
                          focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            <div class="absolute right-3 top-1/2 -translate-y-1/2 tw-spinner text-indigo-500" x-show="loading" x-cloak></div>
          </div>
        </div>

        {{-- Status --}}
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Status</label>
          <select name="status"
                  class="w-full px-3 py-2 rounded-lg text-sm
                         bg-white dark:bg-slate-800
                         border border-slate-300 dark:border-white/10
                         text-slate-900 dark:text-slate-100
                         focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            <option value="">All Statuses</option>
            <option value="sent"    {{ request('status') === 'sent'    ? 'selected' : '' }}>Sent</option>
            <option value="failed"  {{ request('status') === 'failed'  ? 'selected' : '' }}>Failed</option>
            <option value="skipped" {{ request('status') === 'skipped' ? 'selected' : '' }}>Skipped</option>
          </select>
        </div>

        {{-- Type --}}
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Type</label>
          <select name="type"
                  class="w-full px-3 py-2 rounded-lg text-sm
                         bg-white dark:bg-slate-800
                         border border-slate-300 dark:border-white/10
                         text-slate-900 dark:text-slate-100
                         focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            <option value="">All Types</option>
            @foreach($types as $t)
              <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $t)) }}</option>
            @endforeach
          </select>
        </div>

        {{-- Actions --}}
        <div class="flex items-end">
          <button type="button" @click="clearFilters()"
                  class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium
                         bg-white dark:bg-slate-800
                         border border-slate-300 dark:border-white/10
                         text-slate-700 dark:text-slate-200
                         hover:bg-slate-50 dark:hover:bg-slate-700 transition">
            <i class="bi bi-x-lg"></i> ล้าง
          </button>
        </div>
      </div>
    </form>
  </div>

  {{-- ═══ Table ═══ --}}
  <div id="admin-table-area">
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
      @if($logs instanceof \Illuminate\Pagination\LengthAwarePaginator && $logs->total() === 0)
        <div class="py-12 text-center text-slate-500 dark:text-slate-400">
          <i class="bi bi-inbox text-4xl opacity-40 block mb-2"></i>
          No email logs found.
        </div>
      @elseif(($logs instanceof \Illuminate\Support\Collection && $logs->isEmpty()) || !$logs)
        <div class="py-12 text-center text-slate-500 dark:text-slate-400">
          <i class="bi bi-inbox text-4xl opacity-40 block mb-2"></i>
          No email logs found.
        </div>
      @else
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase tracking-wide">
              <tr class="text-slate-500 dark:text-slate-400">
                <th class="px-4 py-3 text-left font-semibold" style="width:155px">Date / Time</th>
                <th class="px-4 py-3 text-left font-semibold">To</th>
                <th class="px-4 py-3 text-left font-semibold">Subject</th>
                <th class="px-4 py-3 text-left font-semibold" style="width:130px">Type</th>
                <th class="px-4 py-3 text-left font-semibold" style="width:100px">Status</th>
                <th class="px-4 py-3 text-left font-semibold" style="width:90px">Driver</th>
                <th class="px-4 py-3 text-center font-semibold" style="width:60px">Error</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-white/10">
              @foreach($logs as $log)
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                <td class="px-4 py-3 whitespace-nowrap">
                  <div class="text-xs text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y') }}</div>
                  <div class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ \Carbon\Carbon::parse($log->created_at)->format('H:i:s') }}</div>
                </td>
                <td class="px-4 py-3">
                  <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $log->to_email }}</span>
                </td>
                <td class="px-4 py-3">
                  <span class="text-sm text-slate-700 dark:text-slate-300 block truncate max-w-[260px]" title="{{ $log->subject }}">
                    {{ $log->subject }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold
                               bg-indigo-100 dark:bg-indigo-500/15
                               text-indigo-700 dark:text-indigo-300">
                    {{ ucwords(str_replace('_', ' ', $log->type)) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  @if($log->status === 'sent')
                    <span class="status-dot connected"><i class="bi bi-check-circle"></i>Sent</span>
                  @elseif($log->status === 'failed')
                    <span class="status-dot disconnected"><i class="bi bi-x-circle"></i>Failed</span>
                  @else
                    <span class="status-dot unknown"><i class="bi bi-dash-circle"></i>Skipped</span>
                  @endif
                </td>
                <td class="px-4 py-3">
                  <code class="text-xs text-slate-500 dark:text-slate-400">{{ $log->driver ?? '—' }}</code>
                </td>
                <td class="px-4 py-3 text-center">
                  @if($log->error_message)
                    <div x-data="{ showErrorModal: false }">
                      <button type="button"
                              @click="showErrorModal = true"
                              title="{{ Str::limit($log->error_message, 80) }}"
                              class="inline-flex items-center justify-center w-8 h-8 rounded-lg
                                     bg-rose-100 dark:bg-rose-500/15
                                     text-rose-600 dark:text-rose-400
                                     hover:bg-rose-200 dark:hover:bg-rose-500/30 transition">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                      </button>

                      {{-- Error Modal --}}
                      <div x-show="showErrorModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showErrorModal = false">
                        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showErrorModal = false"></div>
                        <div class="flex min-h-screen items-center justify-center p-4">
                          <div x-show="showErrorModal" x-transition
                               class="relative rounded-2xl shadow-2xl max-w-lg w-full
                                      bg-white dark:bg-slate-900
                                      border border-slate-200 dark:border-white/10">
                            <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
                              <h5 class="font-bold text-rose-600 dark:text-rose-400">
                                <i class="bi bi-exclamation-triangle mr-2"></i>Delivery Error
                              </h5>
                              <button type="button" @click="showErrorModal = false"
                                      class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                <i class="bi bi-x-lg"></i>
                              </button>
                            </div>
                            <div class="p-6 text-left">
                              <p class="text-xs text-slate-500 dark:text-slate-400 mb-1"><strong class="text-slate-700 dark:text-slate-200">To:</strong> {{ $log->to_email }}</p>
                              <p class="text-xs text-slate-500 dark:text-slate-400 mb-3"><strong class="text-slate-700 dark:text-slate-200">Subject:</strong> {{ $log->subject }}</p>
                              <div class="rounded-lg p-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-white/10">
                                <code class="text-xs text-rose-600 dark:text-rose-400" style="white-space:pre-wrap;word-break:break-all;">{{ $log->error_message }}</code>
                              </div>
                            </div>
                            <div class="px-6 py-4 border-t border-slate-200 dark:border-white/10 flex justify-end">
                              <button type="button" @click="showErrorModal = false"
                                      class="px-4 py-2 rounded-lg text-sm font-medium
                                             bg-slate-100 dark:bg-slate-800
                                             text-slate-700 dark:text-slate-200
                                             hover:bg-slate-200 dark:hover:bg-slate-700 transition">Close</button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  @else
                    <span class="text-slate-400 dark:text-slate-500">—</span>
                  @endif
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if($logs instanceof \Illuminate\Pagination\LengthAwarePaginator && $logs->hasPages())
        <div id="admin-pagination-area" class="px-4 py-3 border-t border-slate-200 dark:border-white/10 flex items-center justify-between flex-wrap gap-2">
          <p class="text-xs text-slate-500 dark:text-slate-400">
            Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ number_format($logs->total()) }} entries
          </p>
          {{ $logs->links('pagination::tailwind') }}
        </div>
        @endif
      @endif
    </div>
  </div>

</div>
@endsection
