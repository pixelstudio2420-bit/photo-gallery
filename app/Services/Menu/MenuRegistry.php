<?php

namespace App\Services\Menu;

use Closure;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Reads the menu definitions in config/menus/*.php and returns a
 * permission/feature-filtered tree that Blade components can render.
 *
 * Why a registry instead of inline @if @can in Blade
 * --------------------------------------------------
 * The previous admin-sidebar.blade.php was 918 lines of repeated
 * "icon + label + permission gate + active-state class" patterns.
 * Adding a new menu item required editing TWO places (the partial
 * + the lang file) and was easy to get wrong (forgot the @can,
 * inconsistent active class, missed icon).
 *
 * Centralising the structure as data lets the Blade component
 * become a recursive renderer (~50 lines) and adding a menu item
 * is now ONE config edit.
 *
 * What this class does NOT do
 * ---------------------------
 *   • render HTML — that's the Blade component's job
 *   • check authentication — caller is already past auth middleware
 *   • cache the resolved tree — the config is array literal so
 *     PHP's opcache caches it for free; nothing to add
 *
 * Usage
 *
 *   $menu = app(MenuRegistry::class)->build(
 *       'admin',
 *       canCallback: fn ($p) => $admin->can($p),
 *       featureCheck: fn ($f) => AppSetting::get($f, '0') === '1',
 *       badges:       ['pendingOrders' => 12, 'newMessages' => 3],
 *   );
 */
class MenuRegistry
{
    /**
     * Load + filter a menu by name.
     *
     * @param  string  $menu  one of: admin | photographer | customer | footer
     * @param  Closure|null  $canCallback   fn(string $permission): bool
     *                                      null = no permission filter
     * @param  Closure|null  $featureCheck  fn(string $feature): bool
     *                                      null = no feature filter
     * @param  array<string, int>  $badges  badge name → count
     * @return array<int, array> filtered tree
     */
    public function build(
        string $menu,
        ?Closure $canCallback = null,
        ?Closure $featureCheck = null,
        array $badges = [],
    ): array {
        $config = config("menus.{$menu}");
        if (!is_array($config)) {
            return [];
        }
        // Footer config has a 'columns' wrapper; everything else is
        // a flat top-level array. Normalize.
        $items = $config['columns'] ?? $config;

        return $this->filterTree($items, $canCallback, $featureCheck, $badges);
    }

    /**
     * Recursive filter. Called once per node; preserves nesting.
     */
    private function filterTree(
        array $items,
        ?Closure $canCallback,
        ?Closure $featureCheck,
        array $badges,
    ): array {
        $out = [];
        foreach ($items as $item) {
            // Permission gate
            if ($canCallback !== null && !empty($item['permission'])) {
                if (!$canCallback($item['permission'])) {
                    continue;
                }
            }
            // Feature gate
            if ($featureCheck !== null && !empty($item['feature'])) {
                if (!$featureCheck($item['feature'])) {
                    continue;
                }
            }
            // Conditional visibility (e.g. footer "show only to guests").
            //
            // Two value shapes accepted:
            //   1. string key — resolved by evaluateCondition() below.
            //      This is the cache-safe form: configs containing only
            //      strings/arrays survive `php artisan config:cache`.
            //   2. Closure — legacy form, still works at runtime but
            //      crashes config:cache (Closures aren't serializable).
            //      New code must use the string form.
            if (!empty($item['condition'])) {
                if (!$this->evaluateCondition($item['condition'])) {
                    continue;
                }
            }

            // Resolve badge value if requested
            if (!empty($item['badge']) && isset($badges[$item['badge']])) {
                $item['badge_value'] = (int) $badges[$item['badge']];
            }

            // Recurse into children
            if (!empty($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->filterTree(
                    $item['children'], $canCallback, $featureCheck, $badges,
                );
                // Drop empty parent groups (all children were filtered out).
                if (empty($item['children']) && empty($item['route'])) {
                    continue;
                }
            }

            // Drop the closure so the array can be cached / serialized.
            unset($item['condition']);

            $out[] = $item;
        }
        return $out;
    }

    /**
     * Walk a menu and report any item whose `route` doesn't resolve.
     *
     * This is what the dead-link test calls — we don't want to land
     * a config edit that creates broken navigation.
     *
     * @return array<int, string> list of "menu.id route_name" strings
     */
    public function deadLinks(string $menu): array
    {
        $config = config("menus.{$menu}");
        if (!is_array($config)) return [];
        $items = $config['columns'] ?? $config;

        $bad = [];
        $this->collectRoutes($items, $bad);
        return $bad;
    }

    private function collectRoutes(array $items, array &$bad, string $path = ''): void
    {
        foreach ($items as $item) {
            $localPath = $path . '/' . ($item['id'] ?? '?');
            if (!empty($item['route'])) {
                if (!RouteFacade::has($item['route'])) {
                    $bad[] = trim($localPath, '/') . ' → ' . $item['route'];
                }
            }
            if (!empty($item['children']) && is_array($item['children'])) {
                $this->collectRoutes($item['children'], $bad, $localPath);
            }
            if (!empty($item['items']) && is_array($item['items'])) {
                // Footer column shape
                $this->collectRoutes($item['items'], $bad, $localPath);
            }
        }
    }

    /**
     * Resolve a `condition` value to a boolean visibility flag.
     *
     * Accepts:
     *   - string key — preferred form (cache-safe)
     *   - Closure   — legacy form (works at runtime but breaks config:cache)
     *
     * Adding a new condition key
     * --------------------------
     * Add a new arm to the match() expression below. Keep the keys
     * descriptive (e.g. "guest", "non_photographer") rather than
     * implementation-leaky (e.g. "auth_check_returns_false") — config
     * authors should be able to read the menu file without knowing
     * which Auth facade method backs each branch.
     */
    private function evaluateCondition($condition): bool
    {
        // Legacy: Closure / callable. Still works for in-process menus,
        // but will block config:cache. Prefer the string form below.
        if ($condition instanceof Closure || is_callable($condition)) {
            return (bool) $condition();
        }

        if (is_string($condition)) {
            return match ($condition) {
                'guest'             => !\Illuminate\Support\Facades\Auth::check(),
                'authenticated'     => \Illuminate\Support\Facades\Auth::check(),
                'non_photographer'  => !\Illuminate\Support\Facades\Auth::user()?->photographerProfile,
                'is_photographer'   => (bool) \Illuminate\Support\Facades\Auth::user()?->photographerProfile,
                default             => true,  // unknown key → don't filter
            };
        }

        return true;
    }
}
