<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Key authentication middleware.
 * Accepts keys via `X-API-Key` header or `?api_key=` query string.
 */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $key = $request->header('X-API-Key') ?? $request->query('api_key');

        if (!$key) {
            return response()->json([
                'success' => false,
                'error'   => 'API key required',
                'code'    => 'API_KEY_REQUIRED',
            ], 401);
        }

        $apiKey = ApiKey::findByKey($key);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid or expired API key',
                'code'    => 'API_KEY_INVALID',
            ], 401);
        }

        // Check IP whitelist
        if (!$apiKey->isIpAllowed($request->ip())) {
            return response()->json([
                'success' => false,
                'error'   => 'IP not allowed for this API key',
                'code'    => 'IP_NOT_ALLOWED',
            ], 403);
        }

        // Check scope
        if ($scope && !$apiKey->hasScope($scope)) {
            return response()->json([
                'success' => false,
                'error'   => "API key does not have required scope: {$scope}",
                'code'    => 'INSUFFICIENT_SCOPE',
            ], 403);
        }

        // Record usage (async-safe: non-blocking)
        try {
            $apiKey->recordUsage($request->ip());
        } catch (\Throwable $e) {
            // Never let usage tracking block the request
        }

        // Attach to request for controllers to access
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
