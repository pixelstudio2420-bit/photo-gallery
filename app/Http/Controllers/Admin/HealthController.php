<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RouteHealthService;
use Illuminate\Http\Request;

/**
 * Route & Page Health dashboard.
 *
 * Surfaces RouteHealthService results: which public routes are currently
 * green/red, 30-day uptime, and the recent-runs timeline. Deliberately a
 * THIN read layer — it does NOT re-implement infra monitoring (that's
 * SystemMonitor), scheduler health, or capacity; it links out to those.
 *
 * The page reads the latest persisted/cached snapshot so it loads instantly.
 * "Run now" triggers a fresh live check on demand.
 */
class HealthController extends Controller
{
    public function index(RouteHealthService $svc)
    {
        $snapshot = $svc->latestSnapshot();   // null if never run
        $uptime30 = $svc->uptime(30);
        $uptime7  = $svc->uptime(7);
        $recent   = $svc->recentRuns(20);

        return view('admin.health.index', compact('snapshot', 'uptime30', 'uptime7', 'recent'));
    }

    /**
     * Run the checks live and redirect back with the fresh result.
     * GET-only sub-requests, so this is safe to trigger from the web context
     * (the service save/restores the outer request binding).
     */
    public function run(Request $request, RouteHealthService $svc)
    {
        $snapshot = $svc->runAll();
        $s = $snapshot['summary'];

        $msg = "ตรวจเสร็จ: {$s['ok']} ok · {$s['warn']} warn · {$s['fail']} fail";

        return redirect()
            ->route('admin.health.index')
            ->with($s['fail'] > 0 ? 'error' : 'success', $msg);
    }

    /** JSON snapshot for any external uptime poller / status badge. */
    public function api(RouteHealthService $svc)
    {
        $snap = $svc->latestSnapshot();
        return response()->json([
            'healthy'    => $snap['summary']['healthy'] ?? null,
            'summary'    => $snap['summary'] ?? null,
            'checked_at' => $snap['checked_at'] ?? null,
            'uptime_30d' => $svc->uptime(30)['uptime_pct'],
        ]);
    }
}
