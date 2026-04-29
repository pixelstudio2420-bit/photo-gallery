<?php

namespace App\Console\Commands;

use App\Services\Notifications\PhotographerLifecycleNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Storage + AI-credits threshold notifier.
 *
 * Walks photographer_profiles once and fires lifecycle notifications
 * at:
 *
 *   Storage:
 *     • >= 95% used  → CRITICAL  (red flex, email, immediate action)
 *     • >= 80% used  → WARN      (amber flex, email)
 *
 *   AI Credits (current billing period):
 *     • used >= cap  → CRITICAL  (depleted — features blocked)
 *
 * Each notification is dedup'd per calendar month (refId carries
 * the YYYYMM stamp) so a photographer who hovers around 80% all
 * month doesn't get a fresh alert every cron run.
 *
 * Designed to run hourly. The query is one indexed scan over
 * photographer_profiles; cheap even at 10k photographers.
 */
class UsageNotifyThresholdsCommand extends Command
{
    protected $signature   = 'usage:notify-thresholds
                              {--quiet-if-none : Suppress output when no thresholds tripped}';
    protected $description = 'Notify photographers when storage or AI-credit usage crosses thresholds';

    private const STORAGE_WARN_PCT     = 80;
    private const STORAGE_CRITICAL_PCT = 95;

    public function handle(PhotographerLifecycleNotifier $notifier): int
    {
        $fired = ['storage_warn' => 0, 'storage_critical' => 0, 'ai_depleted' => 0];

        // ── Storage thresholds ──
        $rows = DB::table('photographer_profiles')
            ->whereNotNull('storage_quota_bytes')
            ->where('storage_quota_bytes', '>', 0)
            ->select('user_id', 'storage_used_bytes', 'storage_quota_bytes')
            ->get();

        foreach ($rows as $row) {
            $used  = (int) $row->storage_used_bytes;
            $quota = (int) $row->storage_quota_bytes;
            if ($quota <= 0) continue;
            $pct = $used / $quota * 100;

            try {
                if ($pct >= self::STORAGE_CRITICAL_PCT) {
                    $notifier->storageWarning(
                        photographerId: (int) $row->user_id,
                        usedBytes:      $used,
                        quotaBytes:     $quota,
                        critical:       true,
                    );
                    $fired['storage_critical']++;
                } elseif ($pct >= self::STORAGE_WARN_PCT) {
                    $notifier->storageWarning(
                        photographerId: (int) $row->user_id,
                        usedBytes:      $used,
                        quotaBytes:     $quota,
                        critical:       false,
                    );
                    $fired['storage_warn']++;
                }
            } catch (\Throwable $e) {
                Log::warning('usage:notify-thresholds storage failed', [
                    'user_id' => $row->user_id, 'error' => $e->getMessage(),
                ]);
            }
        }

        // ── AI credits thresholds ──
        // monthly_ai_credits comes from the plan; check via join.
        $aiRows = DB::table('photographer_profiles as p')
            ->leftJoin('subscription_plans as sp', 'sp.code', '=', 'p.subscription_plan_code')
            ->whereNotNull('sp.monthly_ai_credits')
            ->where('sp.monthly_ai_credits', '>', 0)
            ->whereNotNull('p.ai_credits_used')
            ->select(
                'p.user_id',
                'p.ai_credits_used',
                'sp.monthly_ai_credits as cap',
                'p.ai_credits_period_end',
            )
            ->get();

        foreach ($aiRows as $row) {
            $used = (int) $row->ai_credits_used;
            $cap  = (int) $row->cap;
            if ($cap <= 0 || $used < $cap) continue;

            try {
                $notifier->aiCreditsDepleted(
                    photographerId: (int) $row->user_id,
                    used:           $used,
                    cap:            $cap,
                    resetAt:        $row->ai_credits_period_end
                                       ? \Carbon\Carbon::parse($row->ai_credits_period_end)
                                       : null,
                );
                $fired['ai_depleted']++;
            } catch (\Throwable $e) {
                Log::warning('usage:notify-thresholds ai_credits failed', [
                    'user_id' => $row->user_id, 'error' => $e->getMessage(),
                ]);
            }
        }

        $total = array_sum($fired);
        if ($total === 0 && $this->option('quiet-if-none')) {
            return self::SUCCESS;
        }
        $this->info(sprintf(
            'usage:notify-thresholds storage_warn=%d storage_critical=%d ai_depleted=%d',
            $fired['storage_warn'],
            $fired['storage_critical'],
            $fired['ai_depleted'],
        ));
        return self::SUCCESS;
    }
}
