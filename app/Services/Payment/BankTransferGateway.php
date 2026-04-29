<?php

namespace App\Services\Payment;

use App\Models\BankAccount;

class BankTransferGateway implements PaymentGatewayInterface
{
    // ----------------------------------------------------------------
    // Identity
    // ----------------------------------------------------------------

    public function getName(): string
    {
        return 'โอนผ่านธนาคาร';
    }

    public function getMethodType(): string
    {
        return 'bank_transfer';
    }

    // ----------------------------------------------------------------
    // Availability
    // ----------------------------------------------------------------

    public function isAvailable(): bool
    {
        return BankAccount::active()->exists();
    }

    // ----------------------------------------------------------------
    // Core operations
    // ----------------------------------------------------------------

    public function initiate(array $orderData): array
    {
        $banks = BankAccount::active()->get();

        if ($banks->isEmpty()) {
            return [
                'success'      => false,
                'redirect_url' => null,
                'qr_code'      => null,
                'data'         => ['error' => 'No active bank accounts configured'],
            ];
        }

        return [
            'success'      => true,
            'redirect_url' => null,
            'qr_code'      => null,
            'data'         => [
                'bank_accounts' => $banks->toArray(),
                'amount'        => $orderData['amount'] ?? 0,
                'reference'     => $orderData['transaction_id'] ?? '',
            ],
        ];
    }

    public function verify(array $data): array
    {
        // Requires manual slip verification by admin.
        return ['success' => false, 'transaction_id' => null];
    }

    public function refund(string $transactionId, float $amount): array
    {
        return ['success' => false, 'message' => 'Bank transfer refunds must be processed manually'];
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
        return ['success' => false, 'status' => 'pending', 'message' => 'Requires slip verification'];
    }

    public function handleWebhook(array $payload): array
    {
        return ['success' => false, 'message' => 'Bank transfer does not support webhooks'];
    }

    // ----------------------------------------------------------------
    // Helper: load active accounts for views
    // ----------------------------------------------------------------

    public static function getActiveAccounts()
    {
        return BankAccount::active()->get();
    }
}
