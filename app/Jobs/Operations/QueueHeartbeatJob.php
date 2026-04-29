<?php

namespace App\Jobs\Operations;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Heartbeat marker for queue-worker liveness checks.
 *
 * The cron schedules this job every 5 minutes. Whichever worker
 * dequeues it writes a timestamp to a well-known cache key. A
 * separate command (`queue:check-heartbeat`) reads that key and
 * alerts admins if the timestamp is stale by more than the
 * configured threshold (default 15 min = 3 missed beats).
 *
 * Why this matters
 * ----------------
 * Several pipelines now rely on queue workers being alive:
 *   • SendLinePushJob (customer + admin notifications)
 *   • DeliverOrderViaLineJob (post-payment LINE delivery)
 *   • SyncBookingToCalendarJob (booking → Google Calendar)
 *   • ExportBookingToSheetJob (booking → Sheets)
 *   • ReverseSyncCalendarFromGoogleJob (Google → DB)
 *   • DownloadLineMediaJob (inbound images from LINE)
 *
 * If `php artisan queue:work` dies (server reboot, OOM, deploy
 * mistake) ALL of those silently halt. Without a heartbeat,
 * nobody notices for hours.
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CACHE_KEY = 'queue.heartbeat.last_seen_at';

    public int $tries   = 1;
    public int $timeout = 30;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        // Touch the cache. The check command reads this key and
        // compares against the schedule cadence to decide if the
        // worker is alive.
        Cache::put(self::CACHE_KEY, now()->toIso8601String(), 3600);
    }
}
