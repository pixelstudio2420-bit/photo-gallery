<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Subscribes to Google Calendar push notifications so changes a
 * photographer makes IN Google show up in our database.
 *
 * Why this exists
 * ---------------
 * GoogleCalendarSyncService already handles app → Google. The reverse
 * direction (Google → app) needs Google's "watch" mechanism: we POST
 * to events.watch with our webhook URL and a unique channel id; Google
 * then POSTs to that URL whenever something changes on the calendar.
 *
 * Without this, a photographer who reschedules an event in Google
 * Calendar (the most natural thing to do — Google Calendar is THEIR
 * source of truth, our app is the booking funnel) leaves our DB stale
 * indefinitely, and the customer's reminders fire at the wrong time.
 *
 * Channel lifecycle
 * -----------------
 *   • subscribe(photographerId)   — POST events.watch, store channel
 *                                   row with expiration_at (~7 days).
 *   • renew()                     — for each channel close to expiry,
 *                                   stop the old one + subscribe afresh.
 *                                   Cron-driven daily.
 *   • unsubscribe(photographerId) — POST channels.stop. Called on
 *                                   "disconnect Google Calendar" admin
 *                                   action.
 *
 * Webhook handler (PaymentWebhookController::googleCalendarWebhook —
 * registered separately) reads the X-Goog-Channel-Id and
 * X-Goog-Resource-Id headers, looks up the channel row, dispatches a
 * ReverseSyncCalendarFromGoogleJob to fetch the changes.
 */
class GoogleCalendarWatchService
{
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';

    public function __construct(
        private readonly GoogleCalendarSyncService $sync,
    ) {}

    /**
     * Open a watch channel for the photographer's primary calendar.
     * Returns the channel id on success, null on failure.
     */
    public function subscribe(int $photographerId): ?string
    {
        if (!$this->canWatch($photographerId)) {
            return null;
        }

        $token = $this->getAccessToken($photographerId);
        if (!$token) return null;

        $channelId   = (string) Str::uuid();
        $watchToken  = bin2hex(random_bytes(16));
        $webhookUrl  = url('/api/webhooks/google-calendar');

        try {
            $resp = Http::withToken($token)
                ->timeout(15)
                ->post(self::API_BASE . '/calendars/primary/events/watch', [
                    'id'         => $channelId,
                    'type'       => 'web_hook',
                    'address'    => $webhookUrl,
                    'token'      => $watchToken,
                    // Google ignores values > 7 days; we send 7 to maximize.
                    'expiration' => (string) (now()->addDays(7)->getTimestampMs()),
                ]);

            if (!$resp->successful()) {
                Log::warning('gcal.watch_subscribe_failed', [
                    'photographer_id' => $photographerId,
                    'status'          => $resp->status(),
                    'body'            => substr($resp->body(), 0, 200),
                ]);
                return null;
            }

            $resourceId  = (string) $resp->json('resourceId');
            $resourceUri = (string) $resp->json('resourceUri');
            $expirationMs= (int)    $resp->json('expiration', 0);
            $expiresAt   = $expirationMs > 0
                ? \Carbon\Carbon::createFromTimestampMs($expirationMs)
                : now()->addDays(7);

            DB::table('gcal_watch_channels')->insert([
                'photographer_id'   => $photographerId,
                'channel_id'        => $channelId,
                'resource_id'       => $resourceId,
                'resource_uri'      => $resourceUri,
                'token'             => $watchToken,
                'expiration_at'     => $expiresAt,
                'last_renewed_at'   => now(),
                'status'            => 'active',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
            return $channelId;
        } catch (\Throwable $e) {
            Log::warning('gcal.watch_subscribe_exception', [
                'photographer_id' => $photographerId,
                'err'             => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Stop a specific channel. Used on disconnect or before re-subscribing.
     */
    public function unsubscribe(string $channelId): bool
    {
        $row = DB::table('gcal_watch_channels')
            ->where('channel_id', $channelId)
            ->first();
        if (!$row) return false;

        $token = $this->getAccessToken((int) $row->photographer_id);
        if (!$token) {
            // Even without a token we should still mark our row as stopped
            // so the cron doesn't try to renew a phantom subscription.
            DB::table('gcal_watch_channels')->where('id', $row->id)->update([
                'status'     => 'stopped',
                'updated_at' => now(),
            ]);
            return false;
        }

        try {
            // 'channels.stop' is fire-and-forget — Google returns 204 on
            // success. We don't strictly care about the response: if the
            // channel was already gone, that's fine; we still want to
            // mark it stopped locally.
            Http::withToken($token)
                ->timeout(10)
                ->post(self::API_BASE . '/channels/stop', [
                    'id'         => $channelId,
                    'resourceId' => $row->resource_id,
                ]);
        } catch (\Throwable $e) {
            Log::info('gcal.watch_stop_exception', ['err' => $e->getMessage()]);
        }

        DB::table('gcal_watch_channels')->where('id', $row->id)->update([
            'status'     => 'stopped',
            'updated_at' => now(),
        ]);
        return true;
    }

    /**
     * Renew channels approaching expiry. Idempotent + cron-safe.
     *
     * @return array{renewed:int, expired:int}
     */
    public function renewExpiringChannels(): array
    {
        $renewWindow = (int) config('booking.gcal_watch_renew_hours', 12);
        $cutoff = now()->addHours($renewWindow);

        $rows = DB::table('gcal_watch_channels')
            ->where('status', 'active')
            ->where('expiration_at', '<=', $cutoff)
            ->limit(100)
            ->get();

        $renewed = 0;
        $expired = 0;
        foreach ($rows as $row) {
            // Stop the old channel, subscribe a new one. We accept a brief
            // window where neither channel is live — the alternative
            // (overlapping watches) is messier because Google echoes back
            // the SAME resourceId and our webhook handler can't easily tell
            // which channel a notification belongs to.
            $this->unsubscribe($row->channel_id);
            $newId = $this->subscribe((int) $row->photographer_id);
            if ($newId) {
                $renewed++;
            } else {
                // The new subscribe failed — leave the old row marked
                // 'stopped' (already done by unsubscribe) and bump a
                // counter so the operator notices.
                $expired++;
                DB::table('gcal_watch_channels')->where('id', $row->id)->update([
                    'status'     => 'expired',
                    'updated_at' => now(),
                ]);
            }
        }
        return ['renewed' => $renewed, 'expired' => $expired];
    }

    /* ─────────────────── helpers ─────────────────── */

    private function canWatch(int $photographerId): bool
    {
        if (AppSetting::get('google_calendar_sync_enabled', '1') !== '1') return false;
        if (AppSetting::get('google_client_id', '') === '') return false;
        return DB::table('auth_social_logins')
            ->where('user_id', $photographerId)
            ->where('provider', 'google')
            ->exists();
    }

    /**
     * We piggy-back on GoogleCalendarSyncService's getAccessToken via a
     * tiny reflection step rather than duplicate the refresh-token flow
     * here. Keeps the token-refresh logic DRY (single owner).
     */
    private function getAccessToken(int $photographerId): ?string
    {
        try {
            $ref = new \ReflectionMethod($this->sync, 'getAccessToken');
            $ref->setAccessible(true);
            return $ref->invoke($this->sync, $photographerId);
        } catch (\Throwable $e) {
            Log::warning('gcal.watch_token_lookup_failed', ['err' => $e->getMessage()]);
            return null;
        }
    }
}
