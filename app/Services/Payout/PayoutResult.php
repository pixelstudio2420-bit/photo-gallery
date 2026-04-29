<?php

namespace App\Services\Payout;

/**
 * Value object returned by every PayoutProviderInterface call.
 *
 * Provider-agnostic shape so the payout engine can branch on one stable
 * contract regardless of whether the underlying SDK returned a Stripe-ish,
 * Omise-ish, or bank-specific payload.
 *
 * States:
 *   ok=true,  status=succeeded → money moved, provider_txn_id is final.
 *   ok=true,  status=pending   → accepted, settle async — poll later.
 *   ok=false, status=failed    → permanent error_code, do not retry.
 *   (Retryable errors throw instead of returning, so the queue can backoff.)
 */
final class PayoutResult
{
    public function __construct(
        public readonly bool    $ok,
        public readonly string  $status,          // succeeded | pending | failed
        public readonly ?string $providerTxnId = null,
        public readonly ?string $errorCode     = null,
        public readonly ?string $errorMessage  = null,
        public readonly array   $raw           = [],
    ) {}

    public static function succeeded(string $providerTxnId, array $raw = []): self
    {
        return new self(ok: true, status: 'succeeded', providerTxnId: $providerTxnId, raw: $raw);
    }

    public static function pending(string $providerTxnId, array $raw = []): self
    {
        return new self(ok: true, status: 'pending', providerTxnId: $providerTxnId, raw: $raw);
    }

    public static function failed(string $errorCode, string $message, array $raw = []): self
    {
        return new self(ok: false, status: 'failed', errorCode: $errorCode, errorMessage: $message, raw: $raw);
    }

    public function toArray(): array
    {
        return [
            'ok'              => $this->ok,
            'status'          => $this->status,
            'provider_txn_id' => $this->providerTxnId,
            'error_code'      => $this->errorCode,
            'error_message'   => $this->errorMessage,
        ];
    }
}
