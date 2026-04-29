<?php

namespace App\Services\Seo;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Generate SEO meta + structured data automatically from route + DB.
 *
 * Position in the cascade
 * -----------------------
 * SeoService::render() calls this AFTER consulting:
 *   1. seo_pages override (admin manually overrode)
 *   2. controller-set values ($seo->set([...]))
 *
 * If neither produced a title, this generator fills in from the
 * config/seo_templates.php template + per-route context lookup.
 *
 * Per-route context extractors live as small private methods below.
 * Adding a new route: add a template entry + one `extract*()` method.
 *
 * Suppression
 * -----------
 * Returns an empty bag immediately if:
 *   - the request has `seo.suppress=true` (set by AdminNoindex middleware)
 *   - the route name matches any prefix in config seo_templates.suppress_routes
 *
 * That guarantees zero auto-SEO leaks onto admin/photographer pages
 * even if a template entry mistakenly references one.
 */
class AutoSeoGenerator
{
    private const TITLE_MAX = 60;
    private const DESC_MAX  = 160;

    /**
     * Generate the full SEO bag for the current request.
     *
     * @return array{title?:string,description?:string,keywords?:string,
     *               og_type?:string,meta_robots?:string,structured_data?:array}
     */
    public function generate(): array
    {
        if ($this->isSuppressed()) {
            return [];
        }

        $route = request()->route();
        if (!$route || !$route->getName()) {
            return $this->renderTemplate(config('seo_templates.default') ?? [], $this->baseContext());
        }

        $routeName = $route->getName();
        $templates = config('seo_templates.routes', []);
        $template  = $templates[$routeName] ?? config('seo_templates.default') ?? [];

        // Build context — base + route-specific extractor.
        $context = $this->baseContext()
            + $this->extractContextFor($routeName, $route);

        $bag = $this->renderTemplate($template, $context);

        // Attach structured data — only when the extractor recognised
        // the route well enough to produce one.
        $schema = $this->buildStructuredData($routeName, $context);
        if (!empty($schema)) {
            $bag['structured_data'] = $schema;
        }

        return $bag;
    }

    /* ────────────────────── suppression ────────────────────── */

    private function isSuppressed(): bool
    {
        if (request()->attributes->get('seo.suppress')) return true;

        $route = request()->route();
        $name  = $route?->getName() ?? '';
        foreach (config('seo_templates.suppress_routes', []) as $prefix) {
            if (str_starts_with($name, $prefix)) return true;
        }
        return false;
    }

    /* ────────────────────── template engine ────────────────────── */

    private function renderTemplate(array $template, array $context): array
    {
        $bag = [];
        foreach (['title', 'description', 'keywords', 'og_type', 'meta_robots'] as $field) {
            if (!isset($template[$field])) continue;
            $rendered = $this->substitute($template[$field], $context);
            // Length cap — Google truncates, so we should too.
            if ($field === 'title' && mb_strlen($rendered) > self::TITLE_MAX) {
                $rendered = mb_substr($rendered, 0, self::TITLE_MAX - 1) . '…';
            }
            if ($field === 'description' && mb_strlen($rendered) > self::DESC_MAX) {
                $rendered = mb_substr($rendered, 0, self::DESC_MAX - 1) . '…';
            }
            $bag[$field] = trim($rendered);
        }
        return $bag;
    }

    /**
     * Replace `:placeholder` tokens with values from $context.
     * Missing keys collapse to empty string and we tidy up double
     * separators / leading punctuation that result.
     */
    private function substitute(string $template, array $context): string
    {
        $rendered = preg_replace_callback('/:(\w+)/', function ($m) use ($context) {
            return (string) ($context[$m[1]] ?? '');
        }, $template);

        // Collapse `· ·`, `—  —`, doubled spaces from blank substitutions.
        $rendered = preg_replace('/(\s*[·—|·]\s*){2,}/u', ' · ', $rendered);
        $rendered = preg_replace('/\s{2,}/', ' ', $rendered);
        $rendered = trim($rendered, " ·—|");
        return $rendered;
    }

    /* ────────────────────── context extractors ────────────────────── */

    private function baseContext(): array
    {
        $brand    = AppSetting::get('seo_site_name', config('app.name', 'Loadroop'));
        $tagline  = AppSetting::get('seo_site_tagline', '');

        $page = (int) request()->query('page', 1);
        $pageStr = $page > 1 ? "(หน้า {$page})" : '';

        return [
            'brand'        => $brand,
            'site_tagline' => $tagline,
            'year'         => date('Y'),
            'page'         => $pageStr,
        ];
    }

