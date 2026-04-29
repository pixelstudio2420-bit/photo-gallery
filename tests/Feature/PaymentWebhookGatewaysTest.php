<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Webhook integration tests for the non-Stripe/Omise gateways.
 *
 * Covered gateways
 * ----------------
 *   • PayPal     — push webhook with signature verification toggle
 *   • LINE Pay   — redirect callback that POSTs to LINE Pay confirm API
 *   • TrueMoney  — push webhook with HMAC-SHA256 signature
 *   • 2C2P       — push webhook with HS256 JWT payload
 *
 * What we are testing
 * -------------------
 * The CONTRACT between the external gateway and our system: payloads
 * the controller actually receives in production map to the right
 * order/transaction state changes. Signature verification is in scope
 * (we test both happy path and malformed signature) but is gated by
 * the AppSetting for the relevant secret — when the secret is unset,
 * the verifier is bypassed so dev/staging works without live keys.
 *
 * We deliberately keep this OUT of PaymentWebhookTest.php so the two
 * concerns stay separate: that file owns Stripe + Omise + SlipOK,
 * which were already battle-tested. This file is the parallel coverage
 * for the four gateways that previously had zero webhook tests.
 *
 * Outbound HTTP (LINE Pay's confirm API call) is stubbed via Http::fake()
 * so the suite stays offline.
 */
class PaymentWebhookGatewaysTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Fixture helper
    // -------------------------------------------------------------------------

    /**
     * Create a User + Event + Order + PaymentTransaction wired to a specific
     * gateway. The transaction's gateway_transaction_id is what the webhook
     * payload will reference, so we keep it explicit.
     */
    private function makeOrderForGateway(string $gateway, string $gatewayTxnId, string $status = 'pending_payment'): array
    {
        $user = User::create([
            'first_name'    => 'Gw',
            'last_name'     => 'Tester',
            'email'         => "gw-{$gateway}-" . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        $event = Event::create([
            'name'            => "Gateway Test Event {$gateway}",
            'slug'            => "gw-{$gateway}-" . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 20.00,
            'is_free'         => false,
            'view_count'      => 0,
        ]);

        $orderNumber = 'ORD-' . strtoupper($gateway) . '-' . uniqid();
        $orderId     = DB::table('orders')->insertGetId([
            'user_id'      => $user->id,
            'event_id'     => $event->id,
            'order_number' => $orderNumber,
            'total'        => 100.00,
            'status'       => $status,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $internalTxnId = "TXN-{$gateway}-" . uniqid();
        DB::table('payment_transactions')->insert([
            'transaction_id'         => $internalTxnId,
            'order_id'               => $orderId,
            'user_id'                => $user->id,
            'payment_gateway'        => $gateway,
            'gateway_transaction_id' => $gatewayTxnId,
            'amount'                 => 100.00,
            'currency'               => 'THB',
            'status'                 => 'pending',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        return [
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'txn_id'       => $internalTxnId,
            'user'         => $user,
            'event'        => $event,
        ];
    }

    // =========================================================================
    // PayPal
    // =========================================================================

    public function test_paypal_capture_completed_marks_order_paid(): void
    {
        // No paypal_webhook_id → controller skips signature verification.
        AppSetting::set('paypal_webhook_id', '');
        AppSetting::flushCache();

        $captureId = 'PAY-CAP-' . uniqid();
        $data      = $this->makeOrderForGateway('paypal', $captureId);

        $response = $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => [
                'id'        => $captureId,
                'custom_id' => $data['order_number'],
                'amount'    => ['value' => '100.00', 'currency_code' => 'THB'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $data['order_id'],
            'status'   => 'completed',
        ]);
    }

    public function test_paypal_capture_denied_marks_order_cancelled(): void
    {
        AppSetting::set('paypal_webhook_id', '');
        AppSetting::flushCache();

        $captureId = 'PAY-DEN-' . uniqid();
        $data      = $this->makeOrderForGateway('paypal', $captureId);

        $response = $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource'   => [
                'id'        => $captureId,
                'custom_id' => $data['order_number'],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'cancelled',
        ]);
    }

    public function test_paypal_refund_marks_order_refunded(): void
    {
        AppSetting::set('paypal_webhook_id', '');
        AppSetting::flushCache();

        $captureId = 'PAY-REF-' . uniqid();
        $refundId  = 'PAY-REF-RID-' . uniqid();
        // Order starts paid so refund is the legitimate next step.
        $data = $this->makeOrderForGateway('paypal', $captureId, 'paid');

        $response = $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource'   => [
                'id'                 => $refundId,
                'supplementary_data' => ['related_ids' => ['capture_id' => $captureId]],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'refunded',
        ]);
    }

    public function test_paypal_unknown_event_type_is_ignored_gracefully(): void
    {
        AppSetting::set('paypal_webhook_id', '');
        AppSetting::flushCache();

        $data = $this->makeOrderForGateway('paypal', 'PAY-UNK-' . uniqid());

        $response = $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'BILLING.SUBSCRIPTION.CREATED',
            'resource'   => ['id' => 'sub_test'],
        ]);

        // Unknown event types are logged + ack'd; order remains pending.
        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'pending_payment',
        ]);
    }

    // =========================================================================
    // LINE Pay
    // =========================================================================

    public function test_linepay_callback_with_successful_confirm_marks_order_paid(): void
    {
        // Stub the outbound LINE Pay confirm API. Returns LINE's success
        // code (returnCode='0000').
        Http::fake([
            '*line.me*' => Http::response(['returnCode' => '0000', 'returnMessage' => 'Success']),
        ]);

        $linepayTxnId = 'linepay-' . uniqid();
        $data         = $this->makeOrderForGateway('line_pay', $linepayTxnId);

        $response = $this->call('GET', '/api/webhooks/linepay?transactionId=' . $linepayTxnId . '&orderId=' . $data['order_number']);

        // Note: linepay route is POST in api.php, but LINE Pay redirects
        // are GET. The test intentionally hits POST since that is what
        // the route accepts.
        $response = $this->postJson('/api/webhooks/linepay', [
            'transactionId' => $linepayTxnId,
            'orderId'       => $data['order_number'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $data['order_id'],
            'status'   => 'completed',
        ]);
    }

    public function test_linepay_callback_with_failed_confirm_marks_order_cancelled(): void
    {
        Http::fake([
            '*line.me*' => Http::response(['returnCode' => '1106', 'returnMessage' => 'PaymentAuthFailed']),
        ]);

        $linepayTxnId = 'linepay-fail-' . uniqid();
        $data         = $this->makeOrderForGateway('line_pay', $linepayTxnId);

        $response = $this->postJson('/api/webhooks/linepay', [
            'transactionId' => $linepayTxnId,
            'orderId'       => $data['order_number'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'cancelled',
        ]);
    }

    public function test_linepay_callback_with_cancel_flag_marks_transaction_failed(): void
    {
        // User-cancelled redirect — no Confirm API call, just mark as failed.
        $linepayTxnId = 'linepay-cancel-' . uniqid();
        $data         = $this->makeOrderForGateway('line_pay', $linepayTxnId);

        $response = $this->postJson('/api/webhooks/linepay', [
            'cancel'        => '1',
            'transactionId' => $linepayTxnId,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true, 'status' => 'cancelled']);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'cancelled',
        ]);
    }

    public function test_linepay_callback_missing_transaction_id_is_handled_gracefully(): void
    {
        $response = $this->postJson('/api/webhooks/linepay', []);

        // Missing transactionId is logged + ack'd, never crashes.
        $response->assertStatus(200);
        $response->assertJsonFragment(['received' => true]);
    }

    // =========================================================================
    // TrueMoney
    // =========================================================================

    public function test_truemoney_webhook_success_marks_order_paid(): void
    {
        // No truemoney_secret_key → signature verification is skipped.
        AppSetting::set('truemoney_secret_key', '');
        AppSetting::flushCache();

        $tmTxnId = 'tm-' . uniqid();
        $data    = $this->makeOrderForGateway('truemoney', $tmTxnId);

        $response = $this->postJson('/api/webhooks/truemoney', [
            'transaction_id' => $tmTxnId,
            'status'         => 'SUCCESS',
            'amount'         => 100.00,
            'order_id'       => $data['order_number'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $data['order_id'],
            'status'   => 'completed',
        ]);
    }

    public function test_truemoney_webhook_failure_marks_order_cancelled(): void
    {
        AppSetting::set('truemoney_secret_key', '');
        AppSetting::flushCache();

        $tmTxnId = 'tm-fail-' . uniqid();
        $data    = $this->makeOrderForGateway('truemoney', $tmTxnId);

        $response = $this->postJson('/api/webhooks/truemoney', [
            'transaction_id' => $tmTxnId,
            'status'         => 'FAILED',
            'amount'         => 100.00,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'cancelled',
        ]);
    }

    public function test_truemoney_webhook_pending_status_does_not_change_order(): void
    {
        AppSetting::set('truemoney_secret_key', '');
        AppSetting::flushCache();

        $tmTxnId = 'tm-pend-' . uniqid();
        $data    = $this->makeOrderForGateway('truemoney', $tmTxnId);

        $response = $this->postJson('/api/webhooks/truemoney', [
            'transaction_id' => $tmTxnId,
            'status'         => 'PENDING',
        ]);

        $response->assertStatus(200);
        // Order should stay in pending_payment — not paid, not cancelled.
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'pending_payment',
        ]);
    }

    public function test_truemoney_webhook_invalid_signature_returns_400(): void
    {
        $secret = 'tm_test_secret';
        AppSetting::set('truemoney_secret_key', $secret);
        AppSetting::flushCache();

        $tmTxnId = 'tm-sig-' . uniqid();
        $data    = $this->makeOrderForGateway('truemoney', $tmTxnId);

        $response = $this->postJson('/api/webhooks/truemoney', [
            'transaction_id' => $tmTxnId,
            'status'         => 'SUCCESS',
            'amount'         => 100.00,
            'signature'      => 'definitely-not-the-real-hmac',
        ]);

        $response->assertStatus(400);
        // Order must not have moved to paid.
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'pending_payment',
        ]);
    }

    public function test_truemoney_webhook_valid_signature_marks_order_paid(): void
    {
        $secret = 'tm_test_secret_key_zz';
        AppSetting::set('truemoney_secret_key', $secret);
        AppSetting::flushCache();

        $tmTxnId = 'tm-sig-ok-' . uniqid();
        $data    = $this->makeOrderForGateway('truemoney', $tmTxnId);

        // Build the canonical signed string the way the controller does.
        $payload = [
            'transaction_id' => $tmTxnId,
            'status'         => 'SUCCESS',
            'amount'         => 100.00,
            'order_id'       => $data['order_number'],
        ];
        ksort($payload);
        $signature = hash_hmac('sha256', http_build_query($payload), $secret);

        $response = $this->postJson('/api/webhooks/truemoney', $payload + ['signature' => $signature]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'paid',
        ]);
    }

    public function test_truemoney_webhook_missing_transaction_id_is_acked(): void
    {
        AppSetting::set('truemoney_secret_key', '');
        AppSetting::flushCache();

        $response = $this->postJson('/api/webhooks/truemoney', [
            'status' => 'SUCCESS',
        ]);

        // Should acknowledge so the gateway stops retrying — even with bad data.
        $response->assertStatus(200);
    }

    // =========================================================================
    // 2C2P
    // =========================================================================

    /**
     * Encode a payload as a 2C2P-style HS256 JWT (matching the verifier
     * in PaymentWebhookController::decodeAndVerify2C2PJwt).
     */
    private function encode2C2PJwt(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $b64    = fn ($data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        $headerB64 = $b64($header);
        $bodyB64   = $b64($payload);
        $sig       = rtrim(strtr(base64_encode(
            hash_hmac('sha256', "{$headerB64}.{$bodyB64}", $secret, true)
        ), '+/', '-_'), '=');

        return "{$headerB64}.{$bodyB64}.{$sig}";
    }

    public function test_2c2p_webhook_success_marks_order_paid(): void
    {
        $secret     = '2c2p_test_secret_99';
        $merchantId = 'TESTMID2C2P';

        AppSetting::set('2c2p_secret_key', $secret);
        AppSetting::set('2c2p_merchant_id', $merchantId);
        AppSetting::flushCache();

        $tranRef = '2C2P-OK-' . uniqid();
        $data    = $this->makeOrderForGateway('2c2p', $tranRef);

        $jwt = $this->encode2C2PJwt([
            'merchantID' => $merchantId,
            'tranRef'    => $tranRef,
            'invoiceNo'  => $data['order_number'],
            'respCode'   => '0000',
            'respDesc'   => 'Success',
            'amount'     => 100.00,
        ], $secret);

        $response = $this->post('/api/webhooks/2c2p', ['payload' => $jwt]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $data['order_id'],
            'status'   => 'completed',
        ]);
    }

    public function test_2c2p_webhook_failure_marks_order_cancelled(): void
    {
        $secret     = '2c2p_test_secret_99';
        $merchantId = 'TESTMID2C2P';

        AppSetting::set('2c2p_secret_key', $secret);
        AppSetting::set('2c2p_merchant_id', $merchantId);
        AppSetting::flushCache();

        $tranRef = '2C2P-FAIL-' . uniqid();
        $data    = $this->makeOrderForGateway('2c2p', $tranRef);

        $jwt = $this->encode2C2PJwt([
            'merchantID' => $merchantId,
            'tranRef'    => $tranRef,
            'invoiceNo'  => $data['order_number'],
            'respCode'   => '0002',
            'respDesc'   => 'Rejected',
            'amount'     => 100.00,
        ], $secret);

        $response = $this->post('/api/webhooks/2c2p', ['payload' => $jwt]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'cancelled',
        ]);
    }

    public function test_2c2p_webhook_invalid_jwt_returns_400(): void
    {
        $secret = '2c2p_test_secret_99';
        AppSetting::set('2c2p_secret_key', $secret);
        AppSetting::flushCache();

        // JWT signed with a different secret — verification must fail.
        $tranRef = '2C2P-BADSIG-' . uniqid();
        $data    = $this->makeOrderForGateway('2c2p', $tranRef);

        $jwt = $this->encode2C2PJwt([
            'merchantID' => 'whatever',
            'tranRef'    => $tranRef,
            'respCode'   => '0000',
        ], 'wrong-secret-' . uniqid());

        $response = $this->post('/api/webhooks/2c2p', ['payload' => $jwt]);

        $response->assertStatus(400);
        // Order untouched.
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'pending_payment',
        ]);
    }

    public function test_2c2p_webhook_missing_payload_returns_400(): void
    {
        $response = $this->post('/api/webhooks/2c2p', []);

        $response->assertStatus(400);
    }

    public function test_2c2p_webhook_merchant_id_mismatch_returns_400(): void
    {
        $secret = '2c2p_test_secret_99';
        AppSetting::set('2c2p_secret_key', $secret);
        AppSetting::set('2c2p_merchant_id', 'OUR_MID');
        AppSetting::flushCache();

        $tranRef = '2C2P-MM-' . uniqid();
        $data    = $this->makeOrderForGateway('2c2p', $tranRef);

        // Valid JWT, but merchantID is for a different merchant — must reject.
        $jwt = $this->encode2C2PJwt([
            'merchantID' => 'SOMEONE_ELSES_MID',
            'tranRef'    => $tranRef,
            'respCode'   => '0000',
        ], $secret);

        $response = $this->post('/api/webhooks/2c2p', ['payload' => $jwt]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'pending_payment',
        ]);
    }

    public function test_2c2p_webhook_pending_status_marks_order_pending_review(): void
    {
        $secret     = '2c2p_test_secret_99';
        $merchantId = 'TESTMID2C2P';

        AppSetting::set('2c2p_secret_key', $secret);
        AppSetting::set('2c2p_merchant_id', $merchantId);
        AppSetting::flushCache();

        $tranRef = '2C2P-PEND-' . uniqid();
        $data    = $this->makeOrderForGateway('2c2p', $tranRef);

        $jwt = $this->encode2C2PJwt([
            'merchantID' => $merchantId,
            'tranRef'    => $tranRef,
            'invoiceNo'  => $data['order_number'],
            'respCode'   => '0001',
            'respDesc'   => 'Pending verification',
            'amount'     => 100.00,
        ], $secret);

        $response = $this->post('/api/webhooks/2c2p', ['payload' => $jwt]);

        $response->assertStatus(200);
        // 0001 → processing (txn) → pending_review (order).
        $this->assertDatabaseHas('orders', [
            'id'     => $data['order_id'],
            'status' => 'pending_review',
        ]);
    }

    // =========================================================================
    // Cross-cutting: webhook always writes audit log row
    // =========================================================================

    public function test_paypal_webhook_writes_audit_log(): void
    {
        AppSetting::set('paypal_webhook_id', '');
        AppSetting::flushCache();

        $captureId = 'PAY-AUD-' . uniqid();
        $data      = $this->makeOrderForGateway('paypal', $captureId);

        $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => [
                'id'        => $captureId,
                'custom_id' => $data['order_number'],
            ],
        ]);

        $this->assertDatabaseHas('payment_audit_log', [
            'action' => 'paypal_PAYMENT.CAPTURE.COMPLETED',
        ]);
    }

    public function test_truemoney_webhook_writes_audit_log(): void
    {
        AppSetting::set('truemoney_secret_key', '');
        AppSetting::flushCache();

        $tmTxnId = 'tm-aud-' . uniqid();
        $this->makeOrderForGateway('truemoney', $tmTxnId);

        $this->postJson('/api/webhooks/truemoney', [
            'transaction_id' => $tmTxnId,
            'status'         => 'SUCCESS',
            'amount'         => 100.00,
        ]);

        $this->assertDatabaseHas('payment_audit_log', [
            'action' => 'truemoney_webhook',
        ]);
    }

    public function test_2c2p_webhook_writes_audit_log(): void
    {
        $secret     = '2c2p_test_secret_99';
        $merchantId = 'TESTMID2C2P';

        AppSetting::set('2c2p_secret_key', $secret);
        AppSetting::set('2c2p_merchant_id', $merchantId);
        AppSetting::flushCache();

        $tranRef = '2C2P-AUD-' . uniqid();
        $data    = $this->makeOrderForGateway('2c2p', $tranRef);

        $jwt = $this->encode2C2PJwt([
            'merchantID' => $merchantId,
            'tranRef'    => $tranRef,
            'invoiceNo'  => $data['order_number'],
            'respCode'   => '0000',
        ], $secret);

        $this->post('/api/webhooks/2c2p', ['payload' => $jwt]);

        $this->assertDatabaseHas('payment_audit_log', [
            'action' => '2c2p_webhook',
        ]);
    }
}
