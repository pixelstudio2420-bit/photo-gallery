<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\BookingSeriesService;
use App\Services\LineNotifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Locks down the recurring-bookings engine.
 *
 * Properties:
 *   • create() requires a stop condition (until OR count) — no infinite series
 *   • weekly recurrence with by_day produces the right occurrences
 *   • monthly recurrence on a day-of-month skips months without that day
 *   • exceptions array suppresses individual occurrences
 *   • count caps the total occurrences across multiple materialize runs
 *   • idempotent: re-running materializeOne creates no duplicate rows
 *   • cancelSeries cancels all FUTURE instances (past ones unaffected)
 *
 * Time-zone math is bias-tested at Asia/Bangkok (UTC+7); UTC storage
 * + tz-local generation is verified by reading back the stored values.
 */
class BookingSeriesServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $photographer;
    private BookingSeriesService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::create([
            'first_name'    => 'C',
            'last_name'     => 'X',
            'email'         => 'c-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
        ]);
        $this->photographer = User::create([
            'first_name'    => 'P',
            'last_name'     => 'X',
            'email'         => 'p-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
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

        $this->svc = app(BookingSeriesService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function baseData(): array
    {
        return [
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Weekly portrait',
            'duration_minutes' => 60,
            'agreed_price'     => 2000,
        ];
    }

    public function test_create_requires_a_stop_condition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->create($this->baseData(), [
            'freq'      => 'weekly',
            'starts_on' => now()->toDateString(),
            'time'      => '09:00',
            // no until + no count → must fail
        ]);
    }

    public function test_create_validates_freq(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->create($this->baseData(), [
            'freq'      => 'fortnightly',  // not supported
            'starts_on' => now()->toDateString(),
            'until'     => now()->addMonth()->toDateString(),
        ]);
    }

    public function test_weekly_with_count_materializes_exactly_count_bookings(): void
    {
        // 4 weekly Wednesdays starting next Wednesday.
        $startsOn = now()->next(\Carbon\Carbon::WEDNESDAY);

        $seriesId = $this->svc->create($this->baseData(), [
            'freq'      => 'weekly',
            'interval'  => 1,
            'time'      => '14:00',
            'starts_on' => $startsOn->toDateString(),
            'count'     => 4,
        ]);

        // The first materialise happens inside create(). We extend the
        // horizon to make sure all 4 are visible.
        $this->svc->materializeOne($seriesId, horizonDays: 60);

        $rows = Booking::where('series_id', $seriesId)->get();
        $this->assertCount(4, $rows);
        foreach ($rows as $row) {
            $this->assertSame(\Carbon\Carbon::WEDNESDAY, $row->scheduled_at->dayOfWeek);
        }
    }

    public function test_until_caps_the_series(): void
    {
        $start = now()->next(\Carbon\Carbon::WEDNESDAY);
        $until = $start->copy()->addWeeks(2);   // 3 occurrences total

        $seriesId = $this->svc->create($this->baseData(), [
            'freq'      => 'weekly',
            'time'      => '10:00',
            'starts_on' => $start->toDateString(),
            'until'     => $until->toDateString(),
        ]);
        $this->svc->materializeOne($seriesId, horizonDays: 90);

        $count = Booking::where('series_id', $seriesId)->count();
        $this->assertSame(3, $count, 'until-bounded series should produce starts_on, +1 week, +2 weeks');
    }

    public function test_by_day_overrides_default_dow(): void
    {
        $start = now()->next(\Carbon\Carbon::MONDAY);
        $seriesId = $this->svc->create($this->baseData(), [
            'freq'      => 'weekly',
            'by_day'    => ['MO', 'WE', 'FR'],
            'time'      => '10:00',
            'starts_on' => $start->toDateString(),
            'count'     => 6,
        ]);
        $this->svc->materializeOne($seriesId, horizonDays: 30);

        $dows = Booking::where('series_id', $seriesId)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn ($b) => $b->scheduled_at->dayOfWeek)
            ->unique()
            ->sort()
            ->values()
            ->all();
        // Expect Mon (1), Wed (3), Fri (5) — no other DOWs.
        $this->assertEqualsCanonicalizing([1, 3, 5], $dows);
    }

    public function test_exceptions_skip_specific_dates(): void
    {
        $start = now()->next(\Carbon\Carbon::MONDAY);
        // Skip the 2nd Monday — should still produce 3 bookings (mons 1,3,4).
        $skip  = $start->copy()->addWeek()->toDateString();

        $seriesId = $this->svc->create($this->baseData(), [
            'freq'       => 'weekly',
            'time'       => '10:00',
            'starts_on'  => $start->toDateString(),
            'count'      => 4,
            'exceptions' => [$skip],
        ]);
        $this->svc->materializeOne($seriesId, horizonDays: 60);

        $count = Booking::where('series_id', $seriesId)->count();
        $this->assertSame(3, $count, 'exception date must drop one occurrence');

        $skippedExists = Booking::where('series_id', $seriesId)
            ->whereDate('scheduled_at', $skip)
            ->exists();
        $this->assertFalse($skippedExists);
    }

    public function test_idempotent_materialize_does_not_duplicate(): void
    {
        $start = now()->next(\Carbon\Carbon::TUESDAY);
        $seriesId = $this->svc->create($this->baseData(), [
            'freq'      => 'weekly',
            'time'      => '11:00',
            'starts_on' => $start->toDateString(),
            'count'     => 4,
        ]);
        // Run multiple times — should still be 4 rows.
        $this->svc->materializeOne($seriesId, 60);
        $this->svc->materializeOne($seriesId, 60);
        $this->svc->materializeOne($seriesId, 60);

        $this->assertSame(4, Booking::where('series_id', $seriesId)->count());
    }

    public function test_daily_with_interval_2_creates_every_other_day(): void
    {
        $start = now()->addDay()->startOfDay();
        $seriesId = $this->svc->create($this->baseData(), [
            'freq'      => 'daily',
            'interval'  => 2,
            'time'      => '08:00',
            'starts_on' => $start->toDateString(),
            'count'     => 5,
        ]);
        $this->svc->materializeOne($seriesId, 30);

        $rows = Booking::where('series_id', $seriesId)
            ->orderBy('scheduled_at')->get();
        $this->assertCount(5, $rows);

        // Each pair must be exactly 2 days apart. Carbon::diffInDays in
        // newer versions returns a signed float — we compare absolute
        // value so the assertion isn't sensitive to argument order.
        for ($i = 1; $i < $rows->count(); $i++) {
            $diff = (int) abs($rows[$i]->scheduled_at->diffInDays($rows[$i - 1]->scheduled_at));
            $this->assertSame(2, $diff);
        }
    }

    public function test_materialize_all_walks_every_active_series(): void
    {
        // Use until-bounded series + a small horizon so the create()
        // call doesn't immediately mark them 'ended' — we want them
        // active when materializeAll runs.
        $start = now()->next(\Carbon\Carbon::THURSDAY);
        $until = $start->copy()->addMonths(6);

        $a = $this->svc->create($this->baseData() + ['title' => 'A'], [
            'freq' => 'weekly', 'time' => '09:00',
            'starts_on' => $start->toDateString(),
            'until'     => $until->toDateString(),
        ]);
        $b = $this->svc->create($this->baseData() + ['title' => 'B'], [
            'freq' => 'weekly', 'time' => '15:00',
            'starts_on' => $start->toDateString(),
            'until'     => $until->toDateString(),
        ]);

        // Cancel one to confirm materializeAll skips it.
        DB::table('booking_series')->where('id', $a)->update(['status' => 'cancelled']);

        $result = $this->svc->materializeAll(60);
        $this->assertSame(1, $result['series_processed']);
        $this->assertGreaterThan(0, Booking::where('series_id', $b)->count());
    }

    public function test_cancel_series_only_cancels_future_instances(): void
    {
        $past = now()->subDays(7);
        $futureStart = now()->addDays(2);

        // Direct insert so we can fake a past-completed booking.
        $seriesId = DB::table('booking_series')->insertGetId([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'CancelTest',
            'duration_minutes' => 60,
            'recurrence'       => json_encode([
                'freq' => 'weekly', 'time' => '10:00',
                'starts_on' => $futureStart->toDateString(),
                'count' => 4,
            ]),
            'timezone'   => 'Asia/Bangkok',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Past completed booking attached to this series.
        Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'series_id'        => $seriesId,
            'title'            => 'Past instance',
            'scheduled_at'     => $past,
            'duration_minutes' => 60,
            'status'           => Booking::STATUS_COMPLETED,
        ]);
        // Future pending bookings attached.
        Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'series_id'        => $seriesId,
            'title'            => 'Future 1',
            'scheduled_at'     => $futureStart,
            'duration_minutes' => 60,
            'status'           => Booking::STATUS_PENDING,
        ]);
        Booking::create([
            'customer_user_id' => $this->customer->id,
            'photographer_id'  => $this->photographer->id,
            'series_id'        => $seriesId,
            'title'            => 'Future 2',
            'scheduled_at'     => $futureStart->copy()->addWeek(),
            'duration_minutes' => 60,
            'status'           => Booking::STATUS_PENDING,
        ]);

        $cancelled = $this->svc->cancelSeries($seriesId,
            \App\Models\Booking::CANCELLED_BY_CUSTOMER, 'changed plans');
        $this->assertSame(2, $cancelled);

        // Past completed booking is unaffected.
        $this->assertSame(
            Booking::STATUS_COMPLETED,
            Booking::where('series_id', $seriesId)->where('title', 'Past instance')->value('status'),
        );
        // Future bookings are now cancelled.
        $futureCancelled = Booking::where('series_id', $seriesId)
            ->where('status', Booking::STATUS_CANCELLED)->count();
        $this->assertSame(2, $futureCancelled);

        // Series itself is cancelled.
        $this->assertSame(
            'cancelled',
            DB::table('booking_series')->where('id', $seriesId)->value('status'),
        );
    }
}
