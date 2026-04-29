<?php

namespace App\Http\Middleware;

use App\Services\Usage\QuotaResult;
use App\Services\Usage\QuotaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate every request that consumes a metered resource.
 *
 * Usage in routes:
 *
 *   Route::post('/api/face-search/{id}', ...)
 *       ->middleware('usage.quota:ai.face_search');
 *
 *   Route::post('/api/photos/upload', ...)
 *       ->middleware('usage.quota:photo.upload');
 *
 * Behaviour
 * ---------
 *   • OK         → request proceeds; X-Quota-Remaining header attached
 *   • SOFT_WARN  → request proceeds; X-Quota-Warning header attached
 *   • HARD_BLOCK → 402 Payment Required, JSON body explains
 *   • BREAKER    → 503 Service Unavailable
 *   • DISABLED   → 403 Forbidden + Upgrade-To header
 *
 * The middleware only CHECKS — it doesn't record usage. The controller
 * (or the service it calls) records via UsageMeter::record() ONLY after
 * the operation succeeds, so a failed call doesn't burn the user's quota.
 */
class EnforceUsageQuota
{
    public function __construct(private readonly QuotaService $quota) {}

    public function handle(Request $request, Closure $next, string $resource, string $units = '1'): Response
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required',
            ], 401);
        }

        $result = $this->quota->check($user, $resource, (int) $units);

        if ($result->blocked()) {
            return response()->json([
                'error'             => $result->reason,
                'resource'          => $result->resource,
                'used'              => $result->used,
                'limit'             => $result->hard,
                'period'            => $result->period,
                'state'             => $result->state,
                'utilization_pct'   => $result->utilizationPct(),
            ], $result->statusCode())->withHeaders($this->headersFor($result));
        }

        $response = $next($request);
        if (method_exists($response, 'withHeaders')) {
            $response->withHeaders($this->headersFor($result));
        } else {
            foreach ($this->headersFor($result) as $k => $v) {
                $response->headers->set($k, $v);
            }
        }
        return $response;
    }

    /**
     * Standard rate-limit-style headers so SDK clients can self-throttle.
     *
     * @return array<string, string>
     */
    private function headersFor(QuotaResult $r): array
    {
        $headers = [
            'X-Quota-Resource' => $r->resource,
            'X-Quota-Used'     => (string) $r->used,
            'X-Quota-Period'   => $r->period,
        ];
        if ($r->hard !== null) {
            $headers['X-Quota-Limit']     = (string) $r->hard;
            $headers['X-Quota-Remaining'] = (string) $r->remaining();
        }
        if ($r->state === QuotaResult::STATE_SOFT_WARN) {
            $headers['X-Quota-Warning'] = sprintf(
                'Approaching cap (%s%% used)',
                $r->utilizationPct() ?? 0,
            );
        }
        if ($r->blocked()) {
            // Surface the upgrade target so client apps can deeplink.
            $headers['X-Quota-Upgrade-To'] = match ($r->state) {
                QuotaResult::STATE_DISABLED   => '/pricing',
                QuotaResult::STATE_HARD_BLOCK => '/billing/upgrade',
                QuotaResult::STATE_BREAKER    => '/status',
                default                       => '/pricing',
            };
        }
        return $headers;
    }
}
