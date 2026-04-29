<?php
namespace App\Services\Social;

use Illuminate\Support\Facades\URL;

class SocialShareService
{
    /**
     * Generate sharing URLs for a given page.
     */
    public static function getShareUrls(string $url, string $title, string $description = '', string $image = ''): array
    {
        $encodedUrl = urlencode($url);
        $encodedTitle = urlencode($title);
        $encodedDesc = urlencode($description);

        return [
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}",
            'twitter' => "https://twitter.com/intent/tweet?url={$encodedUrl}&text={$encodedTitle}",
            'line' => "https://social-plugins.line.me/lineit/share?url={$encodedUrl}",
            'pinterest' => $image
                ? "https://pinterest.com/pin/create/button/?url={$encodedUrl}&media=" . urlencode($image) . "&description={$encodedTitle}"
                : null,
            'email' => "mailto:?subject={$encodedTitle}&body={$encodedDesc}%0A%0A{$encodedUrl}",
            'copy' => $url,
        ];
    }

    /**
     * Generate JSON-LD structured data for an event photo gallery.
     */
    public static function eventJsonLd(array $event): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event['name'] ?? '',
            'description' => $event['description'] ?? '',
            'startDate' => $event['date'] ?? '',
            'location' => [
                '@type' => 'Place',
                'name' => $event['location'] ?? $event['name'] ?? '',
            ],
            'image' => $event['image'] ?? '',
            'organizer' => [
                '@type' => 'Organization',
                'name' => config('app.name'),
                'url' => config('app.url'),
            ],
        ];

        if (!empty($event['price'])) {
            $data['offers'] = [
                '@type' => 'Offer',
                'price' => $event['price'],
                'priceCurrency' => 'THB',
                'availability' => 'https://schema.org/InStock',
                'url' => $event['url'] ?? config('app.url'),
            ];
        }

        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
    }

    /**
     * Generate JSON-LD for a product (digital download).
     */
    public static function productJsonLd(array $product): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'] ?? '',
            'description' => $product['description'] ?? '',
            'image' => $product['image'] ?? '',
            'offers' => [
                '@type' => 'Offer',
                'price' => $product['price'] ?? 0,
                'priceCurrency' => 'THB',
                'availability' => 'https://schema.org/InStock',
            ],
        ];

        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
    }
}
