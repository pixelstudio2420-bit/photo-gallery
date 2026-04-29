<?php

namespace App\Console\Commands;

use App\Models\PhotographerSubscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Hourly sweep: find subscriptions whose current period ends within the next
 * N hours and create renewal invoices + orders for them.
 *
 * Once an Order is created, the photographer's configured payment method
 * takes over:
 *   • Omise charge customer (if omise_customer_id stored) — charged via cron
 *     that polls pending renewal orders and dispatches to the gateway.
 *   • Manual gateway (promptpay/bank transfer) — we email the photographer
 *     a payment link; they have until grace_ends_at to pay.
 *
 * Safe to run repeatedly: renew() is a no-op if the subscription already
 * has a pending renewal invoice for the upcoming period.
 */
class SubscriptionRenewDueCommand extends Command
{
    protected $signature   = 'subscriptions:renew-due
                               {--hours=24 : Look ahead window in hours}
                               {--dry-run : Show which subs would renew without creating orders}';
    protected $description = 'Create renewal invoices for subscriptions expiring soon.';

    public function handle(SubscriptionService $subs): int
    {
        if (!$subs->systemEnabled()) {
            $this->line('Subscriptions system disabled — skipping.');
            return self::SUCCESS;
        }

        $hours  = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');

        $due = PhotographerSubscription::with('plan')
            ->dueForRenewal($hours)
            ->where('cancel_at_period_end', false)
            ->get();

        $this->info("Found {$due->count()} subscription(s) due for renewal within {$hours}h.");

        $created = 0;
        $failed  = 0;

        foreach ($due as $sub) {
            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry] #%d %s → %s (period ends %s)',
                    $sub->id,
                    $sub->photographer?->email ?? 'user#'.$sub->photographer_id,
                    $sub->plan?->code ?? '—',
                    $sub->current_period_end?->format('Y-m-d H:i') ?? '—',
                ));
                continue;
            }

            try {
                $order = $subs->renew($sub);
                if ($order) {
                    $created++;
                    $this->line("  ✓ sub#{$sub->id} → order#{$order->id}");
                } else {
                    $this->line("  - sub#{$sub->id} — nothing to do (free / cancelled / no plan)");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('subscriptions:renew-due failed for sub#'.$sub->id, [
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ✗ sub#{$sub->id} — ".$e->getMessage());
            }
        }

        $this->info("Renewal invoices created: {$created}, failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