    /**
     * Dispatch to the right extractor based on route name. Returning an
     * empty array is fine — the template either has no route-specific
     * placeholders, or substitution silently drops them.
     */
    private function extractContextFor(string $routeName, \Illuminate\Routing\Route $route): array
    {
        return match ($routeName) {
            'events.show'           => $this->extractEvent($route),
            'photographers.show'    => $this->extractPhotographer($route),
            'blog.show'             => $this->extractBlogPost($route),
            'products.show'         => $this->extractProduct($route),
            'seo.landing.niche',
            'seo.landing.province'  => $this->extractSeoLanding($route),
            'legal.show'            => $this->extractLegalPage($route),
            default                 => [],
        };
    }

    private function extractEvent(\Illuminate\Routing\Route $route): array
    {
        $param = $route->parameter('slug') ?? $route->parameter('id') ?? $route->parameter('event');
        if (!$param) return [];
        $key = is_object($param) ? $param->getKey() : (string) $param;

        return Cache::remember("seo_auto:event:{$key}", 600, function () use ($key) {
            $event = DB::table('event_events')
                ->where('slug', $key)
                ->orWhere('id', is_numeric($key) ? (int) $key : 0)
                ->first(['id', 'name', 'slug', 'shoot_date', 'location', 'cover_image', 'photographer_id']);
            if (!$event) return [];

            // Photographer name lookup — fallback to photographer_code.
            $photog = DB::table('photographer_profiles as pp')
                ->leftJoin('auth_users as u', 'u.id', '=', 'pp.user_id')
                ->where('pp.user_id', $event->photographer_id)
                ->select(DB::raw('COALESCE(pp.display_name, u.first_name) AS display_name'), 'pp.photographer_code')
                ->first();

            $photoCount = DB::table('event_photos')
                ->where('event_id', $event->id)
                ->whereNull('deleted_at')
                ->count();

            return [
                'event_name'   => (string) $event->name,
                'event_date'   => $event->shoot_date ? \Carbon\Carbon::parse($event->shoot_date)->translatedFormat('j M Y') : '',
                'location'     => (string) ($event->location ?? ''),
                'photo_count'  => number_format($photoCount),
                'photographer' => $photog?->display_name ?: ($photog?->photographer_code ?? ''),
                '_event_id'    => $event->id,
                '_cover'       => $event->cover_image,
            ];
        });
    }

    private function extractPhotographer(\Illuminate\Routing\Route $route): array
    {
        $param = $route->parameter('id');
        if (!$param) return [];
        $userId = is_object($param) ? $param->getKey() : (int) $param;

        return Cache::remember("seo_auto:photog:{$userId}", 600, function () use ($userId) {
            $row = DB::table('photographer_profiles as pp')
                ->leftJoin('auth_users as u', 'u.id', '=', 'pp.user_id')
                ->where('pp.user_id', $userId)
                ->select(
                    DB::raw('COALESCE(pp.display_name, u.first_name) AS display_name'),
                    'pp.photographer_code', 'pp.bio', 'pp.avatar',
                )
                ->first();
            if (!$row) return [];

            $eventCount = DB::table('event_events')->where('photographer_id', $userId)->count();
            return [
                'photographer_name' => (string) $row->display_name,
                'photographer_code' => (string) $row->photographer_code,
                'photographer_bio'  => mb_substr((string) $row->bio, 0, 160),
                'events_count'      => $eventCount,
                'location'          => '',  // photographer_profiles doesn't carry location yet
                '_avatar'           => $row->avatar,
            ];
        });
    }

    private function extractBlogPost(\Illuminate\Routing\Route $route): array
    {
        $slug = $route->parameter('slug');
        if (!$slug) return [];
        $key = is_object($slug) ? $slug->slug : (string) $slug;

        return Cache::remember("seo_auto:blog:{$key}", 600, function () use ($key) {
            $row = DB::table('blog_posts')->where('slug', $key)->first(['title', 'excerpt', 'tags']);
            return $row ? [
                'post_title'   => (string) $row->title,
                'post_excerpt' => mb_substr(strip_tags((string) $row->excerpt), 0, 160),
                'post_tags'    => is_string($row->tags) ? trim($row->tags, '[]"') : '',
            ] : [];
        });
    }

    private function extractProduct(\Illuminate\Routing\Route $route): array
    {
        $slug = $route->parameter('slug');
        if (!$slug) return [];
        $key = is_object($slug) ? $slug->slug : (string) $slug;

        return Cache::remember("seo_auto:product:{$key}", 600, function () use ($key) {
            $row = DB::table('digital_products')->where('slug', $key)->first(['name', 'price', 'description']);
            return $row ? [
                'product_name'        => (string) $row->name,
                'product_price'       => number_format((float) $row->price, 0),
                'product_description' => mb_substr(strip_tags((string) $row->description), 0, 160),
            ] : [];
        });
    }

