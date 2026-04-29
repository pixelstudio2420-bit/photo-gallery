<?php

namespace Tests\Feature\Line;

use App\Models\AppSetting;
use App\Services\Line\LineDeliveryLogger;
use App\Services\Line\LineReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks down the reply-token send path.
 *
 * Properties under test:
 *
 *   • Successful reply → audit row 'sent', http_status=200.
 *   • Empty replyToken → returns ok=false, no LINE call.
 *   • 400 (expired token) → audit row 'failed', error captured.
 *   • Idempotency: second reply with the same token returns the cached
 *     'duplicate' result without calling LINE again.
 *   • Missing channel_access_token → fails fast.
 *   • replyToInboundEvent looks up the token from the persisted event.
 */
class LineReplyServiceTest extends TestCase
{
    use RefreshDatabase;

    private LineReplyService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('line_channel_access_token', 'test-tok');
        AppSetting::flushCache();
        $this->svc = new LineReplyService(new LineDeliveryLogger());
    }

    public function test_successful_reply_marks_audit_sent(): void
    {
        Http::fake(['*api.line.me*/reply' => Http::response('{}', 200)]);

        $r = $this->svc->replyText('TOK-OK-1', 'thanks!');
        $this->assertTrue($r['ok']);
        $this->assertSame(200, $r['http_status']);

        $this->assertSame(
            'sent',
            DB::table('line_deliveries')
                ->where('idempotency_key', 'reply.TOK-OK-1')->value('status'),
        );
    }

    public function test_empty_token_returns_ok_false(): void
    {
        Http::fake();   // assert no calls
        $r = $this->svc->replyWithToken('', [['type' => 'text', 'text' => 'x']]);
        $this->assertFalse($r['ok']);
        $this->assertSame('empty replyToken', $r['error']);
        Http::assertNothingSent();
    }

    public function test_expired_token_400_is_failure(): void
    {
        Http::fake([
            '*api.line.me*/reply' => Http::response('{"message":"Invalid reply token"}', 400),
        ]);

        $r = $this->svc->replyText('TOK-EXP', 'late');
        $this->assertFalse($r['ok']);
        $this->assertSame(400, $r['http_status']);

        $row = DB::table('line_deliveries')->where('idempotency_key', 'reply.TOK-EXP')->first();
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('Invalid reply token', $row->error);
    }

    public function test_duplicate_reply_returns_cached_result_without_calling_line(): void
    {
        Http::fake(['*api.line.me*/reply' => Http::response('{}', 200)]);

        // First call succeeds.
        $r1 = $this->svc->replyText('TOK-DUP', 'hi');
        $this->assertTrue($r1['ok']);

        // Block all further calls so we PROVE the second reply doesn't hit LINE.
        Http::fake(['*' => function () {
            throw new \RuntimeException('LINE must not be called for duplicate reply');
        }]);

        $r2 = $this->svc->replyText('TOK-DUP', 'hi');
        $this->assertTrue($r2['ok']);
        $this->assertSame(200, $r2['http_status']);

        // Only ONE row in the audit table for this token.
        $this->assertSame(
            1,
            DB::table('line_deliveries')->where('idempotency_key', 'reply.TOK-DUP')->count(),
        );
    }

    public function test_missing_token_fails_fast(): void
    {
        AppSetting::set('line_channel_access_token', '');
        AppSetting::flushCache();
        Http::fake();

        $r = $this->svc->replyText('TOK-NOK', 'hi');
        $this->assertFalse($r['ok']);
        $this->assertSame('missing token', $r['error']);
        Http::assertNothingSent();
    }

    public function test_reply_to_inbound_event_uses_persisted_reply_token(): void
    {
        $payload = [
            'type'       => 'message',
            'replyToken' => 'TOK-FROM-EVENT',
            'source'     => ['userId' => 'U' . str_repeat('z', 32)],
            'message'    => ['type' => 'text', 'id' => 'M-9', 'text' => 'help'],
        ];
        $rowId = DB::table('line_inbound_events')->insertGetId([
            'event_id'         => 'E-IB-1',
            'message_id'       => 'M-9',
            'event_type'       => 'message',
            'message_type'     => 'text',
            'line_user_id'     => 'U' . str_repeat('z', 32),
            'payload'          => json_encode($payload),
            'processing_status'=> 'processed',
            'received_at'      => now(),
        ]);

        Http::fake(['*api.line.me*/reply' => Http::response('{}', 200)]);

        $r = $this->svc->replyToInboundEvent($rowId, [['type' => 'text', 'text' => 'sure!']]);
        $this->assertTrue($r['ok']);

        $this->assertSame(
            'sent',
            DB::table('line_deliveries')
                ->where('idempotency_key', 'reply.TOK-FROM-EVENT')->value('status'),
        );
    }

    public function test_reply_to_inbound_event_without_reply_token_returns_error(): void
    {
        $rowId = DB::table('line_inbound_events')->insertGetId([
            'event_id'         => 'E-NO-TOK',
            'event_type'       => 'unfollow',
            'line_user_id'     => 'U' . str_repeat('y', 32),
            'payload'          => json_encode(['type' => 'unfollow']),  // no replyToken
            'processing_status'=> 'processed',
            'received_at'      => now(),
        ]);

        $r = $this->svc->replyToInboundEvent($rowId, [['type' => 'text', 'text' => 'x']]);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no replyToken', $r['error']);
    }
}
