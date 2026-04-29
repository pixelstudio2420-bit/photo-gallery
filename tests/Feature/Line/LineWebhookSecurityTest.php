<?php

namespace Tests\Feature\Line;

use App\Models\AppSetting;
use App\Services\Line\LineSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * End-to-end security + idempotency tests for POST /api/webhooks/line.
 *
 * The contracts we lock in:
 *
 *   1. Invalid signature → 401 + audit row written + NO contact_messages
 *      side-effect.
 *
 *   2. Missing signature header (when enforcement is on) → 401.
 *
 *   3. Valid signature → 200 + line_inbound_events row created with
 *      processing_status='processed' for handled events.
 *
 *   4. Same webhookEventId twice → second one is recorded as 'duplicate'
 *      and no extra contact_messages row.
 *
 *   5. Same message_id twice (even with different webhookEventId) →
 *      second is duplicate via the unique constraint on message_id.
 *
 *   6. Image event → DownloadLineMediaJob is dispatched (queue fake).
 *
 *   7. Unfollow event → users.line_user_id is detached.
 *
 *   8. Signature enforcement can be turned off via app setting (for
 *      local dev), but ON is the default.
 */
class LineWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-channel-secret';

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('line_channel_secret', $this->secret);
        AppSetting::set('line_webhook_signature_required', '1');
        AppSetting::flushCache();
    }

    private function postWithSig(array $payload, ?string $sigOverride = null): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $sig  = $sigOverride ?? (new LineSignatureVerifier())->sign($body, $this->secret);

        return $this->call(
            'POST',
            '/api/webhooks/line',
            [], [], [],
            [
                'CONTENT_TYPE'         => 'application/json',
                'HTTP_X-Line-Signature'=> $sig,
            ],
            $body,
        );
    }

    // =========================================================================
    // Security — signature enforcement
    // =========================================================================

    public function test_invalid_signature_returns_401(): void
    {
        $r = $this->postWithSig(['events' => []], sigOverride: 'definitely-not-the-right-signature');
        $r->assertStatus(401);
    }

    public function test_missing_signature_returns_401(): void
    {
        $body = json_encode(['events' => []]);
        $r = $this->call('POST', '/api/webhooks/line', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $body);
        $r->assertStatus(401);
    }

    public function test_invalid_signature_writes_audit_log_failure(): void
    {
        $this->postWithSig(['events' => []], 'forged');
        $this->assertDatabaseHas('payment_audit_log', [
            'action' => 'line_signature_failure',
        ]);
    }

    public function test_signature_can_be_bypassed_via_app_setting(): void
    {
        // Some local-dev setups can't easily compute the signature; we
        // honour an explicit opt-out in app_settings — but the DEFAULT
        // is on, and this opt-out is documented.
        AppSetting::set('line_webhook_signature_required', '0');
        AppSetting::flushCache();

        $body = json_encode(['events' => []]);
        $r = $this->call('POST', '/api/webhooks/line', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $body);
        $r->assertStatus(200);
    }

    // =========================================================================
    // Idempotency — webhookEventId + message_id
    // =========================================================================

    public function test_duplicate_webhook_event_id_is_skipped(): void
    {
        $event = [
            'webhookEventId' => '01HQXX' . str_repeat('0', 20),
            'type'           => 'message',
            'timestamp'      => 1714316800000,
            'source'         => ['userId' => 'U' . str_repeat('a', 32)],
            'message'        => ['type' => 'text', 'id' => 'M-1', 'text' => 'hi'],
        ];

        $first  = $this->postWithSig(['events' => [$event]]);
        $second = $this->postWithSig(['events' => [$event]]);

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertSame(1, $first->json('processed'));
        $this->assertSame(1, $second->json('duplicate'));

        // contact_messages should have only ONE row (idempotency by msg_id).
        $this->assertSame(
            1,
            DB::table('contact_messages')
                ->where('email', 'like', 'line+U%')
                ->count(),
        );
    }

    public function test_duplicate_message_id_is_skipped_even_with_different_event_id(): void
    {
        // Same message_id, different webhookEventId — LINE's dedup by
        // event id wouldn't catch this, but our message_id constraint
        // does.
        $base = [
            'type'      => 'message',
            'timestamp' => 1714316800000,
            'source'    => ['userId' => 'U' . str_repeat('b', 32)],
            'message'   => ['type' => 'text', 'id' => 'M-DUP', 'text' => 'hello'],
        ];
        $e1 = ['webhookEventId' => 'E1' . str_repeat('0', 30)] + $base;
        $e2 = ['webhookEventId' => 'E2' . str_repeat('0', 30)] + $base;

        $this->postWithSig(['events' => [$e1]]);
        $this->postWithSig(['events' => [$e2]]);

        $this->assertSame(
            1,
            DB::table('line_inbound_events')->where('message_id', 'M-DUP')->count(),
        );
    }

    // =========================================================================
    // Side effects per event type
    // =========================================================================

    public function test_text_message_creates_contact_ticket_and_audit_row(): void
    {
        $event = [
            'webhookEventId' => 'E-' . str_repeat('1', 30),
            'type'           => 'message',
            'timestamp'      => 1714316800000,
            'source'         => ['userId' => 'U' . str_repeat('c', 32)],
            'message'        => ['type' => 'text', 'id' => 'M-TXT-1', 'text' => 'help me'],
        ];
        $this->postWithSig(['events' => [$event]]);

        $this->assertDatabaseHas('line_inbound_events', [
            'event_id'         => 'E-' . str_repeat('1', 30),
            'message_id'       => 'M-TXT-1',
            'event_type'       => 'message',
            'message_type'     => 'text',
            'processing_status'=> 'processed',
        ]);
        // Inbound chats land in the 'general' bucket — admin can re-bucket
        // from the inbox if needed. The status enum doesn't have 'open',
        // so 'new' is the new-ticket marker.
        $this->assertDatabaseHas('contact_messages', [
            'category' => 'general',
            'status'   => 'new',
            'email'    => 'line+U' . str_repeat('c', 32) . '@webhook.local',
        ]);
    }

    public function test_image_message_dispatches_download_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $event = [
            'webhookEventId' => 'E-IMG-' . str_repeat('1', 24),
            'type'           => 'message',
            'timestamp'      => 1714316800000,
            'source'         => ['userId' => 'U' . str_repeat('d', 32)],
            'message'        => ['type' => 'image', 'id' => 'M-IMG-1'],
        ];
        $this->postWithSig(['events' => [$event]]);

        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Jobs\Line\DownloadLineMediaJob::class,
            fn ($job) => $job->messageId === 'M-IMG-1' && $job->contentType === 'image',
        );
        $this->assertDatabaseHas('line_inbound_events', [
            'message_id'   => 'M-IMG-1',
            'message_type' => 'image',
            'processing_status' => 'processed',
        ]);
    }

    public function test_unfollow_event_detaches_user_line_id(): void
    {
        $lineUserId = 'U' . str_repeat('e', 32);

        // Seed a user with this line_user_id.
        $user = \App\Models\User::create([
            'first_name'    => 'L',
            'last_name'     => 'X',
            'email'         => 'detach-' . uniqid() . '@example.com',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('p'),
            'auth_provider' => 'local',
            'line_user_id'  => $lineUserId,
        ]);

        $event = [
            'webhookEventId' => 'E-UF-' . str_repeat('1', 25),
            'type'           => 'unfollow',
            'timestamp'      => 1714316800000,
            'source'         => ['userId' => $lineUserId],
        ];
        $this->postWithSig(['events' => [$event]]);

        $this->assertNull($user->fresh()->line_user_id,
            'unfollow event must clear line_user_id from users table');
    }

    // =========================================================================
    // Robustness — bad payload doesn't crash the webhook
    // =========================================================================

    public function test_unknown_event_type_is_recorded_but_does_not_fail(): void
    {
        $event = [
            'webhookEventId' => 'E-WAT-' . str_repeat('1', 24),
            'type'           => 'beacon',  // not handled, must not 500
            'timestamp'      => 1714316800000,
            'source'         => ['userId' => 'U' . str_repeat('f', 32)],
        ];
        $r = $this->postWithSig(['events' => [$event]]);
        $r->assertStatus(200);

        $row = DB::table('line_inbound_events')
            ->where('event_id', 'E-WAT-' . str_repeat('1', 24))->first();
        $this->assertNotNull($row, 'beacon must be recorded even though handler is a no-op');
        $this->assertSame('processed', $row->processing_status);
    }

    public function test_empty_events_array_is_acked(): void
    {
        $r = $this->postWithSig(['events' => []]);
        $r->assertStatus(200);
    }
}
