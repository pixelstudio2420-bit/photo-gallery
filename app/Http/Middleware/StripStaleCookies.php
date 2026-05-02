<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strip "stale" cookies from outgoing responses.
 *
 * Background — production responses were leaking a third Set-Cookie header
 * with a randomly-generated 40-char name (alphanumeric) on every single
 * request:
 *
 *     Set-Cookie: XSRF-TOKEN=... (expected)
 *     Set-Cookie: loadroop-session=... (expected)
 *     Set-Cookie: 4PiVudqSGe2wvnrP9rDpZeaUfORf3ZtA85BCx5M7=...  ← stale
 *     Set-Cookie: __cf_bm=... (Cloudflare, fine)
 *
 * Each request emitted a different random name, so the browser kept
 * accumulating them. After ~10–20 page loads the Cookie header grew past
 * 8KB and Cloudflare started returning ERR_CONNECTION_CLOSED before the
 * request ever reached origin. We saw this on /api/notifications/unread-count
 * which polls every 30 seconds — the polling alone was filling cookie
 * storage faster than we could investigate.
 *
 * Despite hours of grep, we never found the source — likely a runtime
 * package (Laravel Cloud edge layer, Sentry, or similar) that queues a
 * cookie with `Str::random(40)` as its name. Rather than wait for the
 * upstream fix, we surgically strip any Set-Cookie whose name matches
 * the random-40-alphanumeric pattern AND whose value looks like a
 * Laravel-encrypted blob (starts with `eyJ`). That's specific enough
 * to avoid false positives against legitimate cookie names — no real
 * cookie name is exactly 40 random chars except those Laravel-encrypted
 * runtime droppings.
 *
 * KEEP intentionally:
 *   - XSRF-TOKEN              → CSRF protection
 *   - loadroop-session        → Laravel session
 *   - locale                  → SetLocale middleware
 *   - referral_code           → CaptureReferral middleware
 *   - utm_*                   → CaptureUtm middleware
 *   - __cf_bm                 → Cloudflare Bot Management
 *   - _ga / _gid / _fbp etc.  → Analytics (set by JS, not server)
 */
class StripStaleCookies
{
    /**
     * Pattern: 40 char alphanumeric (Laravel's Str::random(40) output).
     * Anchored with ^...$ so we don't accidentally match longer names
     * that happen to contain a 40-char run.
     */
    private const STALE_NAME = '/^[A-Za-z0-9]{40}$/';

    /**
     * Encrypted-cookie envelope prefix — Laravel's base64-encoded
     * {"iv":"...","value":"...","mac":"..."} starts with "eyJ" because
     * '{"' base64-encodes to 'eyI' (and the "i" in `"iv"` follows).
     * Real value: starts with "eyJ" then alphanumeric.
     */
    private const ENCRYPTED_PREFIX = 'eyJ';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $allCookies = $headers->getCookies();
        if (empty($allCookies)) {
            return $response;
        }

        // Re-emit the cookie collection without the stale ones. We have
        // to clearCookies() then re-set what we keep, because Symfony's
        // header bag has no remove-by-predicate API.
        $kept = [];
        $stripped = 0;
        foreach ($allCookies as $cookie) {
            if ($this->isStale($cookie)) {
                $stripped++;
                continue;
            }
            $kept[] = $cookie;
        }

        if ($stripped > 0) {
            // Symfony's HeaderBag clearCookie() requires name+path+domain
            // to match — easier to wipe all and re-add the keepers. The
            // headers->removeCookie() / setCookie() pair handles that.
            foreach ($allCookies as $cookie) {
                $headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
            foreach ($kept as $cookie) {
                $headers->setCookie($cookie);
            }
        }

        return $response;
    }

    private function isStale(Cookie $cookie): bool
    {
        $name = $cookie->getName();

        // Allow-list common legitimate cookies first — short-circuit before
        // pattern matching for clarity + perf.
        static $allow = [
            'XSRF-TOKEN', 'locale', 'referral_code',
            '__cf_bm', '__Host-X', '_ga', '_gid', '_fbp', '_fbc',
        ];
        if (in_array($name, $allow, true)) {
            return false;
        }

        // Configured session cookie name (loadroop-session in prod,
        // laravel-session in dev) — never strip.
        if ($name === config('session.cookie')) {
            return false;
        }

        // utm_* cookies are dynamic but predictable.
        if (str_starts_with($name, 'utm_') || str_starts_with($name, 'remember_')) {
            return false;
        }

        // Final test — random 40-char name + encrypted-cookie value
        // shape = the exact stale-cookie pattern we want to drop.
        if (!preg_match(self::STALE_NAME, $name)) {
            return false;  // not random-shaped → keep
        }

        $value = (string) $cookie->getValue();
        return str_starts_with($value, self::ENCRYPTED_PREFIX);
    }
}
