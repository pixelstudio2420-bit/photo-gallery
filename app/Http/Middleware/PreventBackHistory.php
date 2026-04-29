<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent Back History
 * ─────────────────────────────────────────
 * Adds HTTP headers that tell the browser NOT to cache
 * authenticated pages. After logout, pressing the back
 * button will trigger a fresh request (which redirects
 * to login) instead of showing a stale cached page.
 *
 * Applied to all authenticated route groups:
 *   - Admin panel
 *   - Photographer panel
 *   - Customer authenticated pages
 */
class PreventBackHistory
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Set headers via the headers bag so we work with both
        // Illuminate\Http\Response (has withHeaders()) and
        // Symfony\Component\HttpFoundation\StreamedResponse / BinaryFileResponse
        // (no withHeaders method). Used by export endpoints.
        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
            'Pragma'        => 'no-cache',
            'Expires'       => 'Sat, 01 Jan 2000 00:00:00 GMT',
        ];
        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }
        return $response;
    }
}
