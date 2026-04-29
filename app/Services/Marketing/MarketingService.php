<?php

namespace App\Services\Marketing;

use App\Models\AppSetting;

/**
 * Central toggle/config reader for all marketing features.
 *
 * Single source of truth — every blade component, middleware, and service
 * checks enabled() before doing work. Defaults OFF, zero overhead when disabled.
 *
 * Hierarchy:
 *   marketing_enabled (master)          ← blocks everything if OFF
 *     └─ marketing_<feature>_enabled    ← individual feature toggle
 */
class MarketingService
{
    /**
     * Master switch — if OFF, EVERY marketing feature is disabled.
     */
    public function masterEnabled(): bool
    {
        return AppSetting::get('marketing_enabled', '0') === '1';
    }

    /**
     * Check if a specific feature is enabled.
     * Returns false if master is off OR if the feature toggle is off.
     */
    public function enabled(string $feature): bool
    {
        if (!$this->masterEnabled()) return false;
        return AppSetting::get("marketing_{$feature}_enabled", '0') === '1';
    }

    /**
     * Read a marketing setting value.
     */
    public function get(string $key, $default = null)
    {
        return AppSetting::get("marketing_{$key}", $default);
    }

    /**
     * Update a marketing setting.
     */
    public function set(string $key, $value): void
    {
        AppSetting::set("marketing_{$key}", $value);
    }

    // ── Fast accessors for common checks ──

    public function pixelEnabled(string $platform): bool
    {
        return match ($platform) {
            'fb'                => $this->enabled('fb_pixel'),
            'fb_capi', 'capi'   => $this->enabled('fb_conversions_api'),
            'ga4'               => $this->enabled('ga4'),
            'gtm'               => $this->enabled('gtm'),
            'google_ads', 'ads' => $this->enabled('google_ads'),
            'line_tag'          => $this->enabled('line_tag'),
            'tiktok'            => $this->enabled('tiktok_pixel'),
            default             => false,
        };
    }

    public function anyPixelEnabled(): bool
    {
        foreach (['fb', 'ga4', 'gtm', 'google_ads', 'line_tag', 'tiktok'] as $p) {
            if ($this->pixelEnabled($p)) return true;
        }
        return false;
    }

    public function utmEnabled(): bool          { return $this->enabled('utm_tracking'); }
    public function schemaEnabled(): bool       { return $this->enabled('schema_markup'); }
    public function ogEnabled(): bool           { return $this->enabled('og_auto'); }
    public function newsletterEnabled(): bool   { return $this->enabled('newsletter'); }
    public function campaignsEnabled(): bool    { return $this->enabled('email_campaigns'); }
    public function lineMessagingEnabled(): bool { return $this->enabled('line_messaging'); }
    public function lineNotifyEnabled(): bool   { return $this->enabled('line_notify'); }
    public function referralEnabled(): bool     { return $this->enabled('referral'); }
    public function loyaltyEnabled(): bool      { return $this->enabled('loyalty'); }
    public function landingPagesEnabled(): bool { return $this->enabled('landing_pages'); }
    public function pushEnabled(): bool         { return $this->enabled('push'); }
    public function analyticsEnabled(): bool    { return $this->enabled('analytics'); }

