<?php

namespace App\Jobs\Operations;

use App\Services\Analytics\CapacityCalculator;
use App\Services\LineNotifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fires hourly. Reads the capacity snapshot; alerts if any dimension
 * is past its threshold.
 *
 * Thresholds
 * ----------
 *   warn   — utilization >= 80%   (alert daily)
 *   crit   — utilization >= 95%   (alert hourly)
 *
 * The dedup is per-dimension so e.g. RPS alert doesn't suppress the
 * unrelated "LINE quota near limit" alert.
 *
 * Channels
 * --------
 *   • Email to all active admins
 *   • LINE multicast to admin user ids (best-effort)
 *
 * Both channels go around the queue (Mail::raw, Http::post) so an
 * already-dead queue doesn't swallow its own alert.
 */
class CapacityAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 30;

    private const WARN_THRESHOLD = 80.0;
    private const CRIT_THRESHOLD = 95.0;
    private const DEDUP_WARN_TTL = 86400;  // 1 day
    private const DEDUP_CRIT_TTL = 3600;   // 1 hour

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(CapacityCalculator $calc): void
    {
        $snap = $calc->snapshot();

        foreach ($snap['utilization'] as $dim => $row) {
            $pct = (float) ($row['percent'] ?? 0);
            $level = $pct >= self::CRIT_THRESHOLD
                ? 'crit'
                : ($pct >= self::WARN_THRESHOLD ? 'warn' : null);
            if (!$level) continue;

            $dedupKey = "capacity_alert.{$dim}.{$level}";
            if (Cache::has($dedupKey)) continue;

            $ttl = $level === 'crit' ? self::DEDUP_CRIT_TTL : self::DEDUP_WARN_TTL;
            Cache::put($dedupKey, now()->toIso8601String(), $ttl);

            $this->raise($dim, $row, $level, $snap);
        }
    }

    private function raise(string $dim, array $row, string $level, array $snap): void
    {
        $emoji = $level === 'crit' ? '🔥' : '⚠️';
        $headline = sprintf(
            '%s Capacity %s: %s at %.1f%% of ceiling',
            $emoji, strtoupper($level), $dim, $row['percent'],
        );

        Log::warning('CapacityAlertJob: ' . $headline, $row);

        $body = "Capacity threshold crossed.\n\n"
              . "Dimension: {$dim}\n"
              . "Observed: {$row['observed']} / {$row['ceiling']}\n"
              . "Utilization: {$row['percent']}%\n"
              . "Baseline: {$row['baseline_metric']}\n"
              . (isset($row['source']) ? "Source: {$row['source']}\n" : '')
              . "\n"
              . "Recommendations:\n"
              . implode("\n", array_map(fn ($t) => '  - ' . $t, $snap['recommendations']))
              . "\n\n"
              . "Detected at: " . now()->toDateTimeString() . "\n";

        $this->emailAdmins($headline, $body);
        $this->lineAdmins($headline);
    }

    private function emailAdmins(string $subject, string $body): void
    {
        try {
            $emails = DB::table('auth_admins')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->pluck('email')->all();
            foreach ($emails as $email) {
                try {
                    Mail::raw($body, function ($m) use ($email, $subject) {
                        $m->to($email)->subject($subject);
                    });
                } catch (\Throwable $e) {
                    Log::info('CapacityAlertJob: per-admin email failed', [
                        'admin' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('CapacityAlertJob: email batch failed', ['error' => $e->getMessage()]);
        }
    }

    private function lineAdmins(string $headline): void
    {
        try {
            app(LineNotifyService::class)->notifyAdmin($headline);
        } catch (\Throwable $e) {
            Log::info('CapacityAlertJob: LINE alert failed', ['error' => $e->getMessage()]);
        }
    }
}
