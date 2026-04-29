<?php

namespace App\Console\Commands;

use App\Services\Upload\MultipartUploadService;
use App\Services\Upload\UploadSessionService;
use Illuminate\Console\Command;

/**
 * Cleans up uploads that never completed.
 *
 * Two kinds of leftover state we need to sweep:
 *
 *  1. upload_chunks rows past expires_at that are still 'initiated' or
 *     'uploading'. Each one is keeping bytes in R2 we're paying for
 *     (R2 charges for in-progress multipart parts) and a row in our DB.
 *
 *  2. upload_sessions rows past expires_at that are still 'open' or
 *     'finalising'. These don't cost storage but clutter the dashboard
 *     query that lists "in-progress batches".
 *
 * Run from cron / Laravel scheduler — the kernel registration is in
 * App\Console\Kernel::schedule(). A single run is bounded by 500 rows
 * per call so it can't lock up the box if the queue piles up.
 */
class SweepStaleUploadsCommand extends Command
{
    protected $signature   = 'uploads:sweep-stale {--dry-run}';
    protected $description = 'Abort stale multipart uploads and expire stale batch sessions';

    public function handle(
        MultipartUploadService $multipart,
        UploadSessionService $sessions,
    ): int {
        $dry = (bool) $this->option('dry-run');

        if ($dry) {
            $this->warn('DRY-RUN: no aborts will be issued.');
        }

        // Sweep stale multipart uploads — also asks R2 to abort.
        $multipartResult = $dry
            ? ['aborted' => 0, 'scanned' => 0]
            : $multipart->sweepExpired();
        $this->line(sprintf(
            'multipart: scanned=%d aborted=%d',
            $multipartResult['scanned'],
            $multipartResult['aborted'],
        ));

        // Sweep stale batch sessions.
        $sessionsExpired = $dry ? 0 : $sessions->sweepExpired();
        $this->line("sessions: expired={$sessionsExpired}");

        return self::SUCCESS;
    }
}
