<?php

namespace App\Services\Payment;

/**
 * ManualGateway — Admin marks orders as paid manually.
 * Always available; no external API required.
 */
class ManualGateway implements PaymentGatewayInterface
{
    public function getName(): string       { return 'ชำระด้วยตนเอง (Manual)'; }
    public function getMethodType(): string { return 'manual'; }

    public function isAvailable(): bool
    {
        // Manual is always available as a fallback.
        return true;
    }

    public function initiate(array $orderData): array
    {
        // No redirect or QR needed — admin will mark the order paid via the admin panel.
        return [
            'success'      => true,
            'redirect_url' => null,
            'qr_code'      => null,
            'data'         => [
                'message'    => 'รอการยืนยันจากผู้ดูแลระบบ',
                'amount'     => $orderData['amount'] ?? 0,
                'order_id'   => $orderData['order_id'] ?? null,
            ],
        ];
    }

    public function verify(array $data): array
    {
        // Admin-driven; verification is manual.
        return ['success' => false, 'transaction_id' => null];
    }

    public function refund(string $transactionId, float $amount): array
    {
        return ['success' => false, 'message' => 'Manual refunds are handled by the admin'];
    }

    // Legacy shims
    public function createPayment(array $data): array          { return $this->initiate($data); }
    public function verifyPayment(string $transactionId): array { return ['success' => false, 'status' => 'pending']; }
    public function handleWebhook(array $payload): array       { return ['success' => false, 'message' => 'Manual gateway has no webhook']; }
}
