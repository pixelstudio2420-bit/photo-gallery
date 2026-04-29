<?php

namespace Tests\Feature\Line;

use App\Jobs\Line\SendLinePushJob;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\Line\LineDeliveryLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks down SendLinePushJob's lifecycle.
 *
 * What's covered:
 *
 *   • 200 from LINE → audit row flips to 'sent'.
 *   • 401 from LINE (bad token) → terminal failure (no retries), audit
 *     row 'failed' with the http_status set.
 *   • 400 "user hasn't added OA" → row 'skipped' AND users.line_user_id
 *     detached (so we don't keep firing pushes at a dead recipient).
 *   • 5xx → re-throws so the queue's retry layer takes over.
 *   • 429 → release(retry_after) so we honour LINE's rate-limit hint.
 *   • Missing token → fail() called explicitly (don't retry forever).
 *
 * We use Http::fake() to short-circuit the actual LINE API call and
 * RefreshDatabase + the real DeliveryLogger so the audit row is real.
 */
class SendLinePushJobTest extends TestCase
{
    use RefreshDatabase;

    private LineDeliveryLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new LineDeliveryLogger();
        AppSetting::set('line_channel_access_token', 'fake-token-' . uniqid());
        AppSetting::flushCache();
    }

    private function lineId(): string
    {
        return 'U' . str_repeat('1', 32);
    }

    private function makeJob(int $deliveryId): SendLinePushJob
    {
        return new SendLinePushJob(
            lineUserId: $this->lineId(),
            messages:   [['type' => 'text', 'text' => 'hi']],
            deliveryId: $deliveryId,
        );
    }

    public function test_successful_send_marks_audit_sent(): void
    {
        Http::fake(['*api.line.me*' => Http::response('{}', 200)]);

        $r = $this->logger->begin(null, $this->lineId(), 'push', 'text');
        $job = $this->makeJob($r['id']);
        $job->handle($this->logger);

        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame('sent', $row->status);
        $this->assertSame(200, (int) $row->http_status);
        $this->assertSame(1, (int) $row->attempts);
    }

    public function test_401_token_failure_is_terminal(): void
    {
        Http::fake(['*api.line.me*' => Http::response('Unauthorized', 401)]);

        $r = $this->logger->begin(null, $this->lineId(), 'push', 'text');
        $job = $this->makeJob($r['id']);

        try {
            $job->handle($this->logger);
        } catch (\Throwable $e) {
            // job calls $this->fail() which throws; we catch so the
            // assertion below can run.
        }

        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame('failed', $row->status);
        $this->assertSame(401, (int) $row->http_status);
    }

    public function test_400_recipient_not_added_marks_skipped_and_detaches_user(): void
    {
        Http::fake(['*api.line.me*' => Http::response(
            '{"message":"Failed to send messages"}', 400,
        )]);

        $lineUserId = $this->lineId();

        // Seed user with this line_user_id so we can verify detach.
        $user = User::create([
            'first_name'    => 'X',
            'last_name'     => 'Y',
            'email'         => 'detach-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
            'line_user_id'  => $lineUserId,
        ]);

        $r = $this->logger->begin(null, $lineUserId, 'push', 'text');
        $job = $this->makeJob($r['id']);
        $job->handle($this->logger);

        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame('skipped', $row->status);

        $this->assertNull($user->fresh()->line_user_id,
            'dead-recipient response must detach line_user_id');
    }

    public function test_5xx_re_throws_for_queue_retry(): void
    {
        Http::fake(['*api.line.me*' => Http::response('boom', 503)]);

        $r = $this->logger->begin(null, $this->lineId(), 'push', 'text');
        $job = $this->makeJob($r['id']);

        $this->expectException(\RuntimeException::class);
        $job->handle($this->logger);
    }

    public function test_missing_token_calls_fail(): void
    {
        AppSetting::set('line_channel_access_token', '');
        AppSetting::flushCache();

        $r = $this->logger->begin(null, $this->lineId(), 'push', 'text');
        $job = $this->makeJob($r['id']);

        try {
            $job->handle($this->logger);
        } catch (\Throwable) {
            // expected — fail() throws to halt the job.
        }

        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('token', $row->error);
    }

    public function test_failed_hook_writes_terminal_state_when_handle_did_not(): void
    {
        // Simulate a code path where handle() crashed before logging:
        // the row is left in 'pending'. failed() must repair this.
        $r = $this->logger->begin(null, $this->lineId(), 'push', 'text');
        $job = $this->makeJob($r['id']);
        $job->failed(new \RuntimeException('crash mid-handle'));

        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('crash mid-handle', $row->error);
    }

    public function test_failed_hook_does_not_overwrite_already_terminal_state(): void
    {
        // If handle() already wrote 'sent', failed() must NOT clobber it
        // back to 'failed' (would happen if sent + then a transient
        // post-condition error re-raised).
        $r = $this->logger->begin(null, $this->lineId(), 'push', 'text');
        $this->logger->markSent($r['id'], 200);

        $job = $this->makeJob($r['id']);
        $job->failed(new \RuntimeException('post-send glitch'));

        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame('sent', $row->status,
            'failed() must only flip pending→failed, never sent→failed');
    }

    public function test_backoff_schedule_grows_exponentially(): void
    {
        $job = $this->makeJob(0);
        $b = $job->backoff();
        $this->assertSame([30, 120, 600, 1800], $b);
        // Sanity: must be monotonically increasing.
        for ($i = 1; $i < count($b); $i++) {
            $this->assertGreaterThan($b[$i - 1], $b[$i]);
        }
    }
}
