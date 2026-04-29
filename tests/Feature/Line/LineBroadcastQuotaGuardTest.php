<?php

namespace Tests\Feature\Line;

use App\Models\AppSetting;
use App\Services\Marketing\LineBroadcastService;
use App\Services\Marketing\MarketingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies the broadcast quota guard behaves correctly.
 *
 * Properties:
 *
 *   • If consumption is well below limit → broadcast goes through (the
 *     /broadcast call gets fired).
 *   • If consumption is near the limit → broadcast is BLOCKED, no
 *     /broadcast call, returns ok=false with quota numbers.
 *   • Quota=none (unlimited paid plan) → never block.
 *   • Strict mode + quota API down → block.
 *   • Lenient mode (default) + quota API down → still allow (don't
 *     block marketing on a transient API issue).
 *   • Guard disabled via app setting → broadcast goes through even
 *     when over-quota.
 */
class LineBroadcastQuotaGuardTest extends TestCase
{
    use RefreshDatabase;

    private LineBroadcastService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        // The marketing service requires both master + per-feature toggles;
        // settings are namespaced with `marketing_` prefix internally.
        AppSetting::set('marketing_enabled', '1');
        AppSetting::set('marketing_line_messaging_enabled', '1');
        AppSetting::set('marketing_line_channel_access_token', 'tok');
        AppSetting::set('line_broadcast_quota_check', '1');
        AppSetting::set('line_broadcast_quota_strict', '0');
        AppSetting::flushCache();
        $this->svc = new LineBroadcastService(app(MarketingService::class));
    }

    private function fakeQuota(?int $limit, int $used): void
    {
        $quotaResp = $limit === null
            ? ['type' => 'none']
            : ['type' => 'limited', 'value' => $limit];

        Http::fake([
            '*api.line.me/v2/bot/message/quota'             => Http::response($quotaResp, 200),
            '*api.line.me/v2/bot/message/quota/consumption' => Http::response(['totalUsage' => $used], 200),
            '*api.line.me/v2/bot/message/broadcast'         => Http::response('{}', 200),
        ]);
    }

    public function test_broadcast_proceeds_when_well_under_quota(): void
    {
        $this->fakeQuota(limit: 200, used: 50);

        $r = $this->svc->broadcastText('hi all');
        $this->assertTrue($r['ok']);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/message/broadcast'));
    }

    public function test_broadcast_blocked_when_near_quota_limit(): void
    {
        // 200 limit, 195 used → headroom = min(50, 5%×200=10) = 10
        // 195 + 10 ≥ 200 → block
        $this->fakeQuota(limit: 200, used: 195);

        $r = $this->svc->broadcastText('would-blow-budget');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('quota exhausted', $r['error']);
        $this->assertSame(195, $r['used']);
        $this->assertSame(200, $r['limit']);

        Http::assertNotSent(fn ($req) => str_contains($req->url(), '/message/broadcast'));
    }

    public function test_unlimited_plan_never_blocks(): void
    {
        $this->fakeQuota(limit: null, used: 999_999);   // 'type' => 'none'

        $r = $this->svc->broadcastText('on a paid plan');
        $this->assertTrue($r['ok']);
    }

    public function test_strict_mode_blocks_when_quota_api_unreachable(): void
    {
        AppSetting::set('line_broadcast_quota_strict', '1');
        AppSetting::flushCache();

        // Both quota endpoints return 503 — we cannot determine state.
        Http::fake([
            '*api.line.me/v2/bot/message/quota'             => Http::response('down', 503),
            '*api.line.me/v2/bot/message/quota/consumption' => Http::response('down', 503),
            '*api.line.me/v2/bot/message/broadcast'         => Http::response('{}', 200),
        ]);

        $r = $this->svc->broadcastText('strict');
        $this->assertFalse($r['ok'], 'strict mode must block when quota state is unknown');
        Http::assertNotSent(fn ($req) => str_contains($req->url(), '/message/broadcast'));
    }

    public function test_lenient_mode_allows_when_quota_api_unreachable(): void
    {
        // Default = lenient.
        Http::fake([
            '*api.line.me/v2/bot/message/quota'             => Http::response('down', 503),
            '*api.line.me/v2/bot/message/quota/consumption' => Http::response('down', 503),
            '*api.line.me/v2/bot/message/broadcast'         => Http::response('{}', 200),
        ]);

        $r = $this->svc->broadcastText('lenient');
        $this->assertTrue($r['ok'],
            'lenient mode must allow broadcasts when the quota probe itself is failing');
    }

    public function test_guard_disabled_skips_check_entirely(): void
    {
        AppSetting::set('line_broadcast_quota_check', '0');
        AppSetting::flushCache();

        // Even if quota would block, guard-off must let it through.
        $this->fakeQuota(limit: 200, used: 199);
        $r = $this->svc->broadcastText('opt-out');

        $this->assertTrue($r['ok']);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/message/broadcast'));
    }
}
