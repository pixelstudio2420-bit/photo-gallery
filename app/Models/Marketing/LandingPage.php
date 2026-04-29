<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class LandingPage extends Model
{
    protected $table = 'marketing_landing_pages';

    protected $fillable = [
        'slug', 'title', 'subtitle', 'hero_image', 'theme',
        'cta_label', 'cta_url', 'sections', 'seo', 'utm_override',
        'status', 'campaign_id', 'views', 'conversions',
        'author_id', 'published_at',
    ];

    protected $casts = [
        'sections'     => 'array',
        'seo'          => 'array',
        'utm_override' => 'array',
        'published_at' => 'datetime',
        'views'        => 'integer',
        'conversions'  => 'integer',
    ];

    public const THEMES = [
        'indigo'  => ['from' => 'from-indigo-500', 'to' => 'to-purple-600'],
        'pink'    => ['from' => 'from-pink-500',   'to' => 'to-rose-600'],
        'emerald' => ['from' => 'from-emerald-500','to' => 'to-teal-600'],
        'amber'   => ['from' => 'from-amber-500',  'to' => 'to-orange-600'],
        'slate'   => ['from' => 'from-slate-700',  'to' => 'to-slate-900'],
    ];

    public function scopePublished($q)
    {
        return $q->where('status', 'published')->whereNotNull('published_at');
    }

    public function themeGradient(): string
    {
        $t = self::THEMES[$this->theme] ?? self::THEMES['indigo'];
        return "bg-gradient-to-br {$t['from']} {$t['to']}";
    }

    public function conversionRate(): float
    {
        if ($this->views <= 0) return 0.0;
        return round(($this->conversions / $this->views) * 100, 2);
    }

    public function publicUrl(): string
    {
        return url('/lp/' . $this->slug);
    }
}
