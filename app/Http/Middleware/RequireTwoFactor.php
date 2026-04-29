<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequireTwoFactor
 *
 * Enforces the 2FA challenge for admins who have TOTP enabled.
 *
 * Flow:
 *   1. Assumes `AdminAuth` has already run — `Auth::guard('admin')` is authenticated.
 *   2. If the admin has 2FA enabled AND session flag `admin_2fa_passed` is NOT true,
 *      redirect to the challenge view. JSON requests get 403.
 *   3. If 2FA is not enabled, or already passed, continue.
 *
 * Must be chained AFTER `admin`. Example:
 *   Route::middleware(['admin', 'admin.2fa', 'no.back'])->group(...)
 *
 * The challenge routes themselves must NOT use this middleware — otherwise the
 * user gets stuck in a redirect loop. Keep them inside the `admin` group but
 * outside the `admin.2fa` sub-group.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        // Safety net: if AdminAuth didn't run (misconfiguration), bounce to login.
        if (!$admin) {
            return redirect()->route('admin.login');
        }

        $twoFa = app(TwoFactorAuthService::class);

        // No 2FA setup — nothing to enforce.
        if (!$twoFa->isEnabled($admin->id)) {
            return $next($request);
        }

        // 2FA already satisfied this session — let through.
        if ($request->session()->get('admin_2fa_passed') === true) {
            return $next($request);
        }

        // Block and redirect (or JSON 403).
        if ($request->expectsJson()) {
            return response()->json([
                'error'    => 'Two-factor authentication required',
                'redirect' => route('admin.2fa.challenge'),
            ], 403);
        }

        // Preserve the originally requested URL so we can bounce back after challenge.
        $request->session()->put('admin_2fa_intended', $request->fullUrl());

        return redirect()->route('admin.2fa.challenge')
            ->with('warning', 'กรุณายืนยันตัวตนด้วย 2FA ก่อนเข้าใช้งาน');
    }
}