    /**
     * Return a summary of every feature's state — used by admin Marketing Hub.
     */
    public function statusSummary(): array
    {
        $master = $this->masterEnabled();

        return [
            'master' => $master,
            'features' => [
                'fb_pixel'           => ['enabled' => $this->pixelEnabled('fb'),         'label' => 'Meta Pixel',             'configured' => (bool) $this->get('fb_pixel_id')],
                'fb_capi'            => ['enabled' => $this->pixelEnabled('fb_capi'),    'label' => 'Meta Conversions API',   'configured' => (bool) $this->get('fb_conversions_api_token')],
                'ga4'                => ['enabled' => $this->pixelEnabled('ga4'),        'label' => 'Google Analytics 4',      'configured' => (bool) $this->get('ga4_measurement_id')],
                'gtm'                => ['enabled' => $this->pixelEnabled('gtm'),        'label' => 'Google Tag Manager',      'configured' => (bool) $this->get('gtm_container_id')],
                'google_ads'         => ['enabled' => $this->pixelEnabled('google_ads'), 'label' => 'Google Ads',              'configured' => (bool) $this->get('google_ads_conversion_id')],
                'line_tag'           => ['enabled' => $this->pixelEnabled('line_tag'),   'label' => 'LINE Tag (LAP)',          'configured' => (bool) $this->get('line_tag_id')],
                'tiktok_pixel'       => ['enabled' => $this->pixelEnabled('tiktok'),     'label' => 'TikTok Pixel',            'configured' => (bool) $this->get('tiktok_pixel_id')],
                'utm_tracking'       => ['enabled' => $this->utmEnabled(),               'label' => 'UTM Tracking',            'configured' => true],
                'schema_markup'      => ['enabled' => $this->schemaEnabled(),            'label' => 'SEO Schema.org',          'configured' => true],
                'og_auto'            => ['enabled' => $this->ogEnabled(),                'label' => 'Open Graph Auto-tags',    'configured' => true],
                'line_messaging'     => ['enabled' => $this->lineMessagingEnabled(),     'label' => 'LINE Broadcast (OA)',     'configured' => (bool) $this->get('line_channel_access_token')],
                'line_notify'        => ['enabled' => $this->lineNotifyEnabled(),        'label' => 'LINE Notify',             'configured' => (bool) $this->get('line_notify_token')],
                'newsletter'         => ['enabled' => $this->newsletterEnabled(),        'label' => 'Newsletter',              'configured' => true],
                'email_campaigns'    => ['enabled' => $this->campaignsEnabled(),         'label' => 'Email Campaigns',         'configured' => (bool) $this->get('email_from_address')],
                'referral'           => ['enabled' => $this->referralEnabled(),          'label' => 'Referral Program',        'configured' => true],
                'loyalty'            => ['enabled' => $this->loyaltyEnabled(),           'label' => 'Loyalty Points',          'configured' => true],
                'landing_pages'      => ['enabled' => $this->landingPagesEnabled(),      'label' => 'Landing Pages',           'configured' => true],
                'push'               => ['enabled' => $this->pushEnabled(),              'label' => 'Web Push',                'configured' => (bool) $this->get('push_vapid_public')],
                'analytics'          => ['enabled' => $this->analyticsEnabled(),         'label' => 'Marketing Analytics',      'configured' => true],
            ],
        ];
    }

    /**
     * Group features by category for the Hub UI.
     */
    public function featureGroups(): array
    {
        return [
            'tracking' => [
                'label' => 'Tracking & Analytics',
                'icon'  => 'bi-activity',
                'color' => 'blue',
                'features' => ['fb_pixel', 'fb_capi', 'ga4', 'gtm', 'google_ads', 'line_tag', 'tiktok_pixel', 'utm_tracking'],
            ],
            'seo' => [
                'label' => 'SEO & Social',
                'icon'  => 'bi-globe',
                'color' => 'emerald',
                'features' => ['schema_markup', 'og_auto'],
            ],
            'line' => [
                'label' => 'LINE Marketing',
                'icon'  => 'bi-chat-dots',
                'color' => 'green',
                'features' => ['line_messaging', 'line_notify'],
            ],
            'email' => [
                'label' => 'Email & Newsletter',
                'icon'  => 'bi-envelope-paper',
                'color' => 'indigo',
                'features' => ['newsletter', 'email_campaigns'],
            ],
            'growth' => [
                'label' => 'Growth & Retention',
                'icon'  => 'bi-graph-up-arrow',
                'color' => 'violet',
                'features' => ['referral', 'loyalty', 'landing_pages', 'push'],
            ],
            'insights' => [
                'label' => 'Insights',
                'icon'  => 'bi-bar-chart',
                'color' => 'amber',
                'features' => ['analytics'],
            ],
        ];
    }
}
