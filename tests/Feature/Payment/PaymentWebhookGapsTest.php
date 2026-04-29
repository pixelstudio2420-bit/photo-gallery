<?php

namespace Tests\Feature\Payment;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fills coverage gaps left by PaymentWebhookTest + PaymentWebhookGatewaysTest.
 *
 * Existing suite covers happy/error paths well per gateway. Gaps it
 * doesn't currently exercise:
 *
 *   1. Omise webhook signature validation (configured + valid → 200,
 *      configured + invalid → 400). The gateway code already enforces
 *      this in production; we want a test pinning the contract so a
 *      future refactor can't quietly drop it.
 *
 *   2. Omise refund.create event flips order to refunded (handler has
 *      the code path but it's untested).
 *
 *   3. Cross-gateway idempotency: re-delivering an identical webhook
 *      MUST NOT double-mutate an already-terminal order. Real gateways
 *      retry on timeouts; we need to be safe under double-fire.
 */
class PaymentWebhookGapsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $orderStatus = 'pending_payment'): array
    {
        $user = User::create([
            'first_name'    => 'Gap',
            'last_name'     => 'Tester',
            'email'         => 'gap-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);
        $event = Event::create([
            'name'            => 'Gap Test ' . uniqid(),
            'slug'            => 'gap-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 100,
            'is_free'         => false,
            'view_count'      => 0,
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'user_id'      => $user->id,
            'event_id'     => $event->id,
            'order_number' => 'O-' . uniqid(),
            'total'        => 100.00,
            'status'       => $orderStatus,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $txnId = 'TXN-' . uniqid();
        DB::table('payment_transactions')->insert([
            'transaction_id'  => $txnId,
            'order_id'        => $orderId,
            'user_id'         => $user->id,
            'payment_gateway' => 'omise',
            'amount'          => 100.00,
            'currency'        => 'THB',
            'status'          => 'pending',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        return ['order_id' => $orderId, 'txn_id' => $txnId];
    }

    private function postOmiseSigned(array $payload, string $secret): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $sig  = hash_hmac('sha256', $body, $secret);
        return $this->call('POST', '/api/webhooks/omise', [], [], [], [
            'CONTENT_TYPE'         => 'application/json',
            'HTTP_X-Omise-Key-Hash' => $sig,
        ], $body);
    }

    /* ───────────────── Omise signature validation ───────────────── */

    public function test_omise_with_secret_configured_accepts_valid_signature(): void
    {
        $secret = 'omise_test_secret_' . uniqid();
        AppSetting::set('omise_webhook_secret', $secret);
        AppSetting::flushCache();

        $f = $this->fixture();

        $response = $this->postOmiseSigned([
            'key'  => 'charge.complete',
            'data' => [
                'id'       => 'chrg_x',
                'status'   => 'successful',
                'metadata' => ['order_id' => $f['order_id'], 'transaction_id' => $f['txn_id']],
            ],
        ], $secret);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $f['order_id'], 'status' => 'paid']);
    }

    public function test_omise_with_secret_configured_rejects_invalid_signature(): void
    {
        $secret = 'omise_test_secret_' . uniqid();
        AppSetting::set('omise_webhook_secret', $secret);
        AppSetting::flushCache();

        $f = $this->fixture();

        // Sign with a DIFFERENT secret — must be rejected.
        $body = json_encode([
            'key'  => 'charge.complete',
            'data' => [
                'id'       => 'chrg_x',
                'status'   => 'successful',
                'metadata' => ['order_id' => $f['order_id']],
            ],
        ]);
        $bogusSig = hash_hmac('sha256', $body, 'wrong-secret');

        $response = $this->call('POST', '/api/webhooks/omise', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_X-Omise-Key-Hash' => $bogusSig,
        ], $body);

        $response->assertStatus(400);
        $this->assertDatabaseHas('orders', [
            'id'     => $f['order_id'],
            'status' => 'pending_payment',  // unchanged
        ]);
    }

    public function test_omise_with_secret_configured_rejects_missing_signature(): void
    {
        $secret = 'omise_test_secret_' . uniqid();
        AppSetting::set('omise_webhook_secret', $secret);
        AppSetting::flushCache();

        $f = $this->fixture();

        $response = $this->postJson('/api/webhooks/omise', [
            'key'  => 'charge.complete',
            'data' => [
                'id'       => 'chrg_x',
                'status'   => 'successful',
                'metadata' => ['order_id' => $f['order_id']],
            ],
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('orders', [
            'id'     => $f['order_id'],
            'status' => 'pending_payment',
        ]);
    }

    /* ───────────────── Omise refund event ───────────────── */

    public function test_omise_refund_event_flips_paid_order_to_refunded(): void
    {
        AppSetting::set('omise_webhook_secret', '');   // skip sig in dev
        AppSetting::flushCache();

        $f = $this->fixture(orderStatus: 'paid');

        $response = $this->postJson('/api/webhooks/omise', [
            'key'  => 'refund.create',
            'data' => [
                'id'       => 'rfnd_x',
                'metadata' => ['order_id' => $f['order_id']],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $f['order_id'], 'status' => 'refunded']);
    }

    /* ───────────────── Idempotency under retry ───────────────── */

    public function test_omise_double_delivery_does_not_double_process(): void
    {
        AppSetting::set('omise_webhook_secret', '');
        AppSetting::flushCache();

        $f = $this->fixture();

        $payload = [
            'key'  => 'charge.complete',
            'data' => [
                'id'       => 'chrg_idem',
                'status'   => 'successful',
                'metadata' => ['order_id' => $f['order_id'], 'transaction_id' => $f['txn_id']],
            ],
        ];

        // Fire 1 — order moves to paid
        $this->postJson('/api/webhooks/omise', $payload)->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $f['order_id'], 'status' => 'paid']);

        // Fire 2 — exact same payload (Omise retry). Order stays paid; no
        // exception, no second state machine transition.
        $response2 = $this->postJson('/api/webhooks/omise', $payload);
        $response2->assertStatus(200);

        // Status is still paid (idempotent), and we did NOT create a second
        // payment_transactions row for the same charge.
        $this->assertDatabaseHas('orders', ['id' => $f['order_id'], 'status' => 'paid']);
        $txnCount = DB::table('payment_transactions')
            ->where('order_id', $f['order_id'])
            ->count();
        $this->assertEquals(1, $txnCount,
            'A re-delivered Omise webhook must not create extra payment_transactions rows.');
    }

    public function test_stripe_double_delivery_does_not_double_process(): void
    {
        $secret = 'whsec_idem_test';
        AppSetting::set('stripe_webhook_secret', $secret);
        AppSetting::flushCache();
        config(['services.stripe.webhook_secret' => $secret]);

        $f = $this->fixture();

        $payload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id'       => 'pi_idem_test',
                    'metadata' => ['order_id' => $f['order_id']],
                ],
            ],
        ]);
        $ts  = time();
        $sig = hash_hmac('sha256', $ts . '.' . $payload, $secret);
        $headers = [
            'HTTP_Stripe-Signature' => "t={$ts},v1={$sig}",
            'CONTENT_TYPE'          => 'application/json',
        ];

        // Two identical deliveries
        $this->call('POST', '/api/webhooks/stripe', [], [], [], $headers, $payload)->assertStatus(200);
        $this->call('POST', '/api/webhooks/stripe', [], [], [], $headers, $payload)->assertStatus(200);

        $this->assertDatabaseHas('orders', ['id' => $f['order_id'], 'status' => 'paid']);
    }
}
