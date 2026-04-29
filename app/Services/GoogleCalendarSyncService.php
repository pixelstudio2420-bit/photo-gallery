<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One-way Google Calendar sync.
 *
 * On booking confirm → creates an event on the photographer's primary calendar.
 * On booking cancel  → deletes that event.
 * On booking update  → updates event details.
 *
 * Auth model: re-uses the photographer's existing Google OAuth tokens that
 * were captured during /photographer/connect-google. Requires the Google
 * Calendar API scope to have been requested (we add it in SocialAuthController
 * scopes — see scopesFor()).
 *
 * Self-disabling: every public method silently no-ops when:
 *   • Google credentials are missing (admin hasn't set them up)
 *   • Photographer hasn't linked a Google account
 *   • Photographer's access token expired (TODO: refresh-token flow — for
 *     MVP we just give up and let the next confirmation try again)
 *
 * Failures are logged but never thrown — booking confirm/cancel must always
 * succeed even if Google is unreachable.
 */
class GoogleCalendarSyncService
{
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';

    /**
     * Create or update the calendar event for a booking. Returns the
     * Google event id (also stored on the booking row).
     */
    public function upsertBookingOnCalendar(Booking $booking): ?string
    {
        if (!$this->isEnabledFor($booking->photographer_id)) {
            return null;
        }

        $token = $this->getAccessToken($booking->photographer_id);
        if (!$token) return null;

        $body = $this->buildEventPayload($booking);

        try {
            if ($booking->gcal_event_id) {
                // Update existing event
                $resp = Http::withToken($token)
                    ->timeout(10)
                    ->put(self::API_BASE . '/calendars/primary/events/' . $booking->gcal_event_id, $body);
            } else {
                // Create new
                $resp = Http::withToken($token)
                    ->timeout(10)
                    ->post(self::API_BASE . '/calendars/primary/events', $body);
            }

            if (!$resp->successful()) {
                Log::warning('gcal.upsert_failed', [
                    'booking_id' => $booking->id,
                    'status'     => $resp->status(),
                    'body'       => substr($resp->body(), 0, 240),
                ]);
                return null;
            }

            $eventId = $resp->json('id');
            $booking->update([
                'gcal_event_id' => $eventId,
                'gcal_synced_at'=> now(),
            ]);
            return $eventId;
        } catch (\Throwable $e) {
            Log::warning('gcal.upsert_exception', ['err' => $e->getMessage(), 'booking_id' => $booking->id]);
            return null;
        }
    }

