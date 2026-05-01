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
        // Customization fields (added 2026-05-01)
        'hero_image', 'theme', 'theme_color',
        'cta_text', 'cta_url', 'extra_sections',
        'show_gallery', 'show_related', 'show_stats', 'show_faq',
    ];

    protected $casts = [
        'source_meta'    => 'array',
        'schema_json'    => 'array',
        'extra_sections' => 'array',
        'is_locked'      => 'boolean',
        'is_published'   => 'boolean',
        'show_gallery'   => 'boolean',
        'show_related'   => 'boolean',
        'show_stats'     => 'boolean',
        'show_faq'       => 'boolean',
        'view_count'     => 'integer',
        'last_viewed_at' => 'datetime',
        'regenerated_at' => 'datetime',
    ];

    /**
     * Theme presets — drives the gradient, accent colour, and icon
     * choices on the rendered landing page. Maps to Tailwind utility
     * classes in the show.blade.php template.
     */
    public const THEMES = [
        'default'      => ['from' => 'from-indigo-500',  'via' => 'via-violet-500',  'to' => 'to-purple-600',  'accent' => 'indigo', 'icon' => 'bi-globe'],
        'wedding'      => ['from' => 'from-rose-400',    'via' => 'via-pink-500',    'to' => 'to-fuchsia-600', 'accent' => 'rose',   'icon' => 'bi-heart-fill'],
        'sport'        => ['from' => 'from-cyan-500',    'via' => 'via-blue-500',    'to' => 'to-indigo-600',  'accent' => 'blue',   'icon' => 'bi-trophy-fill'],
        'concert'      => ['from' => 'from-violet-600',  'via' => 'via-purple-600',  'to' => 'to-fuchsia-700', 'accent' => 'violet', 'icon' => 'bi-music-note'],
        'corporate'    => ['from' => 'from-slate-600',   'via' => 'via-slate-700',   'to' => 'to-zinc-800',    'accent' => 'slate',  'icon' => 'bi-building'],
        'portrait'     => ['from' => 'from-amber-500',   'via' => 'via-orange-500',  'to' => 'to-red-500',     'accent' => 'amber',  'icon' => 'bi-person-fill'],
        'festival'     => ['from' => 'from-yellow-400',  'via' => 'via-orange-500',  'to' => 'to-pink-500',    'accent' => 'orange', 'icon' => 'bi-stars'],
        'photography'  => ['from' => 'from-emerald-500', 'via' => 'via-teal-600',    'to' => 'to-cyan-700',    'accent' => 'emerald','icon' => 'bi-camera'],
    ];

    public function themeData(): array
    {
        return self::THEMES[$this->theme ?? 'default'] ?? self::THEMES['default'];
    }

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
