<?php

namespace App\Console\Commands;

use App\Models\PhotographerSubscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Hourly sweep: find active subscriptions whose `current_period_end` has
 * already passed and downgrade them to the free plan.
 *
 * Why this exists:
 *   The renewal pipeline (`subscriptions:renew-due` → `SubscriptionService::renew()`)
 *   only creates pending Orders. Until auto-charge of a saved payment
 *   method is wired up (Phase B of the billing rebuild), nothing else
 *   transitions an `active` subscription out of `active` after the period
 *   runs out — meaning a one-time-paid sub kept paid-tier features
 *   permanently. This command closes that hole by enforcing the plan
 *   period directly: when `current_period_end < now()` AND the sub is
 *   still flagged active, flip it to `expired` and revert the
 *   photographer profile to free-tier limits.
 *
 * What this does NOT do:
 *   • Does not charge a payment method (that's Phase B).
 *   • Does not transition `grace` → `expired` — `subscriptions:expire-grace`
 *     handles that path (which fires once `markRenewalFailed()` has been
 *     called enough times to enter grace; relevant once Phase B brings
 *     auto-charge online).
 *   • Does not re-create a renewal invoice/Order — `subscriptions:renew-due`
 *     handles that earlier in the period via its 24h look-ahead window.
 *
 * Idempotent: each `expireOverdue()` no-ops on subs that are already
 * non-active or whose period_end is still in the future, so re-running
 * the command in a single hour (e.g. during a deploy) produces zero
 * duplicate work.
 */
class SubscriptionExpireOverdueCommand extends Command
{
    protected $signature   = 'subscriptions:expire-overdue
                               {--dry-run : List which subs would expire without changing anything}
                               {--quiet-if-none : Suppress success line when no subs are due}';
    protected $description = 'Expire active subscriptions whose current_period_end is in the past.';

    public function handle(SubscriptionService $subs): int
    {
        if (!$subs->systemEnabled()) {
            $this->line('Subscriptions system disabled — skipping.');
            return self::SUCCESS;
        }

        $dryRun     = (bool) $this->option('dry-run');
        $quiet      = (bool) $this->option('quiet-if-none');
        $now        = now();

        $overdue = PhotographerSubscription::with('plan', 'photographer')
            ->where('status', PhotographerSubscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->orderBy('current_period_end')
            ->get();

        if ($overdue->isEmpty()) {
            if (!$quiet) $this->info('No active subscriptions past their period_end.');
            return self::SUCCESS;
        }

        $this->info("Found {$overdue->count()} subscription(s) past current_period_end.");

        $expired = 0;
        $failed  = 0;

        foreach ($overdue as $sub) {
            // diffInHours in Carbon 3 returns float and is signed when the
            // target is in the past. Take abs() + cast → integer hours.
            $hoursLate = (int) abs($sub->current_period_end->diffInHours($now));

            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry] sub#%d %s plan=%s ended=%s (%dh ago)',
                    $sub->id,
                    $sub->photographer?->email ?? 'user#' . $sub->photographer_id,
                    $sub->plan?->code ?? '—',
                    $sub->current_period_end->format('Y-m-d H:i'),
                    $hoursLate,
                ));
                continue;
            }

            try {
                $subs->expireOverdue($sub);

                // Re-fetch and verify the transition actually happened —
                // expireOverdue() is a no-op if the row was paid by a
                // race-condition webhook between the SELECT and our call.
                $after = $sub->fresh();
                if ($after && $after->status === PhotographerSubscription::STATUS_EXPIRED) {
                    $expired++;
                    $this->line("  ✓ sub#{$sub->id} → expired ({$hoursLate}h late)");
                } else {
                    $this->line("  - sub#{$sub->id} — skipped (status changed mid-flight)");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('subscriptions:expire-overdue failed for sub#' . $sub->id, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("  ✗ sub#{$sub->id} — " . $e->getMessage());
            }
        }

        $this->info("Expired: {$expired}, failed: {$failed}");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