    /**
     * Delete the calendar event when the booking is cancelled.
     */
    public function removeBookingFromCalendar(Booking $booking): bool
    {
        if (!$booking->gcal_event_id || !$this->isEnabledFor($booking->photographer_id)) {
            return false;
        }
        $token = $this->getAccessToken($booking->photographer_id);
        if (!$token) return false;

        try {
            $resp = Http::withToken($token)
                ->timeout(10)
                ->delete(self::API_BASE . '/calendars/primary/events/' . $booking->gcal_event_id);

            // 410 Gone = already deleted, treat as success
            $ok = $resp->successful() || $resp->status() === 410;

            if ($ok) {
                $booking->update([
                    'gcal_event_id' => null,
                    'gcal_synced_at'=> now(),
                ]);
            }
            return $ok;
        } catch (\Throwable $e) {
            Log::warning('gcal.delete_exception', ['err' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────

    private function isEnabledFor(int $photographerId): bool
    {
        // Admin can flip the feature off globally.
        if (AppSetting::get('google_calendar_sync_enabled', '1') !== '1') {
            return false;
        }
        // Need google client credentials configured.
        if (empty(AppSetting::get('google_client_id'))) {
            return false;
        }
        // Photographer must have linked Google.
        return DB::table('auth_social_logins')
            ->where('user_id', $photographerId)
            ->where('provider', 'google')
            ->exists();
    }

    /**
     * Get the photographer's stored Google access token, refreshing it
     * via the refresh_token grant if it's expired or close to expiry.
     *
     * Token refresh is the difference between "GCal sync silently dies
     * after 1 hour" and a working integration. We aim to refresh ~5
     * minutes before the documented expiry to avoid mid-call expirations.
     */
    private function getAccessToken(int $photographerId): ?string
    {
        $row = DB::table('auth_social_logins')
            ->where('user_id', $photographerId)
            ->where('provider', 'google')
            ->orderByDesc('updated_at')
            ->first();

        if (!$row) return null;

        // Schemas across this app's history vary: some installations
        // have access_token / refresh_token / expires_at as columns;
        // others tuck them into a JSON `meta` blob. Probe both.
        $accessToken  = $row->access_token  ?? null;
        $refreshToken = $row->refresh_token ?? null;
        $expiresAt    = $row->expires_at    ?? null;
        if (is_string($row->meta ?? null)) {
            $meta = json_decode($row->meta, true) ?: [];
            $accessToken  ??= ($meta['access_token']  ?? null);
            $refreshToken ??= ($meta['refresh_token'] ?? null);
            $expiresAt    ??= ($meta['expires_at']    ?? null);
        }

        // If we have a refresh token AND the access token is expired
        // (or expires in < 5 min), refresh proactively.
        $needsRefresh = false;
        if ($refreshToken) {
            if (!$accessToken) {
                $needsRefresh = true;
            } elseif ($expiresAt) {
                try {
                    $exp = is_numeric($expiresAt)
                        ? (int) $expiresAt
                        : strtotime((string) $expiresAt);
                    if ($exp && $exp - time() < 300) {
                        $needsRefresh = true;
                    }
                } catch (\Throwable) {
                    // unparseable expires_at → defensive refresh
                    $needsRefresh = true;
                }
            }
        }

        if ($needsRefresh) {
            $refreshed = $this->refreshAccessToken($refreshToken, $row->id);
            if ($refreshed) {
                $accessToken = $refreshed;
            }
        }

        return $accessToken;
    }

    /**
     * Exchange the refresh_token for a fresh access_token and persist
     * it back to auth_social_logins. Returns the new access token, or
     * null on failure (caller treats null as "skip this sync attempt").
     */
    private function refreshAccessToken(string $refreshToken, int $socialLoginId): ?string
    {
        $clientId     = (string) AppSetting::get('google_client_id', '');
        $clientSecret = (string) AppSetting::get('google_client_secret', '');
        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        try {
            $resp = Http::asForm()
                ->timeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type'    => 'refresh_token',
                ]);

            if (!$resp->successful()) {
                Log::warning('gcal.token_refresh_failed', [
                    'status' => $resp->status(),
                    'body'   => substr($resp->body(), 0, 200),
                ]);
                return null;
            }

            $newAccess = (string) $resp->json('access_token');
            $expiresIn = (int) ($resp->json('expires_in') ?: 3600);
            $newExpiresAt = now()->addSeconds(max(60, $expiresIn - 60));

            // Write back. Use whichever column shape the row has — we
            // try the discrete columns first, fall back to merging into
            // the meta JSON blob if those columns don't exist.
            try {
                DB::table('auth_social_logins')->where('id', $socialLoginId)->update([
                    'access_token' => $newAccess,
                    'expires_at'   => $newExpiresAt,
                    'updated_at'   => now(),
                ]);
            } catch (\Throwable) {
                // Schema with meta-only token storage — merge into JSON.
                $row = DB::table('auth_social_logins')->where('id', $socialLoginId)->first();
                $meta = $row && is_string($row->meta) ? (json_decode($row->meta, true) ?: []) : [];
                $meta['access_token'] = $newAccess;
                $meta['expires_at']   = $newExpiresAt->timestamp;
                DB::table('auth_social_logins')->where('id', $socialLoginId)->update([
                    'meta'       => json_encode($meta),
                    'updated_at' => now(),
                ]);
            }

            return $newAccess;
        } catch (\Throwable $e) {
            Log::warning('gcal.token_refresh_exception', ['err' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Build the Google Calendar API event payload.
     * https://developers.google.com/calendar/api/v3/reference/events
     */
    private function buildEventPayload(Booking $booking): array
    {
        $start = $booking->scheduled_at;
        $end   = $booking->ends_at;

        $description  = "📸 Booking #{$booking->id}\n\n";
        $description .= "ลูกค้า: " . ($booking->customer?->first_name ?? '?') . "\n";
        if ($booking->customer_phone) $description .= "เบอร์: {$booking->customer_phone}\n";
        if ($booking->expected_photos) $description .= "จำนวนรูป: {$booking->expected_photos}\n";
        if ($booking->agreed_price)    $description .= "ราคา: " . number_format((float) $booking->agreed_price) . " ฿\n";
        if ($booking->customer_notes)  $description .= "\nหมายเหตุ: {$booking->customer_notes}\n";
        $description .= "\n— จาก Photo Gallery";

        return [
            'summary'     => '📷 ' . $booking->title,
            'description' => $description,
            'location'    => $booking->location,
            'start' => [
                'dateTime' => $start->toIso8601String(),
                'timeZone' => config('app.timezone', 'Asia/Bangkok'),
            ],
            'end' => [
                'dateTime' => $end->toIso8601String(),
                'timeZone' => config('app.timezone', 'Asia/Bangkok'),
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'popup', 'minutes' => 24 * 60],
                    ['method' => 'popup', 'minutes' => 60],
                ],
            ],
            'colorId' => '11', // tomato red — make booking events stand out
        ];
    }
}
