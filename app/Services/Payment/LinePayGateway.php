<?php

namespace App\Services\Payment;

use App\Models\AppSetting;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LinePayGateway implements PaymentGatewayInterface
{
    // ----------------------------------------------------------------
    // Identity
    // ----------------------------------------------------------------

    public function getName(): string
    {
        return 'LINE Pay';
    }

    public function getMethodType(): string
    {
        return 'line_pay';
    }

    // ----------------------------------------------------------------
    // Availability
    // ----------------------------------------------------------------

    public function isAvailable(): bool
    {
        return !empty(AppSetting::get('line_pay_channel_id', ''))
            && !empty(AppSetting::get('line_pay_channel_secret', ''));
    }

    // ----------------------------------------------------------------
    // Core operations
    // ----------------------------------------------------------------

    public function initiate(array $orderData): array
    {
        try {
            $amount   = (int) ($orderData['amount'] ?? 0);
            $currency = strtoupper($orderData['currency'] ?? 'THB');
            $orderId  = $orderData['order_id'] ?? $orderData['transaction_id'] ?? uniqid('lp_');

            $payload = [
                'amount'   => $amount,
                'currency' => $currency,
                'orderId'  => $orderId,
                'packages' => [
                    [
                        'id'       => 'pkg_' . $orderId,
                        'amount'   => $amount,
                        'name'     => $orderData['description'] ?? 'Photo Order',
                        'products' => [
                            [
                                'name'     => $orderData['description'] ?? 'Photo Order',
                                'quantity' => 1,
                                'price'    => $amount,
                            ],
                        ],
                    ],
                ],
                'redirectUrls' => [
                    'confirmUrl'     => $orderData['success_url'] ?? route('payment.success'),
                    'cancelUrl'      => $orderData['cancel_url'] ?? route('cart.index'),
                ],
            ];

            $requestUri = '/v3/payments/request';
            $nonce      = Str::uuid()->toString();
            $body       = json_encode($payload);
            $signature  = $this->generateSignature($requestUri, $body, $nonce);

            $response = Http::withHeaders($this->buildHeaders($signature, $nonce))
                ->withBody($body, 'application/json')
                ->post($this->baseUrl() . $requestUri);

            if (!$response->successful()) {
                Log::error('LINE Pay request failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                return [
                    'success'      => false,
                    'redirect_url' => null,
                    'qr_code'      => null,
                    'data'         => ['error' => 'LINE Pay request failed: ' . $response->body()],
                ];
            }

            $result     = $response->json();
            $returnCode = $result['returnCode'] ?? '';

            if ($returnCode !== '0000') {
                return [
                    'success'      => false,
                    'redirect_url' => null,
                    'qr_code'      => null,
                    'data'         => ['error' => 'LINE Pay error: ' . ($result['returnMessage'] ?? 'Unknown')],
                ];
            }

            $info = $result['info'] ?? [];

            return [
                'success'      => true,
                'redirect_url' => $info['paymentUrl']['web'] ?? $info['paymentUrl']['app'] ?? null,
                'qr_code'      => null,
                'data'         => [
                    'transaction_id'      => (string) ($info['transactionId'] ?? ''),
                    'payment_access_token' => $info['paymentAccessToken'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('LINE Pay initiate error', ['message' => $e->getMessage()]);
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
            $transactionId = $data['transactionId'] ?? $data['transaction_id'] ?? null;
            if (!$transactionId) {
                return ['success' => false, 'transaction_id' => null, 'message' => 'Missing transactionId'];
            }

            $amount   = (int) ($data['amount'] ?? 0);
            $currency = strtoupper($data['currency'] ?? 'THB');

            $payload = [
                'amount'   => $amount,
                'currency' => $currency,
            ];

            $requestUri = "/v3/payments/requests/{$transactionId}/confirm";
            $nonce      = Str::uuid()->toString();
            $body       = json_encode($payload);
            $signature  = $this->generateSignature($requestUri, $body, $nonce);

            $response = Http::withHeaders($this->buildHeaders($signature, $nonce))
                ->withBody($body, 'application/json')
                ->post($this->baseUrl() . $requestUri);

            if (!$response->successful()) {
                Log::error('LINE Pay confirm failed', [
                    'transactionId' => $transactionId,
                    'status'        => $response->status(),
                    'body'          => $response->json(),
                ]);
                return [
                    'success'        => false,
                    'transaction_id' => $transactionId,
                    'message'        => 'Confirm request failed',
                ];
            }

            $result     = $response->json();
            $returnCode = $result['returnCode'] ?? '';

            return [
                'success'        => $returnCode === '0000',
                'transaction_id' => $transactionId,
                'status'         => $returnCode === '0000' ? 'completed' : 'failed',
                'message'        => $result['returnMessage'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('LINE Pay verify error', ['message' => $e->getMessage()]);
            return ['success' => false, 'transaction_id' => null, 'message' => $e->getMessage()];
        }
    }

    public function refund(string $transactionId, float $amount): array
    {
        try {
            $payload = [
                'refundAmount' => (int) $amount,
            ];

            $requestUri = "/v3/payments/{$transactionId}/refund";
            $nonce      = Str::uuid()->toString();
            $body       = json_encode($payload);
            $signature  = $this->generateSignature($requestUri, $body, $nonce);

            $response = Http::withHeaders($this->buildHeaders($signature, $nonce))
                ->withBody($body, 'application/json')
                ->post($this->baseUrl() . $requestUri);

            if (!$response->successful()) {
                Log::error('LINE Pay refund failed', [
                    'transactionId' => $transactionId,
                    'status'        => $response->status(),
                    'body'          => $response->json(),
                ]);
                return ['success' => false, 'message' => 'Refund request failed: ' . $response->body()];
            }

            $result     = $response->json();
            $returnCode = $result['returnCode'] ?? '';

            return [
                'success'   => $returnCode === '0000',
                'message'   => $result['returnMessage'] ?? 'Refund processed',
                'refund_id' => $result['info']['refundTransactionId'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('LINE Pay refund error', ['message' => $e->getMessage()]);
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
        // LINE Pay requires amount for confirm; attempt a status check
        try {
            $requestUri = "/v3/payments/requests/{$transactionId}/check";
            $nonce      = Str::uuid()->toString();
            $signature  = $this->generateSignature($requestUri, '', $nonce);

            $response = Http::withHeaders($this->buildHeaders($signature, $nonce))
                ->get($this->baseUrl() . $requestUri);

            $result     = $response->json();
            $returnCode = $result['returnCode'] ?? '';

            return [
                'success'        => $returnCode === '0000',
                'status'         => $returnCode === '0000' ? 'completed' : 'pending',
                'transaction_id' => $transactionId,
            ];
        } catch (\Exception $e) {
            Log::error('LINE Pay verifyPayment error', ['message' => $e->getMessage()]);
            return ['success' => false, 'status' => 'error', 'transaction_id' => $transactionId];
        }
    }

    public function handleWebhook(array $payload): array
    {
        $transactionId = $payload['transactionId'] ?? $payload['transaction_id'] ?? null;
        $orderId       = $payload['orderId'] ?? $payload['order_id'] ?? null;

        if (!$transactionId) {
            return ['success' => false, 'message' => 'Missing transactionId in webhook'];
        }

        // Look up the internal transaction
        $transaction = null;
        if ($orderId) {
            $transaction = PaymentTransaction::where('transaction_id', $orderId)->first();
        }
        if (!$transaction) {
            $transaction = PaymentTransaction::where('gateway_reference', $transactionId)->first();
        }

        if ($transaction) {
            PaymentService::completeTransaction($transaction, $transactionId);
            return ['success' => true, 'message' => 'Payment confirmed'];
        }

        Log::warning('LINE Pay webhook: transaction not found', [
            'transactionId' => $transactionId,
            'orderId'       => $orderId,
        ]);
        return ['success' => false, 'message' => 'Transaction not found'];
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function isSandbox(): bool
    {
        return (bool) AppSetting::get('line_pay_sandbox', true);
    }

    private function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://sandbox-api-pay.line.me'
            : 'https://api-pay.line.me';
    }

    /**
     * Generate HMAC-SHA256 signature for LINE Pay API v3.
     *
     * Signature = Base64(HMAC-SHA256(channelSecret, channelSecret + requestUri + body + nonce))
     */
    private function generateSignature(string $requestUri, string $body, string $nonce): string
    {
        $channelSecret = AppSetting::get('line_pay_channel_secret', '');
        $message       = $channelSecret . $requestUri . $body . $nonce;

        return base64_encode(hash_hmac('sha256', $message, $channelSecret, true));
    }

    /**
     * Build the required LINE Pay API headers.
     */
    private function buildHeaders(string $signature, string $nonce): array
    {
        return [
            'Content-Type'           => 'application/json',
            'X-LINE-ChannelId'       => AppSetting::get('line_pay_channel_id', ''),
            'X-LINE-Authorization-Nonce' => $nonce,
            'X-LINE-Authorization'   => $signature,
        ];
    }
}
