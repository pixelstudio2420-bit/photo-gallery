<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DriveController;
use App\Http\Controllers\Api\CartApiController;
use App\Http\Controllers\Api\ChatApiController;
use App\Http\Controllers\Api\CouponApiController;
use App\Http\Controllers\Api\WishlistApiController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\LanguageApiController;
use App\Http\Controllers\Api\PaymentWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public API
Route::get('/language/{locale}', [LanguageApiController::class, 'switch'])->name('api.language');

// Note: User-authenticated API routes (notifications, wishlist, cart, chat)
// are defined in web.php under the 'auth' middleware group
// so they can share the web session for authentication.

// Google Drive (photos)
//
// Throttle calibration:
//   /drive/{eventId} (the photo LIST) — one call per gallery page-load,
//     30/min/IP is generous for the buyer and rejects scrapers.
//   /drive/image/{fileId} (the per-photo PROXY) — a 60-photo gallery on
//     a retina screen can fire ~120 requests on first paint (60 × 1x +
//     60 × 2x via srcset), and lazy-load on scroll adds more. The
//     previous 120/min cap was hitting 429 mid-gallery, leaving the
//     bottom rows showing as broken images. Lift to 600/min so a buyer
//     viewing a 200-photo event can fully load it (with retina + scroll
//     follow-ups) without a single rate-limit miss, while still blocking
//     scrape-the-whole-bucket attempts (an attacker would max out around
//     ~33,000 images/hour at this rate vs. 7,200 before — but most of
//     those URLs 302 to R2 anyway, so the proxy isn't the bandwidth
//     bottleneck for an attacker either way).
Route::get('/drive/{eventId}', [DriveController::class, 'listPhotos'])->name('api.drive.list')->middleware('throttle:30,1');
Route::get('/drive/image/{fileId}', [DriveController::class, 'proxyImage'])->name('api.drive.image')->middleware('throttle:600,1');

// Payment Webhooks (no CSRF, verified by signature)
Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [PaymentWebhookController::class, 'stripe'])->name('webhook.stripe');
    Route::post('/omise', [PaymentWebhookController::class, 'omise'])->name('webhook.omise');
    // Payout/transfer settlement — fired when Omise confirms a PromptPay
    // transfer has actually settled (or failed). Separate from the charge
    // webhook above because transfer events are a different event family
    // and the controller needs different matching logic (disbursement, not
    // order).
    Route::post('/omise/transfers', [PaymentWebhookController::class, 'omiseTransfer'])->name('webhook.omise.transfer');
    Route::post('/paypal', [PaymentWebhookController::class, 'paypal'])->name('webhook.paypal');
    Route::post('/linepay', [PaymentWebhookController::class, 'linepay'])->name('webhook.linepay');
    Route::post('/truemoney', [PaymentWebhookController::class, 'truemoney'])->name('webhook.truemoney');
    Route::post('/2c2p', [PaymentWebhookController::class, 'twoCTwoP'])->name('webhook.2c2p');
    Route::post('/slipok', [PaymentWebhookController::class, 'slipok'])->name('webhook.slipok');
    Route::post('/google-drive', [DriveController::class, 'webhook'])->name('webhook.google_drive');
    // Google Calendar push notifications (events.watch). All info comes
    // from X-Goog-* headers; the body is empty. Token validation lives
    // inside the controller method.
    Route::post('/google-calendar', [PaymentWebhookController::class, 'googleCalendarWebhook'])
        ->name('webhook.google_calendar');
    Route::post('/line', [PaymentWebhookController::class, 'lineWebhook'])->name('webhook.line');
    Route::post('/facebook', [PaymentWebhookController::class, 'facebookWebhook'])->name('webhook.facebook');

    // LINE OA follow/unfollow webhook — flips auth_users.line_is_friend
    // when customers add or remove our LINE OA. Configure in LINE Console
    // under Messaging API → Webhook URL.
    // URL: https://loadroop.com/api/webhooks/line-oa
    // Signature: X-Line-Signature (HMAC-SHA256 of body using channel_secret)
    Route::post('/line-oa', [\App\Http\Controllers\Api\LineOaWebhookController::class, 'handle'])
        ->name('webhook.line_oa');
});

// ─────────────────────────────────────────────────────────────────────
// Photographer Public API v1
// ─────────────────────────────────────────────────────────────────────
//
// Bearer-authenticated read API for photographers' own data — pulls
// events, photos, orders, and aggregate stats. Useful for slideshow
// displays, external CRMs, or studio dashboards.
//
// Auth flow:
//   1. Photographer creates a key in /photographer/api-keys
//   2. Token format `pgk_<48 hex chars>` shown ONCE at create time
//   3. Caller sends `Authorization: Bearer pgk_…` on each request
//   4. `photographer.api` middleware verifies via bcrypt(token_hash)
//      and checks the photographer's plan still includes `api_access`
//
// Throttling:
//   - 60 requests per minute per IP at the network edge (`throttle:60,1`)
//   - Future: per-key bucket via custom RateLimiter::for() — see
//     AppServiceProvider::boot for the bucket definition
//
// All endpoints scoped to the authenticated photographer — cross-tenant
// data access is impossible because every query filters by
// `photographer_id = $profile->user_id`.
//
// Documented in OpenAPI at /api/docs/spec.json (auto-generated from
// ApiDocumentationService::build).
Route::prefix('v1/photographer')
    ->middleware(['photographer.api', 'throttle:photographer-api'])
    ->name('api.photographer.')
    ->group(function () {
        $c = \App\Http\Controllers\Api\V1\PhotographerApiController::class;

        Route::get('/me',                       [$c, 'me'])->name('me');
        Route::get('/stats',                    [$c, 'stats'])->name('stats');

        Route::get('/events',                   [$c, 'events'])->name('events');
        Route::get('/events/{event}',           [$c, 'eventShow'])->where('event', '[0-9]+')->name('events.show');
        Route::get('/events/{event}/photos',    [$c, 'eventPhotos'])->where('event', '[0-9]+')->name('events.photos');

        Route::get('/photos/{photo}',           [$c, 'photoShow'])->where('photo', '[0-9]+')->name('photos.show');

        Route::get('/orders',                   [$c, 'orders'])->name('orders');
        Route::get('/orders/{order}',           [$c, 'orderShow'])->where('order', '[0-9]+')->name('orders.show');
    });

// Admin API — notification routes are in web.php (need session for admin guard)
