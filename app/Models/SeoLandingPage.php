<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * One pSEO-generated (or manually-created) landing page.
 *
 * Each row maps to ONE URL. Holds the resolved SEO meta and body so
 * page renders are a single indexed lookup. Admins can flip is_locked
 * to protect a hand-edited page from the regenerator stomping their
 * changes when source data updates.
 */
class SeoLandingPage extends Model
{
    protected $table = 'seo_landing_pages';

    protected $fillable = [
        'template_id', 'slug', 'type',
        'title', 'meta_description', 'h1', 'body_html',
        'og_image', 'og_title',
        'source_meta', 'is_locked', 'is_published',
        'schema_json', 'view_count', 'last_viewed_at',
        'regenerated_at',
    ];

    protected $casts = [
        'source_meta'    => 'array',
        'schema_json'    => 'array',
        'is_locked'      => 'boolean',
        'is_published'   => 'boolean',
        'view_count'     => 'integer',
        'last_viewed_at' => 'datetime',
        'regenerated_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(SeoPageTemplate::class, 'template_id');
    }

    /* ───────── Scopes ───────── */

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function scopeStale(Builder $q, int $hours = 168): Builder
    {
        // Pages whose last regeneration is older than $hours (default 7 days)
        // OR have never been regenerated. Drives the "stale pages" admin list.
        return $q->where(function ($w) use ($hours) {
            $w->whereNull('regenerated_at')
              ->orWhere('regenerated_at', '<', now()->subHours($hours));
        })->where('is_locked', false);
    }

    /* ───────── Helpers ───────── */

    public function url(): string
    {
        return url('/' . ltrim($this->slug, '/'));
    }
}
