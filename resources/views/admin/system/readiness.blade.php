@extends('layouts.admin')

@section('title', 'Production Readiness')

@push('styles')
@include('admin.settings._shared-styles')
<style>
  /* Category color swatches — explicit classes so Tailwind JIT picks them up */
  .cat-indigo   { --ic-bg: rgb(99 102 241 / 0.10); --ic-fg: rgb(79 70 229); }
  .dark .cat-indigo { --ic-bg: rgb(99 102 241 / 0.15); --ic-fg: rgb(165 180 252); }
  .cat-emerald  { --ic-bg: rgb(16 185 129 / 0.10); --ic-fg: rgb(5 150 105); }
  .dark .cat-emerald { --ic-bg: rgb(16 185 129 / 0.15); --ic-fg: rgb(110 231 183); }
  .cat-sky      { --ic-bg: rgb(14 165 233 / 0.10); --ic-fg: rgb(2 132 199); }
  .dark .cat-sky { --ic-bg: rgb(14 165 233 / 0.15); --ic-fg: rgb(125 211 252); }
  .cat-violet   { --ic-bg: rgb(139 92 246 / 0.10); --ic-fg: rgb(124 58 237); }
  .dark .cat-violet { --ic-bg: rgb(139 92 246 / 0.15); --ic-fg: rgb(196 181 253); }
  .cat-amber    { --ic-bg: rgb(245 158 11 / 0.10); --ic-fg: rgb(217 119 6); }
  .dark .cat-amber { --ic-bg: rgb(245 158 11 / 0.15); --ic-fg: rgb(252 211 77); }
  .cat-rose     { --ic-bg: rgb(244 63 94 / 0.10); --ic-fg: rgb(225 29 72); }
  .dark .cat-rose { --ic-bg: rgb(244 63 94 / 0.15); --ic-fg: rgb(253 164 175); }
  .cat-fuchsia  { --ic-bg: rgb(217 70 239 / 0.10); --ic-fg: rgb(192 38 211); }
  .dark .cat-fuchsia { --ic-bg: rgb(217 70 239 / 0.15); --ic-fg: rgb(240 171 252); }
  .cat-slate    { --ic-bg: rgb(100 116 139 / 0.12); --ic-fg: rgb(71 85 105); }
  .dark .cat-slate { --ic-bg: rgb(100 116 139 / 0.20); --ic-fg: rgb(203 213 225); }

  .cat-icon-tile {
    background: var(--ic-bg);
    color: var(--ic-fg);
  }
  .cat-count { color: var(--ic-fg); }
</style>
@endpush

