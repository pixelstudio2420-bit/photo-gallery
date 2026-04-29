<?php

namespace Tests\Feature\Booking;

use App\Jobs\Booking\ReverseSyncCalendarFromGoogleJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Locks down the GCal webhook contract.
 *
 *   • Unknown channel id → 200 ack (silently swallow rather than 4xx
 *     so Google doesn't retry forever)
 *   • Token mismatch → 401 (timing-safe)
 *   • Sync ping (initial state='sync') → 200 + sync_ack flag, no job
 *   • Real change push → 200 + dispatches ReverseSyncCalendarFromGoogleJob
 *   • Missing channel id header → 400
 */
class GoogleCalendarWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_channel_id_returns_400(): void
    {
        $r = $this->call('POST', '/api/webhooks/google-calendar');
        $r->assertStatus(400);
    }

    public function test_unknown_channel_silently_acks(): void
    {
        $r = $this->call('POST', '/api/webhooks/google-calendar', [], [], [], [
            'HTTP_X-Goog-Channel-Id'    => 'never-was-a-real-channel',
            'HTTP_X-Goog-Channel-Token' => 'whatever',
            'HTTP_X-Goog-Resource-State'=> 'exists',
        ]);
        $r->assertStatus(200);
    }

    public function test_token_mismatch_returns_401(): void
    {
        DB::table('gcal_watch_channels')->insert([
            'photographer_id' => 1,
            'channel_id'      => 'ch-1',
            'resource_id'     => 'r-1',
            'token'           => 'expected-token',
            'expiration_at'   => now()->addDays(7),
            'last_renewed_at' => now(),
            'status'          => 'active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $r = $this->call('POST', '/api/webhooks/google-calendar', [], [], [], [
            'HTTP_X-Goog-Channel-Id'    => 'ch-1',
            'HTTP_X-Goog-Channel-Token' => 'WRONG-TOKEN',
            'HTTP_X-Goog-Resource-State'=> 'exists',
        ]);
        $r->assertStatus(401);
    }

    public function test_sync_ping_acks_without_dispatching_job(): void
    {
        Queue::fake();
        DB::table('gcal_watch_channels')->insert([
            'photographer_id' => 7,
            'channel_id'      => 'ch-sync',
            'resource_id'     => 'r-sync',
            'token'           => 'tok',
            'expiration_at'   => now()->addDays(7),
            'last_renewed_at' => now(),
            'status'          => 'active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $r = $this->call('POST', '/api/webhooks/google-calendar', [], [], [], [
            'HTTP_X-Goog-Channel-Id'    => 'ch-sync',
            'HTTP_X-Goog-Channel-Token' => 'tok',
            'HTTP_X-Goog-Resource-State'=> 'sync',
        ]);
        $r->assertStatus(200);
        $r->assertJson(['sync_ack' => true]);

        Queue::assertNothingPushed();
    }

    public function test_real_change_dispatches_reverse_sync_job(): void
    {
        Queue::fake();
        DB::table('gcal_watch_channels')->insert([
            'photographer_id' => 42,
            'channel_id'      => 'ch-real',
            'resource_id'     => 'r-real',
            'token'           => 'tok-real',
            'expiration_at'   => now()->addDays(7),
            'last_renewed_at' => now(),
            'status'          => 'active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $r = $this->call('POST', '/api/webhooks/google-calendar', [], [], [], [
            'HTTP_X-Goog-Channel-Id'    => 'ch-real',
            'HTTP_X-Goog-Channel-Token' => 'tok-real',
            'HTTP_X-Goog-Resource-State'=> 'exists',
        ]);
        $r->assertStatus(200);

        Queue::assertPushed(ReverseSyncCalendarFromGoogleJob::class,
            fn ($job) => $job->photographerId === 42);
    }

    public function test_stopped_channel_silently_acks(): void
    {
        Queue::fake();
        DB::table('gcal_watch_channels')->insert([
            'photographer_id' => 1,
            'channel_id'      => 'ch-dead',
            'resource_id'     => 'r-dead',
            'token'           => 'tok',
            'expiration_at'   => now()->subDay(),
            'last_renewed_at' => now()->subDays(8),
            'status'          => 'stopped',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $r = $this->call('POST', '/api/webhooks/google-calendar', [], [], [], [
            'HTTP_X-Goog-Channel-Id'    => 'ch-dead',
            'HTTP_X-Goog-Channel-Token' => 'tok',
            'HTTP_X-Goog-Resource-State'=> 'exists',
        ]);
        $r->assertStatus(200);

        // Channel is stopped → don't dispatch a sync job.
        Queue::assertNothingPushed();
    }
}
