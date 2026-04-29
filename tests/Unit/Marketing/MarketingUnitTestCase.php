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
