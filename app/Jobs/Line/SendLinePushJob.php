<?php

namespace App\Jobs\Line;

use App\Models\AppSetting;
use App\Services\Line\LineDeliveryLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Queued + retried + audited push to a single LINE user.
 *
 * Why this is a job
 * -----------------
 * The synchronous LineNotifyService::sendPush() is fine for one-off
 * admin alerts but pins a PHP-FPM worker for ~500ms-10s per call.
 * Photo-delivery flows fire 20+ pushes per order — without queueing,
 * a checkout-completed webhook can blow its 30s budget while still
 * waiting for LINE.
 *
 * The job runs on the 'notifications' queue (separate lane from heavy
 * download work) so a burst of customer notifications doesn't block
 * mirror copies or photo processing.
 *
 * Retry semantics
 * ---------------
 * 5 attempts; backoff is exponential with jitter to avoid thundering
 * herd against LINE during their incidents:
 *
 *    attempt 1 → instant
 *    attempt 2 → ~30s
 *    attempt 3 → ~120s
 *    attempt 4 → ~600s
 *    attempt 5 → ~1800s
 *
 * Some failures are NOT retried (treated as terminal):
 *   • 401 invalid token  — admin needs to fix; retrying just spams
 *     LINE with auth failures.
 *   • 403 forbidden      — same reason.
 *   • 400 user not added — handled inline (detach the dead user_id),
 *     no point retrying.
 *
 * Audit
 * -----
 * Every attempt updates the line_deliveries row passed in via
 * $deliveryId. Final outcome is one of: sent | failed | skipped.
 */
class SendLinePushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 30;

    public function __construct(
        public readonly string $lineUserId,
        public readonly array $messages,
        public readonly int $deliveryId,
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        // Exponential. Jitter is added by the queue worker.
        return [30, 120, 600, 1800];
    }

    public function handle(LineDeliveryLogger $logger): void
    {
        // markSent / markFailed / markSkipped already bump attempts —
        // we don't pre-increment here to avoid double-counting on the
        // happy path. The release(retry) branch DOES bump explicitly
        // via incrementAttempt below since no terminal mark is called.
        $token = (string) AppSetting::get('line_channel_access_token', '');
        if ($token === '') {
            $logger->markFailed($this->deliveryId, null, 'channel access token not configured');
            // Don't re-queue — admin has to fix config; retrying just
            // accumulates failed attempts. release(0) would treat it as
            // a transient error; we deliberately fail() instead.
            $this->fail(new \RuntimeException('LINE channel access token missing'));
            return;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to'       => $this->lineUserId,
                    'messages' => $this->messages,
                ]);

            $status = $response->status();

            if ($response->successful()) {
                $logger->markSent($this->deliveryId, $status);
                return;
            }

            // ── Terminal failures: don't retry ──
            if ($status === 401 || $status === 403) {
                $logger->markFailed($this->deliveryId, $status,
                    'auth/permission rejection: ' . substr($response->body(), 0, 200));
                $this->fail(new \RuntimeException("LINE auth failure: {$status}"));
                return;
            }
            if ($status === 400) {
                $body = $response->body();
                if (str_contains($body, 'Failed to send messages')
                    || str_contains($body, "haven't added")) {
                    // The recipient has not added the OA, or has blocked
                    // it. Detach so we stop pushing.
                    $this->detachDeadUser();
                    $logger->markSkipped($this->deliveryId, 'recipient has not added OA');
                    return;
                }
            }

            // ── Transient: rate limit or server error → let queue retry ──
            $logger->markFailed($this->deliveryId, $status,
                'transient: ' . substr($response->body(), 0, 200));

            // 429 from LINE: respect Retry-After if provided.
            if ($status === 429) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 60);
                $this->release(max(30, min($retryAfter, 1800)));
                return;
            }

            throw new \RuntimeException("LINE push failed HTTP {$status}");
        } catch (\Throwable $e) {
            $logger->markFailed($this->deliveryId, null, $e->getMessage());
            // Re-throw → queue worker counts as failed attempt and
            // either retries (per backoff) or moves to failed_jobs.
            throw $e;
        }
    }

    /**
     * Final-state hook — the queue worker calls this when all retries
     * are exhausted. We've already updated line_deliveries inside
     * handle(); this is a place to escalate to a higher-priority alert
     * (Slack / email) if needed.
     *
     * Order-delivery failures are escalated specifically: if the
     * idempotency_key looks like `order.{id}.line.{slot}`, we trigger
     * an admin alert via two channels (email + LINE OA multicast). The
     * order ID is the most actionable identifier we have, since the
     * user might have a chargeback if their photos never arrive.
     *
     * Dedup: a 30-min cache window per order_id means a single order
     * doesn't generate dozens of repeat alerts (e.g. when both the
     * photo-push AND the download-link push fail in the same hour).
     */
    public function failed(\Throwable $e): void
    {
        Log::error('SendLinePushJob: exhausted retries', [
            'delivery_id'  => $this->deliveryId,
            'line_user_id' => substr($this->lineUserId, 0, 8) . '…',
            'error'        => $e->getMessage(),
        ]);

        // Defence in depth: ensure the audit row is in a terminal state
        // even if handle() failed before doing so.
        $delivery = DB::table('line_deliveries')->where('id', $this->deliveryId)->first();
        if ($delivery && $delivery->status === 'pending') {
            DB::table('line_deliveries')
                ->where('id', $this->deliveryId)
                ->update([
                    'status' => 'failed',
                    'error'  => mb_substr($e->getMessage(), 0, 500),
                ]);
        }

        // ── Admin alert escalation for order-related deliveries ──────
        $idempotencyKey = $delivery->idempotency_key ?? null;
        if ($idempotencyKey && preg_match('/^order\.(\d+)\.line\./', $idempotencyKey, $m)) {
            $this->escalateOrderFailure((int) $m[1], $e->getMessage());
        }
    }

    /**
     * Escalate a final LINE-delivery failure for a paid order.
     *
     * The customer paid; we promised them photos via LINE; LINE refused
     * (after 5 attempts spread over ~50 min). This is a customer-impact
     * incident that admin needs to know about within the hour. We alert
     * via two channels with a 30-min dedup so a flap doesn't spam.
     *
     * Channels:
     *   • Email to all active admins (out-of-band — bypasses the queue)
     *   • LINE multicast via LineNotifyService::notifyAdmin (also bypasses
     *     the queue — calls the LINE API directly)
     *
     * Either of these channels is allowed to fail; one channel is enough
     * to surface the incident.
     */
    private function escalateOrderFailure(int $orderId, string $error): void
    {
        $dedupKey = "line.order_delivery.alert.{$orderId}";
        if (\Illuminate\Support\Facades\Cache::get($dedupKey)) {
            return; // already alerted within the dedup window
        }
        \Illuminate\Support\Facades\Cache::put($dedupKey, now()->toIso8601String(), 1800);

        $errorShort = mb_substr($error, 0, 300);
        $subject    = "[LINE] order #{$orderId} delivery exhausted retries";
        $body       = "An order's LINE photo delivery failed after 5 retries.\n\n"
                    . "Order ID: {$orderId}\n"
                    . "Detected: " . now()->toDateTimeString() . "\n"
                    . "Last error: {$errorShort}\n\n"
                    . "Action: check line_deliveries for this order's "
                    . "idempotency_key prefix `order.{$orderId}.line.*`. "
                    . "Common causes: customer blocked the OA, channel "
                    . "access token expired, customer's LINE account "
                    . "deactivated. The download link is still available "
                    . "via the order page; consider sending the customer "
                    . "an email with the link as fallback.";

        // Channel 1 — email
        try {
            $admins = DB::table('auth_admins')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->pluck('email')->all();
            foreach ($admins as $email) {
                try {
                    \Illuminate\Support\Facades\Mail::raw($body, function ($m) use ($email, $subject) {
                        $m->to($email)->subject($subject);
                    });
                } catch (\Throwable $emailErr) {
                    Log::info('SendLinePushJob.escalate: per-admin email failed', [
                        'admin' => $email,
                        'error' => $emailErr->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $allErr) {
            Log::warning('SendLinePushJob.escalate: admin email batch failed', [
                'error' => $allErr->getMessage(),
            ]);
        }

        // Channel 2 — LINE multicast (uses LINE OA itself, bypasses queue)
        try {
            app(\App\Services\LineNotifyService::class)->notifyAdmin(
                "🚨 LINE delivery failed for order #{$orderId}\n"
                . "All retries exhausted.\n"
                . "Last error: {$errorShort}",
            );
        } catch (\Throwable $lineErr) {
            Log::info('SendLinePushJob.escalate: LINE alert failed', [
                'error' => $lineErr->getMessage(),
            ]);
        }
    }

    /**
     * The recipient has not added the OA / has blocked it. Detach the
     * line_user_id so we don't keep firing this job on every event.
     * Mirrors LineNotifyService::detachDeadLineUser().
     */
    private function detachDeadUser(): void
    {
        try {
            DB::table('users')
                ->where('line_user_id', $this->lineUserId)
                ->update(['line_user_id' => null, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::info('SendLinePushJob.detach: failed to detach', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
