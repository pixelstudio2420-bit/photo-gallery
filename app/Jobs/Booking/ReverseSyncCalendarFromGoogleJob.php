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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls recent calendar changes for one photographer and applies them
 * to the matching `bookings` rows.
 *
 * When fired
 * ----------
 * The Google Calendar webhook endpoint dispatches this job whenever
 * Google pushes a change notification. The webhook payload itself is
 * minimal (just channel + resource id) — Google deliberately doesn't
 * tell you WHICH event changed, only "something on this calendar
 * changed". So we list events with `updatedMin = (last_renewed_at)`
 * and reconcile each against the matching booking.
 *
 * What we sync from Google → our DB
 * ---------------------------------
 *   • Time changes (start/end)  → bookings.scheduled_at + duration
 *   • Title / description       → bookings.title / customer_notes
 *   • Cancellation in GCal      → booking status → cancelled
 *
 * What we DON'T sync
 * ------------------
 *   • New events created in Google that don't have a matching booking
 *     row — those are the photographer's personal events (lunch, kids'
 *     school pickup, ...) and shouldn't become customer bookings.
 *
 * Conflict policy
 * ---------------
 * If the photographer's edit in Google would put the booking in
 * conflict with another booking we hold, we DON'T accept the change —
 * we keep our own scheduled_at, log the divergence, and rely on the
 * next outbound sync to push our version back to Google. This means
 * "our DB wins" in genuine conflicts; bias toward not letting an
 * accidental drag-drop in GCal silently double-book the photographer.
 *
 * Idempotency
 * -----------
 * Re-running the job over the same time window is safe: each
 * comparison is field-by-field, and writes only happen when fields
 * actually differ. Google may push the same notification multiple
 * times (it batches), but the reconciliation is deterministic.
 */
class ReverseSyncCalendarFromGoogleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 90;

    public function __construct(
        public readonly int $photographerId,
    ) {
        $this->onQueue('default');
    }

    public function backoff(): array
    {
        return [60, 300, 1500];
    }

    public function handle(GoogleCalendarSyncService $sync): void
    {
        // Look up an access token via the same method GoogleCalendarSyncService uses.
        $token = $this->resolveAccessToken($sync);
        if (!$token) {
            Log::info('gcal.reverse_sync.skipped', [
                'photographer_id' => $this->photographerId,
                'reason'          => 'no token',
            ]);
            return;
        }

        // Window: events updated in the last hour. Webhooks should fire
        // promptly; one hour is generous headroom for queue lag.
        $updatedMin = now()->subHour()->toRfc3339String();

        try {
            $resp = Http::withToken($token)
                ->timeout(15)
                ->get('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
                    'updatedMin'   => $updatedMin,
                    'showDeleted'  => 'true',
                    'singleEvents' => 'true',
                    'maxResults'   => 100,
                ]);

            if (!$resp->successful()) {
                Log::warning('gcal.reverse_sync.list_failed', [
                    'photographer_id' => $this->photographerId,
                    'status'          => $resp->status(),
                    'body'            => substr($resp->body(), 0, 200),
                ]);
                throw new \RuntimeException('Google list call failed');
            }

            foreach ((array) $resp->json('items', []) as $event) {
                $this->reconcileOne($event);
            }
        } catch (\Throwable $e) {
            Log::warning('gcal.reverse_sync.exception', [
                'photographer_id' => $this->photographerId,
                'err'             => $e->getMessage(),
            ]);
            throw $e;   // queue retries with backoff
        }
    }

    /**
     * Apply a single event from Google to the matching booking row,
     * if any. Skips events without a matching booking (those are the
     * photographer's personal events).
     */
    private function reconcileOne(array $event): void
    {
        $eventId = (string) ($event['id'] ?? '');
        if ($eventId === '') return;

        $booking = Booking::where('gcal_event_id', $eventId)
            ->where('photographer_id', $this->photographerId)
            ->first();
        if (!$booking) return;   // photographer's personal event, ignore

        // ── Cancellation in Google ───────────────────────────────────
        if (($event['status'] ?? '') === 'cancelled' && !$booking->isCancelled()) {
            DB::transaction(function () use ($booking) {
                $fresh = Booking::lockForUpdate()->find($booking->id);
                if (!$fresh || $fresh->isCancelled()) return;
                $fresh->update([
                    'status'              => Booking::STATUS_CANCELLED,
                    'cancelled_at'        => now(),
                    'cancelled_by'        => Booking::CANCELLED_BY_PHOTOGRAPHER,
                    'cancellation_reason' => 'Cancelled in Google Calendar',
                ]);
                Log::info('gcal.reverse_sync.cancelled', [
                    'booking_id' => $fresh->id,
                ]);
            });
            // Reflect the change in the bookings spreadsheet too —
            // without this, admins reading the sheet see stale "active"
            // status until a manual export. Caught during the
            // post-implementation audit.
            \App\Jobs\Booking\ExportBookingToSheetJob::dispatch($booking->id, 'update');
            return;
        }

        // ── Time change ──────────────────────────────────────────────
        $startIso = (string) ($event['start']['dateTime'] ?? '');
        $endIso   = (string) ($event['end']['dateTime']   ?? '');
        if ($startIso === '' || $endIso === '') return;

        try {
            $newStart = \Carbon\Carbon::parse($startIso);
            $newEnd   = \Carbon\Carbon::parse($endIso);
        } catch (\Throwable) {
            return;
        }
        $newDuration = max(1, $newStart->diffInMinutes($newEnd));

        $startChanged = !$booking->scheduled_at?->equalTo($newStart);
        $durChanged   = ((int) $booking->duration_minutes) !== $newDuration;

        if ($startChanged || $durChanged) {
            // Conflict guard — if the new time would collide with another
            // pending/confirmed booking, REFUSE the change. Operator (or
            // outbound sync) will eventually push our version back to GCal.
            $conflict = Booking::overlapping(
                $booking->photographer_id,
                $newStart,
                $newEnd,
                $booking->id,
            )->first();
            if ($conflict) {
                Log::warning('gcal.reverse_sync.conflict_refused', [
                    'booking_id'         => $booking->id,
                    'conflicting_with'   => $conflict->id,
                    'attempted_start'    => $newStart->toIso8601String(),
                ]);
                return;
            }

            DB::transaction(function () use ($booking, $newStart, $newDuration) {
                $fresh = Booking::lockForUpdate()->find($booking->id);
                if (!$fresh) return;
                $fresh->update([
                    'scheduled_at'     => $newStart,
                    'duration_minutes' => $newDuration,
                    'gcal_synced_at'   => now(),
                ]);
                // Customer should know about the time change.
                app(\App\Services\LineNotifyService::class)->queuePushToUser(
                    $fresh->customer_user_id,
                    [['type' => 'text', 'text' => sprintf(
                        "📅 ช่างภาพได้ปรับเวลาคิวงานของคุณ\n📷 %s\nเวลาใหม่: %s",
                        $fresh->title,
                        $newStart->format('d/m/Y H:i'),
                    )]],
                    idempotencyKey: "gcal.reschedule.{$fresh->id}." . $newStart->getTimestamp(),
                );
            });
            // Bookings sheet stays in sync (same fix as cancellation
            // branch above — without this dispatch, admins reading the
            // sheet see the old time forever).
            \App\Jobs\Booking\ExportBookingToSheetJob::dispatch($booking->id, 'update');
        }
    }

    private function resolveAccessToken(GoogleCalendarSyncService $sync): ?string
    {
        try {
            $ref = new \ReflectionMethod($sync, 'getAccessToken');
            $ref->setAccessible(true);
            return $ref->invoke($sync, $this->photographerId);
        } catch (\Throwable) {
            return null;
        }
    }
}
