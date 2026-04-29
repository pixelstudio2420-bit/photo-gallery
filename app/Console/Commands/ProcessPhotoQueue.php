<?php

namespace App\Console\Commands;

use App\Services\QueueService;
use Illuminate\Console\Command;

/**
 * Process jobs from the sync_queue table (custom database queue).
 *
 * This is an alternative to Laravel's built-in queue:work for the
 * custom sync_queue table. Useful for shared hosting or cron-based processing.
 *
 * Usage:
 *   php artisan photos:process-queue           # Process one job
 *   php artisan photos:process-queue --all     # Process all pending jobs
 *   php artisan photos:process-queue --daemon  # Run continuously (like queue:work)
 */
class ProcessPhotoQueue extends Command
{
    protected $signature = 'photos:process-queue
                            {--all : Process all pending jobs}
                            {--daemon : Run continuously}
                            {--sleep=5 : Seconds to sleep between daemon loops}
                            {--max=100 : Maximum jobs to process (with --all)}';

    protected $description = 'Process photo jobs from the sync_queue table';

    public function handle(): int
    {
        $queue = app(QueueService::class);

        if ($this->option('daemon')) {
            return $this->runDaemon($queue);
        }

        if ($this->option('all')) {
            return $this->processAll($queue);
        }

        // Single job
        $processed = $queue->processNext();
        $this->info($processed ? 'Processed 1 job.' : 'No pending jobs.');

        return 0;
    }

    private function processAll(QueueService $queue): int
    {
        $max = (int) $this->option('max');
        $count = 0;

        while ($count < $max && $queue->processNext()) {
            $count++;
            $this->info("Processed job #{$count}");
        }

        $this->info("Done. Processed {$count} job(s).");
        return 0;
    }

    private function runDaemon(QueueService $queue): int
    {
        $sleep = (int) $this->option('sleep');
        $this->info("Running in daemon mode (sleep: {$sleep}s). Press Ctrl+C to stop.");

        while (true) {
            $processed = $queue->processNext();

            if ($processed) {
                $this->info('[' . now()->format('H:i:s') . '] Processed a job');
            } else {
                sleep($sleep);
            }
        }

        return 0; // unreachable but required
    }
}
