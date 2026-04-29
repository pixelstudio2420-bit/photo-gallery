<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentSlip;
use App\Support\FlatConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Hardened payment verification — orchestrates ALL checks before a slip
 * is allowed to mark an order paid.
 *
 * Returns a structured PaymentVerificationResult — the controller must
 * NEVER auto-approve unless this returns STATE_AUTO_APPROVE.
 *
 * Hard rejections (refuse insert)
 * -------------------------------
 *   - Slip hash already used by another user
 *   - Slip hash already used by SAME user for ANOTHER order
 *   - SlipOK transRef already approved (cross-user fraud)
 *   - Slip amount < order amount × 0.99   (1% tolerance, NOT 5%)
 *   - Slip dated BEFORE order created (impossible to be a payment for it)
 *   - Slip dated > 30 days ago (stale slip recycling)
 *
 * Soft warnings (queue for manual)
 * --------------------------------
 *   - Score below auto-approve threshold
 *   - SlipOK confirmation missing in REQUIRE_SLIPOK mode
 *   - Any fraud flag present
 *
 * Auto-approve requires
 * ---------------------
 *   - All hard checks pass
 *   - Score ≥ threshold (default 80)
 *   - Zero fraud flags
 *   - SlipOK confirmation when slip_require_slipok_for_auto = '1'
 *     (we strongly recommend this be ON — see USAGE_CONTROL.md)
 */
class PaymentVerificationService
{
    public function __construct(
        private readonly SlipFingerprintService $fingerprints,
        private readonly FraudDetectionService  $fraud,
    ) {}

