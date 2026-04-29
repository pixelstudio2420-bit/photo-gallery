<?php

namespace App\Http\Middleware;

use App\Models\Marketing\ReferralCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures `?ref=CODE` from any GET request and stores it in:
 *   - session('referral_code')          → drives cart-page UI + checkout
 *   - cookie('referral_code', 30 days)  → survives session expiry / re-login
 *
 * The cookie window is intentionally generous (30 days) because referral
 * conversion can take a while: someone clicks a friend's share link today,
 * comes back next week to actually buy.
 *
 * Validation is lazy — we just persist whatever code arrived. The cart and
 * order flow re-validate via ReferralService::apply() before applying any
 * discount, so a bogus ?ref= won't blow anything up.
 */
class CaptureReferral
{
    /** Cookie + session lifetime in days for a captured referral code. */
    private const COOKIE_DAYS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        // Only capture on idempotent requests; never run on POST/PUT/DELETE.
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $next($request);
        }

        $raw = $request->query('ref');
        if (!is_string($raw) || $raw === '') {
            return $next($request);
        }

        // Normalise: codes are always uppercase; strip whitespace.
        $code = strtoupper(trim($raw));

        // Cap length so a hostile querystring can't blow our cookie size.
        if ($code === '' || strlen($code) > 64) {
            return $next($request);
        }

        // Soft validate — does this code exist + is it usable? We still
        // capture invalid codes (they may be valid by checkout time, e.g.
        // a campaign that activates later), but we mark them so analytics
        // can tell legitimate clicks from typos.
        $exists = false;
        try {
            $exists = ReferralCode::where('code', $code)->exists();
        } catch (\Throwable $e) {
            // DB or table missing during early install → just skip silently.
        }

        // Don't overwrite an existing code unless the new one is valid;
        // otherwise a casual visit to ?ref=somewhere wipes out the code
        // a friend actually shared.
        $current = $request->session()->get('referral_code');
        if ($current && !$exists) {
            return $next($request);
        }

        $request->session()->put('referral_code', $code);
        $request->session()->put('referral_captured_at', now()->toIso8601String());

        $response = $next($request);

        // Set a long-lived cookie so the code survives session resets.
        try {
            $response->headers->setCookie(
                cookie('referral_code', $code, self::COOKIE_DAYS * 24 * 60)
            );
        } catch (\Throwable $e) {
            // If response is a streamed/binary response without cookie support, skip.
        }

        return $response;
    }
}
