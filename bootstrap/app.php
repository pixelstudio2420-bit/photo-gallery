<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(prepend: [
            // Outermost — measures full request lifecycle including
            // every other middleware. Wraps in try/catch internally,
            // never throws, never blocks.
            \App\Http\Middleware\TrackRequestMetrics::class,
            \App\Http\Middleware\ForceHttps::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CheckFirewall::class,
            \App\Http\Middleware\SourceProtection::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\TrackUserPresence::class,
            \App\Http\Middleware\CaptureUtm::class,
            \App\Http\Middleware\CaptureReferral::class,
            // Path-aware noindex for admin/photographer/api/profile/etc.
            // Self-applying — checks request path, no per-route wiring.
            \App\Http\Middleware\AdminNoindex::class,
            // Strip random-named cookies that some platform layer leaks
            // into responses — see class docblock for the full saga.
            // MUST run last so it sees the final cookie list after every
            // upstream middleware has had a chance to add its own.
            \App\Http\Middleware\StripStaleCookies::class,
        ]);

        // ─── CSRF exclusions ────────────────────────────────────────
        // Endpoints that JS triggers WITHOUT a fresh CSRF token in hand.
        // Each is independently safe because:
        //   payment/check-expiry/*  — auth-gated + idempotent state
        //                             check (cancel-if-expired). Token
        //                             is baked into the page at render
        //                             time, so a long-running countdown
        //                             can outlive the session and ship
        //                             a stale token → 419 spam in
        //                             console. Exempting is safe — the
        //                             route still requires session auth
        //                             and the order ID is in the URL.
        $middleware->validateCsrfTokens(except: [
            'payment/check-expiry/*',
        ]);

        $middleware->alias([
            'admin'           => \App\Http\Middleware\AdminAuth::class,
            'admin.2fa'       => \App\Http\Middleware\RequireTwoFactor::class,
            'admin.2fa.setup' => \App\Http\Middleware\RequireTwoFactorSetup::class,
            'admin.role'      => \App\Http\Middleware\AdminRole::class,
            // Special middleware for /admin/deployment — allows access without
            // admin login when the system is in install mode (no admin user
            // exists yet). Auto-secures once installation is complete.
            'admin.or.install' => \App\Http\Middleware\AllowInstallMode::class,
            'photographer'         => \App\Http\Middleware\PhotographerAuth::class,
            'photographer.tier'    => \App\Http\Middleware\RequirePhotographerTier::class,
            'photographer.quota'   => \App\Http\Middleware\EnforceStorageQuota::class,
            // Photographer must link Google before accessing dashboard.
            // Replaces email verification — Google validates ownership for us.
            'photographer.google'  => \App\Http\Middleware\RequireGoogleLinked::class,
            'subscription.feature' => \App\Http\Middleware\RequireSubscriptionFeature::class,
            // Gate a route on a global feature flag (auth-independent).
            // Use for non-subscription features like chatbot / team UI.
            // Returns 404 when off (treats disabled features as "doesn't exist").
            'feature.global'       => \App\Http\Middleware\RequireGlobalFeature::class,
            // De-index admin/dashboard/api responses. Sets X-Robots-Tag
            // header AND suppresses auto-SEO generation on those routes.
            // Apply with `->middleware('admin.noindex')` on protected groups.
            'admin.noindex'        => \App\Http\Middleware\AdminNoindex::class,
            // Unified usage / cost gate — checks plan caps + circuit breakers.
            // Usage: ->middleware('usage.quota:ai.face_search')
            //        ->middleware('usage.quota:photo.upload,1')
            'usage.quota'          => \App\Http\Middleware\EnforceUsageQuota::class,
            'user.storage'         => \App\Http\Middleware\CheckUserStorageEnabled::class,
            'credits.enabled'      => \App\Http\Middleware\CheckCreditsEnabled::class,
            'subscriptions.enabled' => \App\Http\Middleware\CheckSubscriptionsEnabled::class,
            'blog.enabled' => \App\Http\Middleware\CheckBlogEnabled::class,
            'rate.limit'   => \App\Http\Middleware\RateLimit::class,
            'no.back'      => \App\Http\Middleware\PreventBackHistory::class,
            'api.key'      => \App\Http\Middleware\ApiKeyAuth::class,
            'photographer.api' => \App\Http\Middleware\AuthenticatePhotographerApi::class,
            'edge.cache'   => \App\Http\Middleware\EdgeCache::class,
            // Cloudflare Turnstile — no-op until enabled in Admin → Settings
            'turnstile'    => \App\Http\Middleware\VerifyTurnstile::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ─── Sentry error tracking ───
        // No-op when SENTRY_LARAVEL_DSN is unset (package ships without a DSN
        // by default, so installs on dev machines stay silent).
        if (class_exists(\Sentry\Laravel\Integration::class)) {
            \Sentry\Laravel\Integration::handles($exceptions);
        }

        // ─── 419 Page Expired / CSRF token mismatch ───
        // แทนที่จะแสดง default "Page Expired" ที่น่ากลัว
        // ให้ redirect กลับไปหน้าเดิมพร้อม old input และข้อความแจ้ง
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            // For JSON/AJAX, return structured response
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message'     => 'เซสชันหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง',
                    'error_code'  => 'TOKEN_MISMATCH',
                    'csrf_token'  => csrf_token(),
                ], 419);
            }

            // For web: go back to the form with a fresh token + preserve input
            return redirect()
                ->back()
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('warning', 'เซสชันหมดอายุ กรุณาส่งข้อมูลอีกครั้ง');
        });
    })->create();
