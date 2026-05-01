<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\AuthController;
use App\Http\Controllers\Public\CartController;
use App\Http\Controllers\Public\OrderController;
use App\Http\Controllers\Public\PaymentController;
use App\Http\Controllers\Public\ProfileController;
use App\Http\Controllers\Public\ReviewController;
use App\Http\Controllers\Public\PhotographerController;
use App\Http\Controllers\Public\WishlistController;
use App\Http\Controllers\Public\ChatController;
use App\Http\Controllers\Public\ChatbotController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\DownloadController;
use App\Http\Controllers\Public\ProductController;
use App\Http\Controllers\Public\HelpController;
use App\Http\Controllers\Public\NotificationController;

/*
|--------------------------------------------------------------------------
| API Documentation (public)
|--------------------------------------------------------------------------
*/
Route::prefix('api/docs')->name('api.docs')->group(function () {
    Route::get('/', [\App\Http\Controllers\ApiDocsController::class, 'index']);
    Route::get('/redoc', [\App\Http\Controllers\ApiDocsController::class, 'redoc'])->name('.redoc');
    Route::get('/guide', [\App\Http\Controllers\ApiDocsController::class, 'guide'])->name('.guide');
    Route::get('/webhooks', [\App\Http\Controllers\ApiDocsController::class, 'webhooks'])->name('.webhooks');
    Route::get('/spec.json', [\App\Http\Controllers\ApiDocsController::class, 'spec'])->name('.spec');
});

/*
|--------------------------------------------------------------------------
| Language Switcher
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| CSRF Token Refresh — keeps session alive for long-lived forms (login, etc.)
|--------------------------------------------------------------------------
*/
Route::get('/csrf-refresh', function () {
    return response()->json([
        'token'      => csrf_token(),
        'expires_in' => config('session.lifetime') * 60,
    ]);
})->name('csrf.refresh');

/*
|--------------------------------------------------------------------------
| Admin Impersonation — stop endpoint
|--------------------------------------------------------------------------
| While impersonating, the admin is logged in on the `web` guard (as the
| target user). The banner "Stop impersonating" button POSTs here. No auth
| middleware — the session's `impersonator.admin_id` flag controls access.
*/
Route::post('/impersonate/stop', [\App\Http\Controllers\Admin\ImpersonationController::class, 'stop'])
    ->name('impersonate.stop');

Route::get('/lang/{locale}', [\App\Http\Controllers\Api\LanguageApiController::class, 'switch'])
    ->name('lang.switch')
    ->where('locale', 'th|en|zh');
Route::get('/lang/current', [\App\Http\Controllers\Api\LanguageApiController::class, 'current'])
    ->name('lang.current');

/*
|--------------------------------------------------------------------------
| Marketing — Public endpoints (newsletter, referral, loyalty, tracking)
|--------------------------------------------------------------------------
*/
Route::post('/newsletter/subscribe',  [\App\Http\Controllers\Public\MarketingController::class, 'subscribe'])->name('newsletter.subscribe');
Route::get('/newsletter/confirm/{token}', [\App\Http\Controllers\Public\MarketingController::class, 'confirm'])->name('newsletter.confirm');
Route::match(['GET', 'POST'], '/newsletter/unsubscribe', [\App\Http\Controllers\Public\MarketingController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

Route::get('/marketing/referral',  [\App\Http\Controllers\Public\MarketingController::class, 'myReferral'])->name('marketing.referral.me');
Route::get('/marketing/loyalty',   [\App\Http\Controllers\Public\MarketingController::class, 'myLoyalty'])->name('marketing.loyalty.me');

// Email tracking: pixel (open) + click redirect
Route::get('/m/o/{token}', [\App\Http\Controllers\Public\MarketingController::class, 'trackOpen'])->name('marketing.track.open');
Route::get('/m/c/{token}', [\App\Http\Controllers\Public\MarketingController::class, 'trackClick'])->name('marketing.track.click');

// ── Phase 3: Landing Pages ──
Route::get('/lp/{slug}',                [\App\Http\Controllers\Public\MarketingController::class, 'showLandingPage'])->name('marketing.landing.show');
Route::get('/lp/{landingPage}/cta',     [\App\Http\Controllers\Public\MarketingController::class, 'landingCtaClick'])->name('marketing.landing.cta');

// ── Phase 3: Web Push ──
Route::get('/push-sw.js',               [\App\Http\Controllers\Public\MarketingController::class, 'pushServiceWorker'])->name('marketing.push.sw');
Route::get('/push/vapid-public',        [\App\Http\Controllers\Public\MarketingController::class, 'pushVapidPublicKey'])->name('marketing.push.vapid');
Route::post('/push/subscribe',          [\App\Http\Controllers\Public\MarketingController::class, 'pushSubscribe'])->name('marketing.push.subscribe');
Route::post('/push/unsubscribe',        [\App\Http\Controllers\Public\MarketingController::class, 'pushUnsubscribe'])->name('marketing.push.unsubscribe');
Route::get('/m/pc/{campaignId}',        [\App\Http\Controllers\Public\MarketingController::class, 'pushClick'])->whereNumber('campaignId')->name('marketing.push.click');

/*
|--------------------------------------------------------------------------
| Public Routes (ลูกค้า / ผู้เข้าชม)
|--------------------------------------------------------------------------
*/

// ══════════════════════════════════════════════════════════════════════════
// ── Test: Hybrid Storage Architecture ──
// WARNING: these routes auto-login a hardcoded user (ID 2) and bypass all
// auth/middleware. They exist purely for local development debugging.
// Gated to `local` environment so they NEVER register in staging/production,
// even if someone forgets to remove them before deploying.
// ══════════════════════════════════════════════════════════════════════════
if (app()->environment('local')) {
    Route::get('/test/hybrid-storage', function () {
        // Auto-login as photographer for testing
        if (!\Auth::check()) {
            $user = \App\Models\User::find(2); // photographer
            if ($user) \Auth::login($user);
        }
        return view('test.hybrid-storage');
    });
    Route::post('/test/upload-photo/{event}', function (\Illuminate\Http\Request $request, $eventId) {
        // Direct upload test — bypasses photographer middleware
        $event = \App\Models\Event::findOrFail($eventId);
        if (!\Auth::check()) {
            $user = \App\Models\User::find(2);
            if ($user) \Auth::login($user);
        }
        $controller = app(\App\Http\Controllers\Photographer\PhotoController::class);
        return $controller->store($request, $event);
    });
    Route::post('/test/import-drive/{event}', function (\Illuminate\Http\Request $request, $eventId) {
        $event = \App\Models\Event::findOrFail($eventId);
        if (!\Auth::check()) {
            $user = \App\Models\User::find(2);
            if ($user) \Auth::login($user);
        }
        $controller = app(\App\Http\Controllers\Photographer\EventController::class);
        return $controller->importDrive($request, $event);
    });
    Route::get('/test/import-progress/{event}', function ($eventId) {
        $event = \App\Models\Event::findOrFail($eventId);
        if (!\Auth::check()) {
            $user = \App\Models\User::find(2);
            if ($user) \Auth::login($user);
        }
        $controller = app(\App\Http\Controllers\Photographer\EventController::class);
        return $controller->importProgress($event);
    });
    Route::get('/test/photo-status/{event}', function (\Illuminate\Http\Request $request, $eventId) {
        $event = \App\Models\Event::findOrFail($eventId);
        if (!\Auth::check()) {
            $user = \App\Models\User::find(2);
            if ($user) \Auth::login($user);
        }
        $controller = app(\App\Http\Controllers\Photographer\EventController::class);
        return $controller->photoStatus($request, $event);
    });
    Route::post('/test/process-queue', function () {
        $queue = app(\App\Services\QueueService::class);
        $processed = $queue->processNext();
        return response()->json([
            'processed' => $processed,
            'message' => $processed ? 'Processed 1 job' : 'No pending jobs',
        ]);
    });
    Route::post('/test/process-queue-all', function () {
        $queue = app(\App\Services\QueueService::class);
        $count = 0;
        while ($count < 50 && $queue->processNext()) { $count++; }
        return response()->json(['message' => "Processed {$count} job(s)"]);
    });
    Route::get('/test/queue-status', function () {
        $queue = app(\App\Services\QueueService::class);
        $photoStats = [
            'total' => \DB::table('event_photos')->count(),
            'active' => \DB::table('event_photos')->where('status', 'active')->count(),
            'processing' => \DB::table('event_photos')->where('status', 'processing')->count(),
            'failed' => \DB::table('event_photos')->where('status', 'failed')->count(),
        ];
        return response()->json([
            'queue' => $queue->getStatus(),
            'photos' => $photoStats,
        ]);
    });
    Route::post('/test/reset-queue', function () {
        \DB::table('sync_queue')->whereIn('status', ['pending', 'processing', 'failed'])->delete();
        \DB::table('jobs')->truncate();
        return response()->json(['message' => 'Queue reset สำเร็จ']);
    });
}

// Blog routes (public + admin)
require __DIR__ . '/blog.php';

// Announcement routes (admin CRUD + photographer + customer feed).
// Defined in its own file because the three audience-scoped surfaces
// don't share a single prefix.
require __DIR__ . '/announcements.php';

// SEO
Route::get('/sitemap.xml', [App\Http\Controllers\Public\SeoController::class, 'sitemap'])->name('sitemap')->middleware('edge.cache:3600,86400');
Route::get('/robots.txt', [App\Http\Controllers\Public\SeoController::class, 'robots'])->name('robots')->middleware('edge.cache:3600,86400');

// Homepage + static public pages
// edge.cache:{s-maxage},{stale-while-revalidate} — served by Cloudflare/CDN
// for unauthenticated visitors, so the Laravel app only handles ~5% of these
// pageviews at 50k concurrent.
Route::get('/',        [HomeController::class, 'index'])->name('home')->middleware('edge.cache:60,600');
Route::get('/help',    [HelpController::class, 'index'])->name('help')->middleware('edge.cache:300,3600');
Route::get('/contact', [ContactController::class, 'index'])->name('contact')->middleware('edge.cache:300,3600');

// Photographer-side B2B landing page — "Sell on us" sales pitch.
// Cached at the edge so paid-traffic spikes don't hit Laravel; pricing
// data is pulled from subscription_plans which already has its own
// 10-minute application cache, so the worst case is a 10-min stale plan
// price displayed — acceptable for a sales page.
Route::get('/sell-photos', [HomeController::class, 'forPhotographers'])
    ->name('sell-photos')->middleware('edge.cache:300,3600');

// ── Brand Ads tracking endpoints ────────────────────────────────────
// Public, low-overhead endpoints. Both rate-limited at the service
// level (per-IP impression dedup window + per-IP click cap). The
// route-level rate.limit here is a hard ceiling for abuse.
Route::get('/ads/{creative}/click', [\App\Http\Controllers\Public\AdController::class, 'click'])
    ->name('ads.click')->middleware('rate.limit:120,1');
Route::post('/ads/{creative}/seen', [\App\Http\Controllers\Public\AdController::class, 'seen'])
    ->name('ads.seen')->middleware('rate.limit:600,1');

// Programmatic SEO landings — niche × province grid (78 unique pages).
// /pro/{niche}              → "ช่างภาพงานแต่งทั่วประเทศ"
// /pro/{niche}/{province}   → "ช่างภาพงานแต่ง กรุงเทพ"
// Slugs constrained to ASCII letters + dashes so URLs stay copy-pasteable
// in older Thai-locale apps (LINE chat, etc.).
Route::get('/pro/{niche}/{province}', [\App\Http\Controllers\Public\SeoLandingController::class, 'show'])
    ->where(['niche' => '[a-z\-]+', 'province' => '[a-z\-]+'])
    ->name('seo.landing.province')
    ->middleware('edge.cache:300,3600');
Route::get('/pro/{niche}', [\App\Http\Controllers\Public\SeoLandingController::class, 'show'])
    ->where('niche', '[a-z\-]+')
    ->name('seo.landing.niche')
    ->middleware('edge.cache:300,3600');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

// Promo / "Why us" landing page — Thai-market focused marketing page that
// pitches the 3 killer USPs (LINE delivery, Face Search AI, Auto-payout).
// Heavy edge-cache: stats refresh every 5 min, page stays fast under campaign load.
Route::get('/promo', [\App\Http\Controllers\Public\PromoController::class, 'index'])->name('promo')->middleware('edge.cache:300,3600');
// Smart "buy this plan" redirect — funnels promo CTAs straight into the
// subscription checkout flow regardless of the user's auth state.
// NOT edge-cached: the redirect target depends on Auth::check().
Route::get('/promo/checkout/{code}', [\App\Http\Controllers\Public\PromoController::class, 'checkout'])
    ->where('code', '[a-z0-9_-]+')
    ->name('promo.checkout');
Route::get('/why-us', fn () => redirect()->route('promo'))->name('why-us'); // friendly alias

// Static documentation (HTML files in /docs at project root).
// Served via Laravel route so we don't duplicate the files into public/.
// Pattern is whitelisted to .html files inside docs/ to prevent path traversal.
Route::get('/docs/{path}', function (string $path) {
    // Whitelist: only forward-slash paths ending in .html, no '..' or absolute paths
    if (!preg_match('#^[a-z0-9_/-]+\.html$#i', $path) || str_contains($path, '..')) {
        abort(404);
    }
    $abs = base_path('docs/' . $path);
    if (!is_file($abs)) abort(404);
    return response()->file($abs, ['Content-Type' => 'text/html; charset=utf-8']);
})->where('path', '.*\.html$')->name('docs.show')->middleware('edge.cache:600,86400');

// Changelog — public history of features/fixes/improvements
Route::get('/changelog', [\App\Http\Controllers\Public\ChangelogController::class, 'index'])->name('changelog')->middleware('edge.cache:300,3600');

// Legal / Policy pages (Privacy, Terms, Refund, ...)
// Edge-cached heavily: pages change rarely and are identical for all visitors.
Route::get('/privacy-policy',   [\App\Http\Controllers\Public\LegalController::class, 'privacyPolicy'])->name('legal.privacy')->middleware('edge.cache:3600,86400');
Route::get('/terms-of-service', [\App\Http\Controllers\Public\LegalController::class, 'termsOfService'])->name('legal.terms')->middleware('edge.cache:3600,86400');
Route::get('/refund-policy',    [\App\Http\Controllers\Public\LegalController::class, 'refundPolicy'])->name('legal.refund')->middleware('edge.cache:3600,86400');
Route::get('/legal/{slug}',     [\App\Http\Controllers\Public\LegalController::class, 'show'])->name('legal.show')->where('slug', '[a-z0-9-]+')->middleware('edge.cache:3600,86400');

// User Support Portal (authenticated)
Route::middleware(['auth'])->prefix('support')->name('support.')->group(function () {
    Route::get('/', [ContactController::class, 'mytickets'])->name('index');
    Route::get('/{ticket}', [ContactController::class, 'showTicket'])->name('show');
    Route::post('/{ticket}/reply', [ContactController::class, 'replyTicket'])->name('reply');
    Route::post('/{ticket}/rate', [ContactController::class, 'rateTicket'])->name('rate');
});

// Cart Recovery (from abandoned cart email)
Route::get('/cart/recover/{token}', [\App\Http\Controllers\Public\CartRecoveryController::class, 'restore'])
    ->name('cart.recover')
    ->where('token', '[A-Za-z0-9]{32,64}');

// Refund Requests (authenticated users)
Route::middleware(['auth'])->prefix('refunds')->name('refunds.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Public\RefundController::class, 'index'])->name('index');
    Route::get('/create/{order}', [\App\Http\Controllers\Public\RefundController::class, 'create'])->name('create');
    Route::post('/create/{order}', [\App\Http\Controllers\Public\RefundController::class, 'store'])->name('store');
    Route::get('/{refundRequest}', [\App\Http\Controllers\Public\RefundController::class, 'show'])->name('show');
    Route::delete('/{refundRequest}', [\App\Http\Controllers\Public\RefundController::class, 'cancel'])->name('cancel');
});

// Wishlist sharing (authenticated owner + public viewer)
Route::middleware(['auth'])->prefix('wishlist/shares')->name('wishlist.shares.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Public\WishlistShareController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\Public\WishlistShareController::class, 'create'])->name('create');
    Route::delete('/{share}', [\App\Http\Controllers\Public\WishlistShareController::class, 'destroy'])->name('destroy');
});
// Public view (no auth)
Route::get('/wishlist/shared/{token}', [\App\Http\Controllers\Public\WishlistShareController::class, 'view'])
    ->name('wishlist.shared')
    ->where('token', '[A-Za-z0-9]{40}');

// Referral short URL — `/r/ABCD1234` captures the code into session/cookie
// (30-day TTL) and redirects to home (or `?to=events|photographers|register`).
// Guests can land here without auth; the code persists into checkout.
Route::get('/r/{code}', [\App\Http\Controllers\Public\ReferralController::class, 'redirect'])
    ->name('referral.short')
    ->where('code', '[A-Za-z0-9]{1,64}');

// Login History (user-facing)
Route::middleware(['auth'])->get('/profile/login-history', [\App\Http\Controllers\Public\LoginHistoryController::class, 'index'])
    ->name('profile.login-history');

// Login redirect alias (for auth middleware)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');

// Authentication
// login/register are rate-limited to blunt credential stuffing & account-creation abuse.
Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('rate.limit:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post')->middleware('rate.limit:5,10');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Social Login
    Route::get('/google', [AuthController::class, 'redirectGoogle'])->name('google');
    Route::get('/google/callback', [AuthController::class, 'callbackGoogle'])->name('google.callback');
    Route::get('/line', [AuthController::class, 'redirectLine'])->name('line');
    Route::get('/line/callback', [AuthController::class, 'callbackLine'])->name('line.callback');
    Route::get('/facebook', [AuthController::class, 'redirectFacebook'])->name('facebook');
    Route::get('/facebook/callback', [AuthController::class, 'callbackFacebook'])->name('facebook.callback');
});

// Forgot / Reset Password (Public users)
// Password reset endpoints are rate-limited — each send triggers a transactional email.
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('rate.limit:5,10');
Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update')->middleware('rate.limit:10,10');

// Email Verification
Route::get('/verify-email', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])->name('verification.send')->middleware('auth');

