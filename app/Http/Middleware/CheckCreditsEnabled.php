<?php

namespace App\Http\Middleware;

use App\Services\CreditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the upload-credit feature surface.
 *
 * When `AppSetting.credits_system_enabled` is '0':
 *   • Photographer credit screens (/photographer/credits/*) → redirect to
 *     the photographer dashboard with a warning flash.
 *   • Admin credit screens stay accessible — the admin needs to be able
 *     to flip the toggle back on, and they authenticate on the `admin`
 *     guard which short-circuits this middleware.
 *   • JSON / XHR requests get a 503 so SPA panels know to hide.
 *
 * Mirror of CheckUserStorageEnabled — kept as its own class so the two
 * subsystems' admin bypass logic can evolve independently.
 */
class CheckCreditsEnabled
{
    public function __construct(private CreditService $credits) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->credits->systemEnabled()) {
            return $next($request);
        }

        // Admins can still navigate to the toggle page to flip it back on.
        if (auth('admin')->check()) {
            return $next($request);
        }

        $message = 'ระบบเครดิตอัปโหลดปิดใช้งานชั่วคราว';

        if ($request->expectsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 503);
        }

        return redirect()->route('photographer.dashboard')->with('warning', $message);
    }
}
