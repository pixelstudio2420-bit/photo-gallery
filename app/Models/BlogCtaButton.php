<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogCtaButton extends Model
{
    protected $table = 'blog_cta_buttons';

    protected $fillable = [
        'name', 'label', 'sub_label', 'icon', 'style', 'url',
        'affiliate_link_id', 'position', 'show_after_paragraph',
        'variant', 'impressions', 'clicks', 'is_active', 'display_conditions',
    ];

    protected $casts = [
        'affiliate_link_id'  => 'integer',
        'show_after_paragraph' => 'integer',
        'impressions'        => 'integer',
        'clicks'             => 'integer',
        'is_active'          => 'boolean',
        'display_conditions' => 'array',
    ];

    /* ──────────────────────────── Relationships ──────────────────────────── */

    public function affiliateLink()
    {
        return $this->belongsTo(BlogAffiliateLink::class, 'affiliate_link_id');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPosition($query, string $position)
    {
        return $query->where('position', $position);
    }

    /* ──────────────────────────── Methods ──────────────────────────── */

    public function trackImpression(): void
    {
        $this->increment('impressions');
    }

    public function trackClick(): void
    {
        $this->increment('clicks');
    }

    public function getCtr(): float
    {
        if ($this->impressions === 0) {
            return 0.0;
        }

        return round(($this->clicks / $this->impressions) * 100, 2);
    }
}
