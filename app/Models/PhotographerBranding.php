<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One-row-per-photographer branding settings.
 *
 * Read by public event/portfolio pages to override the platform's
 * default header/footer/colors when the photographer is on Business+
 * (custom_branding) or Studio (white_label/hide_platform_credits).
 *
 * The gating logic lives in BrandingService; this model is just storage.
 */
class PhotographerBranding extends Model
{
    protected $table = 'photographer_branding';

    protected $fillable = [
        'photographer_id',
        'logo_path',
        'accent_hex',
        'watermark_text',
        'watermark_enabled',
        'hide_platform_credits',
        'custom_domain',
        'extra',
    ];

    protected $casts = [
        'watermark_enabled'      => 'boolean',
        'hide_platform_credits'  => 'boolean',
        'extra'                  => 'array',
    ];

    public static function forPhotographer(int $photographerId): self
    {
        return static::firstOrNew(['photographer_id' => $photographerId]);
    }
}
