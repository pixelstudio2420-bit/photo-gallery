<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessExpense;
use App\Models\Order;
use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin: Business Expense Tracking + Calculator.
 *
 * Three routes live here:
 *
 *   index()      — list every expense + top-of-page KPI cards
 *                  (monthly total, per-category breakdown, per-service split)
 *   calculator() — a dedicated "what-if" page: admin enters a projected
 *                  monthly revenue and gets margin + break-even numbers
 *                  per service
 *   store/update/destroy — standard CRUD for admin management
 *
 * The calculator is deliberately pure math: it pulls recent monthly revenue
 * from the orders table, overlays the sum of every active expense's
 * `monthlyCost()`, and returns margin / break-even values.
 */
class BusinessExpenseController extends Controller
{
    /**
     * GET /admin/business-expenses
     */
    public function index(Request $request)
    {
        $q = BusinessExpense::query()->orderByDesc('is_active')->orderBy('category')->orderBy('name');

        if ($request->filled('category')) {
            $q->where('category', $request->input('category'));
        }
        if ($request->filled('cycle')) {
            $q->where('billing_cycle', $request->input('cycle'));
        }
        if ($request->filled('status')) {
            $q->where('is_active', $request->input('status') === 'active');
        }
        if ($request->filled('search')) {
            $term = '%' . trim($request->input('search')) . '%';
            $q->where(function ($s) use ($term) {
                $s->where('name', 'ilike', $term)
                  ->orWhere('provider', 'ilike', $term)
                  ->orWhere('notes', 'ilike', $term);
            });
        }

        $expenses = $q->paginate(30)->withQueryString();

        // ── Aggregates (active rows only) ─────────────────────────────────
        $active = BusinessExpense::active()->get();

        $totalMonthly = 0.0;
        $byCategory   = [];
        $byService    = [];

        foreach ($active as $e) {
            $m = $e->monthlyCost();
            $totalMonthly += $m;
            $byCategory[$e->category] = ($byCategory[$e->category] ?? 0) + $m;

            foreach ($e->perServiceAllocation() as $service => $amt) {
                $byService[$service] = ($byService[$service] ?? 0) + $amt;
            }
        }
        arsort($byCategory);
        arsort($byService);

        // Critical expenses (flagged rows that need monitoring)
        $critical = BusinessExpense::active()->critical()->get();

        return view('admin.business-expenses.index', [
            'expenses'     => $expenses,
            'totalMonthly' => $totalMonthly,
            'totalYearly'  => $totalMonthly * 12,
            'byCategory'   => $byCategory,
            'byService'    => $byService,
            'critical'     => $critical,
            'categories'   => BusinessExpense::categories(),
            'services'     => BusinessExpense::serviceSlugs(),
            'cycles'       => BusinessExpense::billingCycles(),
            'filters'      => $request->only(['category', 'cycle', 'status', 'search']),
        ]);
    }

