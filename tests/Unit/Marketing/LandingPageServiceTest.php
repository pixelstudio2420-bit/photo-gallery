<?php

namespace Tests\Unit\Marketing;

use App\Models\Marketing\LandingPage;
use App\Services\Marketing\LandingPageService;

class LandingPageServiceTest extends MarketingUnitTestCase
{
    public function test_create_generates_slug_from_title(): void
    {
        $svc = app(LandingPageService::class);
        $lp = $svc->create(['title' => 'Summer Sale 2026']);

        $this->assertSame('summer-sale-2026', $lp->slug);
        $this->assertSame('draft', $lp->status);
        $this->assertSame('indigo', $lp->theme);
    }

    public function test_create_uses_provided_slug(): void
    {
        $svc = app(LandingPageService::class);
        $lp = $svc->create(['title' => 'Anything', 'slug' => 'custom-slug']);
        $this->assertSame('custom-slug', $lp->slug);
    }

    public function test_unique_slug_appends_suffix_on_conflict(): void
    {
        $svc = app(LandingPageService::class);
        $svc->create(['title' => 'Promo']);
        $second = $svc->create(['title' => 'Promo']);

        $this->assertSame('promo-2', $second->slug);
    }

    public function test_unique_slug_empty_base_generates_random(): void
    {
        $svc = app(LandingPageService::class);
        $slug = $svc->uniqueSlug('!!!');
        $this->assertStringStartsWith('lp-', $slug);
    }

    public function test_update_changes_slug_when_provided_different(): void
    {
        $svc = app(LandingPageService::class);
        $lp = $svc->create(['title' => 'Original']);

        $updated = $svc->update($lp, ['slug' => 'new-slug']);
        $this->assertSame('new-slug', $updated->slug);
    }

    public function test_publish_sets_status_and_published_at(): void
    {
        $svc = app(LandingPageService::class);
        $lp = $svc->create(['title' => 'Launch']);
        $svc->publish($lp);

        $this->assertSame('published', $lp->status);
        $this->assertNotNull($lp->published_at);
    }

    public function test_archive_sets_status(): void
    {
        $svc = app(LandingPageService::class);
        $lp = $svc->create(['title' => 'Old Page']);
        $svc->archive($lp);

        $this->assertSame('archived', $lp->status);
    }

    public function test_record_view_increments_counter(): void
    {
        $svc = app(LandingPageService::class);
        $lp = $svc->create(['title' => 'View Test']);
        $svc->recordView($lp);
        $svc->recordView($lp);
        $lp->refresh();

        $this->assertSame(2, (int) $lp->views);
    }

    public function test_record_conversion_increments_counter(): void
    {
        $svc = app(LandingPageService::class);
        $lp = $svc->create(['title' => 'Conv Test']);
        $svc->recordConversion($lp);
        $lp->refresh();

        $this->assertSame(1, (int) $lp->conversions);
    }

    public function test_normalize_sections_drops_invalid_block_types(): void
    {
        $svc = app(LandingPageService::class);
        $raw = [
            ['type' => 'heading', 'data' => ['heading' => 'Hi']],
            ['type' => 'unknown', 'data' => ['x' => 1]],
            ['type' => 'cta', 'data' => ['label' => 'Click']],
        ];
        $out = $svc->normalizeSections($raw);
        $this->assertCount(2, $out);
        $this->assertSame('heading', $out[0]['type']);
        $this->assertSame('cta', $out[1]['type']);
    }

    public function test_normalize_sections_preserves_block_data(): void
    {
        $svc = app(LandingPageService::class);
        $raw = [['type' => 'text', 'data' => ['body' => 'Hello world']]];
        $out = $svc->normalizeSections($raw);
        $this->assertSame('Hello world', $out[0]['data']['body']);
    }

    public function test_summary_counts_statuses_and_totals(): void
    {
        $svc = app(LandingPageService::class);
        $published = $svc->create(['title' => 'Pub']);
        $svc->publish($published);
        $svc->recordView($published);
        $svc->recordView($published);
        $svc->recordConversion($published);

        $svc->create(['title' => 'Draft One']);
        $archived = $svc->create(['title' => 'Archive Me']);
        $svc->archive($archived);

        $sum = $svc->summary();
        $this->assertSame(3, $sum['total']);
        $this->assertSame(1, $sum['published']);
        $this->assertSame(1, $sum['drafts']);
        $this->assertSame(1, $sum['archived']);
        $this->assertSame(2, $sum['total_views']);
        $this->assertSame(1, $sum['total_conv']);
    }

    public function test_conversion_rate_calculated_correctly(): void
    {
        $svc = app(LandingPageService::class);
        $lp = $svc->create(['title' => 'Rate']);
        $lp->update(['views' => 100, 'conversions' => 5]);
        $lp->refresh();
        $this->assertEquals(5.0, $lp->conversionRate());
    }
}
