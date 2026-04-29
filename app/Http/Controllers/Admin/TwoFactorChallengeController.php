<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * TwoFactorChallengeController
 *
 * Presents the TOTP / backup-code prompt after a successful password login
 * for admins who have 2FA enabled. Sets the `admin_2fa_passed` session flag
 * on success so the `admin.2fa` middleware lets subsequent requests through.
 *
 * Routes:
 *   GET  /admin/2fa/challenge   admin.2fa.challenge
 *   POST /admin/2fa/challenge   admin.2fa.challenge.verify
 *   POST /admin/2fa/cancel      admin.2fa.cancel   (logout mid-challenge)
 */
class TwoFactorChallengeController extends Controller
{
    public function show(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return redirect()->route('admin.login');
        }

        $twoFa = app(TwoFactorAuthService::class);

        // If the admin actually has no 2FA set up, bounce them to dashboard
        // (middleware shouldn't have routed them here, but be defensive).
        if (!$twoFa->isEnabled($admin->id)) {
            $request->session()->put('admin_2fa_passed', true);
            return redirect()->route('admin.dashboard');
        }

        // Already passed this session — skip.
        if ($request->session()->get('admin_2fa_passed') === true) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return view('admin.auth.2fa-challenge', [
            'admin' => $admin,
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:16',
        ]);

        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return redirect()->route('admin.login');
        }

        $twoFa = app(TwoFactorAuthService::class);
        $code  = trim($request->input('code'));

        // Strip spaces for TOTP codes like "123 456"
        $totpCode = preg_replace('/\s+/', '', $code);

        $passed = false;
        $method = null;

        // Try TOTP first (6-digit)
        if (preg_match('/^\d{6}$/', $totpCode)) {
            $secret = $twoFa->getSecret($admin->id);
            if ($secret && $twoFa->verifyCode($secret, $totpCode)) {
                $passed = true;
                $method = 'totp';
            }
        }

        // Fall back to backup code (8 hex chars)
        if (!$passed && preg_match('/^[A-F0-9]{8}$/i', $code)) {
            if ($twoFa->verifyBackupCode($admin->id, $code)) {
                $passed = true;
                $method = 'backup_code';
            }
        }

        if (!$passed) {
            ActivityLogger::admin(
                action: 'admin.2fa_challenge_failed',
                target: $admin,
                description: "ป้อนรหัส 2FA ไม่ถูกต้อง ({$admin->email})",
                oldValues: null,
                newValues: ['email' => $admin->email],
            );
            Log::warning('auth.admin.2fa_failed', [
                'admin_id' => $admin->id,
                'email'    => $admin->email,
                'ip'       => $request->ip(),
                'code_len' => strlen($code),
            ]);

            return back()->withErrors([
                'code' => 'รหัสไม่ถูกต้อง กรุณาลองอีกครั้ง',
            ]);
        }

        // Mark session as 2FA-verified
        $request->session()->put('admin_2fa_passed', true);
        $request->session()->regenerate();

        ActivityLogger::admin(
            action: 'admin.2fa_challenge_passed',
            target: $admin,
            description: "ผ่านการยืนยัน 2FA ({$admin->email}) ด้วย {$method}",
            oldValues: null,
            newValues: ['method' => $method],
        );
        Log::info('auth.admin.2fa_passed', [
            'admin_id' => $admin->id,
            'email'    => $admin->email,
            'method'   => $method,
            'ip'       => $request->ip(),
        ]);

        // Bounce back to where they tried to go, or dashboard.
        $intended = $request->session()->pull('admin_2fa_intended');
        if ($intended && str_starts_with($intended, url('/admin'))) {
            return redirect($intended)->with('success', 'ยืนยัน 2FA สำเร็จ');
        }

        return redirect()->route('admin.dashboard')
            ->with('success', 'ยืนยัน 2FA สำเร็จ — ยินดีต้อนรับ');
    }

    /**
     * Abandon the challenge — log the admin out entirely.
     */
    public function cancel(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if ($admin) {
            ActivityLogger::admin(
                action: 'admin.2fa_challenge_cancelled',
                target: $admin,
                description: "ยกเลิกการยืนยัน 2FA และออกจากระบบ ({$admin->email})",
                oldValues: null,
                newValues: null,
            );
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('info', 'คุณได้ออกจากระบบ — กรุณาเข้าสู่ระบบใหม่');
    }
}
