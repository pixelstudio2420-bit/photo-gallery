<?php

namespace App\Services;

/**
 * Builds Google Maps URLs for a booking's location.
 *
 * Two URL flavours we produce:
 *
 *   • search()    — opens Maps centred on the location (or the address
 *                   as a query). Use in admin tables and customer
 *                   confirmation pages.
 *
 *   • directions()— opens Maps in directions mode with the booking
 *                   location as the destination. Use in LINE pushes
 *                   so the photographer can tap once and start
 *                   navigating from wherever they are.
 *
 * The caller never has to know whether a booking has lat/lng or just a
 * free-form address — we prefer coordinates (precise pin) and fall
 * back to the text address. NULL is returned when neither is available
 * so the caller can hide the "open in Maps" button gracefully.
 *
 * Coordinate validation
 * ---------------------
 * We sanity-check that lat is in [-90, 90] and lng in [-180, 180]. A
 * common bug we've seen is form code that swaps lat/lng — sending
 * (133, 14) instead of (14, 133) — which Google happily turns into a
 * link to the middle of an ocean. Filter those out before building.
 */
class GoogleMapsLink
{
    /**
     * Search URL — drops a pin at the booking location.
     */
    public static function search(?float $lat, ?float $lng, ?string $address = null): ?string
    {
        if (self::validCoords($lat, $lng)) {
            return sprintf(
                'https://www.google.com/maps/search/?api=1&query=%s',
                urlencode("{$lat},{$lng}"),
            );
        }
        if (self::validAddress($address)) {
            return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address);
        }
        return null;
    }

    /**
     * Directions URL — opens the Maps app in turn-by-turn mode.
     * Origin is left unspecified so the user's current location is used.
     */
    public static function directions(?float $lat, ?float $lng, ?string $address = null): ?string
    {
        if (self::validCoords($lat, $lng)) {
            return sprintf(
                'https://www.google.com/maps/dir/?api=1&destination=%s&travelmode=driving',
                urlencode("{$lat},{$lng}"),
            );
        }
        if (self::validAddress($address)) {
            return 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($address) . '&travelmode=driving';
        }
        return null;
    }

    /**
     * Public predicate so callers can decide UI visibility ("show or
     * hide the maps button") without rebuilding a URL.
     */
    public static function hasUsableLocation(?float $lat, ?float $lng, ?string $address = null): bool
    {
        return self::validCoords($lat, $lng) || self::validAddress($address);
    }

    /* ─────────────────── internals ─────────────────── */

    private static function validCoords(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null) return false;
        if ($lat < -90 || $lat > 90)        return false;
        if ($lng < -180 || $lng > 180)      return false;
        // Reject the obvious null-island case — coords (0, 0) are
        // almost certainly a default that wasn't filled in.
        if ($lat === 0.0 && $lng === 0.0)   return false;
        return true;
    }

    private static function validAddress(?string $address): bool
    {
        if ($address === null) return false;
        $address = trim($address);
        // Need at least 3 chars to be meaningful and prevent the
        // generated URL from being just "https://...?query=" — Google
        // would silently land on an unhelpful default.
        return mb_strlen($address) >= 3;
    }
}
