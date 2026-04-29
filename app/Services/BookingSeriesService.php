<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owns recurring bookings.
 *
 * Why a custom recurrence format (not full RRULE)
 * -----------------------------------------------
 * RFC 5545 RRULE supports patterns we'll never use ("every 2nd
 * Tuesday" + "by setpos -1" + "by month 1,3,5"). The validation +
 * parsing burden is real: the Python `dateutil` and JS `rrule.js`
 * libraries are 1k+ LOC each and still have edge cases. For the
 * photographer-booking use case we need:
 *
 *   • daily   (rare — but valid for "boot camp every morning")
 *   • weekly  (THE common case — "every Wednesday at 14:00")
 *   • monthly (e.g. monthly portrait session)
 *   • interval (every 2 weeks)
 *   • by_day  (specific weekdays for weekly)
 *   • count / until — bounded series
 *   • exceptions — skip specific dates
 *
 * Our JSON shape covers all of that in <50 LOC of generation code.
 *
 * Recurrence JSON contract
 * ------------------------
 *   {
 *     "freq":      "daily" | "weekly" | "monthly",
 *     "interval":  1,                  // every N {freq}, default 1
 *     "by_day":    ["MO","WE","FR"],   // weekly only; default = day-of-week of starts_on
 *     "time":      "14:00",            // HH:mm in series.timezone
 *     "starts_on": "2026-05-20",       // first occurrence date
 *     "until":     "2026-12-31",       // optional end date (inclusive)
 *     "count":     24,                 // optional max occurrences
 *     "exceptions":["2026-07-04"]      // dates to skip (holidays etc.)
 *   }
 *
 * Materialization model
 * ---------------------
 * We don't pre-create every occurrence at series-creation time — for
 * a "weekly forever" series that would be unbounded. Instead, the
 * materialize job runs daily and creates rows up to
 * `booking.materialize_horizon_days` ahead of "now". The series row
 * tracks `materialized_until` so the job knows the horizon.
 *
 * Cancelling one instance vs the whole series
 * -------------------------------------------
 * Each materialised booking is a regular `bookings` row with a
 * `series_id` back-reference. The customer or admin can cancel a
 * single instance via the existing BookingService::cancel — only
 * that one row is affected. To cancel the entire series + every
 * future instance use BookingSeriesService::cancelSeries.
 */
class BookingSeriesService
{
    public function __construct(
        private readonly BookingService $bookings,
    ) {}

