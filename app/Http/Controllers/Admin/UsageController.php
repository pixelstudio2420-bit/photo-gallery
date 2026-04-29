<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Usage\CircuitBreakerService;
use App\Services\Usage\PlanCostCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin "Usage & Margin" dashboard.
 *
 * Shows in one place:
 *   • Per-plan revenue vs cost vs margin
 *   • Top-N most expensive users (cost outliers worth investigating)
 *   • Circuit-breaker state for every declared feature
 *   • The 24h trend of usage_events totals (cheap roll-up)
 *
 * The page is read-only by default — actions (manual breaker
 * trip / reset) sit behind explicit POST endpoints below so the
 * dashboard renders even when the breaker rows are missing.
 */
class UsageController extends Controller
{
    public function __construct(
        private readonly CircuitBreakerService $breakers,
        private readonly PlanCostCalculator    $calculator,
    ) {}

    public function index(): View
    {
        return view('admin.usage.index', [
            'planMargins'   => $this->safe(fn () => $this->calculator->planMargins()),
            'topSpenders'   => $this->safe(fn () => $this->calculator->topSpenders(20)),
            'breakers'      => $this->safe(fn () => $this->breakers->snapshot()),
            'recentEvents'  => $this->recentEventsTrend(),
            'flaggedSpikes' => $this->recentSpikes(),
        ]);
    }

    public function tripBreaker(Request $request, string $feature): RedirectResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:255',
        ]);
        $this->breakers->trip($feature, $request->input('note', 'Tripped by admin'));
        return back()->with('success', "Tripped breaker for '{$feature}'");
    }

    public function resetBreaker(Request $request, string $feature): RedirectResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:255',
        ]);
        $this->breakers->reset($feature, $request->input('note'));
        return back()->with('success', "Reset breaker for '{$feature}' to half-open");
    }

    /** 24-hour bucketed totals across the platform — for the trend chart. */
    private function recentEventsTrend(): array
    {
        try {
            return DB::table('usage_events')
                ->where('occurred_at', '>=', now()->subDay())
                ->selectRaw("to_char(occurred_at, 'YYYY-MM-DD HH24:00') AS hour,
                             SUM(units) AS units,
                             SUM(cost_microcents) AS cost")
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->map(fn ($r) => [
                    'hour'  => (string) $r->hour,
                    'units' => (int) $r->units,
                    'thb'   => round(((int) $r->cost) / 1_000_000 * (float) config('usage.usd_to_thb_rate', 35.0), 2),
                ])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Recently flagged spikes — read sentinel rows the spike command writes. */
    private function recentSpikes(): array
    {
        try {
            return DB::table('usage_events')
                ->where('resource', '_spike')
                ->where('occurred_at', '>=', now()->subDays(7))
                ->orderByDesc('occurred_at')
                ->limit(50)
                ->get()
                ->map(fn ($r) => [
                    'user_id'  => (int) $r->user_id,
                    'metadata' => is_string($r->metadata) ? (array) json_decode($r->metadata, true) : (array) $r->metadata,
                    'when'     => (string) $r->occurred_at,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function safe(callable $cb): array
    {
        try {
            $result = $cb();
            return is_array($result) ? $result : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
