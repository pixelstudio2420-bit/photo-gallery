<?php

namespace App\Console\Commands;

use App\Services\Usage\UsagePeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * `php artisan usage:prune`
 *
 * Daily janitor for the usage tracking tables. Without pruning,
 * usage_counters fills up with millions of minute/hour rows that
 * nobody reads, and usage_events grows unbounded.
 *
 * Retention policy (defensive defaults — tunable in config later):
 *   - usage_counters minute → 2 hours
 *   - usage_counters hour   → 48 hours
 *   - usage_counters day    → 35 days
 *   - usage_counters month  → 13 months  (one full year of trend data)
 *   - usage_events          → 13 months  (cost reports back ~1 year)
 *   - signup_signals        → 90 days    (long enough for fraud analysis,
 *                                         short enough that stale IPs
 *                                         don't false-positive new users)
 *
 * Idempotent. --dry-run reports what would be deleted.
 */
class PruneUsageDataCommand extends Command
{
    protected $signature = 'usage:prune
        {--dry-run : Print what would be deleted without touching the DB}
        {--quiet-success : Suppress all-good output}';

    protected $description = 'Prune old usage_counters / usage_events / signup_signals rows by retention policy.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now    = Carbon::now();
        $totals = [];

        $totals['minute']   = $this->prune('usage_counters', 'period', '=',  UsagePeriod::MINUTE, $now->copy()->subHours(2),   $dryRun, periodKeyColumn: 'period_key');
        $totals['hour']     = $this->prune('usage_counters', 'period', '=',  UsagePeriod::HOUR,   $now->copy()->subHours(48),  $dryRun, periodKeyColumn: 'period_key');
        $totals['day']      = $this->prune('usage_counters', 'period', '=',  UsagePeriod::DAY,    $now->copy()->subDays(35),   $dryRun, periodKeyColumn: 'period_key');
        $totals['month']    = $this->prune('usage_counters', 'period', '=',  UsagePeriod::MONTH,  $now->copy()->subMonths(13), $dryRun, periodKeyColumn: 'period_key');
        $totals['events']   = $this->pruneByTimestamp('usage_events',   'occurred_at', $now->copy()->subMonths(13), $dryRun);
        $totals['signals']  = $this->pruneByTimestamp('signup_signals', 'created_at',  $now->copy()->subDays(90),   $dryRun);

        $totalDeleted = array_sum($totals);
        if ($totalDeleted === 0) {
            if (!$this->option('quiet-success')) {
                $this->components->info('Nothing to prune.');
            }
            return self::SUCCESS;
        }

        $action = $dryRun ? 'Would delete' : 'Deleted';
        foreach ($totals as $k => $n) {
            if ($n > 0) {
                $this->line(sprintf('  %s %d rows from %s', $action, $n, $k));
            }
        }
        Log::info('usage:prune complete', $totals + ['dry_run' => $dryRun]);
        return self::SUCCESS;
    }

    /**
     * Prune rows where (filterCol filterOp filterVal) AND $periodKeyColumn < threshold.
     * The period_key is a string like '2026-04-27', so a string comparison
     * works as long as the format is sortable (which it is — ISO dates).
     */
    private function prune(
        string $table,
        string $filterCol,
        string $filterOp,
        string $filterVal,
        Carbon $threshold,
        bool $dryRun,
        string $periodKeyColumn = 'period_key',
    ): int {
        // Match the period_key format of the bucket. UsagePeriod is the
        // single source of truth — if it ever throws here we've added a
        // new period type without updating one of these callsites.
        $thresholdKey = in_array($filterVal, UsagePeriod::ALL, true)
            ? UsagePeriod::key($filterVal, $threshold)
            : $threshold->toDateString();

        $q = DB::table($table)
            ->where($filterCol, $filterOp, $filterVal)
            ->where($periodKeyColumn, '<', $thresholdKey);

        if ($dryRun) {
            return (int) $q->count();
        }
        return (int) $q->delete();
    }

    private function pruneByTimestamp(string $table, string $col, Carbon $threshold, bool $dryRun): int
    {
        $q = DB::table($table)->where($col, '<', $threshold);
        if ($dryRun) {
            return (int) $q->count();
        }
        return (int) $q->delete();
    }
}