    public function create()
    {
        return view('admin.business-expenses.create', [
            'categories' => BusinessExpense::categories(),
            'services'   => BusinessExpense::serviceSlugs(),
            'cycles'     => BusinessExpense::billingCycles(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateExpense($request);
        BusinessExpense::create($data);
        return redirect()->route('admin.business-expenses.index')
            ->with('success', 'เพิ่มค่าใช้จ่ายเรียบร้อย');
    }

    public function edit(BusinessExpense $expense)
    {
        return view('admin.business-expenses.edit', [
            'expense'    => $expense,
            'categories' => BusinessExpense::categories(),
            'services'   => BusinessExpense::serviceSlugs(),
            'cycles'     => BusinessExpense::billingCycles(),
        ]);
    }

    public function update(Request $request, BusinessExpense $expense)
    {
        $data = $this->validateExpense($request);
        $expense->update($data);
        return redirect()->route('admin.business-expenses.index')
            ->with('success', 'อัปเดตค่าใช้จ่ายเรียบร้อย');
    }

    public function destroy(BusinessExpense $expense)
    {
        $expense->delete();
        return back()->with('success', 'ลบค่าใช้จ่ายแล้ว');
    }

    // ──────────────────────────────────────────────────────────────────
    //  Calculator
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /admin/business-expenses/calculator
     *
     * Pure-math projection page. Admin can override the projected revenue
     * via ?revenue=… query string; otherwise we use the average of the last
     * 3 fully-closed months of orders.
     */
    public function calculator(Request $request)
    {
        $active = BusinessExpense::active()->get();

        // Expense totals (monthly)
        $totalMonthly = 0.0;
        $byService    = [];
        $byCategory   = [];
        foreach ($active as $e) {
            $m = $e->monthlyCost();
            $totalMonthly += $m;
            $byCategory[$e->category] = ($byCategory[$e->category] ?? 0) + $m;
            foreach ($e->perServiceAllocation() as $service => $amt) {
                $byService[$service] = ($byService[$service] ?? 0) + $amt;
            }
        }
        arsort($byCategory);
        arsort($byService);

        // Revenue baseline: last 3 fully-closed months average
        $avgMonthlyRevenue = $this->averageMonthlyRevenue();

        // Admin override via query string (handy for what-if scenarios)
        $projectedRevenue = $request->filled('revenue')
            ? max(0, (float) $request->input('revenue'))
            : $avgMonthlyRevenue;

        // Margin / break-even math
        $grossMargin    = $projectedRevenue - $totalMonthly;
        $marginPct      = $projectedRevenue > 0
            ? round(($grossMargin / $projectedRevenue) * 100, 1)
            : null;
        $breakEvenMult  = $totalMonthly > 0 && $projectedRevenue > 0
            ? round($totalMonthly / $projectedRevenue, 2)
            : null;

        // Per-event / per-photo / per-order unit costs
        $unitCosts = $this->computeUnitCosts($totalMonthly);

        return view('admin.business-expenses.calculator', [
            'totalMonthly'       => $totalMonthly,
            'totalYearly'        => $totalMonthly * 12,
            'avgMonthlyRevenue'  => $avgMonthlyRevenue,
            'projectedRevenue'   => $projectedRevenue,
            'grossMargin'        => $grossMargin,
            'marginPct'          => $marginPct,
            'breakEvenMult'      => $breakEvenMult,
            'byCategory'         => $byCategory,
            'byService'          => $byService,
            'unitCosts'          => $unitCosts,
            'categories'         => BusinessExpense::categories(),
            'services'           => BusinessExpense::serviceSlugs(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    //  Internals
    // ──────────────────────────────────────────────────────────────────

    /**
     * Average monthly revenue over the last 3 fully-closed months.
     * Falls back to the current month's paid/completed total if history
     * is thin (new installs).
     */
    private function averageMonthlyRevenue(): float
    {
        try {
            $from = now()->startOfMonth()->subMonths(3);
            $to   = now()->startOfMonth()->subSecond();
            $total = (float) Order::whereIn('status', ['paid', 'completed'])
                ->whereBetween('created_at', [$from, $to])
                ->sum('total');
            if ($total > 0) {
                return round($total / 3, 2);
            }
            // Fallback: this month's running total
            return (float) Order::whereIn('status', ['paid', 'completed'])
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('total');
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * How much does each delivered unit (event / photo / order) cost the
     * business on average? Uses a 30-day rolling window so new installs
     * don't divide by zero.
     */
    private function computeUnitCosts(float $monthlyCost): array
    {
        $out = ['per_event' => null, 'per_photo' => null, 'per_order' => null];
        if ($monthlyCost <= 0) return $out;

        $from = now()->subDays(30);

        try {
            $events = (int) Event::where('created_at', '>=', $from)->count();
            if ($events > 0) $out['per_event'] = round($monthlyCost / $events, 2);

            $photos = (int) EventPhoto::where('created_at', '>=', $from)->count();
            if ($photos > 0) $out['per_photo'] = round($monthlyCost / $photos, 4);

            $orders = (int) Order::whereIn('status', ['paid', 'completed'])
                ->where('created_at', '>=', $from)
                ->count();
            if ($orders > 0) $out['per_order'] = round($monthlyCost / $orders, 2);
        } catch (\Throwable) {
            // fail silently — empty values render as "n/a" in the view
        }
        return $out;
    }

    private function validateExpense(Request $request): array
    {
        $data = $request->validate([
            'category'               => 'required|string|in:' . implode(',', array_keys(BusinessExpense::categories())),
            'name'                   => 'required|string|max:150',
            'provider'               => 'nullable|string|max:100',
            'description'            => 'nullable|string',
            'amount'                 => 'required|numeric|min:0',
            'currency'               => 'nullable|string|max:3',
            'original_amount'        => 'nullable|numeric|min:0',
            'original_currency'      => 'nullable|string|max:3',
            'exchange_rate'          => 'nullable|numeric|min:0',
            'billing_cycle'          => 'required|string|in:monthly,yearly,one_time,usage_based',
            'unit_cost'              => 'nullable|numeric|min:0',
            'usage_unit'             => 'nullable|string|max:30',
            'estimated_monthly_usage' => 'nullable|numeric|min:0',
            'allocated_to'           => 'nullable|array',
            'allocated_to.*'         => 'string|in:' . implode(',', array_keys(BusinessExpense::serviceSlugs())),
            'allocation_weights'     => 'nullable|array',
            'start_date'             => 'nullable|date',
            'end_date'               => 'nullable|date|after_or_equal:start_date',
            'is_active'              => 'nullable|boolean',
            'is_critical'            => 'nullable|boolean',
            'notes'                  => 'nullable|string',
        ]);

        $data['is_active']   = $request->boolean('is_active', true);
        $data['is_critical'] = $request->boolean('is_critical', false);
        $data['currency']    = $data['currency'] ?? 'THB';

        // Clean allocation_weights: strip empty/non-numeric entries
        if (!empty($data['allocation_weights'])) {
            $data['allocation_weights'] = array_filter(
                $data['allocation_weights'],
                fn($v) => is_numeric($v) && (float) $v > 0
            );
            if (empty($data['allocation_weights'])) {
                $data['allocation_weights'] = null;
            }
        }

        return $data;
    }
}
