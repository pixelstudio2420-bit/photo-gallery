<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminRole
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes:
     *   middleware('admin.role:events')           → requires "events" permission
     *   middleware('admin.role:finance,orders')    → requires ANY of these permissions
     *   middleware('admin.role:superadmin')        → requires superadmin role
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return $this->deny($request, 'กรุณาเข้าสู่ระบบ Admin ก่อน');
        }

        // Check if account is active
        if (!$admin->is_active) {
            Auth::guard('admin')->logout();
            return redirect()->route('admin.login')
                ->with('error', 'บัญชีของคุณถูกระงับ กรุณาติดต่อผู้ดูแลระบบ');
        }

        // Superadmin always passes
        if ($admin->isSuperAdmin()) {
            return $next($request);
        }

        // Check for explicit superadmin requirement
        if (in_array('superadmin', $permissions, true)) {
            return $this->deny($request, 'เฉพาะ Super Admin เท่านั้นที่สามารถเข้าถึงได้');
        }

        // Check if admin has any of the required permissions
        if (!empty($permissions) && !$admin->hasAnyPermission($permissions)) {
            return $this->deny($request, 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        }

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        return redirect()->route('admin.dashboard')
            ->with('error', $message);
    }
}
