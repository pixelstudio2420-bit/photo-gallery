<?php

namespace App\Console\Commands;

use App\Models\UserStorageSubscription;
use App\Services\UserStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Hourly sweep: find consumer-storage subscriptions whose current period
 * ends within the next N hours and create a renewal invoice + Order for
 * each. A separate cron (or the payment gateway webhook) is responsible
 * for actually charging the customer — this command only materialises
 * the next period's billing artefacts.
 *
 * Runs hourly rather than daily because:
 *   - Consumer subs are mostly monthly, period_end lands at any hour
 *   - Charging via Omise takes a few seconds — we want renewals to land
 *     on (or near) the original anniversary hour, not the next 00:00 UTC.
 *
 * Safe to run repeatedly:
 *   - `renew()` bails out on free plans / cancel-at-period-end
 *   - Existing pending renewal invoices for the same period are NOT
 *     detected here; relies on the caller only invoking once per period.
 *     (dueForRenewal scope filters cancel_at_period_end=true, and
 *     period_end advances once we mark the invoice paid — so repeat
 *     invocations within the same hour just re-queue the same sub,
 *     which is cheap.)
 *
 * Mirror of SubscriptionRenewDueCommand (photographer tier) — kept as
 * a separate command so we can run / debug them independently.
 */
class UserStorageRenewDueCommand extends Command
{
    protected $signature   = 'user-storage:renew-due
                               {--hours=24 : Look-ahead window in hours}
                               {--dry-run  : Show subs that would renew without creating orders}';
    protected $description = 'Create renewal invoices for consumer-storage subs expiring soon.';

    public function handle(UserStorageService $svc): int
    {
        if (!$svc->systemEnabled()) {
            $this->line('Consumer storage system disabled — skipping.');
            return self::SUCCESS;
        }

        $hours  = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');

        $due = UserStorageSubscription::with('plan', 'user')
            ->dueForRenewal($hours)
            ->get();

        $this->info("Found {$due->count()} storage sub(s) due for renewal within {$hours}h.");

        $created = 0;
        $failed  = 0;

        foreach ($due as $sub) {
            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry] #%d %s → %s (period ends %s)',
                    $sub->id,
                    $sub->user?->email ?? 'user#'.$sub->user_id,
                    $sub->plan?->code ?? '—',
                    $sub->current_period_end?->format('Y-m-d H:i') ?? '—',
                ));
                continue;
            }

            try {
                $order = $svc->renew($sub);
                if ($order) {
                    $created++;
                    $this->line("  ✓ sub#{$sub->id} → order#{$order->id} (฿{$order->total})");
                } else {
                    $this->line("  - sub#{$sub->id} — nothing to do (free / cancelled / no plan)");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('user-storage:renew-due failed for sub#'.$sub->id, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("  ✗ sub#{$sub->id} — ".$e->getMessage());
            }
        }

        $this->info("Renewal invoices created: {$created}, failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
