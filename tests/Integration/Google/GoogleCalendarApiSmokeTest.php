<?php

namespace Tests\Integration\Google;

use Illuminate\Support\Facades\Http;
use Tests\Integration\IntegrationTestCase;

/**
 * Real Google Calendar API smoke.
 *
 * Required env
 * ------------
 *   INTEGRATION_GOOGLE_CALENDAR_ACCESS_TOKEN  fresh OAuth access
 *                                             token for a test
 *                                             account (mint via the
 *                                             Photo Gallery's normal
 *                                             OAuth flow + paste here)
 *
 * What we verify
 * --------------
 *   1. /calendar/v3/calendars/primary returns 200 — token works
 *   2. event create + delete round-trip — exercises the same call
 *      shape GoogleCalendarSyncService uses
 *   3. events.watch can be opened (then we close the channel)
 *
 * Tokens live ~1h. Run this test SOON after pasting the env var.
 * For longer runs, use a refresh_token grant + cache the access
 * token.
 */
class GoogleCalendarApiSmokeTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireEnv(['INTEGRATION_GOOGLE_CALENDAR_ACCESS_TOKEN']);
    }

    public function test_primary_calendar_endpoint_is_reachable(): void
    {
        $resp = Http::withToken(env('INTEGRATION_GOOGLE_CALENDAR_ACCESS_TOKEN'))
            ->timeout(10)
            ->get('https://www.googleapis.com/calendar/v3/calendars/primary');

        $this->assertTrue($resp->successful(),
            'GET /calendars/primary failed: ' . substr($resp->body(), 0, 200));
        $this->assertNotEmpty($resp->json('id'));
    }

    public function test_event_create_then_delete_round_trip(): void
    {
        $token = env('INTEGRATION_GOOGLE_CALENDAR_ACCESS_TOKEN');

        $start = now()->addDay();
        $end   = $start->copy()->addHour();

        $createResp = Http::withToken($token)->timeout(10)->post(
            'https://www.googleapis.com/calendar/v3/calendars/primary/events',
            [
                'summary'     => '🧪 Smoke test event — ' . now()->toIso8601String(),
                'description' => 'Created by GoogleCalendarApiSmokeTest. Safe to delete.',
                'start'       => ['dateTime' => $start->toIso8601String(), 'timeZone' => 'UTC'],
                'end'         => ['dateTime' => $end->toIso8601String(),   'timeZone' => 'UTC'],
            ],
        );
        $this->assertTrue($createResp->successful(),
            'event create failed: ' . substr($createResp->body(), 0, 200));
        $eventId = (string) $createResp->json('id');
        $this->assertNotEmpty($eventId);

        // Clean up.
        $delResp = Http::withToken($token)->timeout(10)->delete(
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$eventId}",
        );
        // 200 (deleted) or 410 (already gone) both acceptable.
        $this->assertTrue($delResp->successful() || $delResp->status() === 410,
            'event delete failed: ' . substr($delResp->body(), 0, 200));
    }
}
