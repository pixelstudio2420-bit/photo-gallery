<?php

namespace Tests\Unit\Marketing;

use App\Models\AppSetting;
use App\Services\Marketing\MarketingService;

class MarketingServiceTest extends MarketingUnitTestCase
{
    public function test_master_off_by_default(): void
    {
        $mk = app(MarketingService::class);
        $this->assertFalse($mk->masterEnabled());
    }

    public function test_enabled_returns_false_when_master_off_even_if_feature_on(): void
    {
        AppSetting::set('marketing_enabled', '0');
        AppSetting::set('marketing_newsletter_enabled', '1');
        AppSetting::flushCache();

        $mk = app(MarketingService::class);
        $this->assertFalse($mk->enabled('newsletter'));
    }

    public function test_enabled_returns_true_only_when_both_toggles_on(): void
    {
        $this->enableMarketing('newsletter');

        $mk = app(MarketingService::class);
        $this->assertTrue($mk->masterEnabled());
        $this->assertTrue($mk->enabled('newsletter'));
        $this->assertFalse($mk->enabled('referral')); // not turned on
    }

    public function test_pixel_enabled_respects_master_gate(): void
    {
        AppSetting::set('marketing_enabled', '0');
        AppSetting::set('marketing_ga4_enabled', '1');
        AppSetting::flushCache();

        $mk = app(MarketingService::class);
        $this->assertFalse($mk->pixelEnabled('ga4'));
    }

    public function test_any_pixel_enabled_false_when_master_off(): void
    {
        AppSetting::set('marketing_enabled', '0');
        foreach (['fb_pixel', 'ga4', 'gtm', 'google_ads', 'line_tag', 'tiktok_pixel'] as $p) {
            AppSetting::set("marketing_{$p}_enabled", '1');
        }
        AppSetting::flushCache();

        $this->assertFalse(app(MarketingService::class)->anyPixelEnabled());
    }

    public function test_get_set_marketing_values(): void
    {
        $mk = app(MarketingService::class);
        $mk->set('test_key', 'hello');
        AppSetting::flushCache();
        $this->assertSame('hello', $mk->get('test_key'));
    }

    public function test_status_summary_reports_all_features(): void
    {
        $mk = app(MarketingService::class);
        $summary = $mk->statusSummary();

        $this->assertArrayHasKey('master', $summary);
        $this->assertArrayHasKey('features', $summary);
        $this->assertArrayHasKey('ga4', $summary['features']);
        $this->assertArrayHasKey('newsletter', $summary['features']);
        $this->assertArrayHasKey('landing_pages', $summary['features']);
        $this->assertArrayHasKey('push', $summary['features']);
        $this->assertFalse($summary['master']);
    }

    public function test_landing_pages_push_analytics_helpers(): void
    {
        $this->enableMarketing('landing_pages', 'push', 'analytics');
        $mk = app(MarketingService::class);

        $this->assertTrue($mk->landingPagesEnabled());
        $this->assertTrue($mk->pushEnabled());
        $this->assertTrue($mk->analyticsEnabled());
    }
}
