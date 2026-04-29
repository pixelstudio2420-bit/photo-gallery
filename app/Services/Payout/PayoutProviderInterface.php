<?php

namespace App\Services\Payout;

use App\Models\PhotographerProfile;

/**
 * Contract every real-time payout provider must satisfy.
 *
 * The system supports multiple providers (Omise Transfers, KBank K-BIZ,
 * SCB Easy API, a mock for dev) and swaps between them based on the
 * `payout_provider` AppSetting. The payout engine should never see a
 * provider-specific SDK type — everything flows through this interface.
 *
 * Error handling contract:
 *   - On success: return a PayoutResult with ok=true and a provider_txn_id.
 *   - On recoverable failure (network, rate limit): throw a
 *     RetryablePayoutException so the queue can retry with backoff.
 *   - On permanent failure (invalid recipient, insufficient platform funds):
 *     return ok=false with an error_code the UI / admin can branch on.
 *     Retrying makes no sense for these.
 *
 * Idempotency:
 *   Callers MUST pass a stable idempotency_key (typically the
 *   photographer_disbursement id). Providers are expected to dedupe on
 *   this key so a job retry doesn't result in a double transfer.
 */
interface PayoutProviderInterface
{
    /**
     * Execute a PromptPay transfer to the photographer's registered number.
     *
     * @param  PhotographerProfile $recipient      Must have a non-empty promptpay_number.
     * @param  float               $amount         THB, minor units handled internally.
     * @param  string              $idempotencyKey Stable unique key for dedupe.
     * @param  array               $meta           Free-form metadata to attach (description, batch_id…).
     * @return PayoutResult
     */
    public function transfer(
        PhotographerProfile $recipient,
        float $amount,
        string $idempotencyKey,
        array $meta = []
    ): PayoutResult;

    /**
     * Poll a pending transfer for its terminal status. Called by the
     * payout engine when a transfer was accepted but returned status=pending
     * (some providers settle asynchronously).
     */
    public function pollStatus(string $providerTxnId): PayoutResult;

    /**
     * Provider identifier — used in logs and persisted on the disbursement
     * row so we can trace which provider moved each baht.
     */
    public function name(): string;

    /**
     * Whether this provider is reachable and authenticated. Called by the
     * admin settings page to render a health indicator + by the payout
     * engine as a preflight before firing transfers.
     */
    public function healthCheck(): bool;
}
