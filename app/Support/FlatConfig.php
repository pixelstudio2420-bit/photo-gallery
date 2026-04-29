<?php

namespace App\Support;

/**
 * Literal-key access into a Laravel config array.
 *
 * Why this exists
 * ---------------
 * Laravel's `config('foo.bar.baz')` interprets every dot as a nesting
 * level. Several config files in this app intentionally use FLAT keys
 * with dots inside them — e.g. `config('media.categories')` is keyed
 * `'events.photos' => […]`, `'auth.avatar' => […]` because the keys
 * mirror the public resource taxonomy `(system.entity)`.
 *
 * Reading those keys with `config('media.categories.events.photos')`
 * walks `media → categories → events → photos` (4 levels) and returns
 * `null` because `events` is not a sub-array.
 *
 * This helper takes the section as a regular dotted path and the key
 * as a *literal* string, sidestepping the dot interpretation.
 *
 *   $cat = FlatConfig::array('media.categories', 'events.photos');
 *   $cap = FlatConfig::array('usage.plan_caps.pro', 'ai.face_search');
 *
 * Single source of truth — when the dot-notation rules of Laravel ever
 * change, only one file needs updating.
 */
final class FlatConfig
{
    /**
     * @param  string $section  Dotted path to the parent array (interpreted by config()).
     * @param  string $key      Literal key inside that array (NOT dot-interpreted).
     */
    public static function get(string $section, string $key, mixed $default = null): mixed
    {
        $bag = (array) config($section, []);
        return $bag[$key] ?? $default;
    }

    /**
     * Convenience for keys that should always be arrays (e.g. category configs).
     * Returns $default when the key is missing OR the value isn't an array
     * — caller doesn't need to defensively wrap.
     *
     * @param  array<mixed>  $default
     * @return array<mixed>
     */
    public static function array(string $section, string $key, array $default = []): array
    {
        $val = self::get($section, $key, $default);
        return is_array($val) ? $val : $default;
    }

    /**
     * Convenience for numeric keys (e.g. per-call cost in microcents).
     */
    public static function int(string $section, string $key, int $default = 0): int
    {
        $val = self::get($section, $key, $default);
        return is_numeric($val) ? (int) $val : $default;
    }

    /**
     * Read the whole flat-keyed section as an associative array. Used by
     * iterators that need every declared key (e.g. CircuitBreaker::snapshot
     * lists every breaker, R2MediaService::deleteUser walks every category).
     *
     * @return array<string, mixed>
     */
    public static function section(string $section): array
    {
        return (array) config($section, []);
    }
}
