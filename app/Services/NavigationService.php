<?php

namespace App\Services;

use App\Models\NavMenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Read-side helper for the admin-managed navigation system.
 *
 * Two surfaces consume this:
 *   • resources/views/layouts/partials/navbar.blade.php
 *     → itemsFor('navbar')
 *   • resources/views/layouts/partials/footer.blade.php
 *     → itemsFor('footer')
 *
 * Each call returns a filtered + sorted Collection of NavMenuItem
 * rows. Filters applied:
 *   1. is_active = true
 *   2. location matches (or 'both')
 *   3. audience matches the current user state
 *   4. visibility_route_pattern (if set) doesn't match current route
 *
 * Caching: the WHOLE nav table is loaded once per request into an
 * in-memory static, then cached at the framework level for 5 minutes
 * so even cold loads only touch the DB once per cache window. Saving
 * any item from /admin/navigation flushes both via the model's
 * booted() hook.
 */
class NavigationService
{
    private const CACHE_KEY = 'nav_menu_items_all';
    private const CACHE_TTL = 300; // 5 minutes

    /** @var Collection<int, NavMenuItem>|null Per-request memo */
    private ?Collection $allItems = null;

    /**
     * Get all items visible at the given location for the current user.
     *
     * @param  string $location  'navbar' or 'footer'. 'both' rows match
     *                            either query.
     * @return Collection<int, NavMenuItem>
     */
    public function itemsFor(string $location): Collection
    {
        $audience    = $this->currentAudience();
        $currentRoute = optional(request()->route())->getName() ?? '';

        return $this->allRaw()
            ->filter(function (NavMenuItem $item) use ($location, $audience, $currentRoute) {
                if (!$item->is_active) return false;
                if ($item->location === 'hidden') return false;

                // Location match (a 'both' row appears in either query).
                $locOk = $item->location === $location || $item->location === 'both';
                if (!$locOk) return false;

                // Audience match.
                if (!$this->audienceMatches($item->audience, $audience)) return false;

                // Optional route pattern hides the item on specific
                // pages (e.g. don't show "เริ่มขายรูป" inside admin).
                if ($item->visibility_route_pattern) {
                    if (preg_match("/{$item->visibility_route_pattern}/i", $currentRoute)) {
                        return false;
                    }
                }
                return true;
            })
            ->sortBy(fn ($i) => [$i->sort_order, $i->id])
            ->values();
    }

    /**
     * Resolve audience tier for the current user. Returns one of:
     *   guest | authenticated | photographer
     * Items with audience='public' match all three.
     */
    public function currentAudience(): string
    {
        $user = Auth::user();
        if (!$user) return 'guest';

        $profile = $user->photographerProfile ?? null;
        if ($profile && ($profile->status ?? null) === 'approved') {
            return 'photographer';
        }
        return 'authenticated';
    }

    /**
     * Whether an item's audience matches the current user's tier.
     *
     * Tiers:
     *   public        → matches everyone
     *   guest         → only logged-out users
     *   authenticated → any logged-in user (incl. photographers)
     *   photographer  → only approved photographers
     */
    private function audienceMatches(string $itemAudience, string $userAudience): bool
    {
        return match ($itemAudience) {
            'public'        => true,
            'guest'         => $userAudience === 'guest',
            'authenticated' => in_array($userAudience, ['authenticated', 'photographer'], true),
            'photographer'  => $userAudience === 'photographer',
            default         => false,
        };
    }

    /**
     * Resolve href for a NavMenuItem. Bare paths (starting with /)
     * get url() prefixed so the link works behind a /subpath base
     * URL. Absolute URLs pass through unchanged.
     */
    public function resolveUrl(NavMenuItem $item): string
    {
        $url = (string) $item->url;
        if ($url === '') return '#';
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        // Bare path — prefix with site base.
        return url($url);
    }

    /**
     * Whether THIS item matches the current request URL — for the
     * navbar's "active" state highlight. Does prefix match on the
     * URL path so /events/123 also activates the "อีเวนต์" item.
     */
    public function isActive(NavMenuItem $item): bool
    {
        $current = '/' . trim(request()->path(), '/');
        $target  = '/' . trim((string) parse_url($item->url, PHP_URL_PATH), '/');

        if ($target === '/') return $current === '/';
        return str_starts_with($current, $target);
    }

    /** Force-clear the cache. Called from NavMenuItem booted hook. */
    public function flushCache(): void
    {
        $this->allItems = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * All rows from DB, cached for the request + framework cache.
     * Returns a fresh Collection each time so filter() doesn't
     * mutate the shared cache.
     */
    private function allRaw(): Collection
    {
        if ($this->allItems !== null) {
            return collect($this->allItems);
        }

        $cached = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => NavMenuItem::orderBy('sort_order')->orderBy('id')->get()
        );

        $this->allItems = $cached;
        return collect($cached);
    }
}
