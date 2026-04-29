<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\Auth\SocialAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * SocialAuthSettingsController
 * ─────────────────────────────────────────────────────────
 * Admin UI for Social Login / Registration. Covers:
 *   • Provider on/off toggles (LINE / Google / Facebook / Apple)
 *   • Email registration toggle
 *   • LINE-connect enforcement
 *   • Default-provider per role (customer / photographer)
 *   • OAuth / OpenID Connect client credentials per provider
 *     (single source of truth — shared with other settings pages
 *      for Google & LINE, migrated from .env for Facebook, new for Apple)
 *
 * All values persist in `app_settings` via the AppSetting helper.
 */
class SocialAuthSettingsController extends Controller
{
    /** Checkbox keys (stored as '0' / '1'). */
    private const BOOL_KEYS = [
        'auth_social_line_enabled',
        'auth_social_google_enabled',
        'auth_social_facebook_enabled',
        'auth_social_apple_enabled',
        'auth_email_registration_enabled',
        'auth_require_line_connect',
        'auth_allow_line_connect_skip',
    ];

    /** Free-form (string) keys. */
    private const STRING_KEYS = [
        'auth_default_photographer_provider',
        'auth_default_customer_provider',
    ];

    /**
     * Public credential keys — plain text inputs, overwritten on every save
     * (an empty submission blanks the field, which is the expected behaviour
     * for public identifiers like Client IDs).
     */
    private const CREDENTIAL_KEYS = [
        // Google — shared with /admin/settings/google-drive
        'google_client_id',
        // LINE — shared with /admin/settings/line
        'line_channel_id',
        // Facebook — migrated from .env (FB_APP_ID) to AppSetting
        'facebook_client_id',
        'facebook_redirect_uri',
        // Apple — new
        'apple_client_id',   // Service ID, e.g. com.example.web
        'apple_team_id',
        'apple_key_id',
    ];

    /**
     * Secret credential keys — password-style inputs. Blank submission =
     * "keep existing value" (so admins don't wipe secrets by accident
     * when saving unrelated toggles).
     */
    private const SECRET_KEYS = [
        'google_client_secret',
        'line_channel_secret',
        'facebook_client_secret',
        'apple_private_key',  // PEM content of the .p8 file
    ];

    /** Defaults when the row has never been persisted. */
    private const DEFAULTS = [
        'auth_social_line_enabled'            => '1',
        'auth_social_google_enabled'          => '1',
        'auth_social_facebook_enabled'        => '1',
        'auth_social_apple_enabled'           => '0',
        'auth_email_registration_enabled'     => '1',
        'auth_require_line_connect'           => '1',
        'auth_allow_line_connect_skip'        => '1',
        'auth_default_photographer_provider'  => 'google',
        'auth_default_customer_provider'      => 'line',
    ];

    public function index()
    {
        $settings = [];

        foreach (array_merge(self::BOOL_KEYS, self::STRING_KEYS) as $key) {
            $settings[$key] = AppSetting::get($key, self::DEFAULTS[$key] ?? '');
        }

        foreach (array_merge(self::CREDENTIAL_KEYS, self::SECRET_KEYS) as $key) {
            // Facebook falls back to legacy .env values if DB is empty —
            // makes the transition from .env-based config seamless.
            $fallback = match ($key) {
                'facebook_client_id'     => (string) env('FB_APP_ID', ''),
                'facebook_client_secret' => (string) env('FB_APP_SECRET', ''),
                'facebook_redirect_uri'  => (string) env('FB_REDIRECT_URI', ''),
                default                  => '',
            };
            $settings[$key] = AppSetting::get($key, $fallback);
        }

        $providers = SocialAuthService::PROVIDERS;

        // Redirect URIs to display (copy-to-clipboard) next to each provider.
        // These are the exact callback paths the app expects OAuth providers
        // to call back to. Admins paste them verbatim into provider consoles.
        $redirectUris = [
            'google'   => route('auth.google.callback'),
            'line'     => route('auth.line.callback'),
            'facebook' => route('auth.facebook.callback'),
            'apple'    => url('/auth/apple/callback'),
        ];

        // Per-provider configured / not-configured status (small dot next to header)
        $providerStatus = [
            'google'   => !empty($settings['google_client_id'])   && !empty($settings['google_client_secret']),
            'line'     => !empty($settings['line_channel_id'])    && !empty($settings['line_channel_secret']),
            'facebook' => !empty($settings['facebook_client_id']) && !empty($settings['facebook_client_secret']),
            'apple'    => !empty($settings['apple_client_id'])    && !empty($settings['apple_team_id'])
                          && !empty($settings['apple_key_id'])    && !empty($settings['apple_private_key']),
        ];

        return view('admin.settings.social-auth', compact(
            'settings', 'providers', 'redirectUris', 'providerStatus'
        ));
    }

    public function update(Request $request)
    {
        // 1) Boolean toggles (unchecked = absent → '0')
        foreach (self::BOOL_KEYS as $key) {
            AppSetting::set($key, $request->has($key) ? '1' : '0');
        }

        // 2) Default-provider selects — must be a known provider key
        $allowed = array_keys(SocialAuthService::PROVIDERS);
        foreach (self::STRING_KEYS as $key) {
            $val = $request->input($key, self::DEFAULTS[$key] ?? '');
            if (!in_array($val, $allowed, true)) {
                $val = self::DEFAULTS[$key] ?? $allowed[0];
            }
            AppSetting::set($key, $val);
        }

        // 3) Public credentials — trim & save (blank allowed)
        foreach (self::CREDENTIAL_KEYS as $key) {
            if ($request->has($key)) {
                AppSetting::set($key, trim((string) $request->input($key, '')));
            }
        }

        // 4) Secret credentials — preserve existing on blank submission
        foreach (self::SECRET_KEYS as $key) {
            if (!$request->has($key)) continue;
            $val = (string) $request->input($key, '');
            if ($val === '') continue; // keep existing
            AppSetting::set($key, $val);
        }

        // 5) Bust caches so Socialite / downstream services pick up new values
        Cache::forget('app_settings_all');

        return back()->with('success', 'บันทึกการตั้งค่าระบบสมัครสมาชิก / Social Login สำเร็จ');
    }
}
