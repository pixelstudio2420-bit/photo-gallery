@extends('layouts.admin')

@section('title', 'Usage & Margin')

@section('content')
<div class="p-6 space-y-6">
    <div class="flex items-baseline justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Usage &amp; Margin</h1>
            <p class="text-sm text-gray-500">Per-plan economics, circuit breakers, and recent spike alerts.</p>
        </div>
        <div class="text-xs text-gray-500">
            Refreshed {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>

    {{-- ─── Plan Margins ─────────────────────────────────────────────── --}}
    <section class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
        <h2 class="text-lg font-semibold mb-3">Plan margins (this month)</h2>

        @if (count($planMargins) === 0)
            <p class="text-sm text-gray-500">No data yet — usage_events is empty for this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 border-b">
                        <tr>
                            <th class="py-2 pr-4">Plan</th>
                            <th class="py-2 pr-4 text-right">Subs</th>
                            <th class="py-2 pr-4 text-right">Revenue</th>
                            <th class="py-2 pr-4 text-right">Cost (AI/ops)</th>
                            <th class="py-2 pr-4 text-right">Cost (storage)</th>
                            <th class="py-2 pr-4 text-right">Margin</th>
                            <th class="py-2 pr-4 text-right">Margin %</th>
                            <th class="py-2 pr-4">Risk</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($planMargins as $row)
                            <tr class="border-b last:border-0">
                                <td class="py-2 pr-4 font-mono">{{ $row['plan_code'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($row['subs']) }}</td>
                                <td class="py-2 pr-4 text-right">฿{{ number_format($row['revenue_thb'], 2) }}</td>
                                <td class="py-2 pr-4 text-right text-gray-600">฿{{ number_format($row['cost_breakdown']['ai_and_ops_thb'] ?? 0, 2) }}</td>
                                <td class="py-2 pr-4 text-right text-gray-600">฿{{ number_format($row['cost_breakdown']['storage_thb']    ?? 0, 2) }}</td>
                                <td class="py-2 pr-4 text-right font-medium {{ $row['margin_thb'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    ฿{{ number_format($row['margin_thb'], 2) }}
                                </td>
                                <td class="py-2 pr-4 text-right">
                                    {{ $row['margin_pct'] !== null ? number_format($row['margin_pct'], 1) . '%' : '—' }}
                                </td>
                                <td class="py-2 pr-4">
                                    <span class="inline-block px-2 py-0.5 rounded text-xs
                                        @class([
                                            'bg-green-100 text-green-700' => $row['risk'] === 'healthy',
                                            'bg-yellow-100 text-yellow-700' => $row['risk'] === 'thin',
                                            'bg-orange-100 text-orange-700' => $row['risk'] === 'razor',
                                            'bg-red-100 text-red-700' => $row['risk'] === 'losing',
                                            'bg-gray-100 text-gray-700' => $row['risk'] === 'unfunded',
                                        ])
                                    ">{{ $row['risk'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ─── Circuit Breakers ─────────────────────────────────────────── --}}
    <section class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
        <h2 class="text-lg font-semibold mb-3">Circuit breakers</h2>
        <p class="text-xs text-gray-500 mb-3">
            Per-feature monthly cost ceilings. When tripped <strong>OPEN</strong> the feature
            returns 503 across <em>all</em> users until the period rolls over or you reset it.
        </p>

        @if (count($breakers) === 0)
            <p class="text-sm text-gray-500">No breakers declared. Set them in <code>config/usage.php</code>.</p>
        @else
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($breakers as $b)
                    <div class="border rounded-lg p-3
                        @class([
                            'border-red-300 bg-red-50' => $b['state'] === 'open',
                            'border-yellow-300 bg-yellow-50' => $b['state'] === 'half_open',
                            'border-gray-200' => $b['state'] === 'closed',
                        ])
                    ">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-sm">{{ $b['feature'] }}</span>
                            <span class="text-xs uppercase px-2 py-0.5 rounded
                                @class([
                                    'bg-red-600 text-white'    => $b['state'] === 'open',
                                    'bg-yellow-500 text-white' => $b['state'] === 'half_open',
                                    'bg-green-600 text-white'  => $b['state'] === 'closed',
                                ])
                            ">{{ $b['state'] }}</span>
                        </div>
                        <div class="mt-2 text-xs text-gray-600">
                            <div>Spent: ฿{{ number_format($b['spent_thb'], 2) }} / ฿{{ number_format($b['threshold_thb'], 2) }}</div>
                            <div>Utilization: {{ number_format($b['utilization_pct'], 1) }}%</div>
                            @if ($b['period_ends'])
                                <div>Resets: {{ \Illuminate\Support\Carbon::parse($b['period_ends'])->diffForHumans() }}</div>
                            @endif
                        </div>
                        <div class="mt-2 h-1.5 bg-gray-200 rounded overflow-hidden">
                            <div class="h-1.5 {{ $b['utilization_pct'] >= 80 ? 'bg-red-500' : 'bg-blue-500' }}"
                                 style="width: {{ min(100, $b['utilization_pct']) }}%"></div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            @if ($b['state'] === 'open')
                                <form method="POST" action="{{ route('admin.usage.breaker.reset', ['feature' => $b['feature']]) }}">
                                    @csrf
                                    <button type="submit" class="text-xs px-2 py-1 bg-blue-600 text-white rounded">Reset</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.usage.breaker.trip', ['feature' => $b['feature']]) }}"
                                      onsubmit="return confirm('Trip the breaker for {{ $b['feature'] }} now? This makes the feature return 503 for ALL users until you reset it.')">
                                    @csrf
                                    <button type="submit" class="text-xs px-2 py-1 bg-red-600 text-white rounded">Trip now</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ─── Top Spenders (cost outliers) ─────────────────────────────── --}}
    <section class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
        <h2 class="text-lg font-semibold mb-3">Top 20 cost outliers (this month)</h2>
        @if (count($topSpenders) === 0)
            <p class="text-sm text-gray-500">No metered usage yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 border-b">
                        <tr>
                            <th class="py-2 pr-4">User</th>
                            <th class="py-2 pr-4">Plan</th>
                            <th class="py-2 pr-4 text-right">Cost (THB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topSpenders as $u)
                            <tr class="border-b last:border-0">
                                <td class="py-2 pr-4 font-mono">#{{ $u['user_id'] }}</td>
                                <td class="py-2 pr-4">{{ $u['plan_code'] }}</td>
                                <td class="py-2 pr-4 text-right font-medium">฿{{ number_format($u['total_thb'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ─── Recent flagged spikes ────────────────────────────────────── --}}
    @if (count($flaggedSpikes) > 0)
        <section class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
            <h2 class="text-lg font-semibold mb-3">Recent flagged spikes (7 days)</h2>
            <ul class="text-sm space-y-2">
                @foreach ($flaggedSpikes as $s)
                    <li class="flex items-center gap-3">
                        <span class="font-mono text-xs text-gray-500 w-32">{{ $s['when'] }}</span>
                        <span class="font-mono">user #{{ $s['user_id'] }}</span>
                        <span class="text-xs text-gray-600">
                            {{ $s['metadata']['resource'] ?? '' }}
                            — {{ $s['metadata']['multiplier'] ?? '?' }}× baseline ({{ $s['metadata']['current'] ?? '' }}/{{ $s['metadata']['baseline'] ?? '' }})
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
