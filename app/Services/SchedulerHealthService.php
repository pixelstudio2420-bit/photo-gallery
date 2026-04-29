<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Summarises the health of the scheduler + queue + failed jobs.
 *
 * Used by Admin → Scheduler Health dashboard + can be consumed by AlertEvaluatorService
 * (queue_pending, queue_failed_24h metrics).
 */
class SchedulerHealthService
{
    /**
     * List every scheduled task with its cron expression, next run, last run (from schedule_runs if present).
     *
     * @return array<int, array<string, mixed>>
     */
    public function tasks(): array
    {
        $schedule = app(Schedule::class);
        $out = [];

        foreach ($schedule->events() as $event) {
            /** @var Event $event */
            $cmd = $this->describeCommand($event);
            $next = $this->nextRun($event);
            $out[] = [
                'command'          => $cmd,
                'cron'             => $event->expression,
                'description'      => $event->description ?? null,
                'timezone'         => $event->timezone,
                'without_overlap'  => (bool) $event->withoutOverlapping,
                'background'       => (bool) $event->runInBackground,
                'next_due_at'      => $next?->toIso8601String(),
                'next_due_human'   => $next?->diffForHumans(),
                'on_one_server'    => (bool) $event->onOneServer,
            ];
        }

        return $out;
    }

    /**
     * Queue depth (pending jobs waiting to be processed) + a quick breakdown by queue name.
     */
    public function queueSnapshot(): array
    {
        $pending = 0;
        $byQueue = [];
        $oldest = null;

        if (Schema::hasTable('jobs')) {
            $pending = (int) DB::table('jobs')->count();
            $byQueue = DB::table('jobs')
                ->selectRaw('queue, COUNT(*) as c, MIN(available_at) as oldest_at')
                ->groupBy('queue')
                ->orderByDesc('c')
                ->get()
                ->map(fn ($r) => [
                    'queue'     => $r->queue,
                    'count'     => (int) $r->c,
                    'oldest_at' => $r->oldest_at ? Carbon::createFromTimestamp((int) $r->oldest_at)->toIso8601String() : null,
                ])
                ->all();

            $oldestTs = DB::table('jobs')->min('available_at');
            if ($oldestTs) $oldest = Carbon::createFromTimestamp((int) $oldestTs);
        }

        return [
            'pending'          => $pending,
            'by_queue'         => $byQueue,
            'oldest_pending'   => $oldest?->toIso8601String(),
            'oldest_age_human' => $oldest?->diffForHumans(),
            'oldest_age_min'   => $oldest ? (int) $oldest->diffInMinutes(now()) : null,
        ];
    }

    /**
     * Failed-job stats: total, last 24h, last 7d, plus the last N rows.
     */
    public function failedJobsSnapshot(int $recent = 25): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return [
                'total' => 0, 'last_24h' => 0, 'last_7d' => 0, 'recent' => [], 'trend_30d' => [],
            ];
        }

        $total   = (int) DB::table('failed_jobs')->count();
        $last24  = (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        $last7d  = (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDays(7))->count();

        $recentRows = DB::table('failed_jobs')
            ->orderByDesc('id')
            ->limit($recent)
            ->get(['id', 'uuid', 'connection', 'queue', 'exception', 'failed_at'])
            ->map(fn ($r) => [
                'id'          => $r->id,
                'uuid'        => $r->uuid,
                'queue'       => $r->queue,
                'connection'  => $r->connection,
                'failed_at'   => $r->failed_at,
                'exception'   => $this->shortException((string) $r->exception),
            ])
            ->all();

        // Daily trend for last 30 days
        $trend = [];
        try {
            $rows = DB::table('failed_jobs')
                ->selectRaw('DATE(failed_at) as d, COUNT(*) as c')
                ->where('failed_at', '>=', now()->subDays(30))
                ->groupBy('d')
                ->orderBy('d')
                ->get();
            foreach ($rows as $r) {
                $trend[] = ['date' => (string) $r->d, 'count' => (int) $r->c];
            }
        } catch (\Throwable) {
            // ignore if DATE() not supported
        }

        return [
            'total'     => $total,
            'last_24h'  => $last24,
            'last_7d'   => $last7d,
            'recent'    => $recentRows,
            'trend_30d' => $trend,
        ];
    }

    /**
     * Consolidated snapshot for the dashboard.
     */
    public function snapshot(): array
    {
        return [
            'scheduler'   => $this->tasks(),
            'queue'       => $this->queueSnapshot(),
            'failed'      => $this->failedJobsSnapshot(),
            'queue_conn'  => config('queue.default'),
            'generated'   => now()->toIso8601String(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════════════

    protected function describeCommand(Event $event): string
    {
        // Try to get a sensible string for display
        if (method_exists($event, 'command') && $event->command) {
            return trim(preg_replace('/\s+/', ' ', (string) $event->command));
        }
        return $event->description ?? $event->expression;
    }

    /**
     * Determine next scheduled run using the cron expression.
     */
    protected function nextRun(Event $event): ?Carbon
    {
        try {
            $cron = new \Cron\CronExpression($event->expression);
            $next = $cron->getNextRunDate(now()->toDateTime(), 0, false, $event->timezone ?? config('app.timezone'));
            return Carbon::instance($next);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function shortException(string $ex): string
    {
        // Just the first line — the class + message
        $firstLine = strtok($ex, "\n");
        return mb_substr($firstLine ?: $ex, 0, 240);
    }
}
