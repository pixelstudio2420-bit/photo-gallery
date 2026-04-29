<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Smart Login Router
 * ─────────────────────────────────────────
 * Determines the best redirect URL after login
 * based on user roles (Admin, Photographer, Customer).
 */
class LoginRouter
{
    /**
     * Determine redirect URL for a logged-in User (web guard).
     *
     * Priority:
     *   1. Admin account match → admin dashboard
     *   2. Approved photographer → photographer dashboard
     *   3. Pending photographer  → home with info
     *   4. Customer              → session redirect or home
     */
    public static function resolveForUser(User $user, ?string $sessionRedirect = null): array
    {
        // ─── Check if this user's email also has an Admin account ───
        $admin = Admin::where('email', $user->email)->where('is_active', true)->first();
        if ($admin) {
            // Login admin guard too for seamless cross-login
            Auth::guard('admin')->login($admin);
            $admin->update(['last_login_at' => now()]);

            return [
                'url'     => route('admin.dashboard'),
                'message' => 'เข้าสู่ระบบสำเร็จ — เปลี่ยนไปยังแดชบอร์ดแอดมิน',
                'target'  => 'admin',
            ];
        }

        // ─── Check photographer profile ───
        $profile = $user->photographerProfile;

        if ($profile && $profile->status === 'approved') {
            return [
                'url'     => route('photographer.dashboard'),
                'message' => 'เข้าสู่ระบบสำเร็จ — ยินดีต้อนรับกลับ ช่างภาพ!',
                'target'  => 'photographer',
            ];
        }

        if ($profile && $profile->status === 'pending') {
            return [
                'url'     => route('home'),
                'message' => 'เข้าสู่ระบบสำเร็จ — บัญชีช่างภาพกำลังรอการอนุมัติ',
                'target'  => 'customer_pending',
            ];
        }

        // ─── Regular customer ───
        return [
            'url'     => $sessionRedirect ?: route('home'),
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'target'  => 'customer',
        ];
    }

    /**
     * Determine where an already-logged-in user should go.
     * Used by showLogin() methods to redirect away from login pages.
     */
    public static function redirectIfAuthenticated(?string $preferredGuard = null): ?string
    {
        // Admin guard takes priority
        if (Auth::guard('admin')->check()) {
            return route('admin.dashboard');
        }

        // Web guard (customer / photographer)
        if (Auth::check()) {
            $user = Auth::user();
            $profile = $user->photographerProfile;

            // If visiting photographer login and is approved photographer → photographer dashboard
            if ($preferredGuard === 'photographer' && $profile && $profile->status === 'approved') {
                return route('photographer.dashboard');
            }

            // If visiting customer login and is approved photographer → photographer dashboard
            if ($profile && $profile->status === 'approved') {
                return route('photographer.dashboard');
            }

            return route('home');
        }

        return null; // Not authenticated
    }
}
