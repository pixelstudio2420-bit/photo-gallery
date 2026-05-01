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
            // Pull every column the Schema.org Event payload could
            // populate — start/end times, venue, organizer, attendees,
            // contact, links — so the generator below can emit the
            // richest JSON-LD without re-querying.
            $event = DB::table('event_events')
                ->where('slug', $key)
                ->orWhere('id', is_numeric($key) ? (int) $key : 0)
                ->first([
                    'id', 'name', 'slug', 'shoot_date', 'location', 'cover_image',
                    'photographer_id',
                    'start_time', 'end_time',
                    'venue_name', 'organizer', 'event_type', 'expected_attendees',
                    'highlights', 'tags',
                    'contact_phone', 'contact_email', 'website_url', 'facebook_url',
                ]);
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

            // Build ISO 8601 dates here once. Mirrors Event::startDateIso()
            // exactly (same +07:00 tag, same default 00:00:00) — kept
            // inline because we're reading from DB::table not Eloquent.
            $isoStart = null;
            $isoEnd   = null;
            if ($event->shoot_date) {
                $date  = \Carbon\Carbon::parse($event->shoot_date)->toDateString();
                $start = $event->start_time ? substr((string) $event->start_time, 0, 8) : '00:00:00';
                $isoStart = "{$date}T{$start}+07:00";
                if ($event->end_time) {
                    $isoEnd = "{$date}T" . substr((string) $event->end_time, 0, 8) . "+07:00";
                }
            }

            $highlights = is_string($event->highlights) ? json_decode($event->highlights, true) : ($event->highlights ?? null);
            $tags       = is_string($event->tags)       ? json_decode($event->tags, true)       : ($event->tags ?? null);

            return [
                'event_name'   => (string) $event->name,
                'event_date'   => $event->shoot_date ? \Carbon\Carbon::parse($event->shoot_date)->translatedFormat('j M Y') : '',
                'location'     => (string) ($event->location ?? ''),
                'photo_count'  => number_format($photoCount),
                'photographer' => $photog?->display_name ?: ($photog?->photographer_code ?? ''),
                '_event_id'    => $event->id,
                '_cover'       => $event->cover_image,
                // Enriched fields (2026-05-01) — used by the
                // structured-data builder below + ev-specific keyword
                // augmentation. Empty strings/arrays when missing
                // so :placeholder substitution in seo_templates.php
                // never leaks raw `:venue_name` to the user.
                'venue_name'         => (string) ($event->venue_name ?? ''),
                'organizer'          => (string) ($event->organizer ?? ''),
                'event_type'         => (string) ($event->event_type ?? ''),
                'expected_attendees' => (int) ($event->expected_attendees ?? 0),
                'event_tags'         => is_array($tags) ? implode(', ', array_slice($tags, 0, 8)) : '',
                '_iso_start'         => $isoStart,
                '_iso_end'           => $isoEnd,
                '_highlights'        => is_array($highlights) ? array_values(array_slice($highlights, 0, 4)) : [],
                '_contact_phone'     => (string) ($event->contact_phone ?? ''),
                '_contact_email'     => (string) ($event->contact_email ?? ''),
                '_website_url'       => (string) ($event->website_url ?? ''),
                '_facebook_url'      => (string) ($event->facebook_url ?? ''),
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

            'events.show' => empty($ctx['_event_id']) ? [] : [array_filter([
                '@context'    => 'https://schema.org',
                '@type'       => 'Event',
                'name'        => $ctx['event_name'] ?? '',
                'description' => $this->eventDescription($ctx),
                // Always prefer the ISO 8601 timestamp — Google's Event
                // rich result requires it. Falls back to the human-readable
                // string only when the row predates the time columns.
                'startDate'   => $ctx['_iso_start'] ?: ($ctx['event_date'] ?? ''),
                'endDate'     => $ctx['_iso_end']   ?: null,
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                // Place schema — combine venue (the building) + location
                // (the area) so the SERP shows both in the rich card.
                'location'    => $this->eventPlace($ctx),
                'image'       => $ctx['_cover'] ?? null,
                'maximumAttendeeCapacity' => ($ctx['expected_attendees'] ?? 0) > 0
                    ? (int) $ctx['expected_attendees']
                    : null,
                'keywords'    => $ctx['event_tags'] ?: null,
                // Organizer — uses the explicit `organizer` column when
                // set, otherwise falls back to the photographer name (a
                // freelance photographer often IS the organizer when the
                // event is their own portfolio shoot).
                'organizer'   => [
                    '@type'    => 'Organization',
                    'name'     => $ctx['organizer'] ?: ($ctx['photographer'] ?: $brand),
                    'telephone'=> $ctx['_contact_phone'] ?: null,
                    'email'    => $ctx['_contact_email'] ?: null,
                    'url'      => $ctx['_website_url']   ?: null,
                    'sameAs'   => $ctx['_facebook_url']
                        ? [$ctx['_facebook_url']]
                        : null,
                ],
            ])],

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

    /* ─────────────── Event JSON-LD helpers (2026-05-01) ───────────────
     * Pulled out of buildStructuredData so the events.show match arm
     * stays readable. Each helper returns the exact shape Schema.org
     * expects for the corresponding subfield, with empty/null values
     * stripped — array_filter() at the call site removes any null
     * keys before the payload is encoded.
     * ──────────────────────────────────────────────────────────────── */

    /**
     * Compose Event.description.
     * Priority order:
     *   1. First 1-2 highlights joined with " · " — most marketable.
     *   2. "ถ่ายโดย {photographer}" — universal fallback.
     *   3. Empty string when neither is available.
     */
    private function eventDescription(array $ctx): string
    {
        $highlights = $ctx['_highlights'] ?? [];
        if (is_array($highlights) && count($highlights) > 0) {
            $head = implode(' · ', array_slice($highlights, 0, 2));
            if ($ctx['photographer'] ?? '') {
                return "{$head} · ถ่ายโดย {$ctx['photographer']}";
            }
            return $head;
        }
        return ($ctx['photographer'] ?? '') ? "ถ่ายโดย {$ctx['photographer']}" : '';
    }

    /**
     * Compose Event.location as Schema.org Place. Returns null when
     * we have neither venue nor address — Google rejects Event rich
     * results without a location, so dropping the key entirely is
     * safer than emitting an empty Place stub.
     */
    private function eventPlace(array $ctx): ?array
    {
        $venue   = $ctx['venue_name'] ?? '';
        $address = $ctx['location']   ?? '';
        if ($venue === '' && $address === '') return null;

        $place = [
            '@type' => 'Place',
            'name'  => $venue !== '' ? $venue : $address,
        ];
        if ($address !== '') {
            // Use PostalAddress for the addressLocality so Google
            // treats the city as a real location anchor (vs. a
            // free-text name field). addressCountry is hardcoded to
            // TH — the platform is Thailand-only and Google penalises
            // generic countries on local rich results.
            $place['address'] = [
                '@type'           => 'PostalAddress',
                'addressLocality' => $address,
                'addressCountry'  => 'TH',
            ];
        }
        return $place;
    }
}
