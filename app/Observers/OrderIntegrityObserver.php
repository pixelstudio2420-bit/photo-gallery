<?php

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Application-level guard against the bug class:
 *   "order.total mutated after the order has been paid".
 *
 * The hard guarantee should ideally live in the database, but a
 * portable migration-friendly approach is this Eloquent observer:
 *
 *   - When status is one of {paid, refunded, completed}, mutating
 *     total/subtotal/discount_amount throws a DomainException.
 *   - The exception is logged at ERROR level so an integration test
 *     or production accident is impossible to miss in Sentry.
 *
 * Admin force-overrides
 * ---------------------
 *   In the rare case where ops genuinely needs to re-issue an order
 *   total (refund correction, audit fix), they call:
 *       $order->forceFill(['total' => …])->saveQuietly();
 *   The observer fires on `updating` only — `saveQuietly()` skips it.
 *   That's intentional: the override has to be explicit + auditable.
 */
class OrderIntegrityObserver
{
    private const LOCKED_STATUSES = ['paid', 'refunded', 'completed'];
    private const LOCKED_FIELDS   = ['total', 'subtotal', 'discount_amount'];

    public function updating(Order $order): void
    {
        $oldStatus = (string) $order->getOriginal('status');
        if (!in_array($oldStatus, self::LOCKED_STATUSES, true)) {
            return; // pre-paid orders are still mutable; that's expected.
        }

        foreach (self::LOCKED_FIELDS as $field) {
            if ($order->isDirty($field)) {
                $original = $order->getOriginal($field);
                $new      = $order->{$field};
                Log::error('Refused to mutate locked order field', [
                    'order_id'    => $order->id,
                    'order_status'=> $oldStatus,
                    'field'       => $field,
                    'old'         => $original,
                    'new'         => $new,
                ]);
                throw new \DomainException(sprintf(
                    "Order #%d is in '%s' state and field '%s' is immutable. "
                    . 'Use saveQuietly() with explicit audit-trail entry to override.',
                    $order->id, $oldStatus, $field,
                ));
            }
        }
    }
}
