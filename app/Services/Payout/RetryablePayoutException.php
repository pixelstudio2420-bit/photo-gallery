<?php

namespace App\Services\Payout;

use RuntimeException;

/**
 * Thrown by PayoutProviderInterface implementations when a transfer failed
 * for a reason we want the queue to retry with backoff (network blip, 429,
 * upstream 5xx, provider timeout).
 *
 * Permanent failures should NOT throw this — they should return a PayoutResult
 * with ok=false + an error_code, so the calling job records the failure and
 * moves on instead of burning retries on a doomed transfer.
 */
class RetryablePayoutException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $providerCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
