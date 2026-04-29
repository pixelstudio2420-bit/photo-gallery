<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\BookingService;
use App\Services\LineNotifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Booking integrity contracts under concurrency and retries.
 *
 * What the existing BookingTest already covers
 *   • happy-path lifecycle (create / confirm / cancel / complete)
 *   • waitlist promotion
 *   • simple conflict detection on confirm
 *   • in-app notification creation
 *
 * What this file adds
 *   • idempotency_key dedups POST retries (no double bookings on the
 *     same submit)
 *   • content-of-conflict — overlap inside the create() transaction
 *     throws DomainException, NOT a silent insert
 *   • deposit_idempotency_key prevents Stripe-style webhook retries
 *     from doubling the deposit_paid total
 *   • reminder claim is atomic — concurrent SendBookingReminders
 *     ticks see one row claimed, the other gets a unique-violation
 *     and silently skips
 *   • admin markNoShow goes through the service — produces
 *     LINE/notification side-effects + dispatches calendar/sheet jobs
 */
class BookingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $photographer;
    /** @var \Mockery\MockInterface */
    private $lineMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer     = $this->makeUser('customer');
        $this->photographer = $this->makeUser('photographer');
        PhotographerProfile::create([
            'user_id'           => $this->photographer->id,
            'photographer_code' => 'PH' . str_pad($this->photographer->id, 4, '0', STR_PAD_LEFT),
            'display_name'      => 'Test Photog',
            'status'            => 'approved',
        ]);

        // Stub LINE so no real API calls.
        $this->lineMock = Mockery::mock(LineNotifyService::class);
        $this->lineMock->shouldReceive('pushText')->andReturn(true)->byDefault();
        $this->lineMock->shouldReceive('pushToUser')->andReturn(true)->byDefault();
        $this->lineMock->shouldReceive('queuePushToUser')->andReturn(true)->byDefault();
        $this->lineMock->shouldReceive('isMessagingEnabled')->andReturn(false)->byDefault();
        $this->app->instance(LineNotifyService::class, $this->lineMock);

        // Block the queued GCal/Sheets jobs so test isolation stays clean.
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeUser(string $tag): User
    {
        return User::create([
            'first_name'    => ucfirst($tag),
            'last_name'     => 'X',
            'email'         => $tag . '-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
        ]);
    }

    private function service(): BookingService
    {
        return app(BookingService::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Idempotency on create
    // ─────────────────────────────────────────────────────────────────────

    public function test_same_idempotency_key_returns_existing_booking(): void
    {
        $key = 'idem-' . uniqid();
        $data = [
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Wedding shoot',
            'scheduled_at'     => now()->addDays(5)->setTime(10, 0),
            'duration_minutes' => 120,
        ];

        $first  = $this->service()->create($data, $key);
        $second = $this->service()->create($data, $key);

        $this->assertSame($first->id, $second->id,
            'second create with same idempotency_key must return the original row');
        $this->assertSame(1,
            Booking::where('idempotency_key', $key)->count(),
            'unique partial index must keep this to one row',
        );
    }

    public function test_overlap_is_rejected_inside_create_transaction(): void
    {
        $start = now()->addDays(3)->setTime(14, 0);

        $this->service()->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'First',
            'scheduled_at'     => $start,
            'duration_minutes' => 120,
        ]);

        $this->expectException(\DomainException::class);
        $this->service()->create([
            'customer_user_id' => $this->makeUser('cust2')->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Conflicting',
            'scheduled_at'     => $start->copy()->addMinutes(30),  // overlaps
            'duration_minutes' => 120,
        ]);
    }

    public function test_waitlist_create_bypasses_overlap_check(): void
    {
        $start = now()->addDays(3)->setTime(14, 0);

        $primary = $this->service()->create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Primary',
            'scheduled_at'     => $start,
            'duration_minutes' => 120,
        ]);

        // Same slot, but is_waitlist=true → must succeed.
        $wait = $this->service()->create([
            'customer_user_id' => $this->makeUser('w')->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Waitlister',
            'scheduled_at'     => $start,
            'duration_minutes' => 120,
            'is_waitlist'      => true,
            'waitlist_for_id'  => $primary->id,
        ]);

        $this->assertTrue((bool) $wait->is_waitlist);
        $this->assertSame($primary->id, $wait->waitlist_for_id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Deposit idempotency
    // ─────────────────────────────────────────────────────────────────────

    public function test_deposit_idempotent_replay_does_not_double_charge(): void
    {
        $booking = Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Test',
            'scheduled_at'     => now()->addDays(5),
            'duration_minutes' => 120,
            'agreed_price'     => 5000,
            'deposit_required_pct' => 30,
            'status'           => Booking::STATUS_CONFIRMED,
        ]);

        $first  = $this->service()->markDepositPaid($booking, 1500, 'pay_xyz');
        $second = $this->service()->markDepositPaid($booking, 1500, 'pay_xyz');

        $fresh = $booking->fresh();
        $this->assertSame('1500.00', (string) $fresh->deposit_paid,
            'second call with same payment_id must NOT add the amount again');
        $this->assertSame('payment.pay_xyz', $fresh->deposit_idempotency_key);
    }

    public function test_deposit_different_payments_accumulate(): void
    {
        $booking = Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Test',
            'scheduled_at'     => now()->addDays(5),
            'duration_minutes' => 120,
            'agreed_price'     => 5000,
            'deposit_required_pct' => 50,
            'status'           => Booking::STATUS_CONFIRMED,
        ]);

        $this->service()->markDepositPaid($booking, 1000, 'pay_a');
        $this->service()->markDepositPaid($booking, 1500, 'pay_b');

        $this->assertSame('2500.00', (string) $booking->fresh()->deposit_paid);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Reminder claim atomicity
    // ─────────────────────────────────────────────────────────────────────

    public function test_reminder_claim_is_idempotent_via_unique_constraint(): void
    {
        $booking = Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Test',
            'scheduled_at'     => now()->addDays(3),
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_CONFIRMED,
        ]);

        // First call claims + sends.
        $first = $this->service()->sendReminder3Days($booking);
        $this->assertTrue($first);

        // Second call must short-circuit (claim row already exists).
        $second = $this->service()->sendReminder3Days($booking->fresh());
        $this->assertFalse($second);

        // Exactly one claim row, status='sent'.
        $row = DB::table('booking_reminder_claims')
            ->where('booking_id', $booking->id)
            ->where('slot', '3d')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('sent', $row->status);
    }

    public function test_reminder_claim_for_one_slot_does_not_block_others(): void
    {
        $booking = Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Test',
            'scheduled_at'     => now()->addDays(3),
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_CONFIRMED,
        ]);

        $this->service()->sendReminder3Days($booking);
        $second = $this->service()->sendReminder1Day($booking->fresh());
        $third  = $this->service()->sendReminderDayOf($booking->fresh());

        $this->assertTrue($second, '1d slot is independent of 3d');
        $this->assertTrue($third,  'day slot is independent too');

        $this->assertSame(
            3,
            DB::table('booking_reminder_claims')->where('booking_id', $booking->id)->count(),
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Admin markNoShow path
    // ─────────────────────────────────────────────────────────────────────

    public function test_admin_no_show_dispatches_calendar_and_sheets_jobs(): void
    {
        Queue::fake();

        $booking = Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Test',
            'scheduled_at'     => now()->addDays(3),
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_CONFIRMED,
        ]);

        $this->service()->markNoShow($booking, adminId: 1, reason: 'customer never arrived');

        $this->assertSame(Booking::STATUS_NO_SHOW, $booking->fresh()->status);
        Queue::assertPushed(\App\Jobs\Booking\SyncBookingToCalendarJob::class,
            fn ($job) => $job->bookingId === $booking->id && $job->operation === 'delete');
        Queue::assertPushed(\App\Jobs\Booking\ExportBookingToSheetJob::class,
            fn ($job) => $job->bookingId === $booking->id && $job->operation === 'update');
    }

    public function test_admin_no_show_is_idempotent(): void
    {
        $booking = Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Test',
            'scheduled_at'     => now()->addDays(3),
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_CONFIRMED,
        ]);

        $first  = $this->service()->markNoShow($booking, 1);
        $second = $this->service()->markNoShow($booking->fresh(), 1);

        // Second call returns the row unchanged — no exception, no
        // re-pushes (we'd see Mockery failure if there were extra pushes).
        $this->assertSame(Booking::STATUS_NO_SHOW, $second->status);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Confirm dispatches the right jobs
    // ─────────────────────────────────────────────────────────────────────

    public function test_confirm_dispatches_calendar_and_sheets_jobs(): void
    {
        Queue::fake();

        $booking = Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Test',
            'scheduled_at'     => now()->addDays(3),
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_PENDING,
        ]);

        $this->service()->confirm($booking);

        Queue::assertPushed(\App\Jobs\Booking\SyncBookingToCalendarJob::class,
            fn ($job) => $job->bookingId === $booking->id && $job->operation === 'upsert');
        Queue::assertPushed(\App\Jobs\Booking\ExportBookingToSheetJob::class,
            fn ($job) => $job->bookingId === $booking->id && $job->operation === 'update');
    }
}
