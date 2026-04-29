<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PhotographerAvailability;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\GoogleCalendarSyncService;
use App\Services\LineNotifyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * Phase 1 + Phase 2 booking system coverage.
 *
 * The Booking model uses Postgres-specific raw SQL in `scopeOverlapping`
 * (`(duration_minutes || ' minutes')::interval`) so these tests run against
 * the Postgres test database, not SQLite. Use:
 *   DB_CONNECTION=pgsql DB_DATABASE=jabphap_test php artisan test --filter=BookingTest
 *
 * LineNotifyService is mocked so tests don't try to push to LINE.
 */
class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $photographer;
    private PhotographerProfile $profile;
    /** @var \Mockery\MockInterface */
    private $lineMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer     = $this->makeUser('customer');
        $this->photographer = $this->makeUser('photographer');

        $this->profile = PhotographerProfile::create([
            'user_id'           => $this->photographer->id,
            'photographer_code' => 'PH' . str_pad($this->photographer->id, 4, '0', STR_PAD_LEFT),
            'display_name'      => 'Test Photographer',
            'status'            => 'approved',
        ]);

        // Block the LINE API — every push call becomes a no-op so we don't
        // hit api.line.me during tests.
        $this->lineMock = Mockery::mock(LineNotifyService::class);
        $this->lineMock->shouldReceive('pushText')->andReturn(true)->byDefault();
        $this->lineMock->shouldReceive('pushToUser')->andReturn(true)->byDefault();
        $this->lineMock->shouldReceive('isMessagingEnabled')->andReturn(false)->byDefault();
        $this->app->instance(LineNotifyService::class, $this->lineMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function makeUser(string $kind): User
    {
        return User::create([
            'first_name'    => ucfirst($kind),
            'last_name'     => 'User',
            'email'         => $kind . '-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);
    }

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Sample Shoot',
            'scheduled_at'     => now()->addDays(7)->setTime(10, 0),
            'duration_minutes' => 120,
            'agreed_price'     => 5000,
            'status'           => Booking::STATUS_PENDING,
        ], $overrides));
    }

    private function bookingService(): BookingService
    {
        // Resolve a fresh BookingService that uses our mocked LineNotifyService
        // and a real AvailabilityService. The GCal service is bound to a stub
        // returning null/false so we don't hit the Google API.
        $gcal = Mockery::mock(GoogleCalendarSyncService::class);
        $gcal->shouldReceive('upsertBookingOnCalendar')->andReturn(null)->byDefault();
        $gcal->shouldReceive('removeBookingFromCalendar')->andReturn(false)->byDefault();
        $this->app->instance(GoogleCalendarSyncService::class, $gcal);

        return new BookingService(
            $this->lineMock,
            app(AvailabilityService::class),
            $gcal,
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    // Phase 1 — Lifecycle (create / confirm / cancel / complete)
    // ═════════════════════════════════════════════════════════════════════

    public function test_customer_can_create_booking(): void
    {
        $svc = $this->bookingService();

        $booking = $svc->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Family Portrait',
            'scheduled_at'     => now()->addDays(10)->setTime(14, 0),
            'agreed_price'     => 3000,
        ]);

        $this->assertSame(Booking::STATUS_PENDING, $booking->status);
        $this->assertSame(120, (int) $booking->duration_minutes, 'default duration is 120 minutes');
        $this->assertNotNull($booking->id);
        $this->assertSame('Family Portrait', $booking->title);
    }

    public function test_creating_booking_creates_in_app_notification_for_photographer(): void
    {
        $svc = $this->bookingService();

        $booking = $svc->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Wedding Shoot',
            'scheduled_at'     => now()->addDays(14),
        ]);

        $note = UserNotification::where('user_id', $this->photographer->id)
            ->where('type', 'booking_new')
            ->first();

        $this->assertNotNull($note, 'photographer should receive in-app booking_new notification');
        $this->assertStringContainsString('Wedding Shoot', $note->message);
        $this->assertSame('booking:' . $booking->id, $note->ref_id);
    }

    public function test_creating_booking_pushes_line_to_photographer(): void
    {
        $captured = [];
        $this->lineMock->shouldReceive('pushText')
            ->andReturnUsing(function ($userId, $text) use (&$captured) {
                $captured[] = ['user_id' => $userId, 'text' => $text];
                return true;
            });

        $svc = $this->bookingService();
        $svc->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Portrait',
            'scheduled_at'     => now()->addDays(5),
        ]);

        $pushedToPhotographer = collect($captured)->where('user_id', $this->photographer->id)->first();
        $this->assertNotNull($pushedToPhotographer, 'LINE push should target the photographer');
    }

    public function test_photographer_can_confirm_pending_booking(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking();

        $svc->confirm($booking);

        $booking->refresh();
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertNotNull($booking->confirmed_at);
    }

    public function test_confirming_booking_creates_in_app_notification_for_customer(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking();

        $svc->confirm($booking);

        $note = UserNotification::where('user_id', $this->customer->id)
            ->where('type', 'booking_confirmed')
            ->first();

        $this->assertNotNull($note, 'customer should receive booking_confirmed notification');
    }

    public function test_confirm_throws_on_overlapping_booking(): void
    {
        $svc = $this->bookingService();

        $startsAt = now()->addDays(7)->setTime(10, 0);

        // Existing confirmed booking from 10:00 to 12:00
        $first = $this->makeBooking([
            'scheduled_at'     => $startsAt,
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_CONFIRMED,
            'confirmed_at'     => now(),
        ]);

        // Overlapping pending booking 11:00–13:00 — half overlap
        $second = $this->makeBooking([
            'scheduled_at'     => (clone $startsAt)->addHour(),
            'duration_minutes' => 120,
            'customer_user_id' => $this->makeUser('cust2')->id,
        ]);

        $this->expectException(\DomainException::class);
        $svc->confirm($second);
    }

    public function test_confirm_throws_when_booking_is_not_pending(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $this->expectException(\DomainException::class);
        $svc->confirm($booking);
    }

    public function test_customer_can_cancel_booking(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $svc->cancel($booking, Booking::CANCELLED_BY_CUSTOMER, 'Schedule conflict');

        $booking->refresh();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame(Booking::CANCELLED_BY_CUSTOMER, $booking->cancelled_by);
        $this->assertSame('Schedule conflict', $booking->cancellation_reason);
    }

    public function test_cancel_pushes_line_to_counterpart(): void
    {
        $captured = [];
        $this->lineMock->shouldReceive('pushText')
            ->andReturnUsing(function ($userId, $text) use (&$captured) {
                $captured[] = ['user_id' => $userId, 'text' => $text];
                return true;
            });

        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $svc->cancel($booking, Booking::CANCELLED_BY_CUSTOMER);

        // When customer cancels, photographer should be notified
        $pushedToPhotographer = collect($captured)
            ->where('user_id', $this->photographer->id)
            ->first();
        $this->assertNotNull($pushedToPhotographer, 'photographer should be notified when customer cancels');
    }

    public function test_cannot_cancel_already_cancelled_booking(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $this->expectException(\DomainException::class);
        $svc->cancel($booking, Booking::CANCELLED_BY_CUSTOMER);
    }

    public function test_cannot_cancel_completed_booking(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $this->expectException(\DomainException::class);
        $svc->cancel($booking, Booking::CANCELLED_BY_CUSTOMER);
    }

    public function test_photographer_can_complete_confirmed_booking(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $svc->complete($booking);

        $booking->refresh();
        $this->assertSame(Booking::STATUS_COMPLETED, $booking->status);
        $this->assertNotNull($booking->completed_at);
    }

    public function test_cannot_complete_unconfirmed_booking(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking(); // status=pending

        $this->expectException(\DomainException::class);
        $svc->complete($booking);
    }

    // ═════════════════════════════════════════════════════════════════════
    // Phase 1 — Booking::overlapping scope
    // ═════════════════════════════════════════════════════════════════════

    public function test_overlapping_scope_finds_true_overlap(): void
    {
        $start = now()->addDays(7)->setTime(10, 0);

        $existing = $this->makeBooking([
            'scheduled_at'     => $start,
            'duration_minutes' => 120, // 10:00–12:00
            'status'           => Booking::STATUS_CONFIRMED,
            'confirmed_at'     => now(),
        ]);

        // Window 11:00–13:00 partially overlaps existing 10:00–12:00.
        $overlapping = Booking::overlapping(
            $this->photographer->id,
            (clone $start)->addHour(),
            (clone $start)->addHours(3),
        )->get();

        $this->assertTrue($overlapping->contains('id', $existing->id),
            'overlapping window should match existing booking');
    }

    public function test_overlapping_scope_excludes_self_via_exclude_id(): void
    {
        $start = now()->addDays(7)->setTime(10, 0);

        $booking = $this->makeBooking([
            'scheduled_at'     => $start,
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_CONFIRMED,
            'confirmed_at'     => now(),
        ]);

        $hits = Booking::overlapping(
            $this->photographer->id,
            $start,
            (clone $start)->addHours(2),
            $booking->id, // exclude self
        )->get();

        $this->assertFalse($hits->contains('id', $booking->id),
            'self should be excluded from overlap check via excludeId');
    }

    public function test_overlapping_scope_skips_cancelled_bookings(): void
    {
        $start = now()->addDays(7)->setTime(10, 0);

        $cancelled = $this->makeBooking([
            'scheduled_at'     => $start,
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_CANCELLED,
            'cancelled_at'     => now(),
        ]);

        $hits = Booking::overlapping(
            $this->photographer->id,
            $start,
            (clone $start)->addHours(2),
        )->get();

        $this->assertFalse($hits->contains('id', $cancelled->id),
            'cancelled bookings should not count as conflicts');
    }

    // ═════════════════════════════════════════════════════════════════════
    // Phase 1 — Status accessors / scopes
    // ═════════════════════════════════════════════════════════════════════

    public function test_color_attribute_matches_status(): void
    {
        $colors = [
            Booking::STATUS_PENDING   => '#f59e0b',
            Booking::STATUS_CONFIRMED => '#10b981',
            Booking::STATUS_COMPLETED => '#6366f1',
            Booking::STATUS_CANCELLED => '#ef4444',
            Booking::STATUS_NO_SHOW   => '#6b7280',
        ];

        foreach ($colors as $status => $expected) {
            $b = new Booking(['status' => $status]);
            $this->assertSame($expected, $b->color, "color for {$status} status");
        }
    }

    public function test_status_label_attribute_returns_thai(): void
    {
        $expected = [
            Booking::STATUS_PENDING   => 'รอยืนยัน',
            Booking::STATUS_CONFIRMED => 'ยืนยันแล้ว',
            Booking::STATUS_COMPLETED => 'เสร็จสิ้น',
            Booking::STATUS_CANCELLED => 'ยกเลิก',
            Booking::STATUS_NO_SHOW   => 'ไม่มาตามนัด',
        ];

        foreach ($expected as $status => $label) {
            $b = new Booking(['status' => $status]);
            $this->assertSame($label, $b->status_label);
        }
    }

    public function test_party_scopes_filter_by_user_id(): void
    {
        $otherCust = $this->makeUser('otherCust');
        $otherPg   = $this->makeUser('otherPg');

        $mine        = $this->makeBooking();
        $otherCustB  = $this->makeBooking(['customer_user_id' => $otherCust->id]);
        $otherPgB    = $this->makeBooking(['photographer_id' => $otherPg->id]);

        $byCustomer = Booking::forCustomer($this->customer->id)->get();
        $this->assertTrue($byCustomer->contains('id', $mine->id));
        $this->assertFalse($byCustomer->contains('id', $otherCustB->id));

        $byPhotographer = Booking::forPhotographer($this->photographer->id)->get();
        $this->assertTrue($byPhotographer->contains('id', $mine->id));
        $this->assertFalse($byPhotographer->contains('id', $otherPgB->id));
    }

    public function test_upcoming_scope_returns_only_future_pending_or_confirmed(): void
    {
        $past   = $this->makeBooking([
            'scheduled_at' => now()->subDays(5),
            'status'       => Booking::STATUS_COMPLETED,
            'completed_at' => now()->subDays(5),
        ]);
        $future = $this->makeBooking([
            'scheduled_at' => now()->addDays(3),
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
        $cancelledFuture = $this->makeBooking([
            'scheduled_at' => now()->addDays(4),
            'status'       => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $rows = Booking::upcoming()->get();

        $this->assertTrue($rows->contains('id', $future->id));
        $this->assertFalse($rows->contains('id', $past->id));
        $this->assertFalse($rows->contains('id', $cancelledFuture->id),
            'cancelled future bookings are not "upcoming"');
    }

    public function test_past_scope_returns_only_past_bookings(): void
    {
        $past   = $this->makeBooking([
            'scheduled_at' => now()->subDays(2),
            'status'       => Booking::STATUS_COMPLETED,
            'completed_at' => now()->subDays(2),
        ]);
        $future = $this->makeBooking([
            'scheduled_at' => now()->addDays(2),
        ]);

        $rows = Booking::past()->get();

        $this->assertTrue($rows->contains('id', $past->id));
        $this->assertFalse($rows->contains('id', $future->id));
    }

    // ═════════════════════════════════════════════════════════════════════
    // Phase 2 — Waitlist
    // ═════════════════════════════════════════════════════════════════════

    public function test_add_to_waitlist_creates_booking_with_waitlist_flag(): void
    {
        $primary = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $svc = $this->bookingService();

        $other = $this->makeUser('cust2');
        $waitlister = $svc->addToWaitlist([
            'customer_user_id' => $other->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Waitlist hopeful',
            'scheduled_at'     => $primary->scheduled_at,
        ], $primary->id);

        $this->assertTrue((bool) $waitlister->is_waitlist);
        $this->assertSame($primary->id, (int) $waitlister->waitlist_for_id);
        $this->assertSame(Booking::STATUS_PENDING, $waitlister->status);
    }

    public function test_waitlist_promotes_oldest_when_primary_cancelled(): void
    {
        $svc = $this->bookingService();

        $primary = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $cust2 = $this->makeUser('w1');
        $cust3 = $this->makeUser('w2');

        // Two waitlisters in chronological order
        $waiter1 = $svc->addToWaitlist([
            'customer_user_id' => $cust2->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Waiter 1',
            'scheduled_at'     => $primary->scheduled_at,
        ], $primary->id);

        // Force created_at order (sleep alternative)
        \DB::table('bookings')->where('id', $waiter1->id)
            ->update(['created_at' => now()->subMinutes(10)]);

        $waiter2 = $svc->addToWaitlist([
            'customer_user_id' => $cust3->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Waiter 2',
            'scheduled_at'     => $primary->scheduled_at,
        ], $primary->id);

        // Now cancel primary — waiter1 (oldest) should be promoted
        $svc->cancel($primary, Booking::CANCELLED_BY_CUSTOMER);

        $waiter1->refresh();
        $waiter2->refresh();

        $this->assertFalse((bool) $waiter1->is_waitlist, 'oldest waitlister becomes primary');
        $this->assertNull($waiter1->waitlist_for_id);
        $this->assertNotNull($waiter1->promoted_from_waitlist_at);

        $this->assertTrue((bool) $waiter2->is_waitlist, 'second waitlister still waiting');
    }

    public function test_is_waitlist_defaults_to_false_strict_equal(): void
    {
        // The model's $attributes default + boolean cast should make
        // a freshly created Booking strict-equal false on is_waitlist —
        // not null, not 0.
        $booking = $this->makeBooking();

        $this->assertSame(false, $booking->is_waitlist,
            'is_waitlist should be strict-equal false on a fresh booking');
    }

    public function test_waitlist_scope_returns_only_waitlist_entries(): void
    {
        $primary    = $this->makeBooking();
        $svc        = $this->bookingService();
        $other      = $this->makeUser('w1');
        $waitlister = $svc->addToWaitlist([
            'customer_user_id' => $other->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Hopeful',
            'scheduled_at'     => $primary->scheduled_at,
        ], $primary->id);

        $rows = Booking::waitlist()->get();
        $this->assertTrue($rows->contains('id', $waitlister->id));
        $this->assertFalse($rows->contains('id', $primary->id));
    }

    // ═════════════════════════════════════════════════════════════════════
    // Phase 2 — Deposit flow
    // ═════════════════════════════════════════════════════════════════════

    public function test_mark_deposit_paid_records_payment(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'agreed_price'         => 10000,
            'deposit_required_pct' => 30,
            'status'               => Booking::STATUS_CONFIRMED,
            'confirmed_at'         => now(),
        ]);

        $svc->markDepositPaid($booking, 3000, 'pi_test_abc123');

        $booking->refresh();
        $this->assertEquals(3000, (float) $booking->deposit_paid);
        $this->assertNotNull($booking->deposit_paid_at);
        $this->assertSame('pi_test_abc123', $booking->deposit_payment_id);
    }

    public function test_mark_deposit_paid_accumulates_multiple_payments(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'agreed_price'         => 10000,
            'deposit_required_pct' => 50,
            'status'               => Booking::STATUS_CONFIRMED,
            'confirmed_at'         => now(),
        ]);

        $svc->markDepositPaid($booking, 2000, 'pay1');
        $svc->markDepositPaid($booking->fresh(), 3000, 'pay2');

        $booking->refresh();
        $this->assertEquals(5000, (float) $booking->deposit_paid,
            'deposit_paid should accumulate across multiple payments');
    }

    public function test_deposit_amount_accessor_calculates_pct_of_price(): void
    {
        $required = $this->makeBooking([
            'agreed_price'         => 10000,
            'deposit_required_pct' => 30,
        ]);
        $this->assertEquals(3000.0, (float) $required->deposit_amount,
            '30% of 10000 = 3000');

        // Edge case: zero pct returns 0, not NaN.
        $optional = $this->makeBooking([
            'agreed_price'         => 10000,
            'deposit_required_pct' => 0,
        ]);
        $this->assertEquals(0.0, (float) $optional->deposit_amount);
    }

    public function test_is_deposit_required_requires_both_pct_and_price(): void
    {
        $required = $this->makeBooking([
            'agreed_price'         => 5000,
            'deposit_required_pct' => 30,
        ]);
        $this->assertTrue($required->isDepositRequired());

        $noPrice = $this->makeBooking([
            'agreed_price'         => 0,
            'deposit_required_pct' => 30,
        ]);
        $this->assertFalse($noPrice->isDepositRequired());

        $noPct = $this->makeBooking([
            'agreed_price'         => 5000,
            'deposit_required_pct' => 0,
        ]);
        $this->assertFalse($noPct->isDepositRequired());
    }

    public function test_is_deposit_paid_handles_required_and_optional(): void
    {
        // Required + 0 paid → false
        $unpaid = $this->makeBooking([
            'agreed_price'         => 10000,
            'deposit_required_pct' => 30,
        ]);
        $this->assertFalse($unpaid->isDepositPaid());

        // No deposit required → true
        $optional = $this->makeBooking([
            'agreed_price'         => 5000,
            'deposit_required_pct' => 0,
        ]);
        $this->assertTrue($optional->isDepositPaid());

        // Required + paid → true
        $svc  = $this->bookingService();
        $paid = $this->makeBooking([
            'agreed_price'         => 10000,
            'deposit_required_pct' => 30,
            'status'               => Booking::STATUS_CONFIRMED,
            'confirmed_at'         => now(),
        ]);
        $svc->markDepositPaid($paid, 3000);
        $this->assertTrue($paid->fresh()->isDepositPaid());
    }

    // ═════════════════════════════════════════════════════════════════════
    // Phase 2 — Map picker fields persisted
    // ═════════════════════════════════════════════════════════════════════

    public function test_lat_lng_persisted_as_decimal_seven(): void
    {
        $booking = $this->makeBooking([
            'location_lat' => 13.7563309,
            'location_lng' => 100.5017651,
        ]);

        $fresh = Booking::find($booking->id);
        // The decimal:7 cast keeps 7 places of precision.
        $this->assertEqualsWithDelta(13.7563309, (float) $fresh->location_lat, 0.0000001);
        $this->assertEqualsWithDelta(100.5017651, (float) $fresh->location_lng, 0.0000001);
    }

    // ═════════════════════════════════════════════════════════════════════
    // Phase 2 — AvailabilityService
    // ═════════════════════════════════════════════════════════════════════

    public function test_window_available_when_no_rules_exist(): void
    {
        // Fresh photographer with no availability rules → 24/7 available
        $svc = app(AvailabilityService::class);

        $start = now()->addDays(2)->setTime(10, 0);
        $end   = (clone $start)->addHours(2);

        $this->assertTrue($svc->isWindowAvailable($this->photographer->id, $start, $end));
    }

    public function test_window_available_when_inside_open_hours(): void
    {
        // Mon–Sun all available 09:00–17:00
        for ($dow = 0; $dow <= 6; $dow++) {
            PhotographerAvailability::create([
                'photographer_id' => $this->photographer->id,
                'type'            => PhotographerAvailability::TYPE_RECURRING,
                'day_of_week'     => $dow,
                'time_start'      => '09:00:00',
                'time_end'        => '17:00:00',
                'effect'          => PhotographerAvailability::EFFECT_AVAILABLE,
            ]);
        }

        $svc   = app(AvailabilityService::class);
        $start = Carbon::parse('next Monday')->setTime(10, 0);
        $end   = (clone $start)->addHours(2);

        $this->assertTrue($svc->isWindowAvailable($this->photographer->id, $start, $end));
    }

    public function test_window_blocked_when_blocked_rule_overlaps(): void
    {
        // Open Mon–Sun 09:00–17:00, plus a holiday on a future date
        for ($dow = 0; $dow <= 6; $dow++) {
            PhotographerAvailability::create([
                'photographer_id' => $this->photographer->id,
                'type'            => PhotographerAvailability::TYPE_RECURRING,
                'day_of_week'     => $dow,
                'time_start'      => '09:00:00',
                'time_end'        => '17:00:00',
                'effect'          => PhotographerAvailability::EFFECT_AVAILABLE,
            ]);
        }
        $holiday = Carbon::parse('next Monday');
        PhotographerAvailability::create([
            'photographer_id' => $this->photographer->id,
            'type'            => PhotographerAvailability::TYPE_OVERRIDE,
            'specific_date'   => $holiday->toDateString(),
            'time_start'      => '00:00:00',
            'time_end'        => '23:59:59',
            'effect'          => PhotographerAvailability::EFFECT_BLOCKED,
        ]);

        $svc = app(AvailabilityService::class);
        $start = (clone $holiday)->setTime(10, 0);
        $end   = (clone $start)->addHours(2);

        $this->assertFalse($svc->isWindowAvailable($this->photographer->id, $start, $end));
    }

    public function test_moment_blocked_wins_over_available_for_lunch_break(): void
    {
        // 09:00-17:00 available, 12:00-13:00 blocked → 12:30 should be unavailable
        $monday = 1;
        PhotographerAvailability::create([
            'photographer_id' => $this->photographer->id,
            'type'            => PhotographerAvailability::TYPE_RECURRING,
            'day_of_week'     => $monday,
            'time_start'      => '09:00:00',
            'time_end'        => '17:00:00',
            'effect'          => PhotographerAvailability::EFFECT_AVAILABLE,
        ]);
        PhotographerAvailability::create([
            'photographer_id' => $this->photographer->id,
            'type'            => PhotographerAvailability::TYPE_RECURRING,
            'day_of_week'     => $monday,
            'time_start'      => '12:00:00',
            'time_end'        => '13:00:00',
            'effect'          => PhotographerAvailability::EFFECT_BLOCKED,
            'label'           => 'Lunch',
        ]);

        $svc        = app(AvailabilityService::class);
        $lunchTime  = Carbon::parse('next Monday')->setTime(12, 30);
        $morningTime= Carbon::parse('next Monday')->setTime(10, 30);

        $this->assertFalse($svc->isMomentAvailable($this->photographer->id, $lunchTime),
            '12:30 falls in the blocked lunch carve-out');
        $this->assertTrue($svc->isMomentAvailable($this->photographer->id, $morningTime),
            '10:30 is inside the open window and outside the lunch block');
    }

    public function test_photographer_availability_scopes(): void
    {
        $otherPg = $this->makeUser('otherPg');

        $mine = PhotographerAvailability::create([
            'photographer_id' => $this->photographer->id,
            'type'            => PhotographerAvailability::TYPE_RECURRING,
            'day_of_week'     => 1,
            'time_start'      => '09:00:00',
            'time_end'        => '17:00:00',
            'effect'          => PhotographerAvailability::EFFECT_AVAILABLE,
        ]);
        $override = PhotographerAvailability::create([
            'photographer_id' => $this->photographer->id,
            'type'            => PhotographerAvailability::TYPE_OVERRIDE,
            'specific_date'   => now()->addDays(5)->toDateString(),
            'time_start'      => '00:00:00',
            'time_end'        => '23:59:59',
            'effect'          => PhotographerAvailability::EFFECT_BLOCKED,
        ]);
        $other = PhotographerAvailability::create([
            'photographer_id' => $otherPg->id,
            'type'            => PhotographerAvailability::TYPE_RECURRING,
            'day_of_week'     => 1,
            'time_start'      => '09:00:00',
            'time_end'        => '17:00:00',
            'effect'          => PhotographerAvailability::EFFECT_AVAILABLE,
        ]);

        // forPhotographer scope only returns this photographer's rules.
        $rows = PhotographerAvailability::forPhotographer($this->photographer->id)->get();
        $this->assertTrue($rows->contains('id', $mine->id));
        $this->assertTrue($rows->contains('id', $override->id));
        $this->assertFalse($rows->contains('id', $other->id));

        // recurring() / override() type filters
        $recurring = PhotographerAvailability::forPhotographer($this->photographer->id)->recurring()->get();
        $overrides = PhotographerAvailability::forPhotographer($this->photographer->id)->override()->get();
        $this->assertTrue($recurring->contains('id', $mine->id));
        $this->assertFalse($recurring->contains('id', $override->id));
        $this->assertTrue($overrides->contains('id', $override->id));
    }

    // ═════════════════════════════════════════════════════════════════════
    // Phase 2 — GoogleCalendarSync graceful no-op
    // ═════════════════════════════════════════════════════════════════════

    public function test_gcal_upsert_returns_null_when_not_configured(): void
    {
        // Don't set google_client_id — service should silently skip.
        $svc = new GoogleCalendarSyncService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $this->assertNull($svc->upsertBookingOnCalendar($booking));
    }

    public function test_gcal_remove_returns_false_when_no_event_id(): void
    {
        $svc = new GoogleCalendarSyncService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            // gcal_event_id is null
        ]);

        $this->assertFalse($svc->removeBookingFromCalendar($booking));
    }

    // ═════════════════════════════════════════════════════════════════════
    // Phase 1 — Reminder dispatch (idempotent)
    // ═════════════════════════════════════════════════════════════════════

    public function test_send_reminder_3_days_is_idempotent(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'scheduled_at' => now()->addDays(3),
        ]);

        $first  = $svc->sendReminder3Days($booking);
        $second = $svc->sendReminder3Days($booking->fresh());

        $this->assertTrue($first, 'first call sends');
        $this->assertFalse($second, 'second call is a no-op');
        $this->assertNotNull($booking->fresh()->reminder_3d_sent_at);
    }

    public function test_send_reminder_1_day_is_idempotent(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'scheduled_at' => now()->addDay(),
        ]);

        $this->assertTrue($svc->sendReminder1Day($booking));
        $this->assertFalse($svc->sendReminder1Day($booking->fresh()));
        $this->assertNotNull($booking->fresh()->reminder_1d_sent_at);
    }

    public function test_send_reminder_1_hour_is_idempotent(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'scheduled_at' => now()->addHour(),
        ]);

        $this->assertTrue($svc->sendReminder1Hour($booking));
        $this->assertFalse($svc->sendReminder1Hour($booking->fresh()));
        $this->assertNotNull($booking->fresh()->reminder_1h_sent_at);
    }

    public function test_send_reminder_day_of_is_idempotent(): void
    {
        $svc     = $this->bookingService();
        $booking = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'scheduled_at' => now()->addHours(4),
        ]);

        $this->assertTrue($svc->sendReminderDayOf($booking));
        $this->assertFalse($svc->sendReminderDayOf($booking->fresh()));
        $this->assertNotNull($booking->fresh()->reminder_day_sent_at);
    }

    public function test_send_post_shoot_review_prompt_is_idempotent_and_requires_completed(): void
    {
        $svc = $this->bookingService();

        // Not completed → returns false
        $confirmed = $this->makeBooking([
            'status'       => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
        $this->assertFalse($svc->sendPostShootReviewPrompt($confirmed));
        $this->assertNull($confirmed->fresh()->post_shoot_review_sent_at);

        // Completed → returns true once, then false
        $completed = $this->makeBooking([
            'status'       => Booking::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        $this->assertTrue($svc->sendPostShootReviewPrompt($completed));
        $this->assertFalse($svc->sendPostShootReviewPrompt($completed->fresh()));
        $this->assertNotNull($completed->fresh()->post_shoot_review_sent_at);
    }
}
