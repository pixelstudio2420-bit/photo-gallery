<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Supported locales. Keep in sync with LanguageApiController::SUPPORTED.
     */
    public const SUPPORTED = ['th', 'en', 'zh'];

    public function handle(Request $request, Closure $next): Response
    {
        $multilangEnabled = self::isMultilangEnabled();
        $defaultLocale    = self::getDefaultLocale();

        // If multi-language is turned off, force default locale and ignore user preferences
        if (!$multilangEnabled) {
            app()->setLocale($defaultLocale);
            return $next($request);
        }

        // Multi-language ON: respect user preferences within enabled list
        $enabled = self::getEnabledLanguages();

        $locale = $request->query('lang')
            ?? session('locale')
            ?? $request->cookie('locale')
            ?? $this->detectFromHeader($request, $enabled)
            ?? $defaultLocale;

        if (in_array($locale, $enabled, true)) {
            app()->setLocale($locale);
            session()->put('locale', $locale);
        } else {
            app()->setLocale($defaultLocale);
        }

        return $next($request);
    }

    /**
     * Is multi-language system enabled?
     */
    public static function isMultilangEnabled(): bool
    {
        try {
            return AppSetting::get('multilang_enabled', '1') === '1';
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Get the configured default locale (or safe fallback).
     */
    public static function getDefaultLocale(): string
    {
        try {
            $default = AppSetting::get('default_language', config('app.locale', 'th'));
            return in_array($default, self::SUPPORTED, true) ? $default : 'th';
        } catch (\Throwable $e) {
            return 'th';
        }
    }

    /**
     * Get the list of enabled languages (subset of SUPPORTED).
     */
    public static function getEnabledLanguages(): array
    {
        try {
            $json = AppSetting::get('enabled_languages', json_encode(self::SUPPORTED));
            $enabled = json_decode($json, true);

            if (!is_array($enabled) || empty($enabled)) {
                return self::SUPPORTED;
            }

            return array_values(array_intersect($enabled, self::SUPPORTED));
        } catch (\Throwable $e) {
            return self::SUPPORTED;
        }
    }

    /**
     * Parse Accept-Language header; returns first enabled locale.
     */
    private function detectFromHeader(Request $request, array $enabled): ?string
    {
        $header = $request->header('Accept-Language', '');
        if (!$header) return null;

        foreach (explode(',', $header) as $part) {
            $code = strtolower(trim(explode(';', $part)[0]));
            $base = substr($code, 0, 2);
            if (in_array($base, $enabled, true)) {
                return $base;
            }
        }
        return null;
    }
}
