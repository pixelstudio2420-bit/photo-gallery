<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\LoginRouter;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Admin;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin()
    {
        // Smart redirect if already authenticated
        $redirect = LoginRouter::redirectIfAuthenticated();
        if ($redirect) {
            return redirect($redirect);
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // ─── Check Admin account first ───
        $admin = Admin::where('email', $request->email)->first();

        if ($admin && Hash::check($request->password, $admin->password_hash)) {
            if (!$admin->is_active) {
                ActivityLogger::admin(
                    action: 'admin.login_blocked_suspended',
                    target: $admin,
                    description: "พยายามเข้าสู่ระบบแต่บัญชีถูกระงับ ({$admin->email})",
                    oldValues: null,
                    newValues: ['email' => $admin->email],
                );

                return back()->withErrors([
                    'email' => 'บัญชีนี้ถูกระงับ กรุณาติดต่อ Super Admin',
                ])->onlyInput('email');
            }

            Auth::guard('admin')->login($admin);
            $request->session()->regenerate();
            $admin->update(['last_login_at' => now()]);

            // ─── 2FA enforcement ───
            // If the admin has 2FA enabled, we leave them authenticated BUT mark
            // the session as "2FA pending". The `admin.2fa` middleware will
            // redirect every admin request to the challenge view until they pass.
            $twoFa    = app(TwoFactorAuthService::class);
            $has2Fa   = $twoFa->isEnabled($admin->id);

            if ($has2Fa) {
                $request->session()->put('admin_2fa_passed', false);

                ActivityLogger::admin(
                    action: 'admin.login_password_ok_2fa_pending',
                    target: $admin,
                    description: "เข้าสู่ระบบผ่านรหัสผ่านแล้ว รอยืนยัน 2FA ({$admin->email})",
                    oldValues: null,
                    newValues: ['email' => $admin->email, 'role' => $admin->role],
                );

                return redirect()->route('admin.2fa.challenge')
                    ->with('info', 'กรุณายืนยันตัวตนด้วยรหัส 2FA เพื่อเข้าใช้งาน');
            }

            // No 2FA configured — proceed directly.
            $request->session()->put('admin_2fa_passed', true);

            ActivityLogger::admin(
                action: 'admin.login_success',
                target: $admin,
                description: "เข้าสู่ระบบสำเร็จ ({$admin->email})",
                oldValues: null,
                newValues: ['email' => $admin->email, 'role' => $admin->role, '2fa_enabled' => false],
            );
            Log::info('auth.admin.login_success', [
                'admin_id' => $admin->id,
                'email'    => $admin->email,
                'ip'       => $request->ip(),
                'ua'       => substr((string) $request->userAgent(), 0, 200),
            ]);

            return redirect()->intended(route('admin.dashboard'))
                ->with('success', 'เข้าสู่ระบบสำเร็จ — ยินดีต้อนรับ ' . ($admin->role_info['thai'] ?? 'Admin'));
        }

        // ─── Check User account (cross-login from admin page) ───
        $user = User::where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password_hash)) {
            if ($user->status === 'suspended') {
                return back()->withErrors(['email' => 'บัญชีถูกระงับ กรุณาติดต่อผู้ดูแลระบบ'])->onlyInput('email');
            }

            Auth::login($user);
            $request->session()->regenerate();

            $user->update([
                'last_login_at' => now(),
                'login_count'   => ($user->login_count ?? 0) + 1,
            ]);

            // Smart routing — photographer or customer
            $route = LoginRouter::resolveForUser($user);

            return redirect($route['url'])
                ->with('info', 'บัญชีนี้ไม่ใช่แอดมิน — ' . $route['message']);
        }

        // Track failed login attempts per IP and notify admins after the
        // 3rd failure within 10 minutes — flags bruteforce attempts.
        // Counter is in cache (no DB write per failed attempt) and
        // self-expires, so it doesn't bloat anything. Best-effort —
        // never blocks the login response.
        try {
            $ip      = $request->ip() ?: '0.0.0.0';
            $cacheKey = 'login_fail:admin:' . $ip;
            $count    = (int) \Illuminate\Support\Facades\Cache::increment($cacheKey);
            if ($count === 1) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, 1, now()->addMinutes(10));
            }
            if ($count >= 3) {
                \App\Models\AdminNotification::loginAbuse($ip, $count, $request->input('email'));
            }
            Log::warning('auth.admin.login_failed', [
                'email'      => (string) $request->input('email'),
                'ip'         => $ip,
                'fail_count' => $count,
                'ua'         => substr((string) $request->userAgent(), 0, 200),
            ]);
        } catch (\Throwable $e) {}

        return back()->withErrors([
            'email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if ($admin) {
            ActivityLogger::admin(
                action: 'admin.logout',
                target: $admin,
                description: "ออกจากระบบ ({$admin->email})",
                oldValues: null,
                newValues: null,
            );
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($admin) {
            Log::info('auth.admin.logout', [
                'admin_id' => $admin->id,
                'email'    => $admin->email,
                'ip'       => $request->ip(),
            ]);
        }

        return redirect()->route('admin.login')
            ->with('logout_success', 'ออกจากระบบสำเร็จ — เซสชันถูกยกเลิกแล้ว');
    }
}
