<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\StoragePlan;
use App\Services\UserStorageService;
use Illuminate\View\View;

/**
 * Public pricing page for consumer cloud storage.
 *
 * Rendered at `/storage/pricing` and `/cloud-pricing` — the marketing
 * landing page for the storage business. Shows all active public plans
 * with their THB price, quotas, and features.
 *
 * When the master toggle (`sales_mode_storage_enabled`) is off we still
 * render the page but show a "coming soon" banner and disable the CTA
 * buttons. This lets us soft-launch: put the page live, watch analytics,
 * flip the switch when ready.
 */
class StoragePricingController extends Controller
{
    public function __construct(private UserStorageService $svc) {}

    public function index(): View
    {
        $plans = StoragePlan::active()->public()->ordered()->get();

        return view('storage.pricing', [
            'plans'          => $plans,
            'salesOpen'      => $this->svc->salesModeEnabled(),
            'systemEnabled'  => $this->svc->systemEnabled(),
        ]);
    }
}
