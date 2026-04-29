<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCreative extends Model
{
    public const PLACEMENT_HOMEPAGE_BANNER = 'homepage_banner';
    public const PLACEMENT_SIDEBAR         = 'sidebar';
    public const PLACEMENT_SEARCH_INLINE   = 'search_inline';
    public const PLACEMENT_LANDING_NATIVE  = 'landing_native';

    protected $fillable = [
        'campaign_id', 'headline', 'body', 'image_url',
        'click_url', 'cta_label', 'placement', 'weight', 'is_active',
    ];

    protected $casts = [
        'weight'    => 'integer',
        'is_active' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
