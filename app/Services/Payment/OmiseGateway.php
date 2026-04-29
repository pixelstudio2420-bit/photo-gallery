<?php

namespace App\Services\Payment;

use App\Models\AppSetting;

class OmiseGateway implements PaymentGatewayInterface
{
    public function getName(): string       { return 'Omise (บัตรเครดิต)'; }
    public function getMethodType(): string { return 'omise'; }

    public function isAvailable(): bool
    {
        return !empty(AppSetting::get('omise_public_key', ''));
    }

    public function initiate(array $orderData): array
    {
        $secretKey = AppSetting::get('omise_secret_key', '');
        if (empty($secretKey)) {
            return [
                'success'      => false,
                'redirect_url' => null,
                'qr_code'      => null,
                'data'         => ['error' => 'Omise secret key not configured'],
            ];
        }

        try {
            $charge = $this->createCharge([
                'amount'      => (int) (($orderData['amount'] ?? 0) * 100),
                'currency'    => 'thb',
                'card'        => $orderData['token'] ?? null,
                'description' => $orderData['description'] ?? 'Photo Order',
                'metadata'    => [
                    'transaction_id' => $orderData['transaction_id'] ?? '',
                    'order_id'       => $orderData['order_id'] ?? '',
                ],
                'return_uri' => $orderData['success_url'] ?? route('payment.success'),
            ], $secretKey);

            if (!empty($charge['authorize_uri'])) {
                return [
                    'success'      => true,
                    'redirect_url' => $charge['authorize_uri'],
                    'qr_code'      => null,
                    'data'         => ['charge_id' => $charge['id'] ?? null],
                ];
            }

            return [
                'success'      => ($charge['status'] ?? '') === 'successful',
                'redirect_url' => null,
                'qr_code'      => null,
                'data'         => $charge,
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
        $secretKey = AppSetting::get('omise_secret_key', '');
        if (empty($secretKey)) {
            return ['success' => false, 'message' => 'Omise secret key not configured'];
        }

        // Wrap the network call so a brownout at api.omise.co doesn't lock
        // up admin refund requests for 15s × N until PHP-FPM saturates.
        // Threshold 5 / cooldown 60 mirrors the breaker on charge create.
        // Body-shape errors are re-thrown inside the closure so the
        // breaker counts them as failures (otherwise call() would mark
        // success and reset the failure counter — undermining the trip).
        $cb = new CircuitBreaker('omise');
        $data = $cb->call(
            function () use ($secretKey, $transactionId, $amount) {
                $response = \Illuminate\Support\Facades\Http::withBasicAuth($secretKey, '')
                    ->timeout(15)
                    ->post("https://api.omise.co/charges/{$transactionId}/refunds", [
                        'amount' => (int) ($amount * 100),
                    ]);
                $body = $response->json();
                if (($body['object'] ?? '') === 'error' || !$response->successful()) {
                    throw new \RuntimeException(
                        'omise.refund: ' . ($body['message'] ?? 'http ' . $response->status())
                    );
                }
                return $body;
            },
            fallback: null,
        );

        if ($data === null) {
            return ['success' => false, 'message' => 'Omise temporarily unavailable — please retry'];
        }

        if (($data['object'] ?? '') === 'refund') {
            return [
                'success'   => true,
                'message'   => 'Refund processed',
                'refund_id' => $data['id'] ?? null,
            ];
        }

        return [
            'success' => false,
            'message' => $data['message'] ?? $data['location'] ?? 'Omise refund failed',
        ];
    }

    // Legacy shims
    public function createPayment(array $data): array          { return $this->initiate($data); }
    public function verifyPayment(string $transactionId): array { return ['success' => true, 'status' => 'completed', 'transaction_id' => $transactionId]; }

    public function handleWebhook(array $payload): array
    {
        $event = $payload['key'] ?? '';
        if ($event === 'charge.complete') {
            $charge = $payload['data'] ?? [];
            $txnId  = $charge['metadata']['transaction_id'] ?? null;
            if ($txnId && ($charge['status'] ?? '') === 'successful') {
                $transaction = \App\Models\PaymentTransaction::where('transaction_id', $txnId)->first();
                if ($transaction) {
                    PaymentService::completeTransaction($transaction, $charge['id'] ?? null);
                    return ['success' => true, 'message' => 'Payment completed'];
                }
            }
        }
        return ['success' => false, 'message' => 'Unhandled event'];
    }

    private function createCharge(array $params, string $secretKey): array
    {
        // Single source of breaker truth for Omise — both charge create
        // and refund route through the same `omise` key, so a sustained
        // outage at api.omise.co opens the breaker once and protects all
        // call sites. Timeout=15s caps the worst case on a closed
        // breaker; once OPEN, calls return in <1ms.
        $cb = new CircuitBreaker('omise');
        $body = $cb->call(
            fn() => \Illuminate\Support\Facades\Http::withBasicAuth($secretKey, '')
                ->timeout(15)
                ->post('https://api.omise.co/charges', $params)
                ->json(),
            fallback: null,
        );

        if ($body === null) {
            // Surface a structured "unavailable" charge that the caller
            // already knows how to handle as a non-success path.
            return [
                'object' => 'error',
                'status' => 'unavailable',
                'message' => 'Omise temporarily unavailable',
            ];
        }
        return $body;
    }
}
