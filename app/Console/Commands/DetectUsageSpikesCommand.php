<?php

namespace App\Console\Commands;

use App\Services\Usage\UsagePeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * `php artisan usage:detect-spikes`
 *
 * Run hourly to catch users whose hourly usage exceeds N× their 7-day
 * moving average. Catches scripted abuse + credential-stuffed accounts
 * BEFORE the monthly quota check would notice.
 *
 * Detection logic per (user, resource):
 *   1. baseline = AVG(hourly_units) over last 7d, exclude current hour
 *   2. current  = SUM(units) in the most recent completed hour
 *   3. trip when current > baseline × multiplier AND baseline >= min
 *
 * Output:
 *   - logs the spike at WARNING level
 *   - inserts a sentinel ledger row (resource = '_spike') for audit
 *   - returns rows for piping to alerting / Slack / Sentry
 */
class DetectUsageSpikesCommand extends Command
{
    protected $signature = 'usage:detect-spikes
        {--quiet-success : Only print when spikes are detected}
        {--multiple= : Override the spike multiplier from config}
        {--dry-run : Detect but do not log/insert sentinel rows}';

    protected $description = 'Detect users whose recent hourly usage exceeds Nx their 7-day moving average.';

    public function handle(): int
    {
        if (!config('usage.spike_detection.enabled', true)) {
            if (!$this->option('quiet-success')) {
                $this->components->info('Spike detection is disabled (config usage.spike_detection.enabled).');
            }
            return self::SUCCESS;
        }

        $multiplier = (float) ($this->option('multiple') ?: config('usage.spike_detection.multiple_of_7d_avg', 10));
        $minBase    = (int) config('usage.spike_detection.min_baseline_calls', 50);
        $dryRun     = (bool) $this->option('dry-run');

        $now    = Carbon::now();
        $hour   = $now->copy()->subHour()->startOfHour();   // most recent completed hour
        $weekAgo  = $hour->copy()->subDays(7);

        // Baseline per (user, resource) — average hourly units over 7d
        $baseline = DB::table('usage_counters')
            ->where('period', UsagePeriod::HOUR)
            ->whereBetween('period_key', [
                UsagePeriod::key(UsagePeriod::HOUR, $weekAgo),
                UsagePeriod::key(UsagePeriod::HOUR, $hour->copy()->subSecond()),
            ])
            ->groupBy('user_id', 'resource')
            ->selectRaw('user_id, resource,
                         CAST(SUM(units) AS FLOAT) / MAX(COUNT(*), 1) AS avg_units,
                         SUM(units) AS total_units')
            ->get()
            ->keyBy(fn ($r) => $r->user_id . '|' . $r->resource);

        // Current hour's totals
        $current = DB::table('usage_counters')
            ->where('period', UsagePeriod::HOUR)
            ->where('period_key', UsagePeriod::key(UsagePeriod::HOUR, $hour))
            ->select('user_id', 'resource', 'units')
            ->get();

        $spikes = [];
        foreach ($current as $row) {
            $key  = $row->user_id . '|' . $row->resource;
            $base = $baseline->get($key);
            if (!$base) continue;

            $avg = (float) $base->avg_units;
            $tot = (int)   $base->total_units;
            if ($tot < $minBase) continue;             // not enough signal
            if ($row->units <= $avg * $multiplier) continue;

            $spikes[] = [
                'user_id'    => (int) $row->user_id,
                'resource'   => (string) $row->resource,
                'current'    => (int) $row->units,
                'baseline'   => round($avg, 2),
                'multiplier' => round($row->units / max(1.0, $avg), 1),
            ];
        }

        if (empty($spikes)) {
            if (!$this->option('quiet-success')) {
                $this->components->info('No usage spikes detected.');
            }
            return self::SUCCESS;
        }

        $this->components->warn(sprintf('Detected %d usage spike(s):', count($spikes)));
        foreach ($spikes as $s) {
            $this->line(sprintf(
                '  user=%d resource=%s current=%d baseline=%.1f multiplier=%.1fx',
                $s['user_id'], $s['resource'], $s['current'], $s['baseline'], $s['multiplier'],
            ));
            if (!$dryRun) {
                Log::warning('Usage spike detected', $s);
                // Sentinel ledger row so admins can grep usage_events for spikes.
                DB::table('usage_events')->insert([
                    'user_id'         => $s['user_id'],
                    'plan_code'       => '_meta',
                    'resource'        => '_spike',
                    'units'           => 0,
                    'cost_microcents' => 0,
                    'metadata'        => json_encode($s),
                    'occurred_at'     => $now,
                ]);
            }
        }

        return self::SUCCESS;
    }
}
