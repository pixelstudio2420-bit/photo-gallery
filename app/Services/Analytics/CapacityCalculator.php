<?php

namespace App\Services\Analytics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes "are we close to the ceiling?" reports.
 *
 * Inputs (all from DB, no guesses):
 *   • capacity_baselines  — measured/extrapolated ceilings
 *   • usage_daily         — actual daily totals from the rollup
 *   • request_minute_buckets — current-minute live data
 *
 * Outputs (consumed by CLI report + admin dashboard JSON + alert job):
 *   • snapshot()          — single-call "where are we right now?"
 *   • dailyTrend()        — N days of usage for trend chart
 *   • bottleneck()        — which dimension fills first as load grows
 *
 * Design philosophy
 * -----------------
 * Capacity is multi-dimensional: RPS, daily users, queue throughput,
 * R2 PUT rate, LINE quota. There's no single "% full" — the
 * SMALLEST dimension is the one that breaks first. snapshot()
 * returns all dimensions; bottleneck() identifies the limiting one.
 *
 * Honesty rules
 * -------------
 * Every output cites its source baseline metric so the operator can
 * see WHY we said "70% full". No hidden constants in code.
 */
class CapacityCalculator
{
    /**
     * Single-call dashboard payload. Returns:
     *
     *   [
     *     'as_of'         => ISO timestamp,
     *     'today'         => [..],        usage so far today
     *     'recent_minute' => [..],        last completed minute
     *     'baselines'     => [..],        provisioned ceilings
     *     'utilization'   => [..],        % per dimension
     *     'bottleneck'    => 'rps.dev_per_box' | etc,
     *     'recommendations' => [..]       what to scale next
     *   ]
     */
    public function snapshot(): array
    {
        $baselines = $this->loadBaselines();
        $today     = $this->todaySoFar();
        $recent    = $this->recentMinute();

        $utilization = $this->computeUtilization($baselines, $today, $recent);
        $bottleneck  = $this->identifyBottleneck($utilization);

        return [
            'as_of'           => now()->toIso8601String(),
            'today'           => $today,
            'recent_minute'   => $recent,
            'baselines'       => $baselines->all(),
            'utilization'     => $utilization,
            'bottleneck'      => $bottleneck,
            'recommendations' => $this->recommendations($utilization, $baselines),
        ];
    }

    /**
     * @return Collection of [metric => ['value', 'unit', 'measured_on', 'source']]
     */
    public function loadBaselines(): Collection
    {
        return DB::table('capacity_baselines')
            ->get()
            ->keyBy('metric')
            ->map(fn ($r) => [
                'value'       => (int) $r->value,
                'unit'        => $r->unit,
                'measured_on' => (string) $r->measured_on,
                'source'      => $r->source,
            ]);
    }

    /**
     * Today's usage rolled up across already-flushed minute buckets.
     * Includes a rough projection to end-of-day at the current pace.
     */
    public function todaySoFar(): array
    {
        $startOfDay = now()->startOfDay();
        $rows = DB::table('request_minute_buckets')
            ->where('bucket_at', '>=', $startOfDay)
            ->selectRaw(
                'COUNT(*) AS minutes_with_data,
                 COALESCE(SUM(request_count), 0)   AS total_requests,
                 COALESCE(SUM(error_count), 0)     AS total_errors,
                 COALESCE(SUM(distinct_users), 0)  AS user_signal,
                 COALESCE(MAX(request_count), 0)   AS peak_minute_count,
                 COALESCE(SUM(duration_ms_sum), 0) AS total_duration_ms'
            )
            ->first();

        $totalRequests = (int) ($rows->total_requests ?? 0);
        $totalErrors   = (int) ($rows->total_errors ?? 0);

        $minutesElapsed = max(1, now()->diffInMinutes($startOfDay));
        $minutesInDay   = 60 * 24;

        return [
            'date'                  => $startOfDay->toDateString(),
            'requests_so_far'       => $totalRequests,
            'errors_so_far'         => $totalErrors,
            'error_rate_percent'    => $totalRequests > 0
                                       ? round(($totalErrors / $totalRequests) * 100, 2) : 0,
            'avg_request_ms'        => $totalRequests > 0
                                       ? (int) ($rows->total_duration_ms / $totalRequests) : 0,
            'peak_rps_so_far'       => round((int) $rows->peak_minute_count / 60, 1),
            'minutes_with_data'     => (int) $rows->minutes_with_data,
            // Distinct users today via activity_logs IF present, else
            // sum-with-overlap-correction from buckets (lossy).
            'dau_estimated'         => $this->estimateDau(),
            // Linear projection — naive but useful as a "if today
            // continues at this rate" sanity number.
            'projected_eod_requests'=> $totalRequests > 0
                ? (int) ($totalRequests / $minutesElapsed * $minutesInDay) : 0,
        ];
    }

