<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CapacityPlannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Server Capacity & Scaling Dashboard.
 *
 * Complementary to the existing SystemMonitor dashboard — that one answers
 * "what's happening right now", this one answers "what CAN we handle, and
 * when do we outgrow this box".
 */
class SystemCapacityController extends Controller
{
    public function __construct(private CapacityPlannerService $planner) {}

    public function index(Request $request)
    {
        $profile = $this->profileFromRequest($request);
        $target  = max(0, (int) $request->input('target', 0));

        $specs    = $this->planner->serverSpecs();
        $capacity = $this->planner->capacityEstimate($profile);
        $load     = $this->planner->currentLoad();
        $growth   = $this->planner->growthProjection();
        $cost     = $this->planner->costPerUser();

        // What-if only runs when admin asks
        $whatIf = $target > 0
            ? $this->planner->whatIf($target, $profile)
            : null;

        // Pre-compute scale presets so the view can offer quick actions
        $presets = [
            ['label' => '500 คน',   'value' => 500],
            ['label' => '1,000 คน', 'value' => 1000],
            ['label' => '2,500 คน', 'value' => 2500],
            ['label' => '5,000 คน', 'value' => 5000],
            ['label' => '10,000 คน', 'value' => 10000],
        ];

        // Human-friendly tier labels for the view
        $tierLabels = [
            'web_workers' => ['label' => 'PHP-FPM Workers', 'icon' => 'bi-cpu',          'color' => 'indigo'],
            'cpu_cores'   => ['label' => 'CPU Cores',       'icon' => 'bi-speedometer2', 'color' => 'amber'],
            'database'    => ['label' => 'MySQL Database',  'icon' => 'bi-database',     'color' => 'emerald'],
            'memory'      => ['label' => 'RAM / Cache',     'icon' => 'bi-memory',       'color' => 'rose'],
        ];

        return view('admin.system.capacity', compact(
            'specs', 'capacity', 'load', 'growth', 'cost',
            'whatIf', 'profile', 'target', 'presets', 'tierLabels'
        ));
    }

    /**
     * Admin-triggered cache bust for server spec introspection.
     * (specs are cached for 5 min to avoid re-running shell_exec every page load)
     */
    public function refresh(Request $request)
    {
        Cache::forget('capacity:specs');
        Cache::forget('capacity:cpu');
        Cache::forget('capacity:ram');

        return redirect()
            ->route('admin.system.capacity')
            ->with('success', 'รีเฟรชข้อมูลเซิร์ฟเวอร์แล้ว');
    }

    /**
     * Extract workload-profile overrides from the form. Only integer
     * values > 0 count — blanks fall back to defaults inside the service.
     */
    protected function profileFromRequest(Request $request): array
    {
        return array_filter([
            'avg_req_per_user_per_min' => $request->integer('req_per_user'),
            'avg_req_duration_ms'      => $request->integer('req_ms'),
            'ram_per_worker_mb'        => $request->integer('ram_per_worker'),
            'peak_multiplier'          => $request->integer('peak_mult'),
            'db_queries_per_request'   => $request->integer('db_queries'),
            'safety_headroom_pct'      => $request->integer('headroom_pct'),
        ], fn ($v) => $v > 0);
    }
}
