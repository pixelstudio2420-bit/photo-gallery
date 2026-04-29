<?php

namespace Tests\Unit\Marketing;

use App\Models\AppSetting;
use App\Models\Marketing\PushCampaign;
use App\Models\Marketing\PushSubscription;
use App\Services\Marketing\PushService;

class PushServiceTest extends MarketingUnitTestCase
{
    public function test_enabled_reflects_master_and_feature(): void
    {
        $svc = app(PushService::class);
        $this->assertFalse($svc->enabled());

        $this->enableMarketing('push');
        $this->assertTrue($svc->enabled());
    }

    public function test_subscribe_creates_record(): void
    {
        $this->enableMarketing('push');
        $svc = app(PushService::class);

        $sub = $svc->subscribe([
            'endpoint' => 'https://fcm.googleapis.com/xyz',
            'keys' => ['p256dh' => 'PPP', 'auth' => 'AAA'],
        ], userId: null, locale: 'th');

        $this->assertInstanceOf(PushSubscription::class, $sub);
        $this->assertSame('active', $sub->status);
        $this->assertSame('PPP', $sub->p256dh);
        $this->assertSame('th', $sub->locale);
    }

    public function test_subscribe_updates_existing_by_endpoint(): void
    {
        $this->enableMarketing('push');
        $svc = app(PushService::class);

        $svc->subscribe([
            'endpoint' => 'https://push.example.com/1',
            'keys' => ['p256dh' => 'old', 'auth' => 'a1'],
        ]);
        $svc->subscribe([
            'endpoint' => 'https://push.example.com/1',
            'keys' => ['p256dh' => 'new', 'auth' => 'a2'],
        ]);

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame('new', PushSubscription::first()->p256dh);
    }

    public function test_unsubscribe_marks_revoked(): void
    {
        $this->enableMarketing('push');
        $svc = app(PushService::class);

        $svc->subscribe([
            'endpoint' => 'https://push.example.com/a',
            'keys' => ['p256dh' => 'x', 'auth' => 'y'],
        ]);
        $svc->unsubscribe('https://push.example.com/a');

        $sub = PushSubscription::where('endpoint', 'https://push.example.com/a')->first();
        $this->assertSame('revoked', $sub->status);
    }

    public function test_public_vapid_key_returns_stored_value(): void
    {
        AppSetting::set('marketing_push_vapid_public', 'ABCDEFGH');
        AppSetting::flushCache();

        $svc = app(PushService::class);
        $this->assertSame('ABCDEFGH', $svc->publicVapidKey());
    }

    public function test_send_short_circuits_when_disabled(): void
    {
        $svc = app(PushService::class);
        $campaign = PushCampaign::create([
            'title' => 'Hello', 'body' => 'World', 'segment' => 'all', 'status' => 'draft',
        ]);
        $result = $svc->send($campaign);

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['targets']);
        $campaign->refresh();
        $this->assertSame('failed', $campaign->status);
    }

    public function test_send_in_stub_mode_marks_failed_with_zero_sent(): void
    {
        $this->enableMarketing('push');
        // No VAPID keys + no minishlink lib → stub fallback path. The
        // previous (buggy) behavior reported `sent = N` which misled
        // admins into thinking pushes had landed; the corrected
        // behavior reports `sent = 0` and status='failed' so the
        // dashboard surfaces the missing dependency.
        AppSetting::set('marketing_push_vapid_public', '');
        AppSetting::set('marketing_push_vapid_private', '');
        AppSetting::flushCache();

        $svc = app(PushService::class);
        PushSubscription::create([
            'endpoint' => 'https://push.example.com/s1',
            'p256dh' => 'a', 'auth' => 'b', 'status' => 'active',
        ]);
        PushSubscription::create([
            'endpoint' => 'https://push.example.com/s2',
            'p256dh' => 'c', 'auth' => 'd', 'status' => 'active',
        ]);

        $campaign = PushCampaign::create([
            'title' => 'Promo', 'body' => 'Sale', 'segment' => 'all', 'status' => 'draft',
        ]);
        $result = $svc->send($campaign);

        $this->assertSame(2, $result['targets']);
        $this->assertSame(0, $result['sent'],   'stub mode must report sent=0');
        $this->assertSame(2, $result['failed'], 'stub mode must report failed=N (truthful telemetry)');
        $campaign->refresh();
        $this->assertSame('failed', $campaign->status);
        $this->assertNotNull($campaign->sent_at, 'sent_at IS set so dashboards can sort by attempt time');
    }

    public function test_send_users_segment_filters(): void
    {
        $this->enableMarketing('push');
        $svc = app(PushService::class);

        PushSubscription::create([
            'endpoint' => 'https://push.example.com/u1', 'user_id' => 1,
            'p256dh' => 'a', 'auth' => 'b', 'status' => 'active',
        ]);
        PushSubscription::create([
            'endpoint' => 'https://push.example.com/g1', 'user_id' => null,
            'p256dh' => 'c', 'auth' => 'd', 'status' => 'active',
        ]);

        $campaign = PushCampaign::create([
            'title' => 'UsersOnly', 'body' => 'Hi', 'segment' => 'users', 'status' => 'draft',
        ]);
        $svc->send($campaign);

        $campaign->refresh();
        $this->assertSame(1, (int) $campaign->targets);
    }

    public function test_record_click_increments_counter(): void
    {
        $c = PushCampaign::create([
            'title' => 'Click', 'body' => 'Me', 'segment' => 'all', 'status' => 'sent',
        ]);
        app(PushService::class)->recordClick($c->id);
        $c->refresh();
        $this->assertSame(1, (int) $c->clicks);
    }

    public function test_summary_reports_counts(): void
    {
        PushSubscription::create(['endpoint' => 'a', 'p256dh' => 'x', 'auth' => 'y', 'status' => 'active']);
        PushSubscription::create(['endpoint' => 'b', 'p256dh' => 'x', 'auth' => 'y', 'status' => 'stale']);
        PushSubscription::create(['endpoint' => 'c', 'p256dh' => 'x', 'auth' => 'y', 'status' => 'revoked']);
        PushCampaign::create(['title' => 'c1', 'body' => 'b', 'segment' => 'all', 'status' => 'sent', 'sent' => 10, 'clicks' => 2]);

        $sum = app(PushService::class)->summary();
        $this->assertSame(1, $sum['subscribers']);
        $this->assertSame(1, $sum['stale']);
        $this->assertSame(1, $sum['revoked']);
        $this->assertSame(1, $sum['campaigns']);
        $this->assertSame(10, $sum['total_sent']);
        $this->assertSame(2, $sum['total_clicks']);
    }
}