    private function extractSeoLanding(\Illuminate\Routing\Route $route): array
    {
        $cfg     = config('seo_landings');
        $niche   = $route->parameter('niche');
        $province = $route->parameter('province');

        $nicheCfg    = $cfg['niches'][$niche] ?? null;
        $provinceCfg = $province ? ($cfg['provinces'][$province] ?? null) : null;

        if (!$nicheCfg) return [];

        $scope = $provinceCfg['short'] ?? 'ทั่วประเทศ';
        return [
            'niche'           => $niche,
            'niche_label'     => $nicheCfg['label'],
            'plural'          => $nicheCfg['plural'] ?? $nicheCfg['label'],
            'pretty_keyword'  => $nicheCfg['pretty_keyword'] ?? $nicheCfg['label'],
            'scope'           => $scope,
            'province'        => $provinceCfg['short'] ?? '',
            'description_pat' => str_replace(':scope:', $scope, $nicheCfg['description_pat'] ?? ''),
            'long_tail_csv'   => implode(', ', $nicheCfg['long_tail'] ?? []),
        ];
    }

    private function extractLegalPage(\Illuminate\Routing\Route $route): array
    {
        $slug = $route->parameter('slug');
        if (!$slug) return [];
        $key = is_object($slug) ? $slug->slug : (string) $slug;
        return Cache::remember("seo_auto:legal:{$key}", 3600, function () use ($key) {
            $row = DB::table('legal_pages')->where('slug', $key)->first(['title', 'meta_description']);
            return $row ? [
                'legal_title'   => (string) $row->title,
                'legal_excerpt' => (string) ($row->meta_description ?? ''),
            ] : [];
        });
    }

    /* ────────────────── auto structured data (Phase 5) ────────────────── */

    /**
     * Build a JSON-LD schema array based on what we know about the
     * current page. SeoService merges these with controller-registered
     * schemas — they're additive, not replacing.
     */
    private function buildStructuredData(string $routeName, array $ctx): array
    {
        $appUrl = rtrim(config('app.url', ''), '/');
        $brand  = $ctx['brand'] ?? 'Loadroop';

        return match ($routeName) {

            'events.show' => empty($ctx['_event_id']) ? [] : [[
                '@context'    => 'https://schema.org',
                '@type'       => 'Event',
                'name'        => $ctx['event_name']   ?? '',
                'description' => $ctx['photographer'] ? "ถ่ายโดย {$ctx['photographer']}" : '',
                'startDate'   => $ctx['event_date']   ?? '',
                'location'    => empty($ctx['location']) ? null : [
                    '@type' => 'Place',
                    'name'  => $ctx['location'],
                ],
                'image'       => $ctx['_cover'] ?? null,
                'organizer'   => [
                    '@type' => 'Organization',
                    'name'  => $brand,
                ],
            ]],

            'photographers.show' => empty($ctx['photographer_name']) ? [] : [[
                '@context' => 'https://schema.org',
                '@type'    => 'Person',
                'name'     => $ctx['photographer_name'],
                'jobTitle' => 'ช่างภาพ',
                'identifier' => $ctx['photographer_code'] ?? '',
                'description' => $ctx['photographer_bio'] ?? '',
                'image'    => $ctx['_avatar'] ?? null,
                'worksFor' => [
                    '@type' => 'Organization',
                    'name'  => $brand,
                ],
            ]],

            'products.show' => empty($ctx['product_name']) ? [] : [[
                '@context'    => 'https://schema.org',
                '@type'       => 'Product',
                'name'        => $ctx['product_name'],
                'description' => $ctx['product_description'] ?? '',
                'offers'      => [
                    '@type'         => 'Offer',
                    'price'         => $ctx['product_price'] ?? '0',
                    'priceCurrency' => 'THB',
                    'availability'  => 'https://schema.org/InStock',
                ],
            ]],

            'seo.landing.province' => empty($ctx['province']) ? [] : [[
                '@context'        => 'https://schema.org',
                '@type'           => 'LocalBusiness',
                'name'            => "{$brand} · {$ctx['niche_label']} {$ctx['province']}",
                'description'     => $ctx['description_pat'] ?? '',
                'address'         => [
                    '@type'           => 'PostalAddress',
                    'addressLocality' => $ctx['province'],
                    'addressCountry'  => 'TH',
                ],
                // priceRange intentionally omitted — Google accepts the
                // schema without it, and per-photographer/per-event price
                // variance would make any single range misleading.
                'url' => $appUrl . request()->getPathInfo(),
            ]],

            'blog.show' => empty($ctx['post_title']) ? [] : [[
                '@context'    => 'https://schema.org',
                '@type'       => 'Article',
                'headline'    => $ctx['post_title'],
                'description' => $ctx['post_excerpt'] ?? '',
                'inLanguage'  => 'th-TH',
                'publisher'   => [
                    '@type' => 'Organization',
                    'name'  => $brand,
                ],
            ]],

            default => [],
        };
    }
}
