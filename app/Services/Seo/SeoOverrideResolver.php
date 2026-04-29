<?php

namespace App\Services\Seo;

use App\Models\SeoPage;

/**
 * Resolve the active SEO override for the current request.
 *
 * Why a separate class (and not a method on SeoService)
 * -----------------------------------------------------
 * SeoService is huge and renders the final <head> snippet. Putting the
 * "consult the DB" logic in a small focused class:
 *
 *   - keeps the database access path testable in isolation
 *   - lets the resolver return null when seo_pages doesn't exist (e.g.
 *     during migration) without polluting render() with try/catch
 *   - allows the Blade view layer to call apply()->altText('hero') for
 *     image alt overrides without going through the whole SEO pipeline
 *
 * Usage from SeoService::render():
 *   $override = app(SeoOverrideResolver::class)->forCurrentRoute();
 *   if ($override) { $title = $override->title ?? $title; ... }
 */
class SeoOverrideResolver
{
    /**
     * Resolve the override for the current Laravel route, or null when:
     *   - we're outside a route (artisan, queue, console)
     *   - seo_pages table doesn't exist yet
     *   - no row matches this route+locale
     *
     * The match logic prefers the most-specific row (route_params hash
     * exact match) and falls back to the wildcard row for the route name.
     */
    public function forCurrentRoute(): ?SeoPage
    {
        $route = request()->route();
        if (!$route || !$route->getName()) {
            return null;
        }

        try {
            return SeoPage::matchFor(
                routeName: $route->getName(),
                params:    $this->normalizedRouteParams($route),
                locale:    app()->getLocale() ?: 'th',
            );
        } catch (\Throwable) {
            // seo_pages may not exist yet during fresh installs;
            // resolver MUST be defensive — failing here would 500 every page.
            return null;
        }
    }

    /**
     * Look up by an explicit (route_name, params, locale). Useful for
     * the admin preview / validation pages where "current request" isn't
     * the page being inspected.
     */
    public function forRoute(string $routeName, ?array $params = null, ?string $locale = null): ?SeoPage
    {
        try {
            return SeoPage::matchFor(
                routeName: $routeName,
                params:    $params,
                locale:    $locale ?: (app()->getLocale() ?: 'th'),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Strip non-string/non-numeric params + omit hash/anchor. Models
     * passed by route-model binding become arrays of attributes — we
     * keep only their primary key so 2 requests for the same model
     * resolve to the same match_key.
     */
    private function normalizedRouteParams(\Illuminate\Routing\Route $route): array
    {
        $clean = [];
        foreach ($route->parameters() as $name => $value) {
            if (is_object($value) && method_exists($value, 'getKey')) {
                $clean[$name] = (string) $value->getKey();
            } elseif (is_scalar($value)) {
                $clean[$name] = (string) $value;
            }
            // arrays / objects without getKey() are skipped — they can't
            // be reproduced cleanly in admin UI, so an override targeting
            // them wouldn't work anyway.
        }
        return $clean;
    }
}
