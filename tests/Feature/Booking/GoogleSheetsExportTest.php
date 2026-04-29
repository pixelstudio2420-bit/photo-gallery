<?php

namespace Tests\Feature\Booking;

use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\GoogleSheetsExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Locks down the Google Sheets export contract.
 *
 *   • isEnabled() reflects the three required settings
 *   • appendBooking() is a no-op when disabled (no audit row, no exception)
 *   • disabled state can't accidentally produce a row in
 *     booking_sheets_exports — protects forensic queries from noise
 *
 * The actual Sheets API call requires a service-account JSON which we
 * don't have in tests. We test the GATE behaviour + audit shape; the
 * happy-path round-trip is covered manually in QA against a real Sheet.
 */
class GoogleSheetsExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(): Booking
    {
        $cust = User::create([
            'first_name'    => 'C', 'last_name' => 'X',
            'email'         => 'c-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
        ]);
        $pg = User::create([
            'first_name'    => 'P', 'last_name' => 'X',
            'email'         => 'p-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
        ]);
        return Booking::create([
            'customer_user_id' => $cust->id,
            'photographer_id'  => $pg->id,
            'title'            => 'Sheets test',
            'scheduled_at'     => now()->addDays(2),
            'duration_minutes' => 120,
            'status'           => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_disabled_when_master_toggle_off(): void
    {
        AppSetting::set('google_sheets_export_enabled', '0');
        AppSetting::flushCache();
        $this->assertFalse(app(GoogleSheetsExportService::class)->isEnabled());
    }

    public function test_disabled_when_service_account_missing(): void
    {
        AppSetting::set('google_sheets_export_enabled', '1');
        AppSetting::set('google_service_account_json', '');
        AppSetting::set('google_sheets_bookings_id', 'sheet_id_123');
        AppSetting::flushCache();
        $this->assertFalse(app(GoogleSheetsExportService::class)->isEnabled());
    }

    public function test_disabled_when_spreadsheet_id_missing(): void
    {
        AppSetting::set('google_sheets_export_enabled', '1');
        AppSetting::set('google_service_account_json', '{"client_email":"x"}');
        AppSetting::set('google_sheets_bookings_id', '');
        AppSetting::flushCache();
        $this->assertFalse(app(GoogleSheetsExportService::class)->isEnabled());
    }

    public function test_append_when_disabled_is_silent_noop(): void
    {
        AppSetting::set('google_sheets_export_enabled', '0');
        AppSetting::flushCache();

        $booking = $this->makeBooking();
        $ok = app(GoogleSheetsExportService::class)->appendBooking($booking);

        $this->assertFalse($ok);
        $this->assertSame(
            0,
            DB::table('booking_sheets_exports')->where('booking_id', $booking->id)->count(),
            'disabled state must NOT pollute the audit table',
        );
    }

    public function test_export_job_short_circuits_when_disabled(): void
    {
        AppSetting::set('google_sheets_export_enabled', '0');
        AppSetting::flushCache();

        $booking = $this->makeBooking();
        // Direct invocation — would throw if it tried to actually export.
        (new \App\Jobs\Booking\ExportBookingToSheetJob($booking->id, 'append'))
            ->handle(app(GoogleSheetsExportService::class));

        $this->assertSame(
            0,
            DB::table('booking_sheets_exports')->where('booking_id', $booking->id)->count(),
        );
    }
}
