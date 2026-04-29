<?php

namespace App\Services\Payment;

use App\Models\AppSetting;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrueMoneyGateway implements PaymentGatewayInterface
{
    // ----------------------------------------------------------------
    // Identity
    // ----------------------------------------------------------------

    public function getName(): string
    {
        return 'TrueMoney Wallet';
    }

    public function getMethodType(): string
    {
        return 'truemoney';
    }

    // ----------------------------------------------------------------
    // Availability
    // ----------------------------------------------------------------

    public function isAvailable(): bool
    {
        // TrueMoney Wallet's public merchant API doesn't match the
        // endpoints this gateway currently posts to (`api.truemoney.com/api/v1/...`).
        // Real merchant integration runs through TrueMoney's developer
        // portal (Ascend Money / TMN Plus) with a different contract.
        // Until we onboard officially, this gateway is disabled by
        // default. To force-enable for testing, set
        // `truemoney_force_enable = '1'` in AppSettings.
        if (AppSetting::get('truemoney_force_enable', '0') !== '1') {
            return false;
        }

        return !empty(AppSetting::get('truemoney_merchant_id', ''))
            && !empty(AppSetting::get('truemoney_secret', ''));
    }

    // ----------------------------------------------------------------
    // Core operations
    // ----------------------------------------------------------------

    public function initiate(array $orderData): array
    {
        try {
            $merchantId = AppSetting::get('truemoney_merchant_id', '');
            $secret     = AppSetting::get('truemoney_secret', '');

            $amount        = number_format($orderData['amount'] ?? 0, 2, '.', '');
            $currency      = strtoupper($orderData['currency'] ?? 'THB');
            $transactionId = $orderData['transaction_id'] ?? $orderData['order_id'] ?? uniqid('tm_');
            $timestamp     = now()->format('Y-m-d\TH:i:s\Z');

            $payload = [
                'merchant_id'    => $merchantId,
                'transaction_id' => $transactionId,
                'amount'         => $amount,
                'currency'       => $currency,
                'description'    => $orderData['description'] ?? 'Photo Order',
                'customer_email' => $orderData['customer_email'] ?? '',
                'return_url'     => $orderData['success_url'] ?? route('payment.success'),
                'cancel_url'     => $orderData['cancel_url'] ?? route('cart.index'),
                'notify_url'     => route('payment.webhook', ['gateway' => 'truemoney']),
                'timestamp'      => $timestamp,
            ];

            // Sign the request
            $payload['signature'] = $this->generateSignature($payload, $secret);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post($this->baseUrl() . '/api/v1/payment/create', $payload);

            if (!$response->successful()) {
                Log::error('TrueMoney create payment failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                return [
                    'success'      => false,
                    'redirect_url' => null,
                    'qr_code'      => null,
                    'data'         => ['error' => 'TrueMoney payment creation failed: ' . ($response->json('message') ?? $response->body())],
                ];
            }

            $result = $response->json();
            $code   = $result['code'] ?? $result['status_code'] ?? '';

            if (!in_array($code, ['0', '0000', 'SUCCESS', 200], true) && !($result['success'] ?? false)) {
                // Accept if there is a redirect URL even without a "success" code
                if (empty($result['redirect_url']) && empty($result['payment_url']) && empty($result['data']['redirect_url'])) {
                    return [
                        'success'      => false,
                        'redirect_url' => null,
                        'qr_code'      => null,
                        'data'         => ['error' => 'TrueMoney error: ' . ($result['message'] ?? json_encode($result))],
                    ];
                }
            }

            $redirectUrl = $result['redirect_url']
                ?? $result['payment_url']
                ?? $result['data']['redirect_url']
                ?? $result['data']['payment_url']
                ?? null;

            return [
                'success'      => true,
                'redirect_url' => $redirectUrl,
                'qr_code'      => $result['qr_code'] ?? $result['data']['qr_code'] ?? null,
                'data'         => [
                    'truemoney_transaction_id' => $result['transaction_id'] ?? $result['data']['transaction_id'] ?? $transactionId,
                    'reference'                => $result['reference'] ?? $result['data']['reference'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('TrueMoney initiate error', ['message' => $e->getMessage()]);
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
            $merchantId    = AppSetting::get('truemoney_merchant_id', '');
            $secret        = AppSetting::get('truemoney_secret', '');
            $transactionId = $data['transaction_id'] ?? $data['truemoney_transaction_id'] ?? null;

            if (!$transactionId) {
                return ['success' => false, 'transaction_id' => null, 'message' => 'Missing transaction ID'];
            }

            $timestamp = now()->format('Y-m-d\TH:i:s\Z');

            $queryPayload = [
                'merchant_id'    => $merchantId,
                'transaction_id' => $transactionId,
                'timestamp'      => $timestamp,
            ];
            $queryPayload['signature'] = $this->generateSignature($queryPayload, $secret);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post($this->baseUrl() . '/api/v1/payment/status', $queryPayload);

            if (!$response->successful()) {
                Log::error('TrueMoney verify failed', [
                    'transaction_id' => $transactionId,
                    'status'         => $response->status(),
                    'body'           => $response->json(),
                ]);
                return [
                    'success'        => false,
                    'transaction_id' => $transactionId,
                    'message'        => 'Status check failed',
                ];
            }

            $result = $response->json();
            $status = strtolower($result['status'] ?? $result['data']['status'] ?? '');

            return [
                'success'        => in_array($status, ['completed', 'success', 'paid']),
                'transaction_id' => $transactionId,
                'status'         => $status,
                'message'        => $result['message'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('TrueMoney verify error', ['message' => $e->getMessage()]);
            return ['success' => false, 'transaction_id' => null, 'message' => $e->getMessage()];
        }
    }

    public function refund(string $transactionId, float $amount): array
    {
        try {
            $merchantId = AppSetting::get('truemoney_merchant_id', '');
            $secret     = AppSetting::get('truemoney_secret', '');
            $timestamp  = now()->format('Y-m-d\TH:i:s\Z');

            $payload = [
                'merchant_id'    => $merchantId,
                'transaction_id' => $transactionId,
                'amount'         => number_format($amount, 2, '.', ''),
                'currency'       => 'THB',
                'timestamp'      => $timestamp,
            ];
            $payload['signature'] = $this->generateSignature($payload, $secret);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post($this->baseUrl() . '/api/v1/payment/refund', $payload);

            if (!$response->successful()) {
                Log::error('TrueMoney refund failed', [
                    'transaction_id' => $transactionId,
                    'status'         => $response->status(),
                    'body'           => $response->json(),
                ]);
                return ['success' => false, 'message' => 'Refund request failed: ' . ($response->json('message') ?? $response->body())];
            }

            $result = $response->json();
            $status = strtolower($result['status'] ?? $result['data']['status'] ?? '');

            return [
                'success'   => in_array($status, ['completed', 'success', 'refunded']),
                'message'   => $result['message'] ?? 'Refund processed',
                'refund_id' => $result['refund_id'] ?? $result['data']['refund_id'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('TrueMoney refund error', ['message' => $e->getMessage()]);
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
        $result = $this->verify(['transaction_id' => $transactionId]);

        return [
            'success'        => $result['success'],
            'status'         => $result['status'] ?? ($result['success'] ? 'completed' : 'pending'),
            'transaction_id' => $transactionId,
        ];
    }

    public function handleWebhook(array $payload): array
    {
        $transactionId = $payload['transaction_id'] ?? null;
        $status        = strtolower($payload['status'] ?? '');
        $signature     = $payload['signature'] ?? null;

        if (!$transactionId) {
            return ['success' => false, 'message' => 'Missing transaction_id in webhook'];
        }

        // Verify the webhook signature
        if ($signature) {
            $secret = AppSetting::get('truemoney_secret', '');
            $checkPayload = $payload;
            unset($checkPayload['signature']);
            $expectedSignature = $this->generateSignature($checkPayload, $secret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('TrueMoney webhook: invalid signature', ['transaction_id' => $transactionId]);
                return ['success' => false, 'message' => 'Invalid signature'];
            }
        }

        if (!in_array($status, ['completed', 'success', 'paid'])) {
            Log::info('TrueMoney webhook: non-completed status', [
                'transaction_id' => $transactionId,
                'status'         => $status,
            ]);
            return ['success' => true, 'message' => "Payment status: {$status}"];
        }

        $transaction = PaymentTransaction::where('transaction_id', $transactionId)->first();

        if ($transaction) {
            PaymentService::completeTransaction(
                $transaction,
                $payload['reference'] ?? $payload['truemoney_transaction_id'] ?? $transactionId
            );
            return ['success' => true, 'message' => 'Payment completed'];
        }

        Log::warning('TrueMoney webhook: transaction not found', ['transaction_id' => $transactionId]);
        return ['success' => false, 'message' => 'Transaction not found'];
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function isSandbox(): bool
    {
        return (bool) AppSetting::get('truemoney_sandbox', true);
    }

    private function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://sandbox-api.truemoney.com'
            : 'https://api.truemoney.com';
    }

    /**
     * Generate HMAC-SHA256 signature for TrueMoney API requests.
     *
     * Sorts payload keys alphabetically, concatenates key=value pairs, then HMAC signs.
     */
    private function generateSignature(array $payload, string $secret): string
    {
        // Remove any existing signature from the payload
        unset($payload['signature']);

        // Sort by keys
        ksort($payload);

        // Build the signing string
        $parts = [];
        foreach ($payload as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        $signingString = implode('&', $parts);

        return hash_hmac('sha256', $signingString, $secret);
    }
}
