<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * RateLimit — sliding-window counter keyed by user (if authenticated) or IP.
 *
 * Usage: `rate.limit:{maxAttempts},{decayMinutes}` in a route middleware.
 *
 * Why key by user when possible: shared NATs (offices, mobile carriers,
 * student housing) share a single public IP. IP-keyed limits punish the
 * whole network for one greedy user. When we know *which* account is
 * making the request we pin the counter to them instead.
 *
 * Guests still fall back to IP. The key also includes the request path so
 * tight limits on login don't bleed into looser limits on /search.
 */
class RateLimit
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $identity = $this->identity($request);
        $key = "rate_limit:{$identity}:" . $request->path();
        $attempts = (int) Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Too many requests',
                    'retry_after' => $decayMinutes * 60,
                ], 429);
            }

            abort(429, 'คุณส่งคำขอบ่อยเกินไป กรุณารอสักครู่');
        }

        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));

        $response = $next($request);

        // Use `$response->headers->add()` instead of `withHeaders()` — the
        // latter only exists on `Illuminate\Http\Response`, not on Symfony's
        // `BinaryFileResponse` / `StreamedResponse`. ZIP downloads and
        // R2-streaming responses return those classes, so calling
        // `withHeaders()` blew up with a 500 (see RateLimit.php@47 stack).
        // `headers->add()` lives on `Symfony\...\Response` — the common base
        // of every response shape we return — so this works everywhere.
        if (method_exists($response, 'headers') || property_exists($response, 'headers')) {
            $response->headers->add([
                'X-RateLimit-Limit'     => (string) $maxAttempts,
                'X-RateLimit-Remaining' => (string) max(0, $maxAttempts - $attempts - 1),
            ]);
        }

        return $response;
    }

    /**
     * Choose the best identity for this request:
     *   admin guard → "a:{adminId}"   (separate from web users)
     *   web guard   → "u:{userId}"
     *   anon        → "ip:{ip}"
     *
     * Namespacing by guard prevents an admin's counter being shared with a
     * regular user who happens to have the same numeric ID.
     */
    private function identity(Request $request): string
    {
        if ($adminId = Auth::guard('admin')->id()) {
            return "a:{$adminId}";
        }
        if ($userId = Auth::id()) {
            return "u:{$userId}";
        }
        return 'ip:' . $request->ip();
    }
}
