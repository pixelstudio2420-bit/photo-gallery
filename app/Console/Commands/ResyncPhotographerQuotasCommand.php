<?php

namespace App\Console\Commands;

use App\Models\PhotographerProfile;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * One-shot command: re-sync every photographer's storage_quota_bytes (and
 * commission_rate, AI credits, etc.) from their CURRENT subscription plan.
 *
 * Use cases:
 *   - After upgrading the StorageQuotaService logic to be plan-driven.
 *   - After bulk plan changes (e.g. admin edits Pro plan storage from
 *     100 GB → 150 GB and wants existing Pro photographers to get the
 *     new cap immediately, not at next renewal).
 *   - After a botched seeder run that overwrote `commission_rate`.
 *
 * Idempotent — safe to run any number of times.
 *
 * Usage:
 *   php artisan storage:resync-photographer-quotas
 *   php artisan storage:resync-photographer-quotas --dry-run
 *   php artisan storage:resync-photographer-quotas --user-id=2
 */
class ResyncPhotographerQuotasCommand extends Command
{
    protected $signature = 'storage:resync-photographer-quotas
                            {--user-id= : Only re-sync one photographer}
                            {--dry-run  : Show what would change without writing}';

    protected $description = "Re-sync every photographer's storage quota + commission rate from their current subscription plan";

    public function handle(SubscriptionService $subs): int
    {
        $userId = $this->option('user-id');
        $dryRun = (bool) $this->option('dry-run');

        $query = PhotographerProfile::query();
        if ($userId) $query->where('user_id', (int) $userId);

        $profiles = $query->get();
        if ($profiles->isEmpty()) {
            $this->warn('No photographers matched.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Re-syncing '.$profiles->count().' photographer(s)…');
        $this->newLine();

        $rows = [];
        $changed = 0;

        foreach ($profiles as $profile) {
            $plan = $subs->currentPlan($profile);
            $oldQuotaGb  = round((int) ($profile->storage_quota_bytes ?? 0) / (1024 ** 3), 2);
            $newQuotaGb  = round((int) ($plan->storage_bytes ?? 0) / (1024 ** 3), 2);
            $oldRate     = (float) $profile->commission_rate;
            $newRate     = max(0, 100 - (float) ($plan->commission_pct ?? 0));

            $diff = ($oldQuotaGb !== $newQuotaGb) || (abs($oldRate - $newRate) > 0.001);

            $rows[] = [
                $profile->user_id,
                $plan->code,
                $oldQuotaGb.' GB',
                $newQuotaGb.' GB',
                $oldRate.'%',
                $newRate.'%',
                $diff ? '✓ changed' : '— same',
            ];

            if (!$diff) continue;

            if (!$dryRun) {
                $sub = \App\Models\PhotographerSubscription::where('photographer_id', $profile->user_id)
                    ->where('status', 'active')
                    ->first();
                if ($sub) {
                    $subs->syncProfileCache($profile, $sub->fresh('plan'));
                } else {
                    // No active sub — provision Free + sync
                    $sub = $subs->ensureFreeSubscription($profile);
                    $subs->syncProfileCache($profile->fresh(), $sub->fresh('plan'));
                }
                $changed++;
            } else {
                $changed++;
            }
        }

        $this->table(
            ['User', 'Plan', 'Old Quota', 'New Quota', 'Old Rate', 'New Rate', 'Status'],
            $rows
        );

        $this->newLine();
        if ($dryRun) {
            $this->info("DRY RUN: would update {$changed} photographer(s). Run without --dry-run to apply.");
        } else {
            $this->info("Updated {$changed} photographer(s).");
        }

        return self::SUCCESS;
    }
}
