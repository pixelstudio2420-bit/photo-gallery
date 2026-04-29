@extends('layouts.admin')

@section('title', 'Queue Management')

@push('styles')
@include('admin.settings._shared-styles')
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16" x-data="{ activeTab: 'jobs' }">

  {{-- ═══ Page Header ═══ --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center shadow-lg shadow-indigo-500/30">
        <i class="bi bi-arrow-repeat text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Queue Management</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Monitor background jobs and configure queue behaviour.
        </p>
      </div>
    </div>
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg
              bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10
              text-slate-700 dark:text-slate-200
              hover:bg-slate-50 dark:hover:bg-slate-700 transition">
      <i class="bi bi-arrow-left"></i> กลับ
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

  {{-- ═══ Tabs ═══ --}}
  <div class="flex border-b border-slate-200 dark:border-white/10 mb-5">
    <button type="button" @click="activeTab = 'jobs'"
            :class="activeTab === 'jobs' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
            class="px-5 py-2.5 text-sm font-medium border-b-2 transition">
      <i class="bi bi-list-task mr-1"></i>Jobs
    </button>
    <button type="button" @click="activeTab = 'settings'"
            :class="activeTab === 'settings' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
            class="px-5 py-2.5 text-sm font-medium border-b-2 transition">
      <i class="bi bi-gear mr-1"></i>Settings
    </button>
  </div>

  {{-- ══════════ JOBS TAB ══════════ --}}
  <div x-show="activeTab === 'jobs'" x-cloak>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="rounded-2xl p-5 border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 text-center">
        <div class="text-3xl font-black text-amber-700 dark:text-amber-300 leading-tight">{{ $queueStats['pending'] }}</div>
        <div class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-1">Pending</div>
        <i class="bi bi-clock text-amber-500 mt-2 block"></i>
      </div>
      <div class="rounded-2xl p-5 border border-sky-200 dark:border-sky-500/30 bg-sky-50 dark:bg-sky-500/10 text-center">
        <div class="text-3xl font-black text-sky-700 dark:text-sky-300 leading-tight">{{ $queueStats['running'] }}</div>
        <div class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-1">Running</div>
        <i class="bi bi-play-circle text-sky-500 mt-2 block"></i>
      </div>
      <div class="rounded-2xl p-5 border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 text-center">
        <div class="text-3xl font-black text-emerald-700 dark:text-emerald-300 leading-tight">{{ $queueStats['completed'] }}</div>
        <div class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-1">Completed</div>
        <i class="bi bi-check-circle text-emerald-500 mt-2 block"></i>
      </div>
      <div class="rounded-2xl p-5 border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 text-center">
        <div class="text-3xl font-black text-rose-700 dark:text-rose-300 leading-tight">{{ $queueStats['failed'] }}</div>
        <div class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-1">Failed</div>
        <i class="bi bi-x-circle text-rose-500 mt-2 block"></i>
      </div>
    </div>

    {{-- Action Buttons --}}
    <div class="flex flex-wrap gap-2 mb-5">
      <form method="POST" action="{{ route('admin.settings.queue.process') }}" onsubmit="return confirm('Process pending jobs now?')">
        @csrf
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-white
                       bg-gradient-to-r from-indigo-600 to-violet-600
                       hover:from-indigo-500 hover:to-violet-500
                       shadow-md shadow-indigo-500/30 transition">
          <i class="bi bi-play-circle"></i> Process Now
        </button>
      </form>
      <form method="POST" action="{{ route('admin.settings.queue.update') }}" onsubmit="return confirm('รีเซ็ต jobs ที่ failed ทั้งหมดเป็น pending?')">
        @csrf
        <input type="hidden" name="action" value="retry_all">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-white
                       bg-amber-500 hover:bg-amber-600 shadow-md shadow-amber-500/30 transition">
          <i class="bi bi-arrow-repeat"></i> Retry All Failed
        </button>
      </form>
      <form method="POST" action="{{ route('admin.settings.queue.clear') }}" onsubmit="return confirm('ลบ completed jobs ที่เก่ากว่า 7 วัน?')">
        @csrf
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium
                       bg-white dark:bg-slate-800
                       border border-slate-300 dark:border-white/10
                       text-slate-700 dark:text-slate-200
                       hover:bg-slate-50 dark:hover:bg-slate-700 transition">
          <i class="bi bi-trash"></i> Clear Completed
        </button>
      </form>
    </div>

    {{-- Jobs Table --}}
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
      @if($recentJobs->isEmpty())
        <div class="py-12 text-center text-slate-500 dark:text-slate-400">
          <i class="bi bi-inbox text-4xl opacity-40 block mb-2"></i>
          <div class="text-sm">ไม่มีข้อมูล</div>
        </div>
      @else
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase tracking-wide">
              <tr class="text-slate-500 dark:text-slate-400">
                <th class="px-4 py-3 text-left font-semibold" style="width:60px;">ID</th>
                <th class="px-4 py-3 text-left font-semibold">Event</th>
                <th class="px-4 py-3 text-left font-semibold">Type</th>
                <th class="px-4 py-3 text-left font-semibold">Status</th>
                <th class="px-4 py-3 text-center font-semibold" style="width:80px;">Attempts</th>
                <th class="px-4 py-3 text-left font-semibold">Error</th>
                <th class="px-4 py-3 text-left font-semibold">Created</th>
                <th class="px-4 py-3 text-left font-semibold">Processed</th>
                <th class="px-4 py-3 text-left font-semibold" style="width:80px;"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-white/10">
              @foreach($recentJobs as $job)
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ $job->id }}</td>
                <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ $job->event_name ?? '—' }}</td>
                <td class="px-4 py-2.5">
                  <span class="inline-block px-2 py-0.5 rounded-md text-xs font-medium
                               bg-slate-100 dark:bg-slate-800
                               text-slate-700 dark:text-slate-300">
                    {{ $job->job_type ?? '—' }}
                  </span>
                </td>
                <td class="px-4 py-2.5">
                  @php
                    $s = $job->status;
                  @endphp
                  @if($s === 'pending')
                    <span class="inline-block px-2 py-0.5 rounded-md text-xs font-medium bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300">pending</span>
                  @elseif($s === 'processing' || $s === 'running')
                    <span class="inline-block px-2 py-0.5 rounded-md text-xs font-medium bg-sky-100 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300">{{ $s }}</span>
                  @elseif($s === 'completed')
                    <span class="inline-block px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">completed</span>
                  @elseif($s === 'failed')
                    <span class="inline-block px-2 py-0.5 rounded-md text-xs font-medium bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300">failed</span>
                  @else
                    <span class="inline-block px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">{{ $s }}</span>
                  @endif
                </td>
                <td class="px-4 py-2.5 text-center text-slate-600 dark:text-slate-400">{{ $job->attempts }}{{ isset($job->max_attempts) ? '/'.$job->max_attempts : '' }}</td>
                <td class="px-4 py-2.5 max-w-[200px] overflow-hidden text-ellipsis whitespace-nowrap text-slate-600 dark:text-slate-400">
                  <span title="{{ $job->error_message ?? '' }}">
                    {{ $job->error_message ? \Illuminate\Support\Str::limit($job->error_message, 50) : '—' }}
                  </span>
                </td>
                <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $job->created_at ? \Carbon\Carbon::parse($job->created_at)->diffForHumans() : '—' }}</td>
                <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $job->processed_at ? \Carbon\Carbon::parse($job->processed_at)->diffForHumans() : '—' }}</td>
                <td class="px-4 py-2.5">
                  @if($job->status === 'failed')
                  <form method="POST" action="{{ route('admin.settings.queue.retry', $job->id) }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold
                                   bg-amber-100 dark:bg-amber-500/15
                                   text-amber-700 dark:text-amber-300
                                   hover:bg-amber-200 dark:hover:bg-amber-500/30 transition">
                      <i class="bi bi-arrow-repeat"></i> Retry
                    </button>
                  </form>
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

  {{-- ══════════ SETTINGS TAB ══════════ --}}
  <div x-show="activeTab === 'settings'" x-cloak>
    <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm max-w-2xl">
      <div class="p-5">
        <form method="POST" action="{{ route('admin.settings.queue.update') }}">
          @csrf

          {{-- Auto Sync Toggle --}}
          <div class="mb-5 p-4 rounded-xl
                      bg-slate-50 dark:bg-slate-800/40
                      border border-slate-200 dark:border-white/10
                      flex items-center justify-between gap-3">
            <div>
              <div class="font-medium text-sm text-slate-900 dark:text-white">Auto Sync</div>
              <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">เปิดใช้งาน Auto Sync สำหรับ background jobs</div>
            </div>
            <label class="tw-switch">
              <input type="checkbox" name="queue_auto_sync" id="autoSync" value="1"
                     {{ ($settings['queue_auto_sync'] ?? '0') == '1' ? 'checked' : '' }}>
              <span class="tw-switch-track"></span>
              <span class="tw-switch-knob"></span>
            </label>
          </div>

          {{-- Sync Interval --}}
          <div class="mb-5">
            <label for="syncInterval" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Sync Interval (minutes)</label>
            <input type="number" id="syncInterval" name="queue_sync_interval_minutes"
                   min="1" max="1440"
                   value="{{ $settings['queue_sync_interval_minutes'] ?? 30 }}"
                   class="w-full max-w-[220px] px-3 py-2 rounded-lg text-sm
                          bg-white dark:bg-slate-800
                          border border-slate-300 dark:border-white/10
                          text-slate-900 dark:text-slate-100
                          focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">ความถี่การ sync อัตโนมัติ (นาที)</div>
          </div>

          {{-- Max Retries --}}
          <div class="mb-5">
            <label for="maxRetries" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Max Retries</label>
            <input type="number" id="maxRetries" name="queue_max_retries"
                   min="0" max="20"
                   value="{{ $settings['queue_max_retries'] ?? 3 }}"
                   class="w-full max-w-[220px] px-3 py-2 rounded-lg text-sm
                          bg-white dark:bg-slate-800
                          border border-slate-300 dark:border-white/10
                          text-slate-900 dark:text-slate-100
                          focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">จำนวนครั้งสูงสุดที่จะ retry job ที่ล้มเหลว</div>
          </div>

          <button type="submit"
                  class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg text-sm font-semibold text-white
                         bg-gradient-to-r from-indigo-600 to-violet-600
                         hover:from-indigo-500 hover:to-violet-500
                         shadow-md shadow-indigo-500/30 transition">
            <i class="bi bi-save"></i> บันทึก Settings
          </button>
        </form>
      </div>
    </div>
  </div>

</div>
@endsection
