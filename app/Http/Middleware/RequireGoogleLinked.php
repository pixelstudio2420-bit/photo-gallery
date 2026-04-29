<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Photographer must have AT LEAST ONE social provider linked before accessing
 * the photographer dashboard. Either Google OR LINE counts as proof of identity
 * (replaces email verification). Many Thai users prefer LINE — forcing both was
 * unnecessary friction. Each provider has its own benefits:
 *
 *   Google:
 *     • Send invoices via Gmail
 *     • Backup photos to Google Drive (Pro plan)
 *     • Schedule shoots on Google Calendar
 *     • Verified email (Google validates ownership)
 *
 *   LINE:
 *     • Push notifications for bookings/orders (LINE Messaging API)
 *     • Native to Thai market (most photographers' primary contact)
 *     • Verified user identity (LINE provides verified user_id)
 *     • Send receipts/messages to customers via LINE OA
 *
 * Skipped if the admin disables the requirement via AppSetting
 * `photographer_require_google_link` = "0". (Setting key kept for backward
 * compat — historically only Google was checked. Now also gates LINE-only.)
 */
class RequireGoogleLinked
{
    public function handle(Request $request, Closure $next)
    {
        // Allow opt-out for admins who don't want this enforcement
        // (e.g. enterprise installs with their own auth proofing).
        if (AppSetting::get('photographer_require_google_link', '1') !== '1') {
            return $next($request);
        }

        $userId = Auth::id();
        if (!$userId) {
            return $next($request); // PhotographerAuth middleware will handle no-login
        }

        // Connection check: row exists in auth_social_logins for this user
        // with provider = 'google' OR 'line'. Either is enough to prove
        // identity — the photographer doesn't have to link both.
        $hasSocial = DB::table('auth_social_logins')
            ->where('user_id', $userId)
            ->whereIn('provider', ['google', 'line'])
            ->exists();

        if ($hasSocial) {
            return $next($request);
        }

        // Already on the connect-google page? Let it through to avoid loops.
        if ($request->routeIs('photographer.connect-google') ||
            $request->routeIs('photographer.auth.*') ||
            $request->routeIs('photographer.logout')) {
            return $next($request);
        }

        // For AJAX/JSON requests, return JSON 403 instead of redirect.
        if ($request->expectsJson()) {
            return response()->json([
                'message'   => 'Connect Google or LINE to access photographer features',
                'redirect'  => route('photographer.connect-google'),
            ], 403);
        }

        return redirect()->route('photographer.connect-google');
    }
}
