<?php

namespace App\Services;

use App\Models\PhotographerAvailability;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves a photographer's availability for any given moment.
 *
 * Combines two rule types:
 *   • Recurring (every Mon 09:00–17:00 = available)
 *   • Override  (specific date wins over recurring)
 *
 * If no rules exist at all → photographer is treated as available 24/7
 * (legacy behaviour, lets new photographers receive bookings immediately
 * before they bother setting up their schedule).
 */
class AvailabilityService
{
    /**
     * Is the photographer available for the entire window [start..end]?
     */
    public function isWindowAvailable(int $photographerId, Carbon $start, Carbon $end): bool
    {
        $rules = $this->loadRulesCached($photographerId);

        // No rules = always available (legacy fallback).
        if ($rules->isEmpty()) return true;

        // Walk through 30-minute slices of the window. If ANY slice is
        // not available → the entire window is rejected.
        $cursor = $start->copy();
        while ($cursor < $end) {
            if (!$this->isMomentAvailable($photographerId, $cursor, $rules)) {
                return false;
            }
            $cursor->addMinutes(30);
        }
        return true;
    }

    /**
     * Cached rule loader.
     *
     * Why this exists
     * ---------------
     * Availability rules change rarely (a photographer might tweak their
     * schedule once a week) but are read on EVERY booking-form load and
     * EVERY conflict check. With even moderate traffic the
     * photographer_availability table becomes a hot read.
     *
     * Cache contract
     * --------------
     *   • Key:   "availability.rules.{photographerId}"
     *   • TTL:   `booking.availability_cache_ttl` (default 600s = 10min)
     *   • Bust:  PhotographerAvailability::saved/deleted observer (added
     *            below the bootstrap of this service in AppServiceProvider)
     *
     * The TTL is the upper bound on stale-rule windows. A photographer
     * adding a holiday and getting one new booking 5 minutes later
     * during that holiday is the worst case — survivable, recoverable
     * by the photographer (they can reject the booking). Shorter TTLs
     * trade hit-rate for staleness; the default value can be tuned via
     * config without touching code.
     */
    private function loadRulesCached(int $photographerId): Collection
    {
        $ttl = (int) config('booking.availability_cache_ttl', 600);
        $key = "availability.rules.{$photographerId}";

        // Return a Collection — Cache layer serialises it via PHP's own
        // serialize, which preserves the Eloquent model structure.
        $cached = \Illuminate\Support\Facades\Cache::remember($key, $ttl, function () use ($photographerId) {
            return PhotographerAvailability::forPhotographer($photographerId)->get();
        });
        return $cached instanceof Collection ? $cached : collect($cached);
    }

    /**
     * Public bust-hook called by the model observer (or admin UI).
     * Idempotent — calling on a non-existent key is a no-op.
     */
    public static function flushCacheFor(int $photographerId): void
    {
        \Illuminate\Support\Facades\Cache::forget("availability.rules.{$photographerId}");
    }

    /**
     * Check a single moment in time against the rule set.
     *
     * Precedence (highest → lowest):
     *   1. Override BLOCKED rule for this date+time   → blocked
     *   2. Override AVAILABLE rule for this date+time → available
     *   3. Recurring BLOCKED rule for this DOW+time   → blocked
     *      (lunch breaks layered on top of open hours)
     *   4. Recurring AVAILABLE rule for this DOW+time → available
     *   5. Otherwise → blocked (no rule covering this time)
     *
     * Blocked-wins-over-available within the same tier so that a more
     * specific carve-out (e.g. "12:00-13:00 lunch") trumps a wider
     * "09:00-17:00 open" window even though both technically cover 12:30.
     */
    public function isMomentAvailable(int $photographerId, Carbon $at, ?Collection $rules = null): bool
    {
        $rules ??= $this->loadRulesCached($photographerId);
        if ($rules->isEmpty()) return true;

        $time = $at->format('H:i:s');

        // ── Tier 1+2: Override rules (specific_date matches) ────────────
        $todayOverrides = $rules->where('type', PhotographerAvailability::TYPE_OVERRIDE)
            ->filter(fn ($r) => $r->specific_date && $r->specific_date->isSameDay($at));

        $matchingOverrides = $todayOverrides->filter(
            fn ($r) => $time >= $r->time_start && $time < $r->time_end
        );

        if ($matchingOverrides->isNotEmpty()) {
            // Blocked wins within overrides
            $hasBlocked = $matchingOverrides->contains(
                fn ($r) => $r->effect === PhotographerAvailability::EFFECT_BLOCKED
            );
            return !$hasBlocked;
        }

        // If overrides exist for this date but none cover this minute, fall
        // through to recurring rules (overrides are additive carve-outs,
        // not "this date is fully manually managed").

        // ── Tier 3+4: Recurring rules for this day-of-week ──────────────
        $dow = $at->dayOfWeek; // 0=Sun..6=Sat
        $recurringForDay = $rules->where('type', PhotographerAvailability::TYPE_RECURRING)
            ->where('day_of_week', $dow);

        if ($recurringForDay->isEmpty()) {
            // No rule for this day = blocked (photographers must explicitly
            // mark their open hours; missing entry = closed for that day).
            return false;
        }

        $matchingRecurring = $recurringForDay->filter(
            fn ($r) => $time >= $r->time_start && $time < $r->time_end
        );

        if ($matchingRecurring->isEmpty()) {
            // Outside every defined recurring window for today → blocked.
            return false;
        }

        // Blocked wins within matching recurring rules — this is what makes
        // a lunch-break carve-out beat the wider open-hours window.
        $hasBlocked = $matchingRecurring->contains(
            fn ($r) => $r->effect === PhotographerAvailability::EFFECT_BLOCKED
        );
        return !$hasBlocked;
    }

    /**
     * Generate available time slots for a specific date — used by the
     * customer booking form to offer "next available" suggestions.
     *
     * Returns array of ['start' => Carbon, 'end' => Carbon] for each
     * 30-min slot that's bookable.
     */
    public function suggestSlotsForDate(int $photographerId, Carbon $date, int $durationMinutes = 120): array
    {
        $rules = $this->loadRulesCached($photographerId);
        if ($rules->isEmpty()) {
            // Default workday: 09:00–18:00
            return $this->slotsInWindow(
                $date->copy()->setTime(9, 0),
                $date->copy()->setTime(18, 0),
                $durationMinutes
            );
        }

        $dow = $date->dayOfWeek;
        $availableWindows = $rules
            ->where('type', PhotographerAvailability::TYPE_RECURRING)
            ->where('day_of_week', $dow)
            ->where('effect', PhotographerAvailability::EFFECT_AVAILABLE);

        $slots = [];
        foreach ($availableWindows as $w) {
            [$h1, $m1] = explode(':', $w->time_start);
            [$h2, $m2] = explode(':', $w->time_end);
            $start = $date->copy()->setTime((int) $h1, (int) $m1);
            $end   = $date->copy()->setTime((int) $h2, (int) $m2);
            $slots = array_merge($slots, $this->slotsInWindow($start, $end, $durationMinutes));
        }
        return $slots;
    }

    private function slotsInWindow(Carbon $start, Carbon $end, int $durationMinutes): array
    {
        $slots = [];
        $cursor = $start->copy();
        while ($cursor->copy()->addMinutes($durationMinutes) <= $end) {
            $slots[] = [
                'start' => $cursor->copy(),
                'end'   => $cursor->copy()->addMinutes($durationMinutes),
            ];
            $cursor->addMinutes(30);
        }
        return $slots;
    }
}