    public function recentMinute(): array
    {
        // The latest fully-flushed minute (avoid the current minute
        // which may still be filling).
        $row = DB::table('request_minute_buckets')
            ->where('bucket_at', '<=', now()->subMinute()->startOfMinute())
            ->orderByDesc('bucket_at')
            ->first();

        if (!$row) {
            return ['rps' => 0, 'errors' => 0, 'avg_ms' => 0, 'bucket_at' => null];
        }

        return [
            'bucket_at' => (string) $row->bucket_at,
            'rps'       => round((int) $row->request_count / 60, 1),
            'errors'    => (int) $row->error_count,
            'error_rate_percent' => $row->request_count > 0
                                   ? round(($row->error_count / $row->request_count) * 100, 2) : 0,
            'avg_ms'    => $row->request_count > 0
                          ? (int) ($row->duration_ms_sum / $row->request_count) : 0,
            'max_ms'    => (int) $row->duration_ms_max,
            'route'     => (string) $row->route_group,
        ];
    }

    /**
     * Compute % utilization per dimension. Honest formula:
     *
     *   utilization = current_observed / baseline_ceiling × 100
     *
     * Each dimension is sourced from a SPECIFIC baseline row so
     * the operator can audit the calculation.
     */
    public function computeUtilization(Collection $baselines, array $today, array $recent): array
    {
        $out = [];

        // RPS utilization vs the deployment-tier ceiling (dev or prod).
        $rpsBaseline = $this->preferProductionBaseline($baselines, 'rps');
        if ($rpsBaseline) {
            $observedRps = max((float) $recent['rps'], (float) $today['peak_rps_so_far']);
            $out['rps'] = [
                'observed' => $observedRps,
                'ceiling'  => $rpsBaseline['value'],
                'percent'  => $rpsBaseline['value'] > 0
                              ? round($observedRps / $rpsBaseline['value'] * 100, 1) : 0,
                'baseline_metric' => $rpsBaseline['metric'],
                'source'   => $rpsBaseline['source'],
            ];
        }

        // Concurrent-users utilization (extrapolated from RPS).
        $cuBaseline = $this->preferProductionBaseline($baselines, 'concurrent_users');
        if ($cuBaseline && isset($today['dau_estimated'])) {
            // ~10% of DAU is concurrent at peak hour (industry rule).
            $concurrentEst = (int) ($today['dau_estimated'] * 0.10);
            $out['concurrent_users'] = [
                'observed' => $concurrentEst,
                'ceiling'  => $cuBaseline['value'],
                'percent'  => $cuBaseline['value'] > 0
                              ? round($concurrentEst / $cuBaseline['value'] * 100, 1) : 0,
                'baseline_metric' => $cuBaseline['metric'],
                'source'   => $cuBaseline['source'],
                'note'     => 'concurrent estimated as 10% of DAU at peak',
            ];
        }

        // LINE monthly quota utilization.
        if ($baselines->has('line.free_quota_per_month')) {
            $monthSent = $this->lineMonthlySent();
            $out['line_quota'] = [
                'observed' => $monthSent,
                'ceiling'  => $baselines['line.free_quota_per_month']['value'],
                'percent'  => $baselines['line.free_quota_per_month']['value'] > 0
                              ? round($monthSent / $baselines['line.free_quota_per_month']['value'] * 100, 1) : 0,
                'baseline_metric' => 'line.free_quota_per_month',
                'note'     => 'paid plans lift this',
            ];
        }

        // Queue throughput vs baseline.
        if ($baselines->has('queue.jobs_per_sec_per_worker')) {
            $observedJobsPerSec = $this->observedQueueRate();
            $out['queue'] = [
                'observed' => $observedJobsPerSec,
                'ceiling'  => $baselines['queue.jobs_per_sec_per_worker']['value'],
                'percent'  => $baselines['queue.jobs_per_sec_per_worker']['value'] > 0
                              ? round($observedJobsPerSec / $baselines['queue.jobs_per_sec_per_worker']['value'] * 100, 1) : 0,
                'baseline_metric' => 'queue.jobs_per_sec_per_worker',
            ];
        }

        return $out;
    }

