<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the public-facing blog surface (article listing + reading + RSS).
 *
 * When `Features::blogEnabled()` returns false:
 *   • Public /blog/* GET requests → 404 (so search engines drop the URLs
 *     and the system "disappears" cleanly rather than serving an
 *     explanatory page that would still be indexed).
 *   • Admin /admin/blog/* routes are NOT touched — admins can still
 *     manage drafts, categories, and most importantly flip the toggle
 *     back on. Mirrors the bypass logic in CheckSubscriptionsEnabled.
 *
 * Why 404 (and not 503 / redirect)?
 *   - 404 is what we want indexed: "this page doesn't exist". Google
 *     drops the URL from the index after a few crawls.
 *   - A redirect to homepage with a warning would leave the URLs
 *     resolving 200/302, which keeps them in the index.
 *   - 503 implies "temporary outage, retry later" — but we may have
 *     deliberately disabled the section permanently.
 */
class CheckBlogEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Features::blogEnabled()) {
            return $next($request);
        }

        // Admins still get through (e.g. someone clicks a /blog/ link
        // from an old email while logged in as admin to re-enable).
        if (auth('admin')->check()) {
            return $next($request);
        }

        // JSON-aware response so SPA panels and feed readers get a
        // clean signal instead of an HTML 404 page.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'ระบบบทความปิดใช้งาน',
            ], 404);
        }

        abort(404);
    }
}
