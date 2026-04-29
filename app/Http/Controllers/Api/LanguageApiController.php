<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;

class LanguageApiController extends Controller
{
    /**
     * All locales supported by the app.
     */
    public const SUPPORTED = [
        'th' => ['name' => 'ไทย', 'flag' => '🇹🇭', 'native' => 'ภาษาไทย'],
        'en' => ['name' => 'English', 'flag' => '🇺🇸', 'native' => 'English'],
        'zh' => ['name' => '中文',  'flag' => '🇨🇳', 'native' => '中文'],
    ];

    /**
     * Return only the ENABLED locales (respecting admin toggle).
     */
    public static function enabled(): array
    {
        if (!SetLocale::isMultilangEnabled()) {
            // When multi-lang is off, only the default locale is "selectable"
            $default = SetLocale::getDefaultLocale();
            return isset(self::SUPPORTED[$default]) ? [$default => self::SUPPORTED[$default]] : [];
        }

        $enabledCodes = SetLocale::getEnabledLanguages();
        $result = [];
        foreach ($enabledCodes as $code) {
            if (isset(self::SUPPORTED[$code])) {
                $result[$code] = self::SUPPORTED[$code];
            }
        }
        return $result ?: self::SUPPORTED;
    }

    /**
     * Is the multi-language system currently enabled?
     */
    public static function isEnabled(): bool
    {
        return SetLocale::isMultilangEnabled();
    }

    /**
     * Switch locale and redirect back (or to URL parameter).
     */
    public function switch(Request $request, string $locale)
    {
        // Reject if multi-language is disabled by admin
        if (!SetLocale::isMultilangEnabled()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'ระบบเปลี่ยนภาษาถูกปิดใช้งาน',
                    'code'    => 'MULTILANG_DISABLED',
                ], 403);
            }
            return back()->with('error', 'ระบบเปลี่ยนภาษาถูกปิดใช้งาน');
        }

        // Reject if locale is not in the enabled list
        $enabled = SetLocale::getEnabledLanguages();
        if (!in_array($locale, $enabled, true)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'ภาษานี้ไม่ได้เปิดใช้งาน',
                    'code'    => 'LOCALE_NOT_ENABLED',
                ], 403);
            }
            return back()->with('error', 'ภาษานี้ไม่ได้เปิดใช้งาน');
        }

        session()->put('locale', $locale);
        app()->setLocale($locale);

        // Cookie for long-lived persistence (1 year)
        cookie()->queue(cookie('locale', $locale, 60 * 24 * 365));

        // JSON response for AJAX callers
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'locale'  => $locale,
                'meta'    => self::SUPPORTED[$locale] ?? null,
            ]);
        }

        // Redirect to specific URL or back
        $redirect = $request->input('redirect');
        if ($redirect && str_starts_with($redirect, '/')) {
            return redirect($redirect);
        }

        return back();
    }

    /**
     * Get current locale info + the list of ENABLED locales (respects toggle).
     */
    public function current()
    {
        $locale = app()->getLocale();

        return response()->json([
            'locale'             => $locale,
            'meta'               => self::SUPPORTED[$locale] ?? null,
            'supported'          => self::SUPPORTED,
            'enabled'            => self::enabled(),
            'multilang_enabled'  => SetLocale::isMultilangEnabled(),
        ]);
    }
}
