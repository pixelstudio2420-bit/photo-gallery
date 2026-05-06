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

            // ── Anti-tamper: total integrity check on paid transition ──
            // Verify that order.total == subtotal - discount + sum(items).
            // OrderIntegrityObserver already prevents direct mutation of
            // the total column post-paid, but it can't catch the case
            // where a malicious actor (or buggy code) put a wrong number
            // in *during* creation. By recomputing from OrderItems on
            // the paid transition we get a second-line check that
            // surfaces discrepancies into the log + admin alerts.
            //
            // We don't HALT the transition — payment was already taken
            // and customers shouldn't get stuck. We log the discrepancy
            // loudly so admin can investigate and refund if needed.
            if ($order->status === 'paid'
                && in_array($order->order_type ?? 'photo_package', [Order::TYPE_PHOTO_PACKAGE, null], true)) {
                try {
                    $itemsTotal = (float) $order->items()->sum('price');
                    $discount   = (float) ($order->discount_amount ?? 0);
                    $expected   = max(0, $itemsTotal - $discount);
                    $actual     = (float) $order->total;
                    $delta      = abs($expected - $actual);

                    // Allow ฿0.01 fuzz for rounding (decimal:2 columns).
                    if ($delta > 0.01) {
                        Log::warning('OrderObserver.total_mismatch_on_paid', [
                            'order_id'    => $order->id,
                            'items_sum'   => $itemsTotal,
                            'discount'    => $discount,
                            'expected'    => $expected,
                            'actual_total'=> $actual,
                            'delta'       => $delta,
                            'photographer'=> $order->event_id ? optional(\App\Models\Event::find($order->event_id))->photographer_id : null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::info('OrderObserver.total_check_skipped: ' . $e->getMessage());
                }
            }

            // Reverse photographer payouts on refund / cancellation.
            // Without this, the platform refunds the buyer but the
            // photographer still gets paid by the disbursement cron —
            // the platform absorbs the loss twice (refund + payout).
            //
            // Logic per payout row (all paths land at status='reversed' —
            // the schema CHECK constraint only allows pending/processing/
            // paid/reversed; an earlier version tried 'cancelled' for the
            // pending case but that violates the constraint and the
            // exception was being swallowed by the outer try/catch,
            // silently leaving the photographer's payout at status='pending'
            // — meaning they could still withdraw money for a refunded sale).
            //
            //   - 'pending'  → mark 'reversed' (never disbursed yet, no clawback needed)
            //   - 'paid'     → mark 'reversed' + create a clawback note
            //   - 'reversed' → no-op (idempotent)
            //
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
        // Only `reversed` is a valid terminal status the schema's CHECK
        // constraint allows ['pending','processing','paid','reversed'] so
        // we filter out only that one — any other status (pending,
        // processing, paid) needs to be transitioned to reversed.
        $payouts = PhotographerPayout::where('order_id', $order->id)
            ->where('status', '!=', 'reversed')
            ->lockForUpdate()
            ->get();

        if ($payouts->isEmpty()) return;

        DB::transaction(function () use ($payouts, $order) {
            foreach ($payouts as $payout) {
                $wasPaid = $payout->status === 'paid';

                // All non-reversed payouts (pending / processing / paid)
                // land at 'reversed'. The note column distinguishes the
                // two business cases (clawback-needed vs not). Trying any
                // other status here used to silently fail at the DB layer
                // and leave the row at its old status — letting the
                // photographer withdraw money for a refunded sale.
                $payout->update([
                    'status'           => 'reversed',
                    'reversed_at'      => now(),
                    'reversal_reason'  => $wasPaid
                        ? "order.{$order->status} — clawback required"
                        : "order.{$order->status} — never disbursed",
                    'note'             => trim(($payout->note ?? '') . ' ['
                        . ($wasPaid
                            ? "reversed by order.{$order->status} — clawback required"
                            : "auto-reversed by order.{$order->status}")
                        . ']'),
                ]);

                // If the payout was already attached to a draft (manual
                // withdrawal in flight) we must release the lock so the
                // draft Disbursement doesn't stay associated with a
                // reversed payout. The WithdrawalRequest stays in pending
                // status — admin/photographer can decide whether to
                // cancel it or settle the residual.
                if ($payout->disbursement_id) {
                    \App\Models\PhotographerDisbursement::where('id', $payout->disbursement_id)
                        ->where('status', \App\Models\PhotographerDisbursement::STATUS_PENDING)
                        ->update(['payout_count' => DB::raw('GREATEST(payout_count - 1, 0)')]);
                }

                // Photographer notification — only for the clawback case
                // (paid → reversed). For pending → reversed, the
                // photographer never had the money in the first place,
                // so nothing to clawback or apologise for.
                if ($wasPaid) {
                    try {
                        \App\Models\UserNotification::notify(
                            $payout->photographer_id,
                            'payout_reversed',
                            '↩️ ยอดขายถูกคืนเงิน',
                            "คำสั่งซื้อ #{$order->order_number} ถูก{$order->status} — ยอด ฿"
                                . number_format((float) $payout->payout_amount, 2)
                                . ' ถูก clawback',
                            'photographer/earnings'
                        );
                    } catch (\Throwable $e) {
                        Log::debug('payout_reversed_notify_failed: ' . $e->getMessage());
                    }
                }
            }
        });
    }
}
