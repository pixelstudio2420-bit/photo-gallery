<?php

namespace Tests\Feature\Marketing;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared helpers for Marketing test suite:
 *  - Uses RefreshDatabase with SQLite :memory: (see phpunit.xml)
 *  - Flushes AppSetting static cache between tests
 *  - Helper to enable/disable marketing features quickly
 */
abstract class MarketingTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Baseline: marketing master switch OFF. RefreshDatabase replays the
        // 2026_05_19_000015_seed_how_to_landing_pages migration which force-sets
        // marketing_enabled='1' as a side effect of seeding landing pages.
        // Tests asserting the off-by-default / feature-disabled paths must
        // start from a known-off baseline; tests that need it ON call
        // enableMarketing() explicitly.
        AppSetting::set('marketing_enabled', '0');
        AppSetting::flushCache();
    }

    protected function tearDown(): void
    {
        AppSetting::flushCache();
        parent::tearDown();
    }

    /**
     * Enable master + one or more features.
     * Usage: $this->enableMarketing('newsletter', 'referral')
     */
    protected function enableMarketing(string ...$features): void
    {
        AppSetting::set('marketing_enabled', '1');
        foreach ($features as $f) {
            AppSetting::set("marketing_{$f}_enabled", '1');
        }
        AppSetting::flushCache();
    }

    protected function disableMarketing(): void
    {
        AppSetting::set('marketing_enabled', '0');
        AppSetting::flushCache();
    }
}
