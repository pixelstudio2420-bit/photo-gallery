<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Redirect all HTTP requests to HTTPS in production.
     *
     * Controlled by:
     *   - APP_ENV=production (auto-enables)
     *   - FORCE_HTTPS=true   (explicit override for any env)
     *
     * Skips redirect for:
     *   - Already HTTPS requests
     *   - Health-check endpoints
     *   - Requests behind a trusted proxy reporting HTTPS via X-Forwarded-Proto
     */
    public function handle(Request $request, Closure $next): Response
    {
        $forceHttps = env('FORCE_HTTPS', false) || app()->environment('production');

        if ($forceHttps && !$request->secure()) {
            // Trust proxy headers (load balancer / CloudFlare / nginx)
            if ($request->header('X-Forwarded-Proto') === 'https') {
                $request->server->set('HTTPS', 'on');
                return $next($request);
            }

            // Skip for health/ping endpoints
            if (in_array($request->path(), ['health', 'ping', 'up'], true)) {
                return $next($request);
            }

            return redirect()->secure($request->getRequestUri(), 301);
        }

        // Set security headers
        $response = $next($request);

        if ($forceHttps && $response instanceof Response) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
