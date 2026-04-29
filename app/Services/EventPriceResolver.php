<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * EventPriceResolver
 *
 * Single source of truth for "how much does one photo from event X cost?".
 *
 * Why this exists: both OrderController (server charge) and CartController
 * (what the UI shows) need the same answer, and we never trust a client-
 * submitted price. Previously the lookup lived as a private method on
 * OrderController; CartController happily stored whatever `price` value
 * the client sent. Now both go through here so the cart total matches
 * what the user is actually charged at checkout.
 *
 * Priority:
 *   1. `pricing_event_prices.price_per_photo` — explicit override row
 *   2. `event_events.price_per_photo`          — event's own base price
 *   3. 0.0                                     — unknown event
 *
 * Results are cached for 60s (event price changes are rare and the check
 * runs on every cart add / AJAX refresh).
 */
class EventPriceResolver
{
    /** @var int Cache TTL in seconds. */
    private const CACHE_TTL = 60;

    /**
     * Return the authoritative price per photo for a given event.
     */
    public function perPhoto(?int $eventId): float
    {
        if (!$eventId) {
            return 0.0;
        }

        $cacheKey = "event_price_per_photo:{$eventId}";

        return (float) Cache::remember($cacheKey, self::CACHE_TTL, function () use ($eventId) {
            $override = DB::table('pricing_event_prices')
                ->where('event_id', $eventId)
                ->value('price_per_photo');

            if (!is_null($override)) {
                return (float) $override;
            }

            $event = Event::find($eventId);
            return $event ? (float) $event->price_per_photo : 0.0;
        });
    }

    /**
     * Invalidate the cache for an event — call after admin edits pricing.
     */
    public function forget(int $eventId): void
    {
        Cache::forget("event_price_per_photo:{$eventId}");
    }
}
