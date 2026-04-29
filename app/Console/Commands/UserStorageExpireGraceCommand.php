<?php

namespace App\Console\Commands;

use App\Models\UserStorageSubscription;
use App\Services\UserStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily sweep: find consumer-storage subscriptions whose grace window has
 * ended and downgrade them to the free plan. During grace (usually 7 days)
 * the user keeps full read/write on their files — this gives them time to
 * fix a failing card or switch gateway before quota is yanked down to 5 GB.
 *
 * After downgrade:
 *   - Files stay on disk (we don't delete them).
 *   - If the user is over the free quota, uploads are blocked until they
 *     prune; they keep read/download access to existing files so they can
 *     migrate out. See FileManagerService::canUpload().
 *
 * Idempotent:
 *   - `graceExpired` scope only returns status=grace + grace_ends_at<=now
 *   - `expireGrace()` no-ops if the sub is no longer in grace (e.g. user
 *     paid during the window)
 *
 * Mirror of SubscriptionExpireGraceCommand (photographer tier).
 */
class UserStorageExpireGraceCommand extends Command
{
    protected $signature   = 'user-storage:expire-grace
                               {--dry-run : Show which subs would be expired without mutating}';
    protected $description = 'Downgrade consumer-storage subs whose grace window has ended to the free plan.';

    public function handle(UserStorageService $svc): int
    {
        if (!$svc->systemEnabled()) {
            $this->line('Consumer storage system disabled — skipping.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $expired = UserStorageSubscription::with('plan', 'user')
            ->graceExpired()
            ->get();

        $this->info("Found {$expired->count()} storage sub(s) whose grace has expired.");

        $processed = 0;
        $failed    = 0;

        foreach ($expired as $sub) {
            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry] #%d %s → downgrade to free (grace ended %s)',
                    $sub->id,
                    $sub->user?->email ?? 'user#'.$sub->user_id,
                    $sub->grace_ends_at?->format('Y-m-d') ?? '—',
                ));
                continue;
            }

            try {
                $svc->expireGrace($sub);
                $processed++;
                $this->line("  ✓ sub#{$sub->id} downgraded to free");
            } catch (\Throwable $e) {
                $failed++;
                Log::error('user-storage:expire-grace failed for sub#'.$sub->id, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("  ✗ sub#{$sub->id} — ".$e->getMessage());
            }
        }

        $this->info("Downgraded: {$processed}, failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
