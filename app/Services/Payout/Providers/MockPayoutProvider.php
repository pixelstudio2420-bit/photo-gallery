<?php

namespace App\Services\Payout\Providers;

use App\Models\PhotographerProfile;
use App\Services\Payout\PayoutProviderInterface;
use App\Services\Payout\PayoutResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * No-op payout provider for dev / staging.
 *
 * Accepts every transfer, logs the payload, and returns a fake but stable
 * provider_txn_id derived from the idempotency key. This lets the entire
 * payout pipeline — triggers → batching → transfer → status update — run
 * end-to-end without real banking creds, so features can ship and be tested
 * before Ops completes the Omise / KBank contract.
 *
 * Swap to OmisePayoutProvider (or whichever real provider) via the
 * `payout_provider` AppSetting. No other code needs to change.
 */
class MockPayoutProvider implements PayoutProviderInterface
{
    public function transfer(
        PhotographerProfile $recipient,
        float $amount,
        string $idempotencyKey,
        array $meta = []
    ): PayoutResult {
        // Guardrails that mirror a real provider's validation, so bugs upstream
        // (e.g. payout job firing for a creator who has no PromptPay yet) are
        // caught in dev the same way they'd be caught in prod.
        if (empty($recipient->promptpay_number)) {
            return PayoutResult::failed(
                errorCode: 'missing_promptpay',
                message: 'Recipient has no PromptPay number on file.'
            );
        }
        if ($amount <= 0) {
            return PayoutResult::failed(
                errorCode: 'invalid_amount',
                message: "Amount must be positive (got {$amount})."
            );
        }

        // Deterministic fake ID: same idempotency_key → same txn_id, so a job
        // retry lands on the same "existing" transfer just like a real provider.
        $txnId = 'mock_trf_' . substr(sha1($idempotencyKey), 0, 20);

        Log::info('MockPayoutProvider.transfer', [
            'recipient_user_id' => $recipient->user_id,
            'promptpay_masked'  => $this->maskPromptPay($recipient->promptpay_number),
            'amount'            => $amount,
            'idempotency_key'   => $idempotencyKey,
            'txn_id'            => $txnId,
            'meta'              => $meta,
        ]);

        return PayoutResult::succeeded($txnId, [
            'provider' => 'mock',
            'note'     => 'Simulated success — no real money moved.',
        ]);
    }

    public function pollStatus(string $providerTxnId): PayoutResult
    {
        // Mock never returns pending, so polling shouldn't happen, but be safe.
        return PayoutResult::succeeded($providerTxnId, ['provider' => 'mock', 'source' => 'poll']);
    }

    public function name(): string
    {
        return 'mock';
    }

    public function healthCheck(): bool
    {
        return true;
    }

    /** Masked PromptPay for log output — never log the full ID. */
    private function maskPromptPay(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);
        $len = strlen($digits);
        if ($len <= 4) return str_repeat('X', $len);
        return str_repeat('X', $len - 4) . substr($digits, -4);
    }
}