// Events (Public) — edge-cached for unauthenticated visitors
Route::get('/events',        [EventController::class, 'index'])->name('events.index')->middleware('edge.cache:30,600');
Route::get('/events/{slug}', [EventController::class, 'show'])->name('events.show')->middleware('edge.cache:60,600');
// Rate-limit password attempts — 5 tries per minute per IP. Without this a
// bot can brute-force the gate on password-protected galleries.
Route::post('/events/{id}/verify-password', [EventController::class, 'verifyEventPassword'])
    ->name('events.verify-password')
    ->middleware('rate.limit:5,1');

// AI Chatbot
// Rate-limited: chatbot calls an LLM per request → bound by external cost/latency.
// `feature.global:chatbot` is OFF by default — see Admin → Feature Flags.
// The chat-widget partial in app.blade.php is also conditionally rendered.
Route::post('/api/chatbot', [ChatbotController::class, 'chat'])->name('api.chatbot')->middleware(['feature.global:chatbot', 'rate.limit:20,1']);

// Face Search (AI)
// Rate-limited: face search is GPU-heavy (embeddings + ANN query); cap aggressively.
// Login-gated: face search uploads biometric data (PDPA §26) — we require an
// authenticated user so we can audit who searched, throttle per-account, and
// later let users review/delete their own search history. Guests are bounced
// to /login with the intended URL preserved (Laravel's default 'auth' behavior).
Route::middleware(['auth'])->group(function () {
    Route::get('/events/{id}/face-search', [\App\Http\Controllers\Public\FaceSearchController::class, 'show'])
        ->name('events.face-search');
    Route::post('/api/face-search/{id}', [\App\Http\Controllers\Public\FaceSearchController::class, 'search'])
        ->name('api.face-search')
        ->middleware(['rate.limit:10,1', 'turnstile', 'usage.quota:ai.face_search']);

    // Direct browser → R2 uploads (presigned PUT). Heavily rate-limited so
    // an authenticated attacker can't burn through R2 PutObject quota by
    // requesting URLs in a tight loop.
    Route::post('/api/uploads/sign',    [\App\Http\Controllers\Api\PresignedUploadController::class, 'sign'])
        ->name('api.uploads.sign')
        ->middleware('rate.limit:60,1');
    Route::post('/api/uploads/confirm', [\App\Http\Controllers\Api\PresignedUploadController::class, 'confirm'])
        ->name('api.uploads.confirm')
        ->middleware('rate.limit:120,1');

    // Multipart / resumable uploads. The per-route rate limits are tighter
    // than the single-PUT path because chunked uploads issue many calls
    // per file; without these caps a misbehaving client could DoS R2's
    // PutObject quota.
    //   sign-part   — one call per chunk; allow more
    //   record-part — one call per chunk; allow more
    //   init        — one call per file; lower cap is fine
    //   complete    — one call per file
    //   abort       — defensive cleanup; allow burst
    Route::prefix('api/uploads/multipart')->group(function () {
        Route::post('/init',     [\App\Http\Controllers\Api\MultipartUploadController::class, 'initMultipart'])
            ->name('api.uploads.multipart.init')->middleware('rate.limit:60,1');
        Route::post('/sign-part',  [\App\Http\Controllers\Api\MultipartUploadController::class, 'signPart'])
            ->name('api.uploads.multipart.sign-part')->middleware('rate.limit:600,1');
        Route::post('/record-part',[\App\Http\Controllers\Api\MultipartUploadController::class, 'recordPart'])
            ->name('api.uploads.multipart.record-part')->middleware('rate.limit:600,1');
        Route::get('/{uploadId}/parts',[\App\Http\Controllers\Api\MultipartUploadController::class, 'listParts'])
            ->name('api.uploads.multipart.parts')->middleware('rate.limit:120,1');
        Route::post('/complete', [\App\Http\Controllers\Api\MultipartUploadController::class, 'completeMultipart'])
            ->name('api.uploads.multipart.complete')->middleware('rate.limit:60,1');
        Route::post('/abort',    [\App\Http\Controllers\Api\MultipartUploadController::class, 'abortMultipart'])
            ->name('api.uploads.multipart.abort')->middleware('rate.limit:120,1');
    });

    Route::prefix('api/uploads/session')->group(function () {
        Route::post('/',                  [\App\Http\Controllers\Api\MultipartUploadController::class, 'openSession'])
            ->name('api.uploads.session.open')->middleware('rate.limit:30,1');
        Route::get('/{token}',            [\App\Http\Controllers\Api\MultipartUploadController::class, 'sessionStatus'])
            ->name('api.uploads.session.status')->middleware('rate.limit:120,1');
        Route::post('/{token}/progress',  [\App\Http\Controllers\Api\MultipartUploadController::class, 'sessionProgress'])
            ->name('api.uploads.session.progress')->middleware('rate.limit:600,1');
        Route::post('/{token}/complete',  [\App\Http\Controllers\Api\MultipartUploadController::class, 'sessionComplete'])
            ->name('api.uploads.session.complete')->middleware('rate.limit:30,1');
    });
});

// Products (Digital)
Route::get('/products',        [ProductController::class, 'index'])->name('products.index')->middleware('edge.cache:60,600');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show')->where('slug', '^(?!checkout|order|my-orders|download)[a-z0-9\-]+$')->middleware('edge.cache:300,3600');

// Photographers (Public Profiles) — two URL forms, both → show().
// Singular `/photographer/{slug}` is reserved for the existing
// authenticated photographer dashboard tree (see line 1497) and would
// collide with /photographer/login etc — so the public slug form lives
// under the plural prefix `/photographers/p/{slug}`. The plain
// `/photographers/{id}` (numeric) stays for back-compat and 301s to
// the canonical slug URL when a slug exists.
// Public photographers index — `/photographers` lists all approved
// photographers with paid-promotion boost ordering. Edge-cached briefly
// since boost re-sort changes hourly.
Route::get('/photographers', [PhotographerController::class, 'index'])
    ->name('photographers.index')
    ->middleware('edge.cache:60,600');
Route::get('/photographers/{id}', [PhotographerController::class, 'show'])
    ->where('id', '[0-9]+')
    ->name('photographers.show')->middleware('edge.cache:60,600');
Route::get('/photographers/p/{slug}', [PhotographerController::class, 'show'])
    ->where('slug', '[a-z0-9][a-z0-9\-]+')
    ->name('photographers.show.slug')
    ->middleware('edge.cache:60,600');

// ── Booking flow (customer-facing) ───────────────────────────────────────
// Customer requests a shoot from a specific photographer; photographer side
// handles confirm/cancel under /photographer/bookings (auth required).
Route::middleware(['auth'])->group(function () {
    // Booking form is per-photographer
    Route::get('/photographers/{photographer}/book',   [\App\Http\Controllers\Public\BookingController::class, 'create'])->name('bookings.create');
    Route::post('/photographers/{photographer}/book',  [\App\Http\Controllers\Public\BookingController::class, 'store'])->name('bookings.store');
    // Customer's own bookings dashboard
    Route::get('/profile/bookings',                    [\App\Http\Controllers\Public\BookingController::class, 'index'])->name('profile.bookings');
    Route::get('/profile/bookings/{booking}',          [\App\Http\Controllers\Public\BookingController::class, 'show'])->name('profile.bookings.show');
    Route::post('/profile/bookings/{booking}/cancel',  [\App\Http\Controllers\Public\BookingController::class, 'cancel'])->name('profile.bookings.cancel');
});

// Reviews (Public Listing)
Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index')->middleware('edge.cache:60,600');

// Downloads (token-based, no auth required — tokens are self-authenticating)
// processDownload is rate-limited: each call may trigger a ZIP build or Drive fetch.
Route::get('/download/{token}', [DownloadController::class, 'showDownload'])->name('download.show');
Route::post('/download/{token}', [DownloadController::class, 'processDownload'])->name('download.process')->middleware('rate.limit:30,1');

/*
|--------------------------------------------------------------------------
| Cloud Storage — Public pricing + shared-link download
|--------------------------------------------------------------------------
| Pricing page is accessible without auth (marketing). Share tokens carry
| their own auth (password-protected shares gate via session flag). The
| entire module can be disabled via the `user.storage` middleware.
*/
Route::middleware(['user.storage'])->group(function () {
    Route::get('/storage/pricing', [\App\Http\Controllers\Public\StoragePricingController::class, 'index'])
        ->name('storage.pricing');

    // Public share endpoint — `/s/{token}` is short enough to paste into chat.
    Route::get('/s/{token}',            [\App\Http\Controllers\Public\StorageShareController::class, 'show'])->name('storage.share.show');
    Route::post('/s/{token}/verify',    [\App\Http\Controllers\Public\StorageShareController::class, 'verify'])->name('storage.share.verify')->middleware('rate.limit:10,1');
    Route::get('/s/{token}/download',   [\App\Http\Controllers\Public\StorageShareController::class, 'download'])->name('storage.share.download')->middleware('rate.limit:60,1');
});

