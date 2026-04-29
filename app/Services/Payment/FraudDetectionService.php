<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentSlip;
use Illuminate\Support\Carbon;

/**
 * Behavioural fraud signals — operates AFTER the structural checks
 * (PaymentVerificationService steps 1-6). Returns flags + a numeric
 * "suspicion delta" that shifts the score; never refuses outright on
 * its own — that's the verifier's job.
 *
 * Patterns detected
 * -----------------
 *   1. user_repeat_rejections    — user has ≥3 rejected slips in 24h
 *   2. user_velocity_high        — user submitted ≥5 slips in 1h
 *   3. order_repeat_attempts     — same order has ≥4 slips already
 *   4. slip_outside_business_h   — submitted between 02:00 and 05:00 user-tz
 *                                   (low signal — many legit too — soft only)
 *
 * Each flag is just a string in the returned array. The verifier
 * decides which are hard/soft.
 */
class FraudDetectionService
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{flags: array<int, string>, signals: array<string, int>}
     */
    public function evaluate(Order $order, SlipFingerprint $fingerprint, array $context): array
    {
        $flags   = [];
        $signals = [];
        $userId  = (int) $order->user_id;
        $now     = Carbon::now();

        // 1. Repeated rejections — strong signal of intentional fraud probe.
        $rejected24h = PaymentSlip::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId))
            ->where('verify_status', 'rejected')
            ->where('created_at', '>=', $now->copy()->subHours(24))
            ->count();
        $signals['rejected_24h'] = $rejected24h;
        if ($rejected24h >= 3) {
            $flags[] = 'user_repeat_rejections';
        }

        // 2. Submission velocity — rapid-fire uploads.
        $submissions1h = PaymentSlip::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId))
            ->where('created_at', '>=', $now->copy()->subHour())
            ->count();
        $signals['submissions_1h'] = $submissions1h;
        if ($submissions1h >= 5) {
            $flags[] = 'user_velocity_high';
        }

        // 3. Same order over-attempted.
        $orderAttempts = PaymentSlip::query()
            ->where('order_id', $order->id)
            ->count();
        $signals['order_attempts'] = $orderAttempts;
        if ($orderAttempts >= 4) {
            $flags[] = 'order_repeat_attempts';
        }

        // 4. Off-hours signal (low value but logged for review).
        $hour = (int) $now->format('H');
        $signals['hour'] = $hour;
        if ($hour >= 2 && $hour <= 5) {
            $flags[] = 'slip_outside_business_hours';
        }

        return ['flags' => array_values(array_unique($flags)), 'signals' => $signals];
    }
}