@php
    // Group the checks by category for presentation
    $byCat = [];
    foreach ($readiness['checks'] as $c) {
        $byCat[$c['category']][] = $c;
    }

    $catMeta = [
        'env'      => ['label' => 'Environment',  'icon' => 'bi-gear-wide-connected', 'color' => 'indigo'],
        'perf'     => ['label' => 'Performance',  'icon' => 'bi-speedometer2',        'color' => 'emerald'],
        'storage'  => ['label' => 'Storage',      'icon' => 'bi-hdd-stack',           'color' => 'sky'],
        'features' => ['label' => 'Features',     'icon' => 'bi-puzzle',              'color' => 'violet'],
        'ops'      => ['label' => 'Operations',   'icon' => 'bi-diagram-3',           'color' => 'amber'],
        'security' => ['label' => 'Security',     'icon' => 'bi-shield-check',        'color' => 'rose'],
        'scaling'  => ['label' => 'Scaling',      'icon' => 'bi-arrows-angle-expand', 'color' => 'fuchsia'],
        'general'  => ['label' => 'General',      'icon' => 'bi-list-check',          'color' => 'slate'],
    ];

    $tierBadge = match($readiness['tier']) {
        'production-ready' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30',
        'staging'          => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-500/30',
        'development'      => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30',
        default            => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30',
    };

    $scoreColor = $readiness['score'] >= 90 ? 'text-emerald-600 dark:text-emerald-400'
                : ($readiness['score'] >= 75 ? 'text-sky-600 dark:text-sky-400'
                : ($readiness['score'] >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400'));

    $ringColor = $readiness['score'] >= 90 ? '#10b981'
                : ($readiness['score'] >= 75 ? '#0ea5e9'
                : ($readiness['score'] >= 50 ? '#f59e0b' : '#f43f5e'));
@endphp

@section('content')
<div class="max-w-7xl mx-auto pb-16">

    {{-- ═══════════════════ Header ═══════════════════ --}}
    <div class="mb-8">
        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-4">
            <a href="{{ route('admin.system.dashboard') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">System Monitor</a>
            <i class="bi bi-chevron-right text-[0.6rem]"></i>
            <span class="text-slate-700 dark:text-slate-200">Production Readiness</span>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <i class="bi bi-rocket-takeoff-fill text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                        Production Readiness Scorecard
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        ตรวจสอบความพร้อมก่อนขึ้น production — environment, performance, storage, operations, security
                    </p>
                </div>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.system.dashboard') }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-medium transition">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <button onclick="location.reload()"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 shadow-lg shadow-indigo-500/20 transition">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Re-run Checks</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════ Score Hero ═══════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-6">

        {{-- Score ring --}}
        <div class="lg:col-span-4 rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 p-6 flex flex-col items-center justify-center shadow-sm">
            <div class="relative w-44 h-44">
                <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="42" fill="none" class="stroke-slate-200 dark:stroke-slate-700" stroke-width="8"/>
                    <circle cx="50" cy="50" r="42" fill="none"
                            stroke="{{ $ringColor }}"
                            stroke-width="8"
                            stroke-linecap="round"
                            stroke-dasharray="{{ 2 * pi() * 42 }}"
                            stroke-dashoffset="{{ 2 * pi() * 42 * (1 - $readiness['score'] / 100) }}"
                            style="transition: stroke-dashoffset 1s ease-out"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="{{ $scoreColor }} text-5xl font-black">{{ $readiness['score'] }}</span>
                    <span class="text-slate-400 dark:text-slate-500 text-xs mt-1">/ 100</span>
                </div>
            </div>
            <div class="mt-4 px-3 py-1.5 rounded-full text-xs font-semibold border {{ $tierBadge }}">
                {{ strtoupper(str_replace('-', ' ', $readiness['tier'])) }}
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-xs mt-3 text-center max-w-[14rem] leading-relaxed">
                @if($readiness['tier'] === 'production-ready')
                    ✓ พร้อมขึ้น production ได้เลย
                @elseif($readiness['tier'] === 'staging')
                    ยังขาดบางข้อ — พร้อมสำหรับ staging / internal beta
                @elseif($readiness['tier'] === 'development')
                    ใช้งานได้ แต่ยังไม่เหมาะกับ traffic จริง
                @else
                    ต้องแก้ไขหลายจุดก่อนขึ้น production
                @endif
            </p>
        </div>

        {{-- Stat tiles --}}
        <div class="lg:col-span-8 grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-2xl border border-emerald-200 dark:border-emerald-500/20 bg-emerald-50/70 dark:bg-emerald-500/5 p-5">
                <div class="text-xs text-emerald-700 dark:text-emerald-400 font-semibold tracking-wide uppercase">Passed</div>
                <div class="text-4xl font-black text-emerald-700 dark:text-emerald-300 mt-2">{{ $readiness['passed'] }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ครบถ้วน</div>
            </div>
            <div class="rounded-2xl border border-amber-200 dark:border-amber-500/20 bg-amber-50/70 dark:bg-amber-500/5 p-5">
                <div class="text-xs text-amber-700 dark:text-amber-400 font-semibold tracking-wide uppercase">Warnings</div>
                <div class="text-4xl font-black text-amber-700 dark:text-amber-300 mt-2">{{ $readiness['warn'] }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ควรปรับปรุง</div>
            </div>
            <div class="rounded-2xl border border-rose-200 dark:border-rose-500/20 bg-rose-50/70 dark:bg-rose-500/5 p-5">
                <div class="text-xs text-rose-700 dark:text-rose-400 font-semibold tracking-wide uppercase">Failed</div>
                <div class="text-4xl font-black text-rose-700 dark:text-rose-300 mt-2">{{ $readiness['failed'] }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ต้องแก้ไข</div>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 p-5">
                <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold tracking-wide uppercase">Total</div>
                <div class="text-4xl font-black text-slate-900 dark:text-white mt-2">{{ $readiness['total'] }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">รายการทั้งหมด</div>
            </div>

            {{-- Quick commands --}}
            <div class="col-span-2 md:col-span-4 rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 p-4">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-2">
                    <i class="bi bi-terminal text-indigo-600 dark:text-indigo-400"></i>
                    <span>Quick commands</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-[0.7rem] font-mono">
                    <code class="px-3 py-2 rounded bg-slate-100 dark:bg-slate-950/70 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 truncate">php artisan config:cache</code>
                    <code class="px-3 py-2 rounded bg-slate-100 dark:bg-slate-950/70 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 truncate">php artisan route:cache</code>
                    <code class="px-3 py-2 rounded bg-slate-100 dark:bg-slate-950/70 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 truncate">php artisan view:cache</code>
                    <code class="px-3 py-2 rounded bg-slate-100 dark:bg-slate-950/70 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 truncate">php artisan queue:restart</code>
                    <code class="px-3 py-2 rounded bg-slate-100 dark:bg-slate-950/70 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 truncate">php artisan system:health</code>
                    <code class="px-3 py-2 rounded bg-slate-100 dark:bg-slate-950/70 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 truncate">php artisan optimize</code>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════ Action items (failing checks) ═══════════════════ --}}
    @php
        $failingChecks = array_filter($readiness['checks'], fn($c) => $c['status'] !== 'ok' && !empty($c['note']));
    @endphp
    @if(count($failingChecks) > 0)
    <div class="rounded-2xl border border-rose-200 dark:border-rose-500/20 bg-gradient-to-br from-rose-50 to-white dark:from-rose-950/40 dark:to-slate-900/40 p-5 mb-6">
        <div class="flex items-center gap-2 mb-3">
            <i class="bi bi-exclamation-triangle-fill text-rose-600 dark:text-rose-400 text-lg"></i>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">ต้องแก้ไข ({{ count($failingChecks) }} รายการ)</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            @foreach($failingChecks as $c)
            <div class="rounded-lg bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-white/10 px-3 py-2 flex items-start gap-2">
                <i class="bi bi-x-circle-fill text-rose-600 dark:text-rose-400 mt-0.5 shrink-0"></i>
                <div class="min-w-0">
                    <div class="text-sm text-slate-900 dark:text-white font-medium">{{ $c['name'] }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $c['note'] }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ═══════════════════ Checks grouped by category ═══════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach($byCat as $cat => $items)
            @php
                $meta = $catMeta[$cat] ?? $catMeta['general'];
                $colorClass = 'cat-' . $meta['color'];
                $passed = count(array_filter($items, fn($c) => $c['status'] === 'ok'));
                $total = count($items);
            @endphp
            <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center cat-icon-tile {{ $colorClass }}">
                            <i class="bi {{ $meta['icon'] }}"></i>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $meta['label'] }}</h3>
                    </div>
                    <div class="text-xs font-semibold">
                        <span class="cat-count {{ $colorClass }}">{{ $passed }}</span>
                        <span class="text-slate-400 dark:text-slate-500">/ {{ $total }}</span>
                    </div>
                </div>

                <div class="space-y-1">
                    @foreach($items as $c)
                        @php
                            $statusCls = match($c['status']) {
                                'ok'   => ['bi-check-circle-fill', 'text-emerald-600 dark:text-emerald-400'],
                                'warn' => ['bi-exclamation-triangle-fill', 'text-amber-600 dark:text-amber-400'],
                                default => ['bi-x-circle-fill', 'text-rose-600 dark:text-rose-400'],
                            };
                        @endphp
                        <div class="flex items-start gap-3 px-3 py-2 rounded-lg hover:bg-slate-50 dark:hover:bg-white/5 border border-transparent hover:border-slate-200 dark:hover:border-white/10 transition">
                            <i class="bi {{ $statusCls[0] }} {{ $statusCls[1] }} text-base shrink-0 mt-0.5"></i>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm {{ $c['status'] === 'ok' ? 'text-slate-700 dark:text-slate-300' : 'text-slate-900 dark:text-white font-medium' }}">
                                    {{ $c['name'] }}
                                </div>
                                @if(!empty($c['note']))
                                    <div class="text-[0.7rem] text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">{{ $c['note'] }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- ═══════════════════ Footer info ═══════════════════ --}}
    <div class="mt-8 rounded-2xl border border-sky-200 dark:border-sky-500/20 bg-sky-50 dark:bg-sky-500/5 p-5">
        <div class="flex items-start gap-3">
            <i class="bi bi-info-circle-fill text-sky-600 dark:text-sky-400 text-xl mt-0.5"></i>
            <div class="text-xs text-slate-600 dark:text-slate-400 space-y-1">
                <p class="text-slate-900 dark:text-white font-medium text-sm">เกี่ยวกับ readiness scorecard</p>
                <p>• คะแนนคำนวณจาก: <code class="text-slate-700 dark:text-slate-300 font-mono">(passed + warn × 0.5) / total × 100</code></p>
                <p>• ≥ 90 = production-ready, 75–89 = staging, 50–74 = development, &lt; 50 = early-dev</p>
                <p>• รัน <code class="text-slate-700 dark:text-slate-300 font-mono">php artisan system:health</code> จาก CLI เพื่อดูผลเดียวกัน (ใช้ใน CI/CD ได้)</p>
                <p>• ดู JSON: <a href="{{ route('admin.system.api.readiness') }}" target="_blank" class="text-sky-600 dark:text-sky-400 hover:underline">{{ route('admin.system.api.readiness') }}</a></p>
            </div>
        </div>
    </div>

</div>
@endsection
