<?php

namespace Tests\Feature\Booking;

use App\Services\GoogleMapsLink;
use Tests\TestCase;

/**
 * GoogleMapsLink contract.
 *
 * Properties locked down:
 *
 *   • valid coords → search() / directions() URLs include the lat,lng
 *   • out-of-range coords (lat>90, lng>180) → fall through to address
 *   • null-island (0,0) is treated as "no location" — common bug from
 *     a form that defaults coordinates to zero
 *   • address fallback only fires when address is at least 3 chars
 *     after trim — empty / whitespace strings produce null
 *   • hasUsableLocation reflects the same predicate
 */
class GoogleMapsLinkTest extends TestCase
{
    public function test_valid_coords_produce_search_url(): void
    {
        $url = GoogleMapsLink::search(13.7563, 100.5018);  // Bangkok
        $this->assertNotNull($url);
        $this->assertStringContainsString('13.7563%2C100.5018', $url);
        $this->assertStringContainsString('maps/search/?api=1', $url);
    }

    public function test_valid_coords_produce_directions_url(): void
    {
        $url = GoogleMapsLink::directions(13.7563, 100.5018);
        $this->assertStringContainsString('travelmode=driving', $url);
        $this->assertStringContainsString('destination=13.7563%2C100.5018', $url);
    }

    public function test_out_of_range_lat_falls_through_to_address(): void
    {
        $url = GoogleMapsLink::search(133.0, 14.0, 'Real address fallback');
        $this->assertNotNull($url);
        $this->assertStringContainsString('Real+address+fallback', $url);
        $this->assertStringNotContainsString('133', $url);
    }

    public function test_null_island_zeros_are_rejected(): void
    {
        // (0,0) is the giveaway for "form default that wasn't filled in".
        $url = GoogleMapsLink::search(0.0, 0.0, null);
        $this->assertNull($url);

        // But (0,0) WITH an address still works via the address fallback.
        $withAddr = GoogleMapsLink::search(0.0, 0.0, 'Studio Bangkok');
        $this->assertNotNull($withAddr);
    }

    public function test_short_address_returns_null(): void
    {
        $this->assertNull(GoogleMapsLink::search(null, null, ''));
        $this->assertNull(GoogleMapsLink::search(null, null, '  '));
        $this->assertNull(GoogleMapsLink::search(null, null, 'ab'));    // 2 chars
        $this->assertNotNull(GoogleMapsLink::search(null, null, 'abc')); // 3 chars OK
    }

    public function test_no_location_data_returns_null(): void
    {
        $this->assertNull(GoogleMapsLink::search(null, null, null));
        $this->assertNull(GoogleMapsLink::directions(null, null, null));
        $this->assertFalse(GoogleMapsLink::hasUsableLocation(null, null, null));
    }

    public function test_has_usable_location_predicate(): void
    {
        $this->assertTrue(GoogleMapsLink::hasUsableLocation(13.7563, 100.5018));
        $this->assertTrue(GoogleMapsLink::hasUsableLocation(null, null, 'Some place'));
        $this->assertFalse(GoogleMapsLink::hasUsableLocation(0.0, 0.0));
        $this->assertFalse(GoogleMapsLink::hasUsableLocation(null, null, ''));
    }

    public function test_address_with_special_characters_is_url_encoded(): void
    {
        $url = GoogleMapsLink::search(null, null, 'ลานจอดรถ ห้างสยาม');
        $this->assertStringNotContainsString('ลานจอด', $url, 'thai chars must be url-encoded');
        $this->assertStringStartsWith('https://www.google.com/maps/search/?api=1&query=', $url);
    }
}
