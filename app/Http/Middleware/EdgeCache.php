<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * EdgeCache Middleware
 * ====================
 * Set HTTP caching headers so Cloudflare / CloudFront / any CDN can serve
 * public GET requests directly from its edge without touching PHP.
 *
 * With a CDN correctly cached this middleware is the single biggest lever
 * for scaling to 50k+ concurrent: if 95% of public pageviews are served
 * from the CDN edge, the Laravel app only processes the remaining 5%.
 *
 * Safety rules:
 * - Only GET / HEAD requests
 * - Only unauthenticated users (logged-in pages are personalized)
 * - No cache on pages with `_token` / `preview` / admin query params
 * - Error responses (>= 400) cached very briefly (or not at all)
 * - Always sends `Vary: Accept-Encoding, Accept-Language` so the CDN
 *   respects per-locale and per-encoding variants
 *
 * Usage (route):
 *    Route::get('/', [...])->middleware('edge.cache:60,300');
 *    Route::get('/events', [...])->middleware('edge.cache:30,600');
 *
 * Parameters:
 *   {sMaxAge}         — seconds the CDN may serve from its own cache
 *   {staleWhileRev}   — seconds the CDN may serve stale while revalidating
 */
class EdgeCache
{
    public function handle(Request $request, Closure $next, int|string $sMaxAge = 60, int|string $staleWhileRev = 300): Response
    {
        $response = $next($request);

        // Never cache non-idempotent methods.
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $response;
        }

        // Never cache personalized pages (logged in users see cart count, etc).
        if ($this->looksAuthenticated($request)) {
            return $this->noCache($response);
        }

        // Never cache pages that carry form tokens / admin preview params.
        foreach (['_token', 'preview', 'admin_preview', 'debug'] as $param) {
            if ($request->has($param)) {
                return $this->noCache($response);
            }
        }

        // Don't cache error responses (gives the CDN a bad snapshot).
        $status = $response->getStatusCode();
        if ($status >= 400) {
            return $this->noCache($response);
        }

        // Normalize Vary so CDN keys per-encoding and per-language.
        $prevVary = $response->headers->get('Vary', '');
        $vary = array_unique(array_filter(array_map('trim', array_merge(
            $prevVary ? explode(',', $prevVary) : [],
            ['Accept-Encoding', 'Accept-Language', 'Cookie']
        ))));
        $response->headers->set('Vary', implode(', ', $vary));

        $s = (int) $sMaxAge;
        $swr = (int) $staleWhileRev;

        // Client browsers get a short max-age (users see fresh content on refresh);
        // CDN/shared caches get a longer s-maxage.
        $response->headers->set(
            'Cache-Control',
            "public, max-age=0, s-maxage={$s}, stale-while-revalidate={$swr}"
        );

        // Dedicated CDN directives (Cloudflare reads CDN-Cache-Control; Fastly/Akamai read Surrogate-Control).
        $response->headers->set('CDN-Cache-Control',       "public, s-maxage={$s}");
        $response->headers->set('Cloudflare-CDN-Cache-Control', "public, s-maxage={$s}");
        $response->headers->set('Surrogate-Control',       "public, max-age={$s}");

        return $response;
    }

    private function noCache(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->remove('CDN-Cache-Control');
        $response->headers->remove('Cloudflare-CDN-Cache-Control');
        $response->headers->remove('Surrogate-Control');
        return $response;
    }

    private function looksAuthenticated(Request $request): bool
    {
        try {
            if (Auth::guard('web')->check())   return true;
            if (Auth::guard('admin')->check()) return true;
        } catch (\Throwable $e) {}

        // If the request carries the Laravel session cookie AND looks
        // like a user who has interacted with the site, be safe and skip.
        foreach ($request->cookies->keys() as $cookie) {
            if (str_contains($cookie, 'session') || str_contains($cookie, 'remember_')) {
                // Session cookie alone is fine (guests have sessions too);
                // `remember_*` implies a persisted login → don't cache.
                if (str_starts_with($cookie, 'remember_')) return true;
            }
        }
        return false;
    }
}
