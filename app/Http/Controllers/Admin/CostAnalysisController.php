<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Finance\CostAnalysisService;
use App\Services\Finance\PlanProfitabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Admin Finance — Cost / Profit Analysis
 *
 *   /admin/finance/cost-analysis     — daily/monthly/yearly P&L
 *   /admin/finance/plan-profit       — per-subscription-plan profitability
 *   POST /admin/finance/cost-rates   — adjust cost assumptions
 *
 * The numbers are estimates that depend on cost assumptions (storage
 * per-GB rate, server baseline, AI per-call cost). Admins can tune
 * those at /admin/finance/cost-analysis via the "ปรับสมมติฐานต้นทุน"
 * panel and the analysis re-renders against the new rates.
 */
class CostAnalysisController extends Controller
{
    public function __construct(
        private CostAnalysisService $costs,
        private PlanProfitabilityService $planProfit,
    ) {}

    /**
     * Daily / monthly / yearly P&L view. Period selectable via query
     * string ?period=day|month|year, or ?from=YYYY-MM-DD&to=YYYY-MM-DD
     * for a custom range.
     */
    public function costAnalysis(Request $request)
    {
        $period = $request->query('period', 'month');
        $from   = $request->query('from') ? Carbon::parse($request->query('from')) : null;
        $to     = $request->query('to')   ? Carbon::parse($request->query('to'))   : null;

        if ($from && $to) {
            $period = 'custom';
        }

        $analysis = $this->costs->analyse($period, $from, $to);

        // Multi-period side-by-side for the summary cards
        $today = $this->costs->analyse('day');
        $thisMonth = $period === 'month' ? $analysis : $this->costs->analyse('month');
        $thisYear = $this->costs->analyse('year');

        return view('admin.finance.cost-analysis', [
            'analysis'  => $analysis,
            'period'    => $period,
            'today'     => $today,
            'thisMonth' => $thisMonth,
            'thisYear'  => $thisYear,
            'rates'     => $this->costs->getRates(),
        ]);
    }

    /**
     * Per-plan profitability dashboard.
     */
    public function planProfit(Request $request)
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : now()->startOfMonth();
        $to   = $request->query('to')   ? Carbon::parse($request->query('to'))   : now()->endOfMonth();

        $report = $this->planProfit->report($from, $to);

        return view('admin.finance.plan-profit', [
            'report' => $report,
            'from'   => $from,
            'to'     => $to,
        ]);
    }

    /**
     * Update one or more cost rates. Expects a posted form with
     * `rate_<key> = value` fields. Flushes the cost analysis cache.
     */
    public function updateRates(Request $request)
    {
        $allowed = array_keys(CostAnalysisService::DEFAULTS);
        $data = $request->validate(array_combine(
            array_map(fn($k) => 'rate_' . $k, $allowed),
            array_fill(0, count($allowed), 'nullable|numeric|min:0|max:1000000')
        ));

        foreach ($allowed as $key) {
            $field = 'rate_' . $key;
            if ($request->filled($field)) {
                $this->costs->setRate($key, (float) $data[$field]);
            }
        }

        // Flush the cost analysis caches so the next page load reflects
        // the new rates immediately.
        Cache::flush();

        return redirect()
            ->route('admin.finance.cost-analysis')
            ->with('success', 'อัปเดตค่าสมมติฐานต้นทุนเรียบร้อย');
    }
}
