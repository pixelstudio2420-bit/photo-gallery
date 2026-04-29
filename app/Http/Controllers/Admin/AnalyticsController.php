<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\CapacityCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-side analytics endpoints. JSON-only — the front-end Blade
 * dashboard pulls from these via fetch().
 *
 * Auth: same admin guard as every other /admin/* route.
 *
 * The CLI command `php artisan analytics:capacity-report` returns
 * the same data as snapshot() in human-readable form. Use whichever
 * fits the consumer.
 */
class AnalyticsController extends Controller
{
    public function __construct(
        private readonly CapacityCalculator $calc,
    ) {}

    /**
     * GET /admin/analytics/capacity
     * Returns the live capacity snapshot.
     */
    public function capacity(): JsonResponse
    {
        return response()->json($this->calc->snapshot());
    }

    /**
     * GET /admin/analytics/trend?days=30
     * Returns daily aggregates for charting.
     */
    public function trend(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) $request->query('days', 30)));
        return response()->json([
            'days'  => $days,
            'data'  => $this->calc->dailyTrend($days),
        ]);
    }
}
