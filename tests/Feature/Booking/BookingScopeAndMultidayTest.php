<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\BookingService;
use App\Services\LineNotifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Two contracts that the audit flagged but didn't have explicit tests:
 *
 *   1. Conflict scope only counts pending+confirmed status — a
 *      cancelled / completed / no_show booking does NOT block a new
 *      booking on the same slot.
 *
 *   2. Multi-day bookings (duration spans across midnight) are
 *      handled correctly by the overlapping scope and don't break
 *      the booking lifecycle.
 */
class BookingScopeAndMultidayTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $photographer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::create([
            'first_name' => 'C', 'last_name' => 'X',
            'email' => 'c-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'), 'auth_provider' => 'local',
        ]);
        $this->photographer = User::create([
            'first_name' => 'P', 'last_name' => 'X',
            'email' => 'p-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'), 'auth_provider' => 'local',
        ]);
        PhotographerProfile::create([
            'user_id'           => $this->photographer->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'X',
            'status'            => 'approved',
        ]);

        $line = Mockery::mock(LineNotifyService::class);
        $line->shouldReceive('pushText')->andReturn(true)->byDefault();
        $line->shouldReceive('pushToUser')->andReturn(true)->byDefault();
        $line->shouldReceive('queuePushToUser')->andReturn(true)->byDefault();
        $line->shouldReceive('isMessagingEnabled')->andReturn(false)->byDefault();
        $this->app->instance(LineNotifyService::class, $line);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(): BookingService
    {
        return app(BookingService::class);
    }

    // ─────────────────────────────────────────────────────────────────
    // Conflict scope — only pending+confirmed block new bookings
    // ─────────────────────────────────────────────────────────────────

    public function test_cancelled_booking_does_not_block_a_new_booking_on_same_slot(): void
    {
        $start = now()->addDays(7)->setTime(10, 0);

        $cancelled = Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Was cancelled',
            'scheduled_at'     => $start,
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_CANCELLED,
        ]);

        // New booking on the SAME slot should succeed.
        $new = $this->service()->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'New on same slot',
            'scheduled_at'     => $start,
            'duration_minutes' => 120,
        ]);

        $this->assertNotSame($cancelled->id, $new->id);
        $this->assertSame(Booking::STATUS_PENDING, $new->status);
    }

    public function test_completed_booking_does_not_block_new_booking(): void
    {
        $start = now()->subDays(7)->setTime(10, 0);   // past

        Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Old completed',
            'scheduled_at'     => $start,
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_COMPLETED,
        ]);

        $new = $this->service()->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'New same slot, in past',
            'scheduled_at'     => $start,
            'duration_minutes' => 120,
        ]);
        $this->assertNotNull($new->id);
    }

    public function test_no_show_booking_does_not_block_new_booking(): void
    {
        $start = now()->subDays(2)->setTime(10, 0);

        Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'No show',
            'scheduled_at'     => $start,
            'duration_minutes' => 60,
            'status'           => Booking::STATUS_NO_SHOW,
        ]);

        $new = $this->service()->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Reuse slot',
            'scheduled_at'     => $start,
            'duration_minutes' => 60,
        ]);
        $this->assertNotNull($new->id);
    }

    public function test_pending_booking_blocks_new_booking_on_overlap(): void
    {
        $start = now()->addDays(3)->setTime(10, 0);

        Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Pending guard',
            'scheduled_at'     => $start,
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_PENDING,
        ]);

        $this->expectException(\DomainException::class);
        $this->service()->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Overlapping',
            'scheduled_at'     => $start->copy()->addMinutes(30),
            'duration_minutes' => 60,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Multi-day bookings — duration > 24h
    // ─────────────────────────────────────────────────────────────────

    public function test_multi_day_booking_is_created_and_conflicts_correctly(): void
    {
        // 36-hour shoot starting Friday 22:00 local — runs through Saturday
        // and ends Sunday 10:00. Common case: corporate weekend retreat.
        $start = now()->next(\Carbon\Carbon::FRIDAY)->setTime(22, 0);

        $weekendShoot = $this->service()->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Weekend retreat',
            'scheduled_at'     => $start,
            'duration_minutes' => 36 * 60,
        ]);

        $this->assertNotNull($weekendShoot->id);
        $this->assertSame(2160, (int) $weekendShoot->duration_minutes);

        // A 1-hour booking 6 hours into the multi-day window must be
        // BLOCKED — conflict detection has to handle the wrap-around.
        $this->expectException(\DomainException::class);
        $this->service()->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Conflicting Saturday',
            'scheduled_at'     => $start->copy()->addHours(6),
            'duration_minutes' => 60,
        ]);
    }

    public function test_back_to_back_slots_do_not_count_as_overlap(): void
    {
        // 09:00–10:00 + 10:00–11:00 should both succeed (boundary inclusive
        // end matches inclusive start, but the overlap predicate uses strict
        // inequalities — they don't overlap).
        $first = now()->addDays(2)->setTime(9, 0);
        $a = $this->service()->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'A',
            'scheduled_at'     => $first,
            'duration_minutes' => 60,
        ]);
        $b = $this->service()->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'B',
            'scheduled_at'     => $first->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        $this->assertNotSame($a->id, $b->id);
        $this->assertNotNull($b->id);
    }
}
