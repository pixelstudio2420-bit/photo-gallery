<?php

namespace App\Services\Payment;

/**
 * Outcome of a payment-verification check. Distinguishes hard rejection
 * (fraud signal — refuse) from soft warning (queue for manual review).
 */
final class PaymentVerificationResult
{
    public const STATE_AUTO_APPROVE  = 'auto_approve';
    public const STATE_MANUAL_REVIEW = 'manual_review';
    public const STATE_REJECTED      = 'rejected';

    /**
     * @param  array<int, string>  $fraudFlags
     * @param  array<string, mixed>  $checks
     */
    public function __construct(
        public readonly string $state,
        public readonly int    $score,
        public readonly array  $fraudFlags,
        public readonly array  $checks,
        public readonly ?string $rejectionReason = null,
    ) {}

    public function isAutoApprove(): bool { return $this->state === self::STATE_AUTO_APPROVE; }
    public function isRejected(): bool    { return $this->state === self::STATE_REJECTED; }
    public function isManualReview(): bool { return $this->state === self::STATE_MANUAL_REVIEW; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'state'             => $this->state,
            'score'             => $this->score,
            'fraud_flags'       => $this->fraudFlags,
            'checks'            => $this->checks,
            'rejection_reason'  => $this->rejectionReason,
        ];
    }
}