/*
|--------------------------------------------------------------------------
| Authenticated User Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'no.back'])->group(function () {
    // Choose Role
    Route::get('/choose-role', [AuthController::class, 'showChooseRole'])->name('choose-role');
    Route::post('/choose-role', [AuthController::class, 'storeChooseRole'])->name('choose-role.store');

    // LINE Connect prompt (for users who didn't sign up via LINE)
    Route::get('/auth/connect-line', [\App\Http\Controllers\Public\LineConnectController::class, 'show'])->name('auth.connect-line');
    Route::post('/auth/connect-line/skip', [\App\Http\Controllers\Public\LineConnectController::class, 'skip'])->name('auth.connect-line.skip');

    // Profile Dashboard (named 'profile' for backward-compat + 'profile.show' as dedicated name)
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/orders', [ProfileController::class, 'orders'])->name('profile.orders');
    Route::get('/profile/downloads', [ProfileController::class, 'downloads'])->name('profile.downloads');
    Route::get('/profile/reviews', [ProfileController::class, 'reviews'])->name('profile.reviews');
    Route::get('/profile/referrals', [ProfileController::class, 'referrals'])->name('profile.referrals');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::get('/profile/notifications', [ProfileController::class, 'notificationPreferences'])->name('profile.notification-preferences');
    Route::put('/profile/notifications', [ProfileController::class, 'updateNotificationPreferences'])->name('profile.notification-preferences.update');

    // Notifications Centre
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // Photographer sign-up — quick 1-page upgrade for logged-in customers
    Route::get('/become-photographer/quick',          [\App\Http\Controllers\Public\PhotographerOnboardingController::class, 'showQuick'])->name('photographer-onboarding.quick');
    Route::post('/become-photographer/quick',         [\App\Http\Controllers\Public\PhotographerOnboardingController::class, 'saveQuick'])->name('photographer-onboarding.quick.save')->middleware('rate.limit:5,10');

    // Photographer sign-up — legacy 2-step wizard (kept for users with
    // bookmarked URLs or admins who want the detailed flow)
    Route::get('/become-photographer',                [\App\Http\Controllers\Public\PhotographerOnboardingController::class, 'index'])->name('photographer-onboarding.index');
    Route::post('/become-photographer/step/{step}',   [\App\Http\Controllers\Public\PhotographerOnboardingController::class, 'save'])->name('photographer-onboarding.save');

    // Gift Cards
    //   - index is public (browse / land on the page)
    //   - purchase requires auth + rate-limit so we always have a real
    //     Order owner; previously this endpoint minted a usable card on
    //     form submit with no payment, which was a free-money exploit.
    //     Now it creates a pending Order and redirects to checkout.
    //   - lookup is rate-limited to deter bruteforce code enumeration.
    Route::get('/gift-cards',          [\App\Http\Controllers\Public\GiftCardController::class, 'index'])->name('gift-cards.index');
    Route::post('/gift-cards',         [\App\Http\Controllers\Public\GiftCardController::class, 'purchase'])
        ->middleware(['auth', 'rate.limit:5,10'])
        ->name('gift-cards.purchase');
    Route::post('/gift-cards/lookup',  [\App\Http\Controllers\Public\GiftCardController::class, 'lookup'])
        ->middleware('rate.limit:30,1')
        ->name('gift-cards.lookup');

    // PDPA Data Export / Delete requests (self-serve)
    Route::get('/profile/data-export', [\App\Http\Controllers\Public\DataExportController::class, 'index'])->name('data-export.index');
    Route::post('/profile/data-export', [\App\Http\Controllers\Public\DataExportController::class, 'store'])
        ->name('data-export.store')
        ->middleware('usage.quota:export.run');
    Route::get('/profile/data-export/download/{token}', [\App\Http\Controllers\Public\DataExportController::class, 'download'])->name('data-export.download');
    Route::post('/profile/data-export/{request}/cancel', [\App\Http\Controllers\Public\DataExportController::class, 'cancel'])->name('data-export.cancel');

    // Cart
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/add-bulk', [CartController::class, 'addBulk'])->name('cart.add-bulk');
    Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
    Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');
    Route::delete('/cart/{item}', [CartController::class, 'remove'])->name('cart.remove.delete');

    // Coupon + referral apply / remove (cart UI). Same controller is also
    // mounted under /api/* below for AJAX callers; both share the same logic
    // and the JSON-vs-redirect response is auto-detected from the request.
    Route::post('/cart/coupon',           [\App\Http\Controllers\Api\CouponApiController::class, 'apply'])->name('cart.coupon');
    Route::post('/cart/coupon/remove',    [\App\Http\Controllers\Api\CouponApiController::class, 'remove'])->name('cart.coupon.remove.public');
    Route::post('/cart/referral',         [\App\Http\Controllers\Api\CouponApiController::class, 'applyReferral'])->name('cart.referral');
    Route::post('/cart/referral/remove',  [\App\Http\Controllers\Api\CouponApiController::class, 'removeReferral'])->name('cart.referral.remove.public');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::post('/orders/express', [OrderController::class, 'expressCheckout'])->name('orders.express');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{id}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
    Route::post('/orders/{id}/send-invoice', [OrderController::class, 'sendInvoice'])->name('orders.send-invoice');
    Route::get('/orders/{id}/download-zip', [OrderController::class, 'downloadZip'])->name('orders.download-zip');

    // Payments
    Route::get('/payment/checkout/{order}', [PaymentController::class, 'checkout'])->name('payment.checkout');
    Route::post('/payment/process', [PaymentController::class, 'process'])->name('payment.process');
    Route::post('/payment/slip/upload', [PaymentController::class, 'uploadSlip'])
        ->name('payment.slip.upload')
        // Burst protection: 5 slip uploads per minute per user.
        // Slip verification hits SlipOK (paid API + AWS Rekognition for OCR);
        // unbounded uploads = DoS on the third party + cost runaway. Five
        // is generous enough that a customer can retry on a bad photo.
        ->middleware('rate.limit:5,1');
    Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
    Route::get('/payment/status/{order}', [PaymentController::class, 'status'])->name('payment.status');
    Route::get('/payment/check-status/{order}', [PaymentController::class, 'checkStatus'])->name('payment.check-status');

    // Downloads (authenticated — history page)
    Route::get('/downloads', [DownloadController::class, 'downloadHistory'])->name('download.history');

    // Reviews
    Route::get('/reviews/create/{order}', [ReviewController::class, 'create'])->name('reviews.create');
    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::post('/reviews/{review}/helpful', [ReviewController::class, 'toggleHelpful'])->name('reviews.helpful');
    Route::post('/reviews/{review}/report', [ReviewController::class, 'report'])->name('reviews.report');

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

    // Chat — gated by `feature.global:chat`. Default OFF on fresh installs
    // (see AppSettingsSeeder). Toggle from /admin/settings → Features.
    // When OFF: routes return 404, the chat-widget partial is hidden,
    // and the photographer's "ส่งข้อความ" CTA on profile pages is muted.
    Route::middleware('feature.global:chat')->group(function () {
        Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
        Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
        Route::post('/chat/{conversation}/send', [ChatController::class, 'send'])->name('chat.send');
        Route::post('/chat/start/{photographer}', [ChatController::class, 'start'])->name('chat.start');

        // Chat API (enhanced: read receipts, typing, search, archive, attachments)
        Route::prefix('api/chat')->name('api.chat.')->group(function () {
            Route::get('/conversations', [\App\Http\Controllers\Api\ChatApiController::class, 'conversations'])->name('conversations');
            Route::get('/search', [\App\Http\Controllers\Api\ChatApiController::class, 'search'])->name('search');
            Route::get('/{conversation}/messages', [\App\Http\Controllers\Api\ChatApiController::class, 'messages'])->name('messages');
            Route::post('/{conversation}/send', [\App\Http\Controllers\Api\ChatApiController::class, 'send'])->name('send');
            Route::post('/{conversation}/read', [\App\Http\Controllers\Api\ChatApiController::class, 'markRead'])->name('read');
            Route::post('/{conversation}/typing', [\App\Http\Controllers\Api\ChatApiController::class, 'typing'])->name('typing');
            Route::post('/{conversation}/archive', [\App\Http\Controllers\Api\ChatApiController::class, 'archive'])->name('archive');
            Route::delete('/{conversation}/messages/{message}', [\App\Http\Controllers\Api\ChatApiController::class, 'deleteMessage'])->name('delete-message');
        });
    });

    // Digital Product Purchase & Orders
    Route::post('/products/{product}/purchase', [ProductController::class, 'purchase'])->name('products.purchase');
    Route::get('/products/checkout/{order}', [ProductController::class, 'checkout'])->name('products.checkout');
    Route::post('/products/checkout/{order}/upload-slip', [ProductController::class, 'uploadSlip'])->name('products.upload-slip');
    Route::get('/products/order/{order}', [ProductController::class, 'order'])->name('products.order');
    Route::get('/products/order/{order}/status', [ProductController::class, 'orderStatus'])->name('products.order.status');
    Route::get('/products/my-orders', [ProductController::class, 'myOrders'])->name('products.my-orders');
    Route::get('/products/download/{token}', [ProductController::class, 'download'])->name('products.download');

    /*
     * Cloud Storage — Authenticated consumer routes.
     * The `user.storage` middleware kills the whole module when the
     * feature is disabled in AppSetting.
     */
    Route::middleware(['user.storage'])->prefix('storage')->name('storage.')->group(function () {
        // Dashboard + plan management
        Route::get('/',         [\App\Http\Controllers\Public\StorageSubscriptionController::class, 'index'])->name('index');
        Route::get('/plans',    [\App\Http\Controllers\Public\StorageSubscriptionController::class, 'plans'])->name('plans');
        Route::post('/subscribe/{code}', [\App\Http\Controllers\Public\StorageSubscriptionController::class, 'subscribe'])->name('subscribe')->middleware('rate.limit:20,1');
        Route::post('/change/{code}',    [\App\Http\Controllers\Public\StorageSubscriptionController::class, 'change'])->name('change')->middleware('rate.limit:20,1');
        Route::post('/cancel',  [\App\Http\Controllers\Public\StorageSubscriptionController::class, 'cancel'])->name('cancel');
        Route::post('/resume',  [\App\Http\Controllers\Public\StorageSubscriptionController::class, 'resume'])->name('resume');
        Route::get('/invoices', [\App\Http\Controllers\Public\StorageSubscriptionController::class, 'invoices'])->name('invoices');

        // File manager — folder param is optional (root listing when omitted)
        Route::get('/files/{folder?}',         [\App\Http\Controllers\Public\FileManagerController::class, 'show'])->name('files.index')->where('folder', '[0-9]+');
        Route::post('/files/upload',           [\App\Http\Controllers\Public\FileManagerController::class, 'upload'])->name('files.upload')->middleware('rate.limit:120,1');
        Route::post('/files/folder',           [\App\Http\Controllers\Public\FileManagerController::class, 'createFolder'])->name('files.folder.create');
        Route::put('/files/folder/{folder}',   [\App\Http\Controllers\Public\FileManagerController::class, 'renameFolder'])->name('files.folder.rename');
        Route::delete('/files/folder/{folder}',[\App\Http\Controllers\Public\FileManagerController::class, 'deleteFolder'])->name('files.folder.delete');
        Route::put('/files/{file}/rename',     [\App\Http\Controllers\Public\FileManagerController::class, 'rename'])->name('files.rename');
        Route::post('/files/{file}/move',      [\App\Http\Controllers\Public\FileManagerController::class, 'move'])->name('files.move');
        Route::delete('/files/{file}',         [\App\Http\Controllers\Public\FileManagerController::class, 'destroy'])->name('files.destroy');
        Route::post('/files/{file}/share',     [\App\Http\Controllers\Public\FileManagerController::class, 'share'])->name('files.share');
        Route::delete('/files/{file}/share',   [\App\Http\Controllers\Public\FileManagerController::class, 'unshare'])->name('files.unshare');
        Route::get('/files/{file}/download',   [\App\Http\Controllers\Public\FileManagerController::class, 'download'])->name('files.download')->middleware('rate.limit:60,1');
    });

    // User API (session-based, shared with web)
    Route::prefix('api')->group(function () {
        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Api\NotificationApiController::class, 'index']);
        Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationApiController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationApiController::class, 'markRead']);
        Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationApiController::class, 'markAllRead']);
        Route::post('/presence', [\App\Http\Controllers\Api\NotificationApiController::class, 'presence']);

        // Wishlist
        Route::get('/wishlist', [\App\Http\Controllers\Api\WishlistApiController::class, 'index']);
        Route::post('/wishlist/toggle', [\App\Http\Controllers\Api\WishlistApiController::class, 'toggle']);

        // Cart
        Route::get('/cart', [\App\Http\Controllers\Api\CartApiController::class, 'index']);
        Route::post('/cart/add', [\App\Http\Controllers\Api\CartApiController::class, 'add']);
        Route::delete('/cart/{item}', [\App\Http\Controllers\Api\CartApiController::class, 'remove']);
        Route::post('/cart/bundle', [\App\Http\Controllers\Api\CartApiController::class, 'addBundle']);
        // Smart upsell suggestion — given the current cart for an event, returns
        // the next bundle that gives the buyer the best per-photo savings.
        Route::get('/cart/upsell-suggestion', [\App\Http\Controllers\Api\CartApiController::class, 'upsellSuggestion'])->name('cart.upsell');
        // Face-match bundle quote — given the photo IDs that face-search returned,
        // returns the bundle price + savings breakdown so the modal can render it.
        Route::post('/cart/face-bundle/quote', [\App\Http\Controllers\Api\CartApiController::class, 'faceBundleQuote'])->name('cart.face-bundle.quote');
        // Add a face-match bundle to the cart (creates a virtual bundle item).
        Route::post('/cart/face-bundle/add', [\App\Http\Controllers\Api\CartApiController::class, 'addFaceBundle'])->name('cart.face-bundle.add');
        Route::post('/cart/coupon', [\App\Http\Controllers\Api\CouponApiController::class, 'apply'])->name('cart.coupon.apply');
        Route::post('/cart/coupon/remove', [\App\Http\Controllers\Api\CouponApiController::class, 'remove'])->name('cart.coupon.remove');
        Route::post('/cart/referral', [\App\Http\Controllers\Api\CouponApiController::class, 'applyReferral'])->name('cart.referral.apply');
        Route::post('/cart/referral/remove', [\App\Http\Controllers\Api\CouponApiController::class, 'removeReferral'])->name('cart.referral.remove');

        // Chat
        Route::get('/chat/{conversation}/messages', [\App\Http\Controllers\Api\ChatApiController::class, 'messages']);
        Route::post('/chat/{conversation}/send', [\App\Http\Controllers\Api\ChatApiController::class, 'send']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    // Admin Auth (no auth middleware, but POST has rate limit to block brute-force).
    // 5 attempts per 1 minute per IP — tighter than the public user login (10/min)
    // because admin accounts are privileged and usually fewer legitimate attempts.
    Route::get('/login', [\App\Http\Controllers\Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])
        ->name('login.post')
        ->middleware('rate.limit:5,1');
    Route::post('/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('logout');

    // ─────────────────────────────────────────────────────────────────────
    // DEPLOYMENT / SERVER SETTINGS — manage .env (DB, Mail, Storage, Domain)
    // Sits OUTSIDE the admin auth + 2FA stack so a fresh upload (no admin
    // user yet) can still reach this page to bootstrap the install. The
    // 'admin.or.install' middleware allows access when:
    //   • install mode is active AND request is trusted (localhost/private/
    //     non-prod/admin-auth), OR
    //   • the user is already authenticated as admin (post-install).
    // It auto-secures: as soon as an admin user exists, install mode ends
    // and this acts like a normal admin route again.
    // ─────────────────────────────────────────────────────────────────────
    Route::prefix('deployment')->name('deployment.')->middleware(['admin.or.install'])->group(function () {
        Route::get('/',                  [\App\Http\Controllers\Admin\DeploymentController::class, 'index'])->name('index');
        Route::get('/guide',             [\App\Http\Controllers\Admin\DeploymentController::class, 'guide'])->name('guide');
        Route::post('/save',             [\App\Http\Controllers\Admin\DeploymentController::class, 'save'])->name('save');
        Route::post('/test/database',    [\App\Http\Controllers\Admin\DeploymentController::class, 'testDatabase'])->name('test.database');
        Route::post('/test/mail',        [\App\Http\Controllers\Admin\DeploymentController::class, 'testMail'])->name('test.mail');
        Route::post('/test/storage',     [\App\Http\Controllers\Admin\DeploymentController::class, 'testStorage'])->name('test.storage');
        Route::post('/test/cache',       [\App\Http\Controllers\Admin\DeploymentController::class, 'testCache'])->name('test.cache');
        Route::post('/backup',           [\App\Http\Controllers\Admin\DeploymentController::class, 'backup'])->name('backup');
        // ── Install-mode bootstrap actions ─────────────────────────────
        Route::post('/install/key',      [\App\Http\Controllers\Admin\DeploymentController::class, 'generateAppKey'])->name('install.key');
        Route::post('/install/migrate',  [\App\Http\Controllers\Admin\DeploymentController::class, 'runMigrations'])->name('install.migrate');
        Route::post('/install/admin',    [\App\Http\Controllers\Admin\DeploymentController::class, 'createFirstAdmin'])->name('install.admin');
    });

    // Admin Protected Routes — require login + 2FA enrolment + 2FA challenge
    //   admin            : session auth
    //   admin.2fa.setup  : force TOTP enrolment when enforcement is ON
    //                      (toggle at Admin → Settings → 2FA, default OFF;
    //                      can be pinned via ENFORCE_ADMIN_2FA env var)
    //   admin.2fa        : once enrolled, challenge this session before access
    //   no.back          : cache-control to stop Back button leaking admin pages
    Route::middleware(['admin', 'admin.2fa.setup', 'admin.2fa', 'no.back'])->group(function () {
        // ─── Analytics + capacity (JSON, for the admin dashboard) ───
        Route::get('/analytics/capacity', [\App\Http\Controllers\Admin\AnalyticsController::class, 'capacity'])
            ->name('analytics.capacity');
        Route::get('/analytics/trend',    [\App\Http\Controllers\Admin\AnalyticsController::class, 'trend'])
            ->name('analytics.trend');

        // ─── 2FA Challenge routes ───
        // Logged in but 2FA not yet verified. These opt out of the `admin.2fa`
        // middleware so the admin can reach the challenge view without looping.
        // They already have 2FA enabled (that's why we're challenging), so
        // `admin.2fa.setup` passes through — no opt-out needed there.
        Route::get('/2fa/challenge',  [\App\Http\Controllers\Admin\TwoFactorChallengeController::class, 'show'])
            ->name('2fa.challenge')->withoutMiddleware('admin.2fa');
        Route::post('/2fa/challenge', [\App\Http\Controllers\Admin\TwoFactorChallengeController::class, 'verify'])
            ->name('2fa.challenge.verify')->withoutMiddleware('admin.2fa')->middleware('rate.limit:10,1');
        Route::post('/2fa/cancel',    [\App\Http\Controllers\Admin\TwoFactorChallengeController::class, 'cancel'])
            ->name('2fa.cancel')->withoutMiddleware('admin.2fa');

        Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/online-users', [\App\Http\Controllers\Admin\DashboardController::class, 'onlineUsers'])->name('online-users');
        Route::get('/api/online-users', [\App\Http\Controllers\Admin\DashboardController::class, 'onlineUsersApi'])->name('api.online-users');

        // Usage & Margin dashboard — per-plan economics + circuit-breaker control
        Route::get('/usage', [\App\Http\Controllers\Admin\UsageController::class, 'index'])->name('usage');
        Route::post('/usage/breakers/{feature}/trip',  [\App\Http\Controllers\Admin\UsageController::class, 'tripBreaker'])
            ->name('usage.breaker.trip')->where('feature', '[a-z0-9_.]+');
        Route::post('/usage/breakers/{feature}/reset', [\App\Http\Controllers\Admin\UsageController::class, 'resetBreaker'])
            ->name('usage.breaker.reset')->where('feature', '[a-z0-9_.]+');

        // Global Search (AJAX)
        Route::get('/search', [\App\Http\Controllers\Admin\GlobalSearchController::class, 'search'])->name('search');

        // Storage Stats
        Route::get('/storage', [\App\Http\Controllers\Admin\StorageStatsController::class, 'index'])->name('storage');

        // Login History
        Route::get('/login-history', [\App\Http\Controllers\Admin\LoginHistoryController::class, 'index'])->name('login-history');

        // User Manual (comprehensive guide)
        Route::get('/manual', fn() => view('admin.manual'))->name('manual');

        // Event Clone
        Route::post('/events/{event}/clone', [\App\Http\Controllers\Admin\EventCloneController::class, 'clone'])->name('events.clone');

        // Activity Log Export
        Route::get('/activity-log/export', function (\App\Services\ActivityLogService $svc) {
            return $svc->export(request()->only(['date_from', 'date_to', 'admin_id', 'user_id', 'action']));
        })->name('activity-log.export');

        // LINE Notify / Messaging test
        Route::post('/api/admin/line-test', [\App\Http\Controllers\Api\AdminApiController::class, 'lineTest'])->name('api.admin.line-test');

        // AWS S3 / SES / CloudFront test
        Route::post('/api/admin/aws-test', [\App\Http\Controllers\Api\AdminApiController::class, 'awsTest'])->name('api.admin.aws-test');

        // Google Drive test & queue management
        Route::post('/api/admin/drive-test', [\App\Http\Controllers\Api\AdminApiController::class, 'driveTest'])->name('api.admin.drive-test');
        Route::match(['get', 'post'], '/api/admin/drive-queue', [\App\Http\Controllers\Api\AdminApiController::class, 'driveQueue'])->name('api.admin.drive-queue');

        // Admin Notifications API (in web.php for session-based admin auth)
        Route::get('/notifications/api', [\App\Http\Controllers\Api\NotificationApiController::class, 'adminIndex'])->name('api.admin.notifications');
        Route::post('/notifications/api/{id}/read', [\App\Http\Controllers\Api\NotificationApiController::class, 'adminMarkRead'])->name('api.admin.notifications.read');
        Route::post('/notifications/api/read-all', [\App\Http\Controllers\Api\NotificationApiController::class, 'adminMarkAllRead'])->name('api.admin.notifications.read-all');
        Route::post('/notifications/api/mark-by-ref', [\App\Http\Controllers\Api\NotificationApiController::class, 'adminMarkByRef'])->name('api.admin.notifications.mark-by-ref');
        Route::get('/notifications/api/stats', [\App\Http\Controllers\Api\NotificationApiController::class, 'adminStats'])->name('api.admin.notifications.stats');

        // Admin Notifications Management UI
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('index');
            Route::post('/{id}/read', [\App\Http\Controllers\Admin\NotificationController::class, 'markRead'])->name('read');
            Route::post('/read-all', [\App\Http\Controllers\Admin\NotificationController::class, 'markAllRead'])->name('read-all');
            Route::delete('/{id}', [\App\Http\Controllers\Admin\NotificationController::class, 'destroy'])->name('destroy');
            Route::post('/bulk-action', [\App\Http\Controllers\Admin\NotificationController::class, 'bulkAction'])->name('bulk-action');
            Route::post('/cleanup', [\App\Http\Controllers\Admin\NotificationController::class, 'cleanup'])->name('cleanup');
            Route::post('/broadcast', [\App\Http\Controllers\Admin\NotificationController::class, 'broadcast'])->name('broadcast');
        });

        // Events
        Route::resource('events', \App\Http\Controllers\Admin\EventController::class);
        Route::get('events/{event}/qrcode', [\App\Http\Controllers\Admin\EventController::class, 'qrcode'])->name('events.qrcode');
        Route::post('events/{event}/toggle-status', [\App\Http\Controllers\Admin\EventController::class, 'toggleStatus'])->name('events.toggle-status');

        // Location API (cascading province → district → subdistrict)
        Route::get('api/locations/districts', [\App\Http\Controllers\Admin\EventController::class, 'getDistricts'])->name('api.locations.districts');
        Route::get('api/locations/subdistricts', [\App\Http\Controllers\Admin\EventController::class, 'getSubdistricts'])->name('api.locations.subdistricts');

        // Event Categories
        Route::resource('categories', \App\Http\Controllers\Admin\CategoryController::class)->except(['show']);

        // Orders.
        // IMPORTANT: register `orders/export` BEFORE the resource route so that
        // `/admin/orders/export` matches the export action and not the
        // `orders/{order}` show action (which would parse "export" as an int
        // and crash with SQLSTATE 22P02 on Postgres / silent miss on MySQL).
        Route::get('orders/export', [\App\Http\Controllers\Admin\OrderController::class, 'export'])->name('orders.export');
        Route::resource('orders', \App\Http\Controllers\Admin\OrderController::class)->only(['index', 'show', 'update']);

        // Users
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class);
        Route::post('users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/impersonate',    [\App\Http\Controllers\Admin\ImpersonationController::class, 'start'])->name('users.impersonate');
        Route::get('users-export', [\App\Http\Controllers\Admin\UserController::class, 'export'])->name('users.export');

        // Photographers
        Route::resource('photographers', \App\Http\Controllers\Admin\PhotographerController::class);
        Route::post('photographers/{photographer}/approve', [\App\Http\Controllers\Admin\PhotographerController::class, 'approve'])->name('photographers.approve');
        Route::post('photographers/{photographer}/suspend', [\App\Http\Controllers\Admin\PhotographerController::class, 'suspend'])->name('photographers.suspend');
        Route::post('photographers/{photographer}/reactivate', [\App\Http\Controllers\Admin\PhotographerController::class, 'reactivate'])->name('photographers.reactivate');
        Route::post('photographers/{photographer}/toggle-status', [\App\Http\Controllers\Admin\PhotographerController::class, 'toggleStatus'])->name('photographers.toggle-status');
        Route::post('photographers/{photographer}/adjust-commission', [\App\Http\Controllers\Admin\PhotographerController::class, 'adjustCommission'])->name('photographers.adjust-commission');
        Route::post('photographers/{photographer}/reset-password', [\App\Http\Controllers\Admin\PhotographerController::class, 'resetPassword'])->name('photographers.reset-password');

        // Commission Management
        Route::prefix('commission')->name('commission.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\CommissionController::class, 'index'])->name('index');
            Route::get('/settings', [\App\Http\Controllers\Admin\CommissionController::class, 'settings'])->name('settings');
            Route::post('/settings', [\App\Http\Controllers\Admin\CommissionController::class, 'updateSettings'])->name('settings.update');
            Route::get('/tiers', [\App\Http\Controllers\Admin\CommissionController::class, 'tiers'])->name('tiers');
            Route::post('/tiers', [\App\Http\Controllers\Admin\CommissionController::class, 'storeTier'])->name('tiers.store');
            Route::put('/tiers/{tier}', [\App\Http\Controllers\Admin\CommissionController::class, 'updateTier'])->name('tiers.update');
            Route::delete('/tiers/{tier}', [\App\Http\Controllers\Admin\CommissionController::class, 'destroyTier'])->name('tiers.destroy');
            Route::post('/tiers/apply', [\App\Http\Controllers\Admin\CommissionController::class, 'applyTiers'])->name('tiers.apply');
            Route::get('/history', [\App\Http\Controllers\Admin\CommissionController::class, 'history'])->name('history');
            Route::get('/bulk', [\App\Http\Controllers\Admin\CommissionController::class, 'bulk'])->name('bulk');
            Route::post('/bulk', [\App\Http\Controllers\Admin\CommissionController::class, 'bulkUpdate'])->name('bulk.update');
        });

        // Payments
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('index');
            Route::get('/methods', [\App\Http\Controllers\Admin\PaymentController::class, 'methods'])->name('methods');
            Route::post('/methods/{id}/toggle', [\App\Http\Controllers\Admin\PaymentController::class, 'toggleMethod'])->name('methods.toggle');
            Route::post('/methods/{id}/sort', [\App\Http\Controllers\Admin\PaymentController::class, 'updateMethodSort'])->name('methods.sort');
            Route::get('/slips', [\App\Http\Controllers\Admin\PaymentController::class, 'slips'])->name('slips');
            // Bulk actions must come before parameterized routes to avoid route conflicts
            Route::post('/slips/bulk-approve', [\App\Http\Controllers\Admin\PaymentController::class, 'bulkApprove'])->name('slips.bulk-approve');
            Route::post('/slips/bulk-reject', [\App\Http\Controllers\Admin\PaymentController::class, 'bulkReject'])->name('slips.bulk-reject');
            Route::post('/slips/{id}/approve', [\App\Http\Controllers\Admin\PaymentController::class, 'approveSlip'])->name('slips.approve');
            Route::post('/slips/{id}/reject', [\App\Http\Controllers\Admin\PaymentController::class, 'rejectSlip'])->name('slips.reject');
            Route::get('/banks', [\App\Http\Controllers\Admin\PaymentController::class, 'banks'])->name('banks');
            Route::post('/banks', [\App\Http\Controllers\Admin\PaymentController::class, 'storeBank'])->name('banks.store');
            Route::put('/banks/{id}', [\App\Http\Controllers\Admin\PaymentController::class, 'updateBank'])->name('banks.update');
            Route::delete('/banks/{id}', [\App\Http\Controllers\Admin\PaymentController::class, 'destroyBank'])->name('banks.destroy');
            Route::post('/slips/settings', [\App\Http\Controllers\Admin\PaymentController::class, 'updateSlipSettings'])->name('slips.settings');
            Route::get('/payouts', [\App\Http\Controllers\Admin\PaymentController::class, 'payouts'])->name('payouts');
            // Bulk route before parameterized route to avoid conflicts
            Route::post('/payouts/bulk-mark-paid', [\App\Http\Controllers\Admin\PaymentController::class, 'bulkMarkPaid'])->name('payouts.bulk-mark-paid');
            Route::post('/payouts/{id}/mark-paid', [\App\Http\Controllers\Admin\PaymentController::class, 'markPayoutPaid'])->name('payouts.mark-paid');

            // Automatic payout engine — settings + run-now trigger.
            // Lives beside the manual payouts page above so admins have both
            // "review individual rows" and "tune the auto-payout rules" in
            // one neighbourhood of the admin panel.
            Route::get('/payouts/automation', [\App\Http\Controllers\Admin\PayoutSettingsController::class, 'index'])->name('payouts.automation');
            Route::post('/payouts/automation', [\App\Http\Controllers\Admin\PayoutSettingsController::class, 'index'])->name('payouts.automation.save');
            Route::post('/payouts/automation/run-now', [\App\Http\Controllers\Admin\PayoutSettingsController::class, 'runNow'])->name('payouts.automation.run-now');
        });

        // Tax & Costs
        Route::get('tax', [\App\Http\Controllers\Admin\TaxController::class, 'index'])->name('tax.index');
        Route::post('tax/vat-settings', [\App\Http\Controllers\Admin\TaxController::class, 'updateVatSettings'])->name('tax.vat-settings');
        Route::get('tax/costs', [\App\Http\Controllers\Admin\TaxController::class, 'costs'])->name('tax.costs');

        // Finance
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\FinanceController::class, 'index'])->name('index');
            Route::get('/transactions', [\App\Http\Controllers\Admin\FinanceController::class, 'transactions'])->name('transactions');
            Route::get('/reports', [\App\Http\Controllers\Admin\FinanceController::class, 'reports'])->name('reports');
            Route::get('/reconciliation', [\App\Http\Controllers\Admin\FinanceController::class, 'reconciliation'])->name('reconciliation');
            Route::get('/refunds', [\App\Http\Controllers\Admin\FinanceController::class, 'refunds'])->name('refunds');
            Route::post('/refunds/create', [\App\Http\Controllers\Admin\FinanceController::class, 'createRefund'])->name('refunds.create');
            Route::post('/refunds/{id}/process', [\App\Http\Controllers\Admin\FinanceController::class, 'processRefund'])->name('refunds.process');

            // Cost / Profit analysis (new — daily/monthly/yearly P&L)
            Route::get('/cost-analysis', [\App\Http\Controllers\Admin\CostAnalysisController::class, 'costAnalysis'])->name('cost-analysis');
            Route::post('/cost-analysis/rates', [\App\Http\Controllers\Admin\CostAnalysisController::class, 'updateRates'])->name('cost-analysis.rates');
            // Per-plan profitability — popular plans, profit per plan
            Route::get('/plan-profit', [\App\Http\Controllers\Admin\CostAnalysisController::class, 'planProfit'])->name('plan-profit');
        });

        // Refund Requests (customer-initiated)
        Route::prefix('refunds')->name('refunds.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\RefundRequestController::class, 'index'])->name('index');
            Route::get('/{refund}', [\App\Http\Controllers\Admin\RefundRequestController::class, 'show'])->name('show');
            Route::post('/{refund}/review', [\App\Http\Controllers\Admin\RefundRequestController::class, 'markReview'])->name('mark-review');
            Route::post('/{refund}/approve', [\App\Http\Controllers\Admin\RefundRequestController::class, 'approve'])->name('approve');
            Route::post('/{refund}/reject', [\App\Http\Controllers\Admin\RefundRequestController::class, 'reject'])->name('reject');
        });

        // Invoices
        Route::get('invoices', [\App\Http\Controllers\Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{order}', [\App\Http\Controllers\Admin\InvoiceController::class, 'show'])->name('invoices.show');

        // Pricing
        Route::resource('pricing', \App\Http\Controllers\Admin\PricingController::class)->except(['show']);

        // Packages
        Route::prefix('packages')->name('packages.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PackageController::class, 'index'])->name('index');
            // Audit log — append-only forensic trail of every pricing
            // change. Read-only by design (no mutation actions exposed).
            Route::get('/audit', [\App\Http\Controllers\Admin\PricingAuditController::class, 'index'])->name('audit');
            // Smart-pricing endpoints — read-only calc + bulk recompute.
            // The `calculate-price` GET feeds the modal's "auto" button;
            // `recalculate-all` POST sweeps every event-bound package
            // through SmartPricingService::computeBundlePrice.
            Route::get('/calculate-price', [\App\Http\Controllers\Admin\PackageController::class, 'calculatePrice'])->name('calculate-price');
            Route::post('/recalculate-all', [\App\Http\Controllers\Admin\PackageController::class, 'recalculateAll'])->name('recalculate-all');
            Route::post('/', [\App\Http\Controllers\Admin\PackageController::class, 'store'])->name('store');
            Route::put('/{id}', [\App\Http\Controllers\Admin\PackageController::class, 'update'])->name('update');
            Route::delete('/{id}', [\App\Http\Controllers\Admin\PackageController::class, 'destroy'])->name('destroy');
        });

        // ── pSEO (Programmatic SEO) — landing-page generator ────────────
        // Templates drive bulk auto-generation; pages are individually
        // editable + lockable. See app/Services/Seo/PSeoService.php.
        Route::prefix('pseo')->name('pseo.')->group(function () {
            Route::get('/',  [\App\Http\Controllers\Admin\PSeoController::class, 'index'])->name('index');
            Route::get('/pages', [\App\Http\Controllers\Admin\PSeoController::class, 'pages'])->name('pages');
            Route::get('/pages/{page}/edit', [\App\Http\Controllers\Admin\PSeoController::class, 'pageEdit'])->name('page-edit');
            Route::put('/pages/{page}', [\App\Http\Controllers\Admin\PSeoController::class, 'pageUpdate'])->name('page-update');
            Route::delete('/pages/{page}', [\App\Http\Controllers\Admin\PSeoController::class, 'pageDestroy'])->name('page-destroy');
            Route::get('/templates/{template}/edit', [\App\Http\Controllers\Admin\PSeoController::class, 'templateEdit'])->name('template-edit');
            Route::put('/templates/{template}', [\App\Http\Controllers\Admin\PSeoController::class, 'templateUpdate'])->name('template-update');
            Route::post('/templates/{template}/toggle', [\App\Http\Controllers\Admin\PSeoController::class, 'templateToggle'])->name('template-toggle');
            Route::post('/templates/{template}/regenerate', [\App\Http\Controllers\Admin\PSeoController::class, 'regenerateTemplate'])->name('regenerate-template');
            Route::post('/regenerate-all', [\App\Http\Controllers\Admin\PSeoController::class, 'regenerateAll'])->name('regenerate-all');
        });

        // Products
        Route::resource('products', \App\Http\Controllers\Admin\ProductController::class);

        // Digital Orders
        Route::get('digital-orders', [\App\Http\Controllers\Admin\DigitalOrderController::class, 'index'])->name('digital-orders.index');
        Route::post('digital-orders/{id}/approve', [\App\Http\Controllers\Admin\DigitalOrderController::class, 'approve'])->name('digital-orders.approve');
        Route::post('digital-orders/{id}/reject', [\App\Http\Controllers\Admin\DigitalOrderController::class, 'reject'])->name('digital-orders.reject');

        // Image Moderation — AI scan of uploaded photos
        Route::prefix('moderation')->name('moderation.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ModerationController::class, 'index'])->name('index');
            Route::post('/bulk', [\App\Http\Controllers\Admin\ModerationController::class, 'bulkAction'])->name('bulk');
            Route::get('/{id}', [\App\Http\Controllers\Admin\ModerationController::class, 'show'])->name('show')->whereNumber('id');
            Route::post('/{id}/approve', [\App\Http\Controllers\Admin\ModerationController::class, 'approve'])->name('approve')->whereNumber('id');
            Route::post('/{id}/reject',  [\App\Http\Controllers\Admin\ModerationController::class, 'reject'])->name('reject')->whereNumber('id');
            Route::post('/{id}/skip',    [\App\Http\Controllers\Admin\ModerationController::class, 'skip'])->name('skip')->whereNumber('id');
            Route::post('/{id}/rescan',  [\App\Http\Controllers\Admin\ModerationController::class, 'rescan'])->name('rescan')->whereNumber('id');
        });

        // Reviews — Full moderation
        // ── Admin Bookings oversight ────────────────────────────────
        Route::prefix('bookings')->name('bookings.')->group(function () {
            Route::get('/',                           [\App\Http\Controllers\Admin\BookingController::class, 'index'])->name('index');
            Route::get('/{booking}',                  [\App\Http\Controllers\Admin\BookingController::class, 'show'])->name('show');
            Route::post('/{booking}/cancel',          [\App\Http\Controllers\Admin\BookingController::class, 'cancel'])->name('cancel');
            Route::post('/{booking}/no-show',         [\App\Http\Controllers\Admin\BookingController::class, 'markNoShow'])->name('no-show');
            Route::post('/{booking}/note',            [\App\Http\Controllers\Admin\BookingController::class, 'updateNote'])->name('note');
        });

        Route::prefix('reviews')->name('reviews.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('index');
            Route::get('/reports', [\App\Http\Controllers\Admin\ReviewController::class, 'reports'])->name('reports');
            Route::post('/reports/{report}/resolve', [\App\Http\Controllers\Admin\ReviewController::class, 'resolveReport'])->name('reports.resolve');
            Route::post('/bulk-action', [\App\Http\Controllers\Admin\ReviewController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/{review}', [\App\Http\Controllers\Admin\ReviewController::class, 'show'])->name('show');
            Route::post('/{review}/approve', [\App\Http\Controllers\Admin\ReviewController::class, 'approve'])->name('approve');
            Route::post('/{review}/hide', [\App\Http\Controllers\Admin\ReviewController::class, 'hide'])->name('hide');
            Route::post('/{review}/reject', [\App\Http\Controllers\Admin\ReviewController::class, 'reject'])->name('reject');
            Route::post('/{review}/flag', [\App\Http\Controllers\Admin\ReviewController::class, 'toggleFlag'])->name('flag');
            Route::post('/{review}/reply', [\App\Http\Controllers\Admin\ReviewController::class, 'reply'])->name('reply');
            Route::delete('/{review}', [\App\Http\Controllers\Admin\ReviewController::class, 'destroy'])->name('destroy');
        });

        // Monetization — Brand Ads + Photographer Promotions revenue.
        // Distinct routes from Subscriptions/Coupons/GiftCards which handle
        // platform's OWN paid tiers; this section is for selling ads + boost
        // slots to brands and photographers as a separate revenue stream.
        Route::prefix('monetization')->name('monetization.')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\Admin\MonetizationController::class, 'dashboard'])->name('dashboard');
            Route::get('/campaigns',           [\App\Http\Controllers\Admin\MonetizationController::class, 'campaignsIndex'])->name('campaigns.index');
            Route::get('/campaigns/create',    [\App\Http\Controllers\Admin\MonetizationController::class, 'campaignsCreate'])->name('campaigns.create');
            Route::post('/campaigns',          [\App\Http\Controllers\Admin\MonetizationController::class, 'campaignsStore'])->name('campaigns.store');
            Route::get('/campaigns/{campaign}', [\App\Http\Controllers\Admin\MonetizationController::class, 'campaignsShow'])->name('campaigns.show');
            Route::post('/campaigns/{campaign}/{action}', [\App\Http\Controllers\Admin\MonetizationController::class, 'campaignToggle'])
                ->where('action', 'active|paused|ended')->name('campaigns.toggle');

            // Creative CRUD nested under campaign — image upload happens here.
            // Files land at R2 system/ad_creative/user_0/campaign_{id}/...
            Route::get('/campaigns/{campaign}/creatives/create', [\App\Http\Controllers\Admin\MonetizationController::class, 'creativesCreate'])
                ->name('campaigns.creatives.create');
            Route::post('/campaigns/{campaign}/creatives',       [\App\Http\Controllers\Admin\MonetizationController::class, 'creativesStore'])
                ->name('campaigns.creatives.store');
            Route::get('/campaigns/{campaign}/creatives/{creative}/edit', [\App\Http\Controllers\Admin\MonetizationController::class, 'creativesEdit'])
                ->name('campaigns.creatives.edit');
            Route::patch('/campaigns/{campaign}/creatives/{creative}',    [\App\Http\Controllers\Admin\MonetizationController::class, 'creativesUpdate'])
                ->name('campaigns.creatives.update');
            Route::delete('/campaigns/{campaign}/creatives/{creative}',   [\App\Http\Controllers\Admin\MonetizationController::class, 'creativesDestroy'])
                ->name('campaigns.creatives.destroy');

            Route::get('/promotions',          [\App\Http\Controllers\Admin\MonetizationController::class, 'promotions'])->name('promotions');

            // Photographer promotion CRUD — admin can edit/cancel/refund
            // any active promotion (incident response: stop a boost
            // that shouldn't have started, comp a refund, etc.)
            Route::get('/promotions/{promotion}/edit',  [\App\Http\Controllers\Admin\MonetizationController::class, 'promotionEdit'])->name('promotions.edit');
            Route::put('/promotions/{promotion}',       [\App\Http\Controllers\Admin\MonetizationController::class, 'promotionUpdate'])->name('promotions.update');
            Route::post('/promotions/{promotion}/cancel',  [\App\Http\Controllers\Admin\MonetizationController::class, 'promotionCancel'])->name('promotions.cancel');
            Route::post('/promotions/{promotion}/refund',  [\App\Http\Controllers\Admin\MonetizationController::class, 'promotionRefund'])->name('promotions.refund');

            // Addon catalog CRUD — DB-backed catalog (table addon_items)
            // that AddonService::catalog() reads from. Edits invalidate
            // the cache via model events so changes are live without a
            // deploy.
            Route::prefix('addons')->name('addons.')->group(function () {
                Route::get('/',              [\App\Http\Controllers\Admin\AddonItemController::class, 'index'])->name('index');
                Route::get('/create',        [\App\Http\Controllers\Admin\AddonItemController::class, 'create'])->name('create');
                Route::post('/',             [\App\Http\Controllers\Admin\AddonItemController::class, 'store'])->name('store');
                Route::get('/{id}/edit',     [\App\Http\Controllers\Admin\AddonItemController::class, 'edit'])->name('edit');
                Route::put('/{id}',          [\App\Http\Controllers\Admin\AddonItemController::class, 'update'])->name('update');
                Route::post('/{id}/toggle',  [\App\Http\Controllers\Admin\AddonItemController::class, 'toggle'])->name('toggle');
                Route::delete('/{id}',       [\App\Http\Controllers\Admin\AddonItemController::class, 'destroy'])->name('destroy');
            });
        });

        // SEO Management — per-page override CMS (CRUD + bulk + history).
        // Distinct from /admin/settings/seo (site-wide defaults) and
        // /admin/settings/seo/analyzer (read-only audit). This handles
        // per-route overrides backed by the seo_pages table.
        Route::prefix('seo')->name('seo.')->group(function () {
            Route::get('/',                       [\App\Http\Controllers\Admin\SeoManagementController::class, 'index'])->name('index');
            Route::get('/audit',                  [\App\Http\Controllers\Admin\SeoManagementController::class, 'audit'])->name('audit');
            Route::get('/create',                 [\App\Http\Controllers\Admin\SeoManagementController::class, 'create'])->name('create');
            Route::post('/',                      [\App\Http\Controllers\Admin\SeoManagementController::class, 'store'])->name('store');
            Route::post('/bulk',                  [\App\Http\Controllers\Admin\SeoManagementController::class, 'bulkUpdate'])->name('bulk');
            Route::get('/{seoPage}',              [\App\Http\Controllers\Admin\SeoManagementController::class, 'show'])->name('show');
            Route::get('/{seoPage}/edit',         [\App\Http\Controllers\Admin\SeoManagementController::class, 'edit'])->name('edit');
            Route::patch('/{seoPage}',            [\App\Http\Controllers\Admin\SeoManagementController::class, 'update'])->name('update');
            Route::delete('/{seoPage}',           [\App\Http\Controllers\Admin\SeoManagementController::class, 'destroy'])->name('destroy');
            Route::post('/{seoPage}/restore/{revisionId}', [\App\Http\Controllers\Admin\SeoManagementController::class, 'rollback'])
                ->name('rollback')->where('revisionId', '\d+');
        });

        // API Keys Management — DEPRECATED for MVP.
        // Gated by `feature.global:api_access` (default OFF). Flip to '1'
        // in Admin → Feature Flags to restore the admin UI.
        Route::prefix('api-keys')->name('api-keys.')->middleware('feature.global:api_access')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ApiKeyController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Admin\ApiKeyController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Admin\ApiKeyController::class, 'store'])->name('store');
            Route::get('/{apiKey}', [\App\Http\Controllers\Admin\ApiKeyController::class, 'show'])->name('show');
            Route::post('/{apiKey}/revoke', [\App\Http\Controllers\Admin\ApiKeyController::class, 'revoke'])->name('revoke');
            Route::post('/{apiKey}/reactivate', [\App\Http\Controllers\Admin\ApiKeyController::class, 'reactivate'])->name('reactivate');
            Route::delete('/{apiKey}', [\App\Http\Controllers\Admin\ApiKeyController::class, 'destroy'])->name('destroy');
        });

        // Coupons (specific routes must come before resource to avoid conflict with {coupon})
        Route::get('coupons/dashboard', [\App\Http\Controllers\Admin\CouponController::class, 'dashboard'])->name('coupons.dashboard');
        Route::get('coupons/bulk-create', [\App\Http\Controllers\Admin\CouponController::class, 'bulkCreate'])->name('coupons.bulk-create');
        Route::post('coupons/bulk-store', [\App\Http\Controllers\Admin\CouponController::class, 'bulkStore'])->name('coupons.bulk-store');
        Route::get('coupons/export', [\App\Http\Controllers\Admin\CouponController::class, 'exportCsv'])->name('coupons.export');
        Route::resource('coupons', \App\Http\Controllers\Admin\CouponController::class);

        // Business Expense Tracking + Calculator
        Route::get('business-expenses/calculator', [\App\Http\Controllers\Admin\BusinessExpenseController::class, 'calculator'])
            ->name('business-expenses.calculator');
        Route::resource('business-expenses', \App\Http\Controllers\Admin\BusinessExpenseController::class)
            ->parameters(['business-expenses' => 'expense']);

        // Messages
        Route::prefix('messages')->name('messages.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\MessageController::class, 'index'])->name('index');
            Route::post('/bulk-action', [\App\Http\Controllers\Admin\MessageController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/{message}', [\App\Http\Controllers\Admin\MessageController::class, 'show'])->name('show');
            Route::post('/{message}/reply', [\App\Http\Controllers\Admin\MessageController::class, 'reply'])->name('reply');
            Route::post('/{message}/assign', [\App\Http\Controllers\Admin\MessageController::class, 'assign'])->name('assign');
            Route::post('/{message}/priority', [\App\Http\Controllers\Admin\MessageController::class, 'updatePriority'])->name('update-priority');
            Route::post('/{message}/category', [\App\Http\Controllers\Admin\MessageController::class, 'updateCategory'])->name('update-category');
            Route::post('/{message}/status', [\App\Http\Controllers\Admin\MessageController::class, 'updateStatus'])->name('update-status');
            Route::delete('/{message}', [\App\Http\Controllers\Admin\MessageController::class, 'destroy'])->name('destroy');
        });

        // Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            // Dual-mode: GET shows the form, POST saves via SettingsController::saveSettings()
            // (index() internally dispatches based on $request->isMethod('post'))
            Route::match(['GET', 'POST'], '/', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('index');
            Route::get('/general', [\App\Http\Controllers\Admin\SettingsController::class, 'general'])->name('general');
            Route::post('/general', [\App\Http\Controllers\Admin\SettingsController::class, 'updateGeneral'])->name('general.update');
            Route::get('/security', [\App\Http\Controllers\Admin\SettingsController::class, 'security'])->name('security');
            Route::post('/idle-timeout', [\App\Http\Controllers\Admin\SettingsController::class, 'updateIdleTimeout'])->name('idle-timeout.update');
            Route::get('/performance', [\App\Http\Controllers\Admin\SettingsController::class, 'performance'])->name('performance');
            Route::post('/performance/clear-cache', [\App\Http\Controllers\Admin\SettingsController::class, 'clearCache'])->name('performance.clear-cache');
            Route::post('/performance/update', [\App\Http\Controllers\Admin\SettingsController::class, 'updatePerformance'])->name('performance.update');

            // Retention Policy — auto-delete expired events
            Route::get('/retention', [\App\Http\Controllers\Admin\SettingsController::class, 'retention'])->name('retention');
            Route::post('/retention', [\App\Http\Controllers\Admin\SettingsController::class, 'updateRetention'])->name('retention.update');
            Route::get('/retention/preview', [\App\Http\Controllers\Admin\SettingsController::class, 'previewRetention'])->name('retention.preview');

            // Per-photographer storage quota (Part A — tier-based quotas)
            Route::get('/photographer-storage', [\App\Http\Controllers\Admin\SettingsController::class, 'photographerStorage'])->name('photographer-storage');
            Route::post('/photographer-storage', [\App\Http\Controllers\Admin\SettingsController::class, 'updatePhotographerStorage'])->name('photographer-storage.update');
            Route::post('/photographer-storage/recalc', [\App\Http\Controllers\Admin\SettingsController::class, 'recalcPhotographerStorage'])->name('photographer-storage.recalc');

            // Multi-driver Storage (R2 / S3 / Drive orchestration)
            Route::get('/storage', [\App\Http\Controllers\Admin\SettingsController::class, 'storage'])->name('storage');
            Route::post('/storage', [\App\Http\Controllers\Admin\SettingsController::class, 'updateStorage'])->name('storage.update');
            Route::post('/storage/probe', [\App\Http\Controllers\Admin\SettingsController::class, 'probeStorage'])->name('storage.probe');

            // Storage credential tester — PUT / GET / DELETE a small test object
            // and surface the full error (AWS error code, HTTP status, previous
            // exception chain) instead of the generic "upload failed" we'd get
            // from the normal upload path.
            Route::get('/storage/test', [\App\Http\Controllers\Admin\SettingsController::class, 'storageTest'])->name('storage.test');
            Route::post('/storage/test/run', [\App\Http\Controllers\Admin\SettingsController::class, 'runStorageTest'])->name('storage.test.run');

            Route::get('/backup', [\App\Http\Controllers\Admin\SettingsController::class, 'backup'])->name('backup');
            Route::post('/backup/database', [\App\Http\Controllers\Admin\SettingsController::class, 'backupDatabase'])->name('backup.database');
            Route::post('/backup/files', [\App\Http\Controllers\Admin\SettingsController::class, 'backupFiles'])->name('backup.files');
            Route::post('/backup/full', [\App\Http\Controllers\Admin\SettingsController::class, 'backupFull'])->name('backup.full');
            Route::get('/backup/download/{filename}', [\App\Http\Controllers\Admin\SettingsController::class, 'backupDownload'])
                ->where('filename', '[A-Za-z0-9._\-]+')
                ->name('backup.download');
            Route::delete('/backup/{filename}', [\App\Http\Controllers\Admin\SettingsController::class, 'backupDelete'])
                ->where('filename', '[A-Za-z0-9._\-]+')
                ->name('backup.delete');

            // SEO Settings
            Route::get('/seo', [\App\Http\Controllers\Admin\SettingsController::class, 'seo'])->name('seo');
            Route::post('/seo', [\App\Http\Controllers\Admin\SettingsController::class, 'updateSeo'])->name('seo.update');
            Route::get('/seo/analyzer', [\App\Http\Controllers\Admin\SeoAnalyzerController::class, 'index'])->name('seo.analyzer');
            Route::post('/seo/refresh-sitemap', [\App\Http\Controllers\Admin\SeoAnalyzerController::class, 'refreshSitemap'])->name('seo.refresh-sitemap');

            // Email Logs
            Route::get('/email-logs', [\App\Http\Controllers\Admin\SettingsController::class, 'emailLogs'])->name('email-logs');

            // Queue Management
            Route::get('/queue', [\App\Http\Controllers\Admin\SettingsController::class, 'queue'])->name('queue');
            Route::post('/queue', [\App\Http\Controllers\Admin\SettingsController::class, 'updateQueue'])->name('queue.update');
            Route::post('/queue/process', [\App\Http\Controllers\Admin\SettingsController::class, 'processQueue'])->name('queue.process');
            Route::post('/queue/retry/{id}', [\App\Http\Controllers\Admin\SettingsController::class, 'retryJob'])->name('queue.retry');
            Route::post('/queue/clear', [\App\Http\Controllers\Admin\SettingsController::class, 'clearQueue'])->name('queue.clear');

            // Settings Guide
            Route::get('/guide', [\App\Http\Controllers\Admin\SettingsController::class, 'guide'])->name('guide');

            // Version Info
            Route::get('/version', [\App\Http\Controllers\Admin\SettingsController::class, 'version'])->name('version');
            Route::post('/version', [\App\Http\Controllers\Admin\SettingsController::class, 'recordVersion'])->name('version.record');

            // System Reset
            Route::get('/reset', [\App\Http\Controllers\Admin\SettingsController::class, 'reset'])->name('reset');
            Route::post('/reset', [\App\Http\Controllers\Admin\SettingsController::class, 'performReset'])->name('reset.perform');

            // Source Protection
            Route::get('/source-protection', [\App\Http\Controllers\Admin\SettingsController::class, 'sourceProtection'])->name('source-protection');
            Route::post('/source-protection', [\App\Http\Controllers\Admin\SettingsController::class, 'updateSourceProtection'])->name('source-protection.update');

            // Proxy Shield
            Route::get('/proxy-shield', [\App\Http\Controllers\Admin\SettingsController::class, 'proxyShield'])->name('proxy-shield');
            Route::post('/proxy-shield', [\App\Http\Controllers\Admin\SettingsController::class, 'updateProxyShield'])->name('proxy-shield.update');

            // Cloudflare
            Route::get('/cloudflare', [\App\Http\Controllers\Admin\SettingsController::class, 'cloudflare'])->name('cloudflare');
            Route::post('/cloudflare', [\App\Http\Controllers\Admin\SettingsController::class, 'updateCloudflare'])->name('cloudflare.update');

            // Watermark Settings
            Route::get('/watermark', [\App\Http\Controllers\Admin\SettingsController::class, 'watermark'])->name('watermark');
            Route::post('/watermark', [\App\Http\Controllers\Admin\SettingsController::class, 'updateWatermark'])->name('watermark.update');

            // Image Processing Settings
            Route::get('/image', [\App\Http\Controllers\Admin\SettingsController::class, 'image'])->name('image');
            Route::post('/image', [\App\Http\Controllers\Admin\SettingsController::class, 'updateImage'])->name('image.update');

            // Photo Performance — event-gallery upload/compression/delivery
            Route::get('/photo-performance',  [\App\Http\Controllers\Admin\SettingsController::class, 'photoPerformance'])->name('photo-performance');
            Route::post('/photo-performance', [\App\Http\Controllers\Admin\SettingsController::class, 'updatePhotoPerformance'])->name('photo-performance.update');

            // Language Settings
            Route::get('/language', [\App\Http\Controllers\Admin\SettingsController::class, 'language'])->name('language');
            Route::post('/language', [\App\Http\Controllers\Admin\SettingsController::class, 'updateLanguage'])->name('language.update');

            // LINE Settings
            Route::get('/line', [\App\Http\Controllers\Admin\SettingsController::class, 'line'])->name('line');
            Route::post('/line', [\App\Http\Controllers\Admin\SettingsController::class, 'updateLine'])->name('line.update');

            // LINE Messaging Test Console — verify token + delivery end-to-end
            // before relying on it in production. See LineMessagingTestController
            // for what each endpoint does.
            Route::get('/line-test',                  [\App\Http\Controllers\Admin\LineMessagingTestController::class, 'index'])->name('line-test');
            Route::get('/line-test/diagnostics',      [\App\Http\Controllers\Admin\LineMessagingTestController::class, 'diagnostics'])->name('line-test.diagnostics');
            Route::post('/line-test/send-text',       [\App\Http\Controllers\Admin\LineMessagingTestController::class, 'sendText'])->name('line-test.send-text');
            Route::post('/line-test/send-photo',      [\App\Http\Controllers\Admin\LineMessagingTestController::class, 'sendPhoto'])->name('line-test.send-photo');
            Route::post('/line-test/replay-order',    [\App\Http\Controllers\Admin\LineMessagingTestController::class, 'replayOrder'])->name('line-test.replay-order');

            // LINE OA Rich Menu — manage the in-chat menu (6 buttons, default for all followers).
            // Calls api.line.me + api-data.line.me using the same channel access token.
            Route::get('/line/richmenu',                  [\App\Http\Controllers\Admin\LineRichMenuController::class, 'index'])->name('line-richmenu');
            Route::post('/line/richmenu/deploy',          [\App\Http\Controllers\Admin\LineRichMenuController::class, 'deploy'])->name('line-richmenu.deploy');
            Route::post('/line/richmenu/set-default',     [\App\Http\Controllers\Admin\LineRichMenuController::class, 'setDefault'])->name('line-richmenu.set-default');
            Route::post('/line/richmenu/clear-default',   [\App\Http\Controllers\Admin\LineRichMenuController::class, 'clearDefault'])->name('line-richmenu.clear');
            Route::post('/line/richmenu/delete',          [\App\Http\Controllers\Admin\LineRichMenuController::class, 'destroy'])->name('line-richmenu.delete');
        });

        // Re-open the settings group so subsequent settings.* routes register correctly.
        // (This brace pair compensates for the closing brace we opened above.)
        Route::prefix('settings')->name('settings.')->group(function () {
            // intentionally empty — this prefix/name group will be picked up by
            // any future settings.* additions placed below.

            // Photo Delivery Settings (web / LINE / email routing after payment)
            Route::get('/delivery', [\App\Http\Controllers\Admin\SettingsController::class, 'delivery'])->name('delivery');
            Route::post('/delivery', [\App\Http\Controllers\Admin\SettingsController::class, 'updateDelivery'])->name('delivery.update');

            // Social Login & Registration
            Route::get('/social-auth', [\App\Http\Controllers\Admin\SocialAuthSettingsController::class, 'index'])->name('social-auth');
            Route::post('/social-auth', [\App\Http\Controllers\Admin\SocialAuthSettingsController::class, 'update'])->name('social-auth.update');

            // Webhook Monitor
            Route::get('/webhooks', [\App\Http\Controllers\Admin\SettingsController::class, 'webhooks'])->name('webhooks');

            // Image Moderation Settings — controls the auto-scan rules that
            // feed /admin/moderation. Thresholds are safe to tweak live since
            // ModeratePhotoJob reads them per run.
            Route::get('/moderation', [\App\Http\Controllers\Admin\SettingsController::class, 'moderation'])->name('moderation');
            Route::post('/moderation', [\App\Http\Controllers\Admin\SettingsController::class, 'updateModeration'])->name('moderation.update');

            // Face Search — cost / abuse controls (daily caps, monthly
            // ceiling, fallback photo cap, result cache TTL, kill switch)
            // plus live usage dashboard. Every control reads from AppSetting
            // at request time so changes take effect without a deploy.
            Route::get('/face-search',         [\App\Http\Controllers\Admin\SettingsController::class, 'faceSearch'])->name('face-search');
            Route::post('/face-search',        [\App\Http\Controllers\Admin\SettingsController::class, 'updateFaceSearch'])->name('face-search.update');
            Route::get('/face-search/usage',   [\App\Http\Controllers\Admin\SettingsController::class, 'faceSearchUsage'])->name('face-search.usage');

            // Google Drive Settings
            Route::get('/google-drive', [\App\Http\Controllers\Admin\SettingsController::class, 'googleDrive'])->name('google-drive');
            Route::post('/google-drive', [\App\Http\Controllers\Admin\SettingsController::class, 'updateGoogleDrive'])->name('google-drive.update');

            // AWS Cloud Settings
            Route::get('/aws', [\App\Http\Controllers\Admin\SettingsController::class, 'aws'])->name('aws');
            Route::post('/aws', [\App\Http\Controllers\Admin\SettingsController::class, 'updateAws'])->name('aws.update');

            // Cloudflare R2 Object Storage — dedicated page (separate from
            // /admin/settings/cloudflare which handles CDN + zone API).
            Route::get('/r2', [\App\Http\Controllers\Admin\SettingsController::class, 'r2'])->name('r2');
            Route::post('/r2', [\App\Http\Controllers\Admin\SettingsController::class, 'updateR2'])->name('r2.update');

            // Payment Gateway Credentials
            Route::get('/payment-gateways', [\App\Http\Controllers\Admin\SettingsController::class, 'paymentGateways'])->name('payment-gateways');
            Route::post('/payment-gateways', [\App\Http\Controllers\Admin\SettingsController::class, 'updatePaymentGateways'])->name('payment-gateways.update');

            // Analytics & Social Settings
            Route::get('/analytics', [\App\Http\Controllers\Admin\SettingsController::class, 'analytics'])->name('analytics');
            Route::post('/analytics', [\App\Http\Controllers\Admin\SettingsController::class, 'updateAnalytics'])->name('analytics.update');
        });

        // Legal Pages CMS — Privacy / Terms / Refund + version history
        Route::prefix('legal')->name('legal.')->group(function () {
            Route::get('/',                        [\App\Http\Controllers\Admin\LegalPageController::class, 'index'])->name('index');
            Route::get('/create',                  [\App\Http\Controllers\Admin\LegalPageController::class, 'create'])->name('create');
            Route::post('/',                       [\App\Http\Controllers\Admin\LegalPageController::class, 'store'])->name('store');
            Route::get('/{legal}/edit',            [\App\Http\Controllers\Admin\LegalPageController::class, 'edit'])->name('edit');
            Route::put('/{legal}',                 [\App\Http\Controllers\Admin\LegalPageController::class, 'update'])->name('update');
            Route::post('/{legal}/toggle-publish', [\App\Http\Controllers\Admin\LegalPageController::class, 'togglePublish'])->name('toggle-publish');
            Route::delete('/{legal}',              [\App\Http\Controllers\Admin\LegalPageController::class, 'destroy'])->name('destroy');
            // Version history
            Route::get('/{legal}/history',                     [\App\Http\Controllers\Admin\LegalPageController::class, 'history'])->name('history');
            Route::get('/{legal}/versions/{version}',          [\App\Http\Controllers\Admin\LegalPageController::class, 'showVersion'])->name('versions.show');
            Route::post('/{legal}/versions/{version}/restore', [\App\Http\Controllers\Admin\LegalPageController::class, 'restoreVersion'])->name('versions.restore');
        });

        // Email test API
        Route::post('/api/admin/email-test', [\App\Http\Controllers\Api\AdminApiController::class, 'emailTest'])->name('api.admin.email-test');

        // Manual (removed per request)

        // Activity Log
        Route::get('/activity-log', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity-log');

        // 2FA Settings
        // Opt out of `admin.2fa.setup` — an admin without 2FA must be able to
        // reach these routes to enrol. Otherwise RequireTwoFactorSetup would
        // redirect them to settings.2fa which itself would redirect… (loop).
        Route::get('/settings/2fa', [\App\Http\Controllers\Admin\SettingsController::class, 'twoFactor'])
            ->name('settings.2fa')->withoutMiddleware('admin.2fa.setup');
        Route::post('/settings/2fa/enable', [\App\Http\Controllers\Admin\SettingsController::class, 'enable2fa'])
            ->name('settings.2fa.enable')->withoutMiddleware('admin.2fa.setup');
        Route::post('/settings/2fa/verify', [\App\Http\Controllers\Admin\SettingsController::class, 'verify2fa'])
            ->name('settings.2fa.verify')->withoutMiddleware('admin.2fa.setup');
        Route::post('/settings/2fa/disable', [\App\Http\Controllers\Admin\SettingsController::class, 'disable2fa'])
            ->name('settings.2fa.disable');
        // Toggle the global "force every admin to enrol in 2FA" switch.
        // Opts out of admin.2fa.setup so an admin without 2FA can still
        // reach the form to turn enforcement OFF (otherwise they'd be
        // redirected to setup before they could flip the toggle).
        Route::post('/settings/2fa/enforcement', [\App\Http\Controllers\Admin\SettingsController::class, 'updateEnforcement'])
            ->name('settings.2fa.enforcement')->withoutMiddleware('admin.2fa.setup');

        // Security Dashboard
        Route::get('/security', [\App\Http\Controllers\Admin\SecurityController::class, 'dashboard'])->name('security.dashboard');
        Route::post('/security/scan', [\App\Http\Controllers\Admin\SecurityController::class, 'scan'])->name('security.scan');
        Route::post('/security/block-ip', [\App\Http\Controllers\Admin\SecurityController::class, 'blockIp'])->name('security.block-ip');
        Route::post('/security/unblock-ip', [\App\Http\Controllers\Admin\SecurityController::class, 'unblockIp'])->name('security.unblock-ip');

        // ═══ Threat Intelligence ═══
        Route::prefix('security/threat-intelligence')->name('security.threat-intelligence.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Admin\ThreatIntelligenceController::class, 'index'])->name('index');
            Route::post('/incidents/{id}/resolve', [\App\Http\Controllers\Admin\ThreatIntelligenceController::class, 'resolve'])->name('incidents.resolve');
            Route::post('/unblock-fingerprint',    [\App\Http\Controllers\Admin\ThreatIntelligenceController::class, 'unblockFingerprint'])->name('unblock-fingerprint');
            Route::post('/cleanup',          [\App\Http\Controllers\Admin\ThreatIntelligenceController::class, 'cleanup'])->name('cleanup');
        });

        // ═══ Geo Access ═══
        Route::prefix('security/geo-access')->name('security.geo-access.')->group(function () {
            Route::get('/',          [\App\Http\Controllers\Admin\GeoAccessController::class, 'index'])->name('index');
            Route::post('/update',   [\App\Http\Controllers\Admin\GeoAccessController::class, 'update'])->name('update');
            Route::post('/lookup',   [\App\Http\Controllers\Admin\GeoAccessController::class, 'lookup'])->name('lookup');
            Route::post('/purge-cache', [\App\Http\Controllers\Admin\GeoAccessController::class, 'purgeCache'])->name('purge-cache');
        });

        // ═══ Diagnostics ═══
        Route::get('/diagnostics/aws', [\App\Http\Controllers\Admin\DiagnosticsController::class, 'awsRekognition'])->name('diagnostics.aws');
        Route::get('/diagnostics/events/{event}/face-coverage', [\App\Http\Controllers\Admin\DiagnosticsController::class, 'eventFaceCoverage'])->name('diagnostics.event-face-coverage');

        // ═══ System Monitor (storage/server/downloads + production readiness) ═══
        Route::prefix('system')->name('system.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Admin\SystemMonitorController::class, 'dashboard'])->name('dashboard');
            Route::get('/readiness',       [\App\Http\Controllers\Admin\SystemMonitorController::class, 'readiness'])->name('readiness');
            Route::get('/api/snapshot',    [\App\Http\Controllers\Admin\SystemMonitorController::class, 'apiSnapshot'])->name('api.snapshot');
            Route::get('/api/readiness',   [\App\Http\Controllers\Admin\SystemMonitorController::class, 'apiReadiness'])->name('api.readiness');
            Route::post('/refresh',        [\App\Http\Controllers\Admin\SystemMonitorController::class, 'refresh'])->name('refresh');

            // Capacity planner (concurrent-user math + growth + cost-per-user)
            Route::get('/capacity',         [\App\Http\Controllers\Admin\SystemCapacityController::class, 'index'])->name('capacity');
            Route::post('/capacity/refresh',[\App\Http\Controllers\Admin\SystemCapacityController::class, 'refresh'])->name('capacity.refresh');
        });

        // ═══ Photographer Onboarding (wizard review flow) ════════════════
        // Routes live under their own prefix to avoid name collisions with the
        // existing `admin.photographers.*` resource routes.
        Route::prefix('photographer-onboarding')->name('photographer-onboarding.')->group(function () {
            Route::get('/',                               [\App\Http\Controllers\Admin\PhotographerOnboardingController::class, 'index'])->name('index');
            Route::get('/{profile}',                      [\App\Http\Controllers\Admin\PhotographerOnboardingController::class, 'review'])->name('review');
            Route::post('/{profile}/approve',             [\App\Http\Controllers\Admin\PhotographerOnboardingController::class, 'approve'])->name('approve');
            Route::post('/{profile}/reject',              [\App\Http\Controllers\Admin\PhotographerOnboardingController::class, 'reject'])->name('reject');
            Route::post('/{profile}/mark-reviewing',      [\App\Http\Controllers\Admin\PhotographerOnboardingController::class, 'markReviewing'])->name('mark-reviewing');
        });

        // ═══ Event Health Scorecard ═══════════════════════════════════════
        Route::prefix('event-health')->name('event-health.')->group(function () {
            Route::get('/',                 [\App\Http\Controllers\Admin\EventHealthController::class, 'index'])->name('index');
            Route::get('/{event}',          [\App\Http\Controllers\Admin\EventHealthController::class, 'show'])->name('show');
        });

        // ═══ Changelog Manager ════════════════════════════════════════════
        Route::prefix('changelog')->name('changelog.')->group(function () {
            Route::get('/',                     [\App\Http\Controllers\Admin\ChangelogController::class, 'index'])->name('index');
            Route::get('/create',               [\App\Http\Controllers\Admin\ChangelogController::class, 'create'])->name('create');
            Route::post('/',                    [\App\Http\Controllers\Admin\ChangelogController::class, 'store'])->name('store');
            Route::get('/{changelog}/edit',     [\App\Http\Controllers\Admin\ChangelogController::class, 'edit'])->name('edit');
            Route::put('/{changelog}',          [\App\Http\Controllers\Admin\ChangelogController::class, 'update'])->name('update');
            Route::delete('/{changelog}',       [\App\Http\Controllers\Admin\ChangelogController::class, 'destroy'])->name('destroy');
            Route::post('/{changelog}/toggle',  [\App\Http\Controllers\Admin\ChangelogController::class, 'togglePublish'])->name('toggle');
        });

        // ═══ PDPA Data Export ═════════════════════════════════════════════
        Route::prefix('data-export')->name('data-export.')->group(function () {
            Route::get('/',                      [\App\Http\Controllers\Admin\DataExportController::class, 'index'])->name('index');
            Route::get('/{request}',             [\App\Http\Controllers\Admin\DataExportController::class, 'show'])->name('show');
            Route::post('/{request}/process',    [\App\Http\Controllers\Admin\DataExportController::class, 'process'])->name('process');
            Route::post('/{request}/reject',     [\App\Http\Controllers\Admin\DataExportController::class, 'reject'])->name('reject');
            Route::get('/{request}/download',    [\App\Http\Controllers\Admin\DataExportController::class, 'download'])->name('download');
            Route::delete('/{request}',          [\App\Http\Controllers\Admin\DataExportController::class, 'destroy'])->name('destroy');
        });

        // ═══ Unit Economics / LTV ═════════════════════════════════════════
        Route::get('/unit-economics', [\App\Http\Controllers\Admin\UnitEconomicsController::class, 'index'])->name('unit-economics.index');

        // ═══ Scheduler & Queue Health ═════════════════════════════════════
        Route::prefix('scheduler')->name('scheduler.')->group(function () {
            Route::get('/',                        [\App\Http\Controllers\Admin\SchedulerHealthController::class, 'index'])->name('index');
            Route::post('/failed/retry-all',       [\App\Http\Controllers\Admin\SchedulerHealthController::class, 'retryAll'])->name('retry-all');
            Route::post('/failed/flush',           [\App\Http\Controllers\Admin\SchedulerHealthController::class, 'flushFailed'])->name('flush-failed');
            Route::post('/failed/{uuid}/retry',    [\App\Http\Controllers\Admin\SchedulerHealthController::class, 'retry'])->name('retry');
            Route::delete('/failed/{uuid}',        [\App\Http\Controllers\Admin\SchedulerHealthController::class, 'forget'])->name('forget');
        });

        // ═══ CDN Cache Purge ══════════════════════════════════════════════
        Route::prefix('cache-purge')->name('cache-purge.')->group(function () {
            Route::get('/',                 [\App\Http\Controllers\Admin\CachePurgeController::class, 'index'])->name('index');
            Route::post('/settings',        [\App\Http\Controllers\Admin\CachePurgeController::class, 'saveSettings'])->name('settings');
            Route::post('/everything',      [\App\Http\Controllers\Admin\CachePurgeController::class, 'purgeEverything'])->name('everything');
            Route::post('/urls',            [\App\Http\Controllers\Admin\CachePurgeController::class, 'purgeUrls'])->name('urls');
            Route::post('/hosts',           [\App\Http\Controllers\Admin\CachePurgeController::class, 'purgeHosts'])->name('hosts');
            Route::post('/tags',            [\App\Http\Controllers\Admin\CachePurgeController::class, 'purgeTags'])->name('tags');
            Route::post('/verify',          [\App\Http\Controllers\Admin\CachePurgeController::class, 'verify'])->name('verify');
        });

        // ═══ Photo Quality Ranking ════════════════════════════════════════
        Route::prefix('photo-quality')->name('photo-quality.')->group(function () {
            Route::get('/',                        [\App\Http\Controllers\Admin\PhotoQualityController::class, 'index'])->name('index');
            Route::post('/rescore-all',            [\App\Http\Controllers\Admin\PhotoQualityController::class, 'rescoreAll'])->name('rescore-all');
            Route::get('/{event}',                 [\App\Http\Controllers\Admin\PhotoQualityController::class, 'show'])->name('show');
            Route::post('/{event}/rescore',        [\App\Http\Controllers\Admin\PhotoQualityController::class, 'rescoreEvent'])->name('rescore-event');
        });

        // ═══ Gift Cards ═══════════════════════════════════════════════════
        Route::prefix('gift-cards')->name('gift-cards.')->group(function () {
            Route::get('/',                        [\App\Http\Controllers\Admin\GiftCardController::class, 'index'])->name('index');
            Route::get('/create',                  [\App\Http\Controllers\Admin\GiftCardController::class, 'create'])->name('create');
            Route::post('/',                       [\App\Http\Controllers\Admin\GiftCardController::class, 'store'])->name('store');
            Route::get('/{giftCard}',              [\App\Http\Controllers\Admin\GiftCardController::class, 'show'])->name('show');
            Route::post('/{giftCard}/adjust',      [\App\Http\Controllers\Admin\GiftCardController::class, 'adjust'])->name('adjust');
            Route::post('/{giftCard}/void',        [\App\Http\Controllers\Admin\GiftCardController::class, 'void'])->name('void');
        });

        // ═══ Upload Credits ═══════════════════════════════════════════════
        // Admin-facing storefront management + photographer balance tools.
        // Package mutations are rare events, so routes are RESTful (vs API-
        // ish) and live under /admin/credits/*.
        Route::prefix('credits')->name('credits.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\CreditController::class, 'index'])->name('index');

            // On/off toggle for the whole upload-credit subsystem
            Route::post('/toggle', [\App\Http\Controllers\Admin\CreditController::class, 'toggleSystem'])->name('toggle');

            // Package catalog
            Route::prefix('packages')->name('packages.')->group(function () {
                Route::get('/',                  [\App\Http\Controllers\Admin\CreditController::class, 'packagesIndex'])->name('index');
                Route::get('/create',            [\App\Http\Controllers\Admin\CreditController::class, 'packagesCreate'])->name('create');
                Route::post('/',                 [\App\Http\Controllers\Admin\CreditController::class, 'packagesStore'])->name('store');
                Route::get('/{package}/edit',    [\App\Http\Controllers\Admin\CreditController::class, 'packagesEdit'])->name('edit');
                Route::put('/{package}',         [\App\Http\Controllers\Admin\CreditController::class, 'packagesUpdate'])->name('update');
                Route::delete('/{package}',      [\App\Http\Controllers\Admin\CreditController::class, 'packagesDestroy'])->name('destroy');
            });

            // Per-photographer balance view + grant/adjust tools
            Route::prefix('photographers')->name('photographers.')->group(function () {
                Route::get('/',                       [\App\Http\Controllers\Admin\CreditController::class, 'photographers'])->name('index');
                Route::get('/{photographer}',         [\App\Http\Controllers\Admin\CreditController::class, 'photographerShow'])->name('show');
                Route::post('/{photographer}/grant',  [\App\Http\Controllers\Admin\CreditController::class, 'photographerGrant'])->name('grant');
                Route::post('/{photographer}/adjust', [\App\Http\Controllers\Admin\CreditController::class, 'photographerAdjust'])->name('adjust');
                Route::post('/{photographer}/recalc', [\App\Http\Controllers\Admin\CreditController::class, 'photographerRecalc'])->name('recalc');
                Route::post('/{photographer}/billing-mode', [\App\Http\Controllers\Admin\CreditController::class, 'photographerSetBillingMode'])->name('billing-mode');
            });
        });

        // ═══ Consumer Cloud Storage (Phase 3) ═══════════════════════════
        // Admin side of the consumer-facing storage product. Plan CRUD,
        // subscriber management, file monitoring + takedown, and the two
        // module-level toggles (sales mode + system kill-switch).
        Route::prefix('user-storage')->name('user-storage.')->group(function () {
            Route::get('/',                          [\App\Http\Controllers\Admin\UserStorageController::class, 'index'])->name('index');
            Route::post('/toggle',                   [\App\Http\Controllers\Admin\UserStorageController::class, 'toggleSetting'])->name('toggle');
            Route::post('/settings',                 [\App\Http\Controllers\Admin\UserStorageController::class, 'updateSettings'])->name('settings');

            // Plan catalog CRUD
            Route::prefix('plans')->name('plans.')->group(function () {
                Route::get('/',             [\App\Http\Controllers\Admin\StoragePlanController::class, 'index'])->name('index');
                Route::get('/create',       [\App\Http\Controllers\Admin\StoragePlanController::class, 'create'])->name('create');
                Route::post('/',            [\App\Http\Controllers\Admin\StoragePlanController::class, 'store'])->name('store');
                Route::get('/{plan}/edit',  [\App\Http\Controllers\Admin\StoragePlanController::class, 'edit'])->name('edit');
                Route::put('/{plan}',       [\App\Http\Controllers\Admin\StoragePlanController::class, 'update'])->name('update');
                Route::delete('/{plan}',    [\App\Http\Controllers\Admin\StoragePlanController::class, 'destroy'])->name('destroy');
            });

            // Subscription-level actions (cancel / resume / extend grace)
            // Declared first so the numeric-ID URL pattern takes priority.
            Route::post('/subscriptions/{subscription}/cancel',       [\App\Http\Controllers\Admin\UserStorageController::class, 'adminCancel'])->name('subscribers.cancel');
            Route::post('/subscriptions/{subscription}/resume',       [\App\Http\Controllers\Admin\UserStorageController::class, 'adminResume'])->name('subscribers.resume');
            Route::post('/subscriptions/{subscription}/extend-grace', [\App\Http\Controllers\Admin\UserStorageController::class, 'extendGrace'])->name('subscribers.extend-grace');

            // Subscribers list + detail (keyed by auth_users.id — ints only)
            Route::prefix('subscribers')->name('subscribers.')->group(function () {
                Route::get('/',                                   [\App\Http\Controllers\Admin\UserStorageController::class, 'subscribers'])->name('index');
                Route::get('/{user}',                             [\App\Http\Controllers\Admin\UserStorageController::class, 'subscriberShow'])->name('show')->where('user', '[0-9]+');
                Route::post('/{user}/recalc',                     [\App\Http\Controllers\Admin\UserStorageController::class, 'recalcUsage'])->name('recalc')->where('user', '[0-9]+');
            });

            // File monitoring + takedown
            Route::prefix('files')->name('files.')->group(function () {
                Route::get('/',                     [\App\Http\Controllers\Admin\UserFilesController::class, 'index'])->name('index');
                Route::get('/{file}/download',      [\App\Http\Controllers\Admin\UserFilesController::class, 'download'])->name('download');
                Route::post('/{file}/unshare',      [\App\Http\Controllers\Admin\UserFilesController::class, 'unshare'])->name('unshare');
                Route::delete('/{file}/takedown',   [\App\Http\Controllers\Admin\UserFilesController::class, 'takedown'])->name('takedown');
                Route::delete('/{file}/purge',      [\App\Http\Controllers\Admin\UserFilesController::class, 'purge'])->name('purge');
            });
        });

        // ═══ Subscriptions (monthly GB + AI features) ═════════════════════
        // Admin side of the Phase-2 subscription system. Plan seeding lives
        // in the migration so this controller is mostly read-only — admins
        // toggle plans on/off, watch KPIs, and occasionally hard-cancel or
        // force-expire a subscription when needed.
        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Admin\SubscriptionController::class, 'index'])->name('index');
            Route::get('/plans',          [\App\Http\Controllers\Admin\SubscriptionController::class, 'plans'])->name('plans');
            Route::get('/plans/{plan}/edit', [\App\Http\Controllers\Admin\SubscriptionController::class, 'editPlan'])->name('plans.edit');
            Route::put('/plans/{plan}',   [\App\Http\Controllers\Admin\SubscriptionController::class, 'updatePlan'])->name('plans.update');
            Route::get('/invoices',       [\App\Http\Controllers\Admin\SubscriptionController::class, 'invoices'])->name('invoices');
            Route::post('/plans/{plan}/toggle', [\App\Http\Controllers\Admin\SubscriptionController::class, 'togglePlan'])->name('plans.toggle');
            Route::get('/{subscription}', [\App\Http\Controllers\Admin\SubscriptionController::class, 'show'])->name('show');
            Route::post('/{subscription}/cancel', [\App\Http\Controllers\Admin\SubscriptionController::class, 'cancel'])->name('cancel');
            Route::post('/{subscription}/expire', [\App\Http\Controllers\Admin\SubscriptionController::class, 'expire'])->name('expire');
        });

        // Global feature flags (kill-switch UI)
        Route::prefix('features')->name('features.')->group(function () {
            Route::get('/',  [\App\Http\Controllers\Admin\FeatureFlagController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\FeatureFlagController::class, 'update'])->name('update');
        });

        // ═══ Alert Rules Engine ═══════════════════════════════════════════
        Route::prefix('alerts')->name('alerts.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Admin\AlertRuleController::class, 'index'])->name('index');
            Route::get('/create',          [\App\Http\Controllers\Admin\AlertRuleController::class, 'create'])->name('create');
            Route::post('/',               [\App\Http\Controllers\Admin\AlertRuleController::class, 'store'])->name('store');
            Route::get('/events',          [\App\Http\Controllers\Admin\AlertRuleController::class, 'events'])->name('events');
            Route::post('/run-now',        [\App\Http\Controllers\Admin\AlertRuleController::class, 'runNow'])->name('run-now');
            Route::get('/{rule}/edit',     [\App\Http\Controllers\Admin\AlertRuleController::class, 'edit'])->name('edit');
            Route::put('/{rule}',          [\App\Http\Controllers\Admin\AlertRuleController::class, 'update'])->name('update');
            Route::delete('/{rule}',       [\App\Http\Controllers\Admin\AlertRuleController::class, 'destroy'])->name('destroy');
            Route::post('/{rule}/toggle',      [\App\Http\Controllers\Admin\AlertRuleController::class, 'toggle'])->name('toggle');
            Route::post('/{rule}/test',        [\App\Http\Controllers\Admin\AlertRuleController::class, 'test'])->name('test');
            Route::post('/{rule}/acknowledge', [\App\Http\Controllers\Admin\AlertRuleController::class, 'acknowledge'])->name('acknowledge');
        });

        // ═══ Marketing Hub (pixels / UTM / SEO / LINE / newsletter / referral / loyalty) ═══
        Route::prefix('marketing')->name('marketing.')->group(function () {
            Route::get('/',                       [\App\Http\Controllers\Admin\MarketingController::class, 'index'])->name('index');
            Route::post('/toggle',                [\App\Http\Controllers\Admin\MarketingController::class, 'toggle'])->name('toggle');

            // Pixels & Analytics
            Route::get('/pixels',                 [\App\Http\Controllers\Admin\MarketingController::class, 'pixels'])->name('pixels');
            Route::post('/pixels',                [\App\Http\Controllers\Admin\MarketingController::class, 'updatePixels'])->name('pixels.update');

            // LINE
            Route::get('/line',                   [\App\Http\Controllers\Admin\MarketingController::class, 'line'])->name('line');
            Route::post('/line',                  [\App\Http\Controllers\Admin\MarketingController::class, 'updateLine'])->name('line.update');
            Route::post('/line/broadcast',        [\App\Http\Controllers\Admin\MarketingController::class, 'broadcastLine'])->name('line.broadcast');
            Route::post('/line/notify-test',      [\App\Http\Controllers\Admin\MarketingController::class, 'testLineNotify'])->name('line.notify-test');

            // SEO / OG / Schema
            Route::get('/seo',                    [\App\Http\Controllers\Admin\MarketingController::class, 'seo'])->name('seo');
            Route::post('/seo',                   [\App\Http\Controllers\Admin\MarketingController::class, 'updateSeo'])->name('seo.update');

            // Analytics
            Route::get('/analytics',              [\App\Http\Controllers\Admin\MarketingController::class, 'analytics'])->name('analytics');

            // ── Phase 2: Newsletter / Subscribers ──
            Route::get('/subscribers',            [\App\Http\Controllers\Admin\MarketingController::class, 'subscribers'])->name('subscribers');
            Route::post('/subscribers/settings',  [\App\Http\Controllers\Admin\MarketingController::class, 'updateSubscriberSettings'])->name('subscribers.settings');
            Route::delete('/subscribers/{subscriber}', [\App\Http\Controllers\Admin\MarketingController::class, 'deleteSubscriber'])->name('subscribers.delete');

            // ── Email Campaigns ──
            Route::prefix('campaigns')->name('campaigns.')->group(function () {
                Route::get('/',                   [\App\Http\Controllers\Admin\MarketingController::class, 'campaigns'])->name('index');
                Route::get('/create',             [\App\Http\Controllers\Admin\MarketingController::class, 'createCampaign'])->name('create');
                Route::post('/',                  [\App\Http\Controllers\Admin\MarketingController::class, 'storeCampaign'])->name('store');
                Route::get('/{campaign}',         [\App\Http\Controllers\Admin\MarketingController::class, 'showCampaign'])->name('show');
                Route::post('/{campaign}/send',   [\App\Http\Controllers\Admin\MarketingController::class, 'sendCampaign'])->name('send');
                Route::post('/{campaign}/cancel', [\App\Http\Controllers\Admin\MarketingController::class, 'cancelCampaign'])->name('cancel');
                Route::delete('/{campaign}',      [\App\Http\Controllers\Admin\MarketingController::class, 'deleteCampaign'])->name('delete');
            });

            // ── Referral ──
            Route::get('/referral',                [\App\Http\Controllers\Admin\MarketingController::class, 'referral'])->name('referral');
            Route::post('/referral/settings',      [\App\Http\Controllers\Admin\MarketingController::class, 'updateReferralSettings'])->name('referral.settings');
            Route::post('/referral/{code}/toggle', [\App\Http\Controllers\Admin\MarketingController::class, 'toggleReferralCode'])->name('referral.toggle');
            Route::post('/referral/{code}/update', [\App\Http\Controllers\Admin\MarketingController::class, 'updateReferralCode'])->name('referral.update');

            // ── Loyalty ──
            Route::get('/loyalty',                [\App\Http\Controllers\Admin\MarketingController::class, 'loyalty'])->name('loyalty');
            Route::post('/loyalty/settings',      [\App\Http\Controllers\Admin\MarketingController::class, 'updateLoyaltySettings'])->name('loyalty.settings');
            Route::post('/loyalty/adjust',        [\App\Http\Controllers\Admin\MarketingController::class, 'adjustLoyalty'])->name('loyalty.adjust');

            // ── Phase 3: Landing Pages ──
            Route::prefix('landing')->name('landing.')->group(function () {
                Route::get('/',                     [\App\Http\Controllers\Admin\MarketingController::class, 'landingPages'])->name('index');
                Route::post('/settings',            [\App\Http\Controllers\Admin\MarketingController::class, 'updateLandingSettings'])->name('settings');
                Route::get('/create',               [\App\Http\Controllers\Admin\MarketingController::class, 'createLandingPage'])->name('create');
                Route::post('/',                    [\App\Http\Controllers\Admin\MarketingController::class, 'storeLandingPage'])->name('store');
                Route::get('/{landingPage}/edit',   [\App\Http\Controllers\Admin\MarketingController::class, 'editLandingPage'])->name('edit');
                Route::put('/{landingPage}',        [\App\Http\Controllers\Admin\MarketingController::class, 'updateLandingPage'])->name('update');
                Route::delete('/{landingPage}',     [\App\Http\Controllers\Admin\MarketingController::class, 'deleteLandingPage'])->name('delete');
            });

            // ── Phase 3: Push Notifications ──
            Route::prefix('push')->name('push.')->group(function () {
                Route::get('/',                     [\App\Http\Controllers\Admin\MarketingController::class, 'push'])->name('index');
                Route::post('/settings',            [\App\Http\Controllers\Admin\MarketingController::class, 'updatePushSettings'])->name('settings');
                Route::post('/vapid-generate',      [\App\Http\Controllers\Admin\MarketingController::class, 'generateVapid'])->name('vapid-generate');
                Route::get('/create',               [\App\Http\Controllers\Admin\MarketingController::class, 'createPushCampaign'])->name('create');
                Route::post('/',                    [\App\Http\Controllers\Admin\MarketingController::class, 'storePushCampaign'])->name('store');
                Route::post('/{campaign}/send',     [\App\Http\Controllers\Admin\MarketingController::class, 'sendPushCampaign'])->name('send');
                Route::delete('/{campaign}',        [\App\Http\Controllers\Admin\MarketingController::class, 'deletePushCampaign'])->name('delete');
            });

            // ── Phase 3: Analytics v2 ──
            Route::get('/analytics-v2',             [\App\Http\Controllers\Admin\MarketingController::class, 'analyticsV2'])->name('analytics-v2');
            Route::post('/analytics-v2/settings',   [\App\Http\Controllers\Admin\MarketingController::class, 'updateAnalyticsSettings'])->name('analytics-v2.settings');
        });

        // ═══ Admin Management (Superadmin only) ═══
        Route::prefix('admins')->name('admins.')->middleware('admin.role:superadmin')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminManagementController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Admin\AdminManagementController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Admin\AdminManagementController::class, 'store'])->name('store');
            Route::get('/{admin}/edit', [\App\Http\Controllers\Admin\AdminManagementController::class, 'edit'])->name('edit');
            Route::put('/{admin}', [\App\Http\Controllers\Admin\AdminManagementController::class, 'update'])->name('update');
            Route::post('/{admin}/toggle-status', [\App\Http\Controllers\Admin\AdminManagementController::class, 'toggleStatus'])->name('toggle-status');
            Route::delete('/{admin}', [\App\Http\Controllers\Admin\AdminManagementController::class, 'destroy'])->name('destroy');
            Route::post('/{admin}/permissions', [\App\Http\Controllers\Admin\AdminManagementController::class, 'updatePermissions'])->name('permissions');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Photographer Routes
