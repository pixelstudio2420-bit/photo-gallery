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
            $listElements[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'item'     => [
                    '@type'       => 'Event',
                    'name'        => $event->name,
                    'startDate'   => optional($event->shoot_date)->toIso8601String(),
                    'eventStatus' => 'https://schema.org/EventScheduled',
                    'url'         => url('/events/' . ($event->slug ?: $event->id)),
                    'location'    => [
                        '@type' => 'Place',
                        'name'  => $event->location ?? '—',
                    ],
                    'image'       => $event->cover_image ? url($event->cover_image) : null,
                ],
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
        $section = match ($page->type) {
            'location'      => ['name' => 'อีเวนต์ตามพื้นที่',  'url' => url('/events')],
            'category'      => ['name' => 'ประเภทช่างภาพ',     'url' => url('/photographers')],
            'combo'         => ['name' => 'ค้นหาช่างภาพ',       'url' => url('/photographers')],
            'photographer'  => ['name' => 'ช่างภาพ',             'url' => url('/photographers')],
            'event_archive' => ['name' => 'อีเวนต์',             'url' => url('/events')],
            default         => ['name' => 'หน้าแรก',             'url' => url('/')],
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
