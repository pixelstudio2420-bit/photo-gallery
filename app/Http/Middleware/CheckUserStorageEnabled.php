<?php

namespace App\Http\Middleware;

use App\Services\UserStorageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the entire consumer cloud-storage module.
 *
 * When `user_storage_enabled` is off in AppSetting:
 *   • Non-admin users get redirected to home with a "system disabled" flash
 *   • JSON requests get a 503 with a helpful message
 *   • Admins pass through so they can still inspect the admin dashboard
 *
 * Separate from sales_mode_storage_enabled — that one only gates NEW paid
 * sign-ups. This gate is the nuclear kill-switch (maintenance, incidents).
 */
class CheckUserStorageEnabled
{
    public function __construct(private UserStorageService $storage) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->storage->systemEnabled()) {
            return $next($request);
        }

        // Admins (logged into the admin guard) always pass — they need to
        // reach the admin pages to flip the toggle back on.
        if (auth('admin')->check()) {
            return $next($request);
        }

        $message = 'ระบบ Cloud Storage ปิดปรับปรุงชั่วคราว — โปรดลองอีกครั้งในภายหลัง';

        if ($request->expectsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 503);
        }

        return redirect()->route('home')->with('warning', $message);
    }
}
