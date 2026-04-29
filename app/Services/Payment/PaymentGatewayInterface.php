<?php

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    /**
     * Human-readable gateway name
     */
    public function getName(): string;

    /**
     * Machine type string (promptpay / bank_transfer / stripe / …)
     */
    public function getMethodType(): string;

    /**
     * Initiate payment.
     *
     * @return array{
     *   success: bool,
     *   redirect_url: string|null,
     *   qr_code: string|null,
     *   data: array
     * }
     */
    public function initiate(array $orderData): array;

    /**
     * Verify / check payment status.
     *
     * @return array{success: bool, transaction_id: string|null}
     */
    public function verify(array $data): array;

    /**
     * Issue a refund.
     *
     * @return array{success: bool, message: string}
     */
    public function refund(string $transactionId, float $amount): array;

    /**
     * Whether this gateway is configured and ready to use.
     */
    public function isAvailable(): bool;

    // ----------------------------------------------------------------
    // Legacy compatibility shims (kept so existing callers don't break)
    // ----------------------------------------------------------------

    public function createPayment(array $data): array;
    public function verifyPayment(string $transactionId): array;
    public function handleWebhook(array $payload): array;
}
