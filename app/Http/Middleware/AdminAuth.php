<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('admin')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return redirect()->route('admin.login')
                ->with('warning', 'กรุณาเข้าสู่ระบบ Admin ก่อน');
        }

        // Check if account is active
        $admin = Auth::guard('admin')->user();
        if (!$admin->is_active) {
            Auth::guard('admin')->logout();
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Account suspended'], 403);
            }
            return redirect()->route('admin.login')
                ->with('error', 'บัญชีของคุณถูกระงับ กรุณาติดต่อ Super Admin');
        }

        // Share admin data with all views
        view()->share('currentAdmin', $admin);

        return $next($request);
    }
}
