<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Announcement model + scope contracts.
 *
 * The visibility scopes are what the photographer/customer feeds depend
 * on — these tests pin down: only published-and-in-window items show,
 * audience filtering respects 'all' as wildcard, pinned/priority
 * sorting puts the right items at the top.
 */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $overrides = []): Announcement
    {
        return Announcement::create(array_merge([
            'title'     => 'Test ' . uniqid(),
            'audience'  => Announcement::AUDIENCE_ALL,
            'priority'  => Announcement::PRIORITY_NORMAL,
            'status'    => Announcement::STATUS_PUBLISHED,
            'starts_at' => null,
            'ends_at'   => null,
            'is_pinned' => false,
        ], $overrides));
    }

    /* ───────────────── Slug auto-gen ───────────────── */

    public function test_slug_auto_generated_from_title(): void
    {
        $a = $this->make(['title' => 'Hello World Promo 2026']);
        $this->assertSame('hello-world-promo-2026', $a->slug);
    }

    public function test_slug_collision_appends_random_suffix(): void
    {
        $a = $this->make(['title' => 'Same Title']);
        $b = $this->make(['title' => 'Same Title']);
        $this->assertNotSame($a->slug, $b->slug);
        $this->assertStringStartsWith('same-title', $b->slug);
    }

    public function test_explicit_slug_preserved(): void
    {
        $a = $this->make(['title' => 'Whatever', 'slug' => 'my-custom-slug']);
        $this->assertSame('my-custom-slug', $a->slug);
    }

    /* ───────────────── Active scope ───────────────── */

    public function test_active_scope_excludes_drafts(): void
    {
        $this->make(['status' => 'published']);
        $this->make(['status' => 'draft']);
        $this->make(['status' => 'archived']);

        $this->assertSame(1, Announcement::active()->count());
    }

    public function test_active_scope_excludes_future_starts(): void
    {
        $this->make(['status' => 'published', 'starts_at' => now()->addHour()]);

        $this->assertSame(0, Announcement::active()->count(),
            'Announcement scheduled for future starts_at must NOT be active yet.');
    }

    public function test_active_scope_excludes_past_ends(): void
    {
        $this->make(['status' => 'published', 'ends_at' => now()->subHour()]);

        $this->assertSame(0, Announcement::active()->count(),
            'Announcement past its ends_at must NOT be active.');
    }

    public function test_active_scope_includes_open_window(): void
    {
        $this->make([
            'status'    => 'published',
            'starts_at' => now()->subDay(),
            'ends_at'   => now()->addDay(),
        ]);

        $this->assertSame(1, Announcement::active()->count());
    }

    public function test_active_scope_includes_no_window(): void
    {
        $this->make(['status' => 'published', 'starts_at' => null, 'ends_at' => null]);
        $this->assertSame(1, Announcement::active()->count());
    }

    /* ───────────────── Audience scope ───────────────── */

    public function test_visible_to_photographer_includes_all_and_photographer(): void
    {
        $this->make(['audience' => 'photographer', 'title' => 'Photographer one']);
        $this->make(['audience' => 'all',          'title' => 'Both']);
        $this->make(['audience' => 'customer',     'title' => 'Customer only']);

        $titles = Announcement::visibleTo('photographer')->pluck('title')->all();
        $this->assertContains('Photographer one', $titles);
        $this->assertContains('Both', $titles);
        $this->assertNotContains('Customer only', $titles,
            'Customer-only announcements MUST NOT leak into photographer feed.');
    }

    public function test_visible_to_customer_includes_all_and_customer(): void
    {
        $this->make(['audience' => 'photographer', 'title' => 'P']);
        $this->make(['audience' => 'all',          'title' => 'B']);
        $this->make(['audience' => 'customer',     'title' => 'C']);

        $titles = Announcement::visibleTo('customer')->pluck('title')->all();
        $this->assertNotContains('P', $titles);
        $this->assertContains('B', $titles);
        $this->assertContains('C', $titles);
    }

    public function test_unknown_audience_falls_back_to_all_only(): void
    {
        $this->make(['audience' => 'all',          'title' => 'A']);
        $this->make(['audience' => 'photographer', 'title' => 'P']);

        $titles = Announcement::visibleTo('garbage')->pluck('title')->all();
        $this->assertContains('A', $titles);
        $this->assertNotContains('P', $titles,
            'Unknown audience must default to all (so we never leak audience-scoped items).');
    }

    /* ───────────────── Feed sort order ───────────────── */

    public function test_feed_order_pinned_first_then_priority_then_recency(): void
    {
        $this->make(['title' => 'Old normal',      'priority' => 'normal', 'is_pinned' => false, 'starts_at' => now()->subDays(3)]);
        $this->make(['title' => 'New normal',      'priority' => 'normal', 'is_pinned' => false, 'starts_at' => now()->subHour()]);
        $this->make(['title' => 'Old high',        'priority' => 'high',   'is_pinned' => false, 'starts_at' => now()->subDays(2)]);
        $this->make(['title' => 'Old pinned',      'priority' => 'normal', 'is_pinned' => true,  'starts_at' => now()->subDays(5)]);

        $titles = Announcement::query()->forFeed()->pluck('title')->all();
        $this->assertSame('Old pinned', $titles[0],   'Pinned wins over everything else');
        $this->assertSame('Old high',   $titles[1],   'Then high-priority');
        $this->assertSame('New normal', $titles[2],   'Then most-recent normal');
        $this->assertSame('Old normal', $titles[3]);
    }

    /* ───────────────── isLive helper ───────────────── */

    public function test_is_live_respects_status_and_window(): void
    {
        $live = $this->make(['status' => 'published']);
        $this->assertTrue($live->isLive());

        $draft = $this->make(['status' => 'draft']);
        $this->assertFalse($draft->isLive());

        $future = $this->make(['status' => 'published', 'starts_at' => now()->addHour()]);
        $this->assertFalse($future->isLive());

        $past = $this->make(['status' => 'published', 'ends_at' => now()->subHour()]);
        $this->assertFalse($past->isLive());
    }

    /* ───────────────── View count ───────────────── */

    public function test_bump_view_count_increments(): void
    {
        $a = $this->make();
        // Refresh once to pick up the DB default of 0 (Eloquent doesn't
        // re-fetch defaults on create).
        $a->refresh();
        $this->assertSame(0, (int) $a->view_count);
        $a->bumpViewCount();
        $a->refresh();
        $this->assertSame(1, (int) $a->view_count);
    }
}
