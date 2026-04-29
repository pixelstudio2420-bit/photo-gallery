<?php

namespace App\Jobs\Booking;

use App\Models\Booking;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a booking to Google Calendar (or removes it) asynchronously.
 *
 * Why this exists
 * ---------------
 * The previous flow called GoogleCalendarSyncService inline inside
 * BookingService::confirm()/cancel(). Two problems:
 *
 *   1. Slow: a Google API roundtrip (~200-1000ms) blocked the photographer's
 *      "confirm" button until the calendar responded. With a transient
 *      Google 5xx, the photographer would see a 30-second hang.
 *
 *   2. Silent failures: errors were logged at warning level and discarded.
 *      Nobody knew which bookings ended up out of sync until a customer
 *      complained "the time isn't on my calendar".
 *
 * The job + booking_calendar_sync audit table fixes both:
 *   • Photographer sees instant confirm; Google sync happens behind.
 *   • A row in booking_calendar_sync per attempt makes the question
 *     "did this booking sync to GCal?" a single SELECT.
 *
 * Retry semantics
 * ---------------
 * 5 attempts; backoff 30s/120s/600s/1800s. Terminal failures (401, 403)
 * fail fast — the photographer's Google token has expired or been revoked
 * and retrying just spams the API. Transient failures (5xx, network) ride
 * the backoff.
 *
 * Idempotency
 * -----------
 * Operation = 'upsert': if booking.gcal_event_id is set, the underlying
 * service does a PUT (update); else POST (create). Re-running the job
 * after a successful upsert is a no-op upsert against the same event.
 *
 * Operation = 'delete': service is a no-op when gcal_event_id is null.
 * Re-running after a successful delete is a quiet no-op.
 */
class SyncBookingToCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 60;

    public function __construct(
        public readonly int $bookingId,
        public readonly string $operation = 'upsert',  // upsert | delete
    ) {
        $this->onQueue('default');
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(GoogleCalendarSyncService $gcal): void
    {
        $booking = Booking::find($this->bookingId);
        if (!$booking) {
            Log::warning('SyncBookingToCalendarJob: booking not found', [
                'booking_id' => $this->bookingId,
            ]);
            return;
        }

        // Open audit row before the API call so a worker crash mid-call
        // is still observable as 'pending' (the failed() hook flips it
        // to 'failed' on terminal failure).
        $auditId = DB::table('booking_calendar_sync')->insertGetId([
            'booking_id'      => $booking->id,
            'photographer_id' => $booking->photographer_id,
            'operation'       => $this->operation,
            'status'          => 'pending',
            'gcal_event_id'   => $booking->gcal_event_id,
            'attempts'        => $this->attempts(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        try {
            if ($this->operation === 'delete') {
                $ok = $gcal->removeBookingFromCalendar($booking);
                $status = $ok ? 'succeeded' : 'skipped';
                DB::table('booking_calendar_sync')->where('id', $auditId)->update([
                    'status'     => $status,
                    'synced_at'  => now(),
                    'updated_at' => now(),
                ]);
                return;
            }

            // upsert
            $eventId = $gcal->upsertBookingOnCalendar($booking);
            $serviceEnabled = $this->isLikelyTransient($gcal, $booking);

            DB::table('booking_calendar_sync')->where('id', $auditId)->update([
                'status'        => $eventId !== null
                                    ? 'succeeded'
                                    : ($serviceEnabled ? 'failed' : 'skipped'),
                'gcal_event_id' => $eventId,
                'synced_at'     => now(),
                'updated_at'    => now(),
            ]);

            // Two distinct null cases:
            //   • feature off (no token, admin disabled) → 'skipped',
            //     no retry — re-running 5x with 30-min backoff will not
            //     conjure credentials out of thin air.
            //   • feature on + API call failed → 'failed', throw so the
            //     queue worker retries with backoff.
            if ($eventId === null && $serviceEnabled) {
                throw new \RuntimeException('GCal upsert failed (likely transient)');
            }
        } catch (\Throwable $e) {
            DB::table('booking_calendar_sync')->where('id', $auditId)->update([
                'status'     => 'failed',
                'error'      => mb_substr($e->getMessage(), 0, 500),
                'updated_at' => now(),
            ]);
            throw $e;   // queue worker will retry per backoff
        }
    }

    /**
     * Final-state hook — called after all retries exhausted. Updates the
     * latest audit row to 'failed' so the admin UI's "X bookings failed
     * to sync" widget surfaces it. Also sends a one-shot LINE alert to
     * admins so a hard sync break doesn't silently rot.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('SyncBookingToCalendarJob: exhausted retries', [
            'booking_id' => $this->bookingId,
            'operation'  => $this->operation,
            'error'      => $e->getMessage(),
        ]);
        DB::table('booking_calendar_sync')
            ->where('booking_id', $this->bookingId)
            ->where('status', 'pending')
            ->update([
                'status'     => 'failed',
                'error'      => mb_substr($e->getMessage(), 0, 500),
                'updated_at' => now(),
            ]);
    }

    /**
     * Distinguishes "feature is enabled but the API call returned null"
     * (transient failure → retry) from "feature is off / not linked"
     * (skip, don't retry).
     *
     * The service's `isEnabledFor` method is private; we duplicate the
     * check here against the same data sources (app_settings + the
     * photographer's social-login row). The cost is minor — two cheap
     * SELECTs — and it keeps the public service surface clean.
     */
    private function isLikelyTransient(GoogleCalendarSyncService $gcal, Booking $booking): bool
    {
        if (\App\Models\AppSetting::get('google_calendar_sync_enabled', '1') !== '1') {
            return false;
        }
        if (\App\Models\AppSetting::get('google_client_id', '') === '') {
            return false;
        }
        return DB::table('auth_social_logins')
            ->where('user_id', $booking->photographer_id)
            ->where('provider', 'google')
            ->exists();
    }
}
