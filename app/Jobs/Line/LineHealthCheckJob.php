<?php

namespace App\Jobs\Line;

use App\Models\AppSetting;
use App\Services\LineNotifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies the LINE Messaging API token is still valid.
 *
 * Why this exists
 * ---------------
 * Channel access tokens (long-lived variant) don't expire on their own,
 * but they DO get invalidated whenever an admin re-issues them in the
 * LINE Developers Console. When that happens, every push silently 401s
 * for hours/days until someone notices "I haven't gotten any LINE
 * notifications recently."
 *
 * This job calls /v2/bot/info — the cheapest read in the API — once an
 * hour. If the response is non-2xx, we:
 *
 *   1. Send a multicast warning to admin LINE user IDs (via the existing
 *      notifyAdmin path, which uses the SAME token — so this only works
 *      if the token is partially broken; if the token is fully dead,
 *      step 2 is the failsafe).
 *
 *   2. Email all admins on the system via the existing MailService.
 *      Email is the out-of-band channel — works even when LINE is down.
 *
 * The job de-duplicates alerts via a 6-hour cache key so a persistent
 * outage doesn't spam admins. Once the token recovers, the cache key
 * expires naturally and the next failure (if any) re-alerts.
 */
class LineHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;       // health checks shouldn't pile up retries
    public int $timeout = 30;

    private const ALERT_DEDUP_CACHE_KEY = 'line_health.alert_sent_at';
    private const ALERT_DEDUP_TTL       = 6 * 3600;  // 6 hours

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(LineNotifyService $line): void
    {
        // Skip if Messaging is globally disabled — health-check on a
        // disabled integration is a config mistake, not a real outage.
        if (!$line->isMessagingEnabled()) {
            return;
        }

        $token = (string) AppSetting::get('line_channel_access_token', '');
        if ($token === '') {
            $this->raiseAlert('LINE channel access token is not configured', 'config');
            return;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get('https://api.line.me/v2/bot/info');

            if ($response->successful()) {
                // Healthy. If we previously raised an alert, drop the
                // dedup key so the next failure (if any) re-alerts
                // immediately instead of waiting 6 h.
                Cache::forget(self::ALERT_DEDUP_CACHE_KEY);
                Log::debug('LineHealthCheckJob: ok', [
                    'bot_id' => $response->json('userId') ?? null,
                ]);
                return;
            }

            $status = $response->status();
            $body   = $response->body();

            // Map status to a humans-readable cause for the alert.
            $cause = match ($status) {
                401     => 'invalid token (re-issued in LINE Console?)',
                403     => 'forbidden (channel disabled?)',
                429     => 'rate limited',
                default => "HTTP {$status}",
            };

            $this->raiseAlert(
                "LINE health check failed: {$cause}\nResponse: " . substr($body, 0, 200),
                "http_{$status}",
            );
        } catch (\Throwable $e) {
            $this->raiseAlert(
                'LINE health check exception: ' . substr($e->getMessage(), 0, 300),
                'exception',
            );
        }
    }

    /**
     * Notify admins via two independent channels (LINE multicast + email)
     * so a totally dead LINE token still surfaces. Deduped via cache so
     * a 4-hour outage doesn't produce 4 alert spams.
     */
    private function raiseAlert(string $message, string $reason): void
    {
        // Dedup window — once we've alerted, sit tight for 6h.
        $lastAlertAt = Cache::get(self::ALERT_DEDUP_CACHE_KEY);
        if ($lastAlertAt) {
            return;
        }
        Cache::put(self::ALERT_DEDUP_CACHE_KEY, now()->toIso8601String(), self::ALERT_DEDUP_TTL);

        Log::error('LineHealthCheckJob: token unhealthy', [
            'reason'  => $reason,
            'message' => $message,
        ]);

        // Channel 1 — LINE multicast (works only if the token is
        // partially broken or the issue is at /info). Best-effort.
        try {
            app(LineNotifyService::class)->notifyAdmin(
                "🚨 LINE health check failed\n"
                . "Reason: {$reason}\n"
                . "Time: " . now()->toDateTimeString() . "\n"
                . substr($message, 0, 300),
            );
        } catch (\Throwable $e) {
            Log::info('LineHealthCheckJob: LINE notifyAdmin failed (expected if token is dead)', [
                'error' => $e->getMessage(),
            ]);
        }

        // Channel 2 — email all admins so we have an out-of-band
        // notification when LINE itself is dead.
        try {
            $this->emailAdmins($reason, $message);
        } catch (\Throwable $e) {
            Log::warning('LineHealthCheckJob: email alert failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Best-effort email — pulls active admin emails directly from the DB.
     * Uses MailService if registered; otherwise drops to Mail facade
     * with a plain message.
     */
    private function emailAdmins(string $reason, string $message): void
    {
        $admins = \DB::table('auth_admins')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->all();
        if (empty($admins)) return;

        $subject = '[LINE] Health check failed — ' . $reason;
        $body    = "LINE Messaging API health check failed at " . now()->toDateTimeString() . ".\n\n"
                 . "Reason: {$reason}\n\n"
                 . "Details:\n{$message}\n\n"
                 . "Action: verify the channel access token in LINE Developers Console "
                 . "and re-paste it under /admin/settings/line.";

        foreach ($admins as $email) {
            try {
                \Illuminate\Support\Facades\Mail::raw($body, function ($m) use ($email, $subject) {
                    $m->to($email)->subject($subject);
                });
            } catch (\Throwable $e) {
                // Email may not be configured in dev — don't crash the job.
                Log::info('LineHealthCheckJob: per-admin email failed', [
                    'admin' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
