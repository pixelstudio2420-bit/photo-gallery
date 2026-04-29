<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the photographer-facing subscription surface.
 *
 * When `AppSetting.subscriptions_enabled` is '0':
 *   • Photographer subscription screens (/photographer/subscription/*) →
 *     redirect to the photographer dashboard with a warning flash.
 *   • Subscribe / change / cancel / resume actions → bounced with the same
 *     warning so a stale tab can't transact while the system is "off".
 *   • Admin guard short-circuits — admins still browse to flip the toggle
 *     back on, audit subscriptions, and run support actions.
 *   • JSON / XHR requests get a 503 so SPA panels know to hide their UI
 *     instead of rendering a half-broken page from a redirect HTML.
 *
 * Mirror of CheckCreditsEnabled / CheckUserStorageEnabled — kept as its own
 * class so each subsystem's bypass logic can evolve independently (e.g. if
 * we later want to allow read-only access to invoices for billing reasons).
 *
 * Note: The existing controller-level guard in SubscriptionController::
 * subscribe() stays — defense in depth in case this middleware is ever
 * removed from a route by accident.
 */
class CheckSubscriptionsEnabled
{
    public function __construct(private SubscriptionService $subs) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->subs->systemEnabled()) {
            return $next($request);
        }

        // Admins can still navigate to the toggle page to flip it back on.
        if (auth('admin')->check()) {
            return $next($request);
        }

        $message = 'ระบบสมัครสมาชิกปิดใช้งานชั่วคราว';

        if ($request->expectsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 503);
        }

        return redirect()->route('photographer.dashboard')->with('warning', $message);
    }
}
