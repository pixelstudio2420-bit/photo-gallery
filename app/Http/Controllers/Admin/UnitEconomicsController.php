<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UnitEconomicsService;

class UnitEconomicsController extends Controller
{
    public function __construct(private UnitEconomicsService $svc) {}

    public function index()
    {
        $headline  = $this->svc->headline();
        $cohorts   = $this->svc->cohortTable();
        $monthly   = $this->svc->monthlyTrend(12);
        $customers = $this->svc->topCustomers(15);

        return view('admin.analytics.unit-economics', compact('headline', 'cohorts', 'monthly', 'customers'));
    }
}