|--------------------------------------------------------------------------
*/
Route::prefix('photographer')->name('photographer.')->group(function () {
    // Photographer Auth (no auth middleware, but POST endpoints are rate-limited
    // to prevent brute-force: 10 login / 5 register per minute per IP).
    Route::get('/login', [\App\Http\Controllers\Photographer\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Photographer\AuthController::class, 'login'])
        ->name('login.post')
        ->middleware('rate.limit:10,1');
    Route::get('/register', [\App\Http\Controllers\Photographer\AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [\App\Http\Controllers\Photographer\AuthController::class, 'register'])
        ->name('register.post')
        ->middleware('rate.limit:5,10');
    // "Claim" path — for an already-logged-in customer (typically signed
    // up via LINE earlier) who wants to become a photographer without
    // re-doing OAuth. Creates the photographer_profile in-place and
    // routes them on to /connect-google.
    Route::post('/register/claim', [\App\Http\Controllers\Photographer\AuthController::class, 'claim'])
        ->name('register.claim')
        ->middleware('rate.limit:10,10');
    Route::post('/logout', [\App\Http\Controllers\Photographer\AuthController::class, 'logout'])->name('logout');

    // Photographer Social Login (Google + LINE)
    //   - /photographer/auth/{provider}          → redirect to provider OAuth
    //   - /photographer/auth/{provider}/callback → handle callback, link or login
    //
    // Acts as both login (existing photographer with linked social account)
    // AND register-link (logged-in photographer linking their account so
    // future logins work via social). Replaces email-verification as the
    // primary identity proof for photographers.
    Route::get('/auth/{provider}',          [\App\Http\Controllers\Photographer\SocialAuthController::class, 'redirect'])
        ->where('provider', 'google|line')
        ->name('auth.redirect');
    Route::get('/auth/{provider}/callback', [\App\Http\Controllers\Photographer\SocialAuthController::class, 'callback'])
        ->where('provider', 'google|line')
        ->name('auth.callback');

    // Forgot / Reset Password (Photographer)
    // Rate-limited to mirror the public-side caps: each send triggers a
    // transactional email so abuse turns into spam + cost.
    Route::get('/forgot-password', [\App\Http\Controllers\Photographer\AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Photographer\AuthController::class, 'sendResetLink'])->name('password.email')->middleware('rate.limit:5,10');
    Route::get('/reset-password', [\App\Http\Controllers\Photographer\AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\Photographer\AuthController::class, 'resetPassword'])->name('password.update')->middleware('rate.limit:10,10');

    // ── Google connection gate (replaces email verification) ───────────
    // Authenticated photographers without a linked Google account land here.
    // The middleware RequireGoogleLinked redirects every dashboard route to
    // this URL until the connection is made. Sits OUTSIDE the dashboard
    // group so it remains reachable while the gate is active.
    Route::get('/connect-google', [\App\Http\Controllers\Photographer\AuthController::class, 'showConnectGoogle'])
        ->middleware('photographer')
        ->name('connect-google');

    // Photographer Protected Routes
    Route::middleware(['photographer', 'photographer.google', 'no.back'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Photographer\DashboardController::class, 'index'])->name('dashboard');

        // /photographer/dashboard is the URL users intuitively type and the
        // one baked into old approval notifications. The canonical route
        // lives at /photographer, but rather than 404 on the natural guess
        // we redirect to the canonical URL — one place, one analytics row.
        Route::get('/dashboard', fn() => redirect()->route('photographer.dashboard'));

        // ── Bookings (job queue) ───────────────────────────────────────
        // Calendar view + per-booking confirm/cancel/complete actions.
        // LINE reminders run via SendBookingReminders cron (every 5 min).
        Route::get('bookings',                          [\App\Http\Controllers\Photographer\BookingController::class, 'index'])->name('bookings');
        Route::get('bookings/feed',                     [\App\Http\Controllers\Photographer\BookingController::class, 'calendarFeed'])->name('bookings.feed');
        Route::get('bookings/{booking}',                [\App\Http\Controllers\Photographer\BookingController::class, 'show'])->name('bookings.show');
        Route::post('bookings/{booking}/confirm',       [\App\Http\Controllers\Photographer\BookingController::class, 'confirm'])->name('bookings.confirm');
        Route::post('bookings/{booking}/cancel',        [\App\Http\Controllers\Photographer\BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('bookings/{booking}/complete',      [\App\Http\Controllers\Photographer\BookingController::class, 'complete'])->name('bookings.complete');
        Route::post('bookings/{booking}/notes',         [\App\Http\Controllers\Photographer\BookingController::class, 'updateNotes'])->name('bookings.notes');

        // ── Availability (recurring slots + day overrides) ─────────────
        Route::get('availability',                      [\App\Http\Controllers\Photographer\AvailabilityController::class, 'index'])->name('availability');
        Route::post('availability',                     [\App\Http\Controllers\Photographer\AvailabilityController::class, 'store'])->name('availability.store');
        Route::delete('availability/{availability}',    [\App\Http\Controllers\Photographer\AvailabilityController::class, 'destroy'])->name('availability.delete');

        // Events
        Route::resource('events', \App\Http\Controllers\Photographer\EventController::class);
        Route::get('events/{event}/qrcode', [\App\Http\Controllers\Photographer\EventController::class, 'qrcode'])->name('events.qrcode');

        // Cascading location picker — fed by the create/edit forms when
        // the photographer changes the province (or district) <select>.
        // Mirrors admin.api.locations.* but auth-scoped to photographer.
        // Public Thai government reference data, no PII; cached at the
        // browser side for the session via fetch().
        Route::get('api/locations/districts',    [\App\Http\Controllers\Photographer\EventController::class, 'getDistricts'])->name('api.locations.districts');
        Route::get('api/locations/subdistricts', [\App\Http\Controllers\Photographer\EventController::class, 'getSubdistricts'])->name('api.locations.subdistricts');

        // ── Per-event photo bundles (volume / face-match / event-all) ────
        // Photographers manage their own bundle pricing here; the public
        // event page reads the same rows. Auto-seeded on event create via
        // EventBundleSeederObserver.
        // Bundle CRUD — read endpoints are unrestricted; mutating
        // endpoints are rate-limited (30 changes per hour per
        // photographer) to neuter price-flip fraud and accidental
        // bulk-edit storms. The cap is generous enough that no
        // legitimate workflow hits it.
        Route::get('events/{event}/packages',                    [\App\Http\Controllers\Photographer\EventPackageController::class, 'index'])->name('events.packages.index');
        Route::post('events/{event}/packages',                   [\App\Http\Controllers\Photographer\EventPackageController::class, 'store'])->name('events.packages.store')->middleware('rate.limit:30,60');
        Route::put('events/{event}/packages/{package}',          [\App\Http\Controllers\Photographer\EventPackageController::class, 'update'])->name('events.packages.update')->middleware('rate.limit:30,60');
        Route::delete('events/{event}/packages/{package}',       [\App\Http\Controllers\Photographer\EventPackageController::class, 'destroy'])->name('events.packages.destroy')->middleware('rate.limit:30,60');
        Route::post('events/{event}/packages/template',          [\App\Http\Controllers\Photographer\EventPackageController::class, 'applyTemplate'])->name('events.packages.template')->middleware('rate.limit:10,60');
        Route::post('events/{event}/packages/recalculate',       [\App\Http\Controllers\Photographer\EventPackageController::class, 'recalculate'])->name('events.packages.recalculate')->middleware('rate.limit:10,60');
        Route::post('events/{event}/packages/{package}/feature', [\App\Http\Controllers\Photographer\EventPackageController::class, 'toggleFeatured'])->name('events.packages.feature')->middleware('rate.limit:30,60');

        // Portfolio archival (manual) — wipes originals but keeps previews + cover
        Route::post('events/{event}/archive-to-portfolio', [\App\Http\Controllers\Photographer\EventController::class, 'archiveToPortfolio'])->name('events.archive-portfolio');
        Route::post('events/{event}/toggle-portfolio', [\App\Http\Controllers\Photographer\EventController::class, 'togglePortfolio'])->name('events.toggle-portfolio');

        // Google Drive import (rate-limited — each import kicks a batch job)
        Route::post('events/{event}/import-drive', [\App\Http\Controllers\Photographer\EventController::class, 'importDrive'])->name('events.import-drive')->middleware('rate.limit:10,1');
        Route::get('events/{event}/import-progress', [\App\Http\Controllers\Photographer\EventController::class, 'importProgress'])->name('events.import-progress');
        Route::get('events/{event}/photo-status', [\App\Http\Controllers\Photographer\EventController::class, 'photoStatus'])->name('events.photo-status');

        // Photos (upload & manage)
        // store is rate-limited: each upload triggers image processing (watermark, thumbnails).
        Route::get('events/{event}/photos', [\App\Http\Controllers\Photographer\PhotoController::class, 'index'])->name('events.photos.index');
        Route::get('events/{event}/photos/upload', [\App\Http\Controllers\Photographer\PhotoController::class, 'create'])->name('events.photos.upload');
        Route::post('events/{event}/photos', [\App\Http\Controllers\Photographer\PhotoController::class, 'store'])
            ->name('events.photos.store')
            // Three layered gates:
            //   rate.limit:60,1     — burst protection (60 req/min/user)
            //   photographer.quota  — bytes-based storage quota (existing)
            //   usage.quota:photo.upload — count-based per-day plan cap (NEW)
            ->middleware(['rate.limit:60,1', 'photographer.quota', 'usage.quota:photo.upload']);
        Route::delete('events/{event}/photos/{photo}', [\App\Http\Controllers\Photographer\PhotoController::class, 'destroy'])->name('events.photos.destroy');
        Route::post('events/{event}/photos/bulk-delete', [\App\Http\Controllers\Photographer\PhotoController::class, 'bulkDelete'])->name('events.photos.bulk-delete');
        Route::post('events/{event}/photos/reorder', [\App\Http\Controllers\Photographer\PhotoController::class, 'reorder'])->name('events.photos.reorder');
        Route::post('events/{event}/photos/{photo}/set-cover', [\App\Http\Controllers\Photographer\PhotoController::class, 'setCover'])->name('events.photos.set-cover');

        // Profile
        Route::get('/profile', [\App\Http\Controllers\Photographer\ProfileController::class, 'index'])->name('profile');
        Route::put('/profile', [\App\Http\Controllers\Photographer\ProfileController::class, 'update'])->name('profile.update');
        Route::get('/setup-bank', [\App\Http\Controllers\Photographer\ProfileController::class, 'setupBank'])->name('setup-bank');
        Route::post('/setup-bank', [\App\Http\Controllers\Photographer\ProfileController::class, 'updateBank'])->name('setup-bank.update');
        // AJAX PromptPay verification — called before Save so the photographer
        // eyeballs the resolved name. Rate-limited so the mock (and the future
        // real provider) can't be abused as a name-lookup oracle.
        Route::post('/setup-bank/verify-promptpay', [\App\Http\Controllers\Photographer\ProfileController::class, 'verifyPromptPay'])
            ->middleware('rate.limit:20,1')
            ->name('setup-bank.verify-promptpay');

        // Earnings
        // Index is open to all tiers (creators can see the tier nudge + empty state).
        // Withdraw is seller-gated: the route only makes sense once PromptPay is on file,
        // and the tier middleware redirects creators to the PromptPay form with a CTA
        // instead of 403-ing them.
        Route::get('/earnings', [\App\Http\Controllers\Photographer\EarningsController::class, 'index'])->name('earnings');
        Route::post('/earnings/withdraw', [\App\Http\Controllers\Photographer\EarningsController::class, 'withdraw'])
            ->middleware('photographer.tier:seller')
            ->name('earnings.withdraw');

        // Analytics
        Route::get('/analytics', [\App\Http\Controllers\Photographer\AnalyticsController::class, 'index'])->name('analytics');

        // Upload credits — photographer-facing storefront + dashboard.
        // The buy action creates a pending Order and hands off to the shared
        // /payment/checkout/{order} flow so all 8 gateways Just Work and the
        // webhook path issues credits via CreditService::issueFromPaidOrder.
        Route::middleware('credits.enabled')->group(function () {
            Route::get('/credits',                [\App\Http\Controllers\Photographer\CreditController::class, 'index'])->name('credits.index');
            Route::get('/credits/store',          [\App\Http\Controllers\Photographer\CreditController::class, 'store'])->name('credits.store');
            Route::get('/credits/history',        [\App\Http\Controllers\Photographer\CreditController::class, 'history'])->name('credits.history');
            Route::post('/credits/buy/{code}',    [\App\Http\Controllers\Photographer\CreditController::class, 'buy'])
                ->middleware('rate.limit:30,1')
                ->name('credits.buy');
        });

        // Monthly GB + AI feature subscriptions — parallels the credits flow
        // above. subscribe/change routes create an Order (order_type=
        // 'subscription') and hand off to /payment/checkout; the webhook
        // calls SubscriptionService::activateFromPaidInvoice to flip status
        // active + refresh the quota cache on photographer_profiles.
        // Wrapped in `subscriptions.enabled` so admin can globally turn the
        // feature off — when off, photographers get a friendly redirect to
        // their dashboard instead of a half-functional plan picker.
        Route::middleware('subscriptions.enabled')->group(function () {
            Route::prefix('subscription')->name('subscription.')->group(function () {
                Route::get('/',              [\App\Http\Controllers\Photographer\SubscriptionController::class, 'index'])->name('index');
                Route::get('/plans',         [\App\Http\Controllers\Photographer\SubscriptionController::class, 'plans'])->name('plans');
                Route::get('/invoices',      [\App\Http\Controllers\Photographer\SubscriptionController::class, 'invoices'])->name('invoices');
                Route::post('/subscribe/{code}', [\App\Http\Controllers\Photographer\SubscriptionController::class, 'subscribe'])
                    ->middleware('rate.limit:20,1')->name('subscribe');
                Route::post('/change/{code}',    [\App\Http\Controllers\Photographer\SubscriptionController::class, 'change'])
                    ->middleware('rate.limit:10,1')->name('change');
                Route::post('/cancel',       [\App\Http\Controllers\Photographer\SubscriptionController::class, 'cancel'])
                    ->middleware('rate.limit:10,1')->name('cancel');
                Route::post('/resume',       [\App\Http\Controllers\Photographer\SubscriptionController::class, 'resume'])
                    ->middleware('rate.limit:10,1')->name('resume');
            });
        });

        // Reviews (photographer can view + reply)
        Route::get('/reviews', [\App\Http\Controllers\Photographer\ReviewController::class, 'index'])->name('reviews');
        Route::post('/reviews/{review}/reply', [\App\Http\Controllers\Photographer\ReviewController::class, 'reply'])->name('reviews.reply');
        Route::delete('/reviews/{review}/reply', [\App\Http\Controllers\Photographer\ReviewController::class, 'deleteReply'])->name('reviews.reply.delete');

        // Chat — gated by global feature flag (same as the customer side)
        Route::middleware('feature.global:chat')->group(function () {
            Route::get('/chat', [\App\Http\Controllers\Photographer\ChatController::class, 'index'])->name('chat');
            Route::get('/chat/{conversation}', [\App\Http\Controllers\Photographer\ChatController::class, 'show'])->name('chat.show');
            Route::post('/chat/{conversation}/send', [\App\Http\Controllers\Photographer\ChatController::class, 'send'])->name('chat.send');
        });

        // Stripe Connect
        Route::get('/stripe-connect', [\App\Http\Controllers\Photographer\StripeConnectController::class, 'show'])->name('stripe-connect');
        Route::post('/stripe-connect/onboard', [\App\Http\Controllers\Photographer\StripeConnectController::class, 'onboard'])->name('stripe-connect.onboard');
        Route::get('/stripe-connect/return', [\App\Http\Controllers\Photographer\StripeConnectController::class, 'return'])->name('stripe-connect.return');
        Route::get('/stripe-connect/refresh', [\App\Http\Controllers\Photographer\StripeConnectController::class, 'refresh'])->name('stripe-connect.refresh');
        Route::post('/stripe-connect/dashboard', [\App\Http\Controllers\Photographer\StripeConnectController::class, 'dashboard'])->name('stripe-connect.dashboard');

        // ─────────────────────────────────────────────────────────────
        // Plan-feature surfaces (Team / Branding / API / AI / Analytics)
        // Each subscription plan exposes these features at different
        // tiers — see SubscriptionPlan.ai_features / max_team_seats /
        // max_concurrent_events. Cap enforcement happens inside the
        // services; this routes file just exposes the endpoints.
        // ─────────────────────────────────────────────────────────────

        // Team Members (Business 3 / Studio 10) — DEPRECATED for MVP.
        // Gated by `feature.global:team_seats` which defaults OFF. The
        // routes are kept so flipping the flag in admin → Feature Flags
        // restores the feature without a redeploy.
        Route::prefix('team')->name('team.')->middleware('feature.global:team_seats')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Photographer\TeamController::class, 'index'])->name('index');
            Route::post('/invite',           [\App\Http\Controllers\Photographer\TeamController::class, 'invite'])->name('invite')->middleware('rate.limit:10,1');
            Route::post('/{member}/role',    [\App\Http\Controllers\Photographer\TeamController::class, 'changeRole'])->name('role');
            Route::delete('/{member}',       [\App\Http\Controllers\Photographer\TeamController::class, 'revoke'])->name('revoke');
        });

        // Custom Branding (Business+)
        Route::prefix('branding')->name('branding.')->group(function () {
            Route::get('/',     [\App\Http\Controllers\Photographer\BrandingController::class, 'edit'])->name('edit');
            Route::post('/',    [\App\Http\Controllers\Photographer\BrandingController::class, 'update'])->name('update')->middleware('rate.limit:30,1');
            Route::delete('/logo', [\App\Http\Controllers\Photographer\BrandingController::class, 'removeLogo'])->name('logo.remove');
        });

        // API Keys (Studio) — DEPRECATED for MVP.
        // Gated by `feature.global:api_access`; flip in Admin → Feature
        // Flags to restore. Underlying ApiKey model + middleware kept.
        Route::prefix('api-keys')->name('api-keys.')->middleware('feature.global:api_access')->group(function () {
            Route::get('/',           [\App\Http\Controllers\Photographer\ApiKeyController::class, 'index'])->name('index');
            Route::post('/',          [\App\Http\Controllers\Photographer\ApiKeyController::class, 'create'])->name('create')->middleware('rate.limit:10,1');
            Route::delete('/{key}',   [\App\Http\Controllers\Photographer\ApiKeyController::class, 'revoke'])->name('revoke');
        });

        // Photographer Store — self-serve catalog (promotions + addons).
        // Distinct from Subscription (main billing tier) and Credits (upload
        // credit packs). This handles boost/featured/highlight slots,
        // storage top-ups, AI-credit packs, branding/priority unlocks.
        Route::prefix('store')->name('store.')->group(function () {
            Route::get('/',           [\App\Http\Controllers\Photographer\PromotionStoreController::class, 'index'])->name('index');
            Route::get('/history',    [\App\Http\Controllers\Photographer\PromotionStoreController::class, 'history'])->name('history');
            // Comprehensive "my plan + add-ons + usage" status. Centralises
            // every billing-relevant signal the photographer needs to see.
            Route::get('/status',     [\App\Http\Controllers\Photographer\PromotionStoreController::class, 'status'])->name('status');
            Route::post('/buy/{sku}', [\App\Http\Controllers\Photographer\PromotionStoreController::class, 'buy'])
                ->where('sku', '[a-z0-9_\.]+')
                ->name('buy')
                ->middleware('rate.limit:20,1');
        });

        // AI Tools (per-feature plan gate via subscription.feature)
        Route::prefix('ai')->name('ai.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Photographer\AiToolsController::class, 'index'])->name('index');

            Route::post('/duplicate-detection/{event}', [\App\Http\Controllers\Photographer\AiToolsController::class, 'runDuplicateDetection'])
                ->name('duplicates')->middleware(['rate.limit:5,1', 'subscription.feature:duplicate_detection']);
            Route::post('/quality-filter/{event}', [\App\Http\Controllers\Photographer\AiToolsController::class, 'runQualityFilter'])
                ->name('quality')->middleware(['rate.limit:5,1', 'subscription.feature:quality_filter']);
            Route::post('/best-shot/{event}', [\App\Http\Controllers\Photographer\AiToolsController::class, 'runBestShot'])
                ->name('best-shot')->middleware(['rate.limit:5,1', 'subscription.feature:best_shot']);
            Route::post('/color-enhance/{event}', [\App\Http\Controllers\Photographer\AiToolsController::class, 'runColorEnhance'])
                ->name('color-enhance')->middleware(['rate.limit:5,1', 'subscription.feature:color_enhance']);
            Route::post('/auto-tagging/{event}', [\App\Http\Controllers\Photographer\AiToolsController::class, 'runAutoTagging'])
                ->name('auto-tagging')->middleware(['rate.limit:5,1', 'subscription.feature:auto_tagging']);
            Route::post('/face-search/{event}', [\App\Http\Controllers\Photographer\AiToolsController::class, 'runFaceIndex'])
                ->name('face-index')->middleware(['rate.limit:5,1', 'subscription.feature:face_search']);
            Route::post('/smart-captions/{event}', [\App\Http\Controllers\Photographer\AiToolsController::class, 'runSmartCaptions'])
                ->name('smart-captions')->middleware(['rate.limit:5,1', 'subscription.feature:smart_captions']);
            Route::post('/video-thumbnails/{event}', [\App\Http\Controllers\Photographer\AiToolsController::class, 'runVideoThumbnails'])
                ->name('video-thumbnails')->middleware(['rate.limit:5,1', 'subscription.feature:video_thumbnails']);
        });

        // Customer Analytics (Business+)
        Route::get('/analytics', [\App\Http\Controllers\Photographer\AnalyticsController::class, 'index'])->name('analytics');

        // Lightroom-style Presets (Starter+ via subscription.feature:presets)
        Route::prefix('presets')->name('presets.')->group(function () {
            Route::get('/',                          [\App\Http\Controllers\Photographer\PresetController::class, 'index'])->name('index');
            Route::get('/create',                    [\App\Http\Controllers\Photographer\PresetController::class, 'create'])->name('create');
            Route::post('/',                         [\App\Http\Controllers\Photographer\PresetController::class, 'store'])->name('store');
            Route::post('/import',                   [\App\Http\Controllers\Photographer\PresetController::class, 'import'])->name('import')->middleware('rate.limit:10,1');
            Route::post('/preview',                  [\App\Http\Controllers\Photographer\PresetController::class, 'preview'])->name('preview')->middleware('rate.limit:60,1');
            Route::post('/upload-sample',            [\App\Http\Controllers\Photographer\PresetController::class, 'uploadSample'])->name('upload-sample')->middleware('rate.limit:10,1');
            Route::post('/clear-default',            [\App\Http\Controllers\Photographer\PresetController::class, 'clearDefault'])->name('clear-default');
            Route::get('/{preset}/edit',             [\App\Http\Controllers\Photographer\PresetController::class, 'edit'])->name('edit');
            Route::put('/{preset}',                  [\App\Http\Controllers\Photographer\PresetController::class, 'update'])->name('update');
            Route::delete('/{preset}',               [\App\Http\Controllers\Photographer\PresetController::class, 'destroy'])->name('destroy');
            Route::post('/{preset}/duplicate',       [\App\Http\Controllers\Photographer\PresetController::class, 'duplicate'])->name('duplicate');
            Route::post('/{preset}/set-default',     [\App\Http\Controllers\Photographer\PresetController::class, 'setDefault'])->name('set-default');
            Route::post('/{preset}/apply/{event}',   [\App\Http\Controllers\Photographer\PresetController::class, 'applyToEvent'])->name('apply')->middleware('rate.limit:5,1');
        });
    });
});

