<?php

namespace App\Console\Commands;

use App\Services\Analytics\UsageTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two-step rollup:
 *
 *  1. minute  — flush cache buckets (last 10 min) into
 *               request_minute_buckets. Runs every minute.
 *
 *  2. daily   — aggregate completed minute rows into usage_daily.
 *               Runs once per day at 00:05 UTC for "yesterday".
 *
 * The two phases are separate commands so the daily cron can run
 * independently of the per-minute drain (which keeps cache from
 * filling up).
 */
class AnalyticsRollupCommand extends Command
{
    protected $signature = 'analytics:rollup
                            {phase=minute : minute|daily}
                            {--date= : YYYY-MM-DD; defaults to yesterday for "daily"}';

    protected $description = 'Flush usage metrics from cache → buckets, then aggregate into usage_daily';

    public function handle(UsageTracker $tracker): int
    {
        $phase = (string) $this->argument('phase');

        return match ($phase) {
            'minute' => $this->runMinute($tracker),
            'daily'  => $this->runDaily(),
            default  => $this->fail('phase must be minute|daily'),
        };
    }

    private function runMinute(UsageTracker $tracker): int
    {
        $persisted = $tracker->flushTo(now());
        $this->info("Flushed {$persisted} bucket row(s) from cache");
        return self::SUCCESS;
    }

    private function runDaily(): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::parse((string) $this->option('date'))
            : now()->subDay();
        $start = $date->copy()->startOfDay();
        $end   = $date->copy()->endOfDay();
        $dateStr = $start->toDateString();

        $this->line("Rolling up {$dateStr} from request_minute_buckets...");

        // Aggregate per-route + overall metrics.
        $perGroup = DB::table('request_minute_buckets')
            ->where('bucket_at', '>=', $start)
            ->where('bucket_at', '<=', $end)
            ->select('route_group')
            ->selectRaw('
                SUM(request_count) AS total_requests,
                SUM(error_count)   AS total_errors,
                SUM(duration_ms_sum) AS total_duration,
                MAX(request_count) AS peak_minute,
                SUM(distinct_users) AS users_signal
            ')
            ->groupBy('route_group')
            ->get();

        if ($perGroup->isEmpty()) {
            $this->warn("No data for {$dateStr}");
            return self::SUCCESS;
        }

        $totals = ['requests' => 0, 'errors' => 0, 'duration' => 0, 'peak_minute' => 0, 'users_signal' => 0];

        foreach ($perGroup as $row) {
            $totals['requests']     += (int) $row->total_requests;
            $totals['errors']       += (int) $row->total_errors;
            $totals['duration']     += (int) $row->total_duration;
            $totals['peak_minute']   = max($totals['peak_minute'], (int) $row->peak_minute);
            $totals['users_signal'] += (int) $row->users_signal;

            $this->upsertDaily($dateStr, 'requests.total', $row->route_group, (int) $row->total_requests);
            $this->upsertDaily($dateStr, 'requests.errors', $row->route_group, (int) $row->total_errors);
        }

        // Overall rows (feature = NULL).
        $this->upsertDaily($dateStr, 'requests.total',   null, $totals['requests']);
        $this->upsertDaily($dateStr, 'requests.errors',  null, $totals['errors']);
        $this->upsertDaily($dateStr, 'requests.peak_rps', null, (int) ($totals['peak_minute'] / 60));
        $this->upsertDaily($dateStr, 'requests.avg_ms',  null,
            $totals['requests'] > 0 ? (int) ($totals['duration'] / $totals['requests']) : 0);

        // DAU — prefer activity_logs distinct user_id, fall back to estimate.
        $dau = $this->dau($start, $end, $totals['users_signal']);
        $this->upsertDaily($dateStr, 'users.dau', null, $dau);

        // Domain-level totals (uploads, bookings, line pushes, ...).
        $this->upsertDomainCounts($dateStr, $start, $end);

        $this->info(sprintf(
            'rollup %s: requests=%d errors=%d peak_rps=%d dau=%d',
            $dateStr,
            $totals['requests'],
            $totals['errors'],
            (int) ($totals['peak_minute'] / 60),
            $dau,
        ));
        return self::SUCCESS;
    }

    private function upsertDaily(string $date, string $metric, ?string $feature, int $value): void
    {
        DB::table('usage_daily')->updateOrInsert(
            ['date' => $date, 'metric' => $metric, 'feature' => $feature],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private function dau($start, $end, int $bucketEstimate): int
    {
        if (Schema::hasTable('activity_logs')) {
            try {
                return (int) DB::table('activity_logs')
                    ->whereBetween('created_at', [$start, $end])
                    ->whereNotNull('user_id')
                    ->distinct('user_id')
                    ->count('user_id');
            } catch (\Throwable) {
                // fall through
            }
        }
        return (int) ($bucketEstimate * 0.20);   // overlap-correction estimate
    }

    /**
     * Domain-level counters — these are exact (sourced from the
     * domain tables) so they don't need the bucket aggregation.
     */
    private function upsertDomainCounts(string $date, $start, $end): void
    {
        // Photo uploads.
        if (Schema::hasTable('event_photos')) {
            $count = (int) DB::table('event_photos')
                ->whereBetween('created_at', [$start, $end])
                ->count();
            $this->upsertDaily($date, 'uploads.photos', null, $count);
        }

        // Booking creates.
        if (Schema::hasTable('bookings')) {
            $count = (int) DB::table('bookings')
                ->whereBetween('created_at', [$start, $end])
                ->count();
            $this->upsertDaily($date, 'bookings.created', null, $count);
        }

        // LINE pushes.
        if (Schema::hasTable('line_deliveries')) {
            $sent = (int) DB::table('line_deliveries')
                ->whereBetween('created_at', [$start, $end])
                ->where('status', 'sent')->count();
            $failed = (int) DB::table('line_deliveries')
                ->whereBetween('created_at', [$start, $end])
                ->where('status', 'failed')->count();
            $this->upsertDaily($date, 'line.pushes', null, $sent);
            $this->upsertDaily($date, 'line.errors', null, $failed);
        }
    }
}
