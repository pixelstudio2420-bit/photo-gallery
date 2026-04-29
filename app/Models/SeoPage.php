<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-page SEO override row.
 *
 * Keyed by (route_name, locale, match_key). Use ::matchFor() to look up
 * the right row for an incoming request — that helper handles the
 * route_params hashing so callers don't need to think about it.
 */
class SeoPage extends Model
{
    protected $fillable = [
        'route_name', 'locale', 'route_params', 'path_preview',
        'title', 'description', 'keywords', 'canonical_url', 'meta_robots',
        'og_title', 'og_description', 'og_image', 'og_type',
        'structured_data', 'alt_text_map',
        'is_active', 'is_locked', 'last_validated_at', 'validation_warnings',
        'version', 'created_by', 'updated_by', 'match_key',
    ];

    /**
     * Transient field — observer reads this to populate the revision row's
     * change_reason. Marked public so callers can do `$page->changeReason = '...'`
     * without it being treated as an Eloquent column attribute.
     */
    public ?string $changeReason = null;

    protected $casts = [
        'route_params'        => 'array',
        'structured_data'     => 'array',
        'alt_text_map'        => 'array',
        'validation_warnings' => 'array',
        'is_active'           => 'boolean',
        'is_locked'           => 'boolean',
        'last_validated_at'   => 'datetime',
        'version'             => 'integer',
    ];

    /**
     * Stable hash of route_params used as the unique-index discriminator.
     *
     * Sorted keys + json_encode => deterministic regardless of insertion
     * order, so {"a":1,"b":2} and {"b":2,"a":1} collide as expected.
     * Empty/null params hash to a constant '_' so the "global override"
     * row for a route is unique.
     */
    public static function buildMatchKey(?array $params): string
    {
        if (empty($params)) return '_';
        ksort($params);
        return md5(json_encode($params, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Look up the active SEO override for a given route + params + locale.
     *
     * Resolution order:
     *   1. exact (route_name, locale, params-hash) match
     *   2. (route_name, locale, '_') — route-wide override
     *   3. null → caller falls back to controller-supplied SEO
     *
     * The lookup goes through cache so this is one cache get per request.
     */
    public static function matchFor(string $routeName, ?array $params = null, string $locale = 'th'): ?self
    {
        $key = self::buildMatchKey($params);

        $cacheKey = "seo_page:lookup:{$routeName}:{$locale}";
        $candidates = \Cache::remember($cacheKey, 300, function () use ($routeName, $locale) {
            return self::query()
                ->where('route_name', $routeName)
                ->where('locale', $locale)
                ->where('is_active', true)
                ->orderByDesc('match_key')   // exact-key rows beat '_' wildcard
                ->get();
        });

        // Find specific match first.
        $exact = $candidates->firstWhere('match_key', $key);
        if ($exact) return $exact;

        // Fall back to the wildcard row for the route.
        return $candidates->firstWhere('match_key', '_');
    }

    /**
     * Bust the per-route cache. Called from the observer on save/delete
     * so flips show up within the next request, not 5 minutes later.
     */
    public function flushCache(): void
    {
        \Cache::forget("seo_page:lookup:{$this->route_name}:{$this->locale}");
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SeoPageRevision::class);
    }
}