    /**
     * @param  array<string, mixed>  $context  request-supplied claim:
     *                                          ['transfer_amount', 'transfer_date',
     *                                           'ref_code', 'order_amount', 'order_created_at']
     */
    public function verify(
        UploadedFile $file,
        Order $order,
        SlipFingerprint $fingerprint,
        array $context,
        ?array $slipokResult = null,
    ): PaymentVerificationResult {
        $checks      = [];
        $fraudFlags  = [];
        $hardReject  = null;

        // ── 1. HARD: order must be in a state that ACCEPTS payment ─────
        if (!in_array($order->status, ['pending_payment', 'pending_review', 'pending', 'failed'], true)) {
            return new PaymentVerificationResult(
                state:           PaymentVerificationResult::STATE_REJECTED,
                score:           0,
                fraudFlags:      ['order_not_payable'],
                checks:          ['order_status' => $order->status],
                rejectionReason: "Order is in '{$order->status}' state and cannot accept new slips.",
            );
        }

        // ── 2. HARD: cross-user hash reuse ─────────────────────────────
        $crossUserDup = $this->fingerprints->findCrossUserDuplicate(
            sha256: $fingerprint->sha256,
            userId: (int) $order->user_id,
        );
        if ($crossUserDup) {
            $fraudFlags[] = 'duplicate_hash_cross_user';
            $checks['cross_user_duplicate_slip_id'] = $crossUserDup->id;
            $hardReject = $hardReject ?? 'Slip image was already submitted under another user account.';
        }

        // ── 3. HARD: same-user different-order reuse ───────────────────
        $sameUserDup = $this->fingerprints->findSameUserDifferentOrder(
            sha256: $fingerprint->sha256,
            userId: (int) $order->user_id,
            orderId: (int) $order->id,
        );
        if ($sameUserDup) {
            $fraudFlags[] = 'duplicate_hash_same_user';
            $checks['same_user_other_order_id'] = $sameUserDup->order_id;
            $hardReject = $hardReject ?? 'You already submitted this slip for a different order.';
        }

        // ── 4. HARD: SlipOK transRef already approved cross-user ───────
        $slipokRef = $slipokResult['trans_ref'] ?? null;
        if ($slipokRef) {
            $slipokDup = $this->fingerprints->findSlipokRefDuplicate($slipokRef);
            if ($slipokDup) {
                $fraudFlags[] = 'duplicate_slipok_trans_ref';
                $checks['duplicate_slipok_slip_id'] = $slipokDup->id;
                $hardReject = $hardReject ?? 'This bank transaction is already attributed to another order.';
            }
        }

        // ── 5. HARD: amount must match within 1% (not the legacy 5%) ───
        $orderAmount    = (float) ($context['order_amount'] ?? $order->total ?? 0);
        $transferAmount = (float) ($context['transfer_amount'] ?? 0);
        $checks['amount_match'] = $this->amountMatchTier($transferAmount, $orderAmount);
        if ($transferAmount < $orderAmount * 0.99) {
            $fraudFlags[] = 'amount_underpaid';
            $hardReject = $hardReject ?? sprintf(
                'Transfer amount (%.2f) is less than order total (%.2f).',
                $transferAmount, $orderAmount,
            );
        }

        // ── 6. HARD: timing sanity ─────────────────────────────────────
        try {
            $slipTime  = Carbon::parse((string) ($context['transfer_date'] ?? 'now'));
            $orderTime = Carbon::parse((string) ($context['order_created_at'] ?? $order->created_at));
            $now       = Carbon::now();

            $checks['slip_minutes_before_order'] = $orderTime->diffInMinutes($slipTime, false);
            // Slip dated BEFORE order — impossible for it to be payment FOR this order.
            if ($slipTime->lt($orderTime->copy()->subMinutes(2))) {  // 2-min clock-skew tolerance
                $fraudFlags[] = 'slip_predates_order';
                $hardReject = $hardReject ?? 'Slip is dated before the order was created.';
            }
            // Slip older than 30 days — stale slip recycling
            if ($slipTime->lt($now->copy()->subDays(30))) {
                $fraudFlags[] = 'slip_too_old';
                $hardReject = $hardReject ?? 'Slip is older than 30 days.';
            }
            // Slip in the future — impossible
            if ($slipTime->gt($now->copy()->addMinutes(5))) {
                $fraudFlags[] = 'slip_in_future';
                $hardReject = $hardReject ?? 'Slip date is in the future.';
            }
        } catch (\Throwable $e) {
            $fraudFlags[] = 'slip_time_unparseable';
        }

        // ── 7. SOFT: behavioural fraud signals ─────────────────────────
        $fraudReport = $this->fraud->evaluate($order, $fingerprint, $context);
        $fraudFlags  = array_unique(array_merge($fraudFlags, $fraudReport['flags']));

        // ── 8. Compute score (kept simple — full SlipVerifier is unchanged
        //                       for the score component; we override the GATE)
        $score = $this->computeScore($checks, $fraudFlags, $slipokResult);

        // ── 9. Decide ──────────────────────────────────────────────────
        if ($hardReject !== null) {
            return new PaymentVerificationResult(
                state:           PaymentVerificationResult::STATE_REJECTED,
                score:           $score,
                fraudFlags:      array_values(array_unique($fraudFlags)),
                checks:          $checks,
                rejectionReason: $hardReject,
            );
        }

        $threshold      = max(50, min(100, FlatConfig::int('slip_security', 'auto_threshold', 80)));
        $verifyMode     = (string) (\App\Models\AppSetting::get('slip_verify_mode', 'manual') ?? 'manual');
        $requireSlipok  = (string) (\App\Models\AppSetting::get('slip_require_slipok_for_auto', '0') ?? '0') === '1';
        $slipokOk       = (bool) ($slipokResult['success'] ?? false);

        $autoApprove = $verifyMode === 'auto'
            && empty($fraudFlags)
            && $score >= $threshold
            && (!$requireSlipok || $slipokOk);

        return new PaymentVerificationResult(
            state:      $autoApprove
                            ? PaymentVerificationResult::STATE_AUTO_APPROVE
                            : PaymentVerificationResult::STATE_MANUAL_REVIEW,
            score:      $score,
            fraudFlags: array_values(array_unique($fraudFlags)),
            checks:     $checks,
        );
    }

    /* ─────────────────── helpers ─────────────────── */

    private function amountMatchTier(float $slipAmount, float $orderAmount): string
    {
        if ($orderAmount <= 0 || $slipAmount <= 0) return 'none';
        $diff = abs($slipAmount - $orderAmount);
        if ($diff < 0.01) return 'exact';
        if ($diff <= $orderAmount * 0.005) return 'within_half_pct';
        if ($diff <= $orderAmount * 0.01)  return 'within_one_pct';
        return 'mismatch';
    }

    /** @param array<string, mixed> $checks @param array<int, string> $flags */
    private function computeScore(array $checks, array $flags, ?array $slipokResult): int
    {
        $score = 30;  // base
        if (($checks['amount_match'] ?? '') === 'exact')          $score += 30;
        elseif (($checks['amount_match'] ?? '') === 'within_half_pct') $score += 15;
        elseif (($checks['amount_match'] ?? '') === 'within_one_pct')  $score += 5;

        if ($slipokResult['success'] ?? false)                    $score += 20;
        if (($slipokResult['amount_verified'] ?? false))          $score += 10;

        if (($checks['slip_minutes_before_order'] ?? -1) >= 0)    $score += 10;

        // Flags strongly degrade
        $score -= count($flags) * 25;
        return max(0, min(100, $score));
    }
}
