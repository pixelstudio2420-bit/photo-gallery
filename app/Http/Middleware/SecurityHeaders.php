<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Production-grade security headers.
 *
 * Applied globally to every web response. Each header has a short comment
 * explaining what attack it mitigates so future maintainers can reason about
 * removing/relaxing them without reintroducing a vulnerability.
 *
 * HSTS is only emitted when `APP_ENV === production` AND the request is
 * HTTPS — applying it on local dev would pin the browser to https://127.0.0.1
 * and prevent subsequent HTTP testing.
 *
 * CSP uses `Content-Security-Policy-Report-Only` by default (set
 * CSP_ENFORCE=true in .env to switch to enforcement) so deployments don't
 * break inline scripts that haven't been audited yet. The policy is
 * deliberately permissive — tighten it per route once the inventory of
 * inline scripts/styles is known.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Streaming/binary responses (ZIP, file downloads) — don't rewrite
        // their headers, they're carefully constructed by the controller.
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            || $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return $response;
        }

        // Prevent MIME sniffing — stops browsers from re-interpreting
        // files based on content (critical for user-uploaded images).
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);

        // Block framing by other origins — blunt clickjacking defense.
        // Using SAMEORIGIN (not DENY) so we can still iframe our own
        // admin preview widgets.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);

        // Modern replacement for X-XSS-Protection: don't leak the
        // referring URL to third parties.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);

        // Restrict browser APIs that the app doesn't use. Narrower than
        // the deprecated Feature-Policy header.
        $response->headers->set(
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(self), usb=()',
            false
        );

        // X-XSS-Protection is formally deprecated but some legacy browsers
        // still honor it. Emitting "0" is better than omitting because a
        // few browsers default to a broken built-in filter.
        $response->headers->set('X-XSS-Protection', '0', false);

        // HSTS — only on HTTPS in production, otherwise a dev environment
        // would permanently pin users to https://127.0.0.1.
        if (config('app.env') === 'production' && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
                false
            );
        }

        // Content-Security-Policy — report-only by default; flip CSP_ENFORCE
        // after you've audited for false positives. The list of sources
        // covers the stack Photo Gallery ships with: Stripe JS, reCAPTCHA,
        // Google Analytics, Tailwind CDN (docs pages only), Cloudflare R2
        // thumbnail serving, and LINE LIFF.
        $enforceCsp = (bool) env('CSP_ENFORCE', false);

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com https://www.googletagmanager.com https://www.google-analytics.com https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net https://cdn.tailwindcss.com https://static.line-scdn.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "img-src 'self' data: blob: https: http:",
            "media-src 'self' https:",
            "connect-src 'self' https://api.stripe.com https://www.google-analytics.com https://api.line.me https://*.r2.cloudflarestorage.com https://*.amazonaws.com",
            "frame-src 'self' https://js.stripe.com https://www.google.com https://hooks.stripe.com",
            "frame-ancestors 'self'",
            "form-action 'self' https://checkout.stripe.com https://omise.co",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        // `upgrade-insecure-requests` is an enforcement-only directive —
        // browsers ignore it in report-only mode and emit a console warning.
        // Only add it when we're actually enforcing AND serving HTTPS
        // (on HTTP dev it would break mixed-content resources with no gain).
        if ($enforceCsp && $request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        $csp = implode('; ', $directives);

        $cspHeader = $enforceCsp ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
        $response->headers->set($cspHeader, $csp, false);

        // Remove server fingerprint headers if PHP added them.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
