<?php

namespace App\Console\Commands;

use App\Jobs\Operations\QueueHeartbeatJob;
use App\Services\LineNotifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Reads the queue heartbeat cache key and alerts on staleness.
 *
 * Cadence
 * -------
 * The Schedule fires QueueHeartbeatJob every 5 min; this command runs
 * every 10 min. If the heartbeat key is older than the threshold
 * (default 15 min = 3 missed beats), we treat the worker as dead.
 *
 * Alert channels
 * --------------
 * Two independent paths so a totally dead queue worker (which is the
 * scenario being detected!) doesn't silently swallow its own alert:
 *
 *   1. Email to all active admins via Mail::raw — direct, doesn't
 *      go through the queue.
 *
 *   2. Inline LINE multicast via LineNotifyService::notifyAdmin —
 *      also doesn't go through the queue (sendMulticast calls
 *      Http::post directly).
 *
 * Both alert channels share a 30-min cache dedup so a multi-hour
 * outage produces 2 alerts/hour, not a flood.
 */
class CheckQueueHeartbeatCommand extends Command
{
    protected $signature   = 'queue:check-heartbeat';
    protected $description = 'Alert if the queue worker hasn\'t processed a heartbeat job in a while';

    private const ALERT_DEDUP_KEY = 'queue.heartbeat.alert_sent_at';
    private const ALERT_DEDUP_TTL = 1800;   // 30 min

    public function handle(): int
    {
        $thresholdMinutes = (int) config('queue.heartbeat_stale_minutes', 15);
        $lastSeenIso = (string) Cache::get(QueueHeartbeatJob::CACHE_KEY, '');

        if ($lastSeenIso === '') {
            // Never seen — either the cron just started, or the worker
            // has been dead for a while (the cache TTL of 1h means a
            // dead worker eventually loses the key entirely).
            $this->raiseAlert('queue worker has never reported a heartbeat');
            return self::SUCCESS;
        }

        try {
            $lastSeen = \Carbon\Carbon::parse($lastSeenIso);
        } catch (\Throwable $e) {
            $this->raiseAlert("heartbeat cache value is malformed: {$lastSeenIso}");
            return self::SUCCESS;
        }

        $stale = $lastSeen->diffInMinutes(now());
        if ($stale >= $thresholdMinutes) {
            $this->raiseAlert(sprintf(
                'queue worker has not processed a job in %d minutes (threshold: %d min)',
                (int) $stale,
                $thresholdMinutes,
            ));
            return self::SUCCESS;
        }

        $this->info(sprintf('Queue worker heartbeat OK (last seen %dm ago)', (int) $stale));
        // Healthy run also clears the alert dedup, so the NEXT failure
        // alerts immediately rather than waiting 30 min.
        Cache::forget(self::ALERT_DEDUP_KEY);
        return self::SUCCESS;
    }

    private function raiseAlert(string $reason): void
    {
        $alreadyAlerted = Cache::get(self::ALERT_DEDUP_KEY);
        if ($alreadyAlerted) {
            $this->warn('alert deduplicated (already raised): ' . $reason);
            return;
        }
        Cache::put(self::ALERT_DEDUP_KEY, now()->toIso8601String(), self::ALERT_DEDUP_TTL);

        Log::error('CheckQueueHeartbeat: queue worker appears dead', [
            'reason' => $reason,
        ]);

        // Channel 1 — email all admins (out-of-band, doesn't use the queue).
        try {
            $admins = \DB::table('auth_admins')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->pluck('email')->all();
            $subject = '[QUEUE] Worker appears dead';
            $body    = "The Laravel queue worker has stopped processing jobs.\n\n"
                     . "Reason: {$reason}\n\n"
                     . "Detected at: " . now()->toDateTimeString() . "\n\n"
                     . "Action: SSH into the server and run `php artisan queue:work`. "
                     . "Check supervisord/systemd config for whether the process should auto-restart.";
            foreach ($admins as $email) {
                try {
                    Mail::raw($body, function ($m) use ($email, $subject) {
                        $m->to($email)->subject($subject);
                    });
                } catch (\Throwable $e) {
                    Log::info('CheckQueueHeartbeat: per-admin email failed', [
                        'admin' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('CheckQueueHeartbeat: email batch failed', ['error' => $e->getMessage()]);
        }

        // Channel 2 — LINE multicast. notifyAdmin() calls sendMulticast()
        // which fires the LINE API directly (does NOT enqueue), so it
        // works even when the worker is the dead component.
        try {
            app(LineNotifyService::class)->notifyAdmin(
                "🚨 Queue worker appears dead\n"
                . "Reason: {$reason}\n"
                . "Time: " . now()->toDateTimeString() . "\n"
                . "Action: restart `php artisan queue:work`",
            );
        } catch (\Throwable $e) {
            Log::info('CheckQueueHeartbeat: LINE alert failed', ['error' => $e->getMessage()]);
        }

        $this->error($reason);
    }
}
