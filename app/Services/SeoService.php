<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;

/**
 * SeoService — Singleton service that generates all SEO meta tags,
 * Open Graph tags, Twitter cards, JSON-LD schema, and Google Analytics code.
 *
 * Replicates the original PHP site's core/SEO.php functionality.
 */
class SeoService
{
    // ---------------------------------------------------------------------------
    // Per-request state
    // ---------------------------------------------------------------------------

    protected ?string $pageTitle       = null;
    protected ?string $description     = null;
    protected ?string $keywords        = null;
    protected ?string $canonical       = null;
    protected ?string $image           = null;
    protected string  $ogType          = 'website';
    protected ?string $robots          = null;
    protected array   $breadcrumbs     = [];
    protected array   $jsonLdSchemas   = [];

    // ---------------------------------------------------------------------------
    // Lazy-loaded settings cache (lives for the lifetime of this singleton)
    // ---------------------------------------------------------------------------

    protected ?array $settings = null;

    // ---------------------------------------------------------------------------
    // Query params that are considered SEO-safe (all others are stripped from
    // canonical URLs)
    // ---------------------------------------------------------------------------

    protected const ALLOWED_PARAMS = ['slug', 'id', 'page', 'cat', 'q', 'photographer', 'tab'];

    // =========================================================================
    // Settings helpers
    // =========================================================================

    /**
     * Load all seo_* settings once and cache them for the request.
     */
    protected function loadSettings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $defaults = [
            'seo_site_name'            => config('app.name', 'My Site'),
            'seo_site_tagline'         => '',
            'seo_site_description'     => '',
            'seo_title_separator'      => '—',
            'seo_default_keywords'     => '',
            'seo_author'               => '',
            'seo_default_robots'       => 'index, follow',
            'seo_theme_color'          => '#212529',
            'seo_og_default_image'     => '',
            'seo_twitter_card_type'    => 'summary_large_image',
            'seo_twitter_site'         => '',
            'seo_social_facebook'      => '',
            'seo_social_instagram'     => '',
            'seo_social_twitter'       => '',
            'seo_social_youtube'       => '',
            'seo_social_line'          => '',
            'seo_google_analytics'     => '',
            'seo_google_verification'  => '',
            'seo_facebook_verification'=> '',
            'seo_custom_head_code'     => '',
            'seo_favicon'              => '',
            'seo_robots_txt'           => '',
            'seo_og_locale'            => 'th_TH',
        ];

        $loaded = [];
        foreach (array_keys($defaults) as $key) {
            $loaded[$key] = AppSetting::get($key, $defaults[$key]);
        }

