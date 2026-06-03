<?php

namespace Tests\Unit\Marketing;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class MarketingUnitTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Establish the documented "safe default" baseline: marketing master
        // switch OFF. RefreshDatabase replays ALL migrations, and one of them
        // (2026_05_19_000015_seed_how_to_landing_pages) force-sets
        // marketing_enabled='1' as a side effect of seeding landing pages.
        // Without this reset, every marketing test that asserts the
        // off-by-default invariant would inherit that '1' and fail. Tests that
        // need it ON call enableMarketing() explicitly.
        AppSetting::set('marketing_enabled', '0');
        AppSetting::flushCache();
    }

    protected function tearDown(): void
    {
        AppSetting::flushCache();
        parent::tearDown();
    }

    protected function enableMarketing(string ...$features): void
    {
        AppSetting::set('marketing_enabled', '1');
        foreach ($features as $f) {
            AppSetting::set("marketing_{$f}_enabled", '1');
        }
        AppSetting::flushCache();
    }
}
