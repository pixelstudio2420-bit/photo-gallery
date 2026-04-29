<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Marketing\ReferralCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Short-URL handler for referral links: /r/{code}
 *
 * Why a dedicated controller (vs just redirecting to /?ref=CODE)?
 *   - Half the bytes in the share URL — better for Twitter, SMS, voice
 *     ("slash R slash A B C D 1 2 3 4" beats reading a query string).
 *   - Future-friendly: if we want to add click analytics, A/B landing
 *     pages, or per-code redirects, this is where they go.
 *   - We can soft-validate codes and bounce typos to home cleanly
 *     instead of relying on the lazy-validate cart logic.
 *
 * The capture logic mirrors {@see \App\Http\Middleware\CaptureReferral}
 * so the user ends up in exactly the same state as if they'd clicked
 * `?ref=CODE` directly. A 30-day cookie + session entry persist past
 * the redirect.
 */
class ReferralController extends Controller
{
    /** Cookie + session lifetime in days. Matches CaptureReferral. */
    private const COOKIE_DAYS = 30;

    /** Whitelist of "to" targets — guards against open-redirect abuse. */
    private const TARGETS = [
        'home'          => '/',
        'events'        => '/events',
        'photographers' => '/photographers',
        'register'      => '/register',
    ];

    public function redirect(string $code, Request $request)
    {
        $normalized = strtoupper(trim($code));

        // Trivial safety: cap length so a hostile URL can't blow our cookie.
        if ($normalized === '' || strlen($normalized) > 64) {
            return redirect('/');
        }

        // Soft validate against the codes table — invalid codes still get
        // the user home, but we don't pollute their session with junk.
        $exists = false;
        try {
            $exists = ReferralCode::where('code', $normalized)->exists();
        } catch (\Throwable $e) {
            Log::warning('referral.short_lookup_failed', [
                'code' => $normalized, 'err' => $e->getMessage(),
            ]);
        }

        // Resolve the destination from `?to=` (whitelisted) or fall back
        // to home. We never accept a free-form path here — preventing
        // /r/X?to=https://evil.com style open-redirect attacks.
        $toKey = $request->query('to', 'home');
        $target = self::TARGETS[$toKey] ?? '/';

        $response = redirect($target);

        if ($exists) {
            $request->session()->put('referral_code', $normalized);
            $request->session()->put('referral_captured_at', now()->toIso8601String());
            $response->headers->setCookie(
                cookie('referral_code', $normalized, self::COOKIE_DAYS * 24 * 60)
            );

            // Optional flash so the user knows the code is now active. The
            // home page can choose to surface this; if it doesn't, the
            // discount still applies silently at checkout.
            $response->with('referral_applied', $normalized);
        }

        return $response;
    }
}
