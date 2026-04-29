<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'name'           => 'Test Event',
            'slug'           => 'test-event-' . uniqid(),
            'description'    => 'A test event for automated testing.',
            'status'         => 'active',
            'visibility'     => 'public',
            'price_per_photo' => 10.00,
            'is_free'        => false,
            'view_count'     => 0,
        ], $overrides));
    }

    // ─── Index ───

    public function test_events_index_page_loads(): void
    {
        $response = $this->get(route('events.index'));

        $response->assertStatus(200);
    }

    // ─── Show ───

    public function test_event_show_page_loads(): void
    {
        $event = $this->createEvent();

        $response = $this->get(route('events.show', $event->slug));

        $response->assertStatus(200);
    }

    // ─── Password Protected ───

    public function test_password_protected_event_requires_password(): void
    {
        $event = $this->createEvent([
            'visibility'     => 'password',
            'event_password' => 'secret',
        ]);

        // Accessing without password should show the password form, not the event detail
        $response = $this->get(route('events.show', $event->slug));

        // The controller returns the password view (still 200, but different template)
        $response->assertStatus(200);
        $response->assertViewIs('public.events.password');
    }

    // ─── View Count ───

    public function test_event_increments_view_count(): void
    {
        $event = $this->createEvent();
        $initialCount = $event->view_count;

        // EventController::show() uses bufferedIncrementView() — counts
        // accumulate in cache and flush to DB every 30 hits (perf opt
        // to avoid 1 UPDATE per pageview). The increment IS happening,
        // just in cache, not in `view_count` until threshold.
        $this->get(route('events.show', $event->slug));

        // Verify the cache buffer was incremented (the production-faithful
        // assertion). Hammering the controller 30× would flush to DB but
        // is fragile under parallel test execution.
        $buffered = (int) \Illuminate\Support\Facades\Cache::get("event_views_buffer:{$event->id}", 0);
        $event->refresh();
        $this->assertSame(
            $initialCount + 1,
            (int) $event->view_count + $buffered,
            'View count should increment somewhere — DB after 30 hits or cache before that',
        );
    }
}
