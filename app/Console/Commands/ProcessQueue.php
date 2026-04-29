<?php

namespace App\Console\Commands;

use App\Services\QueueService;
use Illuminate\Console\Command;

class ProcessQueue extends Command
{
    protected $signature = 'queue:process-custom {--limit=10 : Maximum number of jobs to process}';

    protected $description = 'Process custom sync queue jobs from the sync_queue table';

    public function handle(QueueService $queueService): int
    {
        $limit     = (int) $this->option('limit');
        $processed = 0;

        $this->info("Processing up to {$limit} pending job(s)...");

        for ($i = 0; $i < $limit; $i++) {
            $result = $queueService->processNext();

            if (!$result) {
                break;
            }

            $processed++;
            $this->line("  <fg=green>✓</> Job {$processed} processed.");
        }

        if ($processed === 0) {
            $this->line('  <fg=yellow>No pending jobs found.</> Queue is empty or all jobs have reached max attempts.');
        } else {
            $this->info("Done. Processed {$processed} job(s).");
        }

        // Show current status
        $status = $queueService->getStatus();
        $this->table(
            ['Pending', 'Processing', 'Completed', 'Failed'],
            [[$status['pending'], $status['processing'], $status['completed'], $status['failed']]]
        );

        return self::SUCCESS;
    }
}
