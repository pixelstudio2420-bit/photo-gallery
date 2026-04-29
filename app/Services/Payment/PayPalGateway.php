<?php

namespace App\Services\Payment;

use App\Models\AppSetting;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalGateway implements PaymentGatewayInterface
{
    // ----------------------------------------------------------------
    // Identity
    // ----------------------------------------------------------------

    public function getName(): string
    {
        return 'PayPal';
    }

    public function getMethodType(): string
    {
        return 'paypal';
    }

    // ----------------------------------------------------------------
    // Availability
    // ----------------------------------------------------------------

    public function isAvailable(): bool
    {
        return !empty(AppSetting::get('paypal_client_id', ''))
            && !empty(AppSetting::get('paypal_secret', ''));
    }

    // ----------------------------------------------------------------
    // Core operations
    // ----------------------------------------------------------------

    public function initiate(array $orderData): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return [
                    'success'      => false,
                    'redirect_url' => null,
                    'qr_code'      => null,
                    'data'         => ['error' => 'Failed to obtain PayPal access token'],
                ];
            }

            $amount   = number_format($orderData['amount'] ?? 0, 2, '.', '');
            $currency = strtoupper($orderData['currency'] ?? 'THB');

            $payload = [
                'intent'         => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $orderData['transaction_id'] ?? $orderData['order_id'] ?? uniqid('pp_'),
                        'description'  => $orderData['description'] ?? 'Photo Order',
                        'amount'       => [
                            'currency_code' => $currency,
                            'value'         => $amount,
                        ],
                    ],
                ],
                'application_context' => [
                    'return_url' => $orderData['success_url'] ?? route('payment.success'),
                    'cancel_url' => $orderData['cancel_url'] ?? route('cart.index'),
                    'brand_name' => \App\Models\AppSetting::get('site_name') ?: (string) config('app.name', 'Photo Gallery'),
                    'user_action' => 'PAY_NOW',
                ],
            ];

            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->baseUrl() . '/v2/checkout/orders', $payload);

            if (!$response->successful()) {
                Log::error('PayPal create order failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                return [
                    'success'      => false,
                    'redirect_url' => null,
                    'qr_code'      => null,
                    'data'         => ['error' => 'PayPal order creation failed: ' . ($response->json('message') ?? $response->body())],
                ];
            }

            $order       = $response->json();
            $approvalUrl = null;

            foreach ($order['links'] ?? [] as $link) {
                if ($link['rel'] === 'approve') {
                    $approvalUrl = $link['href'];
                    break;
                }
            }

            return [
                'success'      => true,
                'redirect_url' => $approvalUrl,
                'qr_code'      => null,
                'data'         => [
                    'paypal_order_id' => $order['id'] ?? null,
                    'status'          => $order['status'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('PayPal initiate error', ['message' => $e->getMessage()]);
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
        try {
            $orderId = $data['paypal_order_id'] ?? $data['token'] ?? null;
            if (!$orderId) {
                return ['success' => false, 'transaction_id' => null, 'message' => 'Missing PayPal order ID'];
            }

            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'transaction_id' => null, 'message' => 'Failed to obtain access token'];
            }

            // Capture the order
            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->baseUrl() . "/v2/checkout/orders/{$orderId}/capture", []);

            if (!$response->successful()) {
                Log::error('PayPal capture failed', [
                    'order_id' => $orderId,
                    'status'   => $response->status(),
                    'body'     => $response->json(),
                ]);
                return [
                    'success'        => false,
                    'transaction_id' => null,
                    'message'        => 'Capture failed: ' . ($response->json('message') ?? $response->body()),
                ];
            }

            $capture = $response->json();
            $status  = $capture['status'] ?? '';

            // Extract capture ID from the first purchase unit
            $captureId = null;
            $captures  = $capture['purchase_units'][0]['payments']['captures'] ?? [];
            if (!empty($captures)) {
                $captureId = $captures[0]['id'] ?? null;
            }

            return [
                'success'        => $status === 'COMPLETED',
                'transaction_id' => $captureId ?? $orderId,
                'paypal_order_id' => $orderId,
                'capture_id'     => $captureId,
                'status'         => $status,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal verify error', ['message' => $e->getMessage()]);
            return ['success' => false, 'transaction_id' => null, 'message' => $e->getMessage()];
        }
    }

    public function refund(string $transactionId, float $amount): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'message' => 'Failed to obtain access token'];
            }

            $payload = [
                'amount' => [
                    'value'         => number_format($amount, 2, '.', ''),
                    'currency_code' => 'THB',
                ],
            ];

            // transactionId here should be the capture ID
            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->baseUrl() . "/v2/payments/captures/{$transactionId}/refund", $payload);

            if (!$response->successful()) {
                Log::error('PayPal refund failed', [
                    'capture_id' => $transactionId,
                    'status'     => $response->status(),
                    'body'       => $response->json(),
                ]);
                return [
                    'success' => false,
                    'message' => 'Refund failed: ' . ($response->json('message') ?? $response->body()),
                ];
            }

            $refund = $response->json();

            return [
                'success'   => ($refund['status'] ?? '') === 'COMPLETED',
                'message'   => 'Refund ' . ($refund['status'] ?? 'submitted'),
                'refund_id' => $refund['id'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal refund error', ['message' => $e->getMessage()]);
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
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'status' => 'error', 'transaction_id' => $transactionId];
            }

            $response = Http::withToken($token)
                ->get($this->baseUrl() . "/v2/checkout/orders/{$transactionId}");

            if (!$response->successful()) {
                return ['success' => false, 'status' => 'error', 'transaction_id' => $transactionId];
            }

            $order  = $response->json();
            $status = $order['status'] ?? 'UNKNOWN';

            return [
                'success'        => in_array($status, ['COMPLETED', 'APPROVED']),
                'status'         => strtolower($status),
                'transaction_id' => $transactionId,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal verifyPayment error', ['message' => $e->getMessage()]);
            return ['success' => false, 'status' => 'error', 'transaction_id' => $transactionId];
        }
    }

    public function handleWebhook(array $payload): array
    {
        $eventType = $payload['event_type'] ?? '';

        return match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($payload),
            'PAYMENT.CAPTURE.REFUNDED'  => $this->handleCaptureRefunded($payload),
            default                     => ['success' => true, 'message' => "Unhandled event: {$eventType}"],
        };
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function isSandbox(): bool
    {
        return (bool) AppSetting::get('paypal_sandbox', true);
    }

    private function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Obtain a Bearer access token via OAuth2 client credentials.
     *
     * Wrapped in a circuit breaker — without it, PayPal-side OAuth
     * latency would block every checkout request waiting on a token.
     * Once the breaker is OPEN we fall back to null and the caller
     * surfaces "PayPal unavailable, please try another method".
     */
    private function getAccessToken(): ?string
    {
        $clientId = AppSetting::get('paypal_client_id', '');
        $secret   = AppSetting::get('paypal_secret', '');

        $cb = new CircuitBreaker('paypal');

        return $cb->call(
            function () use ($clientId, $secret) {
                $response = Http::withBasicAuth($clientId, $secret)
                    ->timeout(15)
                    ->asForm()
                    ->post($this->baseUrl() . '/v1/oauth2/token', [
                        'grant_type' => 'client_credentials',
                    ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                // 4xx/5xx → trip the breaker via thrown exception.
                Log::error('PayPal OAuth token request failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                throw new \RuntimeException('PayPal OAuth ' . $response->status());
            },
            fallback: null,
        );
    }

    private function handleCaptureCompleted(array $payload): array
    {
        $resource  = $payload['resource'] ?? [];
        $captureId = $resource['id'] ?? null;

        // Look up by the reference_id in the supplementary data or custom_id
        $customId = $resource['custom_id'] ?? null;
        $invoiceId = $resource['invoice_id'] ?? null;
        $lookupId = $customId ?? $invoiceId;

        if ($lookupId) {
            $transaction = PaymentTransaction::where('transaction_id', $lookupId)->first();
        } else {
            // Fallback: search by gateway_reference
            $transaction = PaymentTransaction::where('gateway_reference', $captureId)->first();
        }

        if ($transaction) {
            PaymentService::completeTransaction($transaction, $captureId);
            return ['success' => true, 'message' => 'Payment completed'];
        }

        Log::warning('PayPal webhook: transaction not found', ['capture_id' => $captureId]);
        return ['success' => false, 'message' => 'Transaction not found'];
    }

    private function handleCaptureRefunded(array $payload): array
    {
        $resource = $payload['resource'] ?? [];
        Log::info('PayPal refund webhook received', ['refund_id' => $resource['id'] ?? null]);
        return ['success' => true, 'message' => 'Refund notification processed'];
    }
}
