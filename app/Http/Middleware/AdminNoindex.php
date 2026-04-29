<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force noindex,nofollow on admin/photographer/api/dashboard/auth-flow.
 *
 * Why a middleware instead of relying on robots.txt
 * --------------------------------------------------
 * robots.txt only tells well-behaved crawlers to stay out — it does NOT
 * remove pages already in Google's index, and not all crawlers obey it.
 * A meta robots tag (or X-Robots-Tag header) actively de-indexes the
 * page and is honoured by Google/Bing/Yandex/DuckDuckGo.
 *
 * Header (not meta tag) because:
 *   - it works on JSON responses (`/api/*`) which can't render meta
 *   - it works on PDF/zip downloads via the same code path
 *   - Google honours `X-Robots-Tag` identically to the meta version
 *
 * Self-applying via path-prefix match
 * -----------------------------------
 * Registered as a global `web` middleware (in bootstrap/app.php) and
 * checks the request path itself. Any admin/photographer route is
 * protected automatically — no risk of forgetting to wire it up to
 * a new route.
 *
 * The middleware also flips request attribute `seo.suppress=true` so
 * the SeoService auto-generator skips these paths entirely. Belt AND
 * suspenders: even if a future X-Robots-Tag-stripping CDN config
 * appears, the page itself still won't have meta tags marketing it.
 */
class AdminNoindex
{
    /**
     * Path prefixes that must NEVER appear in search results.
     * Matched against request->path() (no leading slash).
     */
    private const NOINDEX_PREFIXES = [
        'admin',
        'admin/',
        'dashboard',
        'dashboard/',
        'photographer',
        'photographer/',
        'api',
        'api/',
        'profile',
        'profile/',
        'cart',
        'cart/',
        'checkout',
        'checkout/',
        '2fa',
        '2fa/',
        'forgot-password',
        'reset-password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = ltrim($request->path(), '/');

        if ($this->shouldSuppress($path)) {
            $request->attributes->set('seo.suppress', true);
        }

        $response = $next($request);

        if ($this->shouldSuppress($path)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }

    private function shouldSuppress(string $path): bool
    {
        // exact-match for top-level paths like 'profile' or '2fa'.
        if (in_array($path, self::NOINDEX_PREFIXES, true)) return true;
        foreach (self::NOINDEX_PREFIXES as $prefix) {
            if (!str_ends_with($prefix, '/')) continue;
            if (str_starts_with($path, $prefix)) return true;
        }
        return false;
    }
}
