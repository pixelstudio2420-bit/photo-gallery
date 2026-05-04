<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\UserStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Hourly auto-charge sweep for consumer cloud-storage subscriptions.
 * Mirror of `SubscriptionChargePendingCommand` for user storage plans.
 *
 * Bails out silently when the consumer storage system is disabled
 * (`user_storage_enabled=0`, default), so it's safe to keep scheduled
 * on installs that haven't enabled the feature.
 */
class UserStorageChargePendingCommand extends Command
{
    protected $signature   = 'user-storage:charge-pending
                               {--limit=50 : Max orders to charge per run}
                               {--dry-run : List candidates without calling Omise}
                               {--quiet-if-none : Suppress success line when nothing to do}';
    protected $description = 'Auto-charge pending user storage renewal orders via stored Omise customer.';

    public function handle(UserStorageService $svc): int
    {
        if (!$svc->systemEnabled()) {
            $this->line('User storage system disabled — skipping.');
            return self::SUCCESS;
        }

        $limit  = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $quiet  = (bool) $this->option('quiet-if-none');
        $now    = now();

        $orders = Order::query()
            ->where('order_type', Order::TYPE_USER_STORAGE_SUBSCRIPTION)
            ->where('status', 'pending_payment')
            ->where(function ($q) use ($now) {
                $q->whereNull('payment_expires_at')
                  ->orWhere('payment_expires_at', '>', $now);
            })
            ->whereNotNull('user_storage_invoice_id')
            ->whereExists(function ($q) {
                $q->select(\DB::raw(1))
                  ->from('user_storage_invoices')
                  ->whereColumn('user_storage_invoices.id', 'orders.user_storage_invoice_id')
                  ->whereExists(function ($q2) {
                      $q2->select(\DB::raw(1))
                         ->from('user_storage_subscriptions')
                         ->whereColumn('user_storage_subscriptions.id', 'user_storage_invoices.subscription_id')
                         ->whereNotNull('user_storage_subscriptions.omise_customer_id');
                  });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            if (!$quiet) $this->info('No user storage orders pending auto-charge.');
            return self::SUCCESS;
        }

        $this->info("Found {$orders->count()} user storage order(s) ready for auto-charge.");

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
                    $this->line("  ⏳ order#{$order->id} pending 3DS (webhook will resolve)");
                } else {
                    $failed++;
                    $msg = $result['message'] ?? $result['reason'] ?? 'unknown';
                    $this->error("  ✗ order#{$order->id} {$msg}");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('user-storage:charge-pending failed for order#' . $order->id, [
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
