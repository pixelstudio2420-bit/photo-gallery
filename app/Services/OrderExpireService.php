<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OrderExpireService — auto-cancel orders whose payment window closed.
 *
 * Three callers, one service:
 *   1. Scheduled command (orders:expire-pending) — runs every minute,
 *      sweeps expired pending orders and marks them cancelled.
 *   2. PaymentController checkout/process — caller hits the page after
 *      the window closed; we cancel the order before redirecting so
 *      the buyer doesn't see a stale "pay now" UI.
 *   3. API endpoint /api/orders/{id}/check-expiry — JS countdown on
 *      the checkout page calls this when the timer hits 0 so the
 *      cancellation is reflected before the user has to refresh.
 *
 * Why a service instead of a model method:
 *   Cancellation has 3 side-effects (status flip, payment_transactions
 *   cleanup, audit log entry) that all need to happen atomically.
 *   Centralising the transaction here keeps every caller honest —
 *   no controller can accidentally cancel an order without releasing
 *   its in-flight payment_transactions or logging the event.
 *
 * Idempotency:
 *   expireOrder() is safe to call multiple times on the same order.
 *   The status check at the top short-circuits when the order is no
 *   longer pending, so concurrent callers (cron + page-load) can't
 *   double-cancel or undo a paid order.
 */
class OrderExpireService
{
    public const CANCEL_REASON_TIMEOUT = 'payment_timeout';
    public const CANCEL_REASON_MANUAL  = 'user_cancel';

    /**
     * Cancel a single order due to payment-window expiry.
     *
     * Returns true when the order was JUST cancelled by this call,
     * false when it was already paid / already cancelled / not yet
     * expired. The boolean lets callers (e.g., the controller) decide
     * whether to flash a "your order timed out" message vs. silently
     * proceeding.
     */
    public function expireOrder(Order $order, string $reason = self::CANCEL_REASON_TIMEOUT): bool
    {
        // Defensive guards — in this order so a concurrent paid-callback
        // can't accidentally land in cancelled status.
        if (in_array($order->status, ['paid', 'cancelled', 'refunded'], true)) {
            return false;
        }

        // For timeout-triggered calls only: must actually be expired.
        // Manual-cancel calls bypass this check (user clicks "cancel").
        if ($reason === self::CANCEL_REASON_TIMEOUT) {
            if (!$order->payment_expires_at || !$order->payment_expires_at->isPast()) {
                return false;
            }
        }

        DB::transaction(function () use ($order, $reason) {
            // Re-fetch with lock so a parallel paid-webhook can't slip
            // through between our status check and the update.
            $fresh = Order::where('id', $order->id)->lockForUpdate()->first();
            if (!$fresh || in_array($fresh->status, ['paid', 'cancelled', 'refunded'], true)) {
                return;
            }
            if ($reason === self::CANCEL_REASON_TIMEOUT
                && (!$fresh->payment_expires_at || !$fresh->payment_expires_at->isPast())) {
                return;
            }

            $reasonLabel = $reason === self::CANCEL_REASON_TIMEOUT
                ? 'หมดเวลาชำระเงินอัตโนมัติ'
                : 'ยกเลิกโดยผู้ใช้';

            $existingNote = (string) ($fresh->note ?? '');
            $newNote = trim($existingNote . ' [' . now()->format('Y-m-d H:i') . ' ' . $reasonLabel . ']');

            $fresh->update([
                'status' => 'cancelled',
                'note'   => mb_substr($newNote, 0, 1000),  // column cap defensive
            ]);

            // Cancel any in-flight payment_transactions so the buyer
            // can't accidentally complete a stale Stripe / Omise
            // payment intent against this order. Already-succeeded
            // transactions are left alone — those are paid orders we
            // shouldn't reach anyway thanks to the guard above.
            PaymentTransaction::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'requires_action', 'processing'])
                ->update([
                    'status'     => 'cancelled',
                    'updated_at' => now(),
                ]);

            // Lightweight audit log via the global Log facade — we
            // don't have a dedicated order_audit table, but Laravel's
            // log channel + DB-backed log driver (where enabled) is
            // enough to investigate disputes after the fact.
            Log::info('order.expired', [
                'order_id'           => $order->id,
                'order_number'       => $fresh->order_number ?? null,
                'reason'             => $reason,
                'user_id'            => $fresh->user_id,
                'total'              => $fresh->total,
                'payment_expires_at' => optional($fresh->payment_expires_at)->toIso8601String(),
            ]);
        });

        return true;
    }

    /**
     * Sweep ALL pending orders whose payment_expires_at has passed and
     * cancel them in one pass. Used by the orders:expire-pending cron.
     *
     * Returns the number of orders that were actually cancelled by
     * this run (idempotent — already-cancelled orders aren't counted).
     */
    public function sweepExpired(?Carbon $now = null): int
    {
        $now = $now ?: now();

        // Filter: pending statuses + expired window. Cap to a sane
        // batch size so a backlog after a long downtime can't lock
        // the table — subsequent cron runs will catch the rest.
        $batch = Order::query()
            ->whereIn('status', ['pending', 'pending_payment'])
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<', $now)
            ->limit(500)
            ->get();

        $cancelled = 0;
        foreach ($batch as $order) {
            if ($this->expireOrder($order, self::CANCEL_REASON_TIMEOUT)) {
                $cancelled++;
            }
        }
        return $cancelled;
    }
}
