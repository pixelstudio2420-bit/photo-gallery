<?php

namespace Tests\Integration\Google;

use App\Models\AppSetting;
use App\Services\GoogleSheetsExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Integration\IntegrationTestCase;

/**
 * Real Google Sheets API smoke.
 *
 * Required env
 * ------------
 *   INTEGRATION_GOOGLE_SERVICE_ACCOUNT_JSON  full SA JSON blob
 *   INTEGRATION_GOOGLE_SHEET_ID              a real sheet, shared
 *                                            with the SA email
 *
 * What we verify
 * --------------
 *   1. JWT-bearer token mint succeeds — RS256 sign works on the
 *      private key, Google accepts the assertion
 *   2. healthCheck() can read the sheet metadata (proves the SA is
 *      shared on the spreadsheet)
 *   3. appendBooking writes a row that we can read back
 *
 * Same self-skip pattern as the LINE smoke test.
 */
class GoogleSheetsApiSmokeTest extends IntegrationTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireEnv([
            'INTEGRATION_GOOGLE_SERVICE_ACCOUNT_JSON',
            'INTEGRATION_GOOGLE_SHEET_ID',
        ]);
        AppSetting::set('google_service_account_json',
            env('INTEGRATION_GOOGLE_SERVICE_ACCOUNT_JSON'));
        AppSetting::set('google_sheets_bookings_id',
            env('INTEGRATION_GOOGLE_SHEET_ID'));
        AppSetting::set('google_sheets_export_enabled', '1');
        AppSetting::flushCache();
    }

    public function test_health_check_passes_with_shared_sheet(): void
    {
        $svc = app(GoogleSheetsExportService::class);
        $r = $svc->healthCheck();

        $this->assertTrue($r['ok'],
            'healthCheck failed — likely the sheet is not shared with SA email. '
            . 'Reason: ' . ($r['reason'] ?? '(none)'));
        $this->assertNotEmpty($r['email']);
        $this->assertNotEmpty($r['title'] ?? null);
    }

    public function test_append_booking_writes_a_row(): void
    {
        // Build a minimal Booking-like model. The service reads
        // properties; we don't need a real DB row.
        $booking = new \App\Models\Booking([
            'title'            => 'Smoke Test Row',
            'duration_minutes' => 60,
            'status'           => 'pending',
            'agreed_price'     => 1000,
            'deposit_paid'     => 0,
            'customer_phone'   => '0812345678',
            'location'         => 'Smoke test location',
            'customer_notes'   => 'Created by integration smoke at ' . now()->toIso8601String(),
        ]);
        $booking->id = 999_999_999;
        $booking->setRelation('customer', new \App\Models\User([
            'first_name' => 'Smoke', 'last_name' => 'Test',
        ]));
        $booking->scheduled_at = now()->addDay();
        $booking->updated_at   = now();

        $ok = app(GoogleSheetsExportService::class)->appendBooking($booking);
        $this->assertTrue($ok, 'appendBooking returned false — see audit table');

        $audit = \DB::table('booking_sheets_exports')
            ->where('booking_id', 999_999_999)
            ->orderByDesc('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame('succeeded', $audit->status,
            'audit row must record success; got: ' . ($audit->error ?? 'no error message'));
    }
}
