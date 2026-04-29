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
Route::get('/drive/{eventId}', [DriveController::class, 'listPhotos'])->name('api.drive.list')->middleware('throttle:30,1');
Route::get('/drive/image/{fileId}', [DriveController::class, 'proxyImage'])->name('api.drive.image')->middleware('throttle:120,1');

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
});

// ─────────────────────────────────────────────────────────────────────
// Photographer Public API (Studio plan only)
// ─────────────────────────────────────────────────────────────────────
//
// Bearer-authenticated read API for photographers' own data. Useful for
// integrating with their own apps, slideshow displays, or external CRMs.
// Subscription-gated via the photographer.api middleware which checks
// the api_access feature flag on the photographer's plan.
//
// All routes are scoped to the authenticated photographer — they can
// never see another photographer's events/photos/orders.
Route::prefix('v1/photographer')
    ->middleware('photographer.api')
    ->name('api.photographer.')
    ->group(function () {
        Route::get('/me', function (\Illuminate\Http\Request $r) {
            $profile = $r->attributes->get('photographer_profile');
            return response()->json([
                'success' => true,
                'data'    => [
                    'photographer_id' => $profile->user_id,
                    'display_name'    => $profile->display_name,
                    'plan'            => $profile->subscription_plan_code,
                    'storage_used_bytes' => (int) $profile->storage_used_bytes,
                    'storage_quota_bytes' => (int) $profile->storage_quota_bytes,
                ],
            ]);
        })->name('me');

        Route::get('/events', function (\Illuminate\Http\Request $r) {
            $profile = $r->attributes->get('photographer_profile');
            $events = \App\Models\Event::where('photographer_id', $profile->user_id)
                ->orderByDesc('created_at')
                ->limit(min(100, (int) $r->query('limit', 50)))
                ->get(['id','name','slug','status','price_per_photo','shoot_date','view_count','created_at']);
            return response()->json(['success' => true, 'data' => $events]);
        })->name('events');

        Route::get('/events/{event}/photos', function (\Illuminate\Http\Request $r, int $event) {
            $profile = $r->attributes->get('photographer_profile');
            $ev = \App\Models\Event::where('id', $event)
                ->where('photographer_id', $profile->user_id)
                ->first();
            if (!$ev) {
                return response()->json(['success' => false, 'message' => 'Event not found'], 404);
            }
            $photos = \App\Models\EventPhoto::where('event_id', $event)
                ->orderBy('sort_order')
                ->limit(min(500, (int) $r->query('limit', 100)))
                ->get(['id','filename','file_size','width','height','quality_score','ai_tags','created_at']);
            return response()->json(['success' => true, 'data' => $photos]);
        })->name('events.photos');
    });

// Admin API — notification routes are in web.php (need session for admin guard)
