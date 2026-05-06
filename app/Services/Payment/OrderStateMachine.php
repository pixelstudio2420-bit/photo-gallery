<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Services\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Guarded state-machine wrapper around Order.status.
 *
 * Replaces every `$order->update(['status' => 'paid'])` with a method
 * that:
 *   1. Locks the order row (FOR UPDATE) so concurrent webhooks serialise.
 *   2. Refuses transitions not declared in ALLOWED_TRANSITIONS.
 *   3. Atomically writes status + paid_at + idempotency context.
 *   4. Records an ActivityLog row capturing the WHO/WHAT/WHEN/WHY.
 *
 * The business logic INVARIANT this enforces:
 *   "An order goes through paid EXACTLY ONCE."
 *
 * If the order is already in the target state, we return false and
 * don't trigger fulfillment again — that's the idempotent-retry path.
 */
class OrderStateMachine
{
    /**
     * Allowed forward transitions. Every transition NOT listed here is
     * refused — including same-state self-transitions (which are fine
     * but caller-side: this method returns false silently).
     *
     * Note: terminal states (paid, refunded) accept reverses ONLY via
     * explicit `forceTransition()` for admin override flows.
     */
    public const ALLOWED_TRANSITIONS = [
        'cart'             => ['pending_payment', 'cancelled'],
        'pending_payment'  => ['pending_review', 'paid', 'cancelled', 'failed'],
        'pending_review'   => ['paid', 'cancelled'],
        'paid'             => ['refunded'],
        'refunded'         => [],
        'cancelled'        => [],
        'failed'           => ['pending_payment'],  // retry permitted
    ];

    /**
     * Transition an order to 'paid'. Idempotent — calling twice with
     * the same ($order, $idempotencyKey) is safe.
     *
     * @return bool  true when the state actually changed; false on retry.
     */
    public function transitionToPaid(int $orderId, string $idempotencyKey, array $auditContext = []): bool
    {
        return $this->transition(
            orderId:        $orderId,
            toStatus:       'paid',
            idempotencyKey: $idempotencyKey,
            auditContext:   $auditContext,
            extraColumns:   ['paid_at' => now()],
        );
    }

    /**
     * Generic transition — used for cancel, refund, etc.
     *
     * @return bool  true when the state changed; false on no-op retry.
     * @throws \DomainException  on disallowed transition.
     */
    public function transition(
        int $orderId,
        string $toStatus,
        string $idempotencyKey,
        array $auditContext = [],
        array $extraColumns = [],
    ): bool {
        return DB::transaction(function () use ($orderId, $toStatus, $idempotencyKey, $auditContext, $extraColumns) {
            // SELECT FOR UPDATE — serialises concurrent webhook + manual approve.
            $order = Order::lockForUpdate()->find($orderId);
            if (!$order) {
                throw new \DomainException("Order #{$orderId} not found");
            }

            $current = (string) $order->status;

            // Idempotent retry path — already in target state.
            if ($current === $toStatus) {
                Log::info('OrderStateMachine: no-op (already in target state)', [
                    'order_id'        => $orderId,
                    'state'           => $current,
                    'idempotency_key' => $idempotencyKey,
                ]);
                return false;
            }

            $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];
            if (!in_array($toStatus, $allowed, true)) {
                throw new \DomainException(sprintf(
                    'Invalid order transition: %s → %s. Allowed: [%s]',
                    $current, $toStatus, implode(', ', $allowed),
                ));
            }

            $cols = ['status' => $toStatus] + $extraColumns;
            // Stamp the idempotency key on the order so future probes can
            // reconstruct what triggered the change.
            if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'idempotency_key')
                && empty($order->idempotency_key)) {
                $cols['idempotency_key'] = $idempotencyKey;
            }
            DB::table('orders')
                ->where('id', $orderId)
                ->update($cols);

            // Activity log — fail-soft (log entry is nice-to-have, not blocking)
            try {
                ActivityLogger::system(
                    action: "order.transitioned.{$toStatus}",
                    target: $order,
                    description: "Order #{$order->order_number} transitioned {$current} → {$toStatus}",
                    oldValues: ['status' => $current],
                    newValues: ['status' => $toStatus] + $auditContext + ['idempotency_key' => $idempotencyKey],
                );
            } catch (\Throwable $e) {
                Log::warning('OrderStateMachine: activity log failed', [
                    'order_id' => $orderId,
                    'err'      => $e->getMessage(),
                ]);
            }

            // We use DB::table()->update() directly (instead of $order->update())
            // for performance + lockForUpdate semantics — but that BYPASSES
            // Eloquent's `updated` event, so AdminNotificationObserver
            // never fires its paymentSuccess() helper. Without the manual
            // dispatch below, admins miss every paid-transition notification:
            // SlipOK auto-approve, manual admin approve, webhook callback,
            // subscription activation — all silent. Defer via afterCommit
            // so a rolled-back transaction doesn't leave a phantom
            // notification, and re-fetch through Eloquent so the observer
            // helpers receive a real model with relations available.
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($orderId, $toStatus, $current) {
                try {
                    $fresh = \App\Models\Order::find($orderId);
                    if (!$fresh) return;

                    if ($toStatus === 'paid' && $current !== 'paid') {
                        \App\Models\AdminNotification::paymentSuccess($fresh);
                    }
                    // Other transitions worth surfacing — refunds, cancels —
                    // already have their own notification helpers fired by
                    // the services that initiate them (RefundService, etc.),
                    // so we don't double-fire here.
                } catch (\Throwable $e) {
                    Log::warning('OrderStateMachine: post-commit admin notify failed', [
                        'order_id' => $orderId,
                        'to'       => $toStatus,
                        'err'      => $e->getMessage(),
                    ]);
                }
            });

            return true;
        });
    }
}
