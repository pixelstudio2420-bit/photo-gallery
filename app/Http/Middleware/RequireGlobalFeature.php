<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a non-subscription route on a global feature flag.
 *
 * Usage (in routes):
 *   Route::post('/api/chatbot', …)->middleware('feature.global:chatbot');
 *
 * Difference from `subscription.feature`
 * --------------------------------------
 * `subscription.feature` requires a logged-in photographer with a plan
 * that grants the feature — appropriate for AI tools.
 *
 * This middleware applies to features that are platform-wide (chatbot,
 * team UI, public API surface) where the user identity is irrelevant —
 * the question is just "is this feature turned on for the install?".
 *
 * Response shape
 * --------------
 * Returns 404. A disabled feature is functionally indistinguishable
 * from a non-existent endpoint, and 404 doesn't leak the existence of
 * a hidden feature to scrapers/probes the way 403 would.
 */
class RequireGlobalFeature
{
    public function __construct(private SubscriptionService $subs) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (!$this->subs->featureGloballyEnabled($feature)) {
            abort(404);
        }
        return $next($request);
    }
}
