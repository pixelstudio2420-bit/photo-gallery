<?php

namespace App\Console\Commands;

use App\Models\PhotographerSubscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily sweep: find subscriptions whose grace period has ended and
 * downgrade them to the free plan.
 *
 * Grace is entered when renewal payment fails after
 * max_renewal_attempts attempts (see SubscriptionService::markRenewalFailed).
 * During grace the photographer keeps full access — giving them a week
 * to update their card or switch gateway — before we yank the quota.
 *
 * Idempotent: expireGrace() no-ops if the sub is no longer in grace
 * (e.g. the photographer paid during the window).
 */
class SubscriptionExpireGraceCommand extends Command
{
    protected $signature   = 'subscriptions:expire-grace
                               {--dry-run : Show which subs would be expired without mutating}';
    protected $description = 'Downgrade subscriptions whose grace window has ended to the free plan.';

    public function handle(SubscriptionService $subs): int
    {
        if (!$subs->systemEnabled()) {
            $this->line('Subscriptions system disabled — skipping.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $expired = PhotographerSubscription::with('plan')
            ->graceExpired()
            ->get();

        $this->info("Found {$expired->count()} subscription(s) whose grace has expired.");

        $processed = 0;
        $failed    = 0;

        foreach ($expired as $sub) {
            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry] #%d %s → downgrade to free (grace ended %s)',
                    $sub->id,
                    $sub->photographer?->email ?? 'user#'.$sub->photographer_id,
                    $sub->grace_ends_at?->format('Y-m-d') ?? '—',
                ));
                continue;
            }

            try {
                $subs->expireGrace($sub);
                $processed++;
                $this->line("  ✓ sub#{$sub->id} downgraded");
            } catch (\Throwable $e) {
                $failed++;
                Log::error('subscriptions:expire-grace failed for sub#'.$sub->id, [
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ✗ sub#{$sub->id} — ".$e->getMessage());
            }
        }

        $this->info("Downgraded: {$processed}, failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
