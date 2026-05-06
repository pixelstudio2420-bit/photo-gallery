<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Admin diagnostic page — answer "ระบบซื้อแผนใช้งานได้แล้วใช่ไหม?"
 * with one click. Same data the `payment:readiness` artisan command
 * outputs, but rendered as a traffic-light dashboard with deep-links
 * straight to the admin pages where each blocker can be fixed.
 *
 * Mounted at /admin/payment-readiness — see routes/web.php.
 */
class PaymentReadinessController extends Controller
{
    public function index(PaymentReadinessService $svc): View
    {
        $report = $svc->run();

        return view('admin.payment-readiness.index', [
            'report' => $report,
        ]);
    }

    /**
     * JSON endpoint for health-check probes / external monitors.
     * Returns 200 only when every critical check passes — non-2xx
     * triggers an alert in whatever monitoring tool is watching.
     */
    public function health(PaymentReadinessService $svc): JsonResponse
    {
        $report = $svc->run();

        return response()->json([
            'ready'           => $report['ready'],
            'critical_failed' => $report['critical_failed'],
            'warn_failed'     => $report['warn_failed'],
            'active_gateways' => $report['active_gateways'],
            'checked_at'      => now()->toIso8601String(),
        ], $report['ready'] ? 200 : 503);
    }
}
