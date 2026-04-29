<?php

namespace Tests\Feature\Booking;

use App\Jobs\Booking\SyncBookingToCalendarJob;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * Locks down the GCal sync job's audit + retry contract.
 *
 *   • upsert success → succeeded row, gcal_event_id captured
 *   • upsert null + GCal disabled → skipped row, no throw, no retry
 *   • upsert null + GCal enabled  → failed row + RuntimeException
 *     (queue retries on the throw)
 *   • delete success → succeeded
 *   • delete with no event id → service returns false → skipped
 *   • failed() hook clears any 'pending' row to 'failed'
 */
class SyncBookingToCalendarJobTest extends TestCase
{
    use RefreshDatabase;

    private User $photographer;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
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
        $customer = User::create([
            'first_name'    => 'C', 'last_name' => 'X',
            'email'         => 'c-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
        ]);
        $this->booking = Booking::create([
            'customer_user_id' => $customer->id,
            'photographer_id'  => $this->photographer->id,
            'title'            => 'Sync test',
            'scheduled_at'     => now()->addDays(2),
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_CONFIRMED,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function fakeGcalLinked(): void
    {
        // Simulate the photographer having linked Google + admin enabled.
        AppSetting::set('google_calendar_sync_enabled', '1');
        AppSetting::set('google_client_id', 'fake-id');
        AppSetting::flushCache();
        DB::table('auth_social_logins')->insert([
            'user_id'     => $this->photographer->id,
            'provider'    => 'google',
            'provider_id' => 'g-' . uniqid(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function test_upsert_success_writes_succeeded_audit(): void
    {
        $gcal = Mockery::mock(GoogleCalendarSyncService::class);
        $gcal->shouldReceive('upsertBookingOnCalendar')
            ->once()
            ->andReturn('gcal_event_999');

        (new SyncBookingToCalendarJob($this->booking->id, 'upsert'))->handle($gcal);

        $row = DB::table('booking_calendar_sync')->where('booking_id', $this->booking->id)->first();
        $this->assertSame('succeeded', $row->status);
        $this->assertSame('gcal_event_999', $row->gcal_event_id);
        $this->assertNotNull($row->synced_at);
    }

    public function test_upsert_null_with_disabled_feature_is_skipped_no_throw(): void
    {
        // Feature off — service returns null.
        AppSetting::set('google_calendar_sync_enabled', '0');
        AppSetting::flushCache();

        $gcal = Mockery::mock(GoogleCalendarSyncService::class);
        $gcal->shouldReceive('upsertBookingOnCalendar')->andReturn(null);

        (new SyncBookingToCalendarJob($this->booking->id, 'upsert'))->handle($gcal);

        $row = DB::table('booking_calendar_sync')->where('booking_id', $this->booking->id)->first();
        $this->assertSame('skipped', $row->status,
            'feature-off null must NOT be marked failed (no value retrying)');
    }

    public function test_upsert_null_with_enabled_feature_throws(): void
    {
        $this->fakeGcalLinked();

        $gcal = Mockery::mock(GoogleCalendarSyncService::class);
        $gcal->shouldReceive('upsertBookingOnCalendar')->andReturn(null);

        try {
            (new SyncBookingToCalendarJob($this->booking->id, 'upsert'))->handle($gcal);
            $this->fail('expected RuntimeException — feature-on null is transient and must throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('GCal upsert failed', $e->getMessage());
        }

        $row = DB::table('booking_calendar_sync')->where('booking_id', $this->booking->id)->first();
        $this->assertSame('failed', $row->status);
    }

    public function test_delete_success_writes_succeeded(): void
    {
        $gcal = Mockery::mock(GoogleCalendarSyncService::class);
        $gcal->shouldReceive('removeBookingFromCalendar')->once()->andReturn(true);

        (new SyncBookingToCalendarJob($this->booking->id, 'delete'))->handle($gcal);

        $row = DB::table('booking_calendar_sync')->where('booking_id', $this->booking->id)->first();
        $this->assertSame('succeeded', $row->status);
        $this->assertSame('delete', $row->operation);
    }

    public function test_delete_with_no_event_id_is_skipped(): void
    {
        $gcal = Mockery::mock(GoogleCalendarSyncService::class);
        $gcal->shouldReceive('removeBookingFromCalendar')->andReturn(false);

        (new SyncBookingToCalendarJob($this->booking->id, 'delete'))->handle($gcal);

        $row = DB::table('booking_calendar_sync')->where('booking_id', $this->booking->id)->first();
        $this->assertSame('skipped', $row->status);
    }

    public function test_failed_hook_finalises_pending_row(): void
    {
        DB::table('booking_calendar_sync')->insert([
            'booking_id'      => $this->booking->id,
            'photographer_id' => $this->photographer->id,
            'operation'       => 'upsert',
            'status'          => 'pending',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        (new SyncBookingToCalendarJob($this->booking->id, 'upsert'))
            ->failed(new \RuntimeException('5xx from Google'));

        $row = DB::table('booking_calendar_sync')->where('booking_id', $this->booking->id)->first();
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('5xx', $row->error);
    }

    public function test_missing_booking_is_a_quiet_noop(): void
    {
        $gcal = Mockery::mock(GoogleCalendarSyncService::class);
        $gcal->shouldNotReceive('upsertBookingOnCalendar');
        $gcal->shouldNotReceive('removeBookingFromCalendar');

        (new SyncBookingToCalendarJob(999_999, 'upsert'))->handle($gcal);

        // No rows for the missing booking.
        $this->assertSame(
            0,
            DB::table('booking_calendar_sync')->where('booking_id', 999_999)->count(),
        );
    }
}
