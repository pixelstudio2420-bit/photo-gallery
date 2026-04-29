<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\BookingSeriesService;
use App\Services\LineNotifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Daylight-saving stress tests for the recurrence engine.
 *
 * Why this matters
 * ----------------
 * BookingSeriesService::generateOccurrences() walks the calendar by
 * `addDay()` and applies a fixed "HH:mm" time on each match. If we
 * cross a DST transition, two failure modes are possible:
 *
 *   1. The "spring forward" hour (e.g. 02:00 doesn't exist in
 *      America/New_York on 2026-03-08). A booking at 02:30 local on
 *      that date would be ambiguous.
 *
 *   2. The "fall back" hour (02:00 happens twice). A booking at
 *      01:30 local on 2026-11-01 in NY exists twice — Carbon picks
 *      one consistently, but the customer might expect the other.
 *
 * The Asia/Bangkok production deployment never observes DST, so this
 * is academic for the current operator. But international rollouts
 * (Europe / North America) would crash into both transitions twice
 * a year. Locking these properties in:
 *
 *   • Weekly series across spring-forward → produces same number
 *     of occurrences as a non-DST week.
 *   • Weekly series across fall-back → likewise.
 *   • Stored UTC differs by exactly the new offset post-transition.
 *   • Local-time-stable: customer's "every Mon at 09:00" stays at
 *     09:00 LOCAL even when their UTC offset shifts.
 */
class BookingSeriesDstTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $photographer;
    private BookingSeriesService $svc;

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
        $line->shouldReceive('queuePushToUser')->andReturn(true)->byDefault();
        $line->shouldReceive('pushText')->andReturn(true)->byDefault();
        $line->shouldReceive('pushToUser')->andReturn(true)->byDefault();
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
            'title'            => 'Weekly DST test',
            'duration_minutes' => 60,
        ];
    }

    /**
     * Why we test generateOccurrences() directly
     * ------------------------------------------
     * The materializer's outer loop is forward-only — it skips
     * occurrences whose scheduled_at is before "now". To stress-test
     * DST math we'd need DST transitions in the future, which depend
     * on the test's wall-clock date. Instead we test the date-math
     * primitive directly: it accepts $from/$to so we can pin any
     * window we want.
     */
    private function rule(string $startsOn, string $until, string $time = '09:00', array $extra = []): array
    {
        return array_merge([
            'freq'      => 'weekly',
            'time'      => $time,
            'starts_on' => $startsOn,
            'until'     => $until,
        ], $extra);
    }

    public function test_weekly_series_survives_spring_forward_in_new_york(): void
    {
        $tz   = 'America/New_York';
        // 2026-03-08 is the spring-forward Sunday in the US (02:00 → 03:00).
        $rule = $this->rule('2026-03-01', '2026-03-22');
        $from = \Carbon\Carbon::parse('2026-02-28', $tz);
        $to   = \Carbon\Carbon::parse('2026-03-23', $tz);

        $occ = $this->svc->generateOccurrences($rule, $tz, $from, $to);

        $this->assertCount(4, $occ, 'must produce 4 Sundays even across DST');
        foreach ($occ as $u) {
            $local = $u->copy()->setTimezone($tz);
            $this->assertSame('09:00', $local->format('H:i'),
                "occurrence {$u} must show 09:00 local in {$tz}");
        }
    }

    public function test_weekly_series_survives_fall_back_in_new_york(): void
    {
        $tz   = 'America/New_York';
        // 2026-11-01 is the fall-back Sunday in the US (02:00 → 01:00).
        $rule = $this->rule('2026-10-25', '2026-11-15');
        $from = \Carbon\Carbon::parse('2026-10-24', $tz);
        $to   = \Carbon\Carbon::parse('2026-11-16', $tz);

        $occ = $this->svc->generateOccurrences($rule, $tz, $from, $to);

        $this->assertCount(4, $occ);
        foreach ($occ as $u) {
            $local = $u->copy()->setTimezone($tz);
            $this->assertSame('09:00', $local->format('H:i'));
        }
    }

    /**
     * UTC offset rotation: 09:00 EST = 14:00 UTC; 09:00 EDT = 13:00 UTC.
     * Pre- vs post-DST occurrences must store DIFFERENT UTC hours.
     * If the engine bakes the start-of-series offset and applies it
     * naively, the assertion would fire (both rows in same UTC hour).
     */
    public function test_utc_storage_reflects_offset_change_post_dst(): void
    {
        $tz   = 'America/New_York';
        $rule = $this->rule('2026-03-01', '2026-03-22');
        $from = \Carbon\Carbon::parse('2026-02-28', $tz);
        $to   = \Carbon\Carbon::parse('2026-03-23', $tz);

        $occ = $this->svc->generateOccurrences($rule, $tz, $from, $to);
        $this->assertGreaterThanOrEqual(2, count($occ));

        $firstUtc = $occ[0]->utc()->format('H');
        $lastUtc  = end($occ)->utc()->format('H');
        $this->assertNotSame(
            $firstUtc,
            $lastUtc,
            sprintf('UTC hour must differ across DST. pre=%s post=%s', $firstUtc, $lastUtc),
        );
    }

    /**
     * Bangkok (no DST) — sanity check that no-DST tz produces stable
     * UTC offset. Guards against breaking the trivial case while
     * "fixing" DST math.
     */
    public function test_bangkok_series_has_stable_utc_offset(): void
    {
        $tz   = 'Asia/Bangkok';
        $rule = $this->rule('2026-06-01', '2026-06-22');
        $from = \Carbon\Carbon::parse('2026-05-31', $tz);
        $to   = \Carbon\Carbon::parse('2026-06-23', $tz);

        $occ = $this->svc->generateOccurrences($rule, $tz, $from, $to);
        $this->assertNotEmpty($occ);

        foreach ($occ as $u) {
            // 09:00 Bangkok (UTC+7) = 02:00 UTC, every time, all year.
            $this->assertSame('02:00', $u->utc()->format('H:i'));
        }
    }

    /**
     * Customer in Asia/Tokyo booking with a Bangkok photographer —
     * the booking should be stored at the correct UTC value AND the
     * customer should see their own local time displayed when the
     * front-end renders. The series's `timezone` field is the
     * authoritative TZ for the rule; UTC storage handles cross-tz
     * customer rendering.
     */
    public function test_cross_timezone_customer_sees_correct_local_when_converting(): void
    {
        $seriesTz = 'Asia/Bangkok';
        $rule = $this->rule('2026-06-01', '2026-06-22', '14:00');
        $from = \Carbon\Carbon::parse('2026-05-31', $seriesTz);
        $to   = \Carbon\Carbon::parse('2026-06-23', $seriesTz);

        $occ = $this->svc->generateOccurrences($rule, $seriesTz, $from, $to);
        $this->assertNotEmpty($occ);

        // 14:00 Bangkok (UTC+7) = 16:00 Tokyo (UTC+9).
        $tokyoLocal = $occ[0]->copy()->setTimezone('Asia/Tokyo');
        $this->assertSame('16:00', $tokyoLocal->format('H:i'),
            'JP customer rendering BKK booking must see 16:00');
    }

    /**
     * Final integration sanity — going through the full create() +
     * materializeOne() pipeline with FUTURE dates so the materializer's
     * forward-only filter doesn't drop everything. Verifies the same
     * tz-correctness through the storage layer.
     */
    public function test_future_dated_series_persists_tz_correct_local_time(): void
    {
        // Use dates 2 years out so this test stays valid for a long time
        // and never collides with "now" past it.
        $tz       = 'Asia/Bangkok';
        $startsOn = now()->addYears(2)->startOfMonth()->next(\Carbon\Carbon::WEDNESDAY);
        $until    = $startsOn->copy()->addWeeks(2);

        $seriesId = $this->svc->create(
            $this->baseData() + ['timezone' => $tz],
            [
                'freq'      => 'weekly',
                'time'      => '15:30',
                'starts_on' => $startsOn->toDateString(),
                'until'     => $until->toDateString(),
            ],
        );
        // Horizon must reach 2 years out.
        $this->svc->materializeOne($seriesId, horizonDays: 800);

        $rows = Booking::where('series_id', $seriesId)->orderBy('scheduled_at')->get();
        $this->assertGreaterThanOrEqual(1, $rows->count());

        foreach ($rows as $row) {
            $local = $row->scheduled_at->copy()->setTimezone($tz);
            $this->assertSame('15:30', $local->format('H:i'),
                'persisted booking must round-trip back to 15:30 in series tz');
        }
    }
}
