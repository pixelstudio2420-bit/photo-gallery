<?php

namespace Tests\Feature\Line;

use App\Services\Line\LineDeliveryLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks down the line_deliveries audit table contract.
 *
 * The properties we guarantee:
 *
 *   • begin() returns ['id' => int, 'duplicate' => bool] every time.
 *
 *   • A second begin() with the same (line_user_id, idempotency_key)
 *     returns the FIRST row's id, sets duplicate=true, and does NOT
 *     insert a new row. This is the contract callers rely on to skip
 *     a redundant LINE API call.
 *
 *   • markSent / markFailed / markSkipped each transition state and
 *     bump the attempt counter (where appropriate). Idempotent —
 *     calling twice doesn't cascade into a third state.
 *
 *   • Different idempotency keys for the same user → independent
 *     deliveries.
 *
 *   • NULL idempotency_key → no dedup at all (every call inserts).
 */
class LineDeliveryAuditTest extends TestCase
{
    use RefreshDatabase;

    private LineDeliveryLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new LineDeliveryLogger();
    }

    private function lineId(string $tag): string
    {
        return 'U' . str_pad($tag, 32, 'a');
    }

    public function test_begin_inserts_pending_row(): void
    {
        $r = $this->logger->begin(
            userId:        42,
            lineUserId:    $this->lineId('1'),
            deliveryType:  'push',
            messageType:   'text',
            payloadSummary:'hello',
        );
        $this->assertFalse($r['duplicate']);
        $this->assertGreaterThan(0, $r['id']);

        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame('pending', $row->status);
        $this->assertSame('push', $row->delivery_type);
        $this->assertSame(42, (int) $row->user_id);
    }

    public function test_idempotency_key_dedupes(): void
    {
        $line = $this->lineId('2');
        $key  = 'order.42.delivery';

        $first  = $this->logger->begin(null, $line, 'push', 'text', null, null, $key);
        $second = $this->logger->begin(null, $line, 'push', 'text', null, null, $key);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($first['id'], $second['id']);

        $this->assertSame(
            1,
            DB::table('line_deliveries')->where('idempotency_key', $key)->count(),
        );
    }

    public function test_different_idempotency_keys_do_not_collide(): void
    {
        $line = $this->lineId('3');
        $a = $this->logger->begin(null, $line, 'push', 'text', null, null, 'a');
        $b = $this->logger->begin(null, $line, 'push', 'text', null, null, 'b');

        $this->assertNotSame($a['id'], $b['id']);
        $this->assertFalse($a['duplicate']);
        $this->assertFalse($b['duplicate']);
    }

    public function test_null_idempotency_key_never_dedupes(): void
    {
        $line = $this->lineId('4');
        $a = $this->logger->begin(null, $line, 'push', 'text');
        $b = $this->logger->begin(null, $line, 'push', 'text');
        $this->assertNotSame($a['id'], $b['id']);
        $this->assertFalse($a['duplicate']);
        $this->assertFalse($b['duplicate']);
    }

    public function test_mark_sent_transitions_status_and_increments_attempts(): void
    {
        $r = $this->logger->begin(null, $this->lineId('5'), 'push', 'text');
        $this->logger->markSent($r['id'], 200);

        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame('sent', $row->status);
        $this->assertSame(200, (int) $row->http_status);
        $this->assertSame(1, (int) $row->attempts);
        $this->assertNotNull($row->sent_at);
    }

    public function test_mark_failed_records_error_and_status(): void
    {
        $r = $this->logger->begin(null, $this->lineId('6'), 'push', 'text');
        $this->logger->markFailed($r['id'], 500, 'transient');

        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame('failed', $row->status);
        $this->assertSame(500, (int) $row->http_status);
        $this->assertSame('transient', $row->error);
        $this->assertSame(1, (int) $row->attempts);
    }

    public function test_mark_skipped_does_not_bump_attempts(): void
    {
        $r = $this->logger->begin(null, $this->lineId('7'), 'push', 'text');
        $this->logger->markSkipped($r['id'], 'recipient blocked');

        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame('skipped', $row->status);
        $this->assertSame('recipient blocked', $row->error);
        $this->assertSame(0, (int) $row->attempts,
            'skipped is a "we never tried" state — attempts must stay 0');
    }

    public function test_increment_attempt_does_not_change_status(): void
    {
        $r = $this->logger->begin(null, $this->lineId('8'), 'push', 'text');
        $this->logger->incrementAttempt($r['id']);
        $this->logger->incrementAttempt($r['id']);

        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame('pending', $row->status);
        $this->assertSame(2, (int) $row->attempts);
    }

    public function test_payload_summary_is_truncated(): void
    {
        $long = str_repeat('a', 2000);
        $r = $this->logger->begin(
            userId:        null,
            lineUserId:    $this->lineId('9'),
            deliveryType:  'push',
            messageType:   'text',
            payloadSummary: $long,
        );
        $row = DB::table('line_deliveries')->where('id', $r['id'])->first();
        $this->assertSame(500, mb_strlen($row->payload_summary),
            'payload_summary must be truncated to fit the column');
    }
}
