<?php

namespace App\Services\Seo;

use App\Models\SeoLandingPage;
use Illuminate\Support\Collection;

/**
 * Builds Schema.org JSON-LD payloads for pSEO landing pages.
 *
 * Google rewards pages that ship correct structured data with rich
 * results (image carousels, breadcrumb trails in SERP, ratings, etc).
 * Each page type maps to a slightly different schema shape:
 *
 *   • location / category / combo → ItemList (with related events
 *     wrapped as Event children) + BreadcrumbList
 *   • photographer                → Person + ProviderAggregateRating
 *   • event_archive               → CollectionPage
 *
 * The output is stored back on seo_landing_pages.schema_json so we
 * don't rebuild on every request once a page has been rendered once.
 */
class PSeoSchemaBuilder
{
    public function buildFor(SeoLandingPage $page, Collection $items): array
    {
        $base = [
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            'name'        => $page->title,
            'description' => $page->meta_description,
            'url'         => $page->url(),
        ];

        $payload = match ($page->type) {
            'location', 'category', 'combo' => $this->itemListSchema($page, $items),
            'photographer'                  => $this->personSchema($page, $items),
            'event_archive'                 => $this->collectionSchema($page, $items),
            default                         => $base,
        };

        // Always attach a BreadcrumbList — Google honours these on most
        // page types and they show up in the SERP UI.
        $payload['breadcrumb'] = $this->breadcrumb($page);

        return $payload;
    }

    /**
     * ItemList schema — wraps event listings as Event children with
     * dates, locations, and images so Google can spotlight individual
     * events in rich results.
     */
    private function itemListSchema(SeoLandingPage $page, Collection $items): array
    {
        $listElements = [];
        foreach ($items as $i => $event) {
            // Compose ISO 8601 startDate / endDate exactly like
            // Event::startDateIso() — but we hit this path with both
            // Eloquent models AND raw stdClass rows from DB::table(),
            // so we duplicate the formatting inline rather than
            // require the model. Safe even when start_time is null.
            $shoot = $event->shoot_date ?? null;
            $isoStart = null;
            $isoEnd   = null;
            if ($shoot) {
                $date  = $shoot instanceof \DateTimeInterface
                    ? $shoot->format('Y-m-d')
                    : \Carbon\Carbon::parse($shoot)->toDateString();
                $start = !empty($event->start_time)
                    ? substr((string) $event->start_time, 0, 8)
                    : '00:00:00';
                $isoStart = "{$date}T{$start}+07:00";
                if (!empty($event->end_time)) {
                    $isoEnd = "{$date}T" . substr((string) $event->end_time, 0, 8) . "+07:00";
                }
            }

            // Place: prefer venue_name; fall back to free-text location.
            $place = [
                '@type' => 'Place',
                'name'  => $event->venue_name ?: ($event->location ?? '—'),
            ];
            if (!empty($event->location)) {
                $place['address'] = [
                    '@type'           => 'PostalAddress',
                    'addressLocality' => (string) $event->location,
                    'addressCountry'  => 'TH',
                ];
            }

            $listElements[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'item'     => array_filter([
                    '@type'       => 'Event',
                    'name'        => $event->name,
                    'startDate'   => $isoStart ?: optional($shoot)->toIso8601String(),
                    'endDate'     => $isoEnd,
                    'eventStatus' => 'https://schema.org/EventScheduled',
                    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                    'url'         => url('/events/' . ($event->slug ?: $event->id)),
                    'location'    => $place,
                    'image'       => $event->cover_image ? url($event->cover_image) : null,
                    'organizer'   => !empty($event->organizer) ? [
                        '@type' => 'Organization',
                        'name'  => $event->organizer,
                    ] : null,
                    'maximumAttendeeCapacity' => !empty($event->expected_attendees)
                        ? (int) $event->expected_attendees
                        : null,
                ]),
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'CollectionPage',
            'name'            => $page->title,
            'description'     => $page->meta_description,
            'url'             => $page->url(),
            'mainEntity'      => [
                '@type'           => 'ItemList',
                'numberOfItems'   => $items->count(),
                'itemListElement' => $listElements,
            ],
        ];
    }

    /**
     * Person schema for photographer profiles. Provides Google with a
     * rich card in SERP including image, alma mater, etc. when those
     * fields are populated.
     */
    private function personSchema(SeoLandingPage $page, Collection $events): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Person',
            'name'        => $page->h1 ?: $page->title,
            'description' => $page->meta_description,
            'url'         => $page->url(),
            'jobTitle'    => 'Photographer',
            'worksFor'    => [
                '@type' => 'Organization',
                'name'  => config('app.name', 'Loadroop'),
            ],
        ];
    }

    private function collectionSchema(SeoLandingPage $page, Collection $items): array
    {
        return [
            '@context'      => 'https://schema.org',
            '@type'         => 'CollectionPage',
            'name'          => $page->title,
            'description'   => $page->meta_description,
            'url'           => $page->url(),
            'numberOfItems' => $items->count(),
        ];
    }

    /**
     * Breadcrumb trail rendered as a BreadcrumbList. Always Home →
     * Section → Page so Google's SERP shows a clear path. The Section
     * name varies by page type to stay accurate.
     */
    private function breadcrumb(SeoLandingPage $page): array
    {
        // Same labels + URLs as the visible breadcrumb in show.blade.php.
        // Keep these two paired — Google penalises Schema.org breadcrumbs
        // that don't match the on-page breadcrumb the user actually sees.
        $section = match ($page->type) {
            'location'      => ['name' => 'อีเวนต์ตามพื้นที่', 'url' => url('/events')],
            'category'      => ['name' => 'ช่างภาพ',           'url' => url('/photographers')],
            'combo'         => ['name' => 'ช่างภาพ',           'url' => url('/photographers')],
            'photographer'  => ['name' => 'ช่างภาพ',           'url' => url('/photographers')],
            'event_archive' => ['name' => 'อีเวนต์ทั้งหมด',    'url' => url('/events')],
            'event'         => ['name' => 'อีเวนต์',            'url' => url('/events')],
            default         => ['name' => 'หน้าแรก',           'url' => url('/')],
        };

        return [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => 'หน้าแรก',
                    'item'     => url('/'),
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => $section['name'],
                    'item'     => $section['url'],
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'name'     => $page->h1 ?: $page->title,
                    'item'     => $page->url(),
                ],
            ],
        ];
    }
}