// Public team-invite acceptance — auth required but not photographer-only
Route::middleware(['auth'])->get('/team/accept/{token}', [\App\Http\Controllers\Photographer\TeamController::class, 'accept'])->name('team.accept');

/*
|--------------------------------------------------------------------------
| pSEO landing pages
|--------------------------------------------------------------------------
| Catch-all that matches any single-segment URL not picked up by
| earlier routes (e.g. /events-in-bangkok, /wedding-photographers).
| Resolves the slug against seo_landing_pages and renders the
| public.landing.show view if found, otherwise 404.
|
| Multi-segment slugs like "photographers/john-doe-42" are also handled
| because the route uses `where('slug', '.*')` to swallow the rest of
| the path. Place at the END of routes/web.php so it never overrides
| a more specific route.
*/
/*
| The catch-all for pSEO landing pages must EXCLUDE the prefixes used
| by specific routes (photographers, events, admin, etc.) so it doesn't
| swallow them when Laravel's route compiler picks the first match.
|
| Slug constraint:
|   • starts with a lowercase letter or digit
|   • contains only [a-z0-9-/] after that
|   • CANNOT start with reserved prefixes: photographers/, events/,
|     admin/, photographer/, login, register, api/, etc.
|
| The negative lookahead at the front of the regex is what enforces
| the prefix exclusion. Routes that have their own dedicated handler
| (e.g. /events/{slug}) keep priority; only un-routed slugs hit pSEO.
*/
Route::get('/{slug}', [\App\Http\Controllers\Public\LandingPageController::class, 'show'])
    ->where('slug', '^(?!photographers|events|admin|photographer|login|register|api|cart|checkout|orders|wishlist|profile|notifications|chat|line|webhook|payment|storage|s|p|legal|blog|products|gift|faq|help|contact|about|terms|privacy|cookie|refund|search|booking|home|seo-landing)[a-z0-9][a-z0-9\-/]+$')
    ->name('pseo.landing');