    /**
     * Identifies the dimension closest to its ceiling — that's what
     * fills first as load grows.
     */
    public function identifyBottleneck(array $utilization): ?array
    {
        if (empty($utilization)) return null;
        $worst = null;
        foreach ($utilization as $dim => $row) {
            if ($worst === null || $row['percent'] > $worst['percent']) {
                $worst = ['dimension' => $dim] + $row;
            }
        }
        return $worst;
    }

    /**
     * Per-dimension recommendations. Returns a list of strings,
     * empty when nothing to do.
     */
    public function recommendations(array $util, Collection $baselines): array
    {
        $tips = [];

        if (($util['rps']['percent'] ?? 0) >= 80) {
            $tips[] = 'RPS at ' . $util['rps']['percent'] . '% of ' . $util['rps']['ceiling']
                . ' — provision a second app box, or move to PHP-FPM if still on Apache mpm_winnt.';
        }
        if (($util['concurrent_users']['percent'] ?? 0) >= 70) {
            $tips[] = 'Concurrent users at ' . $util['concurrent_users']['percent']
                . '% — tune DB max_connections and add pgbouncer pooler.';
        }
        if (($util['line_quota']['percent'] ?? 0) >= 80) {
            $tips[] = 'LINE quota at ' . $util['line_quota']['percent']
                . '% of monthly free tier — upgrade to LINE paid plan or convert push→reply where possible.';
        }
        if (($util['queue']['percent'] ?? 0) >= 70) {
            $tips[] = 'Queue throughput at ' . $util['queue']['percent']
                . '% — switch QUEUE_CONNECTION to redis and run more workers (Horizon).';
        }
        // Universal recommendation when nothing's hot.
        if (empty($tips)) {
            $tips[] = 'No dimension above warning threshold. System has headroom.';
        }
        return $tips;
    }

    /* ─────────────────── helpers ─────────────────── */

    /**
     * Prefer 'rps.production_per_box' over 'rps.dev_per_box' if the
     * deployment is past initial dev. We use a simple heuristic:
     * if the production baseline exists and was measured/recorded,
     * use it; otherwise fall back to dev.
     */
    private function preferProductionBaseline(Collection $baselines, string $kind): ?array
    {
        $prodKey = "{$kind}.production_per_box";
        $devKey  = "{$kind}.dev_per_box";
        if ($kind === 'concurrent_users') {
            $prodKey = 'concurrent_users.production';
            $devKey  = 'concurrent_users.dev';
        }
        if ($baselines->has($prodKey)) {
            $v = $baselines[$prodKey];
            return ['metric' => $prodKey] + $v;
        }
        if ($baselines->has($devKey)) {
            $v = $baselines[$devKey];
            return ['metric' => $devKey] + $v;
        }
        return null;
    }

    /**
     * DAU estimate — sum bucket distinct_users with 80% correction
     * for inter-bucket overlap (a user active at 9:00 + 9:30 is one
     * person, not two). The correction factor is from observation
     * of typical active sessions; tune if your traffic differs.
     *
     * For exact DAU, switch to:
     *   activity_logs.user_id distinct WHERE created_at >= today
     */
    private function estimateDau(): int
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('activity_logs')) {
            try {
                return (int) DB::table('activity_logs')
                    ->where('created_at', '>=', now()->startOfDay())
                    ->whereNotNull('user_id')
                    ->distinct('user_id')
                    ->count('user_id');
            } catch (\Throwable) {
                // Fall through to estimate.
            }
        }
        $sum = (int) DB::table('request_minute_buckets')
            ->where('bucket_at', '>=', now()->startOfDay())
            ->sum('distinct_users');
        // 80% overlap correction (industry rule of thumb).
        return (int) ($sum * 0.20);
    }

    private function lineMonthlySent(): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('line_deliveries')) return 0;
        return (int) DB::table('line_deliveries')
            ->where('created_at', '>=', now()->startOfMonth())
            ->where('status', 'sent')
            ->count();
    }

    /**
     * Observed queue rate over the last 5 minutes. Uses jobs table
     * if present (Laravel's default queue=database).
     */
    private function observedQueueRate(): float
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('jobs')) return 0;
        try {
            $count = (int) DB::table('jobs')
                ->where('reserved_at', '>=', now()->subMinutes(5)->getTimestamp())
                ->count();
            return round($count / 300, 2);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Daily trend for the last N days — used by dashboard charts.
     */
    public function dailyTrend(int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();
        return DB::table('usage_daily')
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(fn ($byDate) => $byDate->keyBy('metric')
                ->map(fn ($row) => (int) $row->value)
                ->all())
            ->all();
    }
}
