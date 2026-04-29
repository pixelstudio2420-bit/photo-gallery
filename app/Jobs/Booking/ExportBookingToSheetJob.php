<?php

namespace App\Jobs\Booking;

use App\Models\Booking;
use App\Services\GoogleSheetsExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued export of a booking to Google Sheets.
 *
 * Dispatched on booking lifecycle transitions (create → append; confirm
 * / cancel / complete → update). The Sheets API is slower + flakier
 * than GCal because it goes through the spreadsheet engine, so doing it
 * inline would frequently time out customer requests.
 *
 * Retries: 3 attempts. Sheets is "nice to have" — not delivery-critical
 * — so we don't burn 5 hours retrying like the calendar sync. After 3
 * misses the booking_sheets_exports row stays in 'failed' for the
 * admin to see.
 */
class ExportBookingToSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int $bookingId,
        public readonly string $operation = 'append',  // append | update
    ) {
        $this->onQueue('default');
    }

    public function backoff(): array
    {
        return [60, 300, 1500];
    }

    public function handle(GoogleSheetsExportService $sheets): void
    {
        if (!$sheets->isEnabled()) {
            // Admin hasn't configured the integration; skip silently.
            // booking_sheets_exports stays empty for this booking — no
            // reason to log a "feature off" warning every time.
            return;
        }

        $booking = Booking::find($this->bookingId);
        if (!$booking) {
            Log::info('ExportBookingToSheetJob: booking gone', [
                'booking_id' => $this->bookingId,
            ]);
            return;
        }

        $ok = match ($this->operation) {
            'append' => $sheets->appendBooking($booking),
            'update' => $sheets->updateBooking($booking),
            default  => false,
        };

        if (!$ok) {
            // Re-throw so queue retries per backoff. The audit row was
            // already updated to 'failed' by the service.
            throw new \RuntimeException(
                "Sheets {$this->operation} failed for booking {$this->bookingId}"
            );
        }
    }
}
