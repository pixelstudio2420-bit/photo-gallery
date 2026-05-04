<?php

namespace App\Console\Commands;

use App\Models\UserStorageSubscription;
use App\Services\UserStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Hourly sweep for consumer cloud-storage subscriptions that have run
 * past their `current_period_end`. Flips them to `expired` and reverts
 * the user's denormalised quota columns on `auth_users` to free-tier.
 *
 * Mirror of `SubscriptionExpireOverdueCommand` for the consumer side.
 * Bails out silently when the consumer storage system is disabled
 * (`user_storage_enabled=0`, default), so it's harmless to keep
 * scheduled on installs that haven't enabled the feature.
 *
 * Idempotent: `expireOverdue()` no-ops on subs whose status is no
 * longer `active` or whose period_end is still in the future.
 */
class UserStorageExpireOverdueCommand extends Command
{
    protected $signature   = 'user-storage:expire-overdue
                               {--dry-run : List which subs would expire without changing anything}
                               {--quiet-if-none : Suppress success line when no subs are due}';
    protected $description = 'Expire active user storage subscriptions whose current_period_end is in the past.';

    public function handle(UserStorageService $svc): int
    {
        if (!$svc->systemEnabled()) {
            $this->line('User storage system disabled — skipping.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $quiet  = (bool) $this->option('quiet-if-none');
        $now    = now();

        $overdue = UserStorageSubscription::with('plan', 'user')
            ->where('status', UserStorageSubscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->orderBy('current_period_end')
            ->get();

        if ($overdue->isEmpty()) {
            if (!$quiet) $this->info('No active user storage subscriptions past their period_end.');
            return self::SUCCESS;
        }

        $this->info("Found {$overdue->count()} user storage subscription(s) past current_period_end.");

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
                    $sub->user?->email ?? 'user#' . $sub->user_id,
                    $sub->plan?->code ?? '—',
                    $sub->current_period_end->format('Y-m-d H:i'),
                    $hoursLate,
                ));
                continue;
            }

            try {
                $svc->expireOverdue($sub);

                $after = $sub->fresh();
                if ($after && $after->status === UserStorageSubscription::STATUS_EXPIRED) {
                    $expired++;
                    $this->line("  ✓ sub#{$sub->id} → expired ({$hoursLate}h late)");
                } else {
                    $this->line("  - sub#{$sub->id} — skipped (status changed mid-flight)");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('user-storage:expire-overdue failed for sub#' . $sub->id, [
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
