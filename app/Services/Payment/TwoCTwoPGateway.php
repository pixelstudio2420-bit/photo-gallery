<?php

namespace App\Services\Payment;

use App\Models\AppSetting;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwoCTwoPGateway implements PaymentGatewayInterface
{
    // ----------------------------------------------------------------
    // Identity
    // ----------------------------------------------------------------

    public function getName(): string
    {
        return '2C2P';
    }

    public function getMethodType(): string
    {
        return 'two_c_two_p';
    }

    // ----------------------------------------------------------------
    // Availability
    // ----------------------------------------------------------------

    public function isAvailable(): bool
    {
        return !empty(AppSetting::get('2c2p_merchant_id', ''))
            && !empty(AppSetting::get('2c2p_secret_key', ''));
    }

    // ----------------------------------------------------------------
    // Core operations
    // ----------------------------------------------------------------

    public function initiate(array $orderData): array
    {
        try {
            $merchantId = AppSetting::get('2c2p_merchant_id', '');
            $secretKey  = AppSetting::get('2c2p_secret_key', '');

            $amount        = number_format($orderData['amount'] ?? 0, 2, '.', '');
            $currency      = $this->getCurrencyCode($orderData['currency'] ?? 'THB');
            $transactionId = $orderData['transaction_id'] ?? $orderData['order_id'] ?? uniqid('2c2p_');

            $payloadData = [
                'merchantID'  => $merchantId,
                'invoiceNo'   => $transactionId,
                'description' => $orderData['description'] ?? 'Photo Order',
                'amount'      => $amount,
                'currencyCode'=> $currency,
                'frontendReturnUrl' => $orderData['success_url'] ?? route('payment.success'),
                'backendReturnUrl'  => route('payment.webhook', ['gateway' => 'two_c_two_p']),
            ];

            if (!empty($orderData['customer_email'])) {
                $payloadData['customerEmail'] = $orderData['customer_email'];
            }

            // Encode payload as JWT
            $jwtToken = $this->encodeJwt($payloadData, $secretKey);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post($this->baseUrl() . '/payment/4.3/paymentToken', [
                'payload' => $jwtToken,
            ]);

            if (!$response->successful()) {
                Log::error('2C2P PaymentToken request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [
                    'success'      => false,
                    'redirect_url' => null,
                    'qr_code'      => null,
                    'data'         => ['error' => '2C2P payment token request failed'],
                ];
            }

            $responseBody = $response->json();
            $responsePayload = $responseBody['payload'] ?? null;

            if (!$responsePayload) {
                return [
                    'success'      => false,
                    'redirect_url' => null,
                    'qr_code'      => null,
                    'data'         => ['error' => '2C2P returned empty payload'],
                ];
            }

            // Decode the response JWT
            $decoded  = $this->decodeJwt($responsePayload, $secretKey);
            $respCode = $decoded['respCode'] ?? '';

            if ($respCode !== '0000') {
                return [
                    'success'      => false,
                    'redirect_url' => null,
                    'qr_code'      => null,
                    'data'         => ['error' => '2C2P error [' . $respCode . ']: ' . ($decoded['respDesc'] ?? 'Unknown')],
                ];
            }

            $webPaymentUrl = $decoded['webPaymentUrl'] ?? null;
            $paymentToken  = $decoded['paymentToken'] ?? null;

            return [
                'success'      => true,
                'redirect_url' => $webPaymentUrl,
                'qr_code'      => null,
                'data'         => [
                    'payment_token' => $paymentToken,
                    'invoice_no'    => $transactionId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('2C2P initiate error', ['message' => $e->getMessage()]);
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
            $merchantId = AppSetting::get('2c2p_merchant_id', '');
            $secretKey  = AppSetting::get('2c2p_secret_key', '');

            $invoiceNo = $data['invoice_no'] ?? $data['transaction_id'] ?? null;
            if (!$invoiceNo) {
                return ['success' => false, 'transaction_id' => null, 'message' => 'Missing invoice number'];
            }

            $payloadData = [
                'merchantID' => $merchantId,
                'invoiceNo'  => $invoiceNo,
            ];

            $jwtToken = $this->encodeJwt($payloadData, $secretKey);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post($this->baseUrl() . '/payment/4.3/paymentInquiry', [
                'payload' => $jwtToken,
            ]);

            if (!$response->successful()) {
                Log::error('2C2P inquiry failed', [
                    'invoice_no' => $invoiceNo,
                    'status'     => $response->status(),
                    'body'       => $response->body(),
                ]);
                return [
                    'success'        => false,
                    'transaction_id' => $invoiceNo,
                    'message'        => 'Inquiry request failed',
                ];
            }

            $responseBody    = $response->json();
            $responsePayload = $responseBody['payload'] ?? null;

            if (!$responsePayload) {
                return ['success' => false, 'transaction_id' => $invoiceNo, 'message' => 'Empty response payload'];
            }

            $decoded  = $this->decodeJwt($responsePayload, $secretKey);
            $respCode = $decoded['respCode'] ?? '';

            // 2C2P uses respCode '0000' for successful payment
            $isSuccess = $respCode === '0000';

            return [
                'success'        => $isSuccess,
                'transaction_id' => $decoded['tranRef'] ?? $invoiceNo,
                'invoice_no'     => $invoiceNo,
                'status'         => $isSuccess ? 'completed' : ($decoded['respDesc'] ?? 'pending'),
                'resp_code'      => $respCode,
                'amount'         => $decoded['amount'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('2C2P verify error', ['message' => $e->getMessage()]);
            return ['success' => false, 'transaction_id' => null, 'message' => $e->getMessage()];
        }
    }

    public function refund(string $transactionId, float $amount): array
    {
        try {
            $merchantId = AppSetting::get('2c2p_merchant_id', '');
            $secretKey  = AppSetting::get('2c2p_secret_key', '');

            $payloadData = [
                'merchantID'  => $merchantId,
                'invoiceNo'   => $transactionId,
                'actionAmount'=> number_format($amount, 2, '.', ''),
                'processType' => 'R', // R = Refund
            ];

            $jwtToken = $this->encodeJwt($payloadData, $secretKey);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post($this->baseUrl() . '/payment/4.3/refund', [
                'payload' => $jwtToken,
            ]);

            if (!$response->successful()) {
                Log::error('2C2P refund failed', [
                    'invoice_no' => $transactionId,
                    'status'     => $response->status(),
                    'body'       => $response->body(),
                ]);
                return ['success' => false, 'message' => 'Refund request failed'];
            }

            $responseBody    = $response->json();
            $responsePayload = $responseBody['payload'] ?? null;

            if (!$responsePayload) {
                return ['success' => false, 'message' => 'Empty response payload'];
            }

            $decoded  = $this->decodeJwt($responsePayload, $secretKey);
            $respCode = $decoded['respCode'] ?? '';

            return [
                'success'   => $respCode === '0000',
                'message'   => $decoded['respDesc'] ?? ($respCode === '0000' ? 'Refund successful' : 'Refund failed'),
                'refund_id' => $decoded['tranRef'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('2C2P refund error', ['message' => $e->getMessage()]);
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
        $result = $this->verify(['invoice_no' => $transactionId]);

        return [
            'success'        => $result['success'],
            'status'         => $result['success'] ? 'completed' : 'pending',
            'transaction_id' => $transactionId,
        ];
    }

    public function handleWebhook(array $payload): array
    {
        try {
            $secretKey = AppSetting::get('2c2p_secret_key', '');

            // 2C2P sends a JWT payload in the callback
            $jwtPayload = $payload['payload'] ?? null;

            if (!$jwtPayload) {
                Log::warning('2C2P webhook: missing payload');
                return ['success' => false, 'message' => 'Missing JWT payload'];
            }

            $decoded  = $this->decodeJwt($jwtPayload, $secretKey);
            $respCode = $decoded['respCode'] ?? '';
            $invoiceNo = $decoded['invoiceNo'] ?? null;
            $tranRef   = $decoded['tranRef'] ?? null;

            if ($respCode !== '0000') {
                Log::info('2C2P webhook: non-success response', [
                    'respCode' => $respCode,
                    'respDesc' => $decoded['respDesc'] ?? '',
                    'invoiceNo' => $invoiceNo,
                ]);
                return ['success' => true, 'message' => "Payment status: {$respCode}"];
            }

            if (!$invoiceNo) {
                return ['success' => false, 'message' => 'Missing invoiceNo in decoded payload'];
            }

            $transaction = PaymentTransaction::where('transaction_id', $invoiceNo)->first();

            if ($transaction) {
                PaymentService::completeTransaction($transaction, $tranRef ?? $invoiceNo);
                return ['success' => true, 'message' => 'Payment completed'];
            }

            Log::warning('2C2P webhook: transaction not found', ['invoiceNo' => $invoiceNo]);
            return ['success' => false, 'message' => 'Transaction not found'];
        } catch (\Exception $e) {
            Log::error('2C2P webhook error', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function isSandbox(): bool
    {
        return (bool) AppSetting::get('2c2p_sandbox', true);
    }

    private function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://sandbox-pgw.2c2p.com'
            : 'https://pgw.2c2p.com';
    }

    /**
     * Get ISO 4217 numeric currency code.
     */
    private function getCurrencyCode(string $currency): string
    {
        $codes = [
            'THB' => '764',
            'USD' => '840',
            'EUR' => '978',
            'GBP' => '826',
            'JPY' => '392',
            'SGD' => '702',
            'MYR' => '458',
            'IDR' => '360',
            'PHP' => '608',
        ];

        return $codes[strtoupper($currency)] ?? '764'; // Default THB
    }

    /**
     * Encode a payload as a JWT (HS256) for 2C2P API requests.
     */
    private function encodeJwt(array $payload, string $secret): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $headerEncoded  = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signature        = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    /**
     * Decode and verify a JWT (HS256) from 2C2P API responses.
     *
     * @throws \RuntimeException if signature is invalid
     */
    private function decodeJwt(string $jwt, string $secret): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid JWT format');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $expectedSignature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true);
        $actualSignature   = $this->base64UrlDecode($signatureEncoded);

        if (!hash_equals($expectedSignature, $actualSignature)) {
            Log::warning('2C2P JWT signature verification failed');
            throw new \RuntimeException('Invalid JWT signature');
        }

        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to decode JWT payload: ' . json_last_error_msg());
        }

        return $payload;
    }

    /**
     * Base64 URL-safe encode.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode.
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