    /**
     * Create a new series. Returns the id of the booking_series row.
     * The first batch of occurrences is materialised immediately so
     * the customer sees their schedule right after submitting.
     */
    public function create(array $base, array $recurrence): int
    {
        $this->validateRecurrence($recurrence);

        $seriesId = DB::table('booking_series')->insertGetId([
            'customer_user_id' => (int) $base['customer_user_id'],
            'photographer_id'  => (int) $base['photographer_id'],
            'title'            => (string) $base['title'],
            'description'      => $base['description']    ?? null,
            'location'         => $base['location']       ?? null,
            'location_lat'     => $base['location_lat']   ?? null,
            'location_lng'     => $base['location_lng']   ?? null,
            'duration_minutes' => (int) ($base['duration_minutes'] ?? 120),
            'agreed_price'     => $base['agreed_price']   ?? null,
            'package_name'     => $base['package_name']   ?? null,
            'customer_phone'   => $base['customer_phone'] ?? null,
            'customer_notes'   => $base['customer_notes'] ?? null,
            'timezone'         => (string) ($base['timezone']
                                  ?? config('booking.default_timezone', 'Asia/Bangkok')),
            'recurrence'       => json_encode($recurrence),
            'status'           => 'active',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->materializeOne($seriesId);
        return $seriesId;
    }

    /**
     * Cancel the series + all its FUTURE instances. Past instances
     * (already-completed shoots) keep their status — the series end
     * is "no more new occurrences from this date onward".
     */
    public function cancelSeries(int $seriesId, string $cancelledBy, ?string $reason = null): int
    {
        $now = now();
        DB::transaction(function () use ($seriesId, $now, $cancelledBy, $reason) {
            DB::table('booking_series')->where('id', $seriesId)->update([
                'status'     => 'cancelled',
                'updated_at' => $now,
            ]);
        });

        // Cancel each future booking through the regular service so
        // notifications + GCal cleanup fire normally.
        $futureBookings = Booking::where('series_id', $seriesId)
            ->where('scheduled_at', '>', $now)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED, Booking::STATUS_NO_SHOW])
            ->get();

        $count = 0;
        foreach ($futureBookings as $b) {
            try {
                $this->bookings->cancel($b, $cancelledBy, $reason ?: 'Series cancelled');
                $count++;
            } catch (\Throwable $e) {
                Log::warning('booking_series.cancel_instance_failed', [
                    'booking_id' => $b->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
        return $count;
    }

    /**
     * Walk every active series and extend its materialised window to
     * `now + horizon_days`. Idempotent.
     *
     * @return array{created:int, series_processed:int}
     */
    public function materializeAll(?int $horizonDays = null): array
    {
        $horizonDays ??= (int) config('booking.materialize_horizon_days', 90);
        $created = 0;
        $processed = 0;

        DB::table('booking_series')
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($horizonDays, &$created, &$processed) {
                foreach ($rows as $row) {
                    $created += $this->materializeOne((int) $row->id, $horizonDays);
                    $processed++;
                }
            });

        return ['created' => $created, 'series_processed' => $processed];
    }

    /**
     * Materialize one series up to `now + horizonDays`. Returns the
     * number of new booking rows created.
     */
    public function materializeOne(int $seriesId, ?int $horizonDays = null): int
    {
        $horizonDays ??= (int) config('booking.materialize_horizon_days', 90);
        $row = DB::table('booking_series')->where('id', $seriesId)->first();
        if (!$row || $row->status !== 'active') return 0;

        $rule = json_decode((string) $row->recurrence, true) ?: [];
        if (empty($rule)) return 0;

        $tz       = (string) ($row->timezone ?: config('booking.default_timezone', 'Asia/Bangkok'));
        $horizon  = now()->addDays($horizonDays);
        $startFrom = $row->materialized_until
            ? Carbon::parse($row->materialized_until)
            : now()->subDay();   // include "today" on first run

        $occurrences = $this->generateOccurrences($rule, $tz, $startFrom, $horizon);
        if (empty($occurrences)) return 0;

        $existingCount = (int) Booking::where('series_id', $seriesId)->count();
        $maxCount = (int) ($rule['count'] ?? 0);
        $created  = 0;

        foreach ($occurrences as $occursAt) {
            // Respect the rule's max-count, if any.
            if ($maxCount > 0 && ($existingCount + $created) >= $maxCount) {
                break;
            }

            // Idempotency: if a booking with this series_id +
            // scheduled_at already exists, skip. We don't enforce a
            // unique index because a cancel-and-re-add to the same
            // slot is a legitimate flow.
            $exists = Booking::where('series_id', $seriesId)
                ->where('scheduled_at', $occursAt)
                ->exists();
            if ($exists) continue;

            try {
                Booking::create([
                    'customer_user_id' => (int) $row->customer_user_id,
                    'photographer_id'  => (int) $row->photographer_id,
                    'series_id'        => $seriesId,
                    'title'            => $row->title,
                    'description'      => $row->description,
                    'location'         => $row->location,
                    'location_lat'     => $row->location_lat,
                    'location_lng'     => $row->location_lng,
                    'duration_minutes' => (int) $row->duration_minutes,
                    'agreed_price'     => $row->agreed_price,
                    'package_name'     => $row->package_name,
                    'customer_phone'   => $row->customer_phone,
                    'customer_notes'   => $row->customer_notes,
                    'timezone'         => $tz,
                    'scheduled_at'     => $occursAt,
                    'status'           => Booking::STATUS_PENDING,
                ]);
                $created++;
            } catch (\Throwable $e) {
                Log::warning('booking_series.materialize_insert_failed', [
                    'series_id'    => $seriesId,
                    'scheduled_at' => $occursAt->toIso8601String(),
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        // Bump materialized_until so the next run starts from where we
        // left off. Cap at the horizon so a bounded series ('count' or
        // 'until') doesn't keep being walked forever.
        DB::table('booking_series')->where('id', $seriesId)->update([
            'materialized_until' => $horizon,
            'status'             => $this->shouldEnd($rule, $existingCount + $created)
                                    ? 'ended' : 'active',
            'updated_at'         => now(),
        ]);

        return $created;
    }

    /**
     * Generate occurrence Carbon timestamps for a single series, between
     * $from and $to (inclusive). Returns UTC Carbon instances ready for
     * direct INSERT.
     *
     * @return array<int, Carbon>
     */
    public function generateOccurrences(array $rule, string $tz, Carbon $from, Carbon $to): array
    {
        $freq     = strtolower((string) ($rule['freq'] ?? 'weekly'));
        $interval = max(1, (int) ($rule['interval'] ?? 1));
        $time     = $this->parseTime((string) ($rule['time'] ?? '09:00'));
        $byDay    = $this->parseByDay((array) ($rule['by_day'] ?? []));
        $startsOn = isset($rule['starts_on'])
            ? Carbon::parse((string) $rule['starts_on'], $tz)->startOfDay()
            : $from->copy()->setTimezone($tz)->startOfDay();
        $until    = isset($rule['until'])
            ? Carbon::parse((string) $rule['until'], $tz)->endOfDay()
            : null;
        $exceptions = collect((array) ($rule['exceptions'] ?? []))
            ->map(fn ($d) => (string) Carbon::parse($d, $tz)->format('Y-m-d'))
            ->all();

        $hardEnd = $until ? min($to, $until) : $to;
        // Walk the calendar at $tz so DST / TZ shifts are local-correct,
        // then convert each output to UTC for storage.
        $cursor = $startsOn->copy();
        $occurrences = [];

        // Reasonable step cap so a malformed rule can't infinite-loop.
        $maxIterations = 5000;
        $i = 0;
        // Slot counter for COUNT-bounded rules. Lives outside the loop
        // (NOT a `static` — that would survive across method calls and
        // contaminate the next series).
        $slotCount = 0;
        $countCap  = isset($rule['count']) ? max(1, (int) $rule['count']) : null;

        while ($cursor->lessThanOrEqualTo($hardEnd) && $i++ < $maxIterations) {
            $candidate = $cursor->copy()->setTime($time['h'], $time['m']);
            $accept = false;

            switch ($freq) {
                case 'daily':
                    // every $interval days from starts_on
                    $diff = $startsOn->diffInDays($cursor);
                    $accept = ($diff % $interval) === 0;
                    break;

                case 'weekly':
                    // every $interval weeks from starts_on, on by_day
                    // (default = same DOW as starts_on)
                    $weekDiff = $startsOn->copy()->startOfWeek()->diffInWeeks($cursor->copy()->startOfWeek());
                    if ($weekDiff % $interval !== 0) break;
                    if (empty($byDay)) {
                        $accept = $cursor->dayOfWeek === $startsOn->dayOfWeek;
                    } else {
                        $accept = in_array($cursor->dayOfWeek, $byDay, true);
                    }
                    break;

                case 'monthly':
                    // every $interval months on the same DAY-OF-MONTH as
                    // starts_on. (DOM > 28 is allowed; months without
                    // that day are skipped silently — e.g. day 31 in Feb.)
                    if ($cursor->day !== $startsOn->day) break;
                    $monthDiff = $startsOn->diffInMonths($cursor);
                    $accept = ($monthDiff % $interval) === 0;
                    break;
            }

            // Two-stage filter: the rule generates an occurrence slot,
            // then exceptions / window filter it. We track slots
            // separately from emitted occurrences so `count` consumes
            // exception-filtered slots too — matching RFC 5545
            // semantics where COUNT includes EXDATEs.
            if ($accept) {
                $isException = in_array($cursor->format('Y-m-d'), $exceptions, true);
                $inWindow    = $candidate->greaterThanOrEqualTo($from)
                            && $candidate->lessThanOrEqualTo($hardEnd);

                if ($inWindow && !$isException) {
                    $occurrences[] = $candidate->copy()->utc();
                }

                // Stop walking once we've consumed `count` slots
                // (whether emitted or excepted). Without this, a series
                // with 1 exception + count=4 would generate 4 valid
                // occurrences (skipping past the exception slot).
                if ($countCap !== null) {
                    $slotCount++;
                    if ($slotCount >= $countCap) break;
                }
            }

            $cursor->addDay();
        }

        return $occurrences;
    }

    /* ─────────────────── helpers ─────────────────── */

    private function shouldEnd(array $rule, int $createdSoFar): bool
    {
        if (isset($rule['until']) && now()->greaterThan(Carbon::parse((string) $rule['until']))) {
            return true;
        }
        if (isset($rule['count']) && $createdSoFar >= (int) $rule['count']) {
            return true;
        }
        return false;
    }

    private function validateRecurrence(array $rule): void
    {
        $freq = strtolower((string) ($rule['freq'] ?? ''));
        if (!in_array($freq, ['daily', 'weekly', 'monthly'], true)) {
            throw new \InvalidArgumentException('recurrence.freq must be one of daily|weekly|monthly');
        }
        if (empty($rule['starts_on'])) {
            throw new \InvalidArgumentException('recurrence.starts_on is required');
        }
        // Must have a stop condition — until OR count. Otherwise a
        // weekly series would materialise 90 days forward forever.
        if (empty($rule['until']) && empty($rule['count'])) {
            throw new \InvalidArgumentException('recurrence.until or recurrence.count is required');
        }
    }

    /** "14:00" → ['h' => 14, 'm' => 0] */
    private function parseTime(string $hhmm): array
    {
        $parts = explode(':', $hhmm);
        return [
            'h' => max(0, min(23, (int) ($parts[0] ?? 9))),
            'm' => max(0, min(59, (int) ($parts[1] ?? 0))),
        ];
    }

    /** ['MO','WE'] → [1, 3]  (Carbon dayOfWeek: 0=Sun..6=Sat) */
    private function parseByDay(array $days): array
    {
        $map = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
        $out = [];
        foreach ($days as $d) {
            $key = strtoupper(substr((string) $d, 0, 2));
            if (isset($map[$key])) $out[] = $map[$key];
        }
        return array_values(array_unique($out));
    }
}
