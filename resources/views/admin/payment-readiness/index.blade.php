@extends('layouts.admin')

@section('title', 'Payment Readiness · ระบบซื้อแผน')

@php
  $ready = $report['ready'];
  $partial = !$ready && $report['ready_for_free_only'];

  // Banner color/icon set, picked once based on overall state
  $bannerClasses = $ready
      ? 'from-emerald-500 to-teal-600'
      : ($partial ? 'from-amber-500 to-orange-600' : 'from-rose-500 to-pink-600');
  $bannerIcon = $ready ? 'bi-shield-fill-check' : ($partial ? 'bi-exclamation-triangle-fill' : 'bi-shield-fill-exclamation');
  $bannerLabel = $ready
      ? 'พร้อมใช้งาน — ลูกค้าซื้อแผนได้'
      : ($partial ? 'พร้อมบางส่วน — Free plan ใช้ได้ แต่ paid plan ยังซื้อไม่ได้' : 'ยังไม่พร้อม — ลูกค้าซื้อแผนไม่ได้');
@endphp

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ═══════════════════ Page Header ═══════════════════ --}}
  <div class="mb-6">
    <div class="flex items-start gap-4">
      <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
        <i class="bi bi-clipboard2-pulse text-white text-xl"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
          Payment Readiness · ระบบซื้อแผน
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          ตรวจสอบว่า flow การสั่งซื้อแผนทำงานครบทุกขั้นตอน — แสดงทุกอย่างที่ต้องตั้งค่าก่อนเปิดให้ลูกค้าซื้อจริง
        </p>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Status Banner ═══════════════════ --}}
  <div class="mb-6 rounded-2xl overflow-hidden shadow-lg relative
              bg-gradient-to-br {{ $bannerClasses }}">
    <div class="absolute inset-0 opacity-30 pointer-events-none"
         style="background:radial-gradient(800px 400px at 90% 0%, rgba(255,255,255,.18), transparent 60%);"></div>
    <div class="relative p-6 sm:p-7 text-white">
      <div class="flex items-start gap-4 flex-wrap">
        <div class="w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-sm flex items-center justify-center shrink-0">
          <i class="bi {{ $bannerIcon }} text-3xl"></i>
        </div>
        <div class="flex-1 min-w-[240px]">
          <p class="text-[10px] font-bold uppercase tracking-[0.18em] opacity-85">
            ภาพรวมสถานะ
          </p>
          <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1 leading-tight">
            {{ $bannerLabel }}
          </h2>
          <div class="mt-3 flex items-center gap-2 flex-wrap text-sm">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/15 backdrop-blur-sm font-semibold">
              <i class="bi bi-check-circle-fill text-white"></i>
              ผ่าน {{ $report['passed'] }} / {{ $report['total'] }}
            </span>
            @if($report['critical_failed'] > 0)
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-900/40 backdrop-blur-sm font-semibold">
                <i class="bi bi-x-circle-fill"></i>
                Critical fail {{ $report['critical_failed'] }}
              </span>
            @endif
            @if($report['warn_failed'] > 0)
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-900/40 backdrop-blur-sm font-semibold">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Warning {{ $report['warn_failed'] }}
              </span>
            @endif
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/15 backdrop-blur-sm font-semibold">
              <i class="bi bi-credit-card-2-front-fill"></i>
              Gateway พร้อม {{ $report['active_gateways'] }}
            </span>
          </div>
        </div>
        <div class="flex flex-col gap-2">
          <a href="{{ url()->current() }}"
             class="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 backdrop-blur-sm font-semibold text-sm px-4 py-2 rounded-xl transition">
            <i class="bi bi-arrow-clockwise"></i> ตรวจสอบใหม่
          </a>
          <code class="block text-[11px] bg-black/20 px-3 py-1.5 rounded-lg whitespace-nowrap">
            php artisan payment:readiness
          </code>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Action Items (failed checks only) ═══════════════════ --}}
  @php
    $failed = collect($report['checks'])->where('pass', false)
                  ->sortBy(fn($c) => $c['level'] === 'critical' ? 0 : 1)
                  ->values();
  @endphp
  @if($failed->isNotEmpty())
    <div class="mb-6 rounded-2xl border border-amber-200 dark:border-amber-500/30 bg-amber-50/50 dark:bg-amber-500/5 p-5 sm:p-6">
      <h3 class="font-bold text-slate-900 dark:text-white text-base mb-4 flex items-center gap-2">
        <i class="bi bi-tools text-amber-600 dark:text-amber-400"></i>
        สิ่งที่ต้องแก้ ({{ $failed->count() }} รายการ)
      </h3>
      <div class="space-y-3">
        @foreach($failed as $c)
          <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-4 flex items-start gap-3">
            <span class="shrink-0 w-9 h-9 rounded-lg flex items-center justify-center
                         {{ $c['level'] === 'critical'
                              ? 'bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-300'
                              : 'bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-300' }}">
              <i class="bi {{ $c['level'] === 'critical' ? 'bi-x-octagon-fill' : 'bi-exclamation-triangle-fill' }}"></i>
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <p class="font-semibold text-slate-900 dark:text-white">{{ $c['label'] }}</p>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full
                             {{ $c['level'] === 'critical'
                                  ? 'bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300'
                                  : 'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300' }}">
                  {{ $c['level'] === 'critical' ? 'block' : 'warn' }}
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mb-2 break-words">{{ $c['detail'] }}</p>
              <p class="text-sm text-slate-700 dark:text-slate-200">
                <i class="bi bi-arrow-right-circle-fill text-indigo-500"></i>
                {{ $c['fix'] }}
              </p>
            </div>
            @if(!empty($c['fix_url']))
              <a href="{{ $c['fix_url'] }}"
                 class="shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-300 hover:text-indigo-700 dark:hover:text-indigo-200 transition self-center">
                ไปที่หน้า <i class="bi bi-box-arrow-up-right"></i>
              </a>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  @endif

  {{-- ═══════════════════ Full Checklist ═══════════════════ --}}
  <div class="mb-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-2">
      <i class="bi bi-list-check text-slate-500"></i>
      <h3 class="font-semibold text-slate-900 dark:text-white">รายการตรวจทั้งหมด ({{ $report['total'] }} ข้อ)</h3>
    </div>
    <div class="divide-y divide-slate-100 dark:divide-white/5">
      @foreach($report['checks'] as $c)
        <div class="px-5 py-3.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-white/[0.02] transition">
          <span class="shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-sm font-bold
                       {{ $c['pass']
                            ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-300'
                            : ($c['level'] === 'critical'
                                ? 'bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-300'
                                : 'bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-300') }}">
            <i class="bi {{ $c['pass'] ? 'bi-check-lg' : ($c['level'] === 'critical' ? 'bi-x-lg' : 'bi-exclamation-lg') }}"></i>
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ $c['label'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 break-words">{{ $c['detail'] }}</p>
          </div>
          <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full
                       {{ $c['level'] === 'critical'
                            ? 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30'
                            : 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30' }}">
            {{ $c['level'] }}
          </span>
        </div>
      @endforeach
    </div>
  </div>

  {{-- ═══════════════════ Gateway Grid ═══════════════════ --}}
  <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between gap-2 flex-wrap">
      <div class="flex items-center gap-2">
        <i class="bi bi-credit-card-2-front-fill text-slate-500"></i>
        <h3 class="font-semibold text-slate-900 dark:text-white">Payment gateways ({{ $report['active_gateways'] }} / {{ count($report['gateway_summary']) }} พร้อม)</h3>
      </div>
      <a href="{{ route('admin.payments.methods') }}"
         class="text-xs font-semibold text-indigo-600 dark:text-indigo-300 hover:underline inline-flex items-center gap-1">
        จัดการ <i class="bi bi-arrow-right"></i>
      </a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 p-4">
      @foreach($report['gateway_summary'] as $g)
        <div class="rounded-xl border p-4 flex items-start gap-3
                    {{ $g['ready']
                         ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5'
                         : 'border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02]' }}">
          <span class="shrink-0 w-9 h-9 rounded-lg flex items-center justify-center
                       {{ $g['ready']
                            ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-300'
                            : 'bg-slate-200 dark:bg-white/10 text-slate-400 dark:text-slate-500' }}">
            <i class="bi {{ $g['ready'] ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $g['label'] }}</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">{{ $g['type'] }}</p>
            <p class="text-xs mt-1.5 break-words
                      {{ $g['ready']
                           ? 'text-emerald-700 dark:text-emerald-300 font-semibold'
                           : 'text-slate-500 dark:text-slate-400' }}">
              {{ $g['reason'] }}
            </p>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- ═══════════════════ Health-check endpoint hint ═══════════════════ --}}
  <div class="mt-6 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/[0.02] p-4 text-xs text-slate-500 dark:text-slate-400">
    <p class="mb-1">
      <i class="bi bi-activity"></i>
      <strong class="text-slate-700 dark:text-slate-300">Health-check endpoint:</strong>
      <code class="ml-1 px-1.5 py-0.5 rounded bg-slate-200 dark:bg-white/10 text-slate-700 dark:text-slate-200 font-mono">{{ route('admin.payment-readiness.health') }}</code>
      — return HTTP 200 เมื่อพร้อม, 503 เมื่อมี critical fail (ใช้กับ uptime monitor ได้)
    </p>
    <p>
      <i class="bi bi-terminal"></i>
      <strong class="text-slate-700 dark:text-slate-300">CLI:</strong>
      <code class="ml-1 px-1.5 py-0.5 rounded bg-slate-200 dark:bg-white/10 text-slate-700 dark:text-slate-200 font-mono">php artisan payment:readiness</code>
      หรือ <code class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-white/10 font-mono">--json</code> สำหรับ machine output
    </p>
  </div>
</div>
@endsection
