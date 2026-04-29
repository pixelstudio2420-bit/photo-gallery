<?php

namespace App\Services;

use App\Models\PhotographerBranding;
use App\Models\PhotographerProfile;

/**
 * BrandingService
 * ───────────────
 * Resolves which "branding mode" a photographer has access to:
 *
 *   - Free / Starter / Pro → platform branding only (no custom)
 *   - Business             → custom_branding (logo, accent color, watermark)
 *   - Studio               → white_label (everything Business has + can hide
 *                            "Powered by …" footer + custom domain)
 *
 * The model PhotographerBranding stores the actual values; this service
 * gates which fields are EFFECTIVE based on the current plan, so a
 * Business-tier photographer who downgrades to Pro automatically loses
 * their custom branding without us having to delete the row.
 */
class BrandingService
{
    public function __construct(private SubscriptionService $subs) {}

    public function canCustomBrand(PhotographerProfile $profile): bool
    {
        return $this->subs->canAccessFeature($profile, 'custom_branding');
    }

    public function canWhiteLabel(PhotographerProfile $profile): bool
    {
        return $this->subs->canAccessFeature($profile, 'white_label');
    }

    public function settingsFor(PhotographerProfile $profile): PhotographerBranding
    {
        return PhotographerBranding::forPhotographer($profile->user_id);
    }

    /**
     * Effective branding values, scoped to the photographer's current plan.
     * Returns plain-array snapshot so views/middleware never need to check
     * plan tier themselves.
     *
     *   logo_path:     active iff custom_branding feature is enabled
     *   accent_hex:    same
     *   watermark:     same
     *   hide_credits:  active iff white_label feature is enabled (Studio)
     *   custom_domain: active iff white_label
     */
    public function effective(PhotographerProfile $profile): array
    {
        $b = $this->settingsFor($profile);
        $custom = $this->canCustomBrand($profile);
        $whitelabel = $this->canWhiteLabel($profile);

        return [
            'logo_path'             => $custom ? $b->logo_path : null,
            'accent_hex'            => $custom ? $b->accent_hex : null,
            'watermark_text'        => $custom ? $b->watermark_text : null,
            'watermark_enabled'     => $custom && (bool) $b->watermark_enabled,
            'hide_platform_credits' => $whitelabel && (bool) $b->hide_platform_credits,
            'custom_domain'         => $whitelabel ? $b->custom_domain : null,
            'is_custom'             => $custom,
            'is_white_label'        => $whitelabel,
        ];
    }
}
