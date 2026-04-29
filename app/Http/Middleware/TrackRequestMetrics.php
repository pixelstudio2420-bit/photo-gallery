<?php

namespace App\Http\Middleware;

use App\Services\Analytics\UsageTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures a single line of telemetry per request — feeds the
 * usage-analytics + capacity dashboards.
 *
 * Cost
 * ----
 *   • One Cache::increment() call (atomic, ~50µs on Redis, slightly
 *     slower on file driver) per metric we touch.
 *   • Zero DB writes per request.
 *
 * Failure mode
 * ------------
 * Wrapped in try/catch so a metrics outage NEVER breaks a user
 * request. If the cache backend dies, requests still go through —
 * we just lose that minute's bucket.
 *
 * Order in the middleware stack
 * -----------------------------
 * Mount this OUTSIDE all other middleware so the timing covers the
 * full PHP→user round trip including auth, session, CSRF. Look at
 * `bootstrap/app.php` and use `prepend()` on the `web` group.
 */
class TrackRequestMetrics
{
    public function __construct(private readonly UsageTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startMs = (int) (microtime(true) * 1000);

        // Run the rest of the stack first; we record OUTBOUND so the
        // duration measurement covers everything.
        $response = $next($request);

        try {
            $this->tracker->record(
                routeGroup:  $this->classify($request),
                statusCode:  $response->getStatusCode(),
                durationMs:  (int) ((microtime(true) * 1000) - $startMs),
                userId:      Auth::id() ? (int) Auth::id() : null,
            );
        } catch (\Throwable) {
            // never propagate from analytics
        }

        return $response;
    }

    /**
     * Classify the request into a coarse route group.
     *
     * The same buckets are referenced in the dashboard; if you add a
     * new bucket here, document it in:
     *   - request_minute_buckets schema comment
     *   - admin/dashboard/capacity blade
     */
    private function classify(Request $request): string
    {
        $path   = ltrim($request->path(), '/');
        $method = strtoupper($request->method());

        if ($path === 'up') return 'health';

        if (str_starts_with($path, 'api/webhooks/'))           return 'api.webhook';
        if (str_starts_with($path, 'api/uploads/'))            return 'api.upload';
        if (str_starts_with($path, 'api/'))                    return 'api.other';

        if (str_starts_with($path, 'admin/'))                  return 'admin';
        if (str_starts_with($path, 'photographer/'))           return 'photographer.' . ($method === 'GET' ? 'read' : 'write');

        if (str_starts_with($path, 'auth/') ||
            str_starts_with($path, 'photographer/login') ||
            str_starts_with($path, 'photographer/register')) {
            return 'auth.write';
        }

        // Public — split read/write because they have different cost.
        return 'public.' . ($method === 'GET' ? 'read' : 'write');
    }
}
