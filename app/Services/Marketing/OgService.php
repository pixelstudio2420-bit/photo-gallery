<?php

namespace App\Services\Marketing;

/**
 * Open Graph + Twitter Card meta tag generator.
 *
 * Renders the meta tags that control how your pages look when shared
 * on Facebook, LINE, X/Twitter, Instagram (WhatsApp, LinkedIn, Slack, etc.).
 */
class OgService
{
    public function __construct(protected MarketingService $marketing) {}

    /**
     * Render all OG + Twitter meta tags for the given context.
     *
     * @param array $data  {title, description, image, url, type, site_name}
     */
    public function render(array $data = []): string
    {
        if (!$this->marketing->ogEnabled()) return '';

        $title       = $data['title']       ?? config('app.name');
        $description = $data['description'] ?? '';
        $image       = $data['image']       ?? $this->marketing->get('og_default_image', '');
        $url         = $data['url']         ?? request()->url();
        $type        = $data['type']        ?? 'website';
        $siteName    = $data['site_name']   ?? config('app.name');
        $locale      = $data['locale']      ?? str_replace('-', '_', app()->getLocale() . '_TH');

        // Absolute URL for image
        if ($image && !str_starts_with($image, 'http')) {
            $image = asset($image);
        }

        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $desc = mb_substr(strip_tags($description), 0, 200);

        $tags = [
            // Open Graph
            ['property' => 'og:type',        'content' => $type],
            ['property' => 'og:title',       'content' => $title],
            ['property' => 'og:description', 'content' => $desc],
            ['property' => 'og:url',         'content' => $url],
            ['property' => 'og:site_name',   'content' => $siteName],
            ['property' => 'og:locale',      'content' => $locale],
        ];
        if ($image) {
            $tags[] = ['property' => 'og:image',        'content' => $image];
            $tags[] = ['property' => 'og:image:width',  'content' => '1200'];
            $tags[] = ['property' => 'og:image:height', 'content' => '630'];
        }

        // Product-specific
        if (isset($data['price'])) {
            $tags[] = ['property' => 'product:price:amount',   'content' => (string) $data['price']];
            $tags[] = ['property' => 'product:price:currency', 'content' => $data['currency'] ?? 'THB'];
        }

        // Twitter Card
        $tags[] = ['name' => 'twitter:card',        'content' => $image ? 'summary_large_image' : 'summary'];
        $tags[] = ['name' => 'twitter:title',       'content' => $title];
        $tags[] = ['name' => 'twitter:description', 'content' => $desc];
        if ($image) $tags[] = ['name' => 'twitter:image', 'content' => $image];

        $out = '';
        foreach ($tags as $t) {
            $attr = isset($t['property']) ? 'property' : 'name';
            $key  = $t[$attr];
            $out .= "    <meta {$attr}=\"{$e($key)}\" content=\"{$e($t['content'])}\">\n";
        }
        return $out;
    }

    /**
     * Build OG data array from an Event model (convenience).
     */
    public function fromEvent($event): array
    {
        return [
            'title'       => ($event->name ?? '') . ' | ' . config('app.name'),
            'description' => mb_substr(strip_tags($event->description ?? ''), 0, 200),
            'image'       => $event->cover_url ?? ($event->cover_path ? asset($event->cover_path) : null),
            'url'         => $event->url ?? request()->url(),
            'type'        => 'event',
        ];
    }

    /**
     * Build OG data from a Product/Package.
     */
    public function fromProduct($product): array
    {
        return [
            'title'       => ($product->name ?? $product->title ?? '') . ' | ' . config('app.name'),
            'description' => mb_substr(strip_tags($product->description ?? ''), 0, 200),
            'image'       => $product->image_url ?? ($product->cover_path ? asset($product->cover_path) : null),
            'type'        => 'product',
            'price'       => $product->price ?? null,
            'currency'    => 'THB',
        ];
    }
}
