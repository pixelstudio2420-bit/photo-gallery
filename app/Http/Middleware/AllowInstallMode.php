<?php

namespace App\Http\Middleware;

use App\Services\Deployment\InstallMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Middleware for the deployment / setup admin section.
 *
 * Behaviour:
 *   • If install mode is active (no admin user, DB down, etc.) AND the
 *     request looks trustworthy (localhost, private IP, or non-prod env,
 *     OR already-authenticated admin) → ALLOW without admin login.
 *   • If install mode is active but request is NOT trusted (production,
 *     remote IP, no admin auth) → 403 to avoid drive-by setup hijack.
 *   • If install mode is NOT active → enforce admin login as usual.
 *
 * This is the chicken-and-egg solver: an empty/fresh-upload site has no
 * admin user, so the operator can't log in to configure DB credentials.
 * We open just the deployment endpoints just long enough to bootstrap.
 *
 * Self-locking: once an admin user exists, install mode auto-ends and
 * this middleware reverts to enforcing admin auth — no manual flag to
 * forget about.
 */
class AllowInstallMode
{
    public function handle(Request $request, Closure $next)
    {
        // Path A: already logged in as admin → straight through (lets fully
        // installed sites use this page normally for ongoing tweaks).
        if (auth('admin')->check()) {
            return $next($request);
        }

        // Path B: install mode active.
        if (InstallMode::isActive()) {
            // Block production drive-by attacks.
            if (!InstallMode::isTrustedRequest($request)) {
                Log::warning('install_mode.blocked_untrusted', [
                    'ip'   => $request->ip(),
                    'path' => $request->path(),
                    'ua'   => $request->userAgent(),
                ]);
                abort(403, 'Install mode is restricted to local/private network or admin auth in production.');
            }
            // Trusted + install-needed → let the request through.
            return $next($request);
        }

        // Path C: fully installed but not logged in → bounce to admin login.
        return redirect()->route('admin.login');
    }
}
