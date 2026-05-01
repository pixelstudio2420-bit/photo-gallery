<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\PhotographerPayout;
use App\Services\Marketing\ReferralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Order lifecycle observer — currently focused on three lifecycle effects:
 *
 *   - paid     → grant referral reward to the inviter (loyalty points)
 *   - refunded → reverse referral + reverse photographer payout(s)
 *   - cancelled → reverse referral + cancel pending photographer payouts
 *
 * Wired up in AppServiceProvider::boot(). Failures are swallowed: marketing
 * rewards are nice-to-have and must never block checkout / refund.
 *
 * Why an observer (not inline in PaymentService::completeTransaction)?
 *   - Multiple paths can mark an order paid: gateway webhooks, admin manual
 *     verification, slip approval, etc. An observer means we hook every one
 *     of them with zero coordination.
 *   - Status-change detection via wasChanged('status') means we won't fire
 *     twice if some other code touches the row after the flip.
 */
class OrderObserver
{
    public function updated(Order $order): void
    {
        // Skip if status didn't actually change in this update — this is the
        // critical guard that prevents double rewards on subsequent saves.
        if (!$order->wasChanged('status')) {
            return;
        }

        // Don't reward photographer-internal orders (credit packages,
        // subscription invoices, user storage). Those have their own flows.
        $skipReferral = in_array($order->order_type, [
            Order::TYPE_CREDIT_PACKAGE,
            Order::TYPE_SUBSCRIPTION,
            Order::TYPE_USER_STORAGE_SUBSCRIPTION,
        ], true);

        try {
            if (!$skipReferral) {
                if ($order->status === 'paid' && $order->referral_code_id) {
                    app(ReferralService::class)->rewardOnOrder($order->id);
                }

                if (in_array($order->status, ['refunded', 'cancelled'], true) && $order->referral_code_id) {
                    app(ReferralService::class)->reverseOnRefund($order->id);
                }
            }

            // Bundle purchase counter — increment when an order containing a
            // pricing_packages bundle transitions to paid. Used by the
            // photographer bundle-stats dashboard to highlight best-sellers
            // and by future "show 'X people bought this' social proof" UI.
            // Cheap: a single indexed UPDATE per package.
            if ($order->status === 'paid' && $order->package_id) {
                try {
                    DB::table('pricing_packages')
                        ->where('id', $order->package_id)
                        ->increment('purchase_count');
                } catch (\Throwable $e) {
                    // Counter is denormalized — never block payment confirmation.
                    Log::info('OrderObserver.bundle_counter_skipped: ' . $e->getMessage());
                }
            }

            // Reverse photographer payouts on refund / cancellation.
            // Without this, the platform refunds the buyer but the
            // photographer still gets paid by the disbursement cron —
            // the platform absorbs the loss twice (refund + payout).
            //
            // Logic per payout row:
            //   - 'pending'  → mark 'cancelled' (never disbursed yet)
            //   - 'paid'     → mark 'reversed' + create a clawback note
            //   - 'reversed' → no-op (idempotent)
            // Photo orders only — credit/sub/storage have their own paths.
            if (in_array($order->status, ['refunded', 'cancelled'], true)
                && $order->order_type !== Order::TYPE_CREDIT_PACKAGE
                && $order->order_type !== Order::TYPE_SUBSCRIPTION
                && $order->order_type !== Order::TYPE_USER_STORAGE_SUBSCRIPTION
                && $order->order_type !== Order::TYPE_GIFT_CARD) {
                $this->reversePhotographerPayouts($order);
            }
        } catch (\Throwable $e) {
            Log::warning('OrderObserver.lifecycle_failed', [
                'order'  => $order->id,
                'status' => $order->status,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel pending payouts and clawback paid ones for an order.
     *
     * Idempotent — re-running on an already-reversed order is a no-op.
     * Wraps DB writes in a single transaction so a failure halfway
     * through doesn't leave half the photographers' payouts in
     * inconsistent states.
     */
    private function reversePhotographerPayouts(Order $order): void
    {
        $payouts = PhotographerPayout::where('order_id', $order->id)
            ->whereNotIn('status', ['reversed', 'cancelled'])
            ->lockForUpdate()
            ->get();

        if ($payouts->isEmpty()) return;

        DB::transaction(function () use ($payouts, $order) {
            foreach ($payouts as $payout) {
                if ($payout->status === 'pending' || $payout->status === 'requested') {
                    // Never disbursed → just cancel.
                    $payout->update([
                        'status' => 'cancelled',
                        'note'   => trim(($payout->note ?? '') . " [auto-cancelled by order.{$order->status}]"),
                    ]);
                } elseif ($payout->status === 'paid') {
                    // Already disbursed → mark reversed for clawback.
                    // The disbursement cron skips 'reversed' so it
                    // won't try to settle again. Admin can follow up
                    // for actual money recovery (manual or via
                    // Omise transfer reverse).
                    $payout->update([
                        'status' => 'reversed',
                        'note'   => trim(($payout->note ?? '') . " [reversed by order.{$order->status} — clawback required]"),
                    ]);

                    // Notify the photographer. Best-effort.
                    try {
                        \App\Models\UserNotification::notify(
                            $payout->photographer_id,
                            'payout_reversed',
                            "↩️ ยอดขายถูกคืนเงิน",
                            "คำสั่งซื้อ #{$order->order_number} ถูก{$order->status} — ยอด ฿".number_format((float) $payout->payout_amount, 2)." ถูก clawback",
                            'photographer/earnings'
                        );
                    } catch (\Throwable $e) {}
                }
            }
        });
    }
}
