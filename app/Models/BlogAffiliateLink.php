<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogAffiliateLink extends Model
{
    protected $table = 'blog_affiliate_links';

    protected $fillable = [
        'name', 'slug', 'destination_url', 'provider', 'campaign',
        'commission_rate', 'description', 'image', 'nofollow', 'is_active',
        'total_clicks', 'total_conversions', 'revenue', 'expires_at',
    ];

    protected $casts = [
        'commission_rate'   => 'decimal:2',
        'revenue'           => 'decimal:2',
        'nofollow'          => 'boolean',
        'is_active'         => 'boolean',
        'total_clicks'      => 'integer',
        'total_conversions' => 'integer',
        'expires_at'        => 'datetime',
    ];

    /**
     * Remove the affiliate image from disk when the link row is deleted.
     * BlogAffiliateController::destroy() already does this explicitly for
     * UI-driven deletes; the hook is a safety net for cascade deletes, bulk
     * operations, and tinker sessions so no orphaned image ever survives.
     *
     * Routes through StorageManager::deleteAsset so the sweep covers R2 /
     * S3 / public — the legacy public-disk-only call would leak cloud
     * copies once affiliate images started landing on R2.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $link) {
            if ($link->image) {
                try {
                    app(\App\Services\StorageManager::class)->deleteAsset($link->image);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        "BlogAffiliateLink#{$link->id} image delete failed: " . $e->getMessage()
                    );
                }
            }
        });
    }

    /* ──────────────────────────── Relationships ──────────────────────────── */

    public function clicks()
    {
        return $this->hasMany(BlogAffiliateClick::class, 'affiliate_link_id');
    }

    public function ctaButtons()
    {
        return $this->hasMany(BlogCtaButton::class, 'affiliate_link_id');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }

    /* ──────────────────────────── Methods ──────────────────────────── */

    public function getCloakedUrl(): string
    {
        return url('/go/' . $this->slug);
    }

    public function trackClick(array $data = []): BlogAffiliateClick
    {
        $this->increment('total_clicks');

        return $this->clicks()->create(array_merge([
            'clicked_at' => now(),
        ], $data));
    }
}
