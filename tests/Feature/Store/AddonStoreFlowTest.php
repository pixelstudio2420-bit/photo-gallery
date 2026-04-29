<?php

namespace Tests\Feature\Store;

use App\Models\Order;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\Monetization\AddonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * End-to-end test of the photographer self-serve add-on store.
 *
 * The contract being locked down:
 *
 *   1. Buy creates a pending purchase row + a pending Order. Activation
 *      does NOT happen yet — the photographer cannot use the add-on
 *      until they pay.
 *
 *   2. Order is linked back to purchase via addon_purchase_id (so
 *      OrderFulfillmentService can resolve the activation handler).
 *
 *   3. When the Order's webhook flips it to paid, the fulfillment
 *      service activates the add-on. Storage top-ups bump
 *      storage_quota_bytes; AI credit packs decrement
 *      ai_credits_used; promotions create photographer_promotions
 *      rows; branding flags toggle app_settings.
 *
 *   4. Replaying the activation (webhook retry) is idempotent — quota
 *      doesn't double-bump, AI credits don't double-credit.
 *
 *   5. The status page lists active add-ons + pending purchases.
 */
class AddonStoreFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makePhotographer(array $profileOverrides = []): User
    {
        $user = User::create([
            'first_name'    => 'Store',
            'last_name'     => 'Tester',
            'email'         => 'store-' . uniqid() . '@test.local',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
        ]);
        PhotographerProfile::create(array_merge([
            'user_id'             => $user->id,
            'photographer_code'   => 'PH-T' . substr(uniqid(), -6),
            'display_name'        => 'Store Tester',
            'commission_rate'     => 80,
            'status'              => 'approved',
            'tier'                => 'pro',
            'storage_quota_bytes' => 100 * 1024 * 1024 * 1024,   // 100GB plan default
            'storage_used_bytes'  => 10 * 1024 * 1024 * 1024,
            'ai_credits_used'     => 0,
        ], $profileOverrides));

        // The /photographer/* routes are gated by RequireGoogleLinked
        // middleware which expects a row in auth_social_logins. Seed one so
        // the test photographer can reach the store routes — same fixture
        // pattern used by TestPhotographersSeeder.
        DB::table('auth_social_logins')->insert([
            'user_id'     => $user->id,
            'provider'    => 'google',
            'provider_id' => 'test-google-' . $user->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        return $user;
    }

    /* ───────────────────── Buy flow ───────────────────── */

    public function test_buy_creates_pending_purchase_AND_pending_order_NOT_activated(): void
    {
        $user = $this->makePhotographer();
        $this->actingAsPhotographer($user);

        // Sanity: confirm Auth resolves the right user before middleware kicks in
        $this->assertTrue(\Auth::check(), 'actingAs must establish auth for route middleware');
        $this->assertNotNull($user->photographerProfile, 'photographerProfile must exist');

        $response = $this->post(route('photographer.store.buy', ['sku' => 'storage.50gb']));

        // Debug: capture the actual redirect + any session errors
        $location = $response->headers->get('Location') ?? '(none)';
        $sessionErrors = session('errors')?->all() ?? [];
        $sessionFlash = ['error' => session('error'), 'success' => session('success')];
        // Should redirect to checkout — NOT to history (instant-activate is gone)
        $response->assertRedirect();
        $this->assertStringContainsString('payment/checkout', $location, sprintf(
            "Got redirect to: %s | errors=%s | flash=%s",
            $location,
            json_encode($sessionErrors),
            json_encode($sessionFlash),
        ));

        // Purchase row exists, status=pending (NOT activated)
        $purchase = DB::table('photographer_addon_purchases')
            ->where('photographer_id', $user->id)->first();
        $this->assertNotNull($purchase);
        $this->assertEquals('pending', $purchase->status,
            'Buy must NOT activate until payment clears.');

        // Order exists, type=addon, status=pending_payment, linked back
        $order = Order::where('addon_purchase_id', $purchase->id)->first();
        $this->assertNotNull($order, 'Order must be created and linked to purchase.');
        $this->assertEquals(Order::TYPE_ADDON, $order->order_type);
        $this->assertEquals('pending_payment', $order->status);
        $this->assertEquals(290.0, (float) $order->total);   // storage.50gb price

        // Cross-link: purchase row carries the order_id
        $this->assertEquals($order->id, $purchase->order_id);

        // Storage quota MUST NOT be increased yet
        $profile = PhotographerProfile::where('user_id', $user->id)->first();
        $this->assertEquals(100 * 1024 * 1024 * 1024, (int) $profile->storage_quota_bytes,
            'Storage quota must not change until payment clears.');
    }

    public function test_buy_unknown_sku_returns_404(): void
    {
        $user = $this->makePhotographer();
        $this->actingAsPhotographer($user);
        $this->post(route('photographer.store.buy', ['sku' => 'storage.does_not_exist']))
            ->assertNotFound();
    }

    /* ───────────────────── Activation on paid ───────────────────── */

    public function test_storage_addon_activated_when_order_paid(): void
    {
        $user = $this->makePhotographer();
        $this->actingAsPhotographer($user);

        $this->post(route('photographer.store.buy', ['sku' => 'storage.200gb']));
        $purchase = DB::table('photographer_addon_purchases')->where('photographer_id', $user->id)->first();
        $order    = Order::where('addon_purchase_id', $purchase->id)->first();

        // Simulate webhook: order paid → fulfillment service runs activation
        $order->update(['status' => 'paid', 'paid_at' => now()]);
        app(\App\Services\OrderFulfillmentService::class)->fulfill($order->fresh());

        // Quota bumped by 200GB
        $profile = PhotographerProfile::where('user_id', $user->id)->first();
        $this->assertEquals(
            (100 + 200) * 1024 * 1024 * 1024,
            (int) $profile->storage_quota_bytes,
            'Storage quota must be increased by exactly 200GB after paid activation.',
        );

        // Purchase row marked activated
        $this->assertEquals('activated',
            DB::table('photographer_addon_purchases')->where('id', $purchase->id)->value('status'));
    }

    public function test_ai_credits_addon_activated_when_order_paid(): void
    {
        $user = $this->makePhotographer();
        // ai_credits_used is not in PhotographerProfile fillable — that's
        // by design: it's only writable through SubscriptionService (which
        // uses DB::table). Match that here to seed a non-zero baseline.
        DB::table('photographer_profiles')
            ->where('user_id', $user->id)
            ->update(['ai_credits_used' => 8000]);
        $this->actingAsPhotographer($user);

        $this->post(route('photographer.store.buy', ['sku' => 'ai_credits.5k']));
        $purchase = DB::table('photographer_addon_purchases')->where('photographer_id', $user->id)->first();
        $order    = Order::where('addon_purchase_id', $purchase->id)->first();

        $order->update(['status' => 'paid', 'paid_at' => now()]);
        app(\App\Services\OrderFulfillmentService::class)->fulfill($order->fresh());

        // ai_credits_used was 8000, +5000 of headroom = max(0, 8000-5000) = 3000
        $reloaded = DB::table('photographer_profiles')
            ->where('user_id', $user->id)
            ->value('ai_credits_used');
        $this->assertEquals(3000, (int) $reloaded,
            '5k credit pack must reduce ai_credits_used by 5000 (floor at 0).');
    }

    public function test_branding_flag_addon_activated_when_order_paid(): void
    {
        $user = $this->makePhotographer();
        $this->actingAsPhotographer($user);

        $this->post(route('photographer.store.buy', ['sku' => 'branding.custom_watermark']));
        $purchase = DB::table('photographer_addon_purchases')->where('photographer_id', $user->id)->first();
        $order    = Order::where('addon_purchase_id', $purchase->id)->first();

        $order->update(['status' => 'paid', 'paid_at' => now()]);
        app(\App\Services\OrderFulfillmentService::class)->fulfill($order->fresh());

        $key = "addon_flag:{$user->id}:branding.custom_watermark";
        $this->assertEquals('1', \App\Models\AppSetting::get($key),
            'Branding flag must be set to "1" after paid activation.');
    }

    /* ───────────────────── Idempotency ───────────────────── */

    public function test_replay_activation_does_not_double_credit_storage(): void
    {
        $user = $this->makePhotographer();
        $this->actingAsPhotographer($user);

        $this->post(route('photographer.store.buy', ['sku' => 'storage.50gb']));
        $purchase = DB::table('photographer_addon_purchases')->where('photographer_id', $user->id)->first();
        $order    = Order::where('addon_purchase_id', $purchase->id)->first();
        $order->update(['status' => 'paid', 'paid_at' => now()]);

        $fulfillment = app(\App\Services\OrderFulfillmentService::class);
        $fulfillment->fulfill($order->fresh());
        $fulfillment->fulfill($order->fresh());   // webhook retry
        $fulfillment->fulfill($order->fresh());   // and another

        $profile = PhotographerProfile::where('user_id', $user->id)->first();
        $this->assertEquals(
            (100 + 50) * 1024 * 1024 * 1024,
            (int) $profile->storage_quota_bytes,
            'Replay must NOT double-credit storage — quota stays at +50GB no matter how many times fulfillment runs.',
        );
    }

    /* ───────────────────── Status page ───────────────────── */

    public function test_status_page_lists_active_addons(): void
    {
        $user = $this->makePhotographer();
        $this->actingAsPhotographer($user);

        // Buy + activate one storage pack
        $this->post(route('photographer.store.buy', ['sku' => 'storage.50gb']));
        $order = Order::where('user_id', $user->id)->first();
        $order->update(['status' => 'paid', 'paid_at' => now()]);
        app(\App\Services\OrderFulfillmentService::class)->fulfill($order->fresh());

        $response = $this->get(route('photographer.store.status'));
        $response->assertOk();
        $response->assertSee('+50 GB');                 // storage pack label
        $response->assertSee('Storage Top-ups');        // section header
    }

    public function test_status_page_shows_pending_payment_banner(): void
    {
        $user = $this->makePhotographer();
        $this->actingAsPhotographer($user);

        // Buy but DO NOT pay
        $this->post(route('photographer.store.buy', ['sku' => 'ai_credits.20k']));

        $response = $this->get(route('photographer.store.status'));
        $response->assertOk();
        $response->assertSee('+20,000');
        $response->assertSee('รอชำระเงิน');
    }

    /* ───────────────────── Helpers ───────────────────── */

    private function actingAsPhotographer(User $user): void
    {
        $this->actingAs($user, 'web');
    }
}
