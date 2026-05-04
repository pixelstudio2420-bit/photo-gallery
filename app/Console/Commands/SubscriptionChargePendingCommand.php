<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Hourly sweep: find pending subscription renewal Orders and auto-charge
 * the photographer's saved Omise card-on-file.
 *
 * Runs at minute :40 — after `subscriptions:renew-due` (:10) has had time
 * to create the renewal Order and the Order's payment_expires_at hasn't
 * yet kicked in (default 30 min from creation, so the Order is still
 * pending at :40 since renew-due ran at :10).
 *
 * Match criteria:
 *   • order_type = subscription
 *   • status = pending_payment
 *   • payment_expires_at is in the future (or null)
 *   • the related PhotographerSubscription has omise_customer_id
 *
 * For each match, calls SubscriptionService::chargeRenewal($order) which:
 *   • creates a PaymentTransaction (audit)
 *   • calls Omise charges API with the saved customer
 *   • on success: completes the transaction + fulfils the order (which
 *     activates the subscription via activateFromPaidInvoice)
 *   • on failure: fails the transaction + calls markRenewalFailed (which
 *     after max retries drops the sub into grace status)
 *
 * Idempotent: a successful charge moves the Order to `paid`, so the
 * second run sees `status_not_pending` and skips. A failed charge
 * leaves the Order at `pending_payment` so the cron retries it on the
 * next tick — but `markRenewalFailed` already incremented attempts +
 * set next_retry_at, and after max attempts the sub is in grace.
 */
class SubscriptionChargePendingCommand extends Command
{
    protected $signature   = 'subscriptions:charge-pending
                               {--limit=50 : Max orders to charge per run}
                               {--dry-run : List candidates without calling Omise}
                               {--quiet-if-none : Suppress success line when nothing to do}';
    protected $description = 'Auto-charge pending subscription renewal orders via stored Omise customer.';

    public function handle(SubscriptionService $svc): int
    {
        if (!$svc->systemEnabled()) {
            $this->line('Subscriptions system disabled — skipping.');
            return self::SUCCESS;
        }

        $limit  = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $quiet  = (bool) $this->option('quiet-if-none');
        $now    = now();

        // Pull candidate orders. We join through subscription_invoice →
        // photographer_subscription to filter on omise_customer_id at the
        // SQL level — avoids loading rows we'll skip in PHP.
        $orders = Order::query()
            ->where('order_type', Order::TYPE_SUBSCRIPTION)
            ->where('status', 'pending_payment')
            ->where(function ($q) use ($now) {
                $q->whereNull('payment_expires_at')
                  ->orWhere('payment_expires_at', '>', $now);
            })
            ->whereNotNull('subscription_invoice_id')
            ->whereExists(function ($q) {
                $q->select(\DB::raw(1))
                  ->from('subscription_invoices')
                  ->whereColumn('subscription_invoices.id', 'orders.subscription_invoice_id')
                  ->whereExists(function ($q2) {
                      $q2->select(\DB::raw(1))
                         ->from('photographer_subscriptions')
                         ->whereColumn('photographer_subscriptions.id', 'subscription_invoices.subscription_id')
                         ->whereNotNull('photographer_subscriptions.omise_customer_id');
                  });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            if (!$quiet) $this->info('No subscription orders pending auto-charge.');
            return self::SUCCESS;
        }

        $this->info("Found {$orders->count()} subscription order(s) ready for auto-charge.");

        $charged = 0;
        $failed  = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if ($dryRun) {
                $this->line(sprintf('  [dry] order#%d total=%.2f', $order->id, (float) $order->total));
                continue;
            }

            try {
                $result = $svc->chargeRenewal($order);

                if ($result['ok'] ?? false) {
                    $charged++;
                    $this->line("  ✓ order#{$order->id} charged " . ($result['charge_id'] ?? '—'));
                } elseif (in_array($result['reason'] ?? '', ['order_not_pending', 'invoice_missing', 'subscription_missing', 'no_customer'], true)) {
                    $skipped++;
                    $this->line("  - order#{$order->id} skipped ({$result['reason']})");
                } elseif (($result['reason'] ?? '') === 'charge_pending') {
                    // 3DS challenge — webhook will close the loop.
                    $this->line("  ⏳ order#{$order->id} pending 3DS (webhook will resolve)");
                } else {
                    $failed++;
                    $msg = $result['message'] ?? $result['reason'] ?? 'unknown';
                    $this->error("  ✗ order#{$order->id} {$msg}");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('subscriptions:charge-pending failed for order#' . $order->id, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("  ✗ order#{$order->id} exception: " . $e->getMessage());
            }
        }

        $this->info("Charged: {$charged}, failed: {$failed}, skipped: {$skipped}");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
