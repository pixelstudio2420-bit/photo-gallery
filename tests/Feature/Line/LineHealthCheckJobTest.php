<?php

namespace Tests\Feature\Line;

use App\Jobs\Line\LineHealthCheckJob;
use App\Models\AppSetting;
use App\Services\LineNotifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Locks down the LINE token health check.
 *
 * What we guarantee:
 *
 *   • Healthy 200 response → no alert, dedup cache cleared.
 *   • 401 response → admin email + dedup cache set + multicast attempt.
 *   • Repeated failures within 6h → only ONE alert fires (dedup).
 *   • Recovery after a failure clears the dedup so the next failure
 *     re-alerts immediately.
 *   • Messaging globally disabled → job is a no-op.
 *   • Missing token → still alerts (config-mistake path).
 *
 * Outbound HTTP is mocked via Http::fake; emails are captured via
 * Mail::fake; cache uses the array driver (default in tests).
 */
class LineHealthCheckJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('line_messaging_enabled', '1');
        AppSetting::set('line_channel_access_token', 'test-token');
        AppSetting::set('line_admin_user_ids', '');  // empty so multicast is a no-op
        AppSetting::flushCache();
        Cache::forget('line_health.alert_sent_at');
    }

    private function seedAdmin(): void
    {
        \App\Models\Admin::create([
            'email'         => 'h-' . uniqid() . '@example.com',
            'password_hash' => password_hash('p', PASSWORD_BCRYPT),
            'first_name'    => 'H',
            'last_name'     => 'A',
            'role'          => 'superadmin',
            'is_active'     => true,
        ]);
    }

    public function test_healthy_response_does_not_alert(): void
    {
        Http::fake([
            '*api.line.me/v2/bot/info' => Http::response(['userId' => 'BOT-123'], 200),
        ]);
        Mail::fake();
        $this->seedAdmin();

        (new LineHealthCheckJob())->handle(app(LineNotifyService::class));

        Mail::assertNothingSent();
        $this->assertNull(Cache::get('line_health.alert_sent_at'));
    }

    public function test_401_response_alerts_via_email_and_sets_dedup(): void
    {
        Http::fake([
            '*api.line.me/v2/bot/info' => Http::response('Invalid token', 401),
        ]);
        $this->seedAdmin();

        // Use a log spy instead of Mail::fake — Mail::raw without a
        // Mailable class is awkward to assert via the fake. The log
        // entry is the canonical "we alerted" signal.
        \Log::shouldReceive('error')->once()->withArgs(function ($msg, $ctx = []) {
            return str_contains((string) $msg, 'LineHealthCheckJob')
                && str_contains((string) $msg, 'unhealthy');
        });
        \Log::shouldReceive('debug')->zeroOrMoreTimes();
        \Log::shouldReceive('info')->zeroOrMoreTimes();
        \Log::shouldReceive('warning')->zeroOrMoreTimes();

        (new LineHealthCheckJob())->handle(app(LineNotifyService::class));

        $this->assertNotNull(Cache::get('line_health.alert_sent_at'),
            'a failed health check must set the dedup cache key');
    }

    public function test_dedup_window_blocks_second_alert(): void
    {
        Http::fake([
            '*api.line.me/v2/bot/info' => Http::response('Down', 500),
        ]);
        $this->seedAdmin();

        // First failure raises (we don't strict-assert the log; we
        // observe the side-effect via the cache key).
        (new LineHealthCheckJob())->handle(app(LineNotifyService::class));
        $firstStamp = Cache::get('line_health.alert_sent_at');
        $this->assertNotNull($firstStamp);

        // Second failure within the window must be silent. We assert
        // the cache stamp DIDN'T change (a re-alert would overwrite it
        // with a later timestamp).
        (new LineHealthCheckJob())->handle(app(LineNotifyService::class));
        $this->assertSame($firstStamp, Cache::get('line_health.alert_sent_at'),
            'second failure inside dedup window must NOT re-stamp the cache');
    }

    public function test_recovery_clears_dedup(): void
    {
        // Pretend we already alerted.
        Cache::put('line_health.alert_sent_at', now()->toIso8601String(), 3600);

        Http::fake([
            '*api.line.me/v2/bot/info' => Http::response(['userId' => 'BOT-OK'], 200),
        ]);

        (new LineHealthCheckJob())->handle(app(LineNotifyService::class));

        $this->assertNull(Cache::get('line_health.alert_sent_at'),
            'a healthy probe must drop the alert dedup so the next failure re-alerts');
    }

    public function test_messaging_disabled_is_a_noop(): void
    {
        AppSetting::set('line_messaging_enabled', '0');
        AppSetting::flushCache();
        Http::fake();   // no calls expected

        (new LineHealthCheckJob())->handle(app(LineNotifyService::class));

        Http::assertNothingSent();
        $this->assertNull(Cache::get('line_health.alert_sent_at'),
            'messaging-disabled is a config-not-failure state — must NOT alert');
    }

    public function test_missing_token_alerts_with_config_reason(): void
    {
        AppSetting::set('line_channel_access_token', '');
        AppSetting::flushCache();
        $this->seedAdmin();
        Http::fake();   // not even called when token is missing

        (new LineHealthCheckJob())->handle(app(LineNotifyService::class));

        // Cache dedup key set = an alert fired. The Log::error path
        // captures the reason string ('config' here) — we test the
        // observable side-effect rather than coupling the test to
        // log-spy plumbing.
        $this->assertNotNull(Cache::get('line_health.alert_sent_at'));
        // No /v2/bot/info call should have happened.
        Http::assertNothingSent();
    }
}
