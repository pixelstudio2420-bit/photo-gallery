<?php

namespace App\Services\Auth;

use App\Models\AppSetting;

/**
 * SocialAuthService
 * ─────────────────────────────────────────────────────────────
 * Centralised registry + toggle gateway for social sign-in
 * providers (LINE, Google, Facebook, Apple) and for related
 * authentication rules (email register, LINE connect requirement,
 * default provider per role).
 *
 * Reads its configuration from AppSetting (admin-controlled).
 */
class SocialAuthService
{
    /**
     * Canonical provider registry.
     *
     * Keys:
     *  - label     : human readable (button text)
     *  - color     : brand hex
     *  - icon      : bootstrap-icons class
     *  - bg_class  : Tailwind bg + hover class for primary button
     *  - text_class: Tailwind text colour
     */
    public const PROVIDERS = [
        'line' => [
            'label'      => 'LINE',
            'color'      => '#06C755',
            'icon'       => 'bi-line',
            'bg_class'   => 'bg-[#06C755] hover:bg-[#05b34c]',
            'text_class' => 'text-white',
        ],
        'google' => [
            'label'      => 'Google',
            'color'      => '#4285F4',
            'icon'       => 'bi-google',
            'bg_class'   => 'bg-white hover:bg-gray-50 border-2 border-gray-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:border-white/10',
            'text_class' => 'text-gray-700 dark:text-gray-200',
        ],
        'facebook' => [
            'label'      => 'Facebook',
            'color'      => '#1877F2',
            'icon'       => 'bi-facebook',
            'bg_class'   => 'bg-[#1877F2] hover:bg-[#166fe5]',
            'text_class' => 'text-white',
        ],
        'apple' => [
            'label'      => 'Apple',
            'color'      => '#000000',
            'icon'       => 'bi-apple',
            'bg_class'   => 'bg-black hover:bg-gray-900',
            'text_class' => 'text-white',
        ],
    ];

    /** Default enabled state when no setting row exists. */
    protected const DEFAULTS = [
        'auth_social_line_enabled'        => '1',
        'auth_social_google_enabled'      => '1',
        'auth_social_facebook_enabled'    => '1',
        'auth_social_apple_enabled'       => '0',
        'auth_email_registration_enabled' => '1',
        'auth_require_line_connect'       => '1',
        'auth_allow_line_connect_skip'    => '1',
        'auth_default_photographer_provider' => 'google',
        'auth_default_customer_provider'     => 'line',
    ];

    /**
     * Return list of enabled providers with metadata.
     *
     * @return array<string,array> keyed by provider name
     */
    public function enabledProviders(): array
    {
        $out = [];
        foreach (array_keys(self::PROVIDERS) as $name) {
            if ($this->isProviderEnabled($name)) {
                $out[$name] = self::PROVIDERS[$name] + ['name' => $name];
            }
        }
        return $out;
    }

    /**
     * Is this provider enabled by admin?
     */
    public function isProviderEnabled(string $provider): bool
    {
        if (!array_key_exists($provider, self::PROVIDERS)) {
            return false;
        }

        $key = 'auth_social_' . $provider . '_enabled';
        return AppSetting::get($key, self::DEFAULTS[$key] ?? '0') === '1';
    }

    /**
     * Is classic email + password registration allowed?
     */
    public function isEmailRegistrationEnabled(): bool
    {
        return AppSetting::get(
            'auth_email_registration_enabled',
            self::DEFAULTS['auth_email_registration_enabled']
        ) === '1';
    }

    /**
     * Does admin require every account to connect LINE?
     */
    public function requiresLineConnect(): bool
    {
        return AppSetting::get(
            'auth_require_line_connect',
            self::DEFAULTS['auth_require_line_connect']
        ) === '1';
    }

    /**
     * May a user skip the connect-LINE step?
     */
    public function allowLineConnectSkip(): bool
    {
        return AppSetting::get(
            'auth_allow_line_connect_skip',
            self::DEFAULTS['auth_allow_line_connect_skip']
        ) === '1';
    }

    /**
     * Recommended provider for a given role.
     *
     * Falls back to first enabled provider if the preferred
     * provider is disabled.
     */
    public function defaultProviderForRole(string $role): string
    {
        $key = $role === 'photographer'
            ? 'auth_default_photographer_provider'
            : 'auth_default_customer_provider';

        $preferred = AppSetting::get($key, self::DEFAULTS[$key] ?? 'line');

        if ($this->isProviderEnabled($preferred)) {
            return $preferred;
        }

        // Fallback: first enabled provider in order
        foreach (array_keys(self::PROVIDERS) as $name) {
            if ($this->isProviderEnabled($name)) {
                return $name;
            }
        }

        return $preferred; // last resort, even if disabled
    }

    /**
     * Get full metadata for a single provider (label, icon, etc).
     */
    public function meta(string $provider): ?array
    {
        return self::PROVIDERS[$provider] ?? null;
    }

    /**
     * Does the given user already have a LINE social login record?
     */
    public function userHasLineLinked($user): bool
    {
        if (!$user) return false;

        if ($user->auth_provider === 'line') {
            return true;
        }

        return $user->socialLogins()
            ->where('provider', 'line')
            ->exists();
    }

    /**
     * URL to initiate OAuth for a given provider.
     */
    public function providerUrl(string $provider): string
    {
        return url('/auth/' . $provider);
    }

    /**
     * Helper: should we redirect $user to /auth/connect-line
     * right after signup / social login?
     */
    public function shouldPromptConnectLine($user): bool
    {
        if (!$this->requiresLineConnect()) return false;
        if (!$this->isProviderEnabled('line')) return false;
        if (!$user) return false;

        // Already linked — no prompt needed
        if ($this->userHasLineLinked($user)) return false;

        // User explicitly skipped (stored in session)
        if (session('line_connect_skipped')) return false;

        return true;
    }
}
