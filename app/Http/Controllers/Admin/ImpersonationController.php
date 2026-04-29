<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ImpersonationController
 *
 * Allows an authorised admin to log in as a specific user for support /
 * reproduction purposes. The admin's identity is preserved in the session
 * and restored when impersonation ends.
 *
 * Session shape while impersonating:
 *   impersonator.admin_id     (int)    — original admin user id
 *   impersonator.admin_email  (string) — for banner display
 *   impersonator.started_at   (string) — ISO timestamp
 *
 * Security:
 *   • Only admins with the `users` permission can start impersonation.
 *   • Superadmin cannot be impersonated targets — they are users table only,
 *     so this is not a concern in this project (Admin & User are separate tables).
 *   • Suspended users cannot be impersonated — forces admin to reactivate first.
 *   • Every start and stop event is audited with full metadata.
 *
 * Routes:
 *   POST /admin/users/{user}/impersonate   admin.users.impersonate
 *   POST /impersonate/stop                 impersonate.stop
 */
class ImpersonationController extends Controller
{
    /**
     * Start impersonating the given user.
     *
     * Middleware: admin + admin.2fa + admin.role (or permission gate in-method)
     */
    public function start(Request $request, User $user)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return redirect()->route('admin.login');
        }

        // Permission check — only admins with users.manage can impersonate.
        if (!$admin->hasPermission('users')) {
            abort(403, 'ไม่มีสิทธิ์ใช้งานการ Impersonate');
        }

        // Safety checks
        if ($user->status === 'suspended') {
            return back()->with('error', 'ไม่สามารถ Impersonate บัญชีที่ถูกระงับได้ — กรุณาเปิดใช้งานก่อน');
        }

        // Record the impersonation start (audit)
        ActivityLogger::admin(
            action: 'admin.impersonation_started',
            target: $user,
            description: "Impersonate ผู้ใช้ {$user->email} (ID: {$user->id})",
            oldValues: null,
            newValues: [
                'target_user_id'    => (int) $user->id,
                'target_user_email' => $user->email,
                'target_user_name'  => $user->full_name,
                'admin_id'          => (int) $admin->id,
                'admin_email'       => $admin->email,
            ],
        );

        // Log out of the admin guard, but keep session keys so we can restore.
        Auth::guard('admin')->logout();

        // Log in as the user on the default `web` guard.
        Auth::guard('web')->login($user);

        // Regenerate session ID — defense against session fixation while switching
        // the authenticated identity. `migrate(true)` preserves session data so
        // the impersonator.* keys we set below survive.
        $request->session()->migrate(true);

        // Store impersonator identity in the session.
        $request->session()->put('impersonator.admin_id', (int) $admin->id);
        $request->session()->put('impersonator.admin_email', $admin->email);
        $request->session()->put('impersonator.started_at', now()->toIso8601String());

        // Touch the user's last_login for consistency (but don't inflate login_count).
        $user->update(['last_login_at' => now()]);

        return redirect('/')
            ->with('info', "กำลัง Impersonate เป็น {$user->full_name} — กดปุ่ม 'หยุด Impersonate' ที่ banner เพื่อสิ้นสุด");
    }

    /**
     * Stop impersonating and restore the admin session.
     *
     * Available to any user guard session that has the impersonator flag set.
     * No middleware required beyond CSRF.
     */
    public function stop(Request $request)
    {
        $impersonatorAdminId = $request->session()->get('impersonator.admin_id');
        $impersonatorEmail   = $request->session()->get('impersonator.admin_email');
        $startedAt           = $request->session()->get('impersonator.started_at');

        if (!$impersonatorAdminId) {
            return redirect('/')->with('warning', 'ไม่พบเซสชัน Impersonate');
        }

        $admin = Admin::find($impersonatorAdminId);

        if (!$admin || !$admin->is_active) {
            // Something's wrong — just log out the user guard for safety.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('error', 'ไม่สามารถกู้คืนเซสชันแอดมินได้ กรุณาเข้าสู่ระบบใหม่');
        }

        // Capture the impersonated user for audit before we log out.
        $impersonatedUser = Auth::guard('web')->user();

        // Log out of user guard.
        Auth::guard('web')->logout();

        // Clear impersonator session keys.
        $request->session()->forget([
            'impersonator.admin_id',
            'impersonator.admin_email',
            'impersonator.started_at',
        ]);

        // Log back into admin guard.
        Auth::guard('admin')->login($admin);

        // Regenerate session ID when switching identity back to admin.
        $request->session()->migrate(true);

        // The restored admin session must still respect 2FA — mark passed=true
        // because the admin had already cleared 2FA to reach impersonation.
        $request->session()->put('admin_2fa_passed', true);

        // Audit the stop event.
        ActivityLogger::admin(
            action: 'admin.impersonation_stopped',
            target: $impersonatedUser,
            description: "สิ้นสุดการ Impersonate" . ($impersonatedUser ? " ({$impersonatedUser->email})" : ''),
            oldValues: [
                'target_user_id'    => $impersonatedUser ? (int) $impersonatedUser->id : null,
                'target_user_email' => $impersonatedUser ? $impersonatedUser->email    : null,
                'started_at'        => $startedAt,
            ],
            newValues: [
                'admin_id'    => (int) $admin->id,
                'admin_email' => $admin->email,
                'ended_at'    => now()->toIso8601String(),
            ],
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'สิ้นสุดการ Impersonate — กลับมาเป็น ' . $admin->full_name);
    }
}
