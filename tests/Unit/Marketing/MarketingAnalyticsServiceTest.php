<?php

namespace Tests\Unit\Marketing;

use App\Models\AppSetting;
use App\Models\Marketing\MarketingEvent;
use App\Services\Marketing\MarketingAnalyticsService;

class MarketingAnalyticsServiceTest extends MarketingUnitTestCase
{
    public function test_track_returns_null_when_disabled(): void
    {
        $svc = app(MarketingAnalyticsService::class);
        $this->assertNull($svc->track(MarketingEvent::EV_PAGE_VIEW, ['url' => '/']));
    }

    public function test_track_creates_event_when_enabled(): void
    {
        $this->enableMarketing('analytics');

        $svc = app(MarketingAnalyticsService::class);
        $ev = $svc->track(MarketingEvent::EV_PAGE_VIEW, [
            'url'      => '/home',
            'session_id' => 'abc123',
        ]);

        $this->assertInstanceOf(MarketingEvent::class, $ev);
        $this->assertSame('page_view', $ev->event_name);
        $this->assertSame('/home', $ev->url);
    }

    public function test_track_filters_unknown_keys(): void
    {
        $this->enableMarketing('analytics');
        $svc = app(MarketingAnalyticsService::class);
        $ev = $svc->track(MarketingEvent::EV_PAGE_VIEW, [
            'url'           => '/x',
            'malicious_key' => 'danger',
            'drop_me'       => 'nope',
        ]);
        $this->assertNotNull($ev);
        // the unknown keys shouldn't land in the attributes
        $this->assertArrayNotHasKey('malicious_key', $ev->getAttributes());
    }

    public function test_funnel_counts_distinct_sessions(): void
    {
        $this->enableMarketing('analytics');
        $svc = app(MarketingAnalyticsService::class);

        $svc->track(MarketingEvent::EV_PAGE_VIEW,  ['session_id' => 'a']);
        $svc->track(MarketingEvent::EV_PAGE_VIEW,  ['session_id' => 'b']);
        $svc->track(MarketingEvent::EV_PAGE_VIEW,  ['session_id' => 'a']);
        $svc->track(MarketingEvent::EV_ADD_TO_CART, ['session_id' => 'a']);
        $svc->track(MarketingEvent::EV_PURCHASE,    ['session_id' => 'a']);

        $funnel = $svc->funnel([
            MarketingEvent::EV_PAGE_VIEW,
            MarketingEvent::EV_ADD_TO_CART,
            MarketingEvent::EV_PURCHASE,
        ]);

        $this->assertCount(3, $funnel);
        $this->assertSame(2, $funnel[0]['count']); // distinct sessions a,b
        $this->assertSame(1, $funnel[1]['count']);
        $this->assertSame(1, $funnel[2]['count']);
    }

    public function test_funnel_rates_calculated(): void
    {
        $this->enableMarketing('analytics');
        $svc = app(MarketingAnalyticsService::class);

        foreach (['s1','s2','s3','s4'] as $s) {
            $svc->track(MarketingEvent::EV_PAGE_VIEW, ['session_id' => $s]);
        }
        $svc->track(MarketingEvent::EV_PURCHASE, ['session_id' => 's1']);

        $funnel = $svc->funnel([
            MarketingEvent::EV_PAGE_VIEW,
            MarketingEvent::EV_PURCHASE,
        ]);

        $this->assertSame(25.0, $funnel[1]['rate_from_first']);
    }

    public function test_roas_aggregates_revenue_by_source(): void
    {
        $this->enableMarketing('analytics');
        $svc = app(MarketingAnalyticsService::class);

        $svc->track(MarketingEvent::EV_PURCHASE, [
            'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'spring',
            'value' => 500,
        ]);
        $svc->track(MarketingEvent::EV_PURCHASE, [
            'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'spring',
            'value' => 300,
        ]);
        $svc->track(MarketingEvent::EV_PURCHASE, [
            'utm_source' => 'facebook', 'utm_medium' => 'cpc', 'utm_campaign' => 'summer',
            'value' => 200,
        ]);

        $rows = $svc->roas();
        $this->assertGreaterThanOrEqual(2, $rows->count());
        $top = $rows->first();
        $this->assertSame('google', $top->utm_source);
        $this->assertEquals(800.0, (float) $top->revenue);
        $this->assertEquals(2, (int) $top->purchases);
    }

    public function test_daily_series_fills_missing_dates_with_zero(): void
    {
        $this->enableMarketing('analytics');
        $svc = app(MarketingAnalyticsService::class);

        $svc->track(MarketingEvent::EV_PAGE_VIEW, ['session_id' => 't1']);

        $series = $svc->dailySeries(MarketingEvent::EV_PAGE_VIEW, 7);
        $this->assertCount(7, $series);
        // last day should be today with count >= 1
        $this->assertGreaterThanOrEqual(1, end($series)['count']);
    }

    public function test_overview_returns_expected_keys(): void
    {
        $this->enableMarketing('analytics');
        $svc = app(MarketingAnalyticsService::class);
        $svc->track(MarketingEvent::EV_PAGE_VIEW, ['session_id' => 'x']);
        $svc->track(MarketingEvent::EV_PURCHASE, ['value' => 100]);

        $ov = $svc->overview();
        $this->assertArrayHasKey('page_views_today', $ov);
        $this->assertArrayHasKey('revenue_month', $ov);
        $this->assertArrayHasKey('top_source', $ov);
        $this->assertGreaterThanOrEqual(1, $ov['page_views_today']);
    }

    public function test_ltv_by_source_calculates_per_customer(): void
    {
        $this->enableMarketing('analytics');
        $svc = app(MarketingAnalyticsService::class);

        $svc->track(MarketingEvent::EV_PURCHASE, ['user_id' => 1, 'utm_source' => 'email', 'value' => 300]);
        $svc->track(MarketingEvent::EV_PURCHASE, ['user_id' => 2, 'utm_source' => 'email', 'value' => 700]);

        $rows = $svc->ltvBySource();
        $this->assertGreaterThanOrEqual(1, $rows->count());
        $email = $rows->firstWhere('utm_source', 'email');
        $this->assertNotNull($email);
        $this->assertEquals(1000.0, (float) $email->total_revenue);
        $this->assertEquals(500.0, (float) $email->ltv);
    }

    public function test_purge_deletes_old_events(): void
    {
        $this->enableMarketing('analytics');
        AppSetting::set('marketing_event_retention_days', '30');
        AppSetting::flushCache();

        // one recent, one old
        MarketingEvent::create([
            'event_name' => 'page_view',
            'occurred_at' => now()->subDays(100),
        ]);
        MarketingEvent::create([
            'event_name' => 'page_view',
            'occurred_at' => now()->subDays(5),
        ]);

        $svc = app(MarketingAnalyticsService::class);
        $deleted = $svc->purgeOldEvents();

        $this->assertSame(1, $deleted);
        $this->assertSame(1, MarketingEvent::count());
    }

    public function test_purge_with_zero_retention_returns_zero(): void
    {
        AppSetting::set('marketing_event_retention_days', '0');
        AppSetting::flushCache();
        $svc = app(MarketingAnalyticsService::class);
        $this->assertSame(0, $svc->purgeOldEvents());
    }
}
