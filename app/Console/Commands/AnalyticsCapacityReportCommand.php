<?php

namespace App\Console\Commands;

use App\Services\Analytics\CapacityCalculator;
use Illuminate\Console\Command;

/**
 * `php artisan analytics:capacity-report`
 *
 * Prints the snapshot in human-readable form. Same data is exposed
 * as JSON via the admin endpoint for the dashboard.
 */
class AnalyticsCapacityReportCommand extends Command
{
    protected $signature   = 'analytics:capacity-report {--json}';
    protected $description = 'Print a usage-vs-capacity snapshot';

    public function handle(CapacityCalculator $calc): int
    {
        $snap = $calc->snapshot();

        if ($this->option('json')) {
            $this->line(json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info("─────────────  Capacity snapshot @ {$snap['as_of']}  ─────────────");
        $this->line('');
        $this->line("Today so far ({$snap['today']['date']}):");
        $this->line(sprintf('  requests=%-8d errors=%-4d (%.2f%%)  peak_rps=%-5.1f  avg_ms=%dms',
            $snap['today']['requests_so_far'],
            $snap['today']['errors_so_far'],
            $snap['today']['error_rate_percent'],
            $snap['today']['peak_rps_so_far'],
            $snap['today']['avg_request_ms'],
        ));
        $this->line('  DAU (est)=' . $snap['today']['dau_estimated']
            . '  projected EOD requests=' . $snap['today']['projected_eod_requests']);
        $this->line('');

        if ($snap['recent_minute']['bucket_at']) {
            $this->line("Last completed minute ({$snap['recent_minute']['bucket_at']}):");
            $this->line(sprintf('  rps=%-5.1f  errors=%d (%.2f%%)  avg_ms=%d  max_ms=%d',
                $snap['recent_minute']['rps'],
                $snap['recent_minute']['errors'],
                $snap['recent_minute']['error_rate_percent'],
                $snap['recent_minute']['avg_ms'],
                $snap['recent_minute']['max_ms'],
            ));
            $this->line('');
        }

        $this->line('Utilization vs capacity baselines:');
        foreach ($snap['utilization'] as $dim => $row) {
            $bar = $this->bar((float) $row['percent']);
            $this->line(sprintf(
                '  %-18s %s %5.1f%%  (%s of %s, baseline=%s)',
                $dim, $bar, $row['percent'], $row['observed'], $row['ceiling'], $row['baseline_metric'],
            ));
        }
        $this->line('');

        if ($snap['bottleneck']) {
            $this->line('Bottleneck: ' . $snap['bottleneck']['dimension']
                . ' (' . $snap['bottleneck']['percent'] . '%)');
        }

        $this->line('');
        $this->line('Recommendations:');
        foreach ($snap['recommendations'] as $tip) {
            $this->line('  • ' . $tip);
        }
        return self::SUCCESS;
    }

    private function bar(float $pct): string
    {
        $pct = max(0, min(100, $pct));
        $full = (int) ($pct / 5);
        return '[' . str_repeat('█', $full) . str_repeat('·', 20 - $full) . ']';
    }
}
