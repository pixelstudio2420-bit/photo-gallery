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

        // ── 6. Timing sanity ───────────────────────────────────────────
        //
        // We check three timing problems:
        //   - slip_predates_order  — slip transaction time is before the
        //     order's created_at (impossible for legit "pay for THIS order")
        //   - slip_too_old         — transaction older than the cap (recycled
        //     slip from a previous order being replayed)
        //   - slip_in_future       — transaction time after `now()` (clock
        //     skew or fake)
        //
        // Two important behaviour changes from the original implementation:
        //
        // 1. The "before order" check is now a SOFT WARNING by default,
        //    not a hard reject. Real-world Thai customer behaviour:
        //
        //       a) Order created at 14:00:30, customer transfers at 14:00:00
        //          (they prepared the transfer in another tab).
        //       b) Bank app shows transaction time rounded down to the
        //          minute — slip says 14:00, order created_at 14:00:31.
        //       c) SlipOK / OCR returns the time in the bank's local TZ
        //          but server-side Carbon parses it without TZ awareness.
        //       d) Customer's phone clock is a few minutes off from
        //          server's NTP-synced clock.
        //
        //    All four cases produce a slip dated "before" the order by a
        //    handful of minutes and were rejecting legitimate payments.
        //    A soft warning lets the admin review on the slip-review
        //    page instead of bouncing the upload back to the customer.
        //
        // 2. Both the tolerance and the hard-reject behaviour are now
        //    configurable via AppSettings so an operator can tighten the
        //    rule once they've quantified their fraud rate:
        //
        //      slip_predate_tolerance_minutes   default: 30
        //      slip_predate_hard_reject         default: '0' (soft)
        //      slip_max_age_days                default: 30
        //      slip_future_tolerance_minutes    default: 5
        try {
            $slipTime  = Carbon::parse((string) ($context['transfer_date'] ?? 'now'));
            $orderTime = Carbon::parse((string) ($context['order_created_at'] ?? $order->created_at));
            $now       = Carbon::now();

            $predateTolMinutes = max(0, (int) \App\Models\AppSetting::get('slip_predate_tolerance_minutes', '30'));
            $predateHardReject = (string) \App\Models\AppSetting::get('slip_predate_hard_reject', '0') === '1';
            $maxAgeDays        = max(1, (int) \App\Models\AppSetting::get('slip_max_age_days', '30'));
            $futureTolMinutes  = max(1, (int) \App\Models\AppSetting::get('slip_future_tolerance_minutes', '5'));

            $checks['slip_minutes_before_order'] = $orderTime->diffInMinutes($slipTime, false);

            // Slip dated before order beyond the configured tolerance →
            // raise a flag. Whether that flag turns into a hard reject
            // depends on the operator's setting — by default it's a
            // soft signal that pushes the slip into admin review.
            if ($slipTime->lt($orderTime->copy()->subMinutes($predateTolMinutes))) {
                $fraudFlags[] = 'slip_predates_order';
                if ($predateHardReject) {
                    $hardReject = $hardReject ?? 'Slip is dated before the order was created.';
                }
            }

            // Stale slip recycling — clear hard fraud signal regardless
            // of tolerance settings, so this stays a hard reject.
            if ($slipTime->lt($now->copy()->subDays($maxAgeDays))) {
                $fraudFlags[] = 'slip_too_old';
                $hardReject = $hardReject ?? sprintf('Slip is older than %d days.', $maxAgeDays);
            }

            // Future-dated slip — likewise a hard reject. The tolerance
            // here covers minor clock skew between customer phone and
            // server.
            if ($slipTime->gt($now->copy()->addMinutes($futureTolMinutes))) {
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