        $this->settings = $loaded;
        return $this->settings;
    }

    /**
     * Get a single SEO setting by key.
     */
    protected function setting(string $key, $default = ''): string
    {
        return (string) ($this->loadSettings()[$key] ?? $default);
    }

    // =========================================================================
    // Fluent setters
    // =========================================================================

    /**
     * Bulk-set multiple SEO properties at once.
     *
     * Recognised keys: title, description, keywords, canonical, image, type, robots
     */
    public function set(array $data): self
    {
        foreach ($data as $key => $value) {
            if (method_exists($this, $key)) {
                $this->{$key}((string) $value);
            }
        }
        return $this;
    }

    public function title(string $title): self
    {
        $this->pageTitle = $title;
        return $this;
    }

    /**
     * Set the meta description. Auto-trimmed to 160 chars.
     */
    public function description(string $desc): self
    {
        $this->description = mb_substr(trim(strip_tags($desc)), 0, 160);
        return $this;
    }

    public function keywords(string $keywords): self
    {
        $this->keywords = $keywords;
        return $this;
    }

    /**
     * Set a canonical URL, stripping non-SEO query parameters.
     */
    public function canonical(string $url): self
    {
        $this->canonical = $this->sanitiseCanonical($url);
        return $this;
    }

    public function image(string $url): self
    {
        $this->image = $url;
        return $this;
    }

    /**
     * Set the og:type value (default: 'website').
     */
    public function type(string $type): self
    {
        $this->ogType = $type;
        return $this;
    }

    public function robots(string $robots): self
    {
        $this->robots = $robots;
        return $this;
    }

    // =========================================================================
    // Breadcrumbs
    // =========================================================================

    public function breadcrumb(string $name, string $url = ''): self
    {
        $this->breadcrumbs[] = ['name' => $name, 'url' => $url];
        return $this;
    }

    public function setBreadcrumbs(array $items): self
    {
        $this->breadcrumbs = $items;
        return $this;
    }

    // =========================================================================
    // JSON-LD Schema
    // =========================================================================

    public function addJsonLd(array $schema): self
    {
        $this->jsonLdSchemas[] = $schema;
        return $this;
    }

    /**
     * Add a WebSite schema with a SearchAction (sitelinks search box).
     */
    public function websiteSchema(): self
    {
        $appUrl  = rtrim(config('app.url', ''), '/');
        $siteName = $this->setting('seo_site_name');

        return $this->addJsonLd([
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $siteName,
            'url'      => $appUrl,
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $appUrl . '/search?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }

    /**
     * Add an Event schema (with Offer).
     *
     * @param array $event  Keys: name, description, startDate, endDate,
     *                      location, image, url, price, currency, availability
     */
    public function eventSchema(array $event): self
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Event',
            'name'        => $event['name']        ?? '',
            'description' => $event['description'] ?? '',
            'startDate'   => $event['startDate']   ?? '',
            'endDate'     => $event['endDate']      ?? ($event['startDate'] ?? ''),
            'image'       => $event['image']        ?? '',
            'url'         => $event['url']          ?? '',
            'location'    => [
                '@type' => 'Place',
                'name'  => $event['location'] ?? '',
            ],
        ];

        if (!empty($event['price'])) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => $event['price'],
                'priceCurrency' => $event['currency']     ?? 'THB',
                'availability'  => $event['availability'] ?? 'https://schema.org/InStock',
                'url'           => $event['url']          ?? '',
            ];
        }

        return $this->addJsonLd($schema);
    }

    /**
     * Add a Person schema for a photographer.
     *
     * @param array $photographer  Keys: name, description, image, url, email, telephone
     */
    public function personSchema(array $photographer): self
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Person',
            'name'        => $photographer['name']        ?? '',
            'description' => $photographer['description'] ?? '',
            'image'       => $photographer['image']       ?? '',
            'url'         => $photographer['url']         ?? '',
        ];

        if (!empty($photographer['email'])) {
            $schema['email'] = $photographer['email'];
        }
        if (!empty($photographer['telephone'])) {
            $schema['telephone'] = $photographer['telephone'];
        }

        return $this->addJsonLd($schema);
    }

    /**
     * Add an Organization schema, including social sameAs links.
     */
    public function organizationSchema(): self
    {
        $appUrl   = rtrim(config('app.url', ''), '/');
        $sameAs   = [];

        foreach (['seo_social_facebook', 'seo_social_instagram', 'seo_social_twitter',
                  'seo_social_youtube', 'seo_social_line'] as $key) {
            $val = $this->setting($key);
            if ($val !== '') {
                $sameAs[] = $val;
            }
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $this->setting('seo_site_name'),
            'url'      => $appUrl,
            'logo'     => [
                '@type' => 'ImageObject',
                'url'   => $this->setting('seo_og_default_image'),
            ],
        ];

        if (!empty($sameAs)) {
            $schema['sameAs'] = $sameAs;
        }

        return $this->addJsonLd($schema);
    }

    /**
     * Add a Product schema (for digital products).
     */
    public function productSchema(array $product): self
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $product['name'] ?? '',
            'description' => $product['description'] ?? '',
            'image'       => $product['image'] ?? $this->setting('seo_og_default_image'),
        ];

        if (!empty($product['price'])) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => (string) $product['price'],
                'priceCurrency' => $product['currency'] ?? 'THB',
                'availability'  => $product['availability'] ?? 'https://schema.org/InStock',
                'url'           => $product['url'] ?? null,
            ];
        }

        if (!empty($product['brand'])) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $product['brand']];
        }

        if (!empty($product['sku'])) {
            $schema['sku'] = $product['sku'];
        }

        if (!empty($product['rating']) && !empty($product['review_count'])) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => (string) $product['rating'],
                'reviewCount' => (int) $product['review_count'],
            ];
        }

        return $this->addJsonLd($schema);
    }

    /**
     * Add a BlogPosting schema for blog articles.
     */
    public function blogPostSchema(array $post): self
    {
        $appUrl = rtrim(config('app.url', ''), '/');

        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => $post['type'] ?? 'BlogPosting',
            'headline'      => $post['title'] ?? '',
            'description'   => $post['description'] ?? '',
            'url'           => $post['url'] ?? null,
            'datePublished' => $post['published_at'] ?? null,
            'dateModified'  => $post['modified_at'] ?? $post['published_at'] ?? null,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => $post['url'] ?? null,
            ],
        ];

        if (!empty($post['image'])) {
            $schema['image'] = is_array($post['image']) ? $post['image'] : [$post['image']];
        }

        if (!empty($post['author'])) {
            $schema['author'] = [
                '@type' => 'Person',
                'name'  => $post['author'],
            ];
        }

        if (!empty($post['word_count'])) {
            $schema['wordCount'] = (int) $post['word_count'];
        }

        $schema['publisher'] = [
            '@type' => 'Organization',
            'name'  => $this->setting('seo_site_name'),
            'logo'  => [
                '@type' => 'ImageObject',
                'url'   => $this->setting('seo_og_default_image'),
            ],
        ];

        return $this->addJsonLd($schema);
    }

    /**
     * Add an FAQPage schema.
     *
     * @param array $faqs Array of ['question' => ..., 'answer' => ...]
     */
    public function faqSchema(array $faqs): self
    {
        if (empty($faqs)) return $this;

        $mainEntity = [];
        foreach ($faqs as $faq) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name'  => $faq['question'] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $faq['answer'] ?? '',
                ],
            ];
        }

        return $this->addJsonLd([
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $mainEntity,
        ]);
    }

    /**
     * Add a Review schema.
     */
    public function reviewSchema(array $review): self
    {
        $schema = [
            '@context'     => 'https://schema.org',
            '@type'        => 'Review',
            'reviewRating' => [
                '@type'       => 'Rating',
                'ratingValue' => (string) ($review['rating'] ?? 5),
                'bestRating'  => '5',
                'worstRating' => '1',
            ],
            'author' => [
                '@type' => 'Person',
                'name'  => $review['author_name'] ?? 'ลูกค้า',
            ],
            'reviewBody'    => $review['comment'] ?? '',
            'datePublished' => $review['created_at'] ?? null,
        ];

        if (!empty($review['item_name'])) {
            $schema['itemReviewed'] = [
                '@type' => $review['item_type'] ?? 'Thing',
                'name'  => $review['item_name'],
            ];
        }

        return $this->addJsonLd($schema);
    }

    /**
     * Add a BreadcrumbList schema (from currently set breadcrumbs).
     */
    public function breadcrumbSchema(): self
    {
        if (empty($this->breadcrumbs)) return $this;

        $items = [];
        foreach ($this->breadcrumbs as $i => $b) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $b['name'] ?? '',
                'item'     => $b['url'] ?? null,
            ];
        }

        return $this->addJsonLd([
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ]);
    }

    /**
     * Add a LocalBusiness schema.
     */
    public function localBusinessSchema(array $overrides = []): self
    {
        $appUrl = rtrim(config('app.url', ''), '/');

        $schema = array_merge([
            '@context'    => 'https://schema.org',
            '@type'       => 'LocalBusiness',
            'name'        => $this->setting('seo_site_name'),
            'description' => $this->setting('seo_default_description'),
            'url'         => $appUrl,
            'image'       => $this->setting('seo_og_default_image'),
            'telephone'   => $this->setting('seo_business_phone'),
            'address'     => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $this->setting('seo_business_address'),
                'addressLocality' => $this->setting('seo_business_city', 'Bangkok'),
                'addressCountry'  => $this->setting('seo_business_country', 'TH'),
            ],
        ], $overrides);

        // priceRange: only emit if admin explicitly set seo_business_price_range
        // in app_settings. Default-empty avoids guessing a number that varies
        // by photographer + event size, which would mislead Google's SERP.
        $configuredPriceRange = $this->setting('seo_business_price_range', '');
        if ($configuredPriceRange !== '' && !isset($schema['priceRange'])) {
            $schema['priceRange'] = $configuredPriceRange;
        }

        return $this->addJsonLd($schema);
    }

    // =========================================================================
    // Output helpers
    // =========================================================================

    /**
     * Return the full page <title> string.
     *
     * Homepage  : "{site_name} {separator} {tagline}"
     * Other page: "{title} {separator} {site_name}"
     */
    public function getTitle(): string
    {
        $siteName  = $this->setting('seo_site_name');
        $tagline   = $this->setting('seo_site_tagline');
        $separator = $this->setting('seo_title_separator', '—');

        if ($this->pageTitle === null || $this->pageTitle === '') {
            // Homepage title
            return $tagline !== ''
                ? "{$siteName} {$separator} {$tagline}"
                : $siteName;
        }

        return "{$this->pageTitle} {$separator} {$siteName}";
    }

    /**
     * Build and return the full HTML snippet to inject inside <head>.
     *
     * Output order:
     *  1. <title>
     *  2. meta description
     *  3. meta keywords
     *  4. meta robots
     *  5. canonical
     *  6. Open Graph tags
     *  7. Twitter Card tags
     *  8. meta author
     *  9. meta theme-color
     * 10. favicon
     * 11. JSON-LD scripts (breadcrumb auto-generated when breadcrumbs are set)
     * 12. Google Analytics gtag.js
     * 13. Verification meta tags
     * 14. Custom head code (raw, unescaped)
     */
    public function render(): string
    {
        $s   = $this->loadSettings();
        $out = [];

        // ── 4-layer SEO cascade ────────────────────────────────────────
        //
        //   layer 1 (highest)  Admin override   — DB-stored, /admin/seo CMS
        //   layer 2            Controller       — $seo->set([...]) calls
        //   layer 3            Auto-generator   — config-driven Thai templates
        //   layer 4 (fallback) Site settings    — seo_site_description etc.
        //
        // Each field independently picks the first non-empty layer, so
        // a controller can fix just the title and get auto-gen for
        // description; an admin override of canonical leaves the auto
        // structured data intact; etc.
        $override = null;
        $auto     = [];
        try {
            $override = app(\App\Services\Seo\SeoOverrideResolver::class)->forCurrentRoute();
            // Auto-generator is suppressed entirely on admin/api/profile
            // paths via the seo.suppress flag set by AdminNoindex.
            $auto = app(\App\Services\Seo\AutoSeoGenerator::class)->generate();
        } catch (\Throwable) {
            // Either layer failing must NOT break rendering — fall back
            // to whatever earlier layers produced.
        }

        $title = $override?->title
            ? $override->title . ' ' . ($s['seo_title_separator'] ?: '—') . ' ' . $s['seo_site_name']
            : ($this->pageTitle
                ? $this->getTitle()
                : (!empty($auto['title'])
                    ? $auto['title'] . ' ' . ($s['seo_title_separator'] ?: '—') . ' ' . $s['seo_site_name']
                    : $this->getTitle()));

        $description = $override?->description
            ?: ($this->description
                ?: ($auto['description'] ?? mb_substr($s['seo_site_description'], 0, 160)));

        $keywords  = $override?->keywords      ?: ($this->keywords  ?: ($auto['keywords']    ?? $s['seo_default_keywords']));
        $robots    = $override?->meta_robots   ?: ($this->robots    ?: ($auto['meta_robots'] ?? $s['seo_default_robots']));
        $image     = $override?->og_image      ?: ($this->image     ?: $s['seo_og_default_image']);
        $canonical = $override?->canonical_url ?: ($this->canonical ?: $this->sanitiseCanonical($this->currentUrl()));
        $siteName  = $s['seo_site_name'];
        $locale    = $s['seo_og_locale'] ?: 'th_TH';

        // og_type override > controller-set type > auto-gen > 'website' default.
        // Detect "controller didn't touch it" by comparing to the property's
        // default value ('website'); auto then wins for events/products/articles.
        if ($override?->og_type) {
            $this->ogType = $override->og_type;
        } elseif ($this->ogType === 'website' && !empty($auto['og_type'])) {
            $this->ogType = $auto['og_type'];
        }

        // OG description: prefer override, then controller-set description (longer),
        // then auto, else settings default — same four-layer cascade.
        $ogDescription = $override?->og_description
            ?: ($this->description
                ? mb_substr($this->description, 0, 200)
                : (!empty($auto['description'])
                    ? mb_substr($auto['description'], 0, 200)
                    : mb_substr($s['seo_site_description'], 0, 200)));

        // Merge auto-generated structured data with admin override + controller.
        // Order: controller-registered (already in $this->jsonLdSchemas)
        //   → auto-generated → admin override (so admin wins for duplicate @type).
        if (!empty($auto['structured_data']) && is_array($auto['structured_data'])) {
            foreach ($auto['structured_data'] as $schema) {
                if (is_array($schema) && !empty($schema['@type'])) {
                    $this->jsonLdSchemas[] = $schema;
                }
            }
        }
        if ($override && is_array($override->structured_data)) {
            foreach ($override->structured_data as $schema) {
                if (is_array($schema) && !empty($schema['@type'])) {
                    $this->jsonLdSchemas[] = $schema;
                }
            }
        }

        // ------------------------------------------------------------------
        // 1. <title>
        // ------------------------------------------------------------------
        $out[] = '<title>' . e($title) . '</title>';

        // ------------------------------------------------------------------
        // 2. Meta description
        // ------------------------------------------------------------------
        if ($description !== '') {
            $out[] = '<meta name="description" content="' . e($description) . '">';
        }

        // ------------------------------------------------------------------
        // 3. Meta keywords
        // ------------------------------------------------------------------
        if ($keywords !== '') {
            $out[] = '<meta name="keywords" content="' . e($keywords) . '">';
        }

        // ------------------------------------------------------------------
        // 4. Meta robots
        // ------------------------------------------------------------------
        if ($robots !== '') {
            $out[] = '<meta name="robots" content="' . e($robots) . '">';
        }

        // ------------------------------------------------------------------
        // 5. Canonical
        // ------------------------------------------------------------------
        if ($canonical !== '') {
            $out[] = '<link rel="canonical" href="' . e($canonical) . '">';
        }

        // ------------------------------------------------------------------
        // 6. Open Graph tags
        // ------------------------------------------------------------------
        $out[] = '<meta property="og:title" content="' . e($title) . '">';
        $out[] = '<meta property="og:type" content="' . e($this->ogType) . '">';

        if ($ogDescription !== '') {
            $out[] = '<meta property="og:description" content="' . e($ogDescription) . '">';
        }
        if ($image !== '') {
            $out[] = '<meta property="og:image" content="' . e($image) . '">';
        }
        if ($canonical !== '') {
            $out[] = '<meta property="og:url" content="' . e($canonical) . '">';
        }
        $out[] = '<meta property="og:locale" content="' . e($locale) . '">';
        $out[] = '<meta property="og:site_name" content="' . e($siteName) . '">';

        // ------------------------------------------------------------------
        // 7. Twitter Card tags
        // ------------------------------------------------------------------
        $twitterCard = $s['seo_twitter_card_type'] ?: 'summary_large_image';
        $out[] = '<meta name="twitter:card" content="' . e($twitterCard) . '">';
        $out[] = '<meta name="twitter:title" content="' . e($title) . '">';

        if ($ogDescription !== '') {
            $out[] = '<meta name="twitter:description" content="' . e($ogDescription) . '">';
        }
        if ($image !== '') {
            $out[] = '<meta name="twitter:image" content="' . e($image) . '">';
        }
        if ($s['seo_twitter_site'] !== '') {
            $out[] = '<meta name="twitter:site" content="' . e($s['seo_twitter_site']) . '">';
        }

        // ------------------------------------------------------------------
        // 8. Author
        // ------------------------------------------------------------------
        if ($s['seo_author'] !== '') {
            $out[] = '<meta name="author" content="' . e($s['seo_author']) . '">';
        }

        // ------------------------------------------------------------------
        // 9. Theme color
        // ------------------------------------------------------------------
        $themeColor = $s['seo_theme_color'] ?: '#212529';
        $out[] = '<meta name="theme-color" content="' . e($themeColor) . '">';

        // ------------------------------------------------------------------
        // 10. Favicon
        // ------------------------------------------------------------------
        if ($s['seo_favicon'] !== '') {
            $out[] = '<link rel="icon" href="' . e($s['seo_favicon']) . '">';
        }

        // ------------------------------------------------------------------
        // 11. JSON-LD scripts
        // ------------------------------------------------------------------

        // Auto-generate BreadcrumbList when breadcrumbs are set
        if (!empty($this->breadcrumbs)) {
            $listElements = [];
            foreach ($this->breadcrumbs as $position => $crumb) {
                $item = [
                    '@type'    => 'ListItem',
                    'position' => $position + 1,
                    'name'     => $crumb['name'],
                ];
                if (!empty($crumb['url'])) {
                    $item['item'] = $crumb['url'];
                }
                $listElements[] = $item;
            }

            $breadcrumbSchema = [
                '@context'        => 'https://schema.org',
                '@type'           => 'BreadcrumbList',
                'itemListElement' => $listElements,
            ];

            // Prepend breadcrumb schema so it appears first
            array_unshift($this->jsonLdSchemas, $breadcrumbSchema);
        }

        foreach ($this->jsonLdSchemas as $schema) {
            $json  = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $out[] = '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
        }

        // ------------------------------------------------------------------
        // 12. Google Analytics gtag.js
        // ------------------------------------------------------------------
        $gaId = $s['seo_google_analytics'];
        if ($gaId !== '') {
            $gaId  = e($gaId);
            $out[] = <<<HTML
<script async src="https://www.googletagmanager.com/gtag/js?id={$gaId}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$gaId}');
</script>
HTML;
        }

        // ------------------------------------------------------------------
        // 13. Verification meta tags
        // ------------------------------------------------------------------
        if ($s['seo_google_verification'] !== '') {
            $out[] = '<meta name="google-site-verification" content="' . e($s['seo_google_verification']) . '">';
        }
        if ($s['seo_facebook_verification'] !== '') {
            $out[] = '<meta name="facebook-domain-verification" content="' . e($s['seo_facebook_verification']) . '">';
        }

        // ------------------------------------------------------------------
        // 14. Custom head code (raw, unescaped)
        // ------------------------------------------------------------------
        if ($s['seo_custom_head_code'] !== '') {
            $out[] = $s['seo_custom_head_code'];
        }

        return implode("\n", $out) . "\n";
    }

    // =========================================================================
    // Sitemap
    // =========================================================================

    /**
     * Generate an XML sitemap by querying the relevant database tables.
     */
    public function generateSitemap(): string
    {
        $appUrl = rtrim(config('app.url', ''), '/');
        $now    = now()->toAtomString();

        $urls = [];

        // Homepage
        $urls[] = $this->sitemapUrl($appUrl . '/', $now, 'daily', '1.0');

        // Events
        try {
            $events = DB::table('event_events')
                ->whereIn('status', ['active', 'published'])
                ->where('visibility', 'public')
                ->select('slug', 'id', 'updated_at')
                ->get();

            foreach ($events as $event) {
                $identifier = $event->slug ?: $event->id;
                $lastmod    = $event->updated_at ? date('c', strtotime($event->updated_at)) : $now;
                $urls[] = $this->sitemapUrl($appUrl . '/events/' . $identifier, $lastmod, 'weekly', '0.8');
            }
        } catch (\Throwable) {
            // Table may not exist yet; skip gracefully
        }

        // Digital products
        try {
            $products = DB::table('digital_products')
                ->where('status', 'active')
                ->select('slug', 'id', 'updated_at')
                ->get();

            foreach ($products as $product) {
                $identifier = $product->slug ?: $product->id;
                $lastmod    = $product->updated_at ? date('c', strtotime($product->updated_at)) : $now;
                $urls[] = $this->sitemapUrl($appUrl . '/products/' . $identifier, $lastmod, 'weekly', '0.7');
            }
        } catch (\Throwable) {
            // Skip gracefully
        }

        // Event categories
        try {
            $categories = DB::table('event_categories')
                ->where('status', 'active')
                ->select('slug', 'id', 'updated_at')
                ->get();

            foreach ($categories as $category) {
                $identifier = $category->slug ?: $category->id;
                $lastmod    = $category->updated_at ? date('c', strtotime($category->updated_at)) : $now;
                $urls[] = $this->sitemapUrl($appUrl . '/categories/' . $identifier, $lastmod, 'weekly', '0.5');
            }
        } catch (\Throwable) {}

        // Blog posts (high SEO priority)
        try {
            $posts = DB::table('blog_posts')
                ->where('status', 'published')
                ->where('visibility', 'public')
                ->whereNull('deleted_at')
                ->select('slug', 'id', 'updated_at', 'published_at')
                ->get();

            foreach ($posts as $post) {
                $identifier = $post->slug ?: $post->id;
                $lastmod    = $post->updated_at ? date('c', strtotime($post->updated_at)) : $now;
                $urls[] = $this->sitemapUrl($appUrl . '/blog/' . $identifier, $lastmod, 'monthly', '0.7');
            }

            // Blog index
            $urls[] = $this->sitemapUrl($appUrl . '/blog', $now, 'daily', '0.8');
        } catch (\Throwable) {}

        // Blog categories
        try {
            $blogCats = DB::table('blog_categories')->where('is_active', 1)->select('slug', 'id', 'updated_at')->get();
            foreach ($blogCats as $cat) {
                $identifier = $cat->slug ?: $cat->id;
                $lastmod = $cat->updated_at ? date('c', strtotime($cat->updated_at)) : $now;
                $urls[] = $this->sitemapUrl($appUrl . '/blog/category/' . $identifier, $lastmod, 'weekly', '0.6');
            }
        } catch (\Throwable) {}

        // Photographer profiles
        try {
            $photographers = DB::table('photographer_profiles')
                ->where('status', 'approved')
                ->select('id', 'photographer_code', 'updated_at')
                ->get();

            foreach ($photographers as $p) {
                $lastmod = $p->updated_at ? date('c', strtotime($p->updated_at)) : $now;
                $urls[] = $this->sitemapUrl($appUrl . '/photographers/' . $p->id, $lastmod, 'weekly', '0.6');
            }
        } catch (\Throwable) {}

        // Static pages — keep priority low; these are intent-funnels, not
        // primary content. /sell-photos is the B2B landing → priority 0.7.
        $staticPages = [
            ['login',       '0.3'],
            ['register',    '0.3'],
            ['contact',     '0.3'],
            ['help',        '0.4'],
            ['sell-photos', '0.7'],   // photographer acquisition page
        ];
        foreach ($staticPages as [$page, $prio]) {
            $urls[] = $this->sitemapUrl($appUrl . '/' . $page, $now, 'monthly', $prio);
        }

        // Programmatic SEO landings — niche × province grid.
        // Reads the same config the controller serves from, so the
        // sitemap can never drift from the actual rendered pages.
        try {
            $cfg = config('seo_landings');
            if (is_array($cfg) && !empty($cfg['niches'])) {
                foreach ($cfg['niches'] as $nSlug => $_) {
                    // Nationwide variant — high priority (broad keyword).
                    $urls[] = $this->sitemapUrl(
                        $appUrl . '/pro/' . $nSlug, $now, 'weekly', '0.8',
                    );
                    foreach (($cfg['provinces'] ?? []) as $pSlug => $_p) {
                        // Province-scoped variant — long-tail, slightly lower priority.
                        $urls[] = $this->sitemapUrl(
                            $appUrl . '/pro/' . $nSlug . '/' . $pSlug,
                            $now, 'weekly', '0.7',
                        );
                    }
                }
            }
        } catch (\Throwable) {}

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= implode("\n", $urls);
        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    /**
     * Build a single <url> block for the sitemap.
     */
    protected function sitemapUrl(string $loc, string $lastmod, string $changefreq, string $priority): string
    {
        return "  <url>\n"
            . '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . "\n"
            . '    <lastmod>' . $lastmod . '</lastmod>' . "\n"
            . '    <changefreq>' . $changefreq . '</changefreq>' . "\n"
            . '    <priority>' . $priority . '</priority>' . "\n"
            . '  </url>';
    }

    // =========================================================================
    // Robots.txt
    // =========================================================================

    /**
     * Return the robots.txt body.
     *
     * Uses the `seo_robots_txt` setting when set, otherwise returns the
     * sensible default that disallows admin/photographer/api paths.
     */
    public function generateRobotsTxt(): string
    {
        $custom = $this->setting('seo_robots_txt');
        if ($custom !== '') {
            return $custom;
        }

        $appUrl = rtrim(config('app.url', ''), '/');

        // Default robots.txt:
        //   - / public marketplace pages crawlable
        //   - /admin/ and /photographer/ — authenticated dashboards, no SEO value
        //   - /api/ — JSON endpoints, no SEO value, plus rate-limited (don't waste budget)
        //   - /cart, /checkout, /profile — user-private state, would create infinite
        //     URL variants from query params (UTM etc.) and Googlebot loves those
        //   - Crawl-delay = 1 keeps polite bots from overwhelming dev / staging
        return "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /admin/\n"
            . "Disallow: /photographer/\n"
            . "Disallow: /api/\n"
            . "Disallow: /cart\n"
            . "Disallow: /checkout\n"
            . "Disallow: /profile\n"
            . "Disallow: /search?\n"
            . "Allow: /pro/\n"
            . "Crawl-delay: 1\n"
            . "\n"
            . "Sitemap: {$appUrl}/sitemap.xml\n";
    }

    // =========================================================================
    // Reset (for testing / CLI usage)
    // =========================================================================

    /**
     * Clear all per-request state so the service can be reused.
     */
    public function reset(): void
    {
        $this->pageTitle     = null;
        $this->description   = null;
        $this->keywords      = null;
        $this->canonical     = null;
        $this->image         = null;
        $this->ogType        = 'website';
        $this->robots        = null;
        $this->breadcrumbs   = [];
        $this->jsonLdSchemas = [];
        // Note: $this->settings is intentionally NOT cleared — settings are
        // stable for the entire request and are already cached by AppSetting.
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Strip non-SEO query parameters from a URL and return the clean canonical.
     */
    protected function sanitiseCanonical(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $query = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $params);
            $allowed = array_intersect_key($params, array_flip(self::ALLOWED_PARAMS));
            if (!empty($allowed)) {
                $query = '?' . http_build_query($allowed);
            }
        }

        $scheme    = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host      = $parts['host']     ?? '';
        $port      = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path      = $parts['path']     ?? '';
        $fragment  = '';  // never include fragments in canonical

        return $scheme . $host . $port . $path . $query . $fragment;
    }

    /**
     * Return the current full request URL, falling back to APP_URL.
     */
    protected function currentUrl(): string
    {
        try {
            if (app()->runningInConsole()) {
                return rtrim(config('app.url', ''), '/') . '/';
            }
            return request()->url();
        } catch (\Throwable) {
            return rtrim(config('app.url', ''), '/') . '/';
        }
    }
}
