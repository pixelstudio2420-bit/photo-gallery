<?php

namespace Tests\Feature\Line;

use App\Models\AppSetting;
use App\Services\LineNotifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies multicast attempts produce ONE line_deliveries row per
 * recipient. Without this, "did admin X get the alert?" requires
 * grepping logs.
 *
 * What we test:
 *
 *   • A successful multicast to N admins → N rows, all status='sent'.
 *   • A failed multicast (LINE 500) → N rows, all status='failed'.
 *   • Admin user_id list deduplicates (two identical IDs → one row).
 *   • Invalid-format admin IDs are silently dropped (no row).
 */
class LineMulticastAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('line_messaging_enabled', '1');
        AppSetting::set('line_channel_access_token', 'tok');
        AppSetting::flushCache();
    }

    public function test_successful_multicast_writes_one_row_per_recipient(): void
    {
        Http::fake(['*api.line.me*/multicast' => Http::response('{}', 200)]);

        $a = 'U' . str_repeat('1', 32);
        $b = 'U' . str_repeat('2', 32);
        AppSetting::set('line_admin_user_ids', "{$a}, {$b}");
        AppSetting::flushCache();

        $ok = app(LineNotifyService::class)->notifyAdmin('hello admins');
        $this->assertTrue($ok);

        $rows = DB::table('line_deliveries')->where('delivery_type', 'multicast')->get();
        $this->assertCount(2, $rows, 'one row per recipient — even with chunked API call');
        foreach ($rows as $row) {
            $this->assertSame('sent', $row->status);
            $this->assertSame(200, (int) $row->http_status);
        }

        $this->assertEqualsCanonicalizing(
            [$a, $b],
            $rows->pluck('line_user_id')->all(),
        );
    }

    public function test_failed_multicast_writes_failed_rows(): void
    {
        Http::fake(['*api.line.me*/multicast' => Http::response('boom', 500)]);

        $a = 'U' . str_repeat('a', 32);
        AppSetting::set('line_admin_user_ids', $a);
        AppSetting::flushCache();

        $ok = app(LineNotifyService::class)->notifyAdmin('alert');
        $this->assertFalse($ok);

        $row = DB::table('line_deliveries')
            ->where('delivery_type', 'multicast')
            ->where('line_user_id', $a)
            ->first();
        $this->assertSame('failed', $row->status);
        $this->assertSame(500, (int) $row->http_status);
    }

    public function test_duplicate_admin_ids_are_deduplicated(): void
    {
        Http::fake(['*api.line.me*/multicast' => Http::response('{}', 200)]);

        $id = 'U' . str_repeat('3', 32);
        AppSetting::set('line_admin_user_ids', "{$id} {$id} {$id}");
        AppSetting::flushCache();

        app(LineNotifyService::class)->notifyAdmin('once');

        $this->assertSame(
            1,
            DB::table('line_deliveries')
                ->where('delivery_type', 'multicast')
                ->where('line_user_id', $id)
                ->count(),
            'duplicate admin entries must dedupe to a single audit row',
        );
    }

    public function test_invalid_format_admin_ids_are_dropped(): void
    {
        Http::fake(['*api.line.me*/multicast' => Http::response('{}', 200)]);

        $bad   = 'not-a-line-id';
        $valid = 'U' . str_repeat('4', 32);
        AppSetting::set('line_admin_user_ids', "{$bad}, {$valid}");
        AppSetting::flushCache();

        app(LineNotifyService::class)->notifyAdmin('test');

        // Only the valid id should produce a row.
        $rows = DB::table('line_deliveries')->where('delivery_type', 'multicast')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($valid, $rows->first()->line_user_id);
    }
}
