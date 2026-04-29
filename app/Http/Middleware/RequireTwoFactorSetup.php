<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use App\Services\TwoFactorAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequireTwoFactorSetup
 *
 * Forces every admin to enrol in TOTP 2FA before they can touch the admin
 * panel. Complements `RequireTwoFactor` (alias: admin.2fa), which only
 * challenges admins who already have 2FA enabled.
 *
 *   admin.2fa        → "you have 2FA enabled; prove it this session"
 *   admin.2fa.setup  → "you have no 2FA; set one up before doing anything"
 *
 * Order in the route group:
 *   ['admin', 'admin.2fa.setup', 'admin.2fa', 'no.back']
 *
 * Setup-related routes (QR code page, enable POST, verify POST) opt out
 * with `->withoutMiddleware('admin.2fa.setup')` so the admin can actually
 * reach the setup form.
 *
 * Enforcement resolution order
 * ----------------------------
 * 1. If the env var `ENFORCE_ADMIN_2FA` is explicitly set (true/false), it
 *    wins — lets ops lock the toggle in production regardless of DB state.
 * 2. Otherwise the `enforce_admin_2fa` AppSetting is honoured. Admins flip
 *    this from the 2FA settings page. Default is "0" (OFF) so a fresh
 *    install doesn't force enrolment before an admin has even logged in
 *    to configure the system.
 *
 * When enforcement is OFF, individual admins can still opt in to 2FA — the
 * per-account `RequireTwoFactor` challenge remains active for anyone who
 * has enabled it. OFF only disables the "you MUST enrol" push.
 */
class RequireTwoFactorSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        // AdminAuth should have blocked anons already; bail defensively.
        if (!$admin) {
            return redirect()->route('admin.login');
        }

        if (!self::enforcementActive()) {
            return $next($request);
        }

        $twoFa = app(TwoFactorAuthService::class);
        if ($twoFa->isEnabled($admin->id)) {
            return $next($request);
        }

        // JSON callers get 403 + a redirect hint so the SPA can route the user.
        if ($request->expectsJson()) {
            return response()->json([
                'error'    => 'Two-factor authentication setup required',
                'redirect' => route('admin.settings.2fa'),
            ], 403);
        }

        return redirect()->route('admin.settings.2fa')
            ->with('warning', 'บัญชีแอดมินทุกคนต้องเปิดใช้งาน 2FA ก่อนเข้าใช้งานระบบ');
    }

    /**
     * Single source of truth for "is admin 2FA enrolment enforced right now?".
     *
     * Called from the middleware itself, the disable-2FA action, and the
     * settings view so all three agree on whether the toggle is effective.
     * Keeping this in one place avoids the trap of flipping the AppSetting
     * and having one of the three sites silently miss the update.
     */
    public static function enforcementActive(): bool
    {
        // Env override — explicit string ("true"/"false"/"1"/"0"). When
        // unset we fall back to the AppSetting so the admin UI toggle
        // actually has an effect on default installs.
        $envOverride = env('ENFORCE_ADMIN_2FA');
        if ($envOverride !== null && $envOverride !== '') {
            return filter_var($envOverride, FILTER_VALIDATE_BOOLEAN);
        }

        try {
            return AppSetting::get('enforce_admin_2fa', '0') === '1';
        } catch (\Throwable $e) {
            // DB not ready (e.g. during migrate:fresh) — default to OFF so
            // a broken settings table can't lock every admin out.
            return false;
        }
    }

    /**
     * Whether the env var is pinning the toggle (UI should show as locked).
     */
    public static function enforcementLockedByEnv(): bool
    {
        $envOverride = env('ENFORCE_ADMIN_2FA');
        return $envOverride !== null && $envOverride !== '';
    }
}
