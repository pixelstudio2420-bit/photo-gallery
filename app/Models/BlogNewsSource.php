<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogNewsSource extends Model
{
    protected $table = 'blog_news_sources';

    protected $fillable = [
        'name', 'url', 'feed_url', 'feed_type', 'category_id',
        'language', 'is_active', 'auto_publish', 'fetch_interval_hours',
        'last_fetched_at', 'total_items_fetched',
    ];

    protected $casts = [
        'category_id'         => 'integer',
        'is_active'           => 'boolean',
        'auto_publish'        => 'boolean',
        'fetch_interval_hours' => 'integer',
        'total_items_fetched' => 'integer',
        'last_fetched_at'     => 'datetime',
    ];

    /* ──────────────────────────── Relationships ──────────────────────────── */

    public function items()
    {
        return $this->hasMany(BlogNewsItem::class, 'source_id');
    }

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->whereNull('last_fetched_at')
                           ->orWhereRaw("last_fetched_at <= NOW() - (fetch_interval_hours || ' hours')::interval");
                     });
    }
}
