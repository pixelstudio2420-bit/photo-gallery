<?php

namespace App\Services\Payment;

use App\Models\AppSetting;

class StripeGateway implements PaymentGatewayInterface
{
    // ----------------------------------------------------------------
    // Identity
    // ----------------------------------------------------------------

    public function getName(): string
    {
        return 'Stripe (บัตรเครดิต / เดบิต)';
    }

    public function getMethodType(): string
    {
        return 'stripe';
    }

    // ----------------------------------------------------------------
    // Credentials (single source of truth: app_settings DB table,
    // managed via /admin/settings/payment-gateways)
    // ----------------------------------------------------------------

    public static function secretKey(): string
    {
        return (string) AppSetting::get('stripe_secret_key', '');
    }

    public static function webhookSecret(): string
    {
        return (string) AppSetting::get('stripe_webhook_secret', '');
    }

    // ----------------------------------------------------------------
    // Availability
    // ----------------------------------------------------------------

    public function isAvailable(): bool
    {
        return !empty(self::secretKey());
    }

    // ----------------------------------------------------------------
    // Core operations
    // ----------------------------------------------------------------

    public function initiate(array $orderData): array
    {
        if (!class_exists(\Stripe\StripeClient::class)) {
            return [
                'success'      => false,
                'redirect_url' => null,
                'qr_code'      => null,
                'data'         => ['error' => 'Stripe SDK not installed. Run: composer require stripe/stripe-php'],
            ];
        }

        $secret = self::secretKey();
        if (empty($secret)) {
            return [
                'success'      => false,
                'redirect_url' => null,
                'qr_code'      => null,
                'data'         => ['error' => 'Stripe secret key not configured — set it in /admin/settings/payment-gateways'],
            ];
        }

        try {
            $stripe  = new \Stripe\StripeClient($secret);
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => 'thb',
                        'product_data' => ['name' => $orderData['description'] ?? 'Photo Order'],
                        'unit_amount'  => (int) (($orderData['amount'] ?? 0) * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode'        => 'payment',
                'success_url' => ($orderData['success_url'] ?? route('payment.success')) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => $orderData['cancel_url'] ?? route('cart.index'),
                'metadata'    => [
                    'transaction_id' => $orderData['transaction_id'] ?? '',
                    'order_id'       => $orderData['order_id'] ?? '',
                ],
            ]);

            return [
                'success'      => true,
                'redirect_url' => $session->url,
                'qr_code'      => null,
                'data'         => ['session_id' => $session->id],
            ];
        } catch (\Exception $e) {
            return [
                'success'      => false,
                'redirect_url' => null,
                'qr_code'      => null,
                'data'         => ['error' => $e->getMessage()],
            ];
        }
    }

    public function verify(array $data): array
    {
        return ['success' => true, 'transaction_id' => $data['transaction_id'] ?? null];
    }

    public function refund(string $transactionId, float $amount): array
    {
        if (!class_exists(\Stripe\StripeClient::class)) {
            return ['success' => false, 'message' => 'Stripe SDK not installed'];
        }
        try {
            $stripe = new \Stripe\StripeClient(self::secretKey());
            $refund = $stripe->refunds->create([
                'payment_intent' => $transactionId,
                'amount'         => (int) ($amount * 100),
            ]);
            return ['success' => true, 'message' => 'Refunded', 'refund_id' => $refund->id];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ----------------------------------------------------------------
    // Legacy shims
    // ----------------------------------------------------------------

    public function createPayment(array $data): array
    {
        return $this->initiate($data);
    }

    public function verifyPayment(string $transactionId): array
    {
        return ['success' => true, 'status' => 'completed', 'transaction_id' => $transactionId];
    }

    public function handleWebhook(array $payload): array
    {
        $event = $payload['type'] ?? '';
        return match ($event) {
            'checkout.session.completed' => $this->handleCheckoutComplete($payload['data']['object'] ?? []),
            default                      => ['success' => true, 'message' => "Unhandled event: {$event}"],
        };
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function handleCheckoutComplete(array $session): array
    {
        $txnId = $session['metadata']['transaction_id'] ?? null;
        if ($txnId) {
            $transaction = \App\Models\PaymentTransaction::where('transaction_id', $txnId)->first();
            if ($transaction) {
                PaymentService::completeTransaction($transaction, $session['payment_intent'] ?? null);
                return ['success' => true, 'message' => 'Payment completed'];
            }
        }
        return ['success' => false, 'message' => 'Transaction not found'];
    }
}
