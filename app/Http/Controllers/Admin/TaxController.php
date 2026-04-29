<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PhotographerPayout;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index()
    {
        // Revenue stats
        $totalRevenue = Order::whereIn('status', ['completed', 'paid'])->sum('total');
        $totalExpenses = PhotographerPayout::whereIn('status', ['paid', 'completed'])->sum('payout_amount');
        $platformFees = PhotographerPayout::sum('platform_fee');
        $profit = $totalRevenue - $totalExpenses;

        // Monthly breakdown (last 12 months)
        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthKey = $month->format('Y-m');
            $monthLabel = $month->locale('th')->translatedFormat('M Y');
            $revenue = Order::whereIn('status', ['completed', 'paid'])
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('total');
            $expenses = PhotographerPayout::whereIn('status', ['paid', 'completed'])
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('payout_amount');
            $monthlyData[] = [
                'key' => $monthKey,
                'label' => $monthLabel,
                'revenue' => $revenue,
                'expenses' => $expenses,
                'profit' => $revenue - $expenses,
            ];
        }

        // VAT settings
        $vatSettings = [
            'vat_enabled' => AppSetting::get('vat_enabled', '0'),
            'vat_rate' => (float) AppSetting::get('vat_rate', 7),
            'vat_threshold' => (float) AppSetting::get('vat_threshold', 1800000),
            'vat_alert_enabled' => AppSetting::get('vat_alert_enabled', '0'),
            'company_tax_id' => AppSetting::get('company_tax_id', ''),
        ];

        // Check if revenue exceeds VAT threshold
        $yearRevenue = Order::whereIn('status', ['completed', 'paid'])
            ->whereYear('created_at', now()->year)
            ->sum('total');
        $vatWarning = !($vatSettings['vat_enabled'] === '1') && $yearRevenue >= $vatSettings['vat_threshold'];

        $stats = [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'platform_fees' => $platformFees,
            'profit' => $profit,
            'year_revenue' => $yearRevenue,
            'profit_margin' => $totalRevenue > 0 ? ($profit / $totalRevenue * 100) : 0,
        ];

        return view('admin.tax.index', compact('stats', 'monthlyData', 'vatSettings', 'vatWarning'));
    }

    public function updateVatSettings(Request $request)
    {
        $request->validate([
            'vat_rate' => 'required|numeric|min:0|max:30',
            'vat_threshold' => 'required|numeric|min:0',
            'company_tax_id' => 'nullable|string|max:20',
        ]);

        AppSetting::set('vat_enabled', $request->has('vat_enabled') ? '1' : '0');
        AppSetting::set('vat_rate', $request->vat_rate);
        AppSetting::set('vat_threshold', $request->vat_threshold);
        AppSetting::set('vat_alert_enabled', $request->has('vat_alert_enabled') ? '1' : '0');
        if ($request->filled('company_tax_id')) {
            AppSetting::set('company_tax_id', $request->company_tax_id);
        }

        return back()->with('success', 'บันทึกการตั้งค่า VAT สำเร็จ');
    }

    public function costs()
    {
        // Project cost breakdown
        $totalRevenue = Order::whereIn('status', ['completed', 'paid'])->sum('total');
        $photographerCosts = PhotographerPayout::whereIn('status', ['paid', 'completed'])->sum('payout_amount');
        $platformFees = PhotographerPayout::sum('platform_fee');

        // Per-event costs.
        // Using whereHas (not having on subquery alias) for cross-DB
        // compatibility — Postgres rejects HAVING on a select-list alias
        // without GROUP BY (MySQL allows it as a non-standard extension).
        // whereHas compiles to an EXISTS subquery that works on every DB.
        $eventCosts = \App\Models\Event::query()
            ->whereHas('orders', fn($q) => $q->whereIn('status', ['completed', 'paid']))
            ->withCount(['orders' => fn($q) => $q->whereIn('status', ['completed', 'paid'])])
            ->withSum(['orders' => fn($q) => $q->whereIn('status', ['completed', 'paid'])], 'total')
            ->orderByDesc('orders_sum_total')
            ->limit(20)
            ->get()
            ->map(function ($event) {
                $payouts = PhotographerPayout::where('order_id', '!=', null)
                    ->whereHas('order', fn($q) => $q->where('event_id', $event->id))
                    ->sum('payout_amount');
                $event->photographer_cost = $payouts;
                $event->platform_revenue = ($event->orders_sum_total ?? 0) - $payouts;
                $event->margin_pct = ($event->orders_sum_total ?? 0) > 0
                    ? (($event->platform_revenue / $event->orders_sum_total) * 100) : 0;
                return $event;
            });

        $stats = [
            'total_revenue' => $totalRevenue,
            'photographer_costs' => $photographerCosts,
            'platform_revenue' => $platformFees,
            'cost_ratio' => $totalRevenue > 0 ? ($photographerCosts / $totalRevenue * 100) : 0,
        ];

        return view('admin.tax.costs', compact('stats', 'eventCosts'));
    }
}
