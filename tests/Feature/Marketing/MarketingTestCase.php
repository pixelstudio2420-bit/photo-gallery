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
