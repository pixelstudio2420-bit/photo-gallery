<?php

namespace App\Services\Marketing;

/**
 * Schema.org JSON-LD generator for rich Google search results.
 *
 * Supports:
 *   - Organization / LocalBusiness (site-wide)
 *   - Event (event pages)
 *   - Product (photo packages)
 *   - AggregateRating (photographer / event reviews)
 *   - BreadcrumbList
 *   - FAQPage
 *   - WebSite + SearchAction (enables sitelinks search box)
 */
class SchemaService
{
    public function __construct(protected MarketingService $marketing) {}

    /**
     * Render one or more JSON-LD scripts as HTML.
     * Returns '' if schema markup is disabled.
     */
    public function render(array $schemas): string
    {
        if (!$this->marketing->schemaEnabled()) return '';
        $schemas = array_filter($schemas);
        if (empty($schemas)) return '';

        $out = '';
        foreach ($schemas as $s) {
            $json = json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $out .= "<script type=\"application/ld+json\">\n{$json}\n</script>\n";
        }
        return $out;
    }

    /**
     * Site-wide Organization schema — include on every page.
     */
    public function organization(): array
    {
        return array_filter([
            '@context'  => 'https://schema.org',
            '@type'     => 'Organization',
            'name'      => config('app.name'),
            'url'       => config('app.url'),
            'logo'      => asset('images/logo.png'),
            'sameAs'    => array_filter([
                config('services.facebook.page_url') ?: null,
                config('services.instagram.url') ?: null,
                \App\Models\AppSetting::get('marketing_line_oa_id') ?: null,
            ]),
        ]);
    }

    /**
     * WebSite with SearchAction — enables Google sitelinks search box.
     */
    public function website(): array
    {
        return [
            '@context'  => 'https://schema.org',
            '@type'     => 'WebSite',
            'name'      => config('app.name'),
            'url'       => config('app.url'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type'        => 'EntryPoint',
                    'urlTemplate'  => rtrim(config('app.url'), '/') . '/search?q={query}',
                ],
                'query-input' => 'required name=query',
            ],
        ];
    }

    /**
     * Event schema — richest snippet for event pages.
     * @param object $event  EventModel (name, description, starts_at, location, cover_url, ...)
     */
    public function event($event): array
    {
        return array_filter([
            '@context'    => 'https://schema.org',
            '@type'       => 'Event',
            'name'        => $event->name ?? '',
            'description' => mb_substr(strip_tags($event->description ?? ''), 0, 500),
            'startDate'   => isset($event->starts_at) ? \Carbon\Carbon::parse($event->starts_at)->toIso8601String() : null,
            'endDate'     => isset($event->ends_at)   ? \Carbon\Carbon::parse($event->ends_at)->toIso8601String()   : null,
            'eventStatus' => 'https://schema.org/EventScheduled',
            'location'    => $event->location ? [
                '@type'   => 'Place',
                'name'    => $event->location,
                'address' => $event->address ?? $event->location,
            ] : null,
            'image'       => $event->cover_url ?? ($event->cover_path ? asset($event->cover_path) : null),
            'organizer'   => isset($event->organizer_name) ? [
                '@type' => 'Organization',
                'name'  => $event->organizer_name,
            ] : null,
            'offers'      => isset($event->price) ? [
                '@type'         => 'Offer',
                'price'         => (string) $event->price,
                'priceCurrency' => 'THB',
                'availability'  => 'https://schema.org/InStock',
                'url'           => $event->url ?? null,
            ] : null,
        ], fn($v) => $v !== null && $v !== '');
    }

    /**
     * Product schema — for photo packages / digital products.
     */
    public function product($product): array
    {
        $schema = array_filter([
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $product->name ?? $product->title ?? '',
            'description' => mb_substr(strip_tags($product->description ?? ''), 0, 500),
            'image'       => $product->image_url ?? ($product->cover_path ? asset($product->cover_path) : null),
            'sku'         => $product->sku ?? (string) ($product->id ?? ''),
            'offers'      => [
                '@type'         => 'Offer',
                'price'         => (string) ($product->price ?? 0),
                'priceCurrency' => 'THB',
                'availability'  => 'https://schema.org/InStock',
            ],
        ], fn($v) => $v !== null && $v !== '');

        // Attach aggregate rating if present
        if (isset($product->reviews_avg) && isset($product->reviews_count) && $product->reviews_count > 0) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => (string) round((float) $product->reviews_avg, 1),
                'reviewCount' => (int) $product->reviews_count,
            ];
        }
        return $schema;
    }

    /**
     * Breadcrumb list — helps Google show hierarchy in SERP.
     * @param array $items  [[name => '...', url => '...'], ...]
     */
    public function breadcrumb(array $items): array
    {
        $list = [];
        foreach ($items as $i => $item) {
            $list[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'] ?? '',
                'item'     => $item['url'] ?? null,
            ];
        }
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }

    /**
     * FAQPage schema — for help/support pages.
     * @param array $faqs  [[q => '...', a => '...'], ...]
     */
    public function faq(array $faqs): array
    {
        return [
            '@context'  => 'https://schema.org',
            '@type'     => 'FAQPage',
            'mainEntity' => array_map(fn($f) => [
                '@type'          => 'Question',
                'name'           => $f['q'] ?? '',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a'] ?? ''],
            ], $faqs),
        ];
    }
}
