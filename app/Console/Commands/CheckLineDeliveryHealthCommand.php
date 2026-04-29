<?php

namespace App\Console\Commands;

use App\Services\LineNotifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Watches line_deliveries for sustained failure patterns and alerts
 * admins when LINE delivery health degrades.
 *
 * Runs every 15 min. Two thresholds are checked:
 *
 *   1. Backlog: stuck-pending deliveries older than 30 min.
 *      → Indicates the queue worker is alive but LINE is rejecting
 *        every push (e.g. expired channel access token, LINE outage).
 *
 *   2. Recent-failure rate: in the last 30 min, > 10 deliveries failed
 *      AND failure ratio > 25%.
 *      → Indicates a systemic problem rather than the usual handful of
 *        "user blocked the OA" outcomes.
 *
 * Alert dedup is 1 h per failure mode so a multi-hour outage produces
 * 1-2 alerts/hour, not a flood. Healthy runs clear the dedup so the
 * next alert fires immediately.
 *
 * Two-channel alert (email + LINE multicast) for the same reason as
 * CheckQueueHeartbeat: if the failing component IS LINE, the LINE
 * channel may itself be down — email is the fallback.
 */
class CheckLineDeliveryHealthCommand extends Command
{
    protected $signature   = 'line:check-delivery-health
                              {--quiet-if-clean : Suppress output when nothing is wrong}';
    protected $description = 'Alert if LINE delivery has a sustained failure pattern';

    private const ALERT_DEDUP_TTL = 3600; // 1 h

    /** Pending older than this counts as stuck. */
    private const STUCK_THRESHOLD_MIN = 30;

    /** Window for failure-rate calculation. */
    private const RATE_WINDOW_MIN = 30;

    /** Min count of failures to consider it a real pattern (vs isolated). */
    private const RATE_MIN_FAILURES = 10;

    /** Failure ratio above which we alert (0.25 = 25%). */
    private const RATE_FAIL_THRESHOLD = 0.25;

    public function handle(): int
    {
        $issues = [];

        // ── Check 1: stuck-pending backlog ─────────────────────────
        $stuckCount = (int) DB::table('line_deliveries')
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(self::STUCK_THRESHOLD_MIN))
            ->count();

        if ($stuckCount > 0) {
            $issues[] = [
                'kind'   => 'stuck',
                'msg'    => "There are {$stuckCount} LINE deliveries stuck in 'pending' for more than "
                           . self::STUCK_THRESHOLD_MIN . " min. Queue worker may be alive but LINE is "
                           . "consistently rejecting (check channel access token).",
                'detail' => $stuckCount,
            ];
        }

        // ── Check 2: failure-rate burst ────────────────────────────
        $since = now()->subMinutes(self::RATE_WINDOW_MIN);
        $row = DB::table('line_deliveries')
            ->where('created_at', '>=', $since)
            ->selectRaw("
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'sent'   THEN 1 ELSE 0 END) as sent_count,
                COUNT(*) as total
            ")
            ->first();

        $failedCount = (int) ($row->failed_count ?? 0);
        $total       = (int) ($row->total ?? 0);
        $failureRate = $total > 0 ? ($failedCount / $total) : 0.0;

        if ($failedCount >= self::RATE_MIN_FAILURES && $failureRate >= self::RATE_FAIL_THRESHOLD) {
            $issues[] = [
                'kind'   => 'rate',
                'msg'    => sprintf(
                    "%d/%d (%.1f%%) of LINE deliveries failed in the last %d min — failure rate exceeds %d%% threshold.",
                    $failedCount, $total, $failureRate * 100,
                    self::RATE_WINDOW_MIN, (int) (self::RATE_FAIL_THRESHOLD * 100),
                ),
                'detail' => ['failed' => $failedCount, 'total' => $total],
            ];
        }

        if (empty($issues)) {
            // Clear dedups so the next alert fires immediately.
            Cache::forget('line.delivery.alert.stuck');
            Cache::forget('line.delivery.alert.rate');
            if (!$this->option('quiet-if-clean')) {
                $this->info('LINE delivery health OK');
            }
            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->raiseAlert($issue['kind'], $issue['msg']);
        }
        return self::SUCCESS;
    }

    private function raiseAlert(string $kind, string $reason): void
    {
        $dedupKey = "line.delivery.alert.{$kind}";
        if (Cache::get($dedupKey)) {
            $this->warn("alert deduplicated ({$kind}): {$reason}");
            return;
        }
        Cache::put($dedupKey, now()->toIso8601String(), self::ALERT_DEDUP_TTL);

        Log::error("CheckLineDeliveryHealth: {$kind}", ['reason' => $reason]);

        $subject = "[LINE] delivery degraded: {$kind}";
        $body    = "LINE delivery health check failed.\n\n"
                 . "Kind: {$kind}\n"
                 . "Detail: {$reason}\n"
                 . "Detected: " . now()->toDateTimeString() . "\n\n"
                 . "Action: query `SELECT * FROM line_deliveries WHERE created_at > NOW() - INTERVAL '1 hour' "
                 . "ORDER BY id DESC LIMIT 50` to see the failure pattern. Common causes:\n"
                 . "  • Channel access token expired (admin → integrations → LINE)\n"
                 . "  • LINE Messaging API incident (status.line.me)\n"
                 . "  • Quota exhausted on the OA's plan";

        // Email
        try {
            $admins = DB::table('auth_admins')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->pluck('email')->all();
            foreach ($admins as $email) {
                try {
                    Mail::raw($body, function ($m) use ($email, $subject) {
                        $m->to($email)->subject($subject);
                    });
                } catch (\Throwable $e) {
                    Log::info('CheckLineDeliveryHealth: per-admin email failed', [
                        'admin' => $email, 'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('CheckLineDeliveryHealth: email batch failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // LINE multicast — direct, bypasses queue.
        try {
            app(LineNotifyService::class)->notifyAdmin(
                "🚨 LINE delivery degraded ({$kind})\n{$reason}",
            );
        } catch (\Throwable $e) {
            Log::info('CheckLineDeliveryHealth: LINE alert failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $this->error($reason);
    }
}
