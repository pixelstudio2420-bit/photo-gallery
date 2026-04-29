<?php

namespace App\Http\Middleware;

use App\Services\Marketing\AttributionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures UTM + click-id parameters on every web request.
 * No-op if marketing_utm_tracking_enabled = 0 (checked inside AttributionService).
 * Runs AFTER StartSession so session is available.
 */
class CaptureUtm
{
    public function __construct(protected AttributionService $attribution) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Only capture on GET/HEAD (avoid double-logging on POST redirects)
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            $this->attribution->capture($request);
        }
        return $next($request);
    }
}
